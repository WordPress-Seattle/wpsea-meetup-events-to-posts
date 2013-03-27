<?php
/*
Plugin Name: WPSEA Meetup Events to Posts
Description: Creates WordPress posts from events on Meetup.com 
Version: 0.1a
Author: Seattle WordPress Meetup
Author URI: https://github.com/WordPress-Seattle
*/

if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

define( 'WSMETP_NAME',					'WPSEA Meetup Events to Posts' );
define( 'WSMETP_REQUIRED_PHP_VERSION',	'5.3' );	// because of __DIR__
define( 'WSMETP_REQUIRED_WP_VERSION',	'3.0' );	// because of custom post type support

/**
 * Checks if the system requirements are met
 * @author Ian Dunn <ian@iandunn.name>
 * @return bool True if system requirements are met, false if not
 */
function wsmetp_requirements_met()
{
	global $wp_version;
	
	if( version_compare( PHP_VERSION, WSMETP_REQUIRED_PHP_VERSION, '<' ) )
		return false;
	
	if( version_compare( $wp_version, WSMETP_REQUIRED_WP_VERSION, '<' ) )
		return false;
	
	return true;
}

/**
 * Prints an error that the system requirements weren't met.
 * @author Ian Dunn <ian@iandunn.name>
 */
function wsmetp_requirements_error()
{
	global $wp_version;
	
	ob_start();
	require_once( __DIR__ . '/views/requirements-error.php' );
	$message = ob_get_contents();
	ob_end_clean();
	
	echo $message;
}

// Check requirements and load main class
// The main program needs to be in a separate file that only gets loaded if the plugin requirements are met. Otherwise older PHP installations could crash when trying to parse it.
if( wsmetp_requirements_met() )
{
	require_once( __DIR__ . '/wpsea-meetup-events-to-posts.php' );
	
	if( class_exists( 'wpSeaMeetupEventsToPosts' ) )
	{
		$wsmetp = new wpSeaMeetupEventsToPosts();
		register_activation_hook( __FILE__,		array( $wsmetp, 'activate' ) );
		register_deactivation_hook( __FILE__,	array( $wsmetp, 'deactivate' ) );
	}
}
else
	add_action( 'admin_notices', 'wsmetp_requirements_error' );

?>