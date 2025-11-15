<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dashboard layout template.
 * This file is now actively used to render the dashboard view.
 */

do_action( 'shopanalytics_dashboard_start' );
?>

<div class="shopanalytics-dashboard">
    <h1><?php esc_html_e( '📊 ShopAnalytics Dashboard', 'shopanalytics-lite-customer-sales-insights' ); ?></h1>
    <p><?php esc_html_e( 'Welcome! This is the new dashboard template. Future charts and widgets will appear here.', 'shopanalytics-lite-customer-sales-insights' ); ?></p>

    <div class="shopanalytics-widgets">
        <!-- Example: add_widget( 'total_revenue_chart' ); -->
    </div>
</div>

<?php
do_action( 'shopanalytics_dashboard_end' );
