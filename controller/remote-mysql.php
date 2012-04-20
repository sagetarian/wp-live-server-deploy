<?php
    // import mysql stuff
    extract($_GET);
    
    $filename = dirname(__FILE__).'/lsd_dump.sql';
    if($mysql_exec) :
        if($db_password)
            $password = ' -p ' .$db_password;
        $command = 'mysql -h '.$db_host .' -u ' .$db_username .$password .' ' .$db_name .' < "'.$filename.'"';
        ob_start();
        system($command,$worked);
        ob_get_clean();
        if(!$worked) echo 0;
    endif;
    
    $db_connection = @new mysqli($db_host, $db_username, $db_password);
    if(mysqli_connect_error()) {
        $last_result = mysqli_connect_error();
        $db_connection = false;
        echo $last_result;
        exit;
    }
    
    if(!$db_connection->select_db($db_name)) {
       if($db_connection)
            $last_result = $db_connection->error;
       else 
            $last_result = "Unable to connect to mysql database";
       echo $last_result;
       exit;
    }
    
    $contents = explode("\n",file_get_contents($filename));
    $templine = '';
    foreach($contents as $line) {
        if (substr($line, 0, 2) == '/*' || substr($line, 0, 2) == '--' || $line == '')
            continue;
        
        $templine .= $line;
        if (substr(trim($line), -1, 1) == ';') {
            if(!$db_connection->query($templine)) {
                $last_result = $db_connection->error;
                echo $last_result;
            }
            $templine = '';
        }
    }
    echo 0;
?>
