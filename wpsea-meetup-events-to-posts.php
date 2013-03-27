<?php

if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

if( !class_exists( 'wpSeaMeetupEventsToPosts' ) )
{
	class wpSeaMeetupEventsToPosts
	{
		protected $settings, $meetup_api_dir, $meetup_api_connection;
		const PREFIX				= 'wsmetp_';
		const VERSION				= '0.1a';
		const POST_TYPE_NAME		= 'Meetup Event';
		const POST_TYPE_SLUG		= 'wpsea-meetup-event';
		const REQUIRED_CAPABILITY	= 'manage_options'; 
		
		/**
		 * Constructor
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct()
		{
			add_action( 'init',										array( $this, 'init' ) );
			add_action( 'init',										array( $this, 'create_post_type' ) );
			add_action( 'admin_init',								array( $this, 'add_meta_boxes' ) );
			add_action( 'admin_init',								array( $this, 'register_settings' ) );
			add_action( 'save_post',								array( $this, 'save_post' ), 10, 2 );
			add_action( self::PREFIX . 'cron_import_meetup_events',	array( $this, 'import_meetup_events' ) );
			
			
			add_filter( 'cron_schedules',							array( $this, 'add_custom_cron_intervals' ) );
			add_filter(
				'plugin_action_links_'. plugin_basename( __DIR__ ) .'/bootstrap.php',
				array( $this, 'add_plugin_action_links' )
			);
			
			add_shortcode( 'meetup-events',							array( $this, 'shortcode_meetup_events' ) );
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
			
			$this->settings					= $this->get_settings();
			$this->meetup_api_dir			=  __DIR__ . '/includes/meetup-api-client-for-php';
			$this->meetup_api_connection	= null;
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
					// TODO: add admin notice or throw exception or something
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
				'hierarchical'			=> false,
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
					// TODO: grab any data needed for the view
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
				self::$notices->enqueue( 'Example of failing validation', 'error' );
			*/
		}
		
	
		
		/*
		 * Shortcodes 
		 */
		
		/**
		 * Generates the output for the [meetup-events] shortcode
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes
		 * return string
		 */
		public function shortcode_meetup_events( $attributes )
		{
			$attributes = apply_filters( self::PREFIX . 'meetup-events-shortcode-attributes', $attributes );
			$attributes = self::validate_meetup_events_shortcode_attributes( $attributes );
			
			$event_posts	= get_posts( array(
				'numberposts'	=> -1,
				'post_type'		=> self::POST_TYPE_SLUG,
				
				// TODO: only query ones where the ending timestamp is in the future? probably don't need to display old ones
			) );
			
			ob_start();
			require_once( __DIR__ . '/views/shortcode-meetup-events.php' );
			$output = ob_get_clean();
			
			return apply_filters( self::PREFIX . 'meetup-events-shortcode-return', $output );
		}
		 
		/**
		 * Validates the attributes for the [meetup-events] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $attributes
		 * return array
		 */
		protected function validate_meetup_events_shortcode_attributes( $attributes )
		{
			$defaults = self::get_default_meetup_event_shortcode_attributes();
			$attributes = shortcode_atts( $defaults, $attributes );
			
			return apply_filters( self::PREFIX . 'validate-meetup-events-shortcode-attributes', $attributes );
		}

		/**
		 * Defines the default arguments for the [meetup-events] shortcode
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array
		 * @return array
		 */
		protected function get_default_meetup_event_shortcode_attributes()
		{
			$attributes = array();
			
			return apply_filters( self::PREFIX . 'default-meetup-events-shortcode-attributes', $attributes );
		}
		
		
		
		/*
		 * Settings 
		 */
		  
		/**
		 * Establishes initial values for all settings
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return array
		 */
		protected function get_default_settings()
		{
			return array(
				'meetup_api_key'		=> '',
				'meetup_group_url_name'	=> '',
			);
		}
		
		/**
		 * Retrieves all of the settings from the database
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return array
		 */
		protected function get_settings()
		{	
			$settings = shortcode_atts(
				$this->get_default_settings(),
				get_option( self::PREFIX . 'settings', array() )
			);
			
			return $settings;
		} 
	
		/**
		 * Adds links to the plugin's action link section on the Plugins page
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $links The links currently mapped to the plugin
		 * @return array
		 */
		public function add_plugin_action_links( $links )
		{
			//if( did_action( 'plugin_action_links_'. plugin_basename( __DIR__ ) .'/bootstrap.php' ) != 1 )
				//return $links;
			// TODO: this is getting added twice for some reason. maybe filter getting called multiple times.
			
			array_unshift( $links, '<a href="'. admin_url( 'options-general.php' ) .'">Settings</a>' );
			
			return $links;
		}
	
		/**
		 * Registers settings sections, fields and settings
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function register_settings()
		{
			if( did_action( 'admin_init' ) !== 1 )
				return;

			add_settings_section(
				self::PREFIX . 'section-settings',
				WSMETP_NAME,
				array( $this, 'markup_section_headers' ),
				'general'
			);
			
			add_settings_field(
				self::PREFIX . 'meetup_api_key',
				'Meetup.com API Key',
				array( $this, 'markup_fields' ),
				'general',
				self::PREFIX . 'section-settings',
				array( 'label_for' => self::PREFIX . 'meetup_api_key' )
			);
			
			add_settings_field(
				self::PREFIX . 'meetup_group_url_name',
				'Meetup Group URL Name',
				array( $this, 'markup_fields' ),
				'general',
				self::PREFIX . 'section-settings',
				array( 'label_for' => self::PREFIX . 'meetup_group_url_name' )
			);
			
			// The settings container
			register_setting(
				'general',
				self::PREFIX . 'settings',
				array( $this, 'validate_settings' )
			);
		}

		/**
		 * Adds the section introduction text to the Settings page
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $section
		 */
		public function markup_section_headers( $section )
		{
			require( __DIR__ . '/views/settings-section-headers.php' );
		}

		/**
		 * Delivers the markup for settings fields
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $field
		 */
		public function markup_fields( $field )
		{
			switch( $field[ 'label_for' ] )
			{
				case self::PREFIX . 'meetup_api_key':
					// Do any extra processing here
				break;
				
				case self::PREFIX . 'meetup_group_url_name':
					// Do any extra processing here
				break;
			}

			require( __DIR__ . '/views/settings-fields.php' );
		}

		/**
		 * Validates submitted setting values before they get saved to the database. 
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $new_settings
		 * @return array
		 */
		public function validate_settings( $new_settings )
		{
			$new_settings = shortcode_atts( $this->settings, $new_settings );
			
			// meetup_api_key
			$new_settings[ 'meetup_api_key' ] = strip_tags( $new_settings[ 'meetup_api_key' ] );
			
			// meetup_group_url_name -- Strip out URL bits in case the user pasted one in
			$group_url_name_trim = array( 'https', 'http', '://', 'www.', 'meetup.com', '/' );
			$new_settings[ 'meetup_group_url_name' ] = str_replace( $group_url_name_trim, '', strip_tags( $new_settings[ 'meetup_group_url_name' ] ) );
			
			return $new_settings;
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
				'interval'	=> 15,
				'display'	=> 'Every 15 seconds'
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
					self::PREFIX . 'debug', // TODO: set to 'hourly' when done testing
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
			$this->create_update_posts_from_events( $this->get_upcoming_meetup_events() );
		}
		
		/**
		 * Load the Meetup API if it hasn't been loaded yet
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function load_meetup_api()
		{
			if( !defined( 'MEETUP_API_URL' ) )
			{
				// Note: Most of this will probably happen in the API library itself in the future
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
			
			if( $this->settings[ 'meetup_api_key' ] && class_exists( 'MeetupKeyAuthConnection' ) )
				$this->meetup_api_connection = new MeetupKeyAuthConnection( $this->settings[ 'meetup_api_key' ] );
			else
			{
				// TODO: create an admin notice or throw an exception or something
			}
		}

		/**
		 * Pulls upcoming created events from the Meetup API
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return array
		 */
		protected function get_upcoming_meetup_events()
		{
			$upcoming_events = array();
			
			if( $this->meetup_api_connection && $this->settings[ 'meetup_group_url_name' ] && class_exists( 'MeetupEvents' ) )
			{
				$events_request		= new MeetupEvents( $this->meetup_api_connection );
				$upcoming_events	= $events_request->getEvents( array(
					'group_urlname'	=> $this->settings[ 'meetup_group_url_name' ],
					'status'		=> 'upcoming',
				) );
			}
			
			return $upcoming_events;
		}
		
		/**
		 * Creates or updates WordPress posts from Meetup events
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array $events
		 */
		protected function create_update_posts_from_events( $events )
		{
			var_dump($events);	// TODO
			
			
			if( $events )
			{
				$existing_post_id_map = $this->get_existing_post_id_map();
				return;		// TODO: not ready to insert yet
				
				// Loop through all the events we were given 
				foreach( $events as $event )
				{
					$event_post = array(
						'post_author'	=> '?',		// TODO get admin id. can't assume it'll be 1, because that account may have been deleted. have to find all admins and pick the first one, or something like that.
						'post_content'	=> '?',		// TODO: this isn't included in the API results. wtf mate? maybe have to make more specific calls to API for details
						'post_status'	=> 'publish',
						'post_title'	=> '?',
						'post_type'		=> self::POST_TYPE_SLUG,
					);
					
					if( array_key_exists( $event[ 'id' ], $existing_post_id_map ) )
					{
						// A post has already been created for this event, so just update it
						
						$event_post[ 'ID' ] = $existing_post_id_map[ $event[ 'id' ] ];
						wp_update_post( $event_post );
					}
						
					else
					{
						// A post hasn't been created for this event yet, so create one
						
						$event_post[ 'ID' ] = wp_insert_post( $event_post, true );
						
						if( is_wp_error( $event_post[ 'ID' ] ) )
						{
							// TODO: add admin notice, or throw exception, or somethign
						}
					}
					
					// TODO: whether creating or updating, update post meta w/ custom fields like meetup_event_id, location, date, time, etc
				}
			}
		}

		/**
		 * Retrieves the existing event posts and maps their WordPress post ID to the Meetup.com event ID
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return array
		 */
		protected function get_existing_post_id_map()
		{
			$map			= array();
			$event_posts	= get_posts( array(
				'numberposts'	=> -1,
				'post_type'		=> self::POST_TYPE_SLUG,
				'post_status'	=> 'any',
				
				// TODO: only query ones where the ending timestamp is in the future? probably don't need to bother updating previous
			) );
			
			if( $event_posts )
			{
				foreach( $event_posts as $event_post )
				{	
					if( $event_post->meetup_event_id )
						$map[ $event_post->meetup_event_id ] = $event_post->ID;
				}
			}
			
			var_dump($map);	// TODO: test once get some real data imported
			return $map;
		}
	} // end wpSeaMeetupEventsToPosts
	
	$wpfps = new wpSeaMeetupEventsToPosts();
}

?>