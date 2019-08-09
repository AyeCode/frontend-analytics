<?php
/**
 * Google Analytics Admin.
 *
 * @since 2.0.0
 * @package GeoDir_Google_Analytics
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GeoDir_Google_Analytics_Admin class.
 */
class GeoDir_Google_Analytics_Admin {

    /**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_redirects' ) );
		add_filter( 'geodir_get_settings_pages', array( $this, 'load_settings_page' ), 50, 1 );
		add_action( 'geodir_admin_field_google_analytics', 'geodir_ga_google_analytics_field', 10, 1 );
		add_action( 'geodir_clear_version_numbers' ,array( $this, 'clear_version_number' ) );
		add_filter( 'geodir_uninstall_options', 'geodir_ga_uninstall_settings', 50, 1 );
		add_action( 'geodir_pricing_package_settings', array( $this, 'pricing_package_settings' ), 10, 3 );
		add_action( 'geodir_pricing_process_data_for_save', array( $this, 'pricing_process_data_for_save' ), 1, 3 );
    }

	/**
	 * Handle redirects to setup/welcome page after install and updates.
	 *
	 * For setup wizard, transient must be present, the user must have access rights, and we must ignore the network/bulk plugin updaters.
	 */
	public function admin_redirects() {
		// Nonced plugin install redirects (whitelisted)
		if ( ! empty( $_GET['geodir-ga-install-redirect'] ) ) {
			$plugin_slug = geodir_clean( $_GET['geodir-ga-install-redirect'] );

			$url = admin_url( 'plugin-install.php?tab=search&type=term&s=' . $plugin_slug );

			wp_safe_redirect( $url );
			exit;
		}

		// Activation redirect
		if ( ! get_transient( '_geodir_ga_activation_redirect' ) ) {
			return;
		}
	
		delete_transient( '_geodir_ga_activation_redirect' );

		// Bail if activating from network, or bulk
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || apply_filters( 'geodir_google_analytics_prevent_activation_redirect', false ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=gd-settings&tab=ga' ) );
		exit;
	}

	public static function load_settings_page( $settings_pages ) {
		$post_type = ! empty( $_REQUEST['post_type'] ) ? sanitize_text_field( $_REQUEST['post_type'] ) : 'gd_place';

		if ( ! ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] == $post_type . '-settings' ) ) {
			$settings_pages[] = include( GEODIR_GA_PLUGIN_DIR . 'includes/admin/settings/class-geodir-settings-analytics.php' );
		}

		return $settings_pages;
	}

	/**
	 * Deletes the version number from the DB so install functions will run again.
	 */
	public function clear_version_number(){
		delete_option( 'geodir_ga_version' );
	}

	public function pricing_package_settings( $settings, $package_data ) {
		$new_settings = array();

		foreach ( $settings as $key => $setting ) {
			if ( ! empty( $setting['id'] ) && $setting['id'] == 'package_features_settings' && ! empty( $setting['type'] ) && $setting['type'] == 'sectionend' ) {
				$new_settings[] = array(
					'type' => 'checkbox',
					'id' => 'package_google_analytics',
					'title'=> __( 'Google Analytics', 'geodir-ga' ),
					'desc' => __( 'Tick to enable google analytics.', 'geodir-ga' ),
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
}