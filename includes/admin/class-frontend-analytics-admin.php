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

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_redirects' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
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
	 * For setup wizard, transient must be present, the user must have access rights, and we must ignore the network/bulk plugin updaters.
	 */
	public function admin_redirects() {

		// Nonced plugin install redirects (whitelisted)
		if ( ! empty( $_GET['frontend-analytics-install-redirect'] ) ) {
			$plugin_slug = sanitize_text_field( $_GET['frontend-analytics-install-redirect'] );

			$url = admin_url( 'plugin-install.php?tab=search&type=term&s=' . $plugin_slug );

			wp_safe_redirect( $url );
			exit;
		}

		// Activation redirect
		if ( ! get_transient( '_frontend_analytics_activation_redirect' ) ) {
			return;
		}
	
		delete_transient( '_frontend_analytics_activation_redirect' );

		// Bail if activating from network, or bulk
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || apply_filters( 'frontend_analytics_prevent_activation_redirect', false ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=frontend-analytics' ) );
		exit;
	}

}