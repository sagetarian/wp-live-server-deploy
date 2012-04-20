<?php
    $zip = new ZipArchive;
    $res = $zip->open('./lsd_dump.zip');
    if ($res === TRUE) {
        $zip->extractTo(dirname(__FILE__).'/');
        $zip->close();
        echo 1;
    }
    unlink('./lsd_dump.zip');
?>
