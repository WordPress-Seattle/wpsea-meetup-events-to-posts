<?php
/*
Plugin Name: WPSEA Meetup Events to Posts
Description: Creates posts in a WordPress site from events on Meetup.com
Version: 0.1
Author: Seattle WordPress Meetup
Author URI: https://github.com/WordPress-Seattle
*/
 
if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

if( !class_exists( 'wpSeaMeetupEventsToPosts' ) )
{
	class wpSeaMeetupEventsToPosts
	{	
		const PREFIX = 'wsmetp_';
		
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct()
		{
			$this->meetup_api_dir =  __DIR__ . '/includes/meetup-api-client-for-php';
			$this->load_meetup_api();
		}
		
		/**
		 * Load the Meetup API if it hasn't been loaded yet
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function load_meetup_api()
		{
			if( defined( 'MEETUP_API_URL' ) )
				return;
			
			// Note: Most of this will probably happen in the API itself in the future
			require_once( $this->meetup_api_dir .'/Meetup.php' );
			require_once( $this->meetup_api_dir .'/MeetupConnection.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupApiResponse.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupApiRequest.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupExceptions.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupCheckins.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupEvents.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupFeeds.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupGroups.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupMembers.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupPhotos.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupRsvps.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupTopics.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupVenues.class.php' );
			require_once( $this->meetup_api_dir .'/MeetupOAuth2Helper.class.php' );
		}

		
	} // end wpSeaMeetupEventsToPosts
	
	$wpfps = new wpSeaMeetupEventsToPosts();
}

?>