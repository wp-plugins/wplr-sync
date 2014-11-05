<?php
/*
Plugin Name: WP/LR Sync
Plugin URI: http://www.meow.fr
Description: Synchronize and maintain your photos between Lightroom and Wordpress.
Version: 0.2
Author: Jordy Meow
Author URI: http://www.meow.fr

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html

Originally developed for two of my websites: 
- Totoro Times (http://www.totorotimes.com) 
- Haikyo (http://www.haikyo.org)
*/

// Need 'class-IXR' for the 'IXR_Error' cass
require_once ABSPATH . WPINC . '/class-IXR.php';
require_once "vendor/phasher.class.php";
include "lrsync_lrinfo.php";
include "lrsync_core.php";
include "lrsync_rpc.php";
include "lrsync_admin.php";

function meow_wplrsync_activate() {
	global $wpdb;
	$table_name = $wpdb->prefix . "lrsync"; 
	$sql = "CREATE TABLE $table_name (
		id BIGINT(20) NOT NULL AUTO_INCREMENT,
		wp_id BIGINT(20) NULL,
		lr_id BIGINT(20) NULL,
		lr_file TINYTEXT NULL,
		lastsync DATETIME NULL,
		UNIQUE KEY id (id)
	);";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

function meow_wplrsync_uninstall() {
	global $wpdb;
	$table_name = $wpdb->prefix . "lrsync"; 
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_activation_hook( __FILE__, 'meow_wplrsync_activate' );
register_uninstall_hook( __FILE__, 'meow_wplrsync_uninstall' );

$GLOBALS['Meow_WPLR_Sync'] = get_option( 'wplr_tools_enabled' ) ? new Meow_WPLR_Sync_Admin : new Meow_WPLR_Sync_RPC;

?>
