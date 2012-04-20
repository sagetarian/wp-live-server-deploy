<?php

    if($_POST['liveServerDeployManual'] == 1)
        add_action('init', 'lsd_download_mysql_dump');

    add_action('admin_menu', 'lsd_admin_menu');
    
    function lsd_admin_menu() {
	    add_options_page('Live Server Deploy', 'Live Server Deploy', 'manage_options', 'live-server-deploy', 'lsd_admin_page');
    }

    function lsd_admin_page() {
        $live_server_deploy_settings = get_option('live_server_deploy_settings');
        if($live_server_deploy_settings['lastdeploy']) extract($live_server_deploy_settings['lastdeploy']);
        
?>
<script>
    function liveServerDeploy_tab(tab) {
        jQuery('.wrap .tab').hide();
        jQuery('.wrap #tab-'+tab.id).show();
        jQuery('.nav-tab').removeClass('nav-tab-active');
        jQuery(tab).addClass('nav-tab-active');
        return false;
    }
</script>
<div class="wrap">
<div id="icon-options-general" class="icon32"><br></div>
<h2 class="nav-tab-wrapper">
<a href="#main" onclick="return liveServerDeploy_tab(this)" class="nav-tab nav-tab-active" id="main">Live Server Deploy</a>
<a href="#export" onclick="return liveServerDeploy_tab(this)" class="nav-tab" id="export">Manual Export</a>
</h2>
<div class="tab" id="tab-main">
<h3>About Live Server Deploy</h3>
<p>Live Server Deploy aims at fully automating or reducing WordPress set up when moving domains, e.g when moving a development / test install onto the live server. It handles the MySQL import / export with the updated URL for all your plugins and settings to work out the box, and uploads to the main server for you - reducing upload time by compressing the files prior to upload and unpacking on the server.</p>

<h3>Deploy Server</h3>
<p>The following steps need to be completed:</p>
<p>
    <ol id=liveServerDeploySteps>
        <li id=1>Provide Live Server Settings</li>
        <li id=2>Determine Development Server / Live Server Capabilities</li>
        <li id=3>Prepare export</li>
        <li id=4>Upload to server</li>
        <li id=5>Prepare import</li>
        <li id=6>Finalize setup</li>
    </ol>
</p>
<?php if($_POST['liveServerDeploySettings']) : 
    $live_server_deploy_settings['lastdeploy'] = $_POST;
    update_option('live_server_deploy_settings', $live_server_deploy_settings);
?>
<h3>Deployment Status</h3>
<?php liveServerDeploy_automate($_POST); ?>
<?php else: ?>
<h3>Live Server Settings</h3>
<form method=post>
    <input type="hidden" name="liveServerDeploySettings" value=1 />
    <table class="form-table">
	    <tbody>
	    <tr>
		    <th><label for="site_url">Site URL</label></th>
		    <td>
		        <input name="site_url" id="site_url" type="text" value="<?php echo $site_url; ?>" class="regular-text code"><em>This is the destination servers URL path leading to the new WordPress installation</em><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="ftp_server">FTP Server</label></th>
		    <td>
		        <input name="ftp_server" id="ftp_server" type="text" value="<?php echo $ftp_server; ?>" class="regular-text code"><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="ftp_username">FTP Username</label></th>
		    <td>
		        <input name="ftp_username" id="ftp_username" type="text" value="<?php echo $ftp_username; ?>" class="regular-text code"><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="ftp_pass">FTP Password</label></th>
		    <td>
		        <input name="ftp_pass" id="ftp_pass" type="text" value="<?php echo $ftp_pass; ?>" class="regular-text code"><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="ftp_port">FTP Port</label></th>
		    <td>
		        <input name="ftp_port" id="ftp_port" type="text" value="<?php echo $ftp_port; ?>" class="regular-text code"><em>Defaults to <code>port 21</code></em><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="ftp_root">FTP Root Directory</label></th>
		    <td>
		        <input name="ftp_root" id="ftp_root" type="text" value="<?php echo $ftp_root?$ftp_root:'/public_html'; ?>" class="regular-text code"><em>This is the file path where the files will be uploaded to, usually it is <code>/public_html</code></em><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="db_host">Database Host</label></th>
		    <td>
		        <input name="db_host" id="db_host" type="text" value="<?php echo $db_host; ?>" class="regular-text code"><em>Defaults to <code>localhost</code></em><br>
		    </td>
	    </tr>
	    <tr>
		    <th><label for="db_name">Database Name</label></th>
		    <td>
		        <input name="db_name" id="db_name" type="text" value="<?php echo $db_name; ?>" class="regular-text code">
		    </td>
	    </tr>
	    <tr>
		    <th><label for="db_username">Database Username</label></th>
		    <td>
		        <input name="db_username" id="db_username" type="text" value="<?php echo $db_username; ?>" class="regular-text code">
		    </td>
	    </tr>
	    <tr>
		    <th><label for="db_password">Database Password</label></th>
		    <td>
		        <input name="db_password" id="db_password" type="text" value="<?php echo $db_password; ?>" class="regular-text code">
		    </td>
	    </tr>
	    <tr>
		    <th><label for="ignore_list">Ignore Files/Directories</label></th>
		    <td>
		        <input name="ignore_list" id="ignore_list" type="text" value="<?php echo $ignore_list; ?>" class="regular-text code"><em>Make a list of directories or file names to ignore. Put between double quotes or comma seperate (If the file includes a comma then put between double quotes). e.g <code>.gitignore, .git, .svn, "cache"</code></em><br>
		    </td>
	    </tr>
	    </tbody>
    </table>
<p><strong>NOTICE: Please backup any files or mysql tables on the live server. Although Live Server Deploy aims to provide a seamless deploy, we cannot guarantee that nothing unexpected can happen that might damage your content. Use at own risk.</strong></p>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Automate Deploy"></p>  </form>
<?php endif; ?>
</div>
<div class="tab" id="tab-export">
<h3>Manual Export</h3>
<p>So you want to do everything yourself, but don't want to mess with the MySQL dump you got? Using the typical search / replace function for replacing your Old URL with the New one typically breaks your wordpress database. So just download this MySQL dump with the latest URL inserted and import it on your live server manually.</p>
<form method=post>
    <table class="form-table">
	    <tbody>
	    <tr>
		    <th><label for="site_url">Site URL</label></th>
		    <td>
		        <input name="site_url" id="site_url" type="text" value="<?php echo $site_url; ?>" class="regular-text code"><em>This is the destination servers URL path leading to the new WordPress installation</em><br>
		    </td>
	    </tr>
	    </tbody>
    </table>
    <input type="hidden" name="liveServerDeployManual" value="1" />
    <p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Download MySQL"></p>  
</form>
</div>
</div>
<?php
    }

?>
