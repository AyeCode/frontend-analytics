<?php
/**
 * This is the main plugin class
 *
 * @package    frontend-analytics
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * class Frontend_Analytics
 * 
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
class Frontend_Analytics {
     /**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 */
    private static $instance = null;

	/**
	 * Plugin settings
	 *
	 * @var array
	 */    
    protected $options;

    /**
	 * Frontend_Analytics Main Instance.
	 *
	 * Ensures only one instance of Frontend_Analytics is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return Frontend_Analytics - Main instance.
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Frontend_Analytics ) ) {

            self::$instance = new Frontend_Analytics;
            self::$instance->setup_constants();

            //Load text domain
            self::$instance->load_textdomain();

            self::$instance->includes();
            self::$instance->init_hooks();

            do_action( 'frontend_analytics_loaded' );
        }
 
        return self::$instance;
	}

	/**
     * Setup plugin constants.
     *
     * @access private
     * @since 1.0.0
     * @return void
     */
    private function setup_constants() {

        if ( $this->is_request( 'test' ) ) {
            $plugin_path = dirname( FRONTEND_ANALYTICS_PLUGIN_FILE );
        } else {
            $plugin_path = plugin_dir_path( FRONTEND_ANALYTICS_PLUGIN_FILE );
        }

        $this->define( 'FRONTEND_ANALYTICS_PLUGIN_DIR', $plugin_path );
        $this->define( 'FRONTEND_ANALYTICS_PLUGIN_URL', untrailingslashit( plugins_url( '/', FRONTEND_ANALYTICS_PLUGIN_FILE ) ) );
        $this->define( 'FRONTEND_ANALYTICS_PLUGIN_BASENAME', plugin_basename( FRONTEND_ANALYTICS_PLUGIN_FILE ) );
		
		// Google Analytic app settings
		$this->define( 'FRONTEND_ANALYTICS_CLIENTID', '687912069872-sdpsjssrdt7t3ao1dnv1ib71hkckbt5s.apps.googleusercontent.com' );
		$this->define( 'FRONTEND_ANALYTICS_CLIENTSECRET', 'yBVkDpqJ1B9nAETHy738Zn8C' ); // don't worry - this don't need to be secret in our case
		$this->define( 'FRONTEND_ANALYTICS_REDIRECT', 'urn:ietf:wg:oauth:2.0:oob' );
		$this->define( 'FRONTEND_ANALYTICS_SCOPE', 'https://www.googleapis.com/auth/analytics' ); // .readonly

    }

    /**
     * Include required files.
     *
     * @access private
     * @since 1.0.0
     * @return void
     */
    private function includes() {
        global $wp_version;

        //Load composer packages
        require_once( FRONTEND_ANALYTICS_PLUGIN_DIR . 'vendor/autoload.php' );
        
        //Class autoloader
        include_once( FRONTEND_ANALYTICS_PLUGIN_DIR . 'includes/class-frontend-analytics-autoloader.php' );
 
        //Init our ajax handler
        Frontend_Analytics_AJAX::init();

        //And settings
        new Frontend_Analytics_Settings();
 
        require_once( FRONTEND_ANALYTICS_PLUGIN_DIR . 'includes/functions.php' );

        if ( $this->is_request( 'admin' ) || $this->is_request( 'test' ) || $this->is_request( 'cli' ) ) {
            new Frontend_Analytics_Admin();
        }
    }
    
    /**
     * Hook into actions and filters.
     * @since  1.0.0
     */
    private function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );

		if ( $this->is_request( 'frontend' ) ) {
			add_action( 'wp_head', 'frontend_analytics_add_tracking_code' );
		}
 
		add_action( 'widgets_init', array( $this, 'register_widgets' ), 11 );
    }
    
    /**
     * Initialise plugin when WordPress Initialises.
     */
    public function init() {
        // Before init action.
        do_action( 'frontend_analytics_before_init' );

        // Init action.
        do_action( 'frontend_analytics_init' );
    }
    
    /**
     * Loads the plugin language files
     *
     * @access public
     * @since 1.0.0
     * @return void
     */
    public function load_textdomain() {
        global $wp_version;
        
        $locale = $wp_version >= 4.7 ? get_user_locale() : get_locale();
        
        /**
         * Filter the plugin locale.
         *
         * @since   1.0.0
         */
        $locale = apply_filters( 'plugin_locale', $locale, 'frontend-analytics' );

        load_textdomain( 'frontend-analytics', WP_LANG_DIR . '/' . 'frontend-analytics' . '/' . 'frontend-analytics' . '-' . $locale . '.mo' );
        load_plugin_textdomain( 'frontend-analytics', FALSE, basename( dirname( FRONTEND_ANALYTICS_PLUGIN_FILE ) ) . '/languages/' );
    }

	/**
     * Define constant if not already set.
     *
     * @param  string $name
     * @param  string|bool $value
     */
    private function define( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }
    
    /**
     * Request type.
     *
     * @param  string $type admin, frontend, ajax, cron, test or CLI.
     * @return bool
     */
    private function is_request( $type ) {
        switch ( $type ) {
            case 'admin' :
                return is_admin();
                break;
            case 'ajax' :
                return wp_doing_ajax();
                break;
            case 'cli' :
                return ( defined( 'WP_CLI' ) && WP_CLI );
                break;
            case 'cron' :
                return wp_doing_cron();
                break;
            case 'frontend' :
                return ( ! is_admin() || wp_doing_ajax() ) && ! wp_doing_cron();
                break;
            case 'test' :
                return defined( 'FRONTEND_ANALYTICS_TESTING_MODE' );
                break;
        }
        
        return null;
    }
    
    
    public function get_options() {

        if ( !isset( $this->options ) ) {
            $this->options = get_option( 'frontend-analytics-settings', array() );
        }

        return $this->options;
    }
    
    public function update_options( array $options ) {

        if ( $options === array() ) {
            return;
        }

        $this->options = $options;
        
        update_option( 'frontend-analytics-settings', $options );
    }

    public function register_widgets() {
        register_widget( 'Frontend_Analytics_Widget_Analytics' );
    }

}
