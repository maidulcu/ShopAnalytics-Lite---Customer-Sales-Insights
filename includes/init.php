<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


add_action( 'plugins_loaded', function () {
    if ( class_exists( 'WooCommerce' ) ) {
        $helpers = SHOPANALYTICS_LITE_DIR . 'includes/helpers.php';
        if ( file_exists( $helpers ) ) {
            require_once $helpers;
        }

        $engine = SHOPANALYTICS_LITE_DIR . 'includes/class-analytics-engine.php';
        if ( file_exists( $engine ) ) {
            require_once $engine;
        }

        $admin_ui = SHOPANALYTICS_LITE_DIR . 'includes/class-admin-ui.php';
        if ( file_exists( $admin_ui ) ) {
            require_once $admin_ui;
        }
    } else {
        add_action( 'admin_notices', function () {
            if ( current_user_can( 'activate_plugins' ) ) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__( 'ShopAnalytics Lite requires WooCommerce to be active. Please install and activate WooCommerce.', 'shopanalytics-lite-customer-sales-insights' );
                echo '</p></div>';
            }
        } );
    }
} );


/**
 * Activation redirect: show setup wizard after activation.
 * Guarded and sanitized.
 */
if ( ! function_exists( 'shopanalytics_lite_activation_redirect' ) ) {
    function shopanalytics_lite_activation_redirect() {
        if ( get_option( 'shopanalytics_lite_do_activation_redirect', false ) ) {
            delete_option( 'shopanalytics_lite_do_activation_redirect' );

            // don't redirect during bulk activation
            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=shopanalytics-lite-setup' ) );
                exit;
            }
        }
    }
}
add_action( 'admin_init', 'shopanalytics_lite_activation_redirect' );

function shopanalytics_lite_activate() {
    // Set up the logs table
    global $wpdb;
    $table_name = $wpdb->prefix . 'shopanalytics_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        event text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        INDEX created_at_idx (created_at) -- Added index for performance
    ) $charset_collate;";

    if ( ! function_exists( 'dbDelta' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }
    dbDelta( $sql );

    // Schedule cron job for log cleanup
    if ( ! wp_next_scheduled( 'shopanalytics_custom_daily_log_cleanup_hook' ) ) {
        wp_schedule_event( time(), 'daily', 'shopanalytics_custom_daily_log_cleanup_hook' );
    }

    add_option( 'shopanalytics_lite_do_activation_redirect', true );
}

register_activation_hook( plugin_dir_path(__DIR__) . 'shopanalytics-lite.php', 'shopanalytics_lite_activate' );

/**
 * Setup wizard hidden submenu (so it can be accessed via direct URL).
 * Use manage_woocommerce capability for store admins; fallback to manage_options where appropriate in UI.
 */
if ( ! function_exists( 'shopanalytics_lite_setup_wizard_menu' ) ) {
    function shopanalytics_lite_setup_wizard_menu() {
        add_submenu_page(
            null,
            __( 'Welcome to ShopAnalytics Lite', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Welcome to ShopAnalytics Lite', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-lite-setup',
            'shopanalytics_lite_setup_page'
        );
    }
}
add_action( 'admin_menu', 'shopanalytics_lite_setup_wizard_menu' );



/**
 * Render setup wizard page (simple safe implementation).
 */
if ( ! function_exists( 'shopanalytics_lite_setup_page' ) ) {
    function shopanalytics_lite_setup_page() {
        $step = isset( $_GET['step'] ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : 'welcome';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( '🎉 Welcome to ShopAnalytics Lite', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';

        switch ( $step ) {
            case 'connect':
                echo '<h2>' . esc_html__( 'Step 2: Connect to WooCommerce', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
                echo '<p>' . esc_html__( 'Your store is already connected. ShopAnalytics Lite reads customer and order data from WooCommerce directly.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=shopanalytics-lite-setup&step=complete' ) ) . '" class="button button-primary">' . esc_html__( 'Next Step', 'shopanalytics-lite-customer-sales-insights' ) . '</a></p>';
                break;

            case 'complete':
                echo '<h2>' . esc_html__( 'You are All Set!', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
                echo '<p>' . esc_html__( 'You are ready to start using ShopAnalytics Lite.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=shopanalytics-lite' ) ) . '" class="button button-primary">' . esc_html__( 'Go to Dashboard', 'shopanalytics-lite-customer-sales-insights' ) . '</a></p>';
                break;

            default:
                echo '<h2>' . esc_html__( 'Step 1: Introduction', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
                echo '<p>' . esc_html__( 'ShopAnalytics Lite gives you insights into your WooCommerce sales and customer behavior.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
                echo '<ul>
                        <li>' . esc_html__( '📈 Track Revenue, Orders, and AOV', 'shopanalytics-lite-customer-sales-insights' ) . '</li>
                        <li>' . esc_html__( '🔁 Measure Repeat Purchases', 'shopanalytics-lite-customer-sales-insights' ) . '</li>
                        <li>' . esc_html__( '🏅 Identify Top Customers', 'shopanalytics-lite-customer-sales-insights' ) . '</li>
                      </ul>';
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=shopanalytics-lite-setup&step=connect' ) ) . '" class="button button-primary">' . esc_html__( 'Get Started', 'shopanalytics-lite-customer-sales-insights' ) . '</a></p>';
                break;
        }

        echo '</div>';
    }
}