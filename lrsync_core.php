<?php

class Meow_WPLR_Sync_Core {
	private $error;

	public function __construct() {
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
		add_filter( 'manage_media_columns', array( $this, 'manage_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'manage_media_custom_column' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'admin_head' ), 10, 2 );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/*
		INIT
	*/

	function plugins_loaded() {
		$plugins = get_option( 'wplr_plugins' );
		if ( is_array( $plugins ) ) {
			$dir = trailingslashit( plugin_dir_path( __FILE__ ) ) . trailingslashit( 'extensions' );
			$valid = array();
			$isdead = false;
			foreach ( $plugins as $plugin ) {
				if ( file_exists( trailingslashit( $dir ) . $plugin ) ) {
					include( trailingslashit( $dir ) . $plugin  );
					array_push( $valid, $plugin );
				}
				else
					$isdead = true;
			}
			if ( $isdead ) {
				update_option( 'wplr_plugins', $valid );
			}
		}
	}

	/*
		CORE
	*/

	function log( $data ) {
		if ( !get_option( 'wplr_debuglogs', false ) )
			return;
		$fh = fopen( trailingslashit( plugin_dir_path( __FILE__ ) ) . '/wplr-sync.log', 'a' );
		$date = date( "Y-m-d H:i:s" );
		fwrite( $fh, "$date: {$data}\n" );
		fclose( $fh );
	}

	function check_db() {
		global $wpdb;
		$tbl_m = $wpdb->prefix . 'lrsync_meta';
		if ( !$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = '%s' AND table_name = '%s';", $wpdb->dbname, $tbl_m ) ) ) {
			meow_wplrsync_activate();
		}
	}

	function reset_db() {
		do_action( 'wplr_reset' );
		meow_wplrsync_uninstall();
		meow_wplrsync_activate();
	}

	function get_error() {
		if ( $this->error ) {
			return $this->error;
		}
		else {
			return new IXR_Error( 401, __( 'Unknown error.' ) );
		}
	}

	function wpml_original_id( $wpid ) {
		if ( $this->wpml_media_is_installed() ) {
			global $sitepress;
			$language = $sitepress->get_default_language( $wpid );
			return icl_object_id( $wpid, 'attachment', true, $language );
		}
		return $wpid;
	}

	function wpml_media_is_installed() {
		return defined( 'WPML_MEDIA_VERSION' );
		//return function_exists( 'icl_object_id' ) && !class_exists( 'Polylang' );
	}

	function wpml_original_array( $wpids ) {
		if ( $this->wpml_media_is_installed() ) {
			for ($c = 0; $c < count( $wpids ); $c++ ) {
				$wpids[$c] = $this->wpml_original_id( $wpids[$c] );
			}
			$wpids = array_unique( $wpids );
		}
		return $wpids;
	}

	function get_meta_from_value( $name, $value ) {
		global $wpdb;
		$tbl_meta = $wpdb->prefix . "lrsync_meta";
		return $wpdb->get_val( $wpdb->prepare( "SELECT value FROM $tbl_meta WHERE name = %s AND value = %d", $name, $value ) );
	}

	function get_meta( $name, $id ) {
		global $wpdb;
		$tbl_meta = $wpdb->prefix . "lrsync_meta";
		return $wpdb->get_var( $wpdb->prepare( "SELECT value FROM $tbl_meta WHERE name = %s AND id = %d", $name, $id ) );
	}

	function set_meta( $name, $id, $value, $unique = false ) {
		global $wpdb;
		$tbl_meta = $wpdb->prefix . "lrsync_meta";
		if ( $unique ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl_meta WHERE name = %s AND id = %d", $name, $id ) );
			if ( $count > 0 ) {
				$wpdb->update( $tbl_meta, array( 'value' => $value ), array( 'name' => $name, 'id' => $id ), array( '%s' ), array( '%s', '%d' ) );
				return true;
			}
		}
		$wpdb->insert( $tbl_meta, array( 'name' => $name, 'id' => $id, 'value' => $value ) );
	}

	function delete_meta( $name, $id ) {
		global $wpdb;
		$tbl_meta = $wpdb->prefix . "lrsync_meta";
		$wpdb->query( $wpdb->prepare( "DELETE FROM $tbl_meta WHERE name = %s AND id = %d", $name, $id ) );
	}

	// Return SyncInfo for this WP ID
	function get_sync_info( $wpid ) {
		$wpid = $this->wpml_original_id( $wpid );
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$info = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE wp_id = %d", $wpid ), OBJECT );
		return $info;
	}

	function get_sync_info_from_lr_id( $lr_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$info = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lr_id ), OBJECT );
		return $info;
	}

	function delete_media( $lr_id, $wp_col_id = null ) {
		global $wpdb;
		$lrinfo = $this->get_sync_info_from_lr_id( $lr_id );

		// Delete relations in in collections
		$tbl_r = $wpdb->prefix . 'lrsync_relations';
		if ( !is_null( $wp_col_id ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $tbl_r WHERE wp_id = %d AND wp_col_id = %d", $lrinfo->wp_id, $wp_col_id ) );
			if ( $wp_col_id >= 0 )
				do_action( 'wplr_remove_media_from_collection', $lrinfo->wp_id, $wp_col_id );
		}

		// Delete media if it is not part of any collection
		$left = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl_r WHERE wp_id = %d", $lrinfo->wp_id ) );
		if ( $left < 1 ) {
			// Delete the media, it is not used anywhere
			$table_name = $wpdb->prefix . "lrsync";
			$sync_files = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lr_id), OBJECT );
			$delete_count = 0;
			foreach ( $sync_files as $sync ) {
				if ( wp_delete_attachment( $sync->wp_id ) ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE lr_id = %d", $lr_id ) );
					$delete_count++;
				}
			}
			if ( count( $sync_files ) < 1 ) {
				// There were no files to remove
				return true;
			}
			else if ( $delete_count > 0 ) {
				// Files were removed
				do_action( 'wplr_remove_media', $lrinfo->wp_id );
				return true;
			}
			else {
				// Nothing was removed, strangely
				$this->error = new IXR_Error( 403, __( "The attachment could not be removed." ) );
				return false;
			}
		}
		else {
			// Don't delete the media, it is used somewhere else
			return true;
		}
	}

