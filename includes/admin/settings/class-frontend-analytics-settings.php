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
		 * Inits hooks
		 */
		public function __construct() {
			add_action( 'frontend_analytics_render_select_settings_field', 'Frontend_Analytics_Settings::render_select', 10, 2 );
        	add_action( 'frontend_analytics_render_input_settings_field', 'Frontend_Analytics_Settings::render_input', 10, 2 );
        	add_action( 'frontend_analytics_render_checkbox_settings_field', 'Frontend_Analytics_Settings::render_checkbox', 10, 2 );
        	add_action( 'frontend_analytics_render_analytics_settings_field', 'Frontend_Analytics_Settings::render_analytics', 10, 2 );
		}

		/**
		 * Output the settings.
		 */
		public static function output() {
			
			//Maybe save the settings
			self::maybe_save_settings();

			//Render settings
			include( FRONTEND_ANALYTICS_PLUGIN_DIR . 'includes/admin/settings/template.php' );
		}

		/**
		 * Save settings.
		 */
		public static function maybe_save_settings() {

			//Maybe abort early
			if( empty( $_POST['_wpnonce'] ) || !wp_verify_nonce( $_POST['_wpnonce'] ) ) {
				return;
			}

			//Prepare the settings
			$registered_settings = self::get_settings();
			$posted_settings     = $_POST;
			unset( $posted_settings['_wpnonce'] );
			unset( $posted_settings['_wp_http_referer'] );
	
			//Sanitize the settings
			$options = self::sanitize_settings( $registered_settings, $posted_settings );

			//Then save them
			$fa 		 = frontend_analytics();
			$old_options = $fa->get_options();
			$options	 = array_replace( $old_options, $options);
			$fa->update_options( $options );
		}

		/**
    	 * Sanitizes settings fields
    	 */
    	public static function sanitize_settings( $registered_settings, $posted_settings ) {

        	foreach( $registered_settings as $id=>$args ) {

            	//Deal with checkboxes(unchecked ones are never posted)
            	if( 'checkbox' == $args['el'] ) {
                	$posted_settings[$id] = isset( $posted_settings[$id] ) ? '1' : '0';
            	}
        	}
        	return apply_filters( 'frontend_analytics_sanitize_settings', $posted_settings );
    	}

		/**
		 * Get settings array.
		 *
		 * @return array
		 */
		public static function get_settings() {
			$settings = apply_filters( 'frontend_analytics_settings', 
				array(
					array(
						'name' => __( 'Google analytics access', 'frontend-analytics' ),
						'desc' => '',
						'id' => 'token',
						'el' => 'analytics',
						'css' => 'min-width:300px;',
						'std' => ''
					),
					array(
						'name' => __( 'Google analytics Auth Code', 'frontend-analytics' ),
						'desc' => __( 'You must save this setting before accounts will show.', 'frontend-analytics' ),
						'id' => 'auth_code',
						'el' => 'input',
						'css' => 'min-width:300px;',
						'std' => ''
					),
					array(
						'name' => __( 'Analytics Account', 'frontend-analytics' ),
						'desc' => __( 'Select the account that you setup for this site.', 'frontend-analytics' ),
						'id' => 'account_id',
						'css' => 'min-width:300px;',
						'std' => 'gridview_onehalf',
						'el' => 'select',
						'options' => self::analytics_accounts()
					),
					array(
						'name' => __( 'Add tracking code to site?', 'frontend-analytics' ),
						'desc' => __( 'This will automatically add the correct tracking code to your site', 'frontend-analytics' ),
						'id' => 'add_tracking_code',
						'std' => '0',
						'el' => 'checkbox',
					),
					array(
						'name' => __( 'Anonymize user IP?', 'frontend-analytics' ),
						'desc' => __( 'In most cases this is not required, this is to comply with certain country laws such as Germany.', 'frontend-analytics' ),
						'id' => 'anonymize_ip',
						'el' => 'checkbox',
						'std' => '0',
						'advanced' => true
					),
					array(
						'name' => __( 'Auto refresh active users?', 'frontend-analytics' ),
						'desc' => __( 'If ticked it uses the auto refresh time below, if not it never refreshes unless the refresh button is clicked.', 'frontend-analytics' ),
						'id' => 'auto_refresh',
						'el' => 'checkbox',
						'std' => '0',
						'advanced' => true
					),
					array(
						'name' => __( 'Time interval for auto refresh active users', 'frontend-analytics' ),
						'desc' => __( 'Time interval in seconds to auto refresh active users. The active users will be auto refreshed after this time interval. Leave blank or use 0(zero) to disable auto refresh. Default: 5', 'frontend-analytics' ),
						'id' => 'refresh_time',
						'el' => 'input',
						'std' => '5',
						'class' => 'gd-advanced-setting',
						'advanced' => true
					),
				)
			);

			return apply_filters( 'frontend_analytics_settings', $settings );
		}

		public static function activation_url(){
			return add_query_arg( 
				array(
					'next'          => admin_url( 'admin.php?page=frontend-analytics' ),
					'scope'         => FRONTEND_ANALYTICS_SCOPE,
					'response_type' => 'code',
					'redirect_uri'  => FRONTEND_ANALYTICS_REDIRECT,
					'client_id'     => FRONTEND_ANALYTICS_CLIENTID,
				), 
				'https://accounts.google.com/o/oauth2/auth' 
			);
		}


		public static function analytics_accounts(){
			$ga_account_id = frontend_analytics_get_option( 'account_id' );
			$ga_auth_code = frontend_analytics_get_option( 'auth_code' );
			
			$accounts = array();
			$useAuth = $ga_auth_code == '' ? false : true;
			if ( $useAuth ) {
				try {
					$accounts = self::get_analytics_accounts();
				} catch (Exception $e) {

				}

				if ( is_array( $accounts ) ) {
					$accounts = array_merge( array( __( 'Select Account','frontend-analytics' ) ), $accounts );
				} elseif ( $ga_auth_code ) {
					$accounts = array();
					$accounts[ $ga_auth_code ] = __( 'Account re-authorization may be required', 'frontend-analytics' );
				} else {
					$accounts = array();
				}
			}
			return $accounts;
		}

		public static function get_analytics_accounts() {
			global $frontend_analytics_errors;
			if ( empty( $frontend_analytics_errors ) ) {
				$frontend_analytics_errors = array();
			}
			$accounts = array();

			if ( frontend_analytics_get_option( 'auth_token' ) === false ) {
				frontend_analytics_update_option( 'auth_token', '' );
			}

			if ( frontend_analytics_get_option( 'uids' ) && ! isset( $_POST['auth_code'] ) ) {
				return frontend_analytics_get_option( 'uids' );
			}

			# Create a new Gdata call
			if ( trim( frontend_analytics_get_option( 'auth_code' ) ) != '' )
				$stats = new Frontend_Analytics_API();
			else
				return false;

			
			# Check if Google sucessfully logged in
			if ( ! $stats->checkLogin() )
				return false;

			# Get a list of accounts
			try {
				$accounts = $stats->getAllProfiles();
			} catch ( Exception $e ) {
				$frontend_analytics_errors[$e->getMessage()] = $e->getMessage();
				return false;
			}

			natcasesort ( $accounts );

			# Return the account array if there are accounts
			if ( count( $accounts ) > 0 ) {
				frontend_analytics_update_option( 'uids', $accounts );
				return $accounts;
			}
			else
				return false;
		}

		public static function render_field( $id, $args ) {

			//abort early if no element is specified
			if( empty( $args['el'] ) ) {
				return;
			}
	
			$el            = trim( sanitize_text_field( $args['el'] ) );
			$args['value'] = frontend_analytics_get_option( $id );
	
			do_action( "frontend_analytics_render_{$el}_settings_field", $id, $args );
			
		}
	
		/**
		 * Renders a select field
		 */
		public static function render_select( $id,  $args ) {

			//set options
			if( !empty( $args['data'] ) ) {
				$data = trim( $args['data'] );
	
				if( function_exists("frontend_analytics_get_$data") ) {
					$args['options'] = call_user_func( "frontend_analytics_get_$data", $id, $args );
				}
			}
			
			$id           = esc_attr( $id );
			$value        = isset( $args['value'] ) ? esc_attr( $args['value'] ) : '';
			$class        = empty( $args['class'] ) ? "regular-text" : esc_attr( $args['class'] ) . " regular-text";
			$description  = isset( $args['desc'] ) ? "<p class='description'>{$args['desc']}</p>" : '';
			echo "<label for='$id'><select class='$class' id='$id' name='$id'>";
			
			foreach ( $args['options'] as $key => $label ) {
	
				if( !is_scalar($key) || !is_scalar($label) ) {
					continue;
				}
				$key        = esc_attr( $key );
				$label      = esc_html( $label );
				$selected   = selected( $key, $value, false );
				echo "<option value='$key' $selected>$label</option> ";
			}
	
			echo "</select>$description</label>";
		}
	
		/**
		 * Renders a checkbox field
		 */
		public static function render_checkbox( $id,  $args ) {
	
			$id           = esc_attr( $id );
			$value        = isset( $args['value'] ) ? esc_attr( $args['value'] ) : '0';
			$checked      = checked( $value, '1', false );
			$class        = empty( $args['class'] ) ? "regular-checkbox" : esc_attr( $args['class'] ) . " regular-checkbox";
			$description  = isset( $args['desc'] ) ? $args['desc'] : '';
			echo "<label for='$id'><input class='$class' id='$id' name='$id' value='1' type='checkbox' $checked />$description</label>";
	
		}
	
		/**
		 * Renders an input field
		 */
		public static function render_input( $id,  $args ) {
	
			$id           = esc_attr( $id );
			$value        = isset( $args['value'] ) ? esc_attr( $args['value'] ) : '';
			$type         = empty( $args['type'] )  ? 'text' : esc_attr( $args['type'] );
			$class        = empty( $args['class'] ) ? "regular-$type" : esc_attr( $args['class'] ) . " regular-$type";
			$description  = isset( $args['desc'] ) ? "<p class='description'>{$args['desc']}</p>" : '';
			echo "<label for='$id'><input class='$class' id='$id' name='$id' value='$value' type='$type' />$description</label>";
	
		}

		/**
		 * Renders an analytics field
		 */
		public static function render_analytics( $id,  $args ) {
	
			if ( !empty( $args['value'] ) ) { ?>

				<span class="button-primary" onclick="frontend_analytics_deauthorize('<?php echo wp_create_nonce( 'frontend_analytics_deauthorize' ); ?>');"><?php _e( 'Deauthorize', 'frontend-analytics' ); ?></span> 
				<span style="color:green;font-weight:bold;"><?php _e( 'Authorized', 'frontend-analytics' ); ?></span>

			<?php	} else {	?>

				<span class="button-primary" onclick="window.open('<?php echo Frontend_Analytics_Settings::activation_url();?>', 'activate','width=700, height=600, menubar=0, status=0, location=0, toolbar=0')"><?php _e( 'Get Auth Code', 'frontend-analytics' ); ?></span>

			<?php	} ?>

			<script type="text/javascript">
			function frontend_analytics_deauthorize(nonce) {

				if (result) {
					jQuery.ajax({
						url: ajaxurl,
						type: 'POST',
						dataType: 'html',
						data: {
							action: 'frontend_analytics_deauthorize',
							_wpnonce: nonce
						},
						beforeSend: function() {},
						success: function(data, textStatus, xhr) {
							if (data) {
								window.location.assign(data);
							}
						},
						error: function(xhr, textStatus, errorThrown) {
							alert(textStatus);
						}
					}); // end of ajax
				}
			}
			</script>
<?php 
		}
	}

endif;
