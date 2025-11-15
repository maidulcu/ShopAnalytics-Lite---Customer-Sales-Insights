<?php
/**
 * Plugin Name: ShopAnalytics Lite - Customer & Sales Insights
 * Description: Basic WooCommerce customer and sales insights plugin with Pro upgrade via Freemius.
 * Version:     1.0.1
 * Author:      maidulcu
 * Author URI:
 * Text Domain: shopanalytics-lite-customer-sales-insights
 * Requires Plugins: woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.7.2
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic constants.
 */
if ( ! defined( 'SHOPANALYTICS_LITE_FILE' ) ) {
	define( 'SHOPANALYTICS_LITE_FILE', __FILE__ );
}
if ( ! defined( 'SHOPANALYTICS_LITE_DIR' ) ) {
	define( 'SHOPANALYTICS_LITE_DIR', plugin_dir_path( SHOPANALYTICS_LITE_FILE ) );
}
if ( ! defined( 'SHOPANALYTICS_LITE_URL' ) ) {
	define( 'SHOPANALYTICS_LITE_URL', plugin_dir_url( SHOPANALYTICS_LITE_FILE ) );
}
if ( ! defined( 'SHOPANALYTICS_LITE_VERSION' ) ) {
	define( 'SHOPANALYTICS_LITE_VERSION', '1.0.1' );
}


/**
 * Load includes (init.php registers classes and helpers).
 * Using require_once to avoid duplicate includes.
 */
$includes_init = SHOPANALYTICS_LITE_DIR . 'includes/init.php';
if ( file_exists( $includes_init ) ) {
    require_once $includes_init;
} 

/**
 * Enqueue admin scripts and styles
 */
function shopanalytics_enqueue_admin_scripts($hook) {
    // Only enqueue on our plugin pages
    if (strpos($hook, 'shopanalytics') === false) {
        return;
    }
    
    // Enqueue Chart.js from local assets
    wp_enqueue_script(
        'shopanalytics-chartjs',
        SHOPANALYTICS_LITE_URL . 'assets/js/chart.min.js',
        array(), // no dependencies
        '4.5.0', // version
        true // load in footer
    );
    
    // Enqueue our custom admin script
    wp_enqueue_script(
        'shopanalytics-admin',
        SHOPANALYTICS_LITE_URL . 'assets/js/admin.js',
        array('jquery', 'shopanalytics-chartjs'), // depends on jQuery and Chart.js
        SHOPANALYTICS_LITE_VERSION,
        true
    );
    
    // Enqueue admin styles
    wp_enqueue_style(
        'shopanalytics-admin',
        SHOPANALYTICS_LITE_URL . 'assets/css/admin.css',
        array(),
        SHOPANALYTICS_LITE_VERSION
    );
}
add_action('admin_enqueue_scripts', 'shopanalytics_enqueue_admin_scripts');

/**
 * Register activation & deactivation hooks.
 *
 * Note: includes/init.php defines shopanalytics_lite_activate() and shopanalytics_lite_deactivate()
 * (guarded with function_exists in that file). We register the hooks here (main plugin file).
 */
if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( SHOPANALYTICS_LITE_FILE, 'shopanalytics_lite_activate' );
	
}