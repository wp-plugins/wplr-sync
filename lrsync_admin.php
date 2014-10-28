<?php

class Meow_WPLR_Sync_Admin extends Meow_WPLR_Sync_RPC {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_menu() {
		add_submenu_page( 'tools.php', 'WP/LR Sync', 'WP/LR Sync', 'manage_options', 'wplrsync_tools', array( $this, 'tools' ) );
	}

	function tools() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		// HEADER
		?>
		<div class="wrap" >
			
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

			<h2>
				WP/LR Sync
				<span>
				<form style="float: right;" method="post" action="">
					<input type="hidden" name="action" value="reset">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Reset">
				</form>
				<form style="float: right; margin-right: 5px;" method="post" action="">
					<input type="hidden" name="action" value="scan">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Get unlinks">
				</form>
				<form style="float: right; margin-right: 5px;" method="post" action="">
					<input type="hidden" name="action" value="list">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Get links">
				</form>
			</span>
			</h2>
		<?php

		global $wpdb;
		$table_name = $wpdb->prefix . "lrsync";

		if ( isset( $_POST['action'] ) ) {

			$action = $_POST['action'];
			if ( $action == "reset" ) {
				$this->reset_db();
				echo "<div class='updated'><p>The database was reset.</p></div>";
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
				$lrinfo = new Meow_WPLR_LRInfo( $_POST['lr_id'] == "" ? -1 : $_POST['lr_id'], $_FILES['file']['name'], $_POST['title'], "" );
				$lrinfo->type = $_FILES['file']['type'];
				if ( $this->sync_media( $lrinfo, $tmp_path ) )
					echo "<div class='updated'><p>Lr ID " . $_POST['lr_id'] . " was synchronized with the attachment.</p></div>";
				else
					echo "<div class='error'><p>" . $this->get_error()->message . "</p></div>";

			}
			else if ( $action == "unlink" ) {
				if ( $this->unlink_media( $_POST['lr_id'] ) )
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
			else {
				echo "<div class='error'<p>Unknown action.</p></div>";
			}

		}

		$link_files = $this->list_sync_media();
		$unlink_files = $this->list_unlinks();
		$link_files_count = count( $link_files );
		$unlink_files_count = count( $unlink_files );

		// CONTENT & FORMS
		?>
			<div class="right">
				<?php
					if ( isset( $_POST['action'] ) && $action == "scan" ) {
						echo '<pre>';
						print_r( $unlinks );
						echo '</pre>';
					}
					else if ( isset( $_POST['action'] ) && $action == "list" ) {
						echo '<pre>';
						print_r( $list );
						echo '</pre>';
					}
					else if ( isset( $_POST['action'] ) && $action == "linkinfo_upload" ) {
						echo '<pre>';
						print_r( $linkinfo );
						echo '</pre>';
					}
				?>
			</div>
			
			<div class="left">
				<div style="font-weight: bold;"><?php echo $link_files_count ?> linked files out of <?php echo ( $link_files_count + $unlink_files_count ) ?> media files.</div>
				<div>This screen is only available in debug mode.</div>

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
							<th scope="row"><label for="file">File</label></th>
							<td><input name="file" type="file" id="file"></td>
						</tr>
						<tr>
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Sync Media"></td>
						</tr>
					</table>
					
				</form>

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
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Check"></td>
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
							<th scope="row"></th>
							<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Unlink"></td>
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

			</div>
		</div>
	</div>
		<?php
	}
}

?>