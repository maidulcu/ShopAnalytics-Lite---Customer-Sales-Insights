<?php
/**
 * Plugin Name: ShopAnalytics Lite - Customer & Sales Insights
 * Description: Basic WooCommerce customer and sales insights plugin with Pro upgrade via Freemius.
 * Version: 1.0.0
 * Author: DynamicWebLab
 * Author URI: https://dynamicweblab.com
 * Text Domain: shopanalytics-lite
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Load Freemius SDK
if ( ! function_exists( 'shopanalytics_fs' ) ) {
    function shopanalytics_fs() {
        global $shopanalytics_fs;

        if ( ! isset( $shopanalytics_fs ) ) {
            // Include Freemius SDK
            require_once dirname(__FILE__) . '/freemius/start.php';

            $shopanalytics_fs = fs_dynamic_init( array(
                'id'                  => '12345',
                'slug'                => 'shopanalytics-lite',
                'type'                => 'plugin',
                'public_key'          => 'YOUR_PUBLIC_KEY',
                'is_premium'          => false,
                'has_premium_version' => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug' => 'shopanalytics-lite',
                    'first-path' => 'admin.php?page=shopanalytics-lite',
                    'support' => false,
                ),
            ) );
        }

        return $shopanalytics_fs;
    }

    // Init Freemius
    shopanalytics_fs();
    // Signal SDK is loaded
    do_action( 'shopanalytics_fs_loaded' );
}

require_once plugin_dir_path(__FILE__) . 'includes/init.php';

// Add admin menu
// add_action( 'admin_menu', 'shopanalytics_lite_admin_menu' );
// function shopanalytics_lite_admin_menu() {
//     add_menu_page(
//         'ShopAnalytics Lite',
//         'ShopAnalytics',
//         'manage_woocommerce',
//         'shopanalytics-lite',
//         'shopanalytics_lite_dashboard_callback',
//         'dashicons-chart-area'
//     );
// }

// // Admin page callback
// function shopanalytics_lite_dashboard_callback() {
//     echo '<div class="wrap"><h1>ShopAnalytics Lite Dashboard</h1>';

//     if ( shopanalytics_fs()->can_use_premium_code() ) {
//         echo '<p><strong>Welcome, Pro user!</strong> You have access to all features.</p>';
//         // Pro feature demo
//         echo '<p>Pro Feature: Detailed cohort analysis and export tools here.</p>';
//     } else {
//         echo '<p>You are using the <strong>Lite version</strong>. Upgrade to Pro to unlock advanced insights.</p>';
//         //shopanalytics_fs()->add_upgrade_button();
//     }

//     echo '</div>';
// }
