<?php
    //! @class ArchiveTransfer
    //! @brief this class attempts to handle archiving if ZipArchive is installed
    
    class LSDArchive extends ZipArchive { 
        
        function addDir($filename, $localname, $ignore_list) { 
            if(strlen($localname)) {
                $this->addEmptyDir($localname); 
                $localname .= '/';
            }
            $dirh = opendir($filename);
            
            $count = 0;
            while ($file = readdir($dirh)) : 
                if($file == '.' || $file == '..' || $file == 'lsd_dump.zip' || $file == 'lsd_dump.sql') continue;
                if(array_search($file, $ignore_list) !== false || array_search($filename.$file, $ignore_list) !== false || array_search($filename.$file."/", $ignore_list) !== false) continue;
                
                $method = !is_dir($filename.$file."/") ? 'addFile' : 'addDir'; 
                $fullfilename = !is_dir($filename.$file."/") ? $filename.$file : $filename.$file."/"; 
                
                if($method == 'addDir') {
                    $count += $this->$method($fullfilename, $localname . $file, $ignore_list);
                } else {
                    $return = $this->$method($fullfilename, $localname . $file); 
                    ++$count;
                }
            endwhile;
            return $count;
        } 
    } 
    
    class ArchiveTransfer {
        function dump($ignore_list) {
            $filename = LSD_DIR.'/lsd_dump.zip';
            $archive = new LSDArchive;
            if ($archive->open($filename,ZIPARCHIVE::OVERWRITE) !== TRUE) return -1;
            $count = $archive->addDir(ABSPATH, '',$ignore_list);
            $archive->close();
            return $count;
        }
        
        // file that deletes a directory or just its contents
        function rmdir($dir, $just_contents = false) { 
            if (is_dir($dir)) { 
                $objects = scandir($dir); 
                foreach ($objects as $object) { 
                    if ($object != "." && $object != "..") { 
                        if (filetype($dir."/".$object) == "dir") $this->rmdir($dir."/".$object); else unlink($dir."/".$object); 
                    } 
                } 
                reset($objects); 
                if(!$just_contents) rmdir($dir); 
           } 
        }
        
        function import($root = null) {
            $filename = LSD_DIR.'/lsd_dump.zip';
            $folder = $root ? $root : LSD_DIR.'/test';
            if(file_exists($folder))
                $this->rmdir($folder, true);
            else
                mkdir($folder);
            $archive = new LSDArchive;
            if ($archive->open($filename) !== TRUE) return 1;
            $archive->extractTo($folder);
            $archive->close();
            return 2;
        }
    }
?>
