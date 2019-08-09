<?php
/**
 * Installation related functions and actions.
 *
 * @since 2.0.0
 * @package GeoDir_Google_Analytics
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GeoDir_Google_Analytics_Admin_Install class.
 */
class GeoDir_Google_Analytics_Admin_Install {

	/** @var array DB updates and callbacks that need to be run per version */
	private static $db_updates = array(
		/*'2.0.0' => array(
			'geodir_update_200_file_paths',
			'geodir_update_200_permalinks',
		)*/
		/*'2.0.0.1-dev' => array(
			'geodir_update_2001_dev_db_version',
		),*/
	);

	private static $background_updater;

	/**
	 * Hook in tabs.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
		add_action( 'init', array( __CLASS__, 'init_background_updater' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'install_actions' ) );
	}

	/**
	 * Init background updates
	 */
	public static function init_background_updater() {
		if ( ! class_exists( 'GeoDir_Background_Updater' ) ) {
			include_once( GEODIRECTORY_PLUGIN_DIR . 'includes/class-geodir-background-updater.php' );
		}
		self::$background_updater = new GeoDir_Background_Updater();
	}

	/**
	 * Check plugin version and run the updater as required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) ) {
			if ( self::is_v2_upgrade() ) {
				// v2 upgrade
			} else if ( get_option( 'geodir_ga_version' ) !== GEODIR_GA_VERSION ) {
				self::install();
				do_action( 'geodir_google_analytics_updated' );
			}
		}
	}

	/**
	 * Install actions when a update button is clicked within the admin area.
	 *
	 * This function is hooked into admin_init to affect admin only.
	 */
	public static function install_actions() {
		if ( ! empty( $_GET['do_update_geodir_ga'] ) ) {
			self::update();
		}
		if ( ! empty( $_GET['force_update_geodir_ga'] ) ) {
			$blog_id = get_current_blog_id();
			do_action( 'geodir_ga_' . $blog_id . '_updater_cron' );
			wp_safe_redirect( admin_url( 'admin.php?page=gd-settings' ) );
			exit;
		}
	}

	/**
	 * Install plugin.
	 */
	public static function install() {
		global $wpdb;

		if ( ! is_blog_installed() ) {
			return;
		}

		if ( ! defined( 'GEODIR_GA_INSTALLING' ) ) {
			define( 'GEODIR_GA_INSTALLING', true );
		}

		// Default options
		self::save_default_options();

		// Update GD version
		self::update_gd_version();

		// Update DB version
		self::maybe_update_db_version();

		// Flush rules after install
		do_action( 'geodir_google_analytics_flush_rewrite_rules' );

		// Trigger action
		do_action( 'geodir_google_analytics_installed' );
	}
	
	/**
	 * Is this a brand new install?
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	private static function is_new_install() {
		return is_null( get_option( 'geodir_ga_version', null ) ) && is_null( get_option( 'geodir_ga_db_version', null ) );
	}

	/**
	 * Is a DB update needed?
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	private static function needs_db_update() {
		$current_db_version = get_option( 'geodir_ga_db_version', null );
		$updates            = self::get_db_update_callbacks();

		return ! is_null( $current_db_version ) && ! empty( $updates ) && version_compare( $current_db_version, max( array_keys( $updates ) ), '<' );
	}

	/**
	 * See if we need to show or run database updates during install.
	 *
	 * @since 2.0.0
	 */
	private static function maybe_update_db_version() {
		if ( self::needs_db_update() ) {
			self::update();
		} else {
			self::update_db_version();
		}
	}

	/**
	 * Update GeoDirectory version to current.
	 */
	private static function update_gd_version() {
		delete_option( 'geodir_ga_version' );
		add_option( 'geodir_ga_version', GEODIR_GA_VERSION );
	}

	/**
	 * Get list of DB update callbacks.
	 *
	 * @since  2.0.0
	 * @return array
	 */
	public static function get_db_update_callbacks() {
		return self::$db_updates;
	}

	/**
	 * Push all needed DB updates to the queue for processing.
	 */
	private static function update() {
		$current_db_version = get_option( 'geodir_ga_db_version' );
		$update_queued      = false;

		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					geodir_error_log( sprintf( 'Queuing %s - %s', $version, $update_callback ) );
					self::$background_updater->push_to_queue( $update_callback );
					$update_queued = true;
				}
			}
		}

		if ( $update_queued ) {
			self::$background_updater->save()->dispatch();
		}
	}

	/**
	 * Update DB version to current.
	 * @param string $version
	 */
	public static function update_db_version( $version = null ) {
		delete_option( 'geodir_ga_db_version' );
		add_option( 'geodir_ga_db_version', is_null( $version ) ? GEODIR_GA_VERSION : $version );
	}

	/**
	 * Default options.
	 *
	 * Sets up the default options used on the settings page.
	 */
	private static function save_default_options() {
		$current_settings = geodir_get_settings();

		$settings = GeoDir_Google_Analytics_Admin::load_settings_page( array() );

		if ( ! empty( $settings ) ) {
			foreach ( $settings as $section ) {
				if ( ! method_exists( $section, 'get_settings' ) ) {
					continue;
				}
				$subsections = array_unique( array_merge( array( '' ), array_keys( $section->get_sections() ) ) );

				foreach ( $subsections as $subsection ) {
					$options = $section->get_settings( $subsection );
					if ( empty( $options ) ) {
						continue;
					}

					foreach ( $options as $value ) {
						if ( ! isset( $current_settings[ $value['id'] ] ) && isset( $value['default'] ) && isset( $value['id'] ) ) {
							geodir_update_option($value['id'], $value['default']);
						}
					}
				}
			}
		}
	}

	/**
	 * Is v1 to v2 upgrade.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	private static function is_v2_upgrade() {
		if ( ( get_option( 'geodirectory_db_version' ) && version_compare( get_option( 'geodirectory_db_version' ), '2.0.0.0', '<' ) ) || ( get_option( 'geodir_ga_db_version' ) && version_compare( get_option( 'geodir_ga_db_version' ), '2.0.0.0', '<' ) ) ) {
			return true;
		}

		return false;
	}
}
