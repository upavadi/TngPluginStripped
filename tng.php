<?php
/*
Plugin Name: TNG Wordpress Integration Stripped
Plugin URI: http://tng.lythgoes.net/wiki/index.php?title=Using_TNG_and_WordPress_with_the_tng-wordpress-plugin
Description: Integrates TNG (The Next Generation of Genealogy) with Wordpress. TNG v9 compatibility added by Darrin Lythgoe.
Author: Mark Barnes with additions by Darrin Lythgoe and Roger Moffat CHANGES BY @MAHESH
Updated by: Darrin Lythgoe and Roger Moffat, 2011-2016

changes to remove login-log-out, registration, widgets etc by Mahesh Upadhyaya, @mahesh, 2022
Version: 11.0.1
Author URI: 
Copyright (c) 2008 Mark Barnes 2011-2016 Darrin Lythgoe and Roger Moffat
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.


CONTENTS
========
* Add actions and initialise
* Admin functions
* Log-in/register functions
* User functions
* Output the TNG page
* Determine which page to display
* Widget functions - Moved
* 'Helper' functions

/************************************************
*                                               *
*           ADD ACTIONS AND INITIALISE          *
*                                               *
* mbtng_serve_static_files                      *
* mbtng_initialise                              *
************************************************/

add_action('plugins_loaded', 'mbtng_serve_static_files');				// Serves static files (.jpg, .css, etc.) as soon as possible

// Serves static files, if requested. Runs initialisation if not.
global $current_dir;
function mbtng_serve_static_files () {
	$current_dir = basename(__DIR__) ;
	session_start();
	if (isset($_REQUEST['update_globalvars'])) {
		mbtng_rewrite_globalvars();
		header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page='. $current_dir. '/tng.php&updated=globalvars');
		die();
	}
	if (isset($_REQUEST['tng_search'])) {
		mbtng_search_for_tng (dirname(__FILE__));
	}
	if (mbtng_display_page()) {
		$tng_folder = get_option('mbtng_path');
		$filename = mbtng_filename();
		if (mbtng_extension() != 'php') {
			chdir ($tng_folder);
			$code = @file_get_contents ($filename);
			if ($code == '') {
				if (php_sapi_name()=='CGI') {
					Header("Status: 404 Not Found");
				} else {
					Header("HTTP/1.0 404 Not Found");
				}
				die();
			}
			header("Content-Length: ".filesize($filename));
			if (mbtng_extension() == 'css') {
				header("Content-type: text/css");
				$lines = explode("\n",$code);
				$newcode = '';
				foreach ($lines as $line) {
// Roger changes here (first line is looking for LB_ not .LB_)
					if (strpos($line, '{' ) !== FALSE && isset($_REQUEST['frontend']) && stripos($line, 'LB_') === FALSE)
// next lines control if the css is rewritten or not
//						$newcode .= '#tng_main '.str_ireplace('body {', '{', $line)."\n"; // rewrites CSS
						$newcode .= ''.str_ireplace('body {', '{', $line)."\n"; // does NOT rewrite CSS
					else
						$newcode .= $line."\n";
				}
				$code = $newcode;
			} else
				header("Content-type: ". mime_content_type($filename));
			$lastModifiedDate = filemtime($filename);
			header ("Content-Length: ".strlen($code));
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedDate) {
				if (php_sapi_name()=='CGI') {
					Header("Status: 304 Not Modified");
				} else {
					Header("HTTP/1.0 304 Not Modified");
				}
			} else {
				$gmtDate = gmdate("D, d M Y H:i:s\G\M\T",$lastModifiedDate);
				header('Last-Modified: '.$gmtDate);
				header('HTTP/1.1 200 OK');
			}
			echo $code;
			die();
		}
	}
	add_action('init', 'mbtng_initialise');
	//add_action('widgets_init', 'mbtng_widget_init');						// Initialise the widgets. Moved to tng_widgets.php
	add_action('admin_menu', 'mbtng_add_admin_page');						// Adds TNG menu to Wordpress admin
	add_action('admin_notices', 'mbtng_options_reminder');					// Adds reminder to set TNG page
	add_action('generate_rewrite_rules', 'mbtng_update_tng_url');			// Updates the URL of the TNG page in wp_options
}


