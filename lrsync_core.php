<?php

class Meow_WPLR_Sync_Core {
	private $error;

	public function __construct() {
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
		add_filter( 'manage_media_columns', array( $this, 'manage_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'manage_media_custom_column' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'admin_head' ), 10, 2 );
		add_action( 'wp_ajax_wplrsync_link', array( $this, 'wplrsync_link' ) );
		add_action( 'wp_ajax_wplrsync_unlink', array( $this, 'wplrsync_unlink' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}

	/*
		OPTIONS
	*/

	function admin_init() {
		add_settings_section( "wplrsync_media_options", "WP/LR Sync", 
			array( $this, 'media_options_callback' ), "media" );
		add_settings_field( 'wplr_tools_enabled', "WPLR Tools", 
			array( $this, 'toggle_content_callback' ), "media", 'wplrsync_media_options',
			array( "Enable" ) );
		register_setting( 'media', 'wplr_tools_enabled' );
	}

	function media_options_callback() {
		echo "<p>Those settings should be used for debugging purposes only.</p>";
	}

	function toggle_content_callback( $args ) {
		$html = '<input type="checkbox" id="wplr_tools_enabled" name="wplr_tools_enabled" value="1" ' . checked( 1, get_option( 'wplr_tools_enabled' ), false ) . '/>'; 
		$html .= '<label for="wplr_tools_enabled"> '  . $args[0] . '</label>';
		echo $html;
	}

	function admin_init_options( $args ) {

	}

	/*
		CORE
	*/

	function reset_db() {
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

	function delete_media( $lr_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lr_id), OBJECT );
		if ( $sync ) {
			if ( wp_delete_attachment( $sync->wp_id ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE lr_id = %d", $lr_id ) );
				return true;
			}
		}
		$this->error = new IXR_Error( 403, __( "The attachment could not be removed." ) );
		return false;
	}

	function delete_attachment( $wp_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE wp_id = %d", $wp_id), OBJECT );
		if ( $sync ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE lr_id = %d", $sync->lr_id ) );
		}
	}

	function unlink_media( $lr_id, $wp_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d AND wp_id = %d", $lr_id, $wp_id), OBJECT );
		if ( $sync ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE lr_id = %d AND wp_id = %d", $sync->lr_id, $sync->wp_id ) );
			return true;
		}
		$this->error = new IXR_Error( 403, __( "There is no link for this media." ) );
		return false;
	}

	function link_media( $lr_id, $wp_id ) {
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
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE wp_id = %d", $wp_id), OBJECT );
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
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE wp_id = %d", $wp_id), OBJECT );
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

	function sync_media( $lrinfo, $tmp_path ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lrinfo->lr_id ), OBJECT );
		
		if ( $tmp_path == null || empty( $tmp_path ) ) {
			$this->error = new IXR_Error( 403, __( "The file was not uploaded." ) );
			return false;
		}

		if ( !$sync ) {
			$upload_dir = wp_upload_dir();
			$newfile = wp_unique_filename( $upload_dir["path"], $lrinfo->lr_file );
			$newpath = trailingslashit( $upload_dir["path"] ) . $newfile;
			if ( !@rename( $tmp_path, $newpath ) )
			{
				$this->error = new IXR_Error( 403, __( "Could not move the file." ) );
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

			$wpdb->insert( $table_name, 
				array( 
					'wp_id' => $wp_id,
					'lr_id' => ( $lrinfo->lr_id == "" || $lrinfo->lr_id == null ) ? -1 : $lrinfo->lr_id,
					'lr_file' => $lrinfo->lr_file,
					'lastsync' => current_time('mysql')
				) 
			);
		}
		else {
			$wp_id = $sync->wp_id;
			$meta = wp_get_attachment_metadata( $sync->wp_id );
			$current_file = get_attached_file( $sync->wp_id );
			
			// Support for WP Retina 2x
			if ( function_exists( 'wr2x_generate_images' ) )
				wr2x_delete_attachment( $sync->wp_id );
			
			$pathinfo = pathinfo( $current_file );
			$basepath = $pathinfo['dirname'];

			// Let's clean everything first
			if ( wp_attachment_is_image( $sync->wp_id ) ) {
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
			if ( file_exists($current_file) )
				unlink( $current_file );

			// Insert the new file and delete the temporary one
			rename( $tmp_path, $current_file );
			chmod( $current_file, 0644 );

			// Generate the images
			wp_update_attachment_metadata( $sync->wp_id, wp_generate_attachment_metadata( $sync->wp_id, $current_file ) );

			// Update Title, Description and Caption
			if ( $lrinfo->sync_title || $lrinfo->sync_caption || $lrinfo->sync_desc ) {
				$post = array( 'ID' => $wp_id );
				if ( $lrinfo->sync_title )
					$post['post_title'] = $lrinfo->lr_title;
				if ( $lrinfo->sync_desc )
					$post['post_content'] = $lrinfo->lr_desc;
				if ( $lrinfo->sync_caption )
					$post['post_excerpt'] = $lrinfo->lr_caption;
				wp_update_post( $post );
			}
			
			// Update Alt Text if needed
			if ( $lrinfo->sync_alt_text )
					update_post_meta( $wp_id, '_wp_attachment_image_alt', $lrinfo->lr_alt_text );

			// Support for WP Retina 2x
			if ( function_exists( 'wr2x_generate_images' ) )
				wr2x_generate_images( wp_get_attachment_metadata( $sync->wp_id ) );

			$wpdb->query( $wpdb->prepare( "UPDATE $table_name 
				SET lr_file = %s, lastsync = NOW()
				WHERE lr_id = %d", $lrinfo->lr_file, $lrinfo->lr_id ) 
			);
		}

		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE lr_id = %d", $lrinfo->lr_id ), OBJECT );
		$info = Meow_WPLR_LRInfo::fromRow( $sync );
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

	function list_unlinks( $allfields = false ) {
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$potentials = array();

		if ( $allfields ) {
			$posts = $wpdb->get_results( "SELECT * FROM $wpdb->posts p 
			WHERE post_status = 'inherit' 
			AND post_mime_type = 'image/jpeg'
			AND p.ID NOT IN (SELECT wp_id FROM $table_name)
			ORDER BY p.ID DESC" );
		}
		else {
			$posts = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p 
			WHERE post_status = 'inherit' 
			AND post_mime_type = 'image/jpeg'
			AND p.ID NOT IN (SELECT wp_id FROM $table_name)
			ORDER BY p.ID DESC" );
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
		UTILS FUNCTIONS
	*/

	function b64_to_file( $str ) {
		$tmpHandle = tmpfile();
		$metaDatas = stream_get_meta_data($tmpHandle);
		$file = $metaDatas['uri'];
		fclose($tmpHandle);
		$ifp = fopen( $file, "wb" );
		fwrite( $ifp, base64_decode( $str ) );
		fclose( $ifp );
		return $file;
	}

	/*
		MEDIA LIBRARY COLUMN
	*/

	function html_for_media( $wpid, $sync = null ) {
		$html = "";
		if ( !$sync ) {
			$html .= "<div>Unknown</div>";
			$html .= "<div>
			<small>LR ID: 
				<input type='text' class='wplr-sync-lrid-input' id='wplrsync-link-" . $wpid . "'></input>
				<span class='wplr-sync-ok-button' onclick='wplrsync_link($wpid)'>Link</span>
			</small></div>";
		}
		else {
			if ( $sync->lr_id > 0 ) {
				$html .= "<div style='color: #006EFF;'>Enabled</div>";
				
				if ( !strtotime( $sync->lastsync ) )
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
			$html .= "<small><span class='wplr-sync-ok-button' onclick='wplrsync_unlink($sync->lr_id, $wpid)'>(undo)</span></small>";
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
		$wp_id = intval( $_POST['wp_id'] );

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

				.wplr-sync-info {
					line-height: 14px;
				}

				.wplr-sync-lrid-input {
					width: 60px;
					font-size: 10px;
					font-weight: bold;
					color: black !important;
				}

				.wplr-sync-ok-button {
					color: #5E5E5E;
				}

				.wplr-sync-ok-button:hover {
					cursor: pointer;
					color: #2ea2cc;
				}

			</style>

			<script>

				function wplrsync_handle_response( wp_id, response ) {
					reply = jQuery.parseJSON(response);
					if ( reply.success ) {
						// Remove box (if in WP/LR Dashboard)
						jQuery("#wplr-image-box-" + wp_id).remove();
						// Update row (if in Media Library)
						jQuery("#wplrsync-media-" + wp_id).html(reply.html);
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

				function wplrsync_link( wp_id ) {
					lr_id = jQuery("#wplrsync-link-" + wp_id).val();
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
		$sync = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE wp_id = %d", $wpid ), OBJECT );
		
		echo "<div id='wplrsync-media-" . $wpid . "' class='wplr-sync-info'>";
		echo $this->html_for_media( $wpid, $sync );
		echo "</div>";

	}
}

?>