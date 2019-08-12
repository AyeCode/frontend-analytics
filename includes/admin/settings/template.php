<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <?php do_action( 'frontend_analytics_settings_page_top' ); ?>
    <form class="frontend-analytics-settings-tab-main-form" method="post" action="<?php echo admin_url('admin.php?page=frontend-analytics') ?>">
        <table class="form-table">
            <tbody>
                <?php foreach ( Frontend_Analytics_Settings::get_settings() as $id => $args ) {?>
                    <tr>
                        <th scope="row"><?php if(! empty( $args['name'] ) ) echo $args['name']; ?></th>
                        <td><?php Frontend_Analytics_Settings::render_field(  $id, $args  )?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php wp_nonce_field(); ?>
        <?php submit_button(); ?>
    </form>
    <?php do_action( 'frontend_analytics_settings_page_bottom' ); ?>
</div>