<?php
// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
delete_option( 'shopanalytics_lite_do_activation_redirect' );
delete_option( 'shopanalytics_lite_settings' );
delete_site_option( 'shopanalytics_lite_network_option' );
?>
