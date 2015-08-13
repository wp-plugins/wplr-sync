<?php

class Meow_WPLR_Sync_Admin extends Meow_WPLR_Sync_RPC {

	public function __construct() {
		parent::__construct();
		if ( is_admin() ) {
			$this->check_db();
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'wp_ajax_wplrsync_link', array( $this, 'wplrsync_link' ) );
			add_action( 'wp_ajax_wplrsync_unlink', array( $this, 'wplrsync_unlink' ) );
			add_action( 'wp_ajax_wplrsync_clean', array( $this, 'wplrsync_clean' ) );
			add_action( 'wp_ajax_wplrsync_extensions_reset', array( $this, 'wplrsync_extensions_reset' ) );
			add_action( 'wp_ajax_wplrsync_extensions_init', array( $this, 'wplrsync_extensions_init' ) );
			add_action( 'wp_ajax_wplrsync_extensions_query', array( $this, 'wplrsync_extensions_query' ) );
		}
	}

	function admin_menu() {
		require( 'meow_footer.php' );

		// Standard menu
		add_menu_page( 'WP/LR', 'WP/LR Sync', 'manage_options', 'wplr-main-menu', array( $this, 'admin_extensions' ), 'dashicons-camera', 82 );

		// Extensions
		add_submenu_page( 'wplr-main-menu', 'Extensions', 'Extensions', 'manage_options', 'wplr-main-menu', array( $this, 'admin_extensions' ) );
		add_settings_section( 'wplr_extensions', null, array( $this, 'admin_extension_intro' ), 'wplr-main-menu' );
		add_settings_field( 'wplr_plugins', "Extensions", array( $this, 'admin_extensions_callback' ), 'wplr-main-menu', 'wplr_extensions' );
		add_settings_field( 'wplr_maintenance', "Maintenance", array( $this, 'admin_maintenance_callback' ), 'wplr-main-menu', 'wplr_extensions' );
		register_setting( 'wplr-extensions', 'wplr_plugins' );

		// Settings
		add_submenu_page( 'wplr-main-menu', 'Settings', 'Settings', 'manage_options', 'wplr-settings-menu', array( $this, 'admin_settings' ) );
		add_settings_section( 'wplr_settings', null, array( $this, 'admin_settings_intro' ), 'wplr-settings-menu' );
		//add_settings_field( 'wplr_protocol', "Debugging Protocol", array( $this, 'admin_protocol_callback' ), 'wplr-settings-menu', 'wplr_settings', array( "HTTP API", "XML/RPC" ) );
		add_settings_field( 'wplr_debugging_enabled', "Debugging Tools", array( $this, 'admin_debugging_callback' ), 'wplr-settings-menu', 'wplr_settings', array( "Enable" ) );
		add_settings_field( 'wplr_debuglogs', "Advanced Logs", array( $this, 'admin_debuglogs_callback' ), 'wplr-settings-menu', 'wplr_settings', array( "Enable" ) );
		//register_setting( 'wplr-settings', 'wplr_protocol' );
		register_setting( 'wplr-settings', 'wplr_debuglogs' );
		register_setting( 'wplr-settings', 'wplr_debugging_enabled' );



		// Tools
		add_submenu_page( 'wplr-main-menu', 'Media Tools', 'Media Tools', 'manage_options', 'wplr-tools-menu', array( $this, 'admin_tools' ) );

		// Debug
		if ( get_option( 'wplr_debugging_enabled' ) )
			add_submenu_page( 'wplr-main-menu', 'Debugging Tools', 'Debugging Tools', 'manage_options', 'wplrsync-debug-menu', array( $this, 'admin_debug' ) );
	}

	function admin_settings() {
		?>
		<div class="wrap">
		<?php jordy_meow_donation( true ); ?>
		<h2>Settings | WP/LR Sync <?php by_jordy_meow(); ?></h2>
		<form method="post" action="options.php">
				<?php settings_fields( 'wplr-settings' ); ?>
		    <?php do_settings_sections( 'wplr-settings-menu' ); ?>
		    <?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	function admin_extensions() {
		?>
		<div class="wrap">
		<?php jordy_meow_donation( true ); ?>
		<h2>Extensions | WP/LR Sync <?php by_jordy_meow(); ?></h2>
		<form method="post" action="options.php">
				<?php settings_fields( 'wplr-extensions' ); ?>
				<?php do_settings_sections( 'wplr-main-menu' ); ?>
				<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	/*
		OPTIONS
	*/

	function admin_settings_intro() {
		//$apiurl = admin_url( 'admin-ajax.php' );
		//The API URL is ' . $apiurl . '<br />
		echo '<p>Welcome in WP/LR Sync! If you don\'t know how to get started, check the <a href="http://apps.meow.fr/wplr-sync/tutorial/" target="_blank">tutorial</a>.</p>';
	}

	function admin_extension_intro() {
		$extensions = apply_filters( 'wplr_extensions', array() );
		echo '<p>The plugin synchronizes your collections and collection sets from Lightroom. They are stored in WordPress internally but not used nor displayed initially. Extensions are required to use those collections. You can learn more about it here: <a target="_blank" href="http://apps.meow.fr/wplr-sync/wplr-extensions/">WP/LR Extensions</a>.</p>';
		echo "</p>";
		if ( empty( $extensions ) )
			$html = "<div class='error'><p>No extensions have been loaded.</p></div>";
		else if ( count( $extensions ) == 1 ) {
			$html = "<div class='updated'><p>The extension for <b>" . $extensions[0] . "</b> is loaded.</p></div>";
		}
		else {
			$html = "<div class='updated'><p>The extensions for <b>" . implode( ', ', $extensions ) . "</b> are loaded.</p></div>";
		}
		echo $html;
		echo "</p>";
	}

	function admin_extensions_callback() {
		$options = get_option( 'wplr_plugins', array() );
		$plugins = array();
		$dir = trailingslashit( plugin_dir_path( __FILE__ ) ) . trailingslashit( 'extensions' );
		foreach ( glob( $dir . "*.*" ) as $filename ) {
	    $content = file_get_contents( $filename );
			preg_match( "/Plugin Name: (.*)/", $content, $name );
			preg_match( "/Description: (.*)/", $content, $desc );
			if ( count( $name ) > 1 ) {
				$info = pathinfo( $filename );
				$plugins[$info['basename']] = array( 'name' => $name[1], 'desc' => count( $desc ) > 1 ? $desc[1] : "" );
			}
		}
		$html = '';
		$c = 0;
		foreach ( $plugins as $key => $value ) {
			$checked = ( !empty( $options ) && in_array( $key, $options ) ) ? 'checked="checked"' : '';
			if ( $c++ > 0 )
				$html .= "<br />";
    	$html .= sprintf( '<input type="checkbox" id="%1$s[%2$s]" name="%1$s[]" value="%2$s" %3$s />', "wplr_plugins", $key, $checked );
    	$html .= sprintf( '<label for="%1$s[%3$s]"> %2$s</label><br>', "wplr_plugins", $value['name'], $key );
			$html .= '<span class="description">' . $value['desc'] . '</span><br />';
		}
		echo $html;
	}

	function wplrsync_clean() {
		global $wpdb;
		$tbl_m = $wpdb->prefix . 'lrsync';
		$tbl_r = $wpdb->prefix . 'lrsync_relations';
		$tbl_c = $wpdb->prefix . 'lrsync_collections';
		try {
			do_action( 'wplr_clean' );
			$wpdb->query( "DELETE FROM $tbl_m WHERE wp_id NOT IN (SELECT ID FROM wp_posts WHERE post_type = 'attachment')" );
			$wpdb->query( "DELETE FROM $tbl_r WHERE wp_id NOT IN (SELECT ID FROM wp_posts WHERE post_type = 'attachment')" );
			$wpdb->query( "DELETE FROM $tbl_r WHERE wp_col_id NOT IN (SELECT wp_col_id FROM $tbl_c)" );
			echo json_encode( array( 'success' => true ) );
		}
		catch ( Exception $e ) {
			error_log( print_r( $e, true ) );
			echo json_encode( array( 'success' => false, 'message' => "An exception was caught and written in the PHP error logs." ) );
		}
		die();
	}

	function wplrsync_extensions_reset() {
		try {
			do_action( 'wplr_reset' );
			echo json_encode( array( 'success' => true ) );
		}
		catch ( Exception $e ) {
			error_log( print_r( $e, true ) );
			echo json_encode( array( 'success' => false, 'message' => "An exception was caught and written in the PHP error logs." ) );
		}
		die();
	}

	function wplrsync_extensions_query() {
		try {
			$task = $_POST['task'];
			if ( $task['action'] == 'add_collection' ) {
				if ( $task['is_folder'] )
					do_action( 'wplr_create_folder', (int)$task['wp_col_id'], (int)$task['wp_folder_id'], array( 'name' => $task['name'] ) );
				else
					do_action( 'wplr_create_collection', (int)$task['wp_col_id'], (int)$task['wp_folder_id'], array( 'name' => $task['name'] ) );
			}
			else if ( $task['action'] == 'remove_collection' ) {
				if ( $task['is_folder'] )
					do_action( 'wplr_remove_folder', (int)$task['wp_col_id'] );
				else
					do_action( 'wplr_remove_collection', (int)$task['wp_col_id'] );
			}
			else if ( $task['action'] == 'add_media' )
				do_action( 'wplr_add_media_to_collection', (int)$task['wp_id'], (int)$task['wp_col_id'] );
			else if ( $task['action'] == 'remove_media' )
				do_action( 'wplr_remove_media_from_collection', (int)$task['wp_id'], (int)$task['wp_col_id'] );
			echo json_encode( array( 'success' => true ) );
		}
		catch ( Exception $e ) {
			error_log( print_r( $e, true ) );
			echo json_encode( array( 'success' => false, 'message' => "An exception was caught and written in the PHP error logs." ) );
		}
		die();
	}

	function read_collections_recursively( $parent = null, $results = array(), $isRemoval ) {
		global $wpdb;
		$tbl_c = $wpdb->prefix . 'lrsync_collections';
		if ( is_null( $parent ) )
			$collections = $wpdb->get_results( "SELECT wp_col_id, name, wp_folder_id, is_folder FROM $tbl_c WHERE wp_folder_id IS NULL ORDER BY lr_col_id", ARRAY_A );
		else
			$collections = $wpdb->get_results( $wpdb->prepare( "SELECT wp_col_id, name, wp_folder_id, is_folder FROM $tbl_c WHERE wp_folder_id = %d ORDER BY lr_col_id", $parent ), ARRAY_A );
		foreach ( $collections as $c ) {
			array_push( $results, array_merge( array( 'action' => $isRemoval ? 'remove_collection' : 'add_collection' ), $c ) );
			if ( $c['is_folder'] )
				$results = $this->read_collections_recursively( $c['wp_col_id'], $results, $isRemoval );
		}
		return $results;
	}

	function wplrsync_extensions_init() {
		global $wpdb;
		$isRemoval = isset( $_POST['isRemoval'] ) && $_POST['isRemoval'] == 1;
		$tbl_m = $wpdb->prefix . 'lrsync';
		$tbl_r = $wpdb->prefix . 'lrsync_relations';
		$tasks = $this->read_collections_recursively( null, array(), $isRemoval );
		foreach ( $tasks as $c ) {
			if ( !$c['is_folder'] ) {
				$photos = $wpdb->get_results( $wpdb->prepare( "SELECT wp_id, wp_col_id, sort FROM $tbl_r WHERE wp_col_id = %d ORDER BY sort", $c['wp_col_id'] ), ARRAY_A );
				foreach ( $photos as $p )
					array_push( $tasks, array_merge( array( 'action' => $isRemoval ? 'remove_media' : 'add_media' ), $p ) );
			}
		};
		echo json_encode( array( 'success' => true, 'data' => $isRemoval ? $tasks : array_reverse( $tasks ) ) );
		die();
	}

	function admin_maintenance_callback() {
		?>
			<script type="text/javascript">

				function wplr_do_next(tasks, isRemoval) {
					var tasks = tasks;
					var task = tasks.pop();
					if (!task) {
						if ( isRemoval )
							wplr_final_reset();
						else
							jQuery('#wplr-status').text("Done!");
						return;
					}
					if (task.action == 'add_collection') {
						$collectionOrFolder = task.is_folder == '1' ? 'folder' : 'collection';
						jQuery('#wplr-status').text("Create " + $collectionOrFolder + " '" + task.name + "'...");
					}
					else if (task.action == 'remove_collection') {
						$collectionOrFolder = task.is_folder == '1' ? 'folder' : 'collection';
						jQuery('#wplr-status').text("Remove " + $collectionOrFolder + " '" + task.name + "'...");
					}
					else if (task.action == 'add_media') {
						jQuery('#wplr-status').text("Add media " + task.wp_id + " to collection " + task.wp_col_id + "...");
					}
					else if (task.action == 'remove_media') {
						jQuery('#wplr-status').text("Remove media " + task.wp_id + " from collection " + task.wp_col_id + "...");
					}
					else {
						jQuery('#wplr-status').text("Running unknown tasks...");
					}
					jQuery.post(ajaxurl, {
						action: 'wplrsync_extensions_query',
						isRemoval: 0,
						isAjax: true,
						task: task
					}, function (response) {
						var r = jQuery.parseJSON(response);
						if (r.success === false)
							alert(r.message);
						else {
							wplr_do_next(tasks, isRemoval);
						}
					});
				}

				function wplr_reset() {
					jQuery('#wplr-status').text("Initiating reset...");
					jQuery.post(ajaxurl, {
						action: 'wplrsync_extensions_init',
						isRemoval: 1,
						isAjax: true
					}, function (response) {
						var r = jQuery.parseJSON(response);
						if (r.success === false)
							alert(r.message);
						else {
							wplr_do_next(r.data, true);
						}
					});
				}

				function wplr_final_reset() {
					jQuery('#wplr-status').text("Final reset...");
					jQuery.post(ajaxurl, {
						action: 'wplrsync_extensions_reset',
						isAjax: true
					}, function (response) {
						var r = jQuery.parseJSON(response);
						if (r.success === false)
							alert(r.message);
						else {
							jQuery('#wplr-status').text("Done!");
						}
					});
				}

				function wplr_create() {
					jQuery('#wplr-status').text("Initiating sync...");
					jQuery.post(ajaxurl, {
						action: 'wplrsync_extensions_init',
						isRemoval: false,
						isAjax: true
					}, function (response) {
						var r = jQuery.parseJSON(response);
						if (r.success === false)
							alert(r.message);
						else {
							wplr_do_next(r.data);
						}
					});
				}

				function wplr_clean() {
					jQuery('#wplr-status').text("Cleaning...");
					jQuery.post(ajaxurl, {
						action: 'wplrsync_clean',
						isAjax: true
					}, function (response) {
						var r = jQuery.parseJSON(response);
						if (r.success === false)
							alert(r.message);
						else {
						jQuery('#wplr-status').text("Done!");
						}
					});
				}

			</script>
		<?php
		$html = '<div class="button" onclick="wplr_clean()">Clean DB</div> ';
		$html .= '<div class="button" onclick="wplr_reset()">Reset Extensions</div> ';
		$html .= '<div class="button" onclick="wplr_create()">Synchronize Extensions</div> ';
		$html .= '<small style="top: 4px; position: relative; left: 8px; color: #0064FF;" id="wplr-status"></small>';
		$html .= '<br /><span class="description">Reset will request the extensions to reset/cancel their usage of the collections.<br />Create will request the extensions to re-create the folders/collections structure.</label>';
		echo $html;
	}

	function admin_protocol_callback( $args ) {
		$html = '<select id="wplr_protocol" name="wplr_protocol">';
		foreach ( $args as $arg )
			$html .= '<option value="' . $arg . '"' . selected( $arg, get_option( 'wplr_protocol' ), false ) . ' > '  . $arg . '</option><br />';
		$html .= '</select><br />';
		$html .= '<span class="description">The default used to be XML/RPC but let\'s try to switch to HTTP API.</label>';
		echo $html;
	}

	function admin_debugging_callback( $args ) {
		$html = '<input type="checkbox" id="wplr_debugging_enabled" name="wplr_debugging_enabled" value="1" ' . checked( 1, get_option( 'wplr_debugging_enabled' ), false ) . '/>';
		$html .= '<label for="wplr_debugging_enabled"> '  . $args[0] . '</label><br>';
		$html .= '<span class="description">Add a Debugging Tools menu in WP/LR Sync. For advanced users only.</label>';
		echo $html;
	}

	function admin_debuglogs_callback( $args ) {
		$clearlogs = isset ( $_GET[ 'clearlogs' ] ) ? $_GET[ 'clearlogs' ] : 0;
		if ( $clearlogs ) {
			if ( file_exists( plugin_dir_path( __FILE__ ) . '/wplr-sync.log' ) ) {
				unlink( plugin_dir_path( __FILE__ ) . '/wplr-sync.log' );
			}
		}
		$html = '<input type="checkbox" id="wplr_debuglogs" name="wplr_debuglogs" value="1" ' . checked( 1, get_option( 'wplr_debuglogs' ), false ) . '/>';
		$html .= '<label for="wplr_debuglogs"> '  . $args[0] . '</label><br>';
		$html .= '<span class="description">Create an internal log file. For advanced users only.';
		if ( file_exists( plugin_dir_path( __FILE__ ) . '/wplr-sync.log' ) ) {
			$html .= sprintf( __( '<br />The <a target="_blank" href="%s/wplr-sync.log">log file</a> is available. You can also <a href="?page=wplr-settings-menu&clearlogs=true">clear</a> it.', 'wp-retina-2x' ), plugin_dir_url( __FILE__ ) );
		}
		$html .= '</label>';
		echo $html;
	}

	function display_image_box( $wpid, $image = null ) {
		$metadata = wp_get_attachment_image_src( $wpid, "full", false );
		$parent = wp_get_post_parent_id( $wpid );
		$title = get_the_title( $wpid  );
		$parent_title = $parent ? get_the_title( $parent ) : "";
		$filename = $metadata ? $metadata[0] : "";
		echo "<div id='wplr-image-box-$wpid' class='wplr-image-box'>";
		echo "<div class='wplr-title-wpid'>";
		echo "Media #<a target='_blank' title='$title' href='post.php?post=$wpid&action=edit'>$wpid</a>";
		if ( $parent ) {
			echo "<span style='float: right;'>Post #<a title='$parent_title' target='_blank' href='post.php?post=$parent&action=edit'>$parent</a></span><br />";
		}
		echo "</div>";

		echo "<div class='wplr-image'>";
		echo "<a target='_blank' title='$title' href='$filename'>" . wp_get_attachment_image( $wpid, array( 190, 190 ), false, array( 'alt' => $title ) ) . "</a>";
		echo "</div>";
		echo "<div style='clear: both;'></div>";
		echo "<span class='wplr-actions'>";
		if ( $image ) {
			echo "<a style='background: #B34A4A;' href='upload.php?page=wplrsync&show=duplicates&action=unlink&wpid=$wpid&lrid=$image->lr_id'>Unlink</a>";
			echo "<a style='' href='upload.php?page=wplrsync&show=duplicates&action=delete&wpid=$wpid&lrid=$image->lr_id'>Delete</a>";
		}
		else {
			echo "<div>
				LR ID:
				<input type='text' class='wplr-sync-lrid-input wplrsync-link-" . $wpid . "'></input>
				<div class='wplr-button wplr-button-link' onclick='wplrsync_link($wpid)'>Link</div>
				<div class='wplr-button wplr-button-link' onclick='wplrsync_link($wpid, true)'>Ignore</div>
			</div>";
		}
		echo "</span>";


		echo "</div>";
	}

	function admin_tools() {
		$images = array();
		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";
		$show = null;
		if ( isset( $_GET['action'] ) ) {
			$wpid = isset( $_GET['wpid'] ) ? $_GET['wpid'] : null;
			$lrid = isset( $_GET['lrid'] ) ? $_GET['lrid'] : null;
			if ( $_GET['action'] == "link" ) {
				$this->link_media( $lrid, $wpid );
			}
			else if ( $_GET['action'] == "unlink" ) {
				$this->unlink_media( $lrid, $wpid );
			}
			else if ( $_GET['action'] == "delete" ) {
				$this->unlink_media( $lrid, $wpid );
				wp_delete_attachment( $wpid );
			}
		}

		if ( isset( $_GET['show'] ) && $_GET['show'] == "duplicates" ) {
			$show = "duplicates";
			$images = $this->list_duplicates();
		}
		else {
			$show = "unlinked";
			$images = $this->list_unlinks( true );
		}

		?>
			<style>

				.wplr-title-lrid {
					background: #515151;
					color: white;
					padding: 0px 2px;
					margin-left: 5px;
				}

				.wplr-duplicates-box {
					padding: 5px;
					border: 2px solid  #515151;
					border-radius: 5px;
					margin-bottom: 10px;
				}

				.wplr-image-box {
					width: 190px;
					margin-right: 10px;
					float: left;
					font-size: 10px;
					background: white;
					padding: 2px 5px;
					margin-bottom: 10px;
					box-shadow: 2px 2px 2px #D7D7D7;
				}

				.wplr-image-box .wplr-image {
					width: 190px;
					height: 128px;
					overflow: hidden;
				}

				.wplr-title-wpid {
					font-size: 11px;
					line-height: 20px;
				}

				.wplr-title-filename {
					line-height: 8px;
				}

				.wplr-title-wpid a {
					text-decoration: none;
				}

				.wplr-actions a {
					margin-right: 5px;
					padding: 1px 3px;
					background: rgb(92, 92, 92);
					color: white;
				}

				.subsubsub #icl_subsubsub, .subsubsub br {
					display: none;
				}

			</style>

			<div class='wrap'>
			<?php jordy_meow_donation(true); ?>
			<h2>Tools | WP/LR Sync <?php by_jordy_meow(); ?></h2>
			<p>This screen will help you to link your unlinked photos between WordPress and Lightroom. <b>Don't forget that if you link a photo from here you also need to also add it manually to the WP/LR Service in Lightroom.</b>
			Last but not least, be careful with the in 'Duplicated Links', they are <b>immediate</b> and the 'Delete' is <b>unrecoverable</b>.</p>
			<ul class="subsubsub">
				<li><a class="<?php echo $show == "unlinked" ? "current" : "" ?>" href="admin.php?page=wplr-tools-menu&show=unlinked">Unlinked Photos</a> |</li>
				<li><a class="<?php echo $show == "duplicates" ? "current" : "" ?>" href="admin.php?page=wplr-tools-menu&show=duplicates">Duplicated Links</a></li>
			</ul>
			<div style='clear: both; margin-bottom: 18px; margin-top: -5px;'></div>

		<?php

		if ( count( $images ) == 0 && $show == "unlinked" )
			echo "<div class='updated'><p>There are no unlinked photos.</p></div>";
		else if ( count( $images ) == 0 && $show == "duplicates" )
			echo "<div class='updated'><p>There are no duplicated links.</p></div>";
		else {
			foreach ( $images as $image ) {
				if ( $show == "duplicates" ) {
					echo "<b class='wplr-title-lrid'>LR ID " . $image->lr_id . "</b> Filename: " . ( $image->lr_file ? $image->lr_file : "Unknown" );
					echo "<div class='wplr-duplicates-box'>";
					$wpids = explode( ',', $image->wpids );
					foreach ( $wpids as $wpid ) {
						$this->display_image_box( $wpid, $image );
					}
					echo "<div style='clear: both;'></div>";
					echo "</div>";
				}
				else if ( $show == "unlinked" ) {
					$this->display_image_box( $image->ID );
				}
			}
		}

		echo "</div>";
	}

	function admin_debug() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// HEADER
		?>
		<div class="wrap" >

			<?php jordy_meow_donation( true ); ?>
			<h2>Debug | WP/LR Sync <?php by_jordy_meow(); ?></h2>

			<style>

				.left {
					width: 450px;
				}

				.right {
					position: absolute;
					left: 460px;
				}

				h3 {
					margin-bottom: 5px;
				}

				p {
					margin-top: 0px;
					margin-bottom: 10px;
				}

				.wplrsync-form {
					border: 1px solid lightgrey;
					padding: 5px;
					background: white;
					border-radius: 5px;
					width: 420px;
				}

				th {
					text-align: left;
				}

				#wpfooter {
					display: none;
				}

			</style>

			<p>Those tools should be used for debugging purposed. Be careful when you are using them, especially the colored buttons (usually blue).</p>
		<?php

		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";

		if ( isset( $_POST['action'] ) ) {

			$action = $_POST['action'];
			if ( $action == "reset" ) {
				$this->reset_db();
				echo "<div class='updated'><p>The database was reset.</p></div>";
			}
			else if ( $action == "duplicates" ) {
				$duplicates = $this->list_duplicates();
			}
			else if ( $action == "scan" ) {
				$unlinks = $this->list_unlinks();
			}
			else if ( $action == "list" ) {
				$list = $this->list_sync_media();
			}
			else if ( $action == "link" ) {
				if ( $this->link_media( $_POST['lr_id'], $_POST['wp_id'] ) ) {
					echo "<div class='updated'><p>Link successful.</p></div>";
				}
				else {
					echo "<div class='error'><p>" . $this->get_error()->message . "</p></div>";
				}
			}
			else if ( $action == "linkinfo_upload" ) {
				if ( isset( $_FILES['file'], $_FILES['file']['tmp_name'] ) && !empty( $_FILES['file']['tmp_name'] ) ) {
					$tmp_path = $_FILES['file']['tmp_name'];
					$linkinfo = $this->linkinfo_upload( $tmp_path, null );
				}
				else {
					$linkinfo = $this->linkinfo_media( $_POST['wp_id'] );
				}
				if ( $linkinfo )
					echo "<div class='updated'><p>Link info retrieved.</p></div>";
				else
					echo "<div class='error'><p>" . $this->get_error()->message . "</p></div>";
			}
			else if ( $action == "sync" ) {
				$tmp_path = $_FILES['file']['tmp_name'];
				$lrinfo = new Meow_WPLR_LRInfo();
				$lrinfo->lr_id = $_POST['lr_id'] == "" ? -1 : $_POST['lr_id'];
				$lrinfo->lr_file = $_FILES['file']['name'];
				$lrinfo->lr_title = isset( $_POST['title'] ) ? $_POST['title'] : "";
				$lrinfo->lr_caption = $_POST['caption'];
				$lrinfo->lr_desc = isset( $_POST['desc'] ) ? $_POST['desc'] : "";
				$lrinfo->lr_alt_text = isset( $_POST['altText'] ) ? $_POST['altText'] : "";
				$lrinfo->sync_title = isset( $_POST['syncTitle'] ) && $_POST['syncTitle'] == 'on';
				$lrinfo->sync_caption = isset( $_POST['syncCaption'] ) && $_POST['syncCaption'] == 'on';
				$lrinfo->sync_desc = isset( $_POST['syncDesc'] ) && $_POST['syncDesc'] == 'on';
				$lrinfo->sync_alt_text = isset( $_POST['syncAltText'] ) && $_POST['syncAltText'] == 'on';
				$lrinfo->type = $_FILES['file']['type'];
				if ( $this->sync_media( $lrinfo, $tmp_path ) )
					echo "<div class='updated'><p>Lr ID " . $_POST['lr_id'] . " was synchronized with the attachment.</p></div>";
				else
					echo "<div class='error'><p>" . $this->get_error()->message . "</p></div>";

			}
			else if ( $action == "unlink" ) {
				if ( $this->unlink_media( $_POST['lr_id'], $_POST['wp_id'] ) )
					echo "<div class='updated'><p>Media unlinked.</p></div>";
				else
					echo "<div class='error'><p>" . $this->get_error()->message . "</p></div>";
			}
			else if ( $action == "remove" ) {
				if ( $this->delete_media( $_POST['lr_id'] ) )
					echo "<div class='updated'><p>Media removed.</p></div>";
				else
					echo "<div class='error'><p>" . $this->get_error()->message . "</p></div>";
			}
			else if ( $action != 'api' ) {
				echo "<div class='error'<p>Unknown action.</p></div>";
			}

		}

		// CONTENT & FORMS
		?>
			<div class="right">

				<h3>Display</h3>
				<p class="buttons">
					<form style="float: left; margin-right: 5px;" method="post" action="">
						<input type="submit" name="submit" id="submit" class="button" value="Show Hierarchy">
					</form>
					<form style="float: left; margin-right: 5px;" method="post" action="">
						<input type="hidden" name="action" value="list">
						<input type="submit" name="submit" id="submit" class="button" value="List Linked">
					</form>
					<form style="float: left; margin-right: 5px;" method="post" action="">
						<input type="hidden" name="action" value="scan">
						<input type="submit" name="submit" id="submit" class="button" value="List Unlinked">
					</form>
					<form style="float: left; margin-right: 5px;" method="post" action="">
						<input type="hidden" name="action" value="duplicates">
						<input type="submit" name="submit" id="submit" class="button" value="List Duplicates">
					</form>
					<!-- <form style="float: left; margin-right: 5px;" method="post" action="">
						<input type="hidden" name="action" value="api">
						<input type="submit" name="submit" id="submit" class="button" value="API Ping">
					</form> -->
				</p>
				<br />

				<?php
					if ( isset( $_POST['action'] ) && $action == "scan" ) {
						echo '<br /><h3>UnLinked Media</h3>';
						echo '<pre>';
						print_r( $unlinks );
						echo '</pre>';
					}
					else if ( isset( $_POST['action'] ) && $action == "list" ) {
						echo '<br /><h3>Linked Media</h3>';
						echo '<pre>';
						print_r( $list );
						echo '</pre>';
					}
					else if ( isset( $_POST['action'] ) && $action == "linkinfo_upload" ) {
						echo '<pre>';
						print_r( $linkinfo );
						echo '</pre>';
					}
					else if ( isset( $_POST['action'] ) && $action == "duplicates" ) {
						echo '<br /><h3>Duplicates</h3>';
						echo '<pre>';
						print_r( $duplicates );
						echo '</pre>';
					}
					else if ( isset( $_POST['action'] ) && $action == "api" ) {
						$boundary = wp_generate_password(24);
						$response = wp_remote_post( admin_url( 'admin-ajax.php' ),
							array(
								'headers' => array( 'content-type' => 'multipart/form-data; boundary=' . $boundary ),
								'body' => array( 'action' => 'lrsync_api', 'isAjax' => 1, 'data' => '' )
						  )
						);
						echo '<br /><h3>API Response</h3>';
						echo '<pre>';
						print_r( $response );
						echo '</pre>';
					}
					else {
						function read_galleries_recursively( $parent = null, $level = 0 ) {
							global $wpdb;
							$tbl_m = $wpdb->prefix . 'lrsync';
							$tbl_r = $wpdb->prefix . 'lrsync_relations';
							$tbl_c = $wpdb->prefix . 'lrsync_collections';
							if ( is_null( $parent ) )
								$collections = $wpdb->get_results( "SELECT * FROM $tbl_c WHERE wp_folder_id IS NULL ORDER BY lr_col_id", OBJECT );
							else
								$collections = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tbl_c WHERE wp_folder_id = %d ORDER BY lr_col_id", $parent ), OBJECT );
							if ( $level > 0 )
								echo '<div style="margin-left: 10px; border-left: 1px dotted gray; padding-left: 10px; margin-bottom: 5px;">';
							foreach ( $collections as $c ) {
								$type = $c->is_folder ? '<span class="dashicons dashicons-category"></span>' : '<span class="dashicons dashicons-admin-media"></span>';
								echo "<div>$type " . $c->name . "</div>";
								if ( $c->is_folder )
									read_galleries_recursively( $c->wp_col_id, $level + 1 );
								else {
									$photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tbl_r WHERE wp_col_id = %d ORDER BY sort LIMIT 25", $c->wp_col_id ), OBJECT );
									if ( count( $photos ) > 0 ) {
										echo '<div style="margin-left: 15px; padding: 2px; margin-top: 2px; border: 1px solid gray;">';
										foreach ( $photos as $photo ) {
											$src = wp_get_attachment_image_src( $photo->wp_id, 'thumbnail' );
											echo '<div><a href="media.php?attachment_id=' . $photo->wp_id . '&action=edit"><img style="float: left; margin-right: 2px;" width="32" height="32" src="' . $src[0] . '"></a></div>';
										}
										echo '<div style="clear: both;"></div></div>';
									}
								}
							}
							if ( $level > 0 )
								echo '</div>';
						}
						echo "<br /><h3>Hierarchy</h3>";
						echo "<p>This is stored internally. To replicate this in another plugin through an extension, visit the Extensions menu. A maximum of 25 photos is displayed by collection here to avoid viewing issues.</p>";
						read_galleries_recursively();
					}
				?>
			</div>

			<div class="left">
				<?php if ( isset( $link_files_count ) && ( $unlink_files_count ) ): ?>
				<div style="font-weight: bold;"><?php echo $link_files_count ?> linked files out of <?php echo ( $link_files_count + $unlink_files_count ) ?> media files.</div>
				<?php endif; ?>

				<h3>Link info</h3>
				<p>Link info for either a WP ID <b>or</b> the Uploaded File.</p>
				<form class="wplrsync-form" method="post" action="" enctype="multipart/form-data">
					<input type="hidden" name="action" value="linkinfo_upload">
					<table>
						<tr>
							<th scope="row"><label for="wp_id">WP ID</label></th>
							<td><input name="wp_id" type="text" id="wp_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="file">File</label></th>
							<td><input name="file" type="file" id="file"></td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button" value="Check"></td>
						</tr>
					</table>
				</form>

				<h3>Link</h3>
				<p>Will link the WP Media ID to the Lr ID.</p>
				<form class="wplrsync-form" method="post" action="" enctype="multipart/form-data">
					<input type="hidden" name="action" value="link">
					<table>
						<tr>
							<th scope="row"><label for="lr_id">Lr ID</label></th>
							<td><input name="lr_id" type="text" id="lr_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="wp_id">Media ID</label></th>
							<td><input name="wp_id" type="text" id="wp_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Link Media ID + Lr ID"></td>
						</tr>
					</table>

				</form>

				<h3>Unlink</h3>
				<p>Will unlink the media.</p>
				<form class="wplrsync-form" method="post" action="">
					<input type="hidden" name="action" value="unlink">
					<table>
						<tr>
							<th scope="row"><label for="lr_id">Lr ID</label></th>
							<td><input name="lr_id" type="text" id="lr_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="wp_id">Media ID</label></th>
							<td><input name="wp_id" type="text" id="wp_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Unlink"></td>
						</tr>
					</table>
				</form>

				<h3>Sync</h3>
				<p>Will create the entry if doesn't exist, will update it if exists.</p>
				<form class="wplrsync-form" method="post" action="" enctype="multipart/form-data">
					<input type="hidden" name="action" value="sync">
					<table>
						<tr>
							<th scope="row"><label for="lr_id">Lr ID</label></th>
							<td><input name="lr_id" type="text" id="lr_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="title">Title</label></th>
							<td><input name="title" type="text" id="title" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="title">Caption</label></th>
							<td><input name="caption" type="text" id="caption" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="title">Desc</label></th>
							<td><input name="desc" type="text" id="desc" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="title">Alt</label></th>
							<td><input name="altText" type="text" id="altText" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"><label for="file">File</label></th>
							<td><input name="file" type="file" id="file"></td>
						</tr>
						<tr>
							<th scope="row"><label for="sync">Sync</label></th>
							<td>
								<label><input type="checkbox" id="syncTitle" name="syncTitle">Title</label>
								<label><input type="checkbox" id="syncCaption" name="syncCaption">Caption</label>
								<label><input type="checkbox" id="syncDesc" name="syncDesc">Desc</label>
								<label><input type="checkbox" id="syncAltText" name="syncAltText">Alt</label>
							</td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Sync Media"></td>
						</tr>
					</table>
				</form>

				<h3>Remove</h3>
				<p>Will remove the media.</p>
				<form class="wplrsync-form" method="post" action="">
					<input type="hidden" name="action" value="remove">
					<table>
						<tr>
							<th scope="row"><label for="lr_id">Lr ID</label></th>
							<td><input name="lr_id" type="text" id="lr_id" value="" class="regular-text code"></td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Remove Media"></td>
						</tr>
					</table>
				</form>

				<h3>Actions</h3>
				<p class="buttons">
					Be careful. Those buttons are dangerous.
					<form style="margin-right: 5px;" method="post" action="">
						<input type="hidden" name="action" value="reset">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="Reset WP/LR Sync DB">
					</form>
				</p>

			</div>
		</div>
	</div>
		<?php
	}
}

?>
