<?php

class removeFileInfo extends Omeka_Db_Migration
{
    public function up()
    {
        $db = $this->getDb();

        $db->query("ALTER TABLE `{$db->File}` ADD `metadata` TEXT NULL ");
        $db->query("DROP TABLE IF EXISTS `{$db->MimeElementSetLookup}`");
        
        //remove Omeka File Image adn Omeka File Video element sets, elements, and texts
        $imageElements = $db->getTable('Element')->findBySet('Omeka Image File');
        $videoElements = $db->getTable('Element')->findBySet('Omeka Video File');
        
        foreach($imageElements as $element) {
            $element->delete();
        }
        foreach($videoElements as $element) {
            $element->delete();
        }
        $imageElementSet = $db->getTable('ElementSet')->findByName('Omeka Image File');
        $videoElementSet = $db->getTable('ElementSet')->findByName('Omeka Video File');
        $imageElementSet->delete();
        $videoElementSet->delete();
        
        //go through all the existing files and redo grabbing the metadata with the new process
        $files = $db->getTable('File')->findAll();
        foreach($files as $file) {
            $file->extractMetadata();
            $file->save();
            release_object($file);
        }
        
    }

    public function down()
    {
        $db = $this->getDb();

        $db->query("ALTER TABLE `{$db->File}` DROP `metadata`");
    }
    
    
    
}