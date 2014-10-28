<?php

class Meow_WPLR_LRInfo {

	// Core
	public $lr_id; // LR ID
	public $lr_file; // Filename
	public $lr_title;
	public $lr_caption;
	public $wp_id;
	public $lastsync;

	// Extra
	public $type; // MIME type
	
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
		$instance->lr_title = $row->lr_title;
		$instance->lr_caption = $row->lr_caption;
		$instance->wp_id = $row->wp_id;
		$instance->lastsync = $row->lastsync;
		return $instance;
	}
}

?>