// Add additional actions only if required
function mbtng_initialise () {
	global $wp_query;
	if (mbtng_display_page()) {
		add_filter('the_posts','mbtng_fake_post');						// Return the Wordpress TNG page if any TNG page is requested
		add_action('template_redirect', 'mbtng_buffer_start');			// Intercept front-end to buffer output
		add_action('wp_head', 'mbtng_frontend_header');					// Adds TNG template <head> to WordPress <head>
		add_action('loop_start', 'mbtng_output_page');					// Outputs the TNG page when required
		add_action('loop_end', 'mbtng_discard_output');					// Discards post contents if TNG is displayed
		add_action('wp_footer', 'mbtng_frontend_footer');				// Adds TNG template footer to Wordpress footer
		add_action('shutdown', 'mbtng_buffer_end');						// Flushes output buffer
									// Ensures user is logged out of TNG
	 }
}

/************************************************
*                                               *
*               ADMIN FUNCTIONS                 *
*                                               *
* mbtng_add_admin_page                          *
* mbtng_options_reminder                        *
* mbtng_search_for_tng                          *
* mbtng_rewrite_globalvars                      *
* mbtng_options                                 *
* mbtng_display_tng_admin                       *
************************************************/
//Adds the TNG menu to Wordpress admin
//Icon from FamFamFam: http://www.famfamfam.com/lab/icons/silk/
function mbtng_add_admin_page () {
	static $current_dir;
	add_menu_page('TNG', 'TNG', 'manage_options', __FILE__, 'mbtng_options', plugins_url($current_dir. '/icon.png'));
	add_submenu_page (__FILE__, 'Options', 'Options', 'manage_options', __FILE__, 'mbtng_options');
// Roger comment out the next line to remove Admin from WordPress Admin sidebar
//	add_submenu_page (__FILE__, 'Admin', 'Admin', 'manage_options', 'tng-wordpress-plugin/admin.php', 'mbtng_display_tng_admin');
}

// Adds reminder to set TNG page in options
function mbtng_options_reminder() {
	if (isset($_REQUEST['updated']) && $_REQUEST['updated'] == 'true') {
		update_option('mbtng_path', mbtng_folder_trailingslashit(get_option('mbtng_path')));
		mbtng_update_tng_url();
		mbtng_rewrite_globalvars();
	}
	if (get_option('mbtng_wordpress_page')=='')
		echo '<div id="message" class="updated"><p><b>In order to make TNG visible, you must go to <a href="'.get_bloginfo('wpurl').'/wp-admin/admin.php?page='.$current_dir. '/tng.php">TNG options</a>, and specify a page where TNG will be displayed on your site.</b></div>';
	if (!mbtng_correct_path())
		echo '<div id="message" class="error"><p><b>Warning:</b> TNG cannot be found in '.get_option('mbtng_path').' - please <a href="'.get_bloginfo('wpurl').'/wp-admin/admin.php?page='. $current_dir. '/tng.php">specify the full (absolute) path</a> to your TNG installation, or <a href="'.get_bloginfo('wpurl').'?tng_search">automatically search for the correct folder</a>.</p></div>';
}

//Searches server for TNG files
function mbtng_search_for_tng ($path = '', $go_back = true) {
	global $countit;
	if ($path == '..')
		$oldpath = basename(getcwd());
	else
		$oldpath = '';
	if ($path != '')
		if (!@chdir($path)) {
			header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=tng-wordpress-plugin/tng.php&updated=notfound');
			die();
		}
	$path=getcwd();
	$dir = @opendir($path);
	if ($dir) {
		$element=readdir($dir);
		while ($element !== false) {
			if (is_dir(mbtng_folder_trailingslashit($path).$element) && $element != "." && $element != ".." && $element != $oldpath) {
				mbtng_search_for_tng(mbtng_folder_trailingslashit($path).$element, FALSE);
				chdir($path);
			}
			elseif ($element != "." && $element != "..") {
				if ($element == 'ahnentafel.php' && mbtng_correct_path($path)) {
					update_option('mbtng_path', mbtng_folder_trailingslashit($path));
					header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page='. $current_dir. '/tng.php&updated=search');
					die();
				}
			}
		$element=readdir($dir);
		}
		closedir($dir);
	}
	if ($go_back)
		mbtng_search_for_tng('..', TRUE);
	return;
}

