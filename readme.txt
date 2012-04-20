=== Plugin Name ===
Contributors: sagetarian
Donate link: https://github.com/sagetarian
Tags: install, export, import, migrate, upload, live server, development, deploy
Requires at least: 3.1
Tested up to: 3.3.1
Stable tag: 1.0

Automates the entire process for migrating your wordpress install from a test/development/source to the live/destination domain.

== Description ==

Automates the entire process for migrating your wordpress install from a test/development/source to the live/destination domain. Also allows the ignoring of certain files/directories not needed to upload (e.g /cache, /.git, /.svn /.gitignore etc etc).

Here’s a list of what is accomplished during an automated deployment:

* If supported, archives the entire wordpress installation for compressed upload
* Uploads either the archive or each individual file
* Uploading each individual file uses maximizes upload speed by asynchronously uploading up to 10 files
* You can choose to ignore a list of folders or files for the upload - example cache folder or repository folders
* Dumps/Exports your entire database and uploads it to the live server
* Automatically switches old URL with the new URL
* Automatically updates the wp-config.php accordingly

If you are scared about the automated transfer and wish to do it manually, then just download the MySQL dump with the updated URL.

Disclaimer: Please backup any files or mysql tables on the live server. Although Live Server Deploy aims to provide a seamless deploy, we cannot guarantee that nothing unexpected can happen that might damage your content. Use at own risk.

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Under the 'Settings' menu in WordPress will be a new menu called 'Live Server Deploy'. 

== Frequently Asked Questions ==

= Will WP Live Server mess with my files? =

Although we cannot guarantee that nothing unexpected can happen that might damage the content being transfered to the Live Server, WP Live Server Deploy DOES NOT touch your original files. It is good practice however to backup the live server incase something unusual happens on the live server. (Includes failed FTP uploads or unable to connect to the Database to perform the import)
 
= I don't trust WP Live Server to do everything for me, but I like that it injects the new URL into the MySQL Dump, can't I just get the dump? =

 Yes, you can! In the "Live Server Deploy" page in the admin section, visit the Manual Export section and download the MySQL dump.

== Screenshots ==

* No screenshots

== Changelog ==

= 1.0 =
* Release

== Upgrade Notice ==

= 1.0 =
* Release
