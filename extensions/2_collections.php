<?php
/*
Hyphens are added in the header below to avoid WordPress to recognize this extension as a real plugin. It's actually not a big problem but it displays an error while installing the plugin. If you want to use this extension as a standalone plugin, you can remove the hyphens ;)

- Plugin Name: Basic Collections
- Plugin URI: http://www.meow.fr
- Description: Create collections (post type) and create standard galleries in the post of those collections.<br />The text around the gallery shorcode can be modified.
- Version: 0.1.0
- Author: Jordy Meow
- Author URI: http://www.meow.fr
*/

class WPLR_Extension_Collections {

  public function __construct() {

    // Init
    add_filter( 'wplr_extensions', array( $this, 'extensions' ), 10, 1 );
    add_action( 'init', array( $this, 'init' ), 10, 0 );

    // Collection
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( 'wplr_update_collection', array( $this, 'update_collection' ), 10, 2 );
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );
    add_action( "wplr_move_collection", array( $this, 'move_collection' ), 10, 3 );

    // Folder
    add_action( 'wplr_create_folder', array( $this, 'create_folder' ), 10, 3 );
    add_action( 'wplr_update_folder', array( $this, 'update_folder' ), 10, 2 );
    add_action( "wplr_move_folder", array( $this, 'move_collection' ), 10, 3 ); // same as for collection
    add_action( "wplr_remove_folder", array( $this, 'remove_collection' ), 10, 1 ); // same as for collection

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );

    // Extra
    //add_action( 'wplr_reset', array( $this, 'reset' ), 10, 0 );
  }

  function extensions( $extensions ) {
    array_push( $extensions, 'Basic Collections' );
    return $extensions;
  }

  // Create the "Collections" post type.
  function init() {
    $collections = array(
      'name'               => _x( 'Collections', 'post type general name', 'wplr-sync-collections' ),
      'singular_name'      => _x( 'Collection', 'post type singular name', 'wplr-sync-collections' ),
      'menu_name'          => _x( 'Collections', 'admin menu', 'wplr-sync-collections' ),
      'name_admin_bar'     => _x( 'Collection', 'add new on admin bar', 'wplr-sync-collections' ),
      'add_new'            => _x( 'Add New', 'collection', 'wplr-sync-collections' ),
      'add_new_item'       => __( 'Add New Collection', 'wplr-sync-collections' ),
      'new_item'           => __( 'New Collection', 'wplr-sync-collections' ),
      'edit_item'          => __( 'Edit Collection', 'wplr-sync-collections' ),
      'view_item'          => __( 'View Collection', 'wplr-sync-collections' ),
      'all_items'          => __( 'All Collections', 'wplr-sync-collections' ),
      'search_items'       => __( 'Search Collections', 'wplr-sync-collections' ),
      'parent_item_colon'  => __( 'Parent Collections:', 'wplr-sync-collections' ),
      'not_found'          => __( 'No collections found.', 'wplr-sync-collections' ),
      'not_found_in_trash' => __( 'No collections found in Trash.', 'wplr-sync-collections' )
    );
    $args = array(
      'labels'             		=> $collections,
      'public'             		=> true,
      'publicly_queryable' 		=> true,
      'show_ui'            		=> true,
      'show_in_menu'       		=> true,
      'query_var'          		=> true,
      'rewrite'            		=> array( 'slug' => 'collection' ),
      'has_archive'        		=> true,
      'hierarchical'       		=> true,
      'capability_type'		 		=> 'post',
      'map_meta_cap'			 		=> true,
      'menu_position'      		=> 10,
      'menu_icon'             => 'dashicons-camera',
      'supports'							=> array( 'title', 'editor', 'thumbnail' )
    );
    register_post_type( 'collection', $args );
  }

  function create_collection( $collectionId, $inFolderId, $collection, $isFolder = false ) {
    global $wplr;

    // If exists already, avoid re-creating
    $hasMeta = $wplr->get_meta( "wplr_collections", $collectionId );
    if ( !empty( $hasMeta ) )
      return;

    // Get the ID of the parent collection (if any) - check the end of this function for more explanation.
    $post_parent = null;
    if ( !empty( $inFolderId ) )
      $post_parent = $hasMeta = $wplr->get_meta( "wplr_collections", $inFolderId );

    // Create the collection.
    $post = array(
      'post_title'    => wp_strip_all_tags( $collection['name'] ),
      'post_content'  => $isFolder ? '' : '[gallery ids=""]', // if folder, nothing, if collection, let's start a gallery
      'post_status'   => 'publish',
      'post_type'     => 'collection',
      'post_parent'   => $post_parent
    );
    $id = wp_insert_post( $post );

    $wplr->set_meta( "wplr_collections", $collectionId, $id );
  }

  function create_folder( $folderId, $inFolderId, $folder ) {
    // Well, we can say that a folder is a collection (we could have use a taxonomy for that too)
    // Let's keep it simple and re-use the create_collection with an additional parameter to avoid having content.
    $this->create_collection( $folderId, $inFolderId, $folder, true );
  }

  // Updated the collection with new information.
  // Currently, that would be only its name.
  function update_collection( $collectionId, $collection ) {
    global $wplr;
    $id = $wplr->get_meta( "wplr_collections", $collectionId );
    $post = array( 'ID' => $id, 'post_title' => wp_strip_all_tags( $collection['name'] ) );
    wp_update_post( $post );
  }

  // Updated the folder with new information.
  // Currently, that would be only its name.
  function update_folder( $folderId, $folder ) {
    $this->update_collection( $folderId, $folder );
  }

  // Moved the collection under another folder.
  // If the folder is empty, then it is the root.
  function move_collection( $collectionId, $folderId, $previousFolderId ) {
    global $wplr;
    $post_parent = null;
    if ( !empty( $folderId ) )
      $post_parent = $wplr->get_meta( "wplr_collections", $folderId );
    $id = $wplr->get_meta( "wplr_collections", $collectionId );
    $post = array( 'ID' => $id, 'post_parent' => $post_parent );
    wp_update_post( $post );
  }

  // Added meta to a collection.
  // The $mediaId is actually the WordPress Post/Attachment ID.
  function add_media_to_collection( $mediaId, $collectionId, $isRemove = false ) {
    global $wplr;
    $id = $wplr->get_meta( "wplr_collections", $collectionId );
    $content = get_post_field( 'post_content', $id );
    preg_match_all( '/\[gallery.*ids="([0-9,]*)"\]/', $content, $results );
    if ( !empty( $results ) && !empty( $results[1] ) ) {
      $str = $results[1][0];
      $ids = !empty( $str ) ? explode( ',', $str ) : array();
      $index = array_search( $mediaId, $ids, false );
      if ( $isRemove ) {
        if ( $index !== FALSE )
          unset( $ids[$index] );
      }
      else {
        // If mediaId already there then exit.
        if ( $index !== FALSE )
          return;
        array_push( $ids, $mediaId );
      }
      // Replace the array within the gallery shortcode.
      $content = str_replace( 'ids="' . $str, 'ids="' . implode( ',', $ids ), $content );
      $post = array( 'ID' => $id, 'post_content' => $content );
      wp_update_post( $post );

      // Add a default featured image if none
      add_post_meta( $id, '_thumbnail_id', $mediaId, true );
    }
  }

  // Remove media from the collection.
  function remove_media_from_collection( $mediaId, $collectionId ) {
    global $wplr;
    $this->add_media_to_collection( $mediaId, $collectionId, true );

    // Need to delete the featured image if it was this media
    $postId = $wplr->get_meta( "wplr_posts", $collectionId );
    $thumbnailId = get_post_meta( $postId, '_thumbnail_id', -1 );
    if ( $thumbnailId == $mediaId )
      delete_post_meta( $postId, '_thumbnail_id' );
  }

  // The collection was deleted.
  function remove_collection( $collectionId ) {
    global $wplr;
    $id = $wplr->get_meta( "wplr_collections", $collectionId );
    wp_delete_post( $id, true );
    $wplr->delete_meta( "wplr_collections", $collectionId );
  }
}

new WPLR_Extension_Collections;

?>
