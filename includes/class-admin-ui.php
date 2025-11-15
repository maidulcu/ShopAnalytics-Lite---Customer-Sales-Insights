<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShopAnalytics_Admin_UI {

    private $engine;

    public function __construct() {
        $this->engine = new ShopAnalytics_Engine();
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'shopanalytics_custom_daily_log_cleanup_hook', [ $this, 'cleanup_old_logs' ] ); // Changed hook
    }

    public function enqueue_admin_assets( $hook ) {
        // Only load on our plugin pages
        if ( strpos( $hook, 'shopanalytics' ) === false ) {
            return;
        }

        // Register and enqueue Chart.js
        wp_register_script(
            'shopanalytics-chartjs',
             SHOPANALYTICS_LITE_URL . 'assets/js/chart.min.js',
            [],
            '4.5.0',
            true
        );
        wp_enqueue_script( 'shopanalytics-chartjs' );

        // Register and enqueue admin styles
        wp_register_style(
            'shopanalytics-admin',
            plugin_dir_url( __DIR__ ) . 'assets/admin.css',
            [],
            '1.0.0'
        );
        wp_enqueue_style( 'shopanalytics-admin' );
    }

    public function register_settings() {
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_enable_logging', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_enable_pro_previews', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_light_charts', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_log_retention_days', [ 'sanitize_callback' => 'intval' ] );
    }
    
    public function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'shopanalytics_logs';
        // Ensure retention_days is at least 1
        $retention_days = max( 1, intval( get_option( 'shopanalytics_log_retention_days', 90 ) ) );
        $threshold_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}shopanalytics_logs WHERE created_at < %s", $threshold_date ) );
        wp_cache_flush_group('shopanalytics');
    }

    private function maybe_log_event( $message ) {
        if ( get_option( 'shopanalytics_enable_logging' ) ) {
            $user_id = get_current_user_id();
            $timestamp = current_time( 'mysql' );
            global $wpdb;
            $table_name = $wpdb->prefix . 'shopanalytics_logs';

            $wpdb->insert( $table_name, [
                'user_id'    => $user_id,
                'event'      => $message,
                'created_at' => $timestamp,
            ] );
            wp_cache_delete('shopanalytics_logs_view_*', 'shopanalytics');
            wp_cache_delete('shopanalytics_logs_export_*', 'shopanalytics');
        }
    }

    public function register_menu() {
        // Top-level menu
        add_menu_page(
            __( 'ShopAnalytics Lite', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'ShopAnalytics', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-lite-customer-sales-insights',
            [ $this, 'render_dashboard' ],
            'dashicons-chart-line',
            56
        );

        // Submenus
        add_submenu_page(
            'shopanalytics-lite-customer-sales-insights',
            __( 'Dashboard', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Dashboard', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-lite-customer-sales-insights',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'shopanalytics-lite-customer-sales-insights',
            __( 'Sales Trends', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Sales Trends', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-sales',
            [ $this, 'render_sales_trends' ]
        );

        add_submenu_page(
            'shopanalytics-lite-customer-sales-insights',
            __( 'Order Trends', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Order Trends', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-order-trends',
            [ $this, 'render_order_trends' ]
        );

        add_submenu_page(
            'shopanalytics-lite-customer-sales-insights',
            __( 'Reports', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Reports', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-reports',
            [ $this, 'render_reports' ]
        );

        add_submenu_page(
            'shopanalytics-lite-customer-sales-insights',
            __( 'Top Products', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Top Products', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-products',
            [ $this, 'render_top_products' ]
        );

        add_submenu_page(
            'shopanalytics-lite-customer-sales-insights',
            __( 'Settings', 'shopanalytics-lite-customer-sales-insights' ),
            __( 'Settings', 'shopanalytics-lite-customer-sales-insights' ),
            'manage_woocommerce',
            'shopanalytics-settings',
            [ $this, 'render_settings' ]
        );
    }

    public function render_dashboard() {
        $this->maybe_log_event( 'Visited Dashboard' );
        if ( isset($_POST['export_csv']) && current_user_can('manage_woocommerce') ) {
            // Check nonce for security
            check_admin_referer('export_csv_nonce');

            $export_from = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
            $export_to = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
            $export_from_date = $export_from ? gmdate('Y-m-d', strtotime($export_from)) : null;
            $export_to_date = $export_to ? gmdate('Y-m-d', strtotime($export_to)) : null;

            $export_engine = new ShopAnalytics_Engine();
            $export_customers = $export_engine->get_top_customers( 100, $export_from_date, $export_to_date );

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="top-customers.csv"');
            echo "Name,Email,Orders,Total Spent\n";

            foreach ( $export_customers as $cust ) {
                echo sprintf("%s,%s,%s,%s\n", 
                    esc_html($cust['name']), 
                    esc_html($cust['email']), 
                    esc_html($cust['orders']), 
                    esc_html($cust['total_spent'])
                );
            }
            $this->maybe_log_event( 'Exported Top Customers CSV' );
            exit;
        }

        if ( isset($_POST['export_metrics']) && current_user_can('manage_woocommerce') ) {
            // Check nonce for security
            check_admin_referer('export_metrics_nonce');

            $export_from = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
            $export_to = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
            $export_from_date = $export_from ? gmdate('Y-m-d', strtotime($export_from)) : null;
            $export_to_date = $export_to ? gmdate('Y-m-d', strtotime($export_to)) : null;

            $export_engine = new ShopAnalytics_Engine();

            $metrics = [
                'Total Revenue' => $export_engine->get_total_revenue( $export_from_date, $export_to_date ),
                'Total Orders' => $export_engine->get_total_orders( $export_from_date, $export_to_date ),
                'Average Order Value' => $export_engine->get_average_order_value( $export_from_date, $export_to_date ),
                'Repeat Purchase Rate (%)' => $export_engine->get_repeat_purchase_rate( $export_from_date, $export_to_date ),
            ];

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="key-metrics.csv"');
            echo "Metric,Value\n";
            foreach ( $metrics as $label => $value ) {
                echo sprintf("%s,%s\n", esc_html($label), esc_html($value));
            }
            $this->maybe_log_event( 'Exported Key Metrics CSV' );
            exit;
        }

        if ( isset($_POST['export_orders']) && current_user_can('manage_woocommerce') ) {
            // Check nonce for security
            check_admin_referer('export_orders_nonce');

            $export_from = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
            $export_to = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
            $export_from_date = $export_from ? gmdate('Y-m-d', strtotime($export_from)) : null;
            $export_to_date = $export_to ? gmdate('Y-m-d', strtotime($export_to)) : null;

            $args = [
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1,
            ];
            if ( $export_from_date || $export_to_date ) {
                $args['date_created'] = [];
                if ( $export_from_date ) {
                    $args['date_created']['after'] = new \WC_DateTime( $export_from_date );
                }
                if ( $export_to_date ) {
                    $args['date_created']['before'] = new \WC_DateTime( $export_to_date );
                }
            }
            $orders = wc_get_orders( $args );

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="orders.csv"');
            echo "Order ID,Customer Name,Customer Email,Total,Date\n";

            foreach ( $orders as $order ) {
                echo sprintf("%s,%s,%s,%s,%s\n",
                    esc_html($order->get_id()),
                    esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    esc_html($order->get_billing_email()),
                    esc_html($order->get_total()),
                    esc_html($order->get_date_created()->format('Y-m-d H:i:s'))
                );
            }
            $this->maybe_log_event( 'Exported Orders CSV' );
            exit;
        }

        echo '<div class="wrap shopanalytics-dashboard">';

        // Check nonce for security on filter parameters
        if ( isset( $_GET['_wpnonce'] ) ) {
            check_admin_referer( 'dashboard_filter_nonce' );
        }

        $from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        $to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

        echo '<div class="shopanalytics-dashboard__header">';
        echo '<h1 class="shopanalytics-dashboard__title">' . esc_html__( 'ShopAnalytics Dashboard', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';
        echo '<p class="shopanalytics-dashboard__subtitle">' . esc_html__( 'Review store performance at a glance or narrow the window for deeper insight.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';

        echo '<div class="shopanalytics-dashboard__filters">';
        echo '<form method="get" class="shopanalytics-filter">';
        echo '<input type="hidden" name="page" value="shopanalytics-lite-customer-sales-insights" />';
        wp_nonce_field( 'dashboard_filter_nonce' );
        echo '<div class="shopanalytics-filter__row">';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="shopanalytics-filter-from" class="shopanalytics-filter__label">' . esc_html__( 'From', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="shopanalytics-filter-from" class="shopanalytics-filter__input" type="date" name="from" value="' . esc_attr( $from ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="shopanalytics-filter-to" class="shopanalytics-filter__label">' . esc_html__( 'To', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="shopanalytics-filter-to" class="shopanalytics-filter__input" type="date" name="to" value="' . esc_attr( $to ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        if ( $from || $to ) {
            $reset_url = remove_query_arg( [ 'from', 'to', '_wpnonce' ] );
            echo '<a class="button shopanalytics-filter__reset" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset', 'shopanalytics-lite-customer-sales-insights' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        /**
         * @todo Pro feature
         */
        // echo '<div class="shopanalytics-export-box">';
        // echo '<h3>' . esc_html__( '📤 Export Options', 'shopanalytics-lite-customer-sales-insights' ) . ' <span style="color: #d54e21;">(' . esc_html__( 'Pro', 'shopanalytics-lite-customer-sales-insights' ) . ')</span></h3>';
        // echo '<form method="post" style="margin-top:10px;">';
        // echo '<input type="hidden" name="from" value="' . esc_attr($from) . '" />';
        // echo '<input type="hidden" name="to" value="' . esc_attr($to) . '" />';
        // echo '<input type="submit" name="export_csv" class="button" value="' . esc_attr__( 'Export Top Customers to CSV (Pro)', 'shopanalytics-lite-customer-sales-insights' ) . '" />';
        // echo '<input type="submit" name="export_metrics" class="button" value="' . esc_attr__( 'Export Key Metrics to CSV (Pro)', 'shopanalytics-lite-customer-sales-insights' ) . '" />';
        // echo '<input type="submit" name="export_orders" class="button" value="' . esc_attr__( 'Export Orders to CSV (Pro)', 'shopanalytics-lite-customer-sales-insights' ) . '" />';
        // echo '</form>';
        // echo '</div>';


        $from_date = $from ? gmdate('Y-m-d', strtotime($from)) : null;
        $to_date = $to ? gmdate('Y-m-d', strtotime($to)) : null;
        
        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Note:', 'shopanalytics-lite-customer-sales-insights' ) . '</strong> ' . esc_html__( 'Charts are disabled due to the "Lightweight Charts" setting. Disable this option in Settings to view visualizations.', 'shopanalytics-lite-customer-sales-insights' ) . '</p></div>';
            return;
        }

        $total_revenue = $this->engine->get_total_revenue( $from_date, $to_date );
        $total_orders  = $this->engine->get_total_orders( $from_date, $to_date );
        $aov           = $this->engine->get_average_order_value( $from_date, $to_date );
        $repeat_rate   = $this->engine->get_repeat_purchase_rate( $from_date, $to_date );
        $top_customers = $this->engine->get_top_customers( 5, $from_date, $to_date );

        $metric_cards = [
            [
                'icon'        => '💰',
                'label'       => esc_html__( 'Total Revenue', 'shopanalytics-lite-customer-sales-insights' ),
                'value'       => wp_kses_post( wc_price( $total_revenue ) ),
                'format'      => 'html',
                'description' => esc_html__( 'Gross sales for the selected range.', 'shopanalytics-lite-customer-sales-insights' ),
            ],
            [
                'icon'        => '🛒',
                'label'       => esc_html__( 'Total Orders', 'shopanalytics-lite-customer-sales-insights' ),
                'value'       => number_format_i18n( $total_orders ),
                'format'      => 'text',
                'description' => esc_html__( 'Completed and processing WooCommerce orders.', 'shopanalytics-lite-customer-sales-insights' ),
            ],
            [
                'icon'        => '📦',
                'label'       => esc_html__( 'Average Order Value', 'shopanalytics-lite-customer-sales-insights' ),
                'value'       => wp_kses_post( wc_price( $aov ) ),
                'format'      => 'html',
                'description' => esc_html__( 'Revenue per order over the filtered period.', 'shopanalytics-lite-customer-sales-insights' ),
            ],
            [
                'icon'        => '🔁',
                'label'       => esc_html__( 'Repeat Purchase Rate', 'shopanalytics-lite-customer-sales-insights' ),
                'value'       => number_format_i18n( $repeat_rate, 2 ) . '%',
                'format'      => 'text',
                'description' => esc_html__( 'Share of customers placing more than one order.', 'shopanalytics-lite-customer-sales-insights' ),
            ],
        ];

        echo '<section class="shopanalytics-panel shopanalytics-panel--metrics">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '📈 Key Metrics', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '</div>';
        echo '<div class="shopanalytics-metric-grid">';

        foreach ( $metric_cards as $card ) {
            echo '<div class="shopanalytics-metric-card">';
            echo '<span class="shopanalytics-metric-card__icon" aria-hidden="true">' . esc_html( $card['icon'] ) . '</span>';
            echo '<div class="shopanalytics-metric-card__body">';
            echo '<p class="shopanalytics-metric-card__label">' . esc_html( $card['label'] ) . '</p>';
            if ( 'html' === $card['format'] ) {
                echo '<p class="shopanalytics-metric-card__value">' . $card['value'] . '</p>';
            } else {
                echo '<p class="shopanalytics-metric-card__value">' . esc_html( $card['value'] ) . '</p>';
            }
            echo '<p class="shopanalytics-metric-card__description">' . esc_html( $card['description'] ) . '</p>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '📦 Order & Repeat Customer Trends', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Visualize total orders against returning customers month over month.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';
        echo '<canvas id="orderTrendChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        echo '</div>';
        echo '</section>';

        // Prepare data
        $monthly_orders = [];
        $monthly_repeat = [];
        $current = new DateTime($from_date ?? 'first day of this year');
        $end = new DateTime($to_date ?? 'last day of this year');

        while ($current <= $end) {
            $month_start = $current->format('Y-m-01');
            $month_end = $current->format('Y-m-t');

            // Get all orders for the month
            $args = [
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1,
                'return' => 'ids',
            ];
                if ( $month_start || $month_end ) {
                    if ( $month_start ) {
                        try {
                            $args['date_created_after'] = ( new \WC_DateTime( $month_start ) )->format( DATE_ATOM );
                        } catch ( Exception $e ) {
                            // Invalid date format, skip
                        }
                    }
                    if ( $month_end ) {
                        try {
                            $args['date_created_before'] = ( new \WC_DateTime( $month_end ) )->format( DATE_ATOM );
                        } catch ( Exception $e ) {
                            // Invalid date format, skip
                        }
                    }
                }
            $orders = wc_get_orders( $args );

            $monthly_orders[$current->format('M Y')] = count($orders);

            // Repeat customer count - Optimized to avoid N+1 queries
            $customer_ids = array();
            foreach ( $orders as $order ) {
                if ( is_object( $order ) && method_exists( $order, 'get_customer_id' ) ) {
                    $customer_id = $order->get_customer_id();
                    if ( $customer_id ) {
                        $customer_ids[] = $customer_id;
                    }
                }
            }

            // Batch query to get order counts for all customers
            $repeat_customers = array();
            if ( ! empty( $customer_ids ) ) {
                global $wpdb;
                $customer_ids_placeholders = implode( ',', array_fill( 0, count( array_unique( $customer_ids ) ), '%d' ) );
                $order_counts_query = $wpdb->prepare(
                    "SELECT meta.meta_value as customer_id, COUNT(posts.ID) as order_count
                    FROM {$wpdb->posts} AS posts
                    INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
                    WHERE posts.post_type = 'shop_order'
                    AND posts.post_status IN (%s, %s)
                    AND meta.meta_key = '_customer_user'
                    AND meta.meta_value IN ($customer_ids_placeholders)
                    GROUP BY meta.meta_value",
                    array_merge(
                        array( ShopAnalytics_Engine::ORDER_STATUSES[0], ShopAnalytics_Engine::ORDER_STATUSES[1] ),
                        array_unique( $customer_ids )
                    )
                );
                $customer_order_counts = $wpdb->get_results( $order_counts_query, OBJECT_K );

                foreach ( array_unique( $customer_ids ) as $customer_id ) {
                    if ( isset( $customer_order_counts[ $customer_id ] ) && $customer_order_counts[ $customer_id ]->order_count > 1 ) {
                        $repeat_customers[ $customer_id ] = true;
                    }
                }
            }

            $monthly_repeat[$current->format('M Y')] = count($repeat_customers);

            $current->modify('+1 month');
        }

        // Output chart script
        $chart_data = array(
            'labels' => array_keys($monthly_orders),
            'datasets' => array(
                array(
                    'label' => "Total Orders",
                    'data' => array_values($monthly_orders),
                    'backgroundColor' => "rgba(54, 162, 235, 0.6)"
                ),
                array(
                    'label' => "Repeat Customers",
                    'data' => array_values($monthly_repeat),
                    'backgroundColor' => "rgba(255, 206, 86, 0.6)"
                )
            )
        );
        
        wp_add_inline_script('shopanalytics-chartjs', '
            document.addEventListener("DOMContentLoaded", function() {
                const ctx2 = document.getElementById("orderTrendChart").getContext("2d");
                const orderTrendChart = new Chart(ctx2, {
                    type: "bar",
                    data: ' . wp_json_encode($chart_data) . ',
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        ');


        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '💡 Revenue vs. AOV', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Track how revenue trends alongside average order value.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';
        echo '<canvas id="revenueAOVChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        echo '</div>';
        echo '</section>';

        $monthly_aov = [];
        $current = new DateTime($from_date ?? 'first day of this year');
        $end = new DateTime($to_date ?? 'last day of this year');

        while ($current <= $end) {
            $month_start = $current->format('Y-m-01');
            $month_end = $current->format('Y-m-t');

            $revenue = $this->engine->get_total_revenue($month_start, $month_end);
            $orders = $this->engine->get_total_orders($month_start, $month_end);
            $aov = $orders > 0 ? $revenue / $orders : 0;

            $monthly_revenue[$current->format('M Y')] = $revenue;
            $monthly_aov[$current->format('M Y')] = round($aov, 2);

            $current->modify('+1 month');
        }

        // Prepare chart data for revenue vs AOV chart
        $revenue_aov_data = array(
            'labels' => array_keys($monthly_revenue),
            'datasets' => array(
                array(
                    'label' => "Revenue",
                    'data' => array_values($monthly_revenue),
                    'borderColor' => "rgba(75, 192, 192, 1)",
                    'backgroundColor' => "rgba(75, 192, 192, 0.2)",
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => "y"
                ),
                array(
                    'label' => "AOV",
                    'data' => array_values($monthly_aov),
                    'borderColor' => "rgba(255, 99, 132, 1)",
                    'backgroundColor' => "rgba(255, 99, 132, 0.2)",
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => "y1"
                )
            )
        );

        // Add inline script for revenue vs AOV chart
        wp_add_inline_script('shopanalytics-chartjs', '
            document.addEventListener("DOMContentLoaded", function() {
                const ctx3 = document.getElementById("revenueAOVChart").getContext("2d");
                const revenueAOVChart = new Chart(ctx3, {
                    type: "line",
                    data: ' . wp_json_encode($revenue_aov_data) . ',
                    options: {
                        responsive: true,
                        interaction: {
                            mode: "index",
                            intersect: false
                        },
                        stacked: false,
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                type: "linear",
                                display: true,
                                position: "left"
                            },
                            y1: {
                                type: "linear",
                                display: true,
                                position: "right",
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            });
        ');

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '🏅 Top Customers', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'A quick look at the shoppers driving the most revenue.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( empty( $top_customers ) ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'No customers match the current filters yet.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        } else {
            echo '<div class="shopanalytics-table-wrapper">';
            echo '<table class="widefat fixed striped shopanalytics-table">';
            echo '<thead><tr><th>' . esc_html__( 'Name', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Email', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Orders', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Total Spent', 'shopanalytics-lite-customer-sales-insights' ) . '</th></tr></thead><tbody>';

            foreach ( $top_customers as $customer ) {
                echo '<tr>';
                echo '<td>' . esc_html( $customer['name'] ) . '</td>';
                echo '<td>' . esc_html( $customer['email'] ) . '</td>';
                echo '<td>' . esc_html( $customer['orders'] ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $customer['total_spent'] ) ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';
        
        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '📊 Customer Segmentation', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Understand the mix of first-time, repeat, and high-value customers.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        $segments = [ '1 Order' => 0, '2-3 Orders' => 0, '4-5 Orders' => 0, '6+ Orders' => 0 ];
        $locations = [];

        foreach ( $top_customers as $cust ) {
            $orders = $cust['orders'];
            if ( $orders === 1 ) {
                $segments['1 Order']++;
            } elseif ( $orders <= 3 ) {
                $segments['2-3 Orders']++;
            } elseif ( $orders <= 5 ) {
                $segments['4-5 Orders']++;
            } else {
                $segments['6+ Orders']++;
            }

            $user = get_user_by( 'email', $cust['email'] );
            if ( $user ) {
                $country = get_user_meta( $user->ID, 'billing_country', true );
                if ( $country ) {
                    if ( ! isset( $locations[ $country ] ) ) {
                        $locations[ $country ] = 0;
                    }
                    $locations[ $country ]++;
                }
            }
        }

        echo '<div class="shopanalytics-chart-grid">';
        echo '<div class="shopanalytics-chart-grid__item">';
        echo '<canvas id="segmentChart" class="shopanalytics-panel__chart" height="50"></canvas>';
        echo '</div>';
        echo '<div class="shopanalytics-chart-grid__item">';
        echo '<canvas id="locationChart" class="shopanalytics-panel__chart" height="50"></canvas>';
        echo '</div>';
        echo '<div class="shopanalytics-chart-grid__item">';
        echo '<canvas id="clvChart" class="shopanalytics-panel__chart" height="50"></canvas>';
        echo '</div>';
        echo '</div>';

        // Prepare chart data for customer segmentation
        $segment_data = array(
            'segments' => array(
                'labels' => array_keys($segments),
                'data' => array_values($segments)
            ),
            'locations' => array(
                'labels' => array_keys($locations),
                'data' => array_values($locations)
            ),
            'clv' => array(
                'labels' => array_column($top_customers, 'name'),
                'data' => array_column($top_customers, 'total_spent')
            )
        );

        // Add inline script for customer segmentation charts
        wp_add_inline_script('shopanalytics-chartjs', '
            document.addEventListener("DOMContentLoaded", function () {
                new Chart(document.getElementById("segmentChart").getContext("2d"), {
                    type: "bar",
                    data: {
                        labels: ' . wp_json_encode($segment_data['segments']['labels']) . ',
                        datasets: [{
                            label: "Customer Count",
                            data: ' . wp_json_encode($segment_data['segments']['data']) . ',
                            backgroundColor: "rgba(54, 162, 235, 0.6)"
                        }]
                    }
                });
                
                new Chart(document.getElementById("locationChart").getContext("2d"), {
                    type: "doughnut",
                    data: {
                        labels: ' . wp_json_encode($segment_data['locations']['labels']) . ',
                        datasets: [{
                            label: "Customers by Country",
                            data: ' . wp_json_encode($segment_data['locations']['data']) . ',
                            backgroundColor: [
                                "#36a2eb", "#ffcd56", "#4bc0c0", "#ff6384", "#9966ff", "#c9cbcf"
                            ]
                        }]
                    }
                });
                
                new Chart(document.getElementById("clvChart").getContext("2d"), {
                    type: "line",
                    data: {
                        labels: ' . wp_json_encode($segment_data['clv']['labels']) . ',
                        datasets: [{
                            label: "CLV",
                            data: ' . wp_json_encode($segment_data['clv']['data']) . ',
                            borderColor: "rgba(255, 99, 132, 1)",
                            backgroundColor: "rgba(255, 99, 132, 0.2)",
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            });
        ');
        echo '</div>';
        echo '</section>';

        echo '</div>';
    }

    public function render_sales_trends() {
        $this->maybe_log_event( 'Visited Sales Trends' );

        if ( isset( $_GET['_wpnonce'] ) ) {
            check_admin_referer( 'sales_trends_filter_nonce' );
        }

        $from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        $to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

        $from_date_input = $from ? gmdate( 'Y-m-d', strtotime( $from ) ) : null;
        $to_date_input   = $to ? gmdate( 'Y-m-d', strtotime( $to ) ) : null;

        $from_date = $from_date_input ?: gmdate( 'Y-m-01', strtotime( '-11 months' ) );
        $to_date   = $to_date_input ?: gmdate( 'Y-m-t' );

        echo '<div class="wrap shopanalytics-dashboard">';

        echo '<div class="shopanalytics-dashboard__header">';
        echo '<h1 class="shopanalytics-dashboard__title">' . esc_html__( 'Sales Trends', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';
        echo '<p class="shopanalytics-dashboard__subtitle">' . esc_html__( 'Keep tabs on revenue, order volumes, and your strongest product categories over time.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';

        echo '<div class="shopanalytics-dashboard__filters">';
        echo '<form method="get" class="shopanalytics-filter">';
        echo '<input type="hidden" name="page" value="shopanalytics-sales" />';
        wp_nonce_field( 'sales_trends_filter_nonce' );
        echo '<div class="shopanalytics-filter__row">';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="shopanalytics-sales-from" class="shopanalytics-filter__label">' . esc_html__( 'From', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="shopanalytics-sales-from" class="shopanalytics-filter__input" type="date" name="from" value="' . esc_attr( $from ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="shopanalytics-sales-to" class="shopanalytics-filter__label">' . esc_html__( 'To', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="shopanalytics-sales-to" class="shopanalytics-filter__input" type="date" name="to" value="' . esc_attr( $to ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        if ( $from || $to ) {
            $reset_url = remove_query_arg( [ 'from', 'to', '_wpnonce' ] );
            echo '<a class="button shopanalytics-filter__reset" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset', 'shopanalytics-lite-customer-sales-insights' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Note:', 'shopanalytics-lite-customer-sales-insights' ) . '</strong> ' . esc_html__( 'Charts are disabled because the "Lightweight Charts" option is enabled. Disable it in Settings to view visualizations.', 'shopanalytics-lite-customer-sales-insights' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $timezone         = wp_timezone();
        $start            = new DateTimeImmutable( $from_date, $timezone );
        $end              = new DateTimeImmutable( $to_date, $timezone );
        $monthly_orders   = [];
        $monthly_revenue  = [];
        $ordered_labels   = [];

        $cursor = $start;
        while ( $cursor <= $end ) {
            $label                     = wp_date( 'M Y', $cursor->getTimestamp(), $timezone );
            $monthly_orders[ $label ]  = 0;
            $monthly_revenue[ $label ] = 0;
            $ordered_labels[]          = $label;
            $cursor                    = $cursor->modify( 'first day of next month' );
        }

        $args           = shopanalytics_build_order_query_args( $from_date, $to_date );
        $args['return'] = 'objects';
        $orders         = wc_get_orders( $args );

        $category_revenue    = [];
        $category_quantities = [];

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            $date_created = $order->get_date_created();
            if ( ! $date_created ) {
                continue;
            }

            $label = wp_date( 'M Y', $date_created->getTimestamp(), $timezone );

            if ( ! isset( $monthly_orders[ $label ] ) ) {
                $monthly_orders[ $label ]  = 0;
                $monthly_revenue[ $label ] = 0;
                $ordered_labels[]          = $label;
            }

            $monthly_orders[ $label ]++;
            $monthly_revenue[ $label ] += (float) $order->get_total();

            foreach ( $order->get_items() as $item ) {
                if ( ! is_callable( [ $item, 'get_product_id' ] ) ) {
                    continue;
                }

                $product_id = $item->get_product_id();
                $quantity   = (float) $item->get_quantity();
                $line_total = (float) $item->get_total();

                $categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
                if ( is_wp_error( $categories ) || empty( $categories ) ) {
                    $categories = [ __( 'Uncategorized', 'shopanalytics-lite-customer-sales-insights' ) ];
                }

                foreach ( $categories as $category ) {
                    if ( ! isset( $category_revenue[ $category ] ) ) {
                        $category_revenue[ $category ]    = 0;
                        $category_quantities[ $category ] = 0;
                    }

                    $category_revenue[ $category ]    += $line_total;
                    $category_quantities[ $category ] += $quantity;
                }
            }
        }

        if ( ! empty( $ordered_labels ) ) {
            $ordered_labels = array_values( array_unique( $ordered_labels ) );
            $ordered_orders = [];
            $ordered_rev    = [];

            foreach ( $ordered_labels as $label ) {
                $ordered_orders[ $label ] = isset( $monthly_orders[ $label ] ) ? $monthly_orders[ $label ] : 0;
                $ordered_rev[ $label ]    = isset( $monthly_revenue[ $label ] ) ? $monthly_revenue[ $label ] : 0;
            }

            $monthly_orders  = $ordered_orders;
            $monthly_revenue = $ordered_rev;
        }

        if ( ! empty( $category_revenue ) ) {
            arsort( $category_revenue );
            $ordered_quantities = [];

            foreach ( array_keys( $category_revenue ) as $category ) {
                $ordered_quantities[ $category ] = isset( $category_quantities[ $category ] ) ? $category_quantities[ $category ] : 0;
            }

            $category_quantities = $ordered_quantities;
        }

        $has_sales_data = array_sum( $monthly_orders ) > 0 || array_sum( $monthly_revenue ) > 0;

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '📈 Revenue & Orders', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Compare revenue trends against total orders for the selected range.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( ! $has_sales_data ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'No sales activity found for this period.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        }

        echo '<canvas id="salesTrendsChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        echo '</div>';
        echo '</section>';

        $trend_chart_data = array(
            'labels'   => array_keys( $monthly_revenue ),
            'datasets' => array(
                array(
                    'label'           => __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ),
                    'data'            => array_values( $monthly_revenue ),
                    'borderColor'     => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill'            => true,
                    'tension'         => 0.35,
                    'yAxisID'         => 'y',
                ),
                array(
                    'label'           => __( 'Orders', 'shopanalytics-lite-customer-sales-insights' ),
                    'data'            => array_values( $monthly_orders ),
                    'borderColor'     => 'rgba(255, 159, 64, 1)',
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'fill'            => true,
                    'tension'         => 0.35,
                    'yAxisID'         => 'y1',
                ),
            ),
        );

        wp_add_inline_script( 'shopanalytics-chartjs', '
            document.addEventListener("DOMContentLoaded", function() {
                const ctx = document.getElementById("salesTrendsChart");
                if (!ctx) {
                    return;
                }

                new Chart(ctx.getContext("2d"), {
                    type: "line",
                    data: ' . wp_json_encode( $trend_chart_data ) . ',
                    options: {
                        responsive: true,
                        interaction: {
                            mode: "index",
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                type: "linear",
                                position: "left",
                                title: {
                                    display: true,
                                    text: "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '"
                                }
                            },
                            y1: {
                                type: "linear",
                                position: "right",
                                grid: {
                                    drawOnChartArea: false
                                },
                                title: {
                                    display: true,
                                    text: "' . esc_js( __( 'Orders', 'shopanalytics-lite-customer-sales-insights' ) ) . '"
                                }
                            }
                        }
                    }
                });
            });
        ' );

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '🗂 Revenue by Product Category', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Toggle between revenue and volume to discover which categories lead.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( empty( $category_revenue ) ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'No product category sales were recorded for this period.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        } else {
            echo '<div class="shopanalytics-toggle-group">';
            echo '<label><input type="radio" name="sales-cat-toggle" value="revenue" checked> ' . esc_html__( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
            echo '<label><input type="radio" name="sales-cat-toggle" value="quantity"> ' . esc_html__( 'Quantity Sold', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
            echo '</div>';
            echo '<canvas id="categorySalesChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        }

        echo '</div>';
        echo '</section>';

        if ( ! empty( $category_revenue ) ) {
            $category_chart_data = array(
                'labels'                => array_keys( $category_revenue ),
                'categoryRevenueData'   => array_values( $category_revenue ),
                'categoryQuantityData'  => array_values( $category_quantities ),
            );

            wp_add_inline_script( 'shopanalytics-chartjs', '
                document.addEventListener("DOMContentLoaded", function() {
                    const canvas = document.getElementById("categorySalesChart");
                    if (!canvas) {
                        return;
                    }

                    const chartData = ' . wp_json_encode( $category_chart_data ) . ';
                    const ctx = canvas.getContext("2d");

                    const categoryChart = new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: "' . esc_js( __( 'Revenue by Category', 'shopanalytics-lite-customer-sales-insights' ) ) . '",
                                data: chartData.categoryRevenueData,
                                backgroundColor: "rgba(153, 102, 255, 0.6)"
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '"
                                    }
                                }
                            }
                        }
                    });

                    document.querySelectorAll("input[name=\\"sales-cat-toggle\\"]").forEach(function (el) {
                        el.addEventListener("change", function() {
                            const isQuantity = this.value === "quantity";
                            categoryChart.data.datasets[0].label = isQuantity ? "' . esc_js( __( 'Quantity Sold by Category', 'shopanalytics-lite-customer-sales-insights' ) ) . '" : "' . esc_js( __( 'Revenue by Category', 'shopanalytics-lite-customer-sales-insights' ) ) . '";
                            categoryChart.data.datasets[0].data = isQuantity ? chartData.categoryQuantityData : chartData.categoryRevenueData;
                            categoryChart.options.scales.y.title.text = isQuantity ? "' . esc_js( __( 'Quantity', 'shopanalytics-lite-customer-sales-insights' ) ) . '" : "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '";
                            categoryChart.update();
                        });
                    });
                });
            ' );
        }

        echo '</div>';
    }

    public function render_order_trends() {
        $this->maybe_log_event( 'Visited Order Trends' );

        if ( isset( $_GET['_wpnonce'] ) ) {
            check_admin_referer( 'order_trends_filter_nonce' );
        }

        $from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        $to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

        $from_date_input = $from ? gmdate( 'Y-m-d', strtotime( $from ) ) : null;
        $to_date_input   = $to ? gmdate( 'Y-m-d', strtotime( $to ) ) : null;

        $from_date = $from_date_input ?: gmdate( 'Y-m-01', strtotime( '-11 months' ) );
        $to_date   = $to_date_input ?: gmdate( 'Y-m-t' );

        echo '<div class="wrap shopanalytics-dashboard">';

        echo '<div class="shopanalytics-dashboard__header">';
        echo '<h1 class="shopanalytics-dashboard__title">' . esc_html__( 'Order Trends', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';
        echo '<p class="shopanalytics-dashboard__subtitle">' . esc_html__( 'Monitor order momentum and see how many customers come back for more.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';

        echo '<div class="shopanalytics-dashboard__filters">';
        echo '<form method="get" class="shopanalytics-filter">';
        echo '<input type="hidden" name="page" value="shopanalytics-order-trends" />';
        wp_nonce_field( 'order_trends_filter_nonce' );
        echo '<div class="shopanalytics-filter__row">';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="shopanalytics-orders-from" class="shopanalytics-filter__label">' . esc_html__( 'From', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="shopanalytics-orders-from" class="shopanalytics-filter__input" type="date" name="from" value="' . esc_attr( $from ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="shopanalytics-orders-to" class="shopanalytics-filter__label">' . esc_html__( 'To', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="shopanalytics-orders-to" class="shopanalytics-filter__input" type="date" name="to" value="' . esc_attr( $to ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        if ( $from || $to ) {
            $reset_url = remove_query_arg( [ 'from', 'to', '_wpnonce' ] );
            echo '<a class="button shopanalytics-filter__reset" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset', 'shopanalytics-lite-customer-sales-insights' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Note:', 'shopanalytics-lite-customer-sales-insights' ) . '</strong> ' . esc_html__( 'Charts are disabled because the "Lightweight Charts" option is enabled. Disable it in Settings to view visualizations.', 'shopanalytics-lite-customer-sales-insights' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $timezone        = wp_timezone();
        $start           = new DateTimeImmutable( $from_date, $timezone );
        $end             = new DateTimeImmutable( $to_date, $timezone );
        $monthly_orders  = [];
        $monthly_revenue = [];
        $monthly_repeat  = [];
        $labels_order    = [];

        $cursor = $start;
        while ( $cursor <= $end ) {
            $label                    = wp_date( 'M Y', $cursor->getTimestamp(), $timezone );
            $monthly_orders[ $label ] = 0;
            $monthly_revenue[ $label ] = 0;
            $monthly_repeat[ $label ] = 0;
            $labels_order[]           = $label;
            $cursor                   = $cursor->modify( 'first day of next month' );
        }

        $args           = shopanalytics_build_order_query_args( $from_date, $to_date );
        $args['return'] = 'objects';
        $orders         = wc_get_orders( $args );

        $monthly_customer_orders = [];

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            $created = $order->get_date_created();
            if ( ! $created ) {
                continue;
            }

            $label = wp_date( 'M Y', $created->getTimestamp(), $timezone );

            if ( ! isset( $monthly_orders[ $label ] ) ) {
                $monthly_orders[ $label ]         = 0;
                $monthly_revenue[ $label ]        = 0;
                $monthly_repeat[ $label ]         = 0;
                $monthly_customer_orders[ $label ] = [];
                $labels_order[]                   = $label;
            }

            $monthly_orders[ $label ]++;
            $monthly_revenue[ $label ] += (float) $order->get_total();

            $customer_id = method_exists( $order, 'get_customer_id' ) ? $order->get_customer_id() : 0;
            if ( $customer_id ) {
                if ( ! isset( $monthly_customer_orders[ $label ][ $customer_id ] ) ) {
                    $monthly_customer_orders[ $label ][ $customer_id ] = 0;
                }

                $monthly_customer_orders[ $label ][ $customer_id ]++;

                if ( 2 === $monthly_customer_orders[ $label ][ $customer_id ] ) {
                    $monthly_repeat[ $label ]++;
                }
            }
        }

        if ( ! empty( $labels_order ) ) {
            $labels_order = array_values( array_unique( $labels_order ) );
            $ordered_orders = [];
            $ordered_revenue = [];
            $ordered_repeat = [];

            foreach ( $labels_order as $label ) {
                $ordered_orders[ $label ]  = isset( $monthly_orders[ $label ] ) ? $monthly_orders[ $label ] : 0;
                $ordered_revenue[ $label ] = isset( $monthly_revenue[ $label ] ) ? $monthly_revenue[ $label ] : 0;
                $ordered_repeat[ $label ]  = isset( $monthly_repeat[ $label ] ) ? $monthly_repeat[ $label ] : 0;
            }

            $monthly_orders  = $ordered_orders;
            $monthly_revenue = $ordered_revenue;
            $monthly_repeat  = $ordered_repeat;
        }

        $has_orders = array_sum( $monthly_orders ) > 0 || array_sum( $monthly_revenue ) > 0;

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '📊 Revenue vs. Orders', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'View revenue alongside total order counts for every month in range.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( ! $has_orders ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'No orders were found for this period.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        }

        echo '<div class="shopanalytics-toggle-group">';
        echo '<label><input type="radio" name="order-trend-toggle" value="combined" checked> ' . esc_html__( 'Show Both', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<label><input type="radio" name="order-trend-toggle" value="revenue"> ' . esc_html__( 'Revenue Only', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<label><input type="radio" name="order-trend-toggle" value="orders"> ' . esc_html__( 'Orders Only', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '</div>';

        echo '<canvas id="orderTrendsChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        echo '</div>';
        echo '</section>';

        $order_trend_data = array(
            'labels'  => array_keys( $monthly_orders ),
            'datasets' => array(
                array(
                    'key'             => 'revenue',
                    'label'           => __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ),
                    'data'            => array_values( $monthly_revenue ),
                    'borderColor'     => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill'            => true,
                    'tension'         => 0.35,
                    'yAxisID'         => 'y',
                ),
                array(
                    'key'             => 'orders',
                    'label'           => __( 'Orders', 'shopanalytics-lite-customer-sales-insights' ),
                    'data'            => array_values( $monthly_orders ),
                    'borderColor'     => 'rgba(153, 102, 255, 1)',
                    'backgroundColor' => 'rgba(153, 102, 255, 0.2)',
                    'fill'            => true,
                    'tension'         => 0.35,
                    'yAxisID'         => 'y1',
                ),
            ),
        );

        wp_add_inline_script( 'shopanalytics-chartjs', '
            document.addEventListener("DOMContentLoaded", function() {
                const canvas = document.getElementById("orderTrendsChart");
                if (!canvas) {
                    return;
                }

                const chartPayload = ' . wp_json_encode( $order_trend_data ) . ';
                const ctx = canvas.getContext("2d");

                const trendChart = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: chartPayload.labels,
                        datasets: chartPayload.datasets
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: "index",
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                type: "linear",
                                position: "left",
                                title: {
                                    display: true,
                                    text: "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '"
                                }
                            },
                            y1: {
                                type: "linear",
                                position: "right",
                                grid: {
                                    drawOnChartArea: false
                                },
                                title: {
                                    display: true,
                                    text: "' . esc_js( __( 'Orders', 'shopanalytics-lite-customer-sales-insights' ) ) . '"
                                }
                            }
                        }
                    }
                });

                const toggleInputs = document.querySelectorAll("input[name=\\"order-trend-toggle\\"]");
                toggleInputs.forEach(function (input) {
                    input.addEventListener("change", function() {
                        const mode = this.value;

                        trendChart.data.datasets.forEach(function (dataset) {
                            if (mode === \"combined\") {
                                dataset.hidden = false;
                            } else {
                                dataset.hidden = dataset.key !== mode;
                            }
                        });

                        trendChart.update();
                    });
                });
            });
        ' );

        $has_repeat_data = array_sum( $monthly_orders ) > 0;

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '🔁 Repeat Customer Trends', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Track how many unique shoppers purchased more than once each month.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( ! $has_repeat_data ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'Not enough order history to show repeat behavior.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        }

        echo '<canvas id="repeatOrdersChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        echo '</div>';
        echo '</section>';

        $repeat_chart_data = array(
            'labels'          => array_keys( $monthly_orders ),
            'totalOrders'     => array_values( $monthly_orders ),
            'repeatCustomers' => array_values( $monthly_repeat ),
        );

        wp_add_inline_script( 'shopanalytics-chartjs', '
            document.addEventListener("DOMContentLoaded", function() {
                const canvas = document.getElementById("repeatOrdersChart");
                if (!canvas) {
                    return;
                }

                const chartData = ' . wp_json_encode( $repeat_chart_data ) . ';
                const ctx = canvas.getContext("2d");

                new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: chartData.labels,
                        datasets: [
                            {
                                label: "' . esc_js( __( 'Total Orders', 'shopanalytics-lite-customer-sales-insights' ) ) . '",
                                data: chartData.totalOrders,
                                backgroundColor: "rgba(54, 162, 235, 0.7)"
                            },
                            {
                                label: "' . esc_js( __( 'Repeat Customers', 'shopanalytics-lite-customer-sales-insights' ) ) . '",
                                data: chartData.repeatCustomers,
                                backgroundColor: "rgba(255, 206, 86, 0.7)"
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        ' );

        echo '</div>';
    }

    public function render_customers() {
        $this->maybe_log_event( 'Visited Customers Insights' );
        echo '<div class="wrap"><h1><?php esc_html_e( \'👥 Customer Insights\', \'shopanalytics-lite\' ); ?></h1>';
 
        $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
        $to   = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';
 
        $from_date = $from ? gmdate('Y-m-d', strtotime($from)) : null;
        $to_date = $to ? gmdate('Y-m-d', strtotime($to)) : null;
        
        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>Note:</strong> Charts are disabled due to the "Lightweight Charts" setting. Disable this option in Settings to view visualizations.</p></div>';
            return;
        }
 
        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="shopanalytics-customers" />';
        echo '<label for="from">' . esc_html__( 'From:', 'shopanalytics-lite-customer-sales-insights' ) . ' </label>';
        echo '<input type="date" name="from" value="' . esc_attr($from) . '" />';
        echo '<label for="to"> ' . esc_html__( 'To:', 'shopanalytics-lite-customer-sales-insights' ) . ' </label>';
        echo '<input type="date" name="to" value="' . esc_attr($to) . '" />';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__( 'Filter', 'shopanalytics-lite-customer-sales-insights' ) . '" />';
        echo '</form>';
 
        $top_customers = $this->engine->get_top_customers( 100, $from_date, $to_date );

        // Optimize: Batch fetch all users to avoid N+1 query
        $emails = array_column( $top_customers, 'email' );
        $users_by_email = array();
        if ( ! empty( $emails ) ) {
            $users = get_users( array(
                'search' => '*',
                'search_columns' => array( 'user_email' ),
                'number' => 100,
            ) );
            foreach ( $users as $user ) {
                if ( in_array( $user->user_email, $emails, true ) ) {
                    $users_by_email[ $user->user_email ] = $user;
                }
            }
        }

        echo '<h2>' . esc_html__( '🏅 Top 100 Customers', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<table class="widefat fixed striped" style="margin-top:10px;">';
        echo '<thead><tr><th>' . esc_html__( 'Name', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Email', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Orders', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Total Spent', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Average Order', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'CLV', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Customer Since', 'shopanalytics-lite-customer-sales-insights' ) . '</th></tr></thead><tbody>';

        foreach ( $top_customers as $cust ) {
            $average_order = $cust['orders'] > 0 ? $cust['total_spent'] / $cust['orders'] : 0;
            $user = isset( $users_by_email[ $cust['email'] ] ) ? $users_by_email[ $cust['email'] ] : null;
        $registered = $user ? gmdate( 'Y-m-d', strtotime( $user->user_registered ) ) : 'N/A';
 
            echo '<tr>';
            echo '<td>' . esc_html( $cust['name'] ) . '</td>';
            echo '<td>' . esc_html( $cust['email'] ) . '</td>';
            echo '<td>' . esc_html( $cust['orders'] ) . '</td>';
            echo '<td>' . wp_kses_post( wc_price( $cust['total_spent'] ) ) . '</td>';
            echo '<td>' . wp_kses_post( wc_price( $average_order ) ) . '</td>';
            echo '<td>' . wp_kses_post( wc_price( $cust['clv'] ) ) . '</td>';
            echo '<td>' . esc_html( $registered ) . '</td>';
            echo '</tr>';
        }
 
        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_top_products() {
        $this->maybe_log_event( 'Visited Top Products' );
        if ( isset( $_GET['_wpnonce'] ) ) {
            check_admin_referer( 'top_products_filter_nonce' );
        }

        $from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
        $to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';

        if ( empty( $from ) ) {
            $from = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
        }
        if ( empty( $to ) ) {
            $to = gmdate( 'Y-m-d' );
        }

        $from_date = gmdate( 'Y-m-d', strtotime( $from ) );
        $to_date   = gmdate( 'Y-m-d', strtotime( $to ) );

        echo '<div class="wrap shopanalytics-dashboard">';

        echo '<div class="shopanalytics-dashboard__header">';
        echo '<h1 class="shopanalytics-dashboard__title">' . esc_html__( 'Top Products', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';
        echo '<p class="shopanalytics-dashboard__subtitle">' . esc_html__( 'See which products are winning on revenue and volume, then drill into the details.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';

        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Note:', 'shopanalytics-lite-customer-sales-insights' ) . '</strong> ' . esc_html__( 'Charts are disabled because the "Lightweight Charts" option is enabled. Disable it in Settings to view visualizations.', 'shopanalytics-lite-customer-sales-insights' ) . '</p></div>';
        }

        echo '<div class="shopanalytics-dashboard__filters">';
        echo '<form method="get" class="shopanalytics-filter">';
        echo '<input type="hidden" name="page" value="shopanalytics-products" />';
        wp_nonce_field( 'top_products_filter_nonce' );
        echo '<div class="shopanalytics-filter__row">';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="top-products-from" class="shopanalytics-filter__label">' . esc_html__( 'From', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="top-products-from" class="shopanalytics-filter__input" type="date" name="from" value="' . esc_attr( $from ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="top-products-to" class="shopanalytics-filter__label">' . esc_html__( 'To', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="top-products-to" class="shopanalytics-filter__input" type="date" name="to" value="' . esc_attr( $to ) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        if ( isset( $_GET['from'] ) || isset( $_GET['to'] ) ) {
            $reset_url = remove_query_arg( [ 'from', 'to', '_wpnonce' ] );
            echo '<a class="button shopanalytics-filter__reset" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset', 'shopanalytics-lite-customer-sales-insights' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        $args           = shopanalytics_build_order_query_args( $from_date, $to_date );
        $args['return'] = 'objects';
        $orders         = wc_get_orders( $args );

        $product_sales = [];

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            foreach ( $order->get_items() as $item ) {
                if ( ! is_callable( [ $item, 'get_product_id' ] ) ) {
                    continue;
                }

                $product_id   = $item->get_product_id();
                $name         = $item->get_name();
                $quantity     = (float) $item->get_quantity();
                $revenue      = (float) $item->get_total();

                $product_key = $product_id ?: md5( $name );

                if ( ! isset( $product_sales[ $product_key ] ) ) {
                    $product_sales[ $product_key ] = [
                        'id'      => $product_id,
                        'name'    => $name,
                        'qty'     => 0,
                        'revenue' => 0,
                    ];
                }

                $product_sales[ $product_key ]['qty']     += $quantity;
                $product_sales[ $product_key ]['revenue'] += $revenue;
            }
        }

        uasort( $product_sales, function( $a, $b ) {
            return $b['revenue'] <=> $a['revenue'];
        } );

        $top_products_table = array_slice( $product_sales, 0, 50 );
        $top_products_chart = array_slice( $product_sales, 0, 10 );

        $has_data = ! empty( $top_products_table );

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '📦 Top Products By Revenue', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Switch between revenue and quantity to understand what is moving fastest.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( ! $has_data ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'No product sales were recorded during this period.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        } elseif ( ! $use_light_charts ) {
            echo '<div class="shopanalytics-toggle-group">';
            echo '<label><input type="radio" name="top-products-toggle" value="revenue" checked> ' . esc_html__( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
            echo '<label><input type="radio" name="top-products-toggle" value="quantity"> ' . esc_html__( 'Quantity Sold', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
            echo '</div>';
            echo '<canvas id="topProductsChart" class="shopanalytics-panel__chart" height="60"></canvas>';
        }

        echo '</div>';
        echo '</section>';

        if ( $has_data && ! $use_light_charts ) {
            $chart_payload = [
                'labels'           => array_map( function( $product ) {
                    return wp_strip_all_tags( $product['name'] );
                }, $top_products_chart ),
                'revenueData'      => array_map( function( $product ) {
                    return $product['revenue'];
                }, $top_products_chart ),
                'quantityData'     => array_map( function( $product ) {
                    return $product['qty'];
                }, $top_products_chart ),
            ];

            wp_add_inline_script( 'shopanalytics-chartjs', '
                document.addEventListener("DOMContentLoaded", function() {
                    const canvas = document.getElementById("topProductsChart");
                    if (!canvas) {
                        return;
                    }

                    const chartData = ' . wp_json_encode( $chart_payload ) . ';
                    const ctx = canvas.getContext("2d");

                    const topProductsChart = new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '",
                                data: chartData.revenueData,
                                backgroundColor: "rgba(54, 162, 235, 0.6)"
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '"
                                    }
                                }
                            }
                        }
                    });

                    document.querySelectorAll("input[name=\\"top-products-toggle\\"]").forEach(function (input) {
                        input.addEventListener("change", function() {
                            const useQuantity = this.value === "quantity";
                            topProductsChart.data.datasets[0].label = useQuantity ? "' . esc_js( __( 'Quantity Sold', 'shopanalytics-lite-customer-sales-insights' ) ) . '" : "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '";
                            topProductsChart.data.datasets[0].data = useQuantity ? chartData.quantityData : chartData.revenueData;
                            topProductsChart.options.scales.y.title.text = useQuantity ? "' . esc_js( __( 'Quantity', 'shopanalytics-lite-customer-sales-insights' ) ) . '" : "' . esc_js( __( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) ) . '";
                            topProductsChart.update();
                        });
                    });
                });
            ' );
        }

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( '🏆 Detailed Product Leaderboard', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Revenue, quantity, and average order value for the top 50 products.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        if ( ! $has_data ) {
            echo '<p class="shopanalytics-empty">' . esc_html__( 'There are no product sales within the selected filters.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        } else {
            echo '<div class="shopanalytics-table-wrapper">';
            echo '<table class="widefat fixed striped shopanalytics-table">';
            echo '<thead><tr><th>' . esc_html__( 'Product', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Quantity Sold', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Revenue', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Avg. Order Value', 'shopanalytics-lite-customer-sales-insights' ) . '</th></tr></thead><tbody>';

            foreach ( $top_products_table as $product ) {
                $avg = $product['qty'] > 0 ? $product['revenue'] / $product['qty'] : 0;
                $name = $product['name'];

                if ( ! empty( $product['id'] ) ) {
                    $edit_link = get_edit_post_link( $product['id'] );
                    if ( $edit_link ) {
                        $name = '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $product['name'] ) . '</a>';
                    } else {
                        $name = esc_html( $product['name'] );
                    }
                } else {
                    $name = esc_html( $product['name'] );
                }

                echo '<tr>';
                echo '<td>' . $name . '</td>';
                echo '<td>' . esc_html( number_format_i18n( $product['qty'] ) ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $product['revenue'] ) ) . '</td>';
                echo '<td>' . wp_kses_post( wc_price( $avg ) ) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';

        echo '</div>';
    }

    public function render_reports() {
        $this->maybe_log_event( 'Visited Reports (Log Viewer)' );
        if ( isset($_GET['export_logs']) && current_user_can('manage_woocommerce') ) {
            // Check nonce for security
            check_admin_referer('export_logs_nonce');
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'shopanalytics_logs';

            // Build WHERE conditions properly
            $where_conditions = array( '1=1' );
            $where_values = array();

            if ( isset($_GET['log_user_id']) && $_GET['log_user_id'] !== '' ) {
                $user_id = intval(wp_unslash($_GET['log_user_id']));
                $where_conditions[] = 'user_id = %d';
                $where_values[] = $user_id;
            }
            if ( isset($_GET['log_keyword']) && $_GET['log_keyword'] !== '' ) {
                $keyword = sanitize_text_field(wp_unslash($_GET['log_keyword']));
                $where_conditions[] = 'event LIKE %s';
                $where_values[] = '%' . $wpdb->esc_like($keyword) . '%';
            }

            $where_clause = implode( ' AND ', $where_conditions );
            $cache_key = 'shopanalytics_logs_export_' . md5( serialize( $where_values ) );
            $logs = wp_cache_get($cache_key, 'shopanalytics');

            if (false === $logs) {
                if ( ! empty( $where_values ) ) {
                    $query = $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}shopanalytics_logs WHERE $where_clause ORDER BY created_at DESC",
                        $where_values
                    );
                } else {
                    $query = "SELECT * FROM {$wpdb->prefix}shopanalytics_logs WHERE 1=1 ORDER BY created_at DESC";
                }
                $logs = $wpdb->get_results( $query );
                wp_cache_set($cache_key, $logs, 'shopanalytics', 300);
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="shopanalytics-logs.csv"');
            echo "Time,User ID,Event\n";
            foreach ( $logs as $log ) {
                echo sprintf("%s,%s,%s\n", 
                    esc_html($log->created_at), 
                    esc_html($log->user_id), 
                    esc_html($log->event)
                );
            }
            exit;
        }
        if ( isset($_POST['clear_logs']) && current_user_can('manage_woocommerce') ) {
            // Check nonce for security
            check_admin_referer('clear_logs_nonce');
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'shopanalytics_logs';
            $wpdb->query( "DELETE FROM {$wpdb->prefix}shopanalytics_logs WHERE 1=1" );
            wp_cache_flush_group('shopanalytics');
            echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'All logs have been cleared.', 'shopanalytics-lite-customer-sales-insights' ) . '</p></div>';
        }
        echo '<div class="wrap shopanalytics-dashboard">';

        echo '<div class="shopanalytics-dashboard__header">';
        echo '<h1 class="shopanalytics-dashboard__title">' . esc_html__( 'Reports – Log Viewer', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';
        echo '<p class="shopanalytics-dashboard__subtitle">' . esc_html__( 'Filter, export, or clear usage logs captured by ShopAnalytics.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';

        $filter_user_id = isset($_GET['log_user_id']) ? intval(wp_unslash($_GET['log_user_id'])) : '';
        $filter_keyword = isset($_GET['log_keyword']) ? sanitize_text_field(wp_unslash($_GET['log_keyword'])) : '';

        echo '<div class="shopanalytics-dashboard__filters">';
        echo '<form method="get" class="shopanalytics-filter">';
        echo '<input type="hidden" name="page" value="shopanalytics-reports" />';
        wp_nonce_field('export_logs_nonce');
        echo '<div class="shopanalytics-filter__row">';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="log_user_id" class="shopanalytics-filter__label">' . esc_html__( 'User ID', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="log_user_id" class="shopanalytics-filter__input" type="number" name="log_user_id" value="' . esc_attr($filter_user_id) . '" min="0" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__field">';
        echo '<label for="log_keyword" class="shopanalytics-filter__label">' . esc_html__( 'Keyword', 'shopanalytics-lite-customer-sales-insights' ) . '</label>';
        echo '<input id="log_keyword" class="shopanalytics-filter__input" type="text" name="log_keyword" value="' . esc_attr($filter_keyword) . '" />';
        echo '</div>';
        echo '<div class="shopanalytics-filter__actions">';
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        if ( $filter_user_id || $filter_keyword ) {
            $reset_url = remove_query_arg( [ 'log_user_id', 'log_keyword', '_wpnonce', 'export_logs' ] );
            echo '<a class="button shopanalytics-filter__reset" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset', 'shopanalytics-lite-customer-sales-insights' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="shopanalytics-filter__row">';
        echo '<div class="shopanalytics-filter__actions">';
        echo '<button type="submit" name="export_logs" class="button">' . esc_html__( 'Export to CSV', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<form method="post" class="shopanalytics-panel" style="padding: 16px;">';
        echo '<div class="shopanalytics-panel__header" style="margin-bottom:12px;">';
        echo '<h2 class="shopanalytics-panel__title" style="margin:0;font-size:16px;">' . esc_html__( 'Maintenance', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '</div>';
        echo '<p class="shopanalytics-panel__subtitle" style="margin:0 0 12px;">' . esc_html__( 'Clear all log records. This action cannot be undone.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '<button type="submit" name="clear_logs" class="button button-secondary" onclick="return confirm(\'' . esc_attr__( 'Are you sure you want to delete all logs?', 'shopanalytics-lite-customer-sales-insights' ) . '\')">' . esc_html__( 'Clear Logs Now', 'shopanalytics-lite-customer-sales-insights' ) . '</button>';
        wp_nonce_field('clear_logs_nonce');
        echo '</form>';
 
        global $wpdb;
        $table_name = $wpdb->prefix . 'shopanalytics_logs';

        // Build WHERE conditions properly
        $where_conditions = array( '1=1' );
        $where_values = array();

        if ( $filter_user_id ) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $filter_user_id;
        }
        if ( $filter_keyword ) {
            $where_conditions[] = 'event LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($filter_keyword) . '%';
        }

        $where_clause = implode( ' AND ', $where_conditions );
        $cache_key = 'shopanalytics_logs_view_' . md5( serialize( $where_values ) );
        $logs = wp_cache_get($cache_key, 'shopanalytics');

        if (false === $logs) {
            if ( ! empty( $where_values ) ) {
                $query = $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}shopanalytics_logs WHERE $where_clause ORDER BY created_at DESC LIMIT 100",
                    $where_values
                );
            } else {
                $query = "SELECT * FROM {$wpdb->prefix}shopanalytics_logs WHERE 1=1 ORDER BY created_at DESC LIMIT 100";
            }
            $logs = $wpdb->get_results( $query );
            wp_cache_set($cache_key, $logs, 'shopanalytics', 300);
        }
 
        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'No logs available.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        } else {
            echo '<table class="widefat fixed striped" style="margin-top: 20px;">';
            echo '<thead><tr><th>' . esc_html__( 'Time', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'User ID', 'shopanalytics-lite-customer-sales-insights' ) . '</th><th>' . esc_html__( 'Event', 'shopanalytics-lite-customer-sales-insights' ) . '</th></tr></thead><tbody>';
            foreach ( $logs as $log ) {
                echo '<tr>';
                echo '<td>' . esc_html( $log->created_at ) . '</td>';
                echo '<td>' . esc_html( $log->user_id ) . '</td>';
                echo '<td>' . esc_html( $log->event ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
 
        echo '</div>';
    }

    public function render_settings() {
        $this->maybe_log_event( 'Visited Settings' );
        echo '<div class="wrap shopanalytics-dashboard">';

        echo '<div class="shopanalytics-dashboard__header">';
        echo '<h1 class="shopanalytics-dashboard__title">' . esc_html__( 'Settings', 'shopanalytics-lite-customer-sales-insights' ) . '</h1>';
        echo '<p class="shopanalytics-dashboard__subtitle">' . esc_html__( 'Fine-tune how ShopAnalytics captures data and renders reports.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';

        echo '<section class="shopanalytics-panel">';
        echo '<div class="shopanalytics-panel__header">';
        echo '<h2 class="shopanalytics-panel__title">' . esc_html__( 'General Preferences', 'shopanalytics-lite-customer-sales-insights' ) . '</h2>';
        echo '<p class="shopanalytics-panel__subtitle">' . esc_html__( 'Toggle data logging, chart performance modes, and housekeeping rules.', 'shopanalytics-lite-customer-sales-insights' ) . '</p>';
        echo '</div>';
        echo '<div class="shopanalytics-panel__body">';

        echo '<form method="post" action="options.php" class="shopanalytics-settings">';
        settings_fields( 'shopanalytics_settings_group' );

        ob_start();
        do_settings_sections( 'shopanalytics-lite-customer-sales-insights' );
        $additional_sections = ob_get_clean();

        echo '<div class="shopanalytics-settings__grid">';

        echo '<label class="shopanalytics-settings__field">';
        echo '<span class="shopanalytics-settings__label">' . esc_html__( 'Enable Data Logging', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '<span class="shopanalytics-settings__description">' . esc_html__( 'Capture a lightweight activity log to assist with debugging and audits.', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '<span class="shopanalytics-settings__control">';
        echo '<input type="hidden" name="shopanalytics_enable_logging" value="0" />';
        echo '<input type="checkbox" name="shopanalytics_enable_logging" value="1" ' . checked( 1, (int) get_option( 'shopanalytics_enable_logging' ), false ) . ' />';
        echo '</span>';
        echo '</label>';

        echo '<label class="shopanalytics-settings__field">';
        echo '<span class="shopanalytics-settings__label">' . esc_html__( 'Use Lightweight Charts', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '<span class="shopanalytics-settings__description">' . esc_html__( 'Disables rich visualizations across the plugin in favor of minimal lists.', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '<span class="shopanalytics-settings__control">';
        echo '<input type="hidden" name="shopanalytics_light_charts" value="0" />';
        echo '<input type="checkbox" name="shopanalytics_light_charts" value="1" ' . checked( 1, (int) get_option( 'shopanalytics_light_charts' ), false ) . ' />';
        echo '</span>';
        echo '</label>';

        echo '<label class="shopanalytics-settings__field">';
        echo '<span class="shopanalytics-settings__label">' . esc_html__( 'Log Retention Period', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '<span class="shopanalytics-settings__description">' . esc_html__( 'Automatically purge stored logs after the selected number of days.', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '<span class="shopanalytics-settings__control">';
        echo '<input type="number" class="small-text" name="shopanalytics_log_retention_days" value="' . esc_attr( get_option( 'shopanalytics_log_retention_days', 90 ) ) . '" min="1" step="1" />';
        echo '<span class="shopanalytics-settings__suffix">' . esc_html__( 'days', 'shopanalytics-lite-customer-sales-insights' ) . '</span>';
        echo '</span>';
        echo '</label>';

        echo '</div>';

        if ( ! empty( $additional_sections ) ) {
            echo '<div class="shopanalytics-settings__legacy">' . $additional_sections . '</div>';
        }

        submit_button();

        echo '</form>';
        echo '</div>';
        echo '</section>';

        echo '</div>';
    }
}

new ShopAnalytics_Admin_UI();
