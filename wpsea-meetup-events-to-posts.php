<?php

if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

if( !class_exists( 'wpSeaMeetupEventsToPosts' ) )
{
	class wpSeaMeetupEventsToPosts
	{
		const PREFIX			= 'wsmetp_';
		const VERSION			= '0.1a';
		const POST_TYPE_NAME	= 'Meetup Event';
		const POST_TYPE_SLUG	= 'wpsea-meetup-event';
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct()
		{
			add_action( 'init',										array( $this, 'init' ) );
			add_action( 'init',										array( $this, 'create_post_type' ) );
			add_action( 'admin_init',								array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post',								array( $this, 'save_post' ), 10, 2 );
			add_action( self::PREFIX . 'cron_import_meetup_events',	array( $this, 'import_meetup_events' ) );
			
			add_filter( 'cron_schedules',							array( $this, 'add_custom_cron_intervals' ) );
			
			//add_shortcode( 'cpt-shortcode-example',	array( $this, 'shortcode_wpsea_meetup_posts' );
				// does a shortcode makes sense to output the posts? or use a cpt template in the theme instead? 
		}
		
		/**
		 * Prepares site to use the plugin during activation
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param bool $networkWide
		 */
		public function activate( $networkWide )
		{
			$this->create_post_type();
			$this->register_cron_jobs();
			flush_rewrite_rules();
		}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function deactivate()
		{
			wp_clear_scheduled_hook( self::PREFIX . 'import_meetup_events' );
			flush_rewrite_rules();
		} 
		
		/**
		 * Initializes variables
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function init()
		{
			if( did_action( 'init' ) !== 1 )
				return;
			
			$this->meetup_api_dir =  __DIR__ . '/includes/meetup-api-client-for-php';
		}
		
	
	
		/*
		 * Custom post type 
		 */

		/**
		 * Registers the custom post type
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function create_post_type()
		{
			if( did_action( 'init' ) !== 1 )
				return;

			if( !post_type_exists( self::POST_TYPE_SLUG ) )
			{
				$post_type_params = $this->get_post_type_params();
				$post_type = register_post_type( self::POST_TYPE_SLUG, $post_type_params );
				
				if( is_wp_error( $post_type ) )
				{
					// TODO: add admin notice 
				}
			}
		}

		/**
		 * Defines the parameters for the custom post type
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return array
		 */
		protected function get_post_type_params()
		{
			$labels = array
			(
				'name'					=> self::POST_TYPE_NAME . 's',
				'singular_name'			=> self::POST_TYPE_NAME,
				'add_new'				=> 'Add New',
				'add_new_item'			=> 'Add New '. self::POST_TYPE_NAME,
				'edit'					=> 'Edit',
				'edit_item'				=> 'Edit '. self::POST_TYPE_NAME,
				'new_item'				=> 'New '. self::POST_TYPE_NAME,
				'view'					=> 'View '. self::POST_TYPE_NAME . 's',
				'view_item'				=> 'View '. self::POST_TYPE_NAME,
				'search_items'			=> 'Search '. self::POST_TYPE_NAME . 's',
				'not_found'				=> 'No '. self::POST_TYPE_NAME .'s found',
				'not_found_in_trash'	=> 'No '. self::POST_TYPE_NAME .'s found in Trash',
				'parent'				=> 'Parent '. self::POST_TYPE_NAME
			);

			$post_type_params = array(
				'labels'				=> $labels,
				'singular_label'		=> self::POST_TYPE_NAME,
				'public'				=> true,
				'menu_position'			=> 20,
				'hierarchical'			=> true,
				'capability_type'		=> 'post',
				'has_archive'			=> true,
				'rewrite'				=> array( 'slug' => self::POST_TYPE_SLUG, 'with_front' => false ),
				'query_var'				=> true,
				'supports'				=> array( 'title', 'editor', 'author', 'thumbnail', 'revisions' )
			);
			
			return apply_filters( self::PREFIX . 'post-type-params', $post_type_params );
		}

		/**
		 * Adds meta boxes for the custom post type
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function add_meta_boxes()
		{
			if( did_action( 'admin_init' ) !== 1 )
				return;

			add_meta_box(
				self::PREFIX . 'event-details',
				'Meetup Event Details',
				array( $this, 'markup_meta_boxes' ),
				self::POST_TYPE_SLUG,
				'normal',
				'core'
			);
		}

		/**
		 * Builds the markup for all meta boxes
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param object $post
		 * @param array $box
		 */
		public function markup_meta_boxes( $post, $box )
		{
			switch( $box[ 'id' ] )
			{
				case self::PREFIX . 'event-details':
					//$exampleBoxField = get_post_meta( $post->ID, self::PREFIX . 'example-box-field', true );
					$view = 'metabox-event-details.php';
				break;
			}
			
			$view = dirname( __FILE__ ) . '/views/' . $view;
			if( is_file( $view ) )
				require_once( $view );
			else
				throw new Exception( __METHOD__ . " error: ". $view ." doesn't exist." );
		}

		/**
		 * Saves values of the the custom post type's extra fields
		 * @mvc Controller
		 * @param int $post_id
		 * @param object $post
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function save_post( $post_id, $revision )
		{
			global $post;
			$ignored_actions = array( 'trash', 'untrash', 'restore' );
			
			if( did_action( 'save_post' ) !== 1 )
				return;
			
			if( isset( $_GET[ 'action' ] ) && in_array( $_GET[ 'action' ], $ignored_actions ) )
				return;

			if(	!$post || $post->post_type != self::POST_TYPE_SLUG || !current_user_can( 'edit_posts', $post_id ) )
				return;

			if( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' )
				return;

			self::save_custom_fields( $post_id, $_POST );
		}

		/**
		 * Validates and saves values of the the custom post type's extra fields
		 * @mvc Model
		 * @param int $post_id
		 * @param array $new_values
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function save_custom_fields( $post_id, $new_values )
		{
			/*
			if( true )
				update_post_meta( $postID, self::PREFIX . 'example-box-field', $newValues[ self::PREFIX . 'example-box-field' ] );
			else
				WordPressPluginSkeleton::$notices->enqueue( 'Example of failing validation', 'error' );
			*/
		}
		
		/**
		 * Defines the [wpps-cpt-shortcode] shortcode
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes
		 * return string
		public function cptShortcodeExample( $attributes ) 
		{
			$attributes = apply_filters( self::PREFIX . 'cpt-shortcode-example-attributes', $attributes );
			$attributes = self::validateCPTShortcodeExampleAttributes( $attributes );
			
			ob_start();
			require_once( dirname( __DIR__ ) . '/views/wpps-cpt-example/shortcode-cpt-shortcode-example.php' );
			$output = ob_get_clean();
			
			return apply_filters( self::PREFIX . 'cpt-shortcode-example', $output );
		}
		*/
		 
		/**
		 * Validates the attributes for the [cpt-shortcode-example] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes
		 * return array
		 */
		protected function validate_event_details_attributes( $attributes )
		{
			$defaults = self::get_default_event_details_attributes();
			$attributes = shortcode_atts( $defaults, $attributes );
			
			/*
			if( $attributes[ 'foo' ] != 'valid data' )
				$attributes[ 'foo' ] = $defaults[ 'foo' ];
			*/
			
			return apply_filters( self::PREFIX . 'validate-event-details-attributes', $attributes );
		}

		/**
		 * Defines the default arguments for the [cpt-shortcode-example] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array
		 * @return array
		 */
		protected function get_default_event_details_attributes()
		{
			$attributes = array(
				'foo'	=> 'bar',
				'bar'	=> 'foo'
			);
			
			return apply_filters( self::PREFIX . 'default-cpt-shortcode-example-attributes', $attributes );
		}
		
		
		
		/*
		 * Cron jobs 
		 */
		
		/**
		 * Adds custom intervals to the cron schedule.
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $schedules
		 * @return array
		 */
		public function add_custom_cron_intervals( $schedules )
		{
			$schedules[ self::PREFIX . 'debug' ] = array(
				'interval'	=> 5,
				'display'	=> 'Every 5 seconds'
			);
			
			return $schedules;
		}
		
		/**
		 * Registers cron jobs with WordPress
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function register_cron_jobs()
		{ 
			if( wp_next_scheduled( self::PREFIX . 'cron_import_meetup_events' ) === false )
			{
				wp_schedule_event(
					current_time( 'timestamp' ),
					self::PREFIX . 'debug', // TODO: 'hourly',
					self::PREFIX . 'cron_import_meetup_events'
				);
			}
		}

		/**
		 * Import Meetup.com events into WordPress posts
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $schedules
		 * @return array
		 */
		public function import_meetup_events()
		{
			if( did_action( self::PREFIX . 'cron_import_meetup_events' ) !== 1 )
				return;
			
			// TODO: this is getting run twice? make sure it doesn't
			
			$this->load_meetup_api();
			$events = $this->get_recent_meetup_events();
			$this->create_posts_from_events( $events );
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
			require_once( $this->meetup_api_dir . '/Meetup.php' );
			require_once( $this->meetup_api_dir . '/MeetupConnection.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupApiResponse.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupApiRequest.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupExceptions.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupCheckins.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupEvents.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupFeeds.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupGroups.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupMembers.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupPhotos.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupRsvps.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupTopics.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupVenues.class.php' );
			require_once( $this->meetup_api_dir . '/MeetupOAuth2Helper.class.php' );
		}

		/**
		 * Pulls recently created events from the Meetup API
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return array
		 */
		protected function get_recent_meetup_events()
		{
			// TODO: possible to get ones we don't already have? if not just grab recent ones, and let the other function take care of making sure no dupes are created
			return array( 'test', 'test2' );
		}
		
		/**
		 * Creates WordPress posts from Meetup events
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $events
		 */
		protected function create_posts_from_events( $events )
		{
			if( $events )
			{
				foreach( $events as $event )
				{
					if( true )	// TODO: post doesn't already exist for this event
					{
						// TODO: wp_insert_post();
					}
				}
			}
		}
	} // end wpSeaMeetupEventsToPosts
	
	$wpfps = new wpSeaMeetupEventsToPosts();
}

?>