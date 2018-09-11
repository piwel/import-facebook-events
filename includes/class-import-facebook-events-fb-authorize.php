<?php
/**
 * class for Facebook User Authorization
 *
 * @link       http://xylusthemes.com/
 * @since      1.0.0
 *
 * @package    Import_Facebook_Events
 * @subpackage Import_Facebook_Events/includes
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Import_Facebook_Events_FB_Authorize {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'admin_post_ife_facebook_authorize_action', array( $this, 'ife_facebook_authorize_user' ) );
		add_action( 'admin_post_ife_facebook_authorize_callback', array( $this, 'ife_facebook_authorize_user_callback' ) );
	}

	/*
	* Authorize facebook user to get access token
	*/
    function ife_facebook_authorize_user() {
		if ( ! empty($_POST) && wp_verify_nonce($_POST['ife_facebook_authorize_nonce'], 'ife_facebook_authorize_action' ) ) {

			$ife_options = get_option( IFE_OPTIONS , array() );
			$app_id = isset( $ife_options['facebook_app_id'] ) ? $ife_options['facebook_app_id'] : '';
			$app_secret = isset( $ife_options['facebook_app_secret'] ) ? $ife_options['facebook_app_secret'] : '';
			$redirect_url = admin_url( 'admin-post.php?action=ife_facebook_authorize_callback' );
			$api_version = 'v3.0';
			$param_url = urlencode($redirect_url);
			$ife_session_state = md5(uniqid(rand(), TRUE));
			setcookie("ife_session_state", $ife_session_state, "0", "/");

			if( $app_id != '' && $app_secret != '' ){

				$dialog_url = "https://www.facebook.com/" . $api_version . "/dialog/oauth?client_id="
				        . $app_id . "&redirect_uri=" . $param_url . "&state="
				        . $ife_session_state . "&scope=groups_access_member_info,user_events";
				header("Location: " . $dialog_url);

			}else{
				die( __( 'Please insert Facebook App ID and Secret.', 'import-facebook-events-pro' ) );
			}

        } else {
            die( __('You have not access to doing this operations.', 'import-facebook-events-pro' ) );
        }
    }

    /*
	* Authorize facebook user on callback to get access token
	*/
    function ife_facebook_authorize_user_callback() {
		global $ife_success_msg;
		if ( isset( $_COOKIE['ife_session_state'] ) && isset($_REQUEST['state']) && ( $_COOKIE['ife_session_state'] === $_REQUEST['state'] ) ) {

				$code = sanitize_text_field($_GET['code']);
				$ife_options = get_option( IFE_OPTIONS , array() );
				$app_id = isset( $ife_options['facebook_app_id'] ) ? $ife_options['facebook_app_id'] : '';
				$app_secret = isset( $ife_options['facebook_app_secret'] ) ? $ife_options['facebook_app_secret'] : '';
				$redirect_url = admin_url('admin-post.php?action=ife_facebook_authorize_callback');
				$api_version = 'v3.0';
				$param_url = urlencode($redirect_url);

				if( $app_id != '' && $app_secret != '' ){

					$token_url = "https://graph.facebook.com/" . $api_version . "/oauth/access_token?"
        . "client_id=" . $app_id . "&redirect_uri=" . $param_url
        . "&client_secret=" . $app_secret . "&code=" . $code;

					$access_token = "";
					$ife_user_token_options = $ife_fb_authorize_user = array();
					$response = wp_remote_get( $token_url );
					$body = wp_remote_retrieve_body( $response );
					$body_response = json_decode( $body );
					if ($body != '' && isset( $body_response->access_token ) ) {

						$access_token = $body_response->access_token;
					    $ife_user_token_options['authorize_status'] = 1;
					    $ife_user_token_options['access_token'] = sanitize_text_field($access_token);
					    update_option('ife_user_token_options', $ife_user_token_options);

						$accounts_call= wp_remote_get("https://graph.facebook.com/".$api_version."/me/accounts?access_token=$access_token&limit=100&offset=0");

						$profile_call= wp_remote_get("https://graph.facebook.com/".$api_version."/me?fields=id,name,picture&access_token=$access_token");
						$profile = wp_remote_retrieve_body( $profile_call );
						$profile = json_decode( $profile );
						if( isset( $profile->id ) && isset( $profile->name ) ){
							$ife_fb_authorize_user['ID'] = sanitize_text_field( $profile->id );
							$ife_fb_authorize_user['name'] = sanitize_text_field( $profile->name );
							if( isset( $profile->picture->data->url ) ){
								$ife_fb_authorize_user['avtar'] = esc_url_raw( $profile->picture->data->url );	
							}
						}

						update_option('ife_fb_authorize_user', $ife_fb_authorize_user );
						$redirect_url = admin_url('admin.php?page=facebook_import&tab=settings&authorize=1');
					    wp_redirect($redirect_url);
					    exit();
					}else{
						$redirect_url = admin_url('admin.php?page=facebook_import&tab=settings&authorize=0');
					    wp_redirect($redirect_url);
					    exit();
					}
				} else {
					$redirect_url = admin_url('admin.php?page=facebook_import&tab=settings&authorize=2');
					wp_redirect($redirect_url);
					exit();
					die( __( 'Please insert Facebook App ID and Secret.', 'import-facebook-events-pro' ) );
				}

            } else {
				die( __('You have not access to doing this operations.', 'import-facebook-events-pro' ) );
            }
    }
}