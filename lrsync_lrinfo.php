<?php

class Meow_WPLR_LRInfo {

	// Core
	public $lr_id; // LR ID
	public $lr_file; // Filename
	public $lr_title;
	public $lr_caption;
	public $lr_desc;
	public $lr_alt_text;
	public $wp_id;
	public $lastsync;

	// Conditions
	public $sync_title;
	public $sync_caption;
	public $sync_alt_text;
	public $sync_desc;

	// Extra
	public $type; // MIME type
	public $tags; // MIME type
	
	// WP
	public $wp_url;
	public $wp_phash; // Perceptual Hash
	public $wp_exif; // Exif CreatedDate

	function __construct() {
	}

	public static function fromRow( $row ) {
		$instance = new self();
		$instance->lr_id = $row->lr_id;
		$instance->lr_file = $row->lr_file;
		$instance->wp_id = $row->wp_id;
		$instance->lastsync = $row->lastsync;
		return $instance;
	}
}

?>
