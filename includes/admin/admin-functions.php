<?php
/**
 * Google Analytics Admin Functions.
 *
 * @since 2.0.0
 * @package Geodir_Google_Analytics
 * @author AyeCode Ltd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add the plugin to uninstall settings.
 *
 * @since 2.0.0
 *
 * @return array $settings the settings array.
 * @return array The modified settings.
 */
function geodir_ga_uninstall_settings( $settings ) {
    array_pop( $settings );

	$settings[] = array(
		'name'     => __( 'Google Analytics', 'geodir-ga' ),
		'desc'     => __( 'Check this box if you would like to completely remove all of its data when Google Analytics is deleted.', 'geodir-ga' ),
		'id'       => 'uninstall_geodir_google_analytics',
		'type'     => 'checkbox',
	);
	$settings[] = array( 
		'type' => 'sectionend',
		'id' => 'uninstall_options'
	);

	return $settings;
}

function geodir_ga_google_analytics_field( $field ) {
	global $gd_ga_errors;
	?>
	<tr valign="top">
		<th scope="row" class="titledesc"><?php echo $field['name'] ?></th>
		<td class="forminp">
			<?php if ( geodir_get_option( 'ga_auth_token' ) ) { ?>
				<span class="button-primary" onclick="geodir_ga_deauthorize('<?php echo wp_create_nonce( 'gd_ga_deauthorize' ); ?>');"><?php _e( 'Deauthorize', 'geodir-ga' ); ?></span> 
				<span style="color:green;font-weight:bold;"><?php _e( 'Authorized', 'geodir-ga' ); ?></span>
				<?php
				if ( ! empty( $gd_ga_errors ) ) {
					print_r( $gd_ga_errors );
				}
			} else {
				?>
				<span class="button-primary" onclick="window.open('<?php echo GeoDir_Settings_Analytics::activation_url();?>', 'activate','width=700, height=600, menubar=0, status=0, location=0, toolbar=0')"><?php _e( 'Authorize', 'geodir-ga' ); ?></span>
				<?php
			}
			?>
			<script type="text/javascript">
			function geodir_ga_deauthorize(nonce) {
				var result = confirm(geodir_params.ga_confirm_delete);
				if (result) {
					jQuery.ajax({
						url: geodir_params.ajax_url,
						type: 'POST',
						dataType: 'html',
						data: {
							action: 'geodir_ga_deauthorize',
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
		</td>
	</tr>
	<?php
}