//Searches TNG code for global variables ready to be passed to mbtng_eval_php
function mbtng_rewrite_globalvars() {

	function mbtng_trim (&$item, $key) {
		$item = trim ($item);
	}

	$folders[0] = mbtng_folder_trailingslashit(get_option('mbtng_path'));
	$folders[1] = mbtng_folder_trailingslashit($folders[0].'templates');
	$folders[2] = mbtng_folder_trailingslashit($folders[1].'template1');
	$folders[3] = mbtng_folder_trailingslashit($folders[1].'template2');
	$folders[4] = mbtng_folder_trailingslashit($folders[1].'template3');
	$folders[5] = mbtng_folder_trailingslashit($folders[1].'template4');
	$folders[6] = mbtng_folder_trailingslashit($folders[1].'template5');
	$folders[7] = mbtng_folder_trailingslashit($folders[1].'template6');
	$folders[8] = mbtng_folder_trailingslashit($folders[1].'template7');
	$folders[9] = mbtng_folder_trailingslashit($folders[1].'template8');
	$folders[10] = mbtng_folder_trailingslashit($folders[1].'template9');
	$folders[11] = mbtng_folder_trailingslashit($folders[1].'template10');
	$folders[12] = mbtng_folder_trailingslashit($folders[1].'template11');
	$folders[13] = mbtng_folder_trailingslashit($folders[1].'template12');
	$folders[14] = mbtng_folder_trailingslashit($folders[1].'template13');
	$folders[15] = mbtng_folder_trailingslashit($folders[1].'template14');
	$folders[16] = mbtng_folder_trailingslashit($folders[1].'template15');
	$bnn = $keep = array();
	foreach ($folders as $folder) {
		if ($dh = @opendir($folder)) {
			while (false !== ($file = readdir($dh))) {
				if (substr($file, -4) == ".php" && !is_dir($folder.$file) && !in_array($file, $bnn) && $file != "tng.php") {
					$lines=file($folder.$file);
					foreach ($lines as $line)
						if (stripos($line, 'global $') !== FALSE) {
							$start = stripos($line, 'global $')+7;
							$end = strpos($line, ';', $start);
							$these = explode (',', substr($line, $start, $end-$start));
							$keep = array_merge ($keep, $these);
						}
					}
			}
			closedir($dh);
		}
	}
	array_walk ($keep, 'mbtng_trim');
	$keep = array_unique($keep);
	if (is_array($keep))
		update_option('mbtng_globalvars', '<?php GLOBAL '.implode (', ', $keep).'; ?>'); //Think about BASE64
}

