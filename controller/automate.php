<?php
    function liveServerDeploy_update_status($message, $type = null, $rel = null) {
        
        $var = 'status_'.$type;
        global $$var;
        
        $output = true;
        
        if($rel) {
            ?>
            <script>
                var step_status = jQuery('#liveServerDeploySteps #<?php echo $rel; ?>');
                step_status.css('font-weight','bold');
                <?php
                    if($type == 'success') {
                ?>
                        step_status.css('color','green');
                        step_status.html(step_status.html()+" &#10004;");
                <?php
                    } else if($type == 'error') {
                ?>
                        step_status.css('color','red');
                <?php
                    } else if ($type == 'warning' || $type == 'notice') {
                ?>
                        step_status.css('color','orange');
                <?php
                    }
                ?>
            </script>
            <?php
            if(!$message) $output = false;
        }
        
        switch($type) :
            case 'success':
            case 'error':
            case 'warning':
                $color = '';
                if($type == 'success')
                    $color = 'green';
                if($type == 'error')
                    $color = 'red';
                if($type == 'warning')
                    $color = 'orange';
                if(!$message) break;
            ?>
            <div class='liveServerDeploy_status' id='liveServerDeploy_status_<?php echo $type; ?>' style='color:<?php echo $color; ?>;font-weight:bold'>
                <?php echo $message."<BR>"; ?>
            </div>
            <?php
                break;
            case 'progress':
                if($$var) :
                ?>
                <script>
                    jQuery('#liveServerDeploy_status_<?php echo $type; ?>').html("<?php echo $message; ?>");
                </script>
                <?php
                else:
                ?>
            <div class='liveServerDeploy_status' id='liveServerDeploy_status_<?php echo $type; ?>'>
                <?php echo $message."<BR>"; ?>
            </div>
                <?php
                endif;
                break;
            default:
                if($message)
                ?>
            <div class='liveServerDeploy_status' id='liveServerDeploy_status_<?php echo $type; ?>'>
                <?php echo $message."<BR>"; ?>
            </div>
                <?php
                break;
        endswitch;
        
        ob_flush();
        flush();
        $$var = true;
    }
    
    function liveServerDeploy_get_contents($url) {
        if(!function_exists('curl_init') && ini_get('allow_url_fopen'))
            throw "allow_url_fopen disabled and cURL extensions not installed";
        if(ini_get('allow_url_fopen')) :
            return file_get_contents($url);
        else:
            $ch = @curl_init ($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
            $rawdata=curl_exec($ch);
            curl_close ($ch);
            return $rawdata;
        endif;
    }
    
    function liveServerDeploy_new_config($db_name, $db_username, $db_password, $db_host) {
        $config = file_get_contents(ABSPATH."/wp-config.php");
        $lines = explode("\n", $config);
        $db_host = $db_host?$db_host:"localhost";
        foreach($lines as $k => $line) {
            if(strpos($line, 'DB_NAME') && strpos($line, 'define') !== false)
                $lines[$k] = "define('DB_NAME', '$db_name');";
            if(strpos($line, 'DB_USER') && strpos($line, 'define') !== false)
                $lines[$k] = "define('DB_USER', '$db_username');";
            if(strpos($line, 'DB_PASSWORD') && strpos($line, 'define') !== false)
                $lines[$k] = "define('DB_PASSWORD', '$db_password');";
            if(strpos($line, 'DB_HOST') && strpos($line, 'define') !== false)
                $lines[$k] = "define('DB_HOST', '$db_host');";
        }
        return implode("\n", $lines);
    }
    
    // /\((["'])([^"']+)[^\\]\1,?.*?\)/
    function liveServerDeploy_parse_ignore_list($list) {
        preg_match_all('/".*?"/', $list, $matches);
        $quote_list = current($matches);
        $list = str_replace($quote_list, "", $list);
        $list = explode(",", $list);
        foreach($list as $k => $v) {
            $list[$k] = trim($v);
            if(!strlen($list[$k]))
                unset($list[$k]);
        }
        foreach($quote_list as $v) {
            $list[] = substr($v, 1, -1);
        }
        return $list;
    }

    function liveServerDeploy_automate($settings) {
        extract($settings);
        
        if($ftp_server && $ftp_username && $ftp_pass && $db_name && $db_username && $db_password)
            liveServerDeploy_update_status(null, 'success', '1');
        else {
            liveServerDeploy_update_status("Please provide your Live Server Settings", 'error', '1');
            return;
        }
    
        liveServerDeploy_update_status(null, 'warning', '2');
    
        liveServerDeploy_update_status("Start Deploying ...");
        liveServerDeploy_update_status("Connecting to ftp server ...");
        
        $root = $ftp_root;
        
        // connect to server
        $ftph = @ftp_connect($ftp_server, $ftp_port?"$ftp_port":21);
        
        if($ftph === FALSE) {
            liveServerDeploy_update_status("Failed to connected to the ftp server", 'error');
            liveServerDeploy_update_status("Failed to Deploy", 'error');
            return;
        } else
            liveServerDeploy_update_status("Succesfully connected to the ftp server!", 'success');
        
        // login to server
        if(!ftp_login($ftph, $ftp_username, $ftp_pass)) {
            liveServerDeploy_update_status("Failed to login to ftp server ...", 'error');
            ftp_close($ftph);
            return;
        }
        
        if(!@ftp_put($ftph, $root."/capability.php", dirname(__FILE__).'/remote-capabilities.php',FTP_BINARY)) {
            liveServerDeploy_update_status("Failed to determine remote capabilities ...", 'error', 2);
            ftp_close($ftph);
            return;
        }
        
        $capabilities = @liveServerDeploy_get_contents($site_url.'/capability.php');
        if(!$capabilities) {
            liveServerDeploy_update_status("Failed to determine remote capabilities!", 'error', 2);
            ftp_close($ftph);
            return;
        }
        
        // clean up
        @ftp_delete($ftph, $root."/capability.php");
        
        // get capabilities
        $capabilities = unserialize($capabilities);
        $archive = ($capabilities['archiving'] && LSD_ARCHIVING_ENABLED);
        $mysql_exec = ($capabilities['mysql_exec'] && LSD_MYSQL_DUMP_USE_EXEC);
        $mysql_dump = true;
        
        if($archive)
            liveServerDeploy_update_status("Archiving enabled", 'success', 2);
        else
            liveServerDeploy_update_status("Archiving disabled", 'warning', 2);
        
        if($mysql_exec)
            liveServerDeploy_update_status("System mysql dump/import enabled", 'success', 2);
        else {
        
            if(LSD_MYSQL_DUMP_USE_EXEC && !LSD_MYSQL_DUMP_FALLBACKS) {
                liveServerDeploy_update_status("Live server does not support executing system mysql import - you must manually install the MySQL export on your server. See \"Manual Export\" section.", 'error', 2);
                $mysql_dump = false;
            } else
                liveServerDeploy_update_status("Live Server Deploy mysql dump/import enabled", 'warning', 2);
        }
            
        liveServerDeploy_update_status("Preparing Export ...", 'notice', 3);
        
        $ignore_list = liveServerDeploy_parse_ignore_list($ignore_list);
        if($archive) {
            $archivedump = new ArchiveTransfer;        
            $count = $archivedump->dump($ignore_list);
            if ($count < 0) {
                liveServerDeploy_update_status("Failed to archive files!", 'error');
                $archive = false;
            } else {
                liveServerDeploy_update_status("Archived $count Files ...");
            }
            unset($archivedump);
        }
        
        if($mysql_dump) {
            $mysqldump = new MySQLTransfer(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if($ret = $mysqldump->dump(get_option('siteurl'),$site_url,$mysql_exec)) 
                liveServerDeploy_update_status("Failed to export MySQL database - you must manually install the MySQL export on your server. See \"Manual Export\" section.", 'error', 2);
            else
                liveServerDeploy_update_status("Export MySQL database ...");
            unset($mysqldump);
        }
        
        liveServerDeploy_update_status(null, 'success', 3);

        if($archive) {
            liveServerDeploy_update_status("Uploading compressed archive to server - this might take a while ...");
            ini_set("max_execution_time", "0");
            if(!@ftp_put($ftph, $root."/lsd_dump.zip", LSD_DIR.'/lsd_dump.zip',FTP_BINARY)) {
                liveServerDeploy_update_status("Failed to upload compressed archive ...", 'error', 4);
                ftp_close($ftph);
                $archive = false;
            } else
                liveServerDeploy_update_status("Successfully Uploaded Archive to Server!", 'success', 4);
        } 
        
        if(!$archive) {            
            liveServerDeploy_update_status("Opening additional ftp connections ...", 'notice', 4);
            $ftp_bulk = new FTPBulkTransfer($ftp_server,$ftp_port,$ftp_username,$ftp_pass);
            if($ftp_bulk->is_open())
                liveServerDeploy_update_status("Opened ".$ftp_bulk->connection_count()." ftp connections to server ...", 'notice', 4);
                
            global $liveServerDeploy_file_count;
            
            $liveServerDeploy_file_count = $count = liveServerDeploy_upload_dir($ftph, $ftp_bulk, ABSPATH, '', $root, $counting = true, 0, $ignore_list);
            liveServerDeploy_update_status("Uploading $count files to server - this might take a while ...", null, 4);
            
            ini_set("max_execution_time", "0");   
            if($ftp_bulk->is_open())
                while($ret = $ftp_bulk->poll())
                    ftp_pwd($ftph);   //  keep connection open
            else
                $count = liveServerDeploy_upload_dir($ftph, null, ABSPATH, '', $root,$count,$count, $ignore_list);
            liveServerDeploy_update_status($count." files uploaded ...", 'success');
            
            // free up ftp connections
            $ftp_bulk->close();
        }
        
        liveServerDeploy_update_status("Uploading mysql dump ..");
        
        if($mysql_dump) {
            if(!@ftp_put($ftph, $root."/lsd_dump.sql", LSD_DIR.'/lsd_dump.sql',FTP_BINARY)) {
                liveServerDeploy_update_status("Failed to upload mysql dump ...", 'error', 4);
                ftp_close($ftph);
                $archive = false;
            } else
                liveServerDeploy_update_status("Successfully uploaded MySQL dump to server!", 'success');
        }
        
        liveServerDeploy_update_status(null, 'success', 4);
        
        // send scripts that will take care of extracting the archive (TODO: and importing the mysql)
        
        liveServerDeploy_update_status("Prepare importing ...", 'notice', 5);
        if($archive) {
            if(!@ftp_put($ftph, $root."/decompress.php", dirname(__FILE__).'/remote-decompress.php',FTP_BINARY)) {
                liveServerDeploy_update_status("Failed to decompress archive on remote!", 'error', 5);
                ftp_close($ftph);
                return;
            }
            
            $decompress = @liveServerDeploy_get_contents($site_url.'/decompress.php');
            if(!$decompress) {
                liveServerDeploy_update_status("Failed to decompress archive on remote!", 'error', 5);
                ftp_close($ftph);
                return;
            }
            
            // clean up
            @ftp_delete($ftph, $root."/decompress.php");
            liveServerDeploy_update_status("Successfully decompressed archive, your files are ready!", 'success');
        }
        
        if($mysql_dump) {
            if(!@ftp_put($ftph, $root."/import_mysql.php", dirname(__FILE__).'/remote-mysql.php',FTP_BINARY)) {
                liveServerDeploy_update_status("Failed to import mysql database on remote - you must manually install the MySQL export on your server. See \"Manual Export\" section.", 'error', 5);
                ftp_close($ftph);
                return;
            }
            
            $get = 'mysql_exec='.$mysql_exec;
            $get .= '&db_host='.urlencode($db_host?$db_host:'localhost');
            $get .= '&db_name='.urlencode($db_name);
            $get .= '&db_username='.urlencode($db_username);
            $get .= '&db_password='.urlencode($db_password);
            
            $import_mysql = @liveServerDeploy_get_contents($site_url.'/import_mysql.php?'.$get);
            if($import_mysql) {
                liveServerDeploy_update_status("Failed to import mysql dump on remote - you must manually install the MySQL export on your server. See \"Manual Export\" section.", 'error', 5);
                ftp_close($ftph);
                var_dump($import_mysql);
                return;
            }
            
            // clean up
            @ftp_delete($ftph, $root."/import_mysql.php");
            @ftp_delete($ftph, $root."/lsd_dump.sql");
            liveServerDeploy_update_status("Successfully imported database!", 'success');
        }
        
        liveServerDeploy_update_status(null, 'success', 5);
        liveServerDeploy_update_status(null, 'notice', 6);
            
        // finalize by editing the wp-config.php
        
        @ftp_rename($ftph, $root."/wp-config.php", $root."/wp-config.development.php");
        file_put_contents(ABSPATH."/wp-config.lsd-live.php",liveServerDeploy_new_config($db_name, $db_username, $db_password, $db_host));
        if(!@ftp_put($ftph, $root."/wp-config.php", ABSPATH."/wp-config.lsd-live.php",FTP_BINARY)) {
            liveServerDeploy_update_status("Unable to upload edit wp-config.php file, please update manually", 'notice', 5);
            ftp_close($ftph);
            unlink(ABSPATH."/wp-config.lsd-live.php");
            return;
        }
        unlink(ABSPATH."/wp-config.lsd-live.php");
            
        ftp_close($ftph);
        ?>
        <script>
            jQuery('.liveServerDeploy_status').remove();
        </script>
        <?php
        liveServerDeploy_update_status("Successfully Deployed", 'success', 6);
    }
    
    // credits: http://sameerparwani.com/posts/recursive-ftp-make-directory-mkdir 
    function liveServerDeploy_mkdir($ftp_stream, $dir) {
	    if (liveServerDeploy_ftp_is_dir($ftp_stream, $dir) || @ftp_mkdir($ftp_stream, $dir)) return true;
	    if (!liveServerDeploy_mkdir($ftp_stream, dirname($dir))) return false;
	    return @ftp_mkdir($ftp_stream, $dir);
    }	

    function liveServerDeploy_ftp_is_dir($ftp_stream, $dir) {
       $original_directory = @ftp_pwd($ftp_stream);
       if ( @ftp_chdir( $ftp_stream, $dir ) ) {
	       @ftp_chdir( $ftp_stream, $original_directory );
	       return true;
       }
	   return false;
    }
    
    function liveServerDeploy_upload_success($transfer, $ftp_bulk) {
        global $liveServerDeploy_file_count;
        if($liveServerDeploy_file_count - $ftp_bulk->transfer_count())
            $percent = (int) (($liveServerDeploy_file_count - $ftp_bulk->transfer_count()) / $liveServerDeploy_file_count * 100);
        else 
            $percent = "0";
        liveServerDeploy_update_status("<strong>[$percent%]</strong> Uploaded file: ".$transfer['remote'], 'progress');
    }
    
    function liveServerDeploy_upload_fail($transfer, $ftp_bulk) {
        liveServerDeploy_update_status("Failed to upload file: ".$transfer['remote'], 'error');
    }
    
    function liveServerDeploy_upload_dir($ftph, $ftp_bulk, $filename, $localname, $root, &$counting, $total = 0, $ignore_list = array()) {
        if(strlen($localname)) {
            if($counting !== true) :
                if($total - $counting)
                    $percent = (int) (($total - $counting) / $total * 100);
                else 
                    $percent = "0";
                if(!@liveServerDeploy_mkdir($ftph, $root.'/'.$localname))
                    liveServerDeploy_update_status("Failed to Add Directory: ".$root.'/'.$localname, 'error', 'progress');
                else
                    liveServerDeploy_update_status("<strong>[$percent%]</strong> Added Directory: ".$root.'/'.$localname, 'progress');
            endif;
            $localname .= '/';
        }
        
        $dirh = opendir($filename);
        
        $count = 0;
        while ($file = readdir($dirh)) : 
            if($file == '.' || $file == '..' || $file == 'lsd_dump.zip' || $file == 'lsd_dump.sql') continue;
            if(array_search($file, $ignore_list) !== false || array_search($filename.$file, $ignore_list) !== false || array_search($filename.$file."/", $ignore_list) !== false) {
                continue;
            }
            
            if(is_dir($filename.$file."/")) {
                $count += liveServerDeploy_upload_dir($ftph, $ftp_bulk, $filename.$file."/", $localname . $file, $root, $counting, $total, $ignore_list);
            } else {
                // upload file
                ++$count;
                if($ftp_bulk && $ftp_bulk->is_open())
                    $ftp_bulk->put($root.'/'.$localname.$file, $filename.$file, 'liveServerDeploy_upload_fail', 'liveServerDeploy_upload_success');
                    
                if(!$ftp_bulk->is_open() && $counting !== true) :
                    --$counting;
                    if($total - $counting)
                        $percent = (int) (($total - $counting) / $total * 100);
                    else 
                        $percent = "0";
                    
                    if(!@ftp_put($ftph, $root.'/'.$localname.$file, $filename.$file,FTP_BINARY)) {
                        liveServerDeploy_update_status("Failed to upload file: ".$root.'/'.$localname. $file, 'error');
                    } else
                        liveServerDeploy_update_status("<strong>[$percent%]</strong> Uploaded file: ".$root.'/'.$localname. $file, 'progress');
                endif;
            }
        endwhile;
        return $count;
    }
?>
