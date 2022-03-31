<?php
/************************************************
*                                               *
*               WIDGET FUNCTIONS                *
*                                               *
* mbtng_widget_init                             *
* mbtng_check_parent                            *
* mbtng_display_widget                          *
* mbtng_output_search                           *
* mbtng_output_menu                             *
* mbtng_output_template_menu                    *
* mbtng_base_url                                *

************************************************/
add_action('widgets_init', 'mbtng_widget_init');


// Initialise the widget
function mbtng_widget_init() {
	wp_register_sidebar_widget('mbtng_output_search', 'TNG search', 'mbtng_output_search', array('classname' => 'mbtng_output_search', 'description' => 'Displays the TNG search in the sidebar.'));
	wp_register_sidebar_widget('mbtng_output_menu', 'TNG menu', 'mbtng_output_menu', array('classname' => 'mbtng_output_menu', 'description' => 'Displays the TNG menu in the sidebar.'));
}

// Returns true if page is descendant of TNG page
function mbtng_check_parent($check_id = -10) {
	global $post, $wpdb;
	if ($check_id == -10)
		$check_id = $post->ID;
	if ($check_id == get_option('mbtng_wordpress_page'))
		return true;
	elseif ($check_id == 0)
		return false;
	else {
		$parent = $wpdb->get_var("SELECT post_parent FROM {$wpdb->prefix}posts WHERE id='{$check_id}'");
		return mbtng_check_parent($parent);
	}
}

// Returns true if the widgets should be displayed
function mbtng_display_widget() {
	if (mbtng_display_page())
		return true;
	else
		return mbtng_check_parent();
}

//Outputs the TNG search in the sidebar
function mbtng_output_search ($args) {
	global $languages_path;
// Change the next line to "if (!mbtng_display_widget(() {"
// to show the TNG search in the sidebar of NON-TNG pages
	if (mbtng_display_widget()) {
		extract($args);
		$tng_folder = get_option('mbtng_path');
		chdir($tng_folder);
		include('begin.php');
		include_once($cms['tngpath'] . "genlib.php");
		include($cms['tngpath'] . "getlang.php");
		include($cms['tngpath'] . "{$mylanguage}/text.php");
		echo $before_widget;
		echo $before_title.'Genealogy Database Search'.$after_title;
		$base_url = mbtng_base_url();
		echo "<form action=\"{$base_url}search.php\" method=\"post\">\n";
		echo "<table class=\"menuback\">\n";
		echo "<tr><td><span class=\"normal\">{$text['mnulastname']}:<br /><input type=\"text\" name=\"mylastname\" class=\"searchbox\" size=\"14\" /></span></td></tr>\n";
		echo "<tr><td><span class=\"normal\">{$text['mnufirstname']}:<br /><input type=\"text\" name=\"myfirstname\" class=\"searchbox\" size=\"14\" /></span></td></tr>\n";
		echo "<tr><td><input type=\"hidden\" name=\"mybool\" value=\"AND\" /><input type=\"submit\" name=\"search\" value=\"{$text['mnusearchfornames']}\" class=\"small\" /></td></tr>\n";
		echo "</table>\n";
		echo "</form>\n";
		echo "<ul>\n";
		echo "<li style=\"font-weight:bold\"><a href=\"{$base_url}searchform.php\">{$text['mnuadvancedsearch']}</a></li>\n";
		echo "</ul>\n";
		echo $after_widget;
	}
}