// Displays options page
function mbtng_options () {
	global $wp_version;
	echo "<div class=\"wrap\">\n";
	echo "<h2>TNG / Wordpress integration</h2>\n";
	if (isset($_REQUEST['updated']) && $_REQUEST['updated'] == 'globalvars')
		echo '<div id="message" class="updated fade"><p>TNG variables updated.</p></div>';
	if (isset($_REQUEST['updated']) && $_REQUEST['updated'] == 'search')
		echo '<div id="message" class="updated fade"><p>TNG path found as '.get_option('mbtng_path').'.</p></div>';
	if (isset($_REQUEST['updated']) && $_REQUEST['updated'] == 'notfound')
		echo '<div id="message" class="error"><p>Could not find TNG path.</p></div>';
	if (isset($_REQUEST['updated']) && $_REQUEST['updated'] == 'true') {
		echo '<div id="message" class="updated fade"><p>TNG settings saved.</p></div>';
		update_option('mbtng_timestamp', strtotime('now'));
	}
	if (get_option('permalink_structure')=='')
		echo '<div id="message" class="error"><p>Sorry, you cannot use the default <a href="options-permalink.php">permalink structure</a> with this plugin</p></div>';
	if (!isset($_SERVER['REQUEST_URI']))
		echo '<div id="message" class="error"><p>Sorry, this site appears to be running on a Windows server, which is not currently compatible with the TNG plugin. Please <a href="http://www.4-14.org.uk/contact">contact the developer</a> if you would like to test a Windows version.</p></div>';
	$pages = get_pages('sort_column=menu_order');
	echo "<h3>TNG Options</h3>\n";
	echo "<form method=\"post\" action=\"options.php\">\n";
// Roger added a width to this table
	echo "\t<table width=\"800\">\n";
	echo "\t\t<tr>\n";
// Roger added a width to this table column
echo "\t\t\t<td width=\"200\" style=\"padding: 0.5em 0\">Show TNG on:</td>\n";
	echo "\t\t\t<td style=\"padding: 0.5em 0\"><select name=\"mbtng_wordpress_page\">";
	foreach ($pages as $page) {
		if (get_bloginfo('wpurl') != get_permalink($page->ID)) { // Don't allow homepage to be selected
			if ($page->ID == get_option('mbtng_wordpress_page'))
				$selected = ' selected="selected"';
			else
				$selected='';
			echo "<option value=\"{$page->ID}\"{$selected}>{$page->post_title}</option>";
		}
	}
	echo "</select></td>\n";
	echo "\t\t</tr>\n";
	echo "\t\t<tr>\n";
	echo "\t\t\t<td style=\"padding: 0.5em 0\"valign=\"top\">Path to TNG files:&nbsp;</td>\n";
// Roger changes here to alter message about paths to wp and tng folders
	echo "\t\t\t<td style=\"padding: 0.5em 0\"><input type=\"text\" name=\"mbtng_path\" value=\"".get_option('mbtng_path')."\" size=\"50\" \><br/>This folder should be publicly accessible in order for TNG Admin to work best.<br />One suggested configuration is to have WordPress in one folder in your site's root folder, and TNG in another folder in your site's root folder. So you might have:<br /><strong>/public_html/wp/<br />/public_html/tng/</strong><!--<br /><br />It is best if this folder is <b>publically inaccessible</b>. On many webservers, this means outside the public_html folder. You should set \$rootpath to the same value in your TNG config.php or customconfig.php file. If you do this, you <strong>must</strong> use the TNG Admin inside this WordPress Frame.--></td>\n";
	echo "\t\t</tr>\n";
//Roger added next table row to add field for URL to Admin
	echo "\t\t<tr>\n";
	echo "\t\t\t<td style=\"padding: 0.5em 0\"valign=\"top\">URL to TNG Admin:&nbsp;</td>\n";
	echo "\t\t\t<td style=\"padding: 0.5em 0\"><input type=\"text\" name=\"mbtng_url_to_admin\" value=\"".get_option('mbtng_url_to_admin')."\" size=\"50\" \><br/>This is the URL to the TNG Admin page inside the TNG folder. So if you've put TNG into a folder called tng in your site's root folder it will be of the form http://YourSite.com/tng/admin.php</td>\n";
	echo "\t\t</tr>\n";
//End or Roger addition
	//echo "\t\t<tr>\n";
	//echo "\t\t\t<td style=\"padding: 0.5em 0\"valign=\"top\">Integrate TNG/Wordpress logins:&nbsp;</td>\n";
	//echo "\t\t\t<td style=\"padding: 0.5em 0\"><input type=\"checkbox\" name=\"mbtng_integrate_logins\"";
	//if (get_option('mbtng_integrate_logins')) echo "checked='checked'"; 
	update_option('mbtng_integrate_logins', ""); // add this to get backward comatability - Mahesh
	echo "\></td>\n";
	echo "\t\t</tr>\n";
	// if (version_compare($wp_version, '2.6.1', '>')) {
	// 	echo "\t\t<tr>\n";
	// 	echo "\t\t\t<td style=\"padding: 0.5em 0\"valign=\"top\">Redirect successful login to referrer page:&nbsp;</td>\n";
	// 	echo "\t\t\t<td style=\"padding: 0.5em 0\"><input type=\"checkbox\" name=\"mbtng_redirect_login\"";
	// 	//if (get_option('mbtng_redirect_login')) echo "checked='checked'";
	// 	echo "\></td>\n";
	// 	echo "\t\t</tr>\n";
	// }
	echo "\t\t<tr>\n";
	//echo "\t\t\t<td style=\"padding: 0.5em 0\"valign=\"top\">Replace TNG homepage with Wordpress page:&nbsp;</td>\n";
	//echo "\t\t\t<td style=\"padding: 0.5em 0\"><input type=\"checkbox\" name=\"mbtng_use_wordpress_homepage\"";
	//if (get_option('mbtng_use_wordpress_homepage')) echo "checked='checked'";
	echo "\></td>\n";
	echo "\t\t</tr>\n";
	echo "\t\t<tr>\n";
	echo "\t\t\t<td>&nbsp;</td>\n";
	echo "\t\t\t<td><p class=\"submit\" style=\"padding:0\"><input type=\"submit\" name=\"Submit\" value=\"Save Changes\" /></p></td>\n";
	echo "\t\t</tr>\n";
	wp_nonce_field('update-options');
	echo "<input type=\"hidden\" name=\"action\" value=\"update\" />";
// Roger added new variable mbtng_url_to_admin here to store path directly to TNG Admin
	echo "<input type=\"hidden\" name=\"page_options\" value=\"mbtng_wordpress_page, mbtng_path, mbtng_integrate_logins, mbtng_redirect_login, mbtng_use_wordpress_homepage, mbtng_url_to_admin\" />";
	echo "</form>\n";
	echo "\t</table>\n";
	echo "<h3>Advanced</h3>\n";
	echo "<form method=\"post\">\n";
// Roger added a width to this table
	echo "\t<table width=\"800\">\n";
	echo "<tr>\n";
// Roger added a width to this table column
	echo "<td width=\"200\"><p class=\"submit\" style=\"padding:0\"><input type=\"submit\" name=\"tng_search\" value=\"Search for TNG installation\" style=\"margin-right:1em\"/></p></td>\n";
	echo "<td>Click here to attempt to automatically find your TNG installation folder.</td>\n";
	echo "</tr><tr>\n";
	echo "<td><p class=\"submit\" style=\"padding:0\"><input type=\"submit\" name=\"update_globalvars\" value=\"Update TNG variables\" /></p></td>\n";
	echo "<td>You should press this button if you upgrade your TNG version, or if pages display incorrectly.</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</form>\n";
	echo "</div>\n";
	
// Roger changes here - these lines add link to open TNG Admin in new window
	$tng_folder = get_option('mbtng_path');
	chdir($tng_folder);
	include("begin.php");
	echo "<h3>TNG Admin</h3>\n";
	echo "<form method=\"post\">\n";
	echo "\t<table width=\"800\">\n";
	echo "<tr>\n";
// Roger this next line uses the variable mbtng_url_to_admin direct to TNG Admin
	echo "<td width=\"200\"><p class=\"submit\" style=\"padding:0\"><input type=\"submit\" name=\"GoToAdmin\" value=\"Go to TNG Admin\" onclick=\"newwindow=window.open('" . get_option('mbtng_url_to_admin') . "')\" style=\"margin-right:1em\"/></p></td>\n";
	echo "<td>Press this button to go to the TNG Admin area in a new window outside of the WordPress Admin environment.</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</form>\n";
// End of additions for TNG Admin Link
echo "For Integrated Login/Logout you may want to try latest release of <a href='https://github.com/upavadi/tng-wp-login/' target='_blank'>Wordpress-TNG Login Widget</a>";

}

