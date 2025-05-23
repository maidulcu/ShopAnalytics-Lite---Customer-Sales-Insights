<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * This file will be used in future for dashboard layout abstraction.
 * Currently rendered via class-admin-ui.php directly.
 */

// Example: future template hook
do_action( 'shopanalytics_dashboard_start' );

echo '<div class="shopanalytics-dashboard">';
echo '<p>This template is currently unused. Dashboard is rendered inline via Admin UI class.</p>';
echo '</div>';

do_action( 'shopanalytics_dashboard_end' );
