<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include Helpers
require_once plugin_dir_path(__FILE__) . 'helpers.php';


// Include analytics engine
require_once plugin_dir_path(__FILE__) . 'class-analytics-engine.php';

// Include admin dashboard UI
require_once plugin_dir_path(__FILE__) . 'class-admin-ui.php';

// Initialize the plugin
function shopanalytics_init() {
    // Load necessary classes
    require_once plugin_dir_path(__FILE__) . 'class-analytics-engine.php';
    require_once plugin_dir_path(__FILE__) . 'class-admin-ui.php';
}
add_action('plugins_loaded', 'shopanalytics_init');

// Show setup wizard on first activation
function shopanalytics_lite_activation_redirect() {
    if ( get_option( 'shopanalytics_lite_do_activation_redirect', false ) ) {
        delete_option( 'shopanalytics_lite_do_activation_redirect' );
        if ( ! isset( $_GET['activate-multi'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=shopanalytics-lite-setup' ) );
            exit;
        }
    }
}
add_action( 'admin_init', 'shopanalytics_lite_activation_redirect' );

function shopanalytics_lite_activate() {
    add_option( 'shopanalytics_lite_do_activation_redirect', true );
}
register_activation_hook( plugin_dir_path(__DIR__) . 'shopanalytics-lite.php', 'shopanalytics_lite_activate' );

function shopanalytics_lite_setup_wizard_menu() {
    add_submenu_page(
        null,
        'Welcome to ShopAnalytics Lite',
        'Welcome to ShopAnalytics Lite',
        'manage_woocommerce',
        'shopanalytics-lite-setup',
        'shopanalytics_lite_setup_page'
    );
}
add_action( 'admin_menu', 'shopanalytics_lite_setup_wizard_menu' );

function shopanalytics_lite_setup_page() {
    $step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'welcome';

    echo '<div class="wrap">';
    echo '<h1>🎉 Welcome to ShopAnalytics Lite</h1>';

    switch ($step) {
        case 'connect':
            echo '<h2>Step 2: Connect to WooCommerce</h2>';
            echo '<p>Your store is already connected. ShopAnalytics Lite reads customer and order data from WooCommerce directly.</p>';
            echo '<p><a href="' . admin_url('admin.php?page=shopanalytics-lite-setup&step=complete') . '" class="button button-primary">Next Step</a></p>';
            break;

        case 'complete':
            echo '<h2>You are All Set!</h2>';
            echo '<p>You are ready to start using ShopAnalytics Lite.</p>';
            echo '<p><a href="' . admin_url('admin.php?page=shopanalytics-lite') . '" class="button button-primary">Go to Dashboard</a></p>';
            break;

        default:
            echo '<h2>Step 1: Introduction</h2>';
            echo '<p>ShopAnalytics Lite gives you insights into your WooCommerce sales and customer behavior.</p>';
            echo '<ul>
                    <li>📈 Track Revenue, Orders, and AOV</li>
                    <li>🔁 Measure Repeat Purchases</li>
                    <li>🏅 Identify Top Customers</li>
                  </ul>';
            echo '<p><a href="' . admin_url('admin.php?page=shopanalytics-lite-setup&step=connect') . '" class="button button-primary">Get Started</a></p>';
            break;
    }

    echo '</div>';
}
