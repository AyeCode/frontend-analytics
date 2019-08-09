<?php
/**
 * Frontend Analytics Settings
 *
 * @author   AyeCode
 * @category Admin
 * @package  frontend-analytics
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Frontend_Analytics_Settings', false ) ) :

	/**
	 * Outputs the settings page.
	 */
	class Frontend_Analytics_Settings {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id    = 'analytics';
			$this->label = __( 'Google Analytics', 'geodir-ga' );

			add_filter( 'geodir_settings_tabs_array', array( $this, 'add_settings_page' ), 50 );
			add_action( 'geodir_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'geodir_sections_' . $this->id, array( $this, 'output_toggle_advanced' ) );

			add_action( 'geodir_settings_save_' . $this->id, array( $this, 'save' ) );
			add_action( 'geodir_sections_' . $this->id, array( $this, 'output_sections' ) );
		}

		/**
		 * Get sections.
		 *
		 * @return array
		 */
		public function get_sections() {
			$sections = array();

			return apply_filters( 'geodir_get_sections_' . $this->id, $sections );
		}

		/**
		 * Output the settings.
		 */
		public function output() {
			global $current_section;

			$settings = $this->get_settings( $current_section );

			GeoDir_Admin_Settings::output_fields( $settings );
		}

		/**
		 * Save settings.
		 */
		public function save() {
			global $current_section;

			$settings = $this->get_settings( $current_section );

			GeoDir_Admin_Settings::save_fields( $settings );
		}

		/**
		 * Get settings array.
		 *
		 * @return array
		 */
		public function get_settings( $current_section = '' ) {
			$settings = apply_filters( 'geodir_google_analytics_settings', 
				array(
					array( 
						'name' => __( 'Google Analytics', 'geodir-ga' ), 
						'type' => 'title', 
						'desc' => '', 
						'id' => 'google_analytic_settings' 
					),
					array(
						'name' => __( 'Google Analytic Settings', 'geodir-ga' ),
						'type' => 'sectionstart', 
						'id' => 'google_analytic_settings'
					),
					array(
						'name' => __( 'Enable output widget?', 'geodir-ga' ),
						'desc' => __( 'This will enable the output of the analytics output widget.', 'geodir-ga' ),
						'id' => 'ga_stats',
						'std' => '0',
						'type' => 'checkbox',
					),
					array(
						'name' => __( 'Google analytics access', 'geodir-ga' ),
						'desc' => '',
						'id' => 'ga_token',
						'type' => 'google_analytics',
						'css' => 'min-width:300px;',
						'std' => ''
					),
					array(
						'name' => __( 'Google analytics Auth Code', 'geodir-ga' ),
						'desc' => __( 'You must save this setting before accounts will show.', 'geodir-ga' ),
						'id' => 'ga_auth_code',
						'type' => 'text',
						'css' => 'min-width:300px;',
						'std' => ''
					),
					array(
						'name' => __( 'Analytics Account', 'geodir-ga' ),
						'desc' => __( 'Select the account that you setup for this site.', 'geodir-ga' ),
						'id' => 'ga_account_id',
						'css' => 'min-width:300px;',
						'std' => 'gridview_onehalf',
						'type' => 'select',
						'class' => 'geodir-select',
						'options' => self::analytics_accounts()
					),
					array(
						'name' => __( 'Add tracking code to site?', 'geodir-ga' ),
						'desc' => __( 'This will automatically add the correct tracking code to your site', 'geodir-ga' ),
						'id' => 'ga_add_tracking_code',
						'std' => '0',
						'type' => 'checkbox',
					),
					array(
						'name' => __( 'Anonymize user IP?', 'geodir-ga' ),
						'desc' => __( 'In most cases this is not required, this is to comply with certain country laws such as Germany.', 'geodir-ga' ),
						'id' => 'ga_anonymize_ip',
						'type' => 'checkbox',
						'std' => '0',
						'advanced' => true
					),
					array(
						'name' => __( 'Auto refresh active users?', 'geodir-ga' ),
						'desc' => __( 'If ticked it uses the auto refresh time below, if not it never refreshes unless the refresh button is clicked.', 'geodir-ga' ),
						'id' => 'ga_auto_refresh',
						'type' => 'checkbox',
						'std' => '0',
						'advanced' => true
					),
					array(
						'name' => __( 'Time interval for auto refresh active users', 'geodir-ga' ),
						'desc' => __( 'Time interval in seconds to auto refresh active users. The active users will be auto refreshed after this time interval. Leave blank or use 0(zero) to disable auto refresh. Default: 5', 'geodir-ga' ),
						'id' => 'ga_refresh_time',
						'type' => 'text',
						'std' => '5',
						'class' => 'gd-advanced-setting',
						'advanced' => true
					),
					array(
						'type' => 'sectionend', 
						'id' => 'google_analytic_settings'
					),
				)
			);

			return apply_filters( 'geodir_get_settings_' . $this->id, $settings, $current_section );
		}

		public static function activation_url(){
			return add_query_arg( 
				array(
					'next'          => admin_url( 'admin.php?page=geodirectory&active_tab=google_analytic_settings' ),
					'scope'         => GEODIR_GA_SCOPE,
					'response_type' => 'code',
					'redirect_uri'  => GEODIR_GA_REDIRECT,
					'client_id'     => GEODIR_GA_CLIENTID,
				), 
				'https://accounts.google.com/o/oauth2/auth' 
			);
		}


		public static function analytics_accounts(){
			$ga_account_id = geodir_get_option( 'ga_account_id' );
			$ga_auth_code = geodir_get_option( 'ga_auth_code' );
			
			$accounts = array();
			$useAuth = $ga_auth_code == '' ? false : true;
			if ( $useAuth ) {
				try {
					$accounts = self::get_analytics_accounts();
				} catch (Exception $e) {
					geodir_error_log( wp_sprintf( __( 'GD Google Analytics API Error(%s) : %s', 'geodir-ga' ), $e->getCode(), $e->getMessage() ) );
				}

				if ( is_array( $accounts ) ) {
					$accounts = array_merge( array( __( 'Select Account','geodir-ga' ) ), $accounts );
				} elseif ( $ga_auth_code ) {
					$accounts = array();
					$accounts[ $ga_auth_code ] = __( 'Account re-authorization may be required', 'geodir-ga' ).' (' . $ga_account_id . ')';
				} else {
					$accounts = array();
				}
			}
			return $accounts;
		}

		public static function get_analytics_accounts() {
			global $gd_ga_errors;
			if ( empty( $gd_ga_errors ) ) {
				$gd_ga_errors = array();
			}
			$accounts = array();

			if ( geodir_get_option( 'ga_auth_token' ) === false ) {
				geodir_update_option( 'ga_auth_token', '' );
			}

			if ( geodir_get_option( 'ga_uids' ) && ! isset( $_POST['ga_auth_code'] ) ) {
				return geodir_get_option( 'ga_uids' );
			}

			# Create a new Gdata call
			if ( trim( geodir_get_option( 'ga_auth_code' ) ) != '' )
				$stats = new GeoDir_Google_Analytics_API();
			else
				return false;

			# Check if Google sucessfully logged in
			if ( ! $stats->checkLogin() )
				return false;

			# Get a list of accounts
			try {
				$accounts = $stats->getAllProfiles();
			} catch ( Exception $e ) {
				$gd_ga_errors[] = $e->getMessage();
				return false;
			}

			natcasesort ( $accounts );

			# Return the account array if there are accounts
			if ( count( $accounts ) > 0 ) {
				geodir_update_option( 'ga_uids', $accounts );
				return $accounts;
			}
			else
				return false;
		}
	}

endif;

return new GeoDir_Settings_Analytics();
