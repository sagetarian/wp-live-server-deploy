<?php
    echo serialize(array (
        'archiving' => class_exists('ZipArchive'),
        'mysql_exec' => !ini_get('safe_mode') && !ini_get('safe_mode_exec_dir')
    ));
?>