//Outputs the TNG menu in the sidebar
function mbtng_output_menu ($args) {
// John Lisle commented out next line, added lines 2 and 3 after this
//	global $allow_admin, $languages_path; 
	global $languages_path; // John Lisle
	$allow_admin = $_SESSION['allow_admin']; // John Lisle
//  the next line is to display the widgets NOT on the TNG page
	if (!mbtng_display_widget()) {
		extract($args);
		$tng_folder = get_option('mbtng_path');
		chdir($tng_folder);
		include('begin.php');
		include_once($cms['tngpath'] . "genlib.php");
		include($cms['tngpath'] . "getlang.php");
		include($cms['tngpath'] . "{$mylanguage}/text.php");
		echo $before_widget;
		echo $before_title.'Genealogy Menu'.$after_title;
		$base_url = mbtng_base_url();
		echo "<ul>\n";
		echo "<li class=\"surnames\" style=\"font-weight:bold\"><a href=\"{$base_url}surnames.php\">{$text['mnulastnames']}</a></li>\n";
		echo "</ul>\n";
		echo "<ul style=\"margin-top:0.75em\">\n";
		echo "<li class=\"whatsnew\"><a href=\"{$base_url}whatsnew.php\">{$text['mnuwhatsnew']}</a></li>\n";
		echo "<li class=\"mostwanted\"><a href=\"{$base_url}mostwanted.php\">{$text['mostwanted']}</a></li>\n";
		echo "<li class=\"media\"><a href=\"{$base_url}browsemedia.php\">{$text['allmedia']}</a>\n";
			echo "<ul>\n";
			echo "<li class=\"photos\"><a href=\"{$base_url}browsemedia.php?mediatypeID=photos\">{$text['mnuphotos']}</a></li>\n";
			echo "<li class=\"histories\"><a href=\"{$base_url}browsemedia.php?mediatypeID=histories\">{$text['mnuhistories']}</a></li>\n";
			echo "<li class=\"documents\"><a href=\"{$base_url}browsemedia.php?mediatypeID=documents\">{$text['documents']}</a></li>\n";
			echo "<li class=\"videos\"><a href=\"{$base_url}browsemedia.php?mediatypeID=videos\">{$text['videos']}</a></li>\n";
			echo "<li class=\"recordings\"><a href=\"{$base_url}browsemedia.php?mediatypeID=recordings\">{$text['recordings']}</a></li>\n";
			echo "</ul></li>";
		echo "<li class=\"albums\"><a href=\"{$base_url}browsealbums.php\">{$text['albums']}</a></li>\n";
		echo "<li class=\"cemeteries\"><a href=\"{$base_url}cemeteries.php\">{$text['mnucemeteries']}</a></li>\n";
		echo "<li class=\"heastones\"><a href=\"{$base_url}browsemedia.php?mediatypeID=headstones\">{$text['mnutombstones']}</a></li>\n";
		echo "<li class=\"places\"><a href=\"{$base_url}places.php\">{$text['places']}</a></li>\n";
		echo "<li class=\"notes\"><a href=\"{$base_url}browsenotes.php\">{$text['notes']}</a></li>\n";
		echo "<li class=\"anniversaries\"><a href=\"{$base_url}anniversaries.php\">{$text['anniversaries']}</a></li>\n";
		echo "<li class=\"reports\"><a href=\"{$base_url}reports.php\">{$text['mnureports']}</a></li>\n";
		echo "<li class=\"sources\"><a href=\"{$base_url}browsesources.php\">{$text['mnusources']}</a></li>\n";
		echo "<li class=\"repos\"><a href=\"{$base_url}browserepos.php\">{$text['repositories']}</a></li>\n";
		echo "<li class=\"trees\"><a href=\"{$base_url}browsetrees.php\">{$text['mnustatistics']}</a></li>\n";
		echo "<li class=\"language\"><a href=\"{$base_url}changelanguage.php\">{$text['mnulanguage']}</a></li>\n";
		if ($allow_admin) {
			echo "<li class=\"showlog\"><a href=\"{$base_url}showlog.php\">{$text['mnushowlog']}</a></li>\n";
			echo "<li class=\"admin\"><a href=\"{$base_url}admin.php\">{$text['mnuadmin']}</a></li>\n";
		}
		echo "<li class=\"bookmarks\"><a href=\"{$base_url}bookmarks.php\">{$text['bookmarks']}</a></li>\n";
		echo "<li class=\"suggest\"><a href=\"{$base_url}suggest.php\">{$text['contactus']}</a></li>\n";
		echo "</ul>\n";
		echo "<ul style=\"margin-top:0.75em\">\n";
		if (!is_user_logged_in()) {
			echo "<li class=\"register\" style=\"font-weight:bold\"><a href=\"{$base_url}newacctform.php\">{$text['mnuregister']}</a></li>\n";
			echo "<li class=\"login\" style=\"font-weight:bold\"><a href=\"{$base_url}login.php\">{$text['mnulogon']}</a></li>\n";
		} else {
			if (function_exists('wp_logout_url'))
				echo "<li class=\"logout\" style=\"font-weight:bold\"><a href=\"".html_entity_decode(wp_logout_url())."\">{$text['logout']}</a></li>\n";
			else
				echo "<li class=\"logout\" style=\"font-weight:bold\"><a href=\"".trailingslashit(get_bloginfo('wpurl'))."wp-login.php?action=logout"."\">{$text['logout']}</a></li>\n";
		}
		echo "</ul>";
		echo $after_widget;
	}
}