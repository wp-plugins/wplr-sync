<?php

class Meow_WPLR_Sync_RPC extends Meow_WPLR_Sync_Core {

	public function __construct() {
		parent::__construct();
		add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ));
	}

		// Authenticate and share the useful arguments.
	function rpc_init_with( &$args ) {
		global $wp_xmlrpc_server;
		$this->check_db();
		$wp_xmlrpc_server->escape( $args );
		$blog_id  = array_shift( $args );
		$username = array_shift( $args );
		$password = array_shift( $args );
		$user = $wp_xmlrpc_server->login( $username, $password );
		if ( !$user ) {
			$this->error = new IXR_Error( 403, __( 'Incorrect username or password.' ) );
			return false;
		}

		if ( !current_user_can( 'upload_files' ) ) {
			$this->error = new IXR_Error( 403, __( 'You do not have permission to upload files.' ) );
			return false;
		}

		return $user;
	}

	// Ping for the client. Should probably send back the version of the plugin.
	function rpc_ping( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) )
			return "Pong! User information were not provided.";
		return "Pong to you, " . $user->data->display_name . "!";
	}

	// Sync file
	function rpc_presync( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		if ( defined ('HHVM_VERSION' ) ) {
			$max_execution_time = ini_get( 'max_execution_time' ) ? (int)ini_get( 'max_execution_time' ) : 30;
			$post_max_size = ini_get( 'post_max_size' ) ? (int)$this->parse_ini_size( ini_get( 'post_max_size' ) ) : (int)ini_get( 'hhvm.server.max_post_size' );
			$upload_max_filesize = ini_get( 'upload_max_filesize' ) ? (int)$this->parse_ini_size( ini_get( 'upload_max_filesize' ) ) : (int)ini_get( 'hhvm.server.upload.upload_max_file_size' );
		}
		else {
			$max_execution_time = (int)ini_get( 'max_execution_time' );
			$post_max_size = (int)$this->parse_ini_size( ini_get( 'post_max_size' ) );
			$upload_max_filesize = (int)$this->parse_ini_size( ini_get( 'upload_max_filesize' ) );
		}
		$presync = array(
			'max_execution_time' => $max_execution_time,
			'post_max_size' => $post_max_size,
			'upload_max_filesize' => $upload_max_filesize,
			'max_size' => min( $post_max_size, $upload_max_filesize )
		);
		return $presync;
	}

	// Sync file
	function rpc_sync_collection( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) )
			return $this->error;
		if ( !$list = $this->sync_collection( $args[0] ) )
			return $this->error;
		return $list;
	}

	// Sync file
	function rpc_sync( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$fileinfo = $args[0];
		$lrinfo = new Meow_WPLR_LRInfo();
		$lrinfo->lr_id = $fileinfo["id"];
		$lrinfo->lr_file = $fileinfo["file"];
		$lrinfo->lr_title = $fileinfo["title"];
		$lrinfo->lr_caption = $fileinfo["caption"];
		$lrinfo->lr_desc = $fileinfo["desc"];
		$lrinfo->lr_alt_text = $fileinfo["altText"];
		$lrinfo->sync_title = $fileinfo["syncTitle"];
		$lrinfo->sync_caption = $fileinfo["syncCaption"];
		$lrinfo->sync_desc = $fileinfo["syncDesc"];
		$lrinfo->sync_alt_text = $fileinfo["syncAltText"];
		$lrinfo->type = $fileinfo["type"];
		$lrinfo->tags = $fileinfo["tags"];
		$file = $this->b64_to_file( $fileinfo["data"] );
		if ( !isset( $fileinfo["wp_col_id"] ) || $fileinfo["wp_col_id"] == null )
			$fileinfo["wp_col_id"] = -1;
		if ( !$sync = $this->sync_media( $lrinfo, $file, $fileinfo["wp_col_id"] ) )
			return $this->error;
		return $sync;
	}

	// Delete file
	function rpc_delete( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$list = $this->delete_media( $args[0], isset( $args[1] ) ? $args[1] : -1 );
		return $list;
	}

	// Delete collection
	function rpc_delete_collection( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$res = $this->delete_collection( $args[0] );
		return $res;
	}

	// List synced files
	function rpc_list( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$list = $this->list_sync_media();
		return $list;
	}

	// List files (the Media IDs) that are not linked
	function rpc_list_unlinks( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$list = $this->list_unlinks();
		return $list;
	}

	// Get LinkInfo for the Media ID
	function rpc_linkinfo( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$linkinfo = $this->linkinfo_media( $args[0] );
		return $linkinfo;
	}

	// Get LinkInfo for the upload
	function rpc_linkinfo_upload( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$file = $this->b64_to_file( $args[0] );
		$linkinfo = $this->linkinfo_upload( $file, null );
		return $linkinfo;
	}

	// Get LinkInfo for the Media ID
	function rpc_userinfo( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		return $user->data;
	}

	// Get LinkInfo for the Media ID
	function rpc_link( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$lr_id = $args[0];
		$wp_id = $args[1];
		return $this->link_media( $lr_id, $wp_id );
	}

	// Unlink
	function rpc_unlink( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$lr_id = $args[0];
		$wp_id = $args[1];
		return $this->unlink_media( $lr_id, $wp_id );
	}

	// Get WP IDs for a given LR ID
	function rpc_list_wpids( $args ) {
		if ( !$user = $this->rpc_init_with( $args ) ) {
			return $this->error;
		}
		$lr_id = $args[0];
		return $this->list_wpids( $lr_id );
	}

	function xmlrpc_methods( $methods ) {
		$methods['lrsync.ping'] = array( $this, 'rpc_ping' );
		$methods['lrsync.presync'] = array( $this, 'rpc_presync' );
		$methods['lrsync.sync'] = array( $this, 'rpc_sync' );
		$methods['lrsync.sync_collection'] = array( $this, 'rpc_sync_collection' );
		$methods['lrsync.list'] = array( $this, 'rpc_list' );
		$methods['lrsync.delete'] = array( $this, 'rpc_delete' );
		$methods['lrsync.delete_collection'] = array( $this, 'rpc_delete_collection' );
		$methods['lrsync.list_unlinks'] = array( $this, 'rpc_list_unlinks' );
		$methods['lrsync.link'] = array( $this, 'rpc_link' );
		$methods['lrsync.unlink'] = array( $this, 'rpc_unlink' );
		$methods['lrsync.linkinfo'] = array( $this, 'rpc_linkinfo' );
		$methods['lrsync.linkinfo_upload'] = array( $this, 'rpc_linkinfo_upload' );
		$methods['lrsync.userinfo'] = array( $this, 'rpc_userinfo' );
		$methods['lrsync.list_wpids'] = array( $this, 'rpc_list_wpids' );
		return $methods;
	}

}
