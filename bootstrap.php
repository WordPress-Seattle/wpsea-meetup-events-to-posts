<?php
/*
Plugin Name: WPSEA Meetup Events to Posts
Plugin URI: https://github.com/WordPress-Seattle/wpsea-meetup-events-to-posts
Description: Creates WordPress posts from a Meetup group's events 
Version: 0.1a
Author: Seattle WordPress Meetup
Author URI: http://wpseattle.org
License: GPL2
*/

/*  
 * Copyright 2011-2012 Seattle WordPress Meetup (website : http://wpseattle.org)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

define( 'WSMETP_NAME',					'WPSEA Meetup Events to Posts' );
define( 'WSMETP_REQUIRED_PHP_VERSION',	'5.3' );	// because of __DIR__
define( 'WSMETP_REQUIRED_WP_VERSION',	'3.5' );	// because of WP_Post class

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