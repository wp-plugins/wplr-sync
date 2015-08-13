<?php
/*
Plugin Name: WP/LR Sync
Plugin URI: http://www.meow.fr
Description: Synchronize and maintain your photos and collections between Lightroom and Wordpress.
Version: 2.0.0
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
include "lrsync_api.php";
include "lrsync_admin.php";

function meow_wplrsync_activate() {
	global $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$tbl_lrsync = $wpdb->prefix . "lrsync";
	$sql = "CREATE TABLE $tbl_lrsync (
		id BIGINT(20) NOT NULL AUTO_INCREMENT,
		wp_id BIGINT(20) NULL,
		lr_id BIGINT(20) NULL,
		lr_file TINYTEXT NULL,
		lastsync DATETIME NULL,
		UNIQUE KEY id (id)
	);";
	dbDelta($sql);
	$tbl_collections = $wpdb->prefix . "lrsync_collections";
	$isNewToCollections = ( $wpdb->get_var("SHOW TABLES LIKE '$tbl_collections'") != $tbl_collections );
	$sql = "CREATE TABLE $tbl_collections (
		wp_col_id BIGINT(20) NOT NULL AUTO_INCREMENT,
		lr_col_id BIGINT(20) NULL,
		wp_folder_id BIGINT(20) NULL,
		name TINYTEXT NULL,
		is_folder TINYINT(1) NOT NULL DEFAULT 0,
		lastsync DATETIME NULL,
		UNIQUE KEY id (wp_col_id)
	);";
	dbDelta($sql);
	$tbl_relations = $wpdb->prefix . "lrsync_relations";
	$sql = "CREATE TABLE $tbl_relations (
		wp_col_id BIGINT(20) NULL,
		wp_id BIGINT(20) NULL,
		sort INT(11) DEFAULT 0,
		UNIQUE KEY (wp_col_id, wp_id)
	);";
	dbDelta($sql);
	$tbl_meta = $wpdb->prefix . "lrsync_meta";
	$isNewToCollections = ( $wpdb->get_var("SHOW TABLES LIKE '$tbl_meta'") != $tbl_meta );
	$sql = "CREATE TABLE $tbl_meta (
		meta_id BIGINT(20) NOT NULL AUTO_INCREMENT,
		name TINYTEXT NULL,
		id BIGINT(20) NULL,
		value LONGTEXT NULL,
		UNIQUE KEY id (meta_id)
	);";
	dbDelta($sql);
	// If this install is new to Collections, insert all the linked
	// media in the -1 collection (default one)
	if ( $isNewToCollections ) {
		$wpdb->query( "INSERT INTO $tbl_relations (wp_col_id, wp_id, sort) SELECT -1, wp_id, 0 FROM $tbl_lrsync" );
	}
}

function meow_wplrsync_uninstall() {
	// Better to avoid removing the table...
	global $wpdb;
	$tbl_col = $wpdb->prefix . 'lrsync_collections';
	$tbl_r = $wpdb->prefix . 'lrsync_relations';
	$tbl_m = $wpdb->prefix . 'lrsync_meta';
	$tbl_lr = $wpdb->prefix . 'lrsync';
	$wpdb->query( "DROP TABLE IF EXISTS $tbl_col" );
	$wpdb->query( "DROP TABLE IF EXISTS $tbl_r" );
	$wpdb->query( "DROP TABLE IF EXISTS $tbl_lr" );
	$wpdb->query( "DROP TABLE IF EXISTS $tbl_m" );
}

register_activation_hook( WP_PLUGIN_DIR . '/wplr-sync/wp-lrsync.php', 'meow_wplrsync_activate' );
//register_uninstall_hook( WP_PLUGIN_DIR . '/wplr-sync/wp-lrsync.php', 'meow_wplrsync_uninstall' );

global $wplr;
$wplr = new Meow_WPLR_Sync_Admin;

?>
