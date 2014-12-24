<?php

class Meow_WPLR_Sync_Admin extends Meow_WPLR_Sync_RPC {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_menu() {
		if ( WP_DEBUG ) {
			add_submenu_page( 'tools.php', 'WP/LR Sync', 'WP/LR Sync', 'manage_options', 'wplrsync_tools', array( $this, 'tools' ) );
		}
		add_media_page( 'WP/LR Sync', 'WP/LR Sync', 'manage_options', 'wplrsync', array( $this, 'wplrsync_media' ) ); 
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
				<input type='text' class='wplr-sync-lrid-input' id='wplrsync-link-" . $wpid . "'></input>
				<div class='wplr-button wplr-button-link' onclick='wplrsync_link($wpid)'>Link</div>
				<div class='wplr-button wplr-button-link' onclick='wplrsync_link($wpid, true)'>Ignore</div>
			</div>";
		}
		echo "</span>";


		echo "</div>";
	}

	function wplrsync_media() {
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
			$images = $wpdb->get_results(
				"SELECT lr.lr_id, lr.lr_file, GROUP_CONCAT(lr.wp_id SEPARATOR ',') as wpids
				FROM $wpdb->posts p, $table_name lr
				WHERE p.ID = lr.wp_id AND lr.lr_id != 0
				GROUP BY lr.lr_id
				HAVING COUNT(p.ID) > 1
				ORDER BY lr.lr_id DESC", OBJECT );
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
				
			</style>

			<div class='wrap'>
			<div id="icon-upload" class="icon32"><br></div>
			<h2>WP/LR Sync</h2>
			<p>This screen will help you to link your unlinked photos between WordPress and Lightroom. <b>Don't forget that if you link a photo from here you also need to also add it manually to the WP/LR Service in Lightroom.</b>
			Last but not least, be careful with the actions in the 'Duplicated Links', the actions are <u>instantaneous</u> and the 'Delete' is <u>unrecoverable</u>.</p>
			<ul class="subsubsub">
				<li><a class="<?php echo $show == "unlinked" ? "current" : "" ?>" href="upload.php?page=wplrsync&show=unlinked">Unlinked Photos</a> |</li>
				<li><a class="<?php echo $show == "duplicates" ? "current" : "" ?>" href="upload.php?page=wplrsync&show=duplicates">Duplicated Links</a></li>
			</ul>
			<div style='clear: both; margin-bottom: 18px; margin-top: -5px;'></div>

		<?php

		if ( count( $images ) == 0 && $show == "unlinked" )
			echo "<div class='updated'><p>There are no unlinked photos. Good job!</p></div>";
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
					<input type="hidden" name="action" value="duplicates">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Get Duplicates">
				</form>
				<form style="float: right; margin-right: 5px;" method="post" action="">
					<input type="hidden" name="action" value="scan">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Get Unlinked">
				</form>
				<form style="float: right; margin-right: 5px;" method="post" action="">
					<input type="hidden" name="action" value="list">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Get Linked">
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
			else if ( $action == "duplicates" ) {
				echo "<div class='updated'><p>Checked duplicates.</p></div>";
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
					else if ( isset( $_POST['duplicates'] ) && $action == "list" ) {



						echo '<pre>';
						print_r( $list );
						echo '</pre>';
					}
				?>
			</div>
			
			<div class="left">
				<div style="font-weight: bold;"><?php echo $link_files_count ?> linked files out of <?php echo ( $link_files_count + $unlink_files_count ) ?> media files.</div>

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

			</div>
		</div>
	</div>
		<?php
	}
}

?>