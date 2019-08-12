<?php
/**
 * Frontend Google Analytics Uninstall
 *
 * Uninstalling Frontend Analytics deletes analytics options.
 *
 * @author      AyeCode Ltd
 * @package     frontend-analytics
 * @version     1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete core options
delete_option( 'frontend-analytics-db-version' );
delete_option( 'frontend-analytics-settings' );

// Clear any cached data that has been removed.
wp_cache_flush();