//Displays TNG admin in an iframe
//Should consider javascript to set iframe height

function mbtng_display_tng_admin ($echo='') {
	// $iframe = '<iframe name="tng" id="tng" src ="'.trailingslashit(get_option('mbtng_url')).'admin.php?true" height = 2000 width="100%"></iframe>';
	// if ($echo === false)
	// 	return $iframe;
	// else
	// 	echo $iframe;
}

/************************************************
*                                               *
*             OUTPUT THE TNG PAGE               *
*                                               *
* mbtng_use_tng_homepage                        *
* mbtng_frontend_header                         *
* mbtng_frontend_footer                         *
* mbtng_fake_post                               *
* mbtng_output_page                             *
* mbtng_buffer_start                            *
* mbtng_buffer_end                              *
* mbtng_discard_output                          *
* mbtng_eval_php                                *
************************************************/
//need this function to draw the page
function mbtng_use_tng_homepage() {
	if (get_option('mbtng_use_wordpress_homepage')==TRUE && mbtng_requested_url() == get_option('mbtng_url'))
		return false;
	else
		return true;
}

//Adds TNG header to Wordpress header
function mbtng_frontend_header() {
	global $tng_head, $current_dir;
// Roger - if you want to over-ride these styles, copy these lines to mytngstyle.css and change them there
	$tng_head = "<!-- ". $current_dir. " v11.0 -->
	<style type=\"text/css\">
	#tng_main {line-height: 1.2em}
/* next line removed for desctracker.php display issues */
/*	#tng_main tr td {padding: 2px 4px; margin:0; border-top: none} */
	p, pre {line-height: 1.2em}
	</style>\r\n".$tng_head;
	if (strpos(mbtng_filename(), 'admin') !== FALSE)
		$tng_head .= '<base href = "'.get_permalink(get_option('mbtng_wordpress_page')).'"/>';
	$tng_head .= "<style type=\"text/css\">#tng_main td.menuback, #tng_main td.spacercol, #tng_main table.page td.section td.fieldname {display:none}</style>";
	if ($tng_head != '')
		echo $tng_head;
}

