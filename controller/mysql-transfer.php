<?php
    //! @class MySQLTransfer
    //! @brief this class handles dumping mysql and creating a php script which will handle the SQL importing and deleting itself
    
    class MySQLTransfer {
        var $db_connection;
        var $db_host;
        var $db_user;
        var $db_password;
        var $db_name;
        var $last_result;
        
        function MySQLTransfer($db_host = DB_HOST, $db_user = DB_USER, $db_password = DB_PASSWORD, $db_name = DB_NAME) {
            $this->db_host = $db_host;
            $this->db_user = $db_user;
            $this->db_password = $db_password;
            $this->db_name = $db_name;
        }
        
        function dump($old_url = NULL, $new_url = NULL, $exec = false) {
            $filename = LSD_DIR.'/lsd_dump.sql';
        
            if(LSD_MYSQL_DUMP_USE_EXEC || $exec) :
                $worked = $this->exec_dump($filename, $old_url, $new_url);
                switch($worked){
                    case 0:
                        break;
                    case 1:
                        $this->last_result = "NOTICE: ".$this->last_result;
                        break;
                    case 2:
                        $this->last_result = "ERROR: ".$this->last_result;
                        break;
                }
                if(!$worked) return 0;
                if(!LSD_MYSQL_DUMP_FALLBACKS) return $worked;
            endif;
            
            return $this->php_dump($filename, $old_url, $new_url);
        }
        
        function import() {
            $filename = LSD_DIR.'/lsd_dump.sql';
            if(LSD_MYSQL_DUMP_USE_EXEC) :
                $command = 'mysql -h '.$this->db_host .' -u ' .$this->db_user .$password .' ' .$this->db_name .' < "'.$filename.'"';
                
                system($command,$worked);
                if(!$worked) return 0;
                if(!LSD_MYSQL_DUMP_FALLBACKS) return $worked;
            endif;
            
            $this->db_connection = new mysqli($this->db_host, $this->db_user, $this->db_password);
            if(!$this->db_connection || !$this->db_connection->select_db($this->db_name)) {
               if($this->db_connection)
                    $this->last_result = $this->db_connection->error;
               else 
                    $this->last_result = "Unable to connect to mysql database";
                return 3;
            }
            
            if(!$this->db_connection->query(file_get_contents($filename))) {
                echo $this->last_result = $this->db_connection->error;
                return 2;
            }
            return 0;
        }
        
        protected function exec_dump($filename, $old_url = NULL, $new_url = NULL) {        
            if($this->db_password)
                $password = ' -p ' .$this->db_password;
            $command='mysqldump --opt -h '.$this->db_host .' -u ' .$this->db_user .$password .' ' .$this->db_name .' > "'.$filename.'"';
            
            $this->last_result = system($command,$worked);
            if(!$worked && $old_url != $new_url) :
                $dump = file_get_contents($filename);
                $dump = $this->recalcserializedlengths(str_replace($old_url, $new_url, $dump), true);
                file_put_contents($filename, $dump);
            endif;
            return $worked;
        }
        
        // slightly edited version of davidcoveney's serialize recalculator http://davidcoveney.com/575/php-serialization-fix-for-wordpress-migrations/
        // used to fix serialized strings when URL's are changed
        protected function recalcserializedlengths($string, $escaped = false) {
            if($escaped) 
                $__ret =preg_replace('!s:(\d+):\\\\"(.*?)\\\\";!e', "'s:'.strlen('$2').':\\\"$2\\\";'", $string );
            else
                $__ret =preg_replace('!s:(\d+):"(.*?)";!e', "'s:'.strlen('$2').':\"$2\";'", $string );
           
            return $__ret;
        } 
        
        protected function php_dump($filename, $old_url = NULL, $new_url = NULL) {
            if(!$this->db_connection) :
                $this->db_connection = new mysqli($this->db_host, $this->db_user, $this->db_password);
                if(!$this->db_connection || !$this->db_connection->select_db($this->db_name)) {
                   if($this->db_connection)
                        $this->last_result = $this->db_connection->error;
                   else 
                        $this->last_result = "Unable to connect to mysql database";
                    return 3;
                }
            endif;
            
            // list tables
            $sql = 'SHOW TABLES FROM '.$this->db_name;
            $result = $this->db_connection->query($sql);
            if (!$result) {
                $this->last_result = $this->db_connection->error;
                return 3;
            }
            
            $dump = "-- WP Live Server Deploy MySQL Dump 0.2\n"
            ."--\n"
            ."-- Host: ".$this->db_host."    Database: ".$this->db_name."\n"
            ."-- ------------------------------------------------------\n"
            ."-- Server version	".mysql_get_server_info()."\n";
            
            $column_Set = false;
            // go through each table
            while ($row = mysqli_fetch_row($result)) :
                $table = $row[0];
                
                $dump .= $this->dump_comment("Table structure for table `".$table."`");
                
                $dump .= "DROP TABLE IF EXISTS `".$table."`;\n";
                $dump .= "CREATE TABLE `".$table."` (\n";
                // list columns
                $sql = 'SHOW COLUMNS FROM `'.$table.'`';
                $cresult = $this->db_connection->query($sql);
                if (!$cresult) {
                    echo $sql;
                    $this->last_result = $this->db_connection->error;
                    return 3;
                }
                $primary_key = "";
                $keys = "";
                
                while ($column = mysqli_fetch_assoc($cresult)) :
                    $scolumn = "`".$column['Field']."` ";
                    $scolumn .= ($column['Type'])." ";
                    if($column['Null'] == "NO") $scolumn .= "NOT NULL ";
                    if(isset($column['Default']) || !($column['Null'] == "NO" && $column['Default'] == NULL)) {
                        if(!isset($column['Default']) && !is_string($column['Default'])) $scolumn .= "NULL ";
                        else $scolumn .= "DEFAULT '{$column['Default']}' ";
                    }
                    $scolumn .= strtoupper($column['Extra'])." ";
                    $dump .= "  ".trim($scolumn).",\n";
                endwhile;

                $sql = 'SHOW INDEXES FROM `'.$table.'`';
                $cresult = $this->db_connection->query($sql);
                if (!$cresult) {
                    echo $sql;
                    $this->last_result = $this->db_connection->error;
                    return 3;
                }
                $primary_key = "";
                $keys = "";
                
                $indexes = array();
                
                while ($column = mysqli_fetch_assoc($cresult)) :
                    if(!@$indexes[$column['Key_name']]) :
                        $indexes[$column['Key_name']] = $column;
                    else :
                        $indexes[$column['Key_name']]['Column_name'] = $indexes[$column['Key_name']]['Column_name'] .= '`, `'.$column['Column_name'];
                    endif;
                endwhile;
                
                foreach ($indexes as $column) :
                    $type = null;
                    if(!$column['Non_unique']) :
                        if($column['Key_name'] == "PRIMARY")
                            $type = "PRIMARY";
                        else
                            $type = "UNIQUE";
                    else :
                        $type = "KEY";
                    endif;
                    switch($type) :
                        case 'PRIMARY':
                            $dump .= "  PRIMARY KEY (`".$column['Column_name']."`),\n";
                            break;
                        case 'UNIQUE':
                            $dump .= "  UNIQUE KEY `".$column['Key_name']."` (`".$column['Column_name']."`),\n";
                            break;
                        case 'KEY':
                            $dump .= "  KEY `".$column['Key_name']."` (`".$column['Column_name']."`),\n";
                            break;
                    endswitch;
                endforeach;
                
                $sql = 'SHOW TABLE STATUS LIKE "'.$table.'"';
                $cresult = $this->db_connection->query($sql);
                if (!$cresult) {
                    echo $sql;
                    $this->last_result = $this->db_connection->error;
                    return 3;
                }
                $attributes = "";
                
                while ($column = mysqli_fetch_assoc($cresult)) :
                    $attributes .= " ENGINE=".$column['Engine'];
                    if($column['Auto_increment']) $attributes .= " AUTO_INCREMENT=".$column['Auto_increment'];
                    if($column['Collation']) $attributes .= " DEFAULT CHARSET=".current(explode('_',$column['Collation']));
                endwhile;

                // cut of the last comma
                $dump = trim($dump);
                if($dump[strlen($dump)-1] == ',') $dump[strlen($dump)-1] = "\n";

                $dump .= ") $attributes;\n";
                
                $dump .= $this->dump_comment("Dumping data for table `".$table."`");
                $dump .= "LOCK TABLES `".$table."` WRITE;\n";
                //$dump .= "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n";
                
                $sql = 'SELECT * FROM `'.$table.'`';
                $cresult = $this->db_connection->query($sql);
                if (!$cresult) {
                    echo $sql;
                    $this->last_result = $this->db_connection->error;
                    return 3;
                }
                
                while ($column = mysqli_fetch_assoc($cresult)) :
                    $dump .= "INSERT INTO `$table` VALUES (";
                    $first = true;
                    foreach($column as $v) {
                        if(!$first) $dump .= ", ";
                        if($old_url != $new_url)
                            $v = $this->recalcserializedlengths(str_replace($old_url, $new_url, $v));
                        $dump .= "'".mysql_real_escape_string($v)."'";
                        $first = false;
                    }
                    $dump .= ");\n";
                    $column;
                endwhile;
                
                //$dump .= "/*!40000 ALTER TABLE `".$table."` ENABLE KEYS */;\n";
                $dump .= "UNLOCK TABLES;\n";
                
                
            endwhile;
            mysqli_free_result($result);
            
            $fp = fopen($filename, 'w');
            if(!$fp) return 4;
            fwrite($fp, $dump);
            fclose($fp);
            return 0;
        }
        
        protected function dump_comment($text) {
            return "\n--\n".
            "-- ".$text."\n".
            "--\n\n";
        }
    }

    function lsd_download_mysql_dump() {
        $mysql_dump = new MySQLTransfer();
        $mysql_dump->dump(get_option('siteurl'), $_POST['site_url']);
        
        // Set headers
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=".DB_NAME.".sql");
        header("Content-Type: application/sql");
        header("Content-Transfer-Encoding: binary");
        @readfile(LSD_DIR."/lsd_dump.sql");
        @unlink(LSD_DIR."/lsd_dump.sql");
        exit;
    }

