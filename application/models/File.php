<?php
/**
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @subpackage Models
 * @author CHNM
 */

/**
 * Defines mime_content_type() if it is not available in the current
 * installation environment.
 */
if (!function_exists('mime_content_type')) {
   function mime_content_type($f) {
       return trim(exec('file -bi ' . escapeshellarg ($f))) ;
   }
}

/**
 * Represents a file and its metadata.
 *
 * @package Omeka
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2010
 */
class File extends Omeka_Record
{
    const DISABLE_DEFAULT_VALIDATION_OPTION = 'disable_default_file_validation';

    public $item_id;
    public $archive_filename;
    public $original_filename;
    public $size = '0';
    public $authentication;
    public $mime_browser;
    public $mime_os;
    public $type_os;
    public $has_derivative_image = '0';
    public $added;
    public $modified;
    public $stored = '0';
    public $metadata = '';
    public $id;
    
    /**
     * List of MIME types that could be considered ambiguous.
     *
     * @see Omeka_File::_mimeTypeIsAmbiguous()
     * @var array
     */
    protected $_ambiguousMimeTypes = array(
        'text/plain',
        'application/octet-stream',
        'regular file');

    static private $_pathsByType = array(
        'archive' => 'files',
        'fullsize' => 'fullsize',
        'thumbnail' => 'thumbnails',
        'square_thumbnail' => 'square_thumbnails'
    );

    protected function _initializeMixins()
    {
        $this->_mixins[] = new ActsAsElementText($this);
    }

    protected function beforeInsert()
    {
        $now = Zend_Date::now()->toString(self::DATE_FORMAT);
        $this->added = $now;
        $this->modified = $now;
        $this->metadata = serialize($this->metadata);
        $this->setMimeTypeIfAmbiguous();
    }

    protected function afterInsert()
    {
        $dispatcher = Zend_Registry::get('job_dispatcher');
        $dispatcher->setQueueName('uploads');
        $dispatcher->send('File_ProcessUploadJob',
                          array('fileId' => $this->id));
    }
    
    protected function beforeUpdate()
    {
        $this->metadata = serialize($this->metadata);
        $this->modified = Zend_Date::now()->toString(self::DATE_FORMAT);
    }
    
    protected function filterInput($post)
    {
        $immutable = array('id', 'modified', 'added',
                           'authentication', 'archive_filename',
                           'original_filename', 'mime_browser',
                           'mime_os', 'type_os', 'item_id');
        foreach ($immutable as $value) {
            unset($post[$value]);
        }
        return $post;
    }
    
    protected function beforeSaveForm($post)
    {
        $this->beforeSaveElements($post);
    }
        
    public function getItem()
    {
        return $this->getTable('Item')->find($this->item_id);
    }
    
    /**
     * Retrieve the path for the file
     *
     * @return string
     */
    public function getPath($type='fullsize')
    {
        $fn = $this->getDerivativeFilename();

        if ($this->stored) {
            throw new Exception('Cannot get the local path for a stored file.');
        }

        $dir = $this->getStorage()->getTempDir();
        
        if ($type == 'archive') {
            return $dir . '/' . $this->archive_filename;
        } else {
            return $dir . "/{$type}_{$fn}";
        }
    }
    
    /**
     * Retrieve the web path for the file
     *
     * @return void
     */
    public function getWebPath($type='fullsize')
    {
        return $this->getStorage()->getUri($this->getStoragePath($type));
    }
    
    public function getDerivativeFilename()
    {
        list($base, $ext) = explode('.', $this->archive_filename);
        $fn = $base . '.' . Omeka_File_Derivative_Image::DERIVATIVE_EXT;
        return $fn;
    }
    
    public function hasThumbnail()
    {
        return $this->has_derivative_image;
    }
    
    public function hasFullsize()
    {
        return $this->has_derivative_image;
    }
    
    /**
     * Set the default values that will be stored for this file in the 'files' table.
     *
     * These values include 'size', 'authentication', 'mime_browser', 'mime_os', 'type_os'
     * and 'archive_filename.
     *
     * @param string
     * @return void
     */
    public function setDefaults($filepath, array $options = array())
    {
        $this->size = filesize($filepath);
        $this->authentication = md5_file($filepath);
        
        $this->setMimeType(mime_content_type($filepath));
        
        $this->mime_os      = trim(exec('file -ib ' . trim(escapeshellarg($filepath))));
        $this->type_os      = trim(exec('file -b ' . trim(escapeshellarg($filepath))));
        
        $this->archive_filename = basename($filepath);
    }
        
    public function getMimeTypeElements($mimeType = null)
    {
        if (!$mimeType) {
            $mimeType = $this->getMimeType();
        }
        
        return $this->getTable('Element')->findForFilesByMimeType($mimeType);
    }
    
    /**
     * Retrieve the definitive MIME type for this file.
     *
     * @param string
     * @return string
     */
    public function getMimeType()
    {
        return $this->mime_browser;
    }
    
