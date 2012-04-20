<?php
/**
 * @package WP Live Server Deploy
 * @version 1.0
 */
/*
Plugin Name: WP Live Server Deploy
Plugin URI: 
Description: Easily move an entire wordpress installation to a new server with just your FTP details and site URL. Mostly useful to developers.
Author: Shannon Antonio Black
Author URI: https://github.com/sagetarian
Version: 1.0
Tested up to: 3.3
*/
ini_set('display_errors','on');

define(LSD_URL, plugins_url('',__FILE__));
define(LSD_DIR, dirname(__FILE__));

define(LSD_ARCHIVING_ENABLED, class_exists('ZipArchive')); // this is so that you can utilize minimal upload size and therefore speedious ftp .. requires that the Zip extension is installed on both your servers

define(LSD_MYSQL_DUMP_USE_EXEC, !ini_get('safe_mode') && !ini_get('safe_mode_exec_dir'));  // this will use the system shell for mysql dumping/importing or use built in import/export .. built in import / export is experimental
define(LSD_MYSQL_DUMP_FALLBACKS, FALSE); // if the mysql dump exec fails then it will use the built in importing/exporting .. for consistency between source/destination server keep at false
define(LSD_FTP_MAXIMUM_CONNECTIONS, 10); // must be at least 1

include "controller/automate.php";
if (LSD_ARCHIVING_ENABLED)
    include "controller/archive-transfer.php";
    
include "controller/ftp-bulk-transfer.php";
include "controller/mysql-transfer.php";

include "view/admin.php";

?>