	function delete_attachment( $wp_id ) {
		$wp_id = $this->wpml_original_id( $wp_id );
		$this->sync_media_tags( $wp_id );
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE wp_id = %d", $wp_id ) );
	}

	function unlink_media( $lr_id, $wp_id ) {
		$wp_id = $this->wpml_original_id( $wp_id );
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";

		// Remove media
		if ( $wp_id ) {
			$sync = $this->get_sync_info( $wp_id );
			if ( $sync ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE wp_id = %d", $sync->wp_id ) );
				return true;
			}
		}
		else {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE lr_id = %d", $sync->lr_id ) );
			return true;
		}

		$this->error = new IXR_Error( 403, __( "There is no link for this media." ) );
		return false;
	}

	function link_media( $lr_id, $wp_id ) {
		$wp_id = $this->wpml_original_id( $wp_id );
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		if ( empty( $wp_id ) ) {
			$this->error = new IXR_Error( 403, __( "The arguments lr_id and wp_id are required." ) );
			return false;
		}
		if ( !wp_attachment_is_image( $wp_id ) ) {
			$this->error = new IXR_Error( 403, __( "Attachment " . ($wp_id ? $wp_id : "[null]") . " does not exist or is not an image." ) );
			return false;
		}
		$sync = $this->get_sync_info( $wp_id );
		if ( !$sync ) {
			$wpdb->insert( $table_name,
				array(
					'wp_id' => $wp_id,
					'lr_id' => $lr_id,
					'lr_file' => null,
					'lastsync' => null
				)
			);
		}
		else {
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name
				SET lr_id = %d
				WHERE wp_id = %d", $lr_id, $wp_id )
			);
		}
		$sync = $this->get_sync_info( $wp_id );
		$info = Meow_WPLR_LRInfo::fromRow( $sync );
		return $info;
	}

	function list_sync_media() {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$sync_files = $wpdb->get_results( "SELECT * FROM $table_name WHERE lr_id >= 0 AND wp_id >= 0", OBJECT );
		$list = array();
		foreach ( $sync_files as $sync_file ) {
			$info = Meow_WPLR_LRInfo::fromRow( $sync_file );
			array_push( $list, $info );
		}
		return $list;
	}

	function update_metadata( $wp_id, $lrinfo, $isTranslation = false ) {
		// Update Title, Description and Caption

		$meta = null;
		if ( $isTranslation ) {
			$meta = get_post( $wp_id, ARRAY_A );
		}

		// Update Title, Caption and Desc (if needed)
		if ( $lrinfo->sync_title || $lrinfo->sync_caption || $lrinfo->sync_desc ) {
			$post = array( 'ID' => $wp_id );
			if ( $lrinfo->sync_title && ( !$meta || empty( $meta['post_title'] ) ) )
				$post['post_title'] = $lrinfo->lr_title;
			if ( $lrinfo->sync_desc && ( !$meta || empty( $meta['post_content'] ) ) )
				$post['post_content'] = $lrinfo->lr_desc;
			if ( $lrinfo->sync_caption && ( !$meta || empty( $meta['post_excerpt'] ) ) )
				$post['post_excerpt'] = $lrinfo->lr_caption;
			wp_update_post( $post );
		}

		// Update Alt Text if needed
		if ( $lrinfo->sync_alt_text ) {
			if ( $isTranslation )
				$meta_alt = get_post_meta( $wp_id, '_wp_attachment_image_alt', true );
			if ( !$isTranslation || empty( $meta_alt ) )
				update_post_meta( $wp_id, '_wp_attachment_image_alt', $lrinfo->lr_alt_text );
		}
	}

	function sync_media_tags( $wp_id, $tags = '' ) {
		$tags = str_replace( ' ,', ',', str_replace( ', ', ',', $tags ) );
		$oldTags = $this->get_meta( 'media_tags', $wp_id ) ? explode( ',', $this->get_meta( 'media_tags', $wp_id ) ) : array();
		$newTags = empty( $tags ) ? array() : explode( ',', $tags );

		// Set or delete meta
		if ( empty( $tags ) )
			$this->delete_meta( 'media_tags', $wp_id );
		else
			$this->set_meta( 'media_tags', $wp_id, $tags, true );

		// Call the extensions
		$toAdds = array_diff( $newTags, $oldTags );
		foreach ( $toAdds as $toAdd )
			do_action( 'wplr_add_media_tag', $wp_id, trim( $toAdd ) );
		$toDeletes = array_diff( $oldTags, $newTags  );
		foreach ( $toDeletes as $toDelete )
			do_action( 'wplr_remove_media_tag', $wp_id, trim( $toDelete ) );
		return true;
	}

	function sync_media_update( $lrinfo, $tmp_path, $sync ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$wp_id = $sync->wp_id;
		$meta = wp_get_attachment_metadata( $wp_id );
		$current_file = get_attached_file( $wp_id );

		// Support for WP Retina 2x
		if ( function_exists( 'wr2x_generate_images' ) )
			wr2x_delete_attachment( $wp_id );

		// The file doesn't exist anymore for some reason
		if ( !file_exists( $current_file ) ) {
			error_log( "WP/LR Sync: get_attached_file() returned empty. Assuming broken DB, delete link and continue." );
			$this->delete_attachment( $wp_id );
			return false;
		}

		$pathinfo = pathinfo( $current_file );
		if ( !isset( $pathinfo['dirname'] ) ) {
			error_log( "WP/LR Sync: pathinfo() failed in sync_media_update with " . $current_file );
			$this->error = new IXR_Error( 403, __( "Could not handle the file on the server-side." ) );
			return false;
		}
		$basepath = $pathinfo['dirname'];

		// Let's clean everything first
		if ( wp_attachment_is_image( $wp_id ) ) {
			$sizes = $this->get_image_sizes();
			foreach ($sizes as $name => $attr) {
				if (isset($meta['sizes'][$name]) && isset($meta['sizes'][$name]['file']) && file_exists( trailingslashit( $basepath ) . $meta['sizes'][$name]['file'] )) {
					$normal_file = trailingslashit( $basepath ) . $meta['sizes'][$name]['file'];
					$pathinfo = pathinfo( $normal_file );

					// Support for WP Retina 2x
					if ( function_exists( 'wr2x_generate_images' ) )
						$retina_file = trailingslashit( $pathinfo['dirname'] ) . $pathinfo['filename'] . wr2x_retina_extension() . $pathinfo['extension'];

					// Test if the file exists and if it is actually a file (and not a dir)
					// Some old WordPress Media Library are sometimes broken and link to directories
					if ( file_exists( $normal_file ) && is_file( $normal_file ) )
						unlink( $normal_file );

					// Support for WP Retina 2x
					if ( function_exists( 'wr2x_generate_images' ) && ( file_exists( $retina_file ) && is_file( $retina_file ) ) )
							unlink( $retina_file );
				}
			}
		}
		if ( file_exists( $current_file ) )
			unlink( $current_file );

		// Insert the new file and delete the temporary one
		copy( $tmp_path, $current_file );
		chmod( $current_file, 0644 );

		// Generate the images
		wp_update_attachment_metadata( $wp_id, wp_generate_attachment_metadata( $wp_id, $current_file ) );

		// Update metadata
		$this->update_metadata( $wp_id, $lrinfo );

		// If there are translations, maybe they need to be updated too!
		if ( $this->wpml_media_is_installed() ) {
			global $sitepress;
			$trid = $sitepress->get_element_trid( $wp_id, 'post_attachment' );
			$translations = $sitepress->get_element_translations( $trid, 'post_attachment' );
			foreach( $translations as $k => $v ) {
				if ( $v->element_id != $wp_id )
					$this->update_metadata( $v->element_id, $lrinfo, true );
			}
		}

		// Support for WP Retina 2x
		if ( function_exists( 'wr2x_generate_images' ) )
			wr2x_generate_images( wp_get_attachment_metadata( $wp_id ) );

		$wpdb->query( $wpdb->prepare( "UPDATE $table_name
			SET lr_file = %s, lastsync = NOW()
			WHERE lr_id = %d", $lrinfo->lr_file, $lrinfo->lr_id )
		);

		if ( !empty( $lrinfo->tags ) )
			$this->sync_media_tags( $wp_id, $lrinfo->tags );

		$tbl_r = $wpdb->prefix . "lrsync_relations";
		$gallery_ids = $wpdb->get_col( $wpdb->prepare( "SELECT wp_col_id FROM $tbl_r WHERE wp_id = %d", $wp_id ) );
		do_action( 'wplr_update_media', $wp_id, $gallery_ids );

		return true;
	}

	function sync_media_add( $lrinfo, $tmp_path ) {
		global $wpdb;
		$tbl_wplr = $wpdb->prefix . "lrsync";
		$upload_dir = wp_upload_dir();
		$newfile = wp_unique_filename( $upload_dir["path"], $lrinfo->lr_file );
		$newpath = trailingslashit( $upload_dir["path"] ) . $newfile;
		if ( !@rename( $tmp_path, $newpath ) )
		{
			$this->error = new IXR_Error( 403, __( "Could not copy the file." ) );
			return false;
		}
		$wp_upload_dir = wp_upload_dir();
		if ( !$wp_id = wp_insert_attachment( array(
			'guid' => $wp_upload_dir['url'] . '/' . basename( $newpath ),
			'post_title' => $lrinfo->lr_title,
			'post_content' => $lrinfo->lr_desc,
			'post_excerpt' => $lrinfo->lr_caption,
			'post_mime_type' => $lrinfo->type,
			'post_status' => "inherit",
		), $newpath ) ) {
			$this->error = new IXR_Error( 403, __( "Could not insert attachment for " . $newpath ) );
			return false;
		}
		$attach_data = wp_generate_attachment_metadata( $wp_id, $newpath );
		wp_update_attachment_metadata( $wp_id, $attach_data );

		// Create Alt Text
		update_post_meta( $wp_id, '_wp_attachment_image_alt', $lrinfo->lr_alt_text );

		// Support for WP Retina 2x
		if ( function_exists( 'wr2x_generate_images' ) ) {
			wr2x_generate_images( $attach_data );
		}

		$wpdb->insert( $tbl_wplr,
			array(
				'wp_id' => $wp_id,
				'lr_id' => ( $lrinfo->lr_id == "" || $lrinfo->lr_id == null ) ? -1 : $lrinfo->lr_id,
				'lr_file' => $lrinfo->lr_file,
				'lastsync' => current_time( 'mysql' )
			)
		);

		do_action( 'wplr_add_media', $wp_id );

		if ( !empty( $lrinfo->tags ) )
			$this->sync_media_tags( $wp_id, $lrinfo->tags );

		return true;
	}

	function sync_media( $lrinfo, $tmp_path, $wp_col_id = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$sync_files = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lrinfo->lr_id ), OBJECT );

		if ( $tmp_path == null || empty( $tmp_path ) ) {
			$this->error = new IXR_Error( 403, __( "The file was not uploaded." ) );
			return false;
		}

		// Never synced, create the attachment
		if ( !$sync_files ) {
			if ( !$this->sync_media_add( $lrinfo, $tmp_path ) )
				return false;
		}

		// Synced info found in DB, go through them
		else {
			$updates = 0;
			foreach ( $sync_files as $sync ) {
				if ( $this->sync_media_update( $lrinfo, $tmp_path, $sync ) )
					$updates++;
			}
			// In case DB is broken and no updates was made, we need to create the attachment
			if ( $updates == 0 ) {
				if ( !$this->sync_media_add( $lrinfo, $tmp_path ) )
					return false;
			}
		}
		if ( file_exists( $tmp_path ) )
			unlink( $tmp_path );

		// Returns only one result even if there are many.
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lrinfo->lr_id ), OBJECT );
		$info = Meow_WPLR_LRInfo::fromRow( $sync );

		// Handle the collection if any
		if ( !is_null( $wp_col_id ) ) {
			$this->add_media_to_collection( $sync, $wp_col_id );
		}

		return $info;
	}

	function get_image_sizes() {
		$sizes = array();
		global $_wp_additional_image_sizes;
		foreach (get_intermediate_image_sizes() as $s) {
			$crop = false;
			if (isset($_wp_additional_image_sizes[$s])) {
				$width = intval($_wp_additional_image_sizes[$s]['width']);
				$height = intval($_wp_additional_image_sizes[$s]['height']);
				$crop = $_wp_additional_image_sizes[$s]['crop'];
			} else {
				$width = get_option($s.'_size_w');
				$height = get_option($s.'_size_h');
				$crop = get_option($s.'_crop');
			}
			$sizes[$s] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
		}
		return $sizes;
	}

	// Returns link info for a file at this path
	function linkinfo_upload( $path, $meta = null ) {
		$I = PHasher::Instance();
		$exif = null;
		if ( $meta == null) {
			$meta = wp_read_image_metadata( $path );
			$exif = ( isset( $meta, $meta["created_timestamp"] ) && (int)$meta["created_timestamp"] > 0 ) ? date( "Y/m/d H:i:s", $meta["created_timestamp"] ) : null;
		}
		else if ( isset( $meta, $meta["image_meta"], $meta["image_meta"]["created_timestamp"] ) && (int)$meta["image_meta"]["created_timestamp"] > 0 ) {
			$exif = date( "Y/m/d H:i:s", $meta["image_meta"]["created_timestamp"] );
		}
		return array(
			'wp_phash' => $I->HashAsString( $I->HashImage( $path, 0, 0, 16 ), true ),
			'wp_exif' => $exif
		);
	}

	// Returns link info to help LR to find the original image
	function linkinfo_media( $wp_id ) {
		$wp_id = $this->wpml_original_id( $wp_id );
		if ( !wp_attachment_is_image( $wp_id ) ) {
			$this->error = new IXR_Error( 403, __( "Attachment " . ($wp_id ? $wp_id : "[null]") . " does not exist or is not an image." ) );
			return false;
		}
		$linkinfo = $this->linkinfo_upload( get_attached_file( $wp_id ), wp_get_attachment_metadata( $wp_id ) );
		return array(
			'wp_id' => $wp_id,
			'wp_url' => wp_get_attachment_url( $wp_id ),
			'wp_phash' => $linkinfo["wp_phash"],
			'wp_exif' => $linkinfo["wp_exif"]
		);
	}

	// Returns an array of wp_id linked to this lr_id
	function list_wpids( $lr_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$wp_ids = $wpdb->get_results( $wpdb->prepare( "SELECT p.wp_id FROM $table_name p WHERE p.lr_id = %d", $lr_id ) );
		return $wp_ids;
	}

	function list_duplicates() {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$images = $wpdb->get_results(
			"SELECT lr.lr_id, lr.lr_file, GROUP_CONCAT(lr.wp_id SEPARATOR ',') as wpids
			FROM $wpdb->posts p, $table_name lr
			WHERE p.ID = lr.wp_id AND lr.lr_id != 0
			GROUP BY lr.lr_id
			HAVING COUNT(p.ID) > 1
			ORDER BY lr.lr_id DESC", OBJECT );
		return $images;
	}

	function list_unlinks( $allfields = false ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$potentials = array();

		$whereIsOriginal = "";
		if ( $this->wpml_media_is_installed() ) {
			global $sitepress;
			$tbl_wpml = $wpdb->prefix . "icl_translations";
			$language = $sitepress->get_default_language();
			$whereIsOriginal = "AND p.ID IN (SELECT element_id FROM $tbl_wpml WHERE element_type = 'post_attachment' AND language_code = '$language') ";
		}

		if ( $allfields ) {
			$posts = $wpdb->get_results( "SELECT * FROM $wpdb->posts p
			WHERE post_status = 'inherit'
			AND post_mime_type = 'image/jpeg'
			AND p.ID NOT IN (SELECT wp_id FROM $table_name) " .
			$whereIsOriginal .
			"ORDER BY p.ID DESC" );
		}
		else {
			$posts = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p
			WHERE post_status = 'inherit'
			AND post_mime_type = 'image/jpeg'
			AND p.ID NOT IN (SELECT wp_id FROM $table_name) " .
			$whereIsOriginal .
			"ORDER BY p.ID DESC" );
		}

		foreach ( $posts as $post ) {
			if ( $allfields ) {
				if ( !wp_attachment_is_image( $post->ID ) )
					continue;
				array_push( $potentials, $post );
			}
			else {
				if ( !wp_attachment_is_image( $post ) )
					continue;
				array_push( $potentials, $post );
			}
		}
		return $potentials;
	}

	/*
		COLLECTIONS
	*/

	function add_media_to_collection( $lrinfo, $wp_col_id, $sort = 0 ) {
		global $wpdb;
		$tbl_r = $wpdb->prefix . 'lrsync_relations';
		$tbl_col = $wpdb->prefix . 'lrsync_collections';
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tbl_r WHERE wp_id = %d AND wp_col_id = %d", $lrinfo->wp_id, $wp_col_id ) );
		if ( $count < 1 ) {
			if ( $wpdb->insert( $tbl_r,
				array( 'wp_col_id' => $wp_col_id, 'wp_id' => $lrinfo->wp_id, 'sort' => $sort ),
				array( '%d', '%d', '%s' )
			) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $tbl_col SET lastsync = %s WHERE wp_col_id = %d", current_time( 'mysql' ), $wp_col_id ) );
				if ( $wp_col_id > -1 )
					do_action( "wplr_add_media_to_collection", $lrinfo->wp_id, $wp_col_id );
			}
			else {
				$this->error = new IXR_Error( 403, __( "Could not add media " . ( isset( $lrinfo->wp_id ) ? $lrinfo->wp_id : "[null]") . " to collection " . ( isset( $wp_col_id ) ? $wp_col_id : "[null]") . "." ) );
				return false;
			}
		}
	}

	function sync_collection( $collections ) {
		global $wpdb;
		$folder = null;
		$tbl_col = $wpdb->prefix . 'lrsync_collections';
		$this->log( "sync_collection with:" );
		$this->log( print_r( $collections, 1 ) );
		for ( $c = 1; $c <= count( $collections ); $c++) {
			$collection = &$collections[$c];
			$type = $collection['type'] == 'folder' ? 'folder' : 'collection';
			$parent_folder = ( !$folder || empty( $folder['wp_col_id'] ) ) ? null : (int)$folder['wp_col_id'];
			$row = empty( $collection['wp_col_id'] ) ? null : $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tbl_col c WHERE wp_col_id = %d AND lr_col_id = %d", $collection['wp_col_id'], $collection['lr_col_id'] ), OBJECT );
			if ( empty( $row ) ) {
				// Collection needs to be created in DB.
				if ( $wpdb->insert( $tbl_col,
					array(
						'lr_col_id' => $collection['lr_col_id'],
						'is_folder' => $type == 'folder' ? 1 : 0,
						'name' => $collection['name'],
						'lastsync' => current_time( 'mysql' )
					),
					array( '%d', '%d', '%s', '%s' )
				) ) {
					$collection['wp_col_id'] = $wpdb->insert_id;
					if ( !is_null( $parent_folder ) ) {
						$wpdb->query( $wpdb->prepare( "UPDATE $tbl_col
							SET wp_folder_id = %s
							WHERE wp_col_id = %d", $parent_folder, $collection['wp_col_id'] )
						);
					}
				}
				else {
					// Folder/Collection failed.
					$this->error = new IXR_Error( 403, __( "Could not create the folder/collection called " . ( isset( $collection['name'] ) ? $collection['name'] : "[null]") . "." ) );
					return false;
				}
				do_action( "wplr_create_$type", $collection['wp_col_id'], $parent_folder, array( 'name' => $collection['name'] ) );
			}
			else {
				// Collection exists in DB, check for changes.
				$collection['wp_col_id'] = $row->wp_col_id;
				if ( $collection['name'] != $row->name ) {
					$wpdb->query( $wpdb->prepare( "UPDATE $tbl_col
						SET name = %s
						WHERE wp_col_id = %d", $collection['name'], $row->wp_col_id )
					);
					do_action( "wplr_update_$type", $row->wp_col_id, array( 'name' => $collection['name'] ) );
				}
				if ( $parent_folder != $row->wp_folder_id ) {
					if ( is_null( $parent_folder ) )
						$wpdb->query( $wpdb->prepare( "UPDATE $tbl_col SET wp_folder_id = NULL WHERE wp_col_id = %d", $row->wp_col_id ) );
					else
						$wpdb->query( $wpdb->prepare( "UPDATE $tbl_col SET wp_folder_id = %d WHERE wp_col_id = %d", $parent_folder, $row->wp_col_id ) );
					do_action( "wplr_move_$type", $row->wp_col_id, $parent_folder, $row->wp_folder_id );
				}
			}
			$folder = $collection;
		}
		return $collections;
	}

	function delete_collection_recursively( $tbl_col, $tbl_r, $tbl_lr, $wp_col_id ) {
		global $wpdb;
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(wp_col_id) FROM $tbl_col WHERE wp_folder_id = %d", $wp_col_id ) );
		foreach ( $children as $kid ) {
			$res = $this->delete_collection_recursively( $tbl_col, $tbl_r, $tbl_lr, $kid );
			if ( !$res )
				return false;
		}
		$lr_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(lr_id) FROM $tbl_r r INNER JOIN $tbl_lr l ON r.wp_id = l.wp_id WHERE r.wp_col_id = %d", $wp_col_id ) );
		if ( !empty( $lr_ids ) ) {
			foreach ( $lr_ids as $lr_id ) {
				if ( !$this->delete_media( $lr_id, $wp_col_id ) ) {
					return false;
				}
			}
		}
		$collection = $wpdb->get_row( $wpdb->prepare( "SELECT wp_col_id, is_folder FROM $tbl_col WHERE wp_col_id = %d", $wp_col_id ), OBJECT, 0 );
		if ( !empty( $collection ) ) {
			$type = $collection->is_folder ? 'folder' : 'collection';
			do_action( "wplr_remove_{$type}", $wp_col_id );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $tbl_col WHERE wp_col_id = %d", $wp_col_id ) );
		}
		return true;
	}

	function delete_collection( $wp_col_id ) {
		global $wpdb;
		$folder = null;
		$tbl_col = $wpdb->prefix . 'lrsync_collections';
		$tbl_r = $wpdb->prefix . 'lrsync_relations';
		$tbl_lr = $wpdb->prefix . 'lrsync';
		return $this->delete_collection_recursively( $tbl_col, $tbl_r, $tbl_lr, $wp_col_id );
	}

	/*
		UTILS FUNCTIONS
	*/

	function get_upload_root()
	{
		$uploads = wp_upload_dir();
		return $uploads['basedir'];
	}

	// Converts PHP INI size type (e.g. 24M) to int
	function parse_ini_size( $size ) {
		$unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
		$size = preg_replace('/[^0-9\.]/', '', $size);
		if ( $unit )
			return round( $size * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
		else
			round( $size );
	}

	// This function should not work even with HVVM
	function b64_to_file( $str ) {

		// From version 1.3.4 (use uploads folder for tmp):
		if ( !file_exists( trailingslashit( $this->get_upload_root() ) . "wplr-tmp" ) )
			mkdir( trailingslashit( $this->get_upload_root() ) . "wplr-tmp" );
		$file = tempnam( trailingslashit( $this->get_upload_root() ) . "wplr-tmp", "wplr_" );

		// Before version 1.3.4:
		//$file = tempnam( sys_get_temp_dir(), "wplr" );

		$ifp = fopen( $file, "wb" );
		fwrite( $ifp, base64_decode( $str ) );
		fclose( $ifp );
		chmod( $file, 0664 );
		return $file;
	}

	// function b64_to_file( $str ) {
	// 	$tmpHandle = tmpfile();
	// 	$metaDatas = stream_get_meta_data($tmpHandle);
	// 	$file = $metaDatas['uri'];
	// 	fclose($tmpHandle);
	// 	$ifp = fopen( $file, "wb" );
	// 	fwrite( $ifp, base64_decode( $str ) );
	// 	fclose( $ifp );
	// 	return $file;
	// }

	/*
		MEDIA LIBRARY COLUMN
	*/

	function html_for_media( $wpid, $sync = null ) {
		$wpid = $this->wpml_original_id($wpid);
		$html = "";
		if ( !$sync ) {
			$html .= "<div>Unknown</div>";
			$html .= "<div>
			<small>LR ID:
				<input type='text' class='wplr-sync-lrid-input wplrsync-link-" . $wpid . "'></input>
				<span class='wplr-button' onclick='wplrsync_link($wpid)'>Link</span>
			</small></div>";
		}
		else {
			if ( $sync->lr_id > 0 ) {
				$html .= "<div style='color: #006EFF;'>Enabled</div>";

				if ( !strtotime( $sync->lastsync ) || $sync->lastsync == "0000-00-00 00:00:00" )
					$html .= "<div style='color: #0BF;'><small>Never synced.</small></div>";
				else {
					if ( date('Ymd') == date('Ymd', strtotime( $sync->lastsync )) )
						$html .= "<div><small>Synced at " . date("g:ia", strtotime( $sync->lastsync )) . "</small></div>";
					else
						$html .= "<div><small>Synced on " . date("Y/m/d", strtotime( $sync->lastsync )) . "</small></div>";
				}
				$html .= "<div><small>LR ID: " . $sync->lr_id . "</small></div>";
			}
			else if ( $sync->lr_id == 0 ) {
				$html .= "<div style='color: gray;'>Ignored</div>";
			}
			$html .= "<small><span class='wplr-link-undo' onclick='wplrsync_unlink($sync->lr_id, $wpid)'>(undo)</span></small>";
		}
		return $html;
	}

	function wplrsync_unlink() {
		$this->wplrsync_ajax_link_unlink(true);
	}

	function wplrsync_link() {
		$this->wplrsync_ajax_link_unlink();
	}

	function wplrsync_ajax_link_unlink( $is_unlink = false ) {
		if ( !current_user_can('upload_files') ) {
			echo json_encode( array( 'success' => false, 'message' => "You do not have the roles to perform this action." ) );
			die;
		}
		if ( !isset( $_POST['lr_id'] ) || $_POST['lr_id'] == "" || !isset( $_POST['wp_id'] ) || empty( $_POST['wp_id'] ) ) {
			echo json_encode( array( 'success' => false, 'message' => "Some information is missing." ) );
			die;
		}

		$lr_id = intval( $_POST['lr_id'] );
		$wp_id = $this->wpml_original_id( intval( $_POST['wp_id'] ) );

		$sync = null;
		if ( $is_unlink ) {

			if ( $this->unlink_media( $lr_id, $wp_id ) ) {
				echo json_encode( array(
					'success' => true,
					'html' => $this->html_for_media( $wp_id, null )
				) );
			}
			else {
				echo json_encode( array(
					'success' => false,
					'message' => $this->error || "Unknown error."
				) );
			}
		}
		else {
			$sync = $this->link_media( $lr_id, $wp_id );
			if ( $sync ) {
				echo json_encode( array(
					'success' => true,
					'html' => $this->html_for_media( $wp_id, $sync )
				) );
			}
			else {
				echo json_encode( array(
					'success' => false,
					'message' => $this->error || "Unknown error."
				) );
			}
		}
		die();
	}

	function admin_head() {
		echo '
			<style type="text/css">

				.wplr-button {
					background: #3E79BB;
					color: white;
					display: inline;
					padding: 2px 8px;
					border-radius: 7px;
					text-transform: uppercase;
					margin-left: 1px;
				}

				.wplr-button:hover {
					cursor: pointer;
					background: #5D93CF;
				}

				.wplr-link-undo {
					color: #5E5E5E;
				}

				.wplr-link-undo:hover {
					cursor: pointer;
					color: #2ea2cc;
				}

				.wplr-sync-info {
					line-height: 14px;
				}

				.wplr-sync-lrid-input {
					width: 56px;
					font-size: 10px;
					font-weight: bold;
					color: black !important;
				}

			</style>

			<script>

				function wplrsync_handle_response( wp_id, response ) {
					reply = jQuery.parseJSON(response);
					if ( reply.success ) {
						// Remove box (if in WP/LR Dashboard)
						jQuery("#wplr-image-box-" + wp_id).remove();
						// Update row (if in Media Library)
						jQuery(".wplrsync-media-" + wp_id).html(reply.html);
					}
					else {
						alert(reply.message);
					}
				}

				function wplrsync_unlink( lr_id, wp_id ) {
					var data = { action: "wplrsync_unlink", lr_id: lr_id, wp_id: wp_id };
					jQuery.post(ajaxurl, data, function (response) {
						wplrsync_handle_response( wp_id, response );
					});
				}

				function wplrsync_link( wp_id, ignore ) {
					if (!ignore) {
						lr_id = jQuery(".wplrsync-link-" + wp_id).val();
					}
					else {
						lr_id = 0;
					}
					var data = { action: "wplrsync_link", lr_id: lr_id, wp_id: wp_id };
					jQuery.post(ajaxurl, data, function (response) {
						wplrsync_handle_response( wp_id, response );
					});
				}
			</script>
		';
	}

	function manage_media_columns( $cols ) {
		$cols["WP/LR Sync"] = "LR Sync";
		return $cols;
	}

	function manage_media_custom_column( $column_name, $wpid ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		if ( $column_name != 'WP/LR Sync' )
			return;
		$meta = wp_get_attachment_metadata( $wpid );
		if ( !($meta && isset( $meta['width'] ) && isset( $meta['height'] )) ) {
			return;
		}
		$wpid = $this->wpml_original_id( $wpid );
		$sync = $this->get_sync_info( $wpid );
		echo "<div class='wplr-sync-info wplrsync-media-" . $wpid . "'>";
		echo $this->html_for_media( $wpid, $sync );
		echo "</div>";
	}
}

?>
