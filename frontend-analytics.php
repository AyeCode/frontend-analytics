<?php
/**
 * This is the main plugin file, here we declare and call the important stuff
 *
 * @package     frontend-analytics
 * @copyright   2019 AyeCode Ltd
 * @license     GPLv3
 * @since       1.0.0
 *
 * @frontend-analytics
 * Plugin Name: Frontend Analytics
 * Plugin URI: https://wpgeodirectory.com/downloads/frontend-analytics/
 * Description: View each page's Google Analytics starts on the front-page
 * Version: 2.0.0
 * Author: AyeCode Ltd
 * Author URI: https://wpgeodirectory.com/
 * Requires at least: 4.9
 * Tested up to: 5.5
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: frontend-analytics
 * Domain Path: /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! defined( 'FRONTEND_ANALYTICS_VERSION' ) ) {
	define( 'FRONTEND_ANALYTICS_VERSION', '2.0.0' );
}

if ( ! defined( 'FRONTEND_ANALYTICS_PLUGIN_FILE' ) ) {
	define( 'FRONTEND_ANALYTICS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'FRONTEND_ANALYTICS_PLUGIN_URL' ) ) {
	define( 'FRONTEND_ANALYTICS_PLUGIN_URL',untrailingslashit( plugins_url( '/', __FILE__ ) ) );
}

// Load the main plugin class
require_once ( plugin_dir_path( FRONTEND_ANALYTICS_PLUGIN_FILE ) . 'includes/class-frontend-analytics.php' );

/**
 * Returns an instance of the main plugin file
 * @since  1.0.0
 */
function frontend_analytics() {
	return Frontend_Analytics::instance();
}
	
//Kickstart plugin execution as soon as all plugins are loaded
add_action( 'plugins_loaded', 'frontend_analytics' );