//Adds TNG footer to Wordpress footer
function mbtng_frontend_footer() {
	global $tng_footer;
	if ($tng_footer != '')
		echo $tng_footer;
}

// Returns the TNG Wordpress post whenever a TNG page is requested
function mbtng_fake_post($posts){
	if (mbtng_display_page()){
		$posts = get_pages('include='.get_option('mbtng_wordpress_page'));
		add_filter('user_trailingslashit', 'mbtng_smart_trailingslashit');
	}
	return $posts;
}

//Outputs the TNG code (outside the loop to minimise CSS conflicts)
function mbtng_output_page() {
	global $tng_output;
	if (mbtng_display_page() && mbtng_use_tng_homepage()) {
		echo $tng_output;
		ob_start();
	}
}

// Buffers Wordpress and TNG code to allow for correct merging of HTML
function mbtng_buffer_start() {
	global $tng_output, $tng_head, $tng_footer, $tngdomain;
	$query = mbtng_requested('query');
	if (mbtng_display_page()) {
		$tng_folder = get_option('mbtng_path');
		$filename = mbtng_filename();
		if (mbtng_extension() == 'php') {
			$tng_output = mbtng_eval_php($filename);
			$non_template_files = array ('tngrss.php', 'addbookmark.php', 'fpdf.php', 'ufpdf.php', 'log.php');
			if (in_array($filename, $non_template_files) || (isset($_REQUEST['tngprint']) && $_REQUEST['tngprint']==1) || stripos($filename, 'admin') !== FALSE || stripos($filename, 'ajx_') !== FALSE || stripos($filename, 'img_') !== FALSE || stripos($filename, 'find') !== FALSE || stripos($filename, 'rpt_') !== FALSE || stripos($tng_output, '<!-- The Next Generation of Genealogy Sitebuilding') === FALSE) {
				echo $tng_output;
				die();
			} else {
				if ($filename == 'admin.php') {
					$tng_output = '<div id="tng_main">'.mbtng_display_tng_admin(false).'</div>';
				}
				else {
					$head_start = stripos ($tng_output, '<head>')+6;
					$head_end = stripos ($tng_output, '</head>');
					$tng_head = substr ($tng_output, $head_start, $head_end-$head_start);
					$tng_head = str_replace('src="js', 'src="'.trailingslashit($tngdomain).'js', $tng_head);
					$tng_head = str_replace('href="css', 'href="'.trailingslashit($tngdomain).'css', $tng_head);
					$tng_head = str_replace('.css?', '.css?frontend&amp;', $tng_head);
					$tng_head = str_replace('.css" rel', '.css?frontend" rel', $tng_head);
					$footer_start = stripos ($tng_output, '</body>')+7;
					$footer_end = stripos ($tng_output, '</html>');
					$tng_footer = substr ($tng_output, $footer_start, $footer_end-$footer_start);
					$output_start = stripos($tng_output, '>', stripos ($tng_output, '<body'))+1;
					$output_end = $footer_start-8;
					$tng_output = '<div id="tng_main">'.substr ($tng_output, $output_start, $output_end-$output_start).'</div>';
				}
				ob_start();
			}
		}
	}
}

//Flushes the output buffer
function mbtng_buffer_end() {
	@ob_end_flush();
}
 
// Discards contents of page if TNG is displayed
function mbtng_discard_output() {
	if (mbtng_display_page() && mbtng_use_tng_homepage()) {
		ob_clean();
		// Remove actions in case loop used elsewhere (e.g. in sidebar)
		remove_action('loop_start', 'mbtng_output_page');
		remove_action('loop_end', 'mbtng_discard_output');
	}

}

