<?php
/**
 * Class for Facebook Imports.
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 *
 * @package    Import_Facebook_Events
 * @subpackage Import_Facebook_Events/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Import_Facebook_Events_Facebook {

	/*
	*	Facebook app ID
	*/
	public $fb_app_id;

	/*
	*	Facebook app Secret
	*/
	public $fb_app_secret;

	/*
	*	Facebook Graph URL
	*/
	public $fb_graph_url;

	/*
	*	Facebook Access Token
	*/
	private $fb_access_token;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $ife_events;
		
		$options = ife_get_import_options( 'facebook' );
		$this->fb_app_id = isset( $options['facebook_app_id'] ) ? $options['facebook_app_id'] : '';
		$this->fb_app_secret = isset( $options['facebook_app_secret'] ) ? $options['facebook_app_secret'] : '';
		$this->fb_graph_url = 'https://graph.facebook.com/v3.0/';

	}

	/**
	 * import facebook events by oraganization or facebook page.
	 *
	 * @since  1.0.0
	 * @param  array $eventdata  import event data.
	 * @return array/boolean
	 */
	public function import_events( $event_data = array() ){

		global $ife_errors, $ife_events;
		$imported_events = array();
		$facebook_event_ids = array();

		if( $this->fb_app_id == '' || $this->fb_app_secret == '' ){
			$ife_errors[] = __( 'Please insert Facebook app ID and app Secret.', 'import-facebook-events');
			return;
		}			

		$import_by = isset( $event_data['import_by'] ) ? esc_attr( $event_data['import_by'] ) : '';

		if( 'facebook_organization' == $import_by ){
			$page_username = isset( $event_data['page_username'] ) ? $event_data['page_username'] : '';
			if( $page_username == '' ){
				$ife_errors[] = __( 'Please insert valid Facebook page username.', 'import-facebook-events');
				return false;
			}
			if ( version_compare( IFEPRO_VERSION, '1.5.0', '<=' ) ) {
				$facebook_event_ids = $ife_events->facebook_pro->get_events_for_facebook_page( $page_username, 'page' );
			} else {
				$facebook_events = $ife_events->facebook_pro->get_events_for_facebook_page( $page_username, 'page' );
				if( !empty( $facebook_events ) ){
					foreach ( $facebook_events as $facebook_event ){
						$imported_event = $this->save_facebook_event( $facebook_event, $event_data );
						if( !empty( $imported_event ) ){
							foreach ($imported_event as $imported_event0 ) {
								$imported_events[] = $imported_event0;
							}
						}
					}
					return $imported_events;
				}
			}

		} elseif ( 'facebook_group' == $import_by ){
				
			$facebook_group_id = isset( $event_data['facebook_group_id'] ) ? $event_data['facebook_group_id'] : '';
			if( $facebook_group_id == '' ){
				$ife_errors[] = __( 'Please insert valid Facebook Group URL or ID.', 'import-facebook-events');
				return false;
			}

			if ( version_compare( IFEPRO_VERSION, '1.5.0', '<=' ) ) {
				$facebook_event_ids = $ife_events->facebook_pro->get_events_for_facebook_page( $facebook_group_id, 'group' );
			} else {
				$facebook_events = $ife_events->facebook_pro->get_events_for_facebook_page( $facebook_group_id, 'group' );
				if( !empty( $facebook_events ) ){
					foreach ( $facebook_events as $facebook_event ){
						$imported_event = $this->save_facebook_event( $facebook_event, $event_data );
						if( !empty( $imported_event ) ){
							foreach ($imported_event as $imported_event0 ) {
								$imported_events[] = $imported_event0;
							}
						}
					}
					return $imported_events;
				}
			}
			
		} elseif ( 'facebook_event_id' == $import_by ){
				
			$facebook_event_ids = isset( $event_data['event_ids'] ) ? $event_data['event_ids'] : array();
		}
		
		if( !empty( $facebook_event_ids ) ){
			foreach ($facebook_event_ids as $facebook_event_id ) {
				if( $facebook_event_id != '' ){
					$imported_event = $this->import_event_by_event_id( $facebook_event_id, $event_data );
					if( !empty( $imported_event ) ){
						foreach ($imported_event as $imported_event0 ) {
							$imported_events[] = $imported_event0;
						}					
					}			
				}
			}
		}
		return $imported_events;
	}

	/**
	 * import facebook event by ID.
	 *
	 * @since  1.0.0
	 * @param  array $eventdata  import event data.
	 * @return int/boolean
	 */
	public function import_event_by_event_id( $facebook_event_id, $event_data = array() ){

		global $ife_errors, $ife_events;
		$options = ife_get_import_options( 'facebook' );
		$update_events = isset( $options['update_events'] ) ? $options['update_events'] : 'no';
		
		if( $facebook_event_id == '' || $this->fb_app_id == '' || $this->fb_app_secret == '' ){
			if( $this->fb_app_id == '' || $this->fb_app_secret == '' ){
				$ife_errors[] = __( 'Please insert Facebook app ID and app Secret.', 'import-facebook-events');
				return;
			}
			return false;
		}
		if( $facebook_event_id == '' || !is_numeric( $facebook_event_id ) ){
			$ife_errors[] = sprintf( esc_html__( 'Please provide valid Facebook event ID: %s.', 'import-facebook-events' ), $facebook_event_id ) ;
			return false;
		}

		$facebook_event_object = $this->get_facebook_event_by_event_id( $facebook_event_id );
		if( isset( $facebook_event_object->error ) ){
			$ife_errors[] = sprintf( esc_html__( 'We are not able to access Facebook event: %s. Possible reasons: - App Credentials are wrong - Facebook event is not public or some restrictions are there like age,country etc.', 'import-facebook-events' ), $facebook_event_id ) ;
			return false;
		}
		return $this->save_facebook_event( $facebook_event_object, $event_data );
	}

	/**
	 * Save (Create or update) facebook imported to The Event Calendar Events.
	 *
	 * @since  1.0.0
	 * @param array  $facebook_event_object Event object get from facebook.com.
	 * @return void
	 */
	public function save_facebook_event( $facebook_event_object = array(), $event_args = array() ) {

		global $ife_events;
		$import_result = '';

		if ( ! empty( $facebook_event_object ) && isset( $facebook_event_object->id ) ) {
			$centralize_arrays = $this->generate_centralize_array( $facebook_event_object );
			$saved_events = array();
			if( !empty( $centralize_arrays ) ){
				foreach ($centralize_arrays as $centralize_array ) {
					$saved_events[] = $ife_events->common->import_events_into( $centralize_array, $event_args );
				}
			}
			return $saved_events;
		}
	}

	/**
	 * get access token
	 *
	 * @since 1.0.0
	 */
	public function get_access_token(){

		if( $this->fb_access_token != '' ){

			return $this->fb_access_token;

		}else{

			$args = array(
				'grant_type' => 'client_credentials', 
				'client_id'  => $this->fb_app_id,
				'client_secret' => $this->fb_app_secret
				);
			$access_token_url = add_query_arg( $args, $this->fb_graph_url . 'oauth/access_token' );
			$access_token_response = wp_remote_get( $access_token_url );
			$access_token_response_body = wp_remote_retrieve_body( $access_token_response );
			$access_token_data = json_decode( $access_token_response_body );
			$access_token = ! empty( $access_token_data->access_token ) ? $access_token_data->access_token : null;

			$ife_user_token_options = get_option( 'ife_user_token_options', array() );
			if( !empty( $ife_user_token_options ) && $access_token != '' ){
				$authorize_status =	isset( $ife_user_token_options['authorize_status'] ) ? $ife_user_token_options['authorize_status'] : 0;
				$user_access_token = isset( $ife_user_token_options['access_token'] ) ? $ife_user_token_options['access_token'] : '';
				if( 1 == $authorize_status && $user_access_token != '' ){

					$args = array(
						'input_token' => $user_access_token,
						'access_token'  => $access_token,
						);
					$access_token_url = add_query_arg( $args, $this->fb_graph_url . 'debug_token' );
					$access_token_response = wp_remote_get( $access_token_url );
					$access_token_response_body = wp_remote_retrieve_body( $access_token_response );
					$access_token_data = json_decode( $access_token_response_body );
					if( !isset( $access_token_data->error ) && $access_token_data->data->is_valid == 1 ){
						$access_token = $user_access_token;
					}else{
						$ife_user_token_options['authorize_status'] = 0;
						update_option( 'ife_user_token_options', $ife_user_token_options );
					}
				}
			}

			$this->fb_access_token = apply_filters( 'ife_facebook_access_token', $access_token );
			return $this->fb_access_token;
		}		
	}
	
	/**
	 * Generate Facebook api URL for grab Event.
	 *
	 * @since 1.0.0
	 */
	public function generate_facebook_api_url( $path = '', $query_args = array(), $access_token = '' ) {
		$query_args = array_merge( $query_args, array( 'access_token' => $this->get_access_token() ) );
		if( !empty( $access_token ) ){
			$query_args['access_token'] = $access_token;
		}
		$url = add_query_arg( $query_args, $this->fb_graph_url . $path );

		return $url;
	}

	/**
	 * get a facebook object.
	 *
	 * @since 1.0.0
	 */
	public function get_facebook_response_data( $event_id, $args = array() ) {
		$url = $this->generate_facebook_api_url( $event_id, $args );
		$event_data = $this->get_json_response_from_url( $url );
		return $event_data;
	}

	/**
	 * get a facebook event object
	 *
	 * @since 1.0.0
	 */
	public function get_facebook_event_by_event_id( $event_id ) {
		$fields = array(
					'id',
					'name',
					'description',
					'start_time',
					'end_time',
					'event_times',
					'cover',
					'ticket_uri',
					'timezone',
					'place',
				);
		$include_owner = apply_filters( 'ife_import_owner', false );
		if( $include_owner ){
			$fields[] = 'owner';
		}

		return $this->get_facebook_response_data(
			$event_id,
			array(
				'fields' => implode(
					',',
					$fields
				),
			)
		);
	}

	/**
	* Get body data from url and return decoded data.
	*
	* @since 1.0.0
	*/
	public function get_json_response_from_url( $url ) {
		$args = array( 'timeout' => 15 );
		$response = wp_remote_get( $url, $args );
		$response = json_decode( wp_remote_retrieve_body( $response ) );
		return $response;
	}

	/**
	 * Format events arguments as per TEC
	 *
	 * @since    1.0.0
	 * @param array $facebook_event Facebook event.
	 * @return array
	 */
	public function generate_centralize_array( $facebook_event ) {
		global $ife_events;

		if( !isset( $facebook_event->id ) || $facebook_event->id == '' ){
			return;
		}
		$start_time = $start_time_utc = time();
		$end_time = $end_time_utc = time();
		$utc_offset = '';

		$facebook_id = $facebook_event->id;
		$post_title = isset( $facebook_event->name ) ? $facebook_event->name : '';
		$post_description = isset( $facebook_event->description ) ? $facebook_event->description : '';
		
		$start_time = isset( $facebook_event->start_time ) ? strtotime( $ife_events->common->convert_datetime_to_db_datetime( $facebook_event->start_time ) ) : date( 'Y-m-d H:i:s');
		$end_time = isset( $facebook_event->end_time ) ? strtotime( $ife_events->common->convert_datetime_to_db_datetime( $facebook_event->end_time ) ) : $start_time;

		$ticket_uri = isset( $facebook_event->ticket_uri ) ? esc_url( $facebook_event->ticket_uri ) : 'https://www.facebook.com/events/'.$facebook_id;
		$timezone = $this->get_utc_offset( $facebook_event->start_time );
		$cover_image = isset( $facebook_event->cover->source ) ? $ife_events->common->clean_url( esc_url( $facebook_event->cover->source ) ) : '';

		$event_times_obj = isset( $facebook_event->event_times ) ? $facebook_event->event_times : array();
		$event_times = array();
		if( !empty( $event_times_obj ) ){
			foreach ($event_times_obj as $event_time ) {
				if( isset( $event_time->start_time ) && isset( $event_time->end_time ) ){
					$et_temp = array();
					$et_temp['ID'] = $event_time->id;
					$et_temp['start_time'] = strtotime( $ife_events->common->convert_datetime_to_db_datetime( $event_time->start_time ) );
					$et_temp['end_time'] = strtotime( $ife_events->common->convert_datetime_to_db_datetime( $event_time->end_time ) );
					if( $et_temp['end_time'] > time() ){
						$event_times[] = $et_temp;
					}					
				}
			}
		}

		$xt_event = array(
			'origin'          => 'facebook',
			'ID'              => $facebook_id,
			'name'            => $post_title,
			'description'     => $post_description,
			'starttime_local' => $start_time,
			'endtime_local'   => $end_time,
			'startime_utc'    => '',
			'endtime_utc'     => '',
			'timezone'        => $timezone,
			'utc_offset'      => '',
			'event_duration'  => '',
			'is_all_day'      => '',
			'url'             => $ticket_uri,
			'image_url'       => $cover_image,
		);

		if ( isset( $facebook_event->owner ) ) {
			$xt_event['organizer'] = $this->get_organizer( $facebook_event );
		}

		if ( isset( $facebook_event->place ) ) {
			$xt_event['location'] = $this->get_location( $facebook_event );
		}

		$xt_events = array();
		if( empty( $event_times ) ){
			$xt_events[] = $xt_event;
		}else{
			foreach ($event_times as $event_time ) {
				$xt_event['ID'] = $event_time['ID'];
				$xt_event['starttime_local'] = $event_time['start_time'];
				$xt_event['endtime_local'] = $event_time['end_time'];
				$xt_events[] = $xt_event;
			}
		}
		return $xt_events;
		
	}

	/**
	 * Get organizer args for event.
	 *
	 * @since    1.0.0
	 * @param array $eventbrite_event Eventbrite event.
	 * @return array
	 */
	public function get_organizer( $facebook_event ) {

		if ( !isset( $facebook_event->owner->id ) ) {
			return null;
		}

		$organizer_raw_data = $this->get_facebook_response_data(
			$facebook_event->owner->id,
			array(
				'fields' => implode(
					',',
					array(
						'id',
						'name',
						'link'
					)
				),
			)
		);

		if ( !isset( $organizer_raw_data->id ) ) {
			return null;
		}

		$event_organizer = array(
			'ID'          => isset( $organizer_raw_data->id ) ? $organizer_raw_data->id : '',
			'name'        => isset( $organizer_raw_data->name ) ? $organizer_raw_data->name : '',
			'description' => '',
			'email'       => '',
			'phone'       => isset( $organizer_raw_data->phone ) ? $organizer_raw_data->phone : '',
			'url'         => isset( $organizer_raw_data->link ) ? $organizer_raw_data->link : '',
			'image_url'   => '',
			/*'image_url'   => $image_url,*/
		);
		return $event_organizer;

	}

	/**
	 * Get location args for event
	 *
	 * @since    1.0.0
	 * @param array $eventbrite_event Eventbrite event.
	 * @return array
	 */
	public function get_location( $facebook_event ) {

		if ( !isset( $facebook_event->place->id ) ) {
			return null;
		}
		$event_venue = $facebook_event->place;
		$event_location = array(
			'ID'           => isset( $facebook_event->place->id ) ? $facebook_event->place->id : '',
			'name'         => isset( $event_venue->name ) ? $event_venue->name : '',
			'description'  => '',
			'address_1'    => isset( $event_venue->location->street ) ? $event_venue->location->street : '',
			'address_2'    => '',
			'city'         => isset( $event_venue->location->city ) ? $event_venue->location->city : '',
			'state'        => isset( $event_venue->location->state ) ? $event_venue->location->state : '',
			'country'      => isset( $event_venue->location->country ) ? $event_venue->location->country : '',
			'zip'	       => isset( $event_venue->location->zip ) ? $event_venue->location->zip : '',
			'lat'     	   => isset( $event_venue->location->latitude ) ? $event_venue->location->latitude : '',
			'long'		   => isset( $event_venue->location->longitude ) ? $event_venue->location->longitude : '',
			'full_address' => isset( $event_venue->location->street ) ? $event_venue->location->street : '',
			'url'          => '',
			'image_url'    => ''
		);
		return $event_location;
	}

	/**
	 * Get organizer Name based on Organiser ID.
	 *
	 * @since    1.0.0
	 * @param array $organizer_id Organizer event.
	 * @return array
	 */
	public function get_organizer_name_by_id( $organizer_id, $full_data = false ) {
		global $ife_errors;
		if( !$organizer_id || $organizer_id == '' ){
			return;
		}

		$organizer_raw_data = $this->get_facebook_response_data( $organizer_id, array() );
		if( isset( $organizer_raw_data->error->message ) ){
			return false;
			// $ife_errors[] = $organizer_raw_data->error->message;
		}
		
		if( ! isset( $organizer_raw_data->name ) ){
			return false;
		}

		if( $full_data ){
			return $organizer_raw_data;
		}
		
		$oraganizer_name = isset( $organizer_raw_data->name ) ? $organizer_raw_data->name : '';
		return $oraganizer_name;

	}

	/**
	 * Get UTC offset
	 *
	 * @since    1.0.0
	 */
	public function get_utc_offset( $datetime ) {
		try {
			$datetime = new DateTime( $datetime );
		} catch ( Exception $e ) {
			return '';
		}

		$timezone = $datetime->getTimezone();
		$offset   = $timezone->getOffset( $datetime ) / 60 / 60;

		if ( $offset >= 0 ) {
			$offset = '+' . $offset;
		}

		return 'UTC' . $offset;
	}
}
