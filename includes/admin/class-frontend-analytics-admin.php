<?php
/**
 * Frontend Analytics Admin.
 *
 * @since 1.0.0
 * @package Frontend_Analytics_Admin
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend_Analytics_Admin class.
 */
class Frontend_Analytics_Admin {

	static $default_options = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_redirects' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		Frontend_Analytics_Admin::$default_options = wp_list_pluck( Frontend_Analytics_Settings::get_settings(), 'std', 'id');
		add_filter( 'geodir_pricing_package_settings', array( $this, 'pricing_package_settings' ), 10, 3 );
		add_action( 'geodir_pricing_process_data_for_save', array( $this, 'pricing_process_data_for_save' ), 1, 3 );

		// Save authenticate code.
		if ( ! empty( $_GET['ga_auth_code'] ) && ! empty( $_GET['page'] ) && $_GET['page'] === 'frontend-analytics' ) {
			$this->save_auth_code();
		}
	}

	/**
	 * admin menu.
	 */
	public function admin_menu() {
		add_options_page( 
			'Frontend Analytics',
			'Frontend Analytics',
			'manage_options',
			'frontend-analytics',
			array( $this, 'render_settings_page' ) 
		);
	}

	/**
	 * renders settings page
	 */
	public function render_settings_page() {
		Frontend_Analytics_Settings::output();
	}

	/**
	 * Handle redirects to setup/welcome page after install and updates.
	 *
	 * For setup wizard, transient must be present, the user must have access rights, and we must ignore the network/bulk plugin updates.
	 */
	public function admin_redirects() {

		// Nonced plugin install redirects (whitelisted)
		if ( ! empty( $_GET['frontend-analytics-install-redirect'] ) ) {
			$plugin_slug = sanitize_text_field( $_GET['frontend-analytics-install-redirect'] );

			$url = admin_url( 'plugin-install.php?tab=search&type=term&s=' . $plugin_slug );

			wp_safe_redirect( $url );
			exit;
		}

		$redirected = get_option( 'frontend_analytics_redirected', 0 );

		if( $redirected ) {
			return;
		}

		// Bail if activating from network, or bulk
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || apply_filters( 'frontend_analytics_prevent_activation_redirect', false ) ) {
			return;
		}

		update_option( 'frontend_analytics_redirected', 1 );

		wp_safe_redirect( admin_url( 'admin.php?page=frontend-analytics' ) );
		exit;
	}

	public function pricing_package_settings( $settings, $package_data ) {
		$new_settings = array();

		foreach ( $settings as $key => $setting ) {
			if ( ! empty( $setting['id'] ) && $setting['id'] == 'package_features_settings' && ! empty( $setting['type'] ) && $setting['type'] == 'sectionend' ) {
				$new_settings[] = array(
					'type' => 'checkbox',
					'id' => 'package_google_analytics',
					'title'=> __( 'Google Analytics', 'frontend-analytics' ),
					'desc' => __( 'Tick to enable google analytics.', 'frontend-analytics' ),
					'std' => '0',
					'advanced' => true,
					'value'	=> ( ! empty( $package_data['google_analytics'] ) ? '1' : '0' )
				);
			}
			$new_settings[] = $setting;
		}

		return $new_settings;
	}

	public function pricing_process_data_for_save( $package_data, $data, $package ) {
		if ( isset( $data['google_analytics'] ) ) {
			$package_data['meta']['google_analytics'] = ! empty( $data['google_analytics'] ) ? 1 : 0;
		} else if ( isset( $package['google_analytics'] ) ) {
			$package_data['meta']['google_analytics'] = $package['google_analytics'];
		} else {
			$package_data['meta']['google_analytics'] = 0;
		}

		return $package_data;
	}

	public function save_auth_code() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$auth_code = sanitize_text_field( $_GET['ga_auth_code'] );

		frontend_analytics_update_option( 'auth_code', $auth_code );
		frontend_analytics_update_option( 'auth_token', '' );
		frontend_analytics_update_option( 'auth_date', '' );

		wp_safe_redirect( admin_url( 'admin.php?page=frontend-analytics' ) );
	}
}