//Runs the TNG PHP code and returns the HTML
function mbtng_eval_php($filename) {
	global $tng_output;
	if ($tng_output == '') {
		$tng_folder = get_option('mbtng_path');
		if (stripos($filename, 'admin') !== FALSE) {
			//$filename = substr ($filename, 6);
			$admin = true;
			//$tng_folder .= 'admin';
		} else {
			ini_set('include_path', ini_get('include_path').PATH_SEPARATOR.$tng_folder);
			$admin = false;
		}
		eval('?>'.get_option('mbtng_globalvars').'<?php ');
    $filename = mbtng_folder_trailingslashit($tng_folder).$filename;
		ob_start();
		if ($admin || $filename == 'pdfform.php')
			chdir($tng_folder);

    require $filename;
    $output = ob_get_contents();
    ob_end_clean();
		$tng_output = $output;
	}
	mbtng_close_tng_table();
	return $tng_output;
}

/************************************************
*                                               *
*        DETERMINE WHICH PAGE TO DISPLAY        *
*                                               *
* mbtng_requested                               *
* mbtng_extension                               *
* mbtng_filename                                *
************************************************/

// Returns the requested TNG page or query
function mbtng_requested ($type = 'url') {
	$requested = mbtng_requested_url();
	$query_pos = strrpos($requested, '?')+1;
	$query = '';
	if ($query_pos !== 1) {
		$query = substr($requested, $query_pos);
		$requested = substr($requested, 0, $query_pos-1);
		$query_pos = strrpos($query, 'tng_template='); // Check
		if ($query_pos !== FALSE)
			$query = substr($query, 0, $query_pos);
	}
	if ($type == 'query')
		return $query;
	elseif ($type='url') {
		$requested = substr($requested, strlen(get_option('mbtng_url')));
		if (substr($requested, 0, 1) != '/')
			$requested = '/'.$requested;
		if (substr($requested, -1) == '/')
			$requested .= 'index.php';
		return $requested;
	}
}

// Returns the file extension of the requested page
function mbtng_extension () {
	$requested = mbtng_requested();
	if (mbtng_display_page())
		return strtolower(substr($requested, strrpos($requested, '.') + 1));
}

// Returns the filename of the requested page
function mbtng_filename () {
	return substr(mbtng_requested(),1);
}

/************************************************
*                                               *
*                'HELPER' FUNCTIONS             *
*                                               *
* mbtng_db_connect                              *
* mbtng_close_tng_table                         *
* mbtng_requested_url                           *
* mbtng_is_windows                              *
* mbtng_correct_path                            *
* mbtng_display_page                            *
* mbtng_get_template_list                       *
* mbtng_get_template                            *
* mbtng_smart_trailingslashit                   *
* mbtng_folder_trailingslashit                  *
* mime_content_type                             *
************************************************/

//Replicates tng_db_connect
function mbtng_db_connect() {
	global $textpart, $session_charset;
	$tng_folder = get_option('mbtng_path');
	chdir($tng_folder);
	$config = file_get_contents ('config.php');
	$config .= file_get_contents ('customconfig.php');
	$configlines = explode("\n",$config);
	$config = '';
	$globalvars = 'global $tngconfig';
	foreach ($configlines as $line) {
		if (substr(trim($line), 0, 10) == '$database_')
			$config .= trim($line)."\n";
		if (stripos($line, '_table') !== FALSE) {
			$globalvars .= ', '.substr(trim($line), 0, stripos($line, '_table')+6);
			$config .= trim($line)."\n";
		}
		else if(strpos($line, 'tngconfig') !== FALSE) {
			$config .= trim($line)."\n";
		}
	}
	eval($globalvars.';'.$config);
	$link = @tng_connect($database_host, $database_username, $database_password);
	if ($session_charset == 'UTF-8')
		@tng_query("SET NAMES 'utf8'");
	if( $link && tng_select_db($database_name, $link))
		return $link;
	else {
		echo "Error: TNG is not communicating with your database. Please check your database settings and try again.";
		exit;
	}
	return( FALSE );
}

// Reselects the Wordpress database table
function mbtng_close_tng_table () {
	$link = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	mysqli_select_db ($link, DB_NAME);
}

