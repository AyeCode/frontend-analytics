<?php
/**
 * Frontend Analytics AJAX class.
 *
 * Frontend Analytics AJAX Event Handler.
 *
 * @since 1.0.0
 * @package frontend-analytics
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend_Analytics_AJAX class.
 */
class Frontend_Analytics_AJAX {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		
		$ajax_events = array(
			'stats' 		 => true,
			'deauthorize' => false,
			'callback'    => false,
		);

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_frontend_analytics_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_frontend_analytics_' . $ajax_event, array( __CLASS__, $ajax_event ) );

				// Frontend analytics AJAX can be used for frontend ajax requests.
				add_action( 'frontend_analytics_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	public static function stats() {

		// some special security checks
		$ref = wp_get_referer();
		$req = isset($_REQUEST['ga_page']) ? urldecode($_REQUEST['ga_page']) : '';
		$page_token = isset($_REQUEST['pt']) ? $_REQUEST['pt'] : '';

        if (
	        $ref
	        && $req
	        && $page_token
	        && $ref !== wp_unslash( $_SERVER['REQUEST_URI'] )
	        && $ref !== home_url() . wp_unslash( $_SERVER['REQUEST_URI'] )
	        && untrailingslashit(home_url()) . $req == $ref
	        && frontend_analytics_validate_page_access_token($page_token,$req)
        ) {

	        if ( isset( $_REQUEST['ga_start'] ) ) {
		        $ga_start = $_REQUEST['ga_start'];
	        } else {
		        $ga_start = '';
	        }
	        if ( isset( $_REQUEST['ga_end'] ) ) {
		        $ga_end = $_REQUEST['ga_end'];
	        } else {
		        $ga_end = '';
	        }
	        try {
		        frontend_analytics_get_analytics( $_REQUEST['ga_page'], $ga_start, $ga_end );
	        } catch ( Exception $e ) {

	        }
        }


		exit;
	}

	/**
	 * Deauthorize Google Analytics
	 */
	public static function deauthorize(){
		// security
		check_ajax_referer( 'frontend_analytics_deauthorize', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}

		$fa = frontend_analytics();
		$options = $fa->get_options();

		$options['auth_token'] = '';
		$options['auth_code'] = '';
		$options['uids'] = '';
		$options['account_id'] = '';

		$fa->update_options( $options );

		echo admin_url( 'admin.php?page=frontend-analytics' );

		wp_die( -1 );
	}
	
	public static function callback(){
		if ( ! empty( $_REQUEST['code'] )) {
			$oAuthURL = "https://www.googleapis.com/oauth2/v3/token?";
			$code = "code=" . sanitize_text_field( $_REQUEST['code'] );
			$grant_type = "&grant_type=authorization_code";
			$redirect_uri = "&redirect_uri=" . admin_url( 'admin-ajax.php' ) . "?action=frontend_analytics_callback";
			$client_id = "&client_id=" . frontend_analytics_get_option( 'ga_client_id' );
			$client_secret = "&client_secret=" . frontend_analytics_get_option( 'ga_client_secret' );

			$auth_url = $oAuthURL . $code . $redirect_uri .  $grant_type . $client_id . $client_secret;

			$response = wp_remote_post( $auth_url, array( 'timeout' => 15 ) );

			$error_msg =  __('Something went wrong','frontend-analytics');
			if ( ! empty( $response['response']['code'] ) && $response['response']['code'] == 200 ) {
				$parts = json_decode( $response['body'] );
				if ( ! isset( $parts->access_token ) ) {
					echo $error_msg . " - #1";
					exit;
				} else {
					frontend_analytics_update_option( 'access_token', $parts->access_token );
					frontend_analytics_update_option( 'refresh_token', $parts->refresh_token );
					?><script>window.close();</script><?php
				}
			} elseif ( ! empty( $response['response']['code'] ) ) {
				$parts = json_decode( $response['body'] );

				if ( isset( $parts->error ) ) {
					echo $parts->error . ": " . $parts->error_description;
					exit;
				} else {
					echo $error_msg . " - #2";
					exit;
				}
			} else {
				echo $error_msg . " - #3";
				exit;
			}
		}
		die();
	}
}