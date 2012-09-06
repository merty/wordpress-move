<?php
/**
 * WP Move Uninstaller
 *
 * @author Mert Yazicioglu
 * @date 2011-07-04 22:16:00 +03:00
 */

// Do nothing unless sent by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	die();

// Delete the options we've created	
delete_option( 'wpmove_options' );

?>