//Returns the full URL requested. May need modifying for IIS.
function mbtng_requested_url () {
	$http = is_ssl() ? "https" : "http";
	return "{$http}://".$_SERVER['SERVER_NAME'].urldecode($_SERVER['REQUEST_URI']);
}

//Returns TRUE if running on Windows
function mbtng_is_windows () {
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		return true;
	else
		return false;
}

//Returns true if mbtng_path option is correct
function mbtng_correct_path($path='') {
	$current_folder = getcwd();
	if ($path == '')
		$path = get_option('mbtng_path');
	@chdir($path);
	if (file_exists('begin.php') && file_exists('admin.php') && file_exists('genlib.php')) {
		chdir($current_folder);
		return true;
	} else {
		chdir($current_folder);
		return false;
	}
}

// Returns true if TNG page requested
function mbtng_display_page () {
	if (get_option('mbtng_wordpress_page')=='' | !mbtng_correct_path())
		return false;
	else {
		$requested = mbtng_requested_url();
		$query_pos = strrpos($requested, '?')+1;
		if ($query_pos !== 1)
			$requested = substr($requested, 0, $query_pos-1);
		if (stripos ($requested, get_option('mbtng_url')) !== FALSE) {
			$tng_folder = get_option('mbtng_path');
			$filename = mbtng_filename();
			if (file_exists("{$tng_folder}/{$filename}")) {
				return true;
			}
		}
		else
			return false;
	}
}

// Updates the TNG URL in wp_options
function mbtng_update_tng_url() {
	update_option('mbtng_url', get_permalink(get_option('mbtng_wordpress_page')));
}

//Removes the trailing slash from the URL
function mbtng_smart_trailingslashit ($url) {
	$url = rtrim($url, '/') . '/';
	if ( 0 < preg_match("#\.[^/]+/$#", $url) )
		$url = rtrim($url, '/');
	return $url;
}

//Adds a server-specific slash for folders
function mbtng_folder_trailingslashit ($folder) {
	if (mbtng_is_windows())
		return rtrim($folder, '\\').'\\';
	else
		return trailingslashit($folder);
}

//Replacement mime_content_type_function if required
if(!function_exists('mime_content_type')) {
	function mime_content_type($filename) {
		$mime_types = array(
			'txt' => 'text/plain',
			'htm' => 'text/html',
			'html' => 'text/html',
			'php' => 'text/html',
			'css' => 'text/css',
			'js' => 'application/javascript',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'swf' => 'application/x-shockwave-flash',
			'flv' => 'video/x-flv',
			'png' => 'image/png',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'ico' => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif' => 'image/tiff',
			'svg' => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'zip' => 'application/zip',
			'rar' => 'application/x-rar-compressed',
			'exe' => 'application/x-msdownload',
			'msi' => 'application/x-msdownload',
			'cab' => 'application/vnd.ms-cab-compressed',
			'mp3' => 'audio/mpeg',
			'qt' => 'video/quicktime',
			'mov' => 'video/quicktime',
			'pdf' => 'application/pdf',
			'psd' => 'image/vnd.adobe.photoshop',
			'ai' => 'application/postscript',
			'eps' => 'application/postscript',
			'ps' => 'application/postscript',
			'doc' => 'application/msword',
			'rtf' => 'application/rtf',
			'xls' => 'application/vnd.ms-excel',
			'ppt' => 'application/vnd.ms-powerpoint',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'odt' => 'application/vnd.oasis.opendocument.text',
			'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			'ged' => 'application/x-gedcom',
		);
		$ext = strtolower(array_pop(explode('.',$filename)));
		if (array_key_exists($ext, $mime_types)) {
			return $mime_types[$ext];
		} elseif (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME);
			$mimetype = finfo_file($finfo, $filename);
			finfo_close($finfo);
			return $mimetype;
		} else {
			return 'application/octet-stream';
		}
	}
}

//Compatibility for WP 2.9
if (!function_exists('get_user_meta')) {
	function get_user_meta ($user_id, $key, $single) {
		return get_metadata('user', $user_id, $key, $single);
	}
}

/* TO DO
========
Get it working with non-pretty permalinks
Find error in cemeteries page
Make it work with the home page
*/
?>