=== WP/LR Sync ===
Contributors: TigrouMeow
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=H2S7S3G4XMJ6J
Tags: lightroom, photoshop, sync, synchronization, image, management, admin, export, media, image, photo
Requires at least: 3.5
Tested up to: 4.2.0
Stable tag: 1.3.6

Synchronize your photos and metadata between Lightroom and WordPress.

== Description ==

Synchronize your photos and metadata between Lightroom and WordPress. This plugin is made to work like a charm with the WP/LR Sync plugin for Lightroom (http://apps.meow.fr/wplr-sync).

Lightroom will be then able to add, update and delete photos directly. Titles, captions and alternative texts can be also synchronized. 

If you dream to link the photos you uploaded previously with the original ones in Lightroom, this is possible. Using EXIF and image perceptual analysis, the plugin will offer you to link them. You also have the choice to do it manually through WordPress. The user interface will guide you through it.

Languages: English.

== Installation ==

1. Upload `wp-lrsync` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Install the Lightroom plugin from here: http://apps.meow.fr/wplr-sync 
4. Synchronize everything :)

== Upgrade Notice ==

Replace all the files. Nothing else to do.

== Frequently Asked Questions ==

Official FAQ is here: http://apps.meow.fr/wplr-sync/faq.

== Changelog ==

= 1.3.6 =
* Fix: Issue with rights on uploaded files.

= 1.3.5 =
* Fix: Multilanguage support issue with un-links detection (not a major bug).

= 1.3.4 =
* Change: The temporary directory has been changed to avoid issues.

= 1.3.3 =
* Fix: Presync support for HVVM.
* Fix: Handle broken databases nicely and period of time when the plugin is turned off (and changes are made).
* FIx: Plugin keeps its own DB table clean.

= 1.3.2 =
* Add: Presync. PHP settings are sent to LR to prevent errors.

= 1.2.4 =
* Add: Update the empty WP metadata with the LR data.

= 1.2.2 =
* Fix: Change on how temporary files are created (to support HHVM).

= 1.2.0 =
* Add: Additional features to support WPML Media.
* Update: Switch Photos module enabled in LR.

= 0.8.8 =
* Add: Compatibility with WPML Media (translation plugin for the media images).
* Info: WPML Media needs to change something on their side for full support. Please check this post: http://apps.meow.fr/wpml-media-and-wplr-sync/.

= 0.8.6 =
* Add: Ignore button.
* Add: Post attachment information and showing the titles of post and media when hovering the links.
* Fix: When linking a media, the page doesn't scroll up annoyingly to the top anymore.
* Info: If you like the plugin, please help me finding new users! Adding a review on the Adobe Exchange could help, here: https://www.adobeexchange.com/partners/29997/products. Thank you all! Merry Christmas & Happy Holidays! :)

= 0.8.3 =
* Add: Handlers of new module in the LR plugin (module to switch photos).
* Info: In preparation of the future release of the LR plugin (1.2).

= 0.8 =
* Fix: Duplicate files are now all deleted on WP when the LR photo is removed (against only the first one before).
* Info: Minor and major number of the most current version between the WP and LR plugins will always match from now.

= 0.7 =
* Add: Undo function available.
* Info: This is in preparation for the future version of the Lightroom plugin.

= 0.6 =
* Fix: Many WP images linked to same LR image? They now all update, instead of only the first one previously.

= 0.4 =
* Fix: Title was not being updated properly during sync.

= 0.3 =
* Add: Dashboard for WPLR.
* Update: Better sync/link management through the Media Manager.

= 0.2 =
* Add: Handles metadata.
* Fix: XML/RPC Sync.

= 0.1 =
* First release.

== Screenshots ==

1. Settings in the WP/LR Sync Lightroom Publish Service
2. Total Synchronization Module
3. Advanced Tab in Total Synchronization
