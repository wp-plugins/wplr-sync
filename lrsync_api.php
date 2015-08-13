<?php

class Meow_WPLR_Sync_API extends Meow_WPLR_Sync_RPC {

	public function __construct() {
		parent::__construct();
		add_action( 'parse_request', array( $this, 'parse_request' ), 0 );
		add_action( 'wp_ajax_lrsync_api', array( $this, 'lrsync_api' ) );
		add_action( 'wp_ajax_nopriv_lrsync_api', array( $this, 'lrsync_api' ) );
	}

	function lrsync_api( $args ) {
		echo "PING";
		//var_dump( $args );
		die();
	}

}

?>