    /**
     * @internal Seems kind of arbitrary that 'mime_browser' contains the
     * definitive MIME type, but at least we can abstract it so that it's
     * easier to change later if necessary.
     *
     * @param string
     * @return void
     */
    public function setMimeType($mimeType)
    {
        $this->mime_browser = $this->_filterMimeType($mimeType);
    }
    
    /**
     * Filters the mime type.  In particular, it removes the charset information.
     *
     * @param string $mimeType The raw mime type
     * @return string Filtered mime type.
     */
    protected function _filterMimeType($mimeType)
    {
        $mimeTypeParts = explode(';', $mimeType);
        return trim($mimeTypeParts[0]);
    }
    
    public function unlinkFile()
    {
        $storage = $this->getStorage();

        $files = array($this->getStoragePath('archive'));

        if ($this->has_derivative_image) {
            $types = self::$_pathsByType;
            unset($types['archive']);

            foreach($types as $type => $path) {
                $files[] = $this->getStoragePath($type);
            }
        }
        
        foreach($files as $file) {
            $storage->delete($file);
        }
    }
    
    protected function _delete()
    {
        $this->unlinkFile();
        $this->deleteElementTexts();
    }
    
    public function createDerivatives()
    {
        $pathToOriginalFile = $this->getPath('archive');
        
        // Create derivative images if possible.
        if (Omeka_File_Derivative_Image::createAll($pathToOriginalFile,
                                                   $this->getMimeType())) {
            $this->has_derivative_image = 1;
            $this->save();
        }
    }
    
    /**
     * Extract metadata associated with the file.
     *
     * @return boolean
     */
    public function extractMetadata()
    {

        if (!is_readable($this->getPath('archive'))) {
            throw new Exception('Could not extract metadata: unable to read file at the following path: "' . $this->_filePath . '"');
        }

        // Skip if getid3 did not return a valid object.
        if (!$id3 = $this->_getId3()) {
            return false;
        }
        $this->metadata = $id3->info;
        return true;

    }
    /**
     * References a list of ambiguous mime types from "http://msdn2.microsoft.com/en-us/library/ms775147.aspx".
     *
     * @param string $mimeType
     * @return boolean
     */
    public function isAmbiguousMimeType($mimeType)
    {
        return (empty($mimeType) || in_array($mimeType, $this->_ambiguousMimeTypes));
    }

    /**
     * Sets the MIME type for the file to the one detected by getID3, but only
     * if the existing MIME type is 'ambiguous' and getID3 can detect a better
     * one.
     *
     * @uses Omeka_File::isAmbiguousMimeType()
     */
    public function setMimeTypeIfAmbiguous()
    {
        $mimeType = $this->getMimeType();
        if ($this->isAmbiguousMimeType($mimeType)) {
            // WARNING: this may cause a memory error on large files.
            if ($id3 = $this->_getId3()) {
                $mimeType = $id3->info['mime_type'];
            }
            if ($mimeType) {
                $this->setMimeType($mimeType);
            }
        }
    }
    public function storeFiles()
    {
        $storage = $this->getStorage();

        $archiveFilename = $this->archive_filename;
        $derivativeFilename = $this->getDerivativeFilename();
        
        $storage->store($this->getPath('archive'), $this->getStoragePath('archive'));
                
        if ($this->has_derivative_image) {
            $types = array_keys(self::$_pathsByType);

            foreach ($types as $type) {
                if ($type != 'archive') {
                    $storage->store($this->getPath($type), $this->getStoragePath($type));
                }
            }
        }
        $this->stored = '1';
        $this->save();
    }

    public function getStoragePath($type = 'fullsize')
    {
        $storage = $this->getStorage();
        
        if ($type == 'archive') {
            $fn = $this->archive_filename;
        } else {
            $fn = $this->getDerivativeFilename();
        }

        return $storage->getPathByType($fn, self::$_pathsByType[$type]);
    }

    public function setStorage($storage)
    {
        $this->_storage = $storage;
    }

    public function getStorage()
    {
        if (!$this->_storage) {
            $this->_storage = Zend_Registry::get('storage');
        }

        return $this->_storage;
    }
    
    /**
     * Pull down the file's extra metadata via getID3 library.
     *
     * @param string $path Path to file.
     * @return getID3
     */
    private function _getId3()
    {
        // Do not extract metadata if the exif module is not loaded. This
        // applies to all files, not just files with Exif data -- i.e. images.
        if (!extension_loaded('exif')) {
            return false;
        }
        
        require_once 'getid3/getid3.php';
        $id3 = new getID3;
        $id3->encoding = 'UTF-8';
        
        try {
            $id3->Analyze($this->getPath('archive'));
            $this->_id3 = $id3;
        } catch (getid3_exception $e) {
            $message = $e->getMessage();
            _log("getID3: $message");
            return false;
        }

        return $id3;
    }
}
