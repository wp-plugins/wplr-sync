<?php
/*
Hyphens are added in the header below to avoid WordPress to recognize this extension as a real plugin. It's actually not a big problem but it displays an error while installing the plugin. If you want to use this extension as a standalone plugin, you can remove the hyphens ;)

- Plugin Name: Basic Posts
- Plugin URI: http://www.meow.fr
- Description: A collection on LR will become a post on WP and the gallery within it will be kept synchronized.<br />Folders are ignored so they can be used to clearly organize you LR hierarchy.
- Version: 0.1.0
- Author: Jordy Meow
- Author URI: http://www.meow.fr
*/

class WPLR_Extension_Posts {

  public function __construct() {

    // Init
    add_filter( 'wplr_extensions', array( $this, 'extensions' ), 10, 1 );

    // Collection
    add_action( 'wplr_create_collection', array( $this, 'create_collection' ), 10, 3 );
    add_action( "wplr_remove_collection", array( $this, 'remove_collection' ), 10, 1 );

    // Media
    add_action( "wplr_add_media_to_collection", array( $this, 'add_media_to_collection' ), 10, 2 );
    add_action( "wplr_remove_media_from_collection", array( $this, 'remove_media_from_collection' ), 10, 2 );
  }

  function extensions( $extensions ) {
    array_push( $extensions, 'Basic Posts' );
    return $extensions;
  }

  function create_collection( $collectionId, $inFolderId, $collection ) {
    global $wplr;

    // If exists already, avoid re-creating
    $hasMeta = $wplr->get_meta( "wplr_posts", $collectionId );
    if ( !empty( $hasMeta ) )
      return;

    // Create the post
    $post = array(
      'post_title'    => wp_strip_all_tags( $collection['name'] ),
      'post_content'  => '[gallery ids=""]',
      'post_status'   => 'publish',
      'post_type'     => 'post'
    );
    $id = wp_insert_post( $post );

    // Use the WPLR metadata to set a link between the collection and the post we have just created
    $wplr->set_meta( 'wplr_posts', $collectionId, $id );
  }

  // Add media to a collection.
  // The $mediaId is the attachment ID.
  function add_media_to_collection( $mediaId, $collectionId, $isRemove = false ) {
    global $wplr;
    $id = $wplr->get_meta( "wplr_posts", $collectionId );
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
    $id = $wplr->get_meta( "wplr_posts", $collectionId );
    wp_delete_post( $id, true );
    $wplr->delete_meta( "wplr_posts", $collectionId );
  }
}

new WPLR_Extension_Posts;

?>
