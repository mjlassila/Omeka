<?php
/**
 * @copyright Roy Rosenzweig Center for History and New Media, 2009
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @access private
 */

/**
 * Retrieve the list of all available metadata for a specific file.
 *
 * @internal This implements Omeka internals and is not part of the public API.
 * @access private
 * @package Omeka
 * @copyright Roy Rosenzweig Center for History and New Media, 2009
 */
class Omeka_View_Helper_FileMetadataList extends Omeka_View_Helper_RecordMetadataList
{
    
    public function fileMetadataList(File $file, array $options = array())
    {
        return $this->_getList($file, $options);
    }
    
    protected function _loadViewPartial($vars = array())
    {
        return common('item-metadata', $vars, 'items');
    }
    
    protected function _getFormattedElementText($record, $elementSetName, $elementName)
    {
        return $this->view->fileMetadata($record, $elementSetName, $elementName, 'all');
    }
}
