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
        add_action( 'admin_init', [ $this, 'cleanup_old_logs' ] );
    }

    public function register_settings() {
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_enable_logging' );
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_enable_pro_previews' );
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_light_charts' );
        register_setting( 'shopanalytics_settings_group', 'shopanalytics_log_retention_days' );
    }
    
    public function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'shopanalytics_logs';
        $retention_days = intval( get_option( 'shopanalytics_log_retention_days', 90 ) );
        $threshold_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE created_at < %s", $threshold_date ) );
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
        }
    }

    public function register_menu() {
        // Top-level menu
        add_menu_page(
            'ShopAnalytics Lite',
            'ShopAnalytics',
            'manage_woocommerce',
            'shopanalytics-lite',
            [ $this, 'render_dashboard' ],
            'dashicons-chart-line',
            56
        );

        // Submenus
        add_submenu_page(
            'shopanalytics-lite',
            'Dashboard',
            'Dashboard',
            'manage_woocommerce',
            'shopanalytics-lite',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'shopanalytics-lite',
            'Sales Trends',
            'Sales Trends',
            'manage_woocommerce',
            'shopanalytics-sales',
            [ $this, 'render_sales_trends' ]
        );

        add_submenu_page(
            'shopanalytics-lite',
            'Order Trends',
            'Order Trends',
            'manage_woocommerce',
            'shopanalytics-order-trends',
            [ $this, 'render_order_trends' ]
        );

        add_submenu_page(
            'shopanalytics-lite',
            'Customers',
            'Customers',
            'manage_woocommerce',
            'shopanalytics-customers',
            [ $this, 'render_customers' ]
        );

        add_submenu_page(
            'shopanalytics-lite',
            'Top Products',
            'Top Products',
            'manage_woocommerce',
            'shopanalytics-products',
            [ $this, 'render_top_products' ]
        );

        add_submenu_page(
            'shopanalytics-lite',
            'Reports',
            'Reports',
            'manage_woocommerce',
            'shopanalytics-reports',
            [ $this, 'render_reports' ]
        );

        add_submenu_page(
            'shopanalytics-lite',
            'Settings',
            'Settings',
            'manage_woocommerce',
            'shopanalytics-settings',
            [ $this, 'render_settings' ]
        );
    }

    public function render_dashboard() {
        $this->maybe_log_event( 'Visited Dashboard' );
        if ( isset($_POST['export_csv']) && current_user_can('manage_woocommerce') && shopanalytics_fs()->can_use_premium_code() ) {
            $export_from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
            $export_to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : '';
            $export_from_date = $export_from ? date('Y-m-d', strtotime($export_from)) : null;
            $export_to_date = $export_to ? date('Y-m-d', strtotime($export_to)) : null;

            $export_engine = new ShopAnalytics_Engine();
            $export_customers = $export_engine->get_top_customers( 100, $export_from_date, $export_to_date );

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="top-customers.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Orders', 'Total Spent']);

            foreach ( $export_customers as $cust ) {
                fputcsv($output, [$cust['name'], $cust['email'], $cust['orders'], $cust['total_spent']]);
            }

            fclose($output);
            $this->maybe_log_event( 'Exported Top Customers CSV' );
            exit;
        }

        if ( isset($_POST['export_metrics']) && current_user_can('manage_woocommerce') && shopanalytics_fs()->can_use_premium_code() ) {
            $export_from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
            $export_to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : '';
            $export_from_date = $export_from ? date('Y-m-d', strtotime($export_from)) : null;
            $export_to_date = $export_to ? date('Y-m-d', strtotime($export_to)) : null;

            $export_engine = new ShopAnalytics_Engine();

            $metrics = [
                'Total Revenue' => $export_engine->get_total_revenue( $export_from_date, $export_to_date ),
                'Total Orders' => $export_engine->get_total_orders( $export_from_date, $export_to_date ),
                'Average Order Value' => $export_engine->get_average_order_value( $export_from_date, $export_to_date ),
                'Repeat Purchase Rate (%)' => $export_engine->get_repeat_purchase_rate( $export_from_date, $export_to_date ),
            ];

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="key-metrics.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Metric', 'Value']);
            foreach ( $metrics as $label => $value ) {
                fputcsv($output, [$label, $value]);
            }
            fclose($output);
            $this->maybe_log_event( 'Exported Key Metrics CSV' );
            exit;
        }

        if ( isset($_POST['export_orders']) && current_user_can('manage_woocommerce') && shopanalytics_fs()->can_use_premium_code() ) {
            $export_from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
            $export_to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : '';
            $export_from_date = $export_from ? date('Y-m-d', strtotime($export_from)) : null;
            $export_to_date = $export_to ? date('Y-m-d', strtotime($export_to)) : null;

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
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order ID', 'Customer Name', 'Customer Email', 'Total', 'Date']);

            foreach ( $orders as $order ) {
                fputcsv($output, [
                    $order->get_id(),
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    $order->get_billing_email(),
                    $order->get_total(),
                    $order->get_date_created()->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($output);
            $this->maybe_log_event( 'Exported Orders CSV' );
            exit;
        }

        echo '<div class="wrap">';
        echo '<style>
            .shopanalytics-export-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin: 20px 0;
                border-radius: 6px;
            }

            .shopanalytics-export-box h3 {
                margin-top: 0;
                font-size: 16px;
                font-weight: 600;
                color: #23282d;
            }

            .shopanalytics-export-box .button {
                margin-right: 10px;
                margin-top: 10px;
            }

            .shopanalytics-metrics ul {
                list-style: none;
                padding: 0;
            }

            .shopanalytics-metrics li {
                background: #f9f9f9;
                margin: 5px 0;
                padding: 10px 15px;
                border-left: 4px solid #007cba;
            }

            .shopanalytics-metrics strong {
                display: inline-block;
                width: 200px;
            }
        </style>';

        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';

        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="shopanalytics-lite" />';
        echo '<label for="from">From: </label>';
        echo '<input type="date" name="from" value="' . esc_attr($from) . '" />';
        echo '<label for="to"> To: </label>';
        echo '<input type="date" name="to" value="' . esc_attr($to) . '" />';
        echo '<input type="submit" class="button button-primary" value="Filter" />';
        echo '</form>';

        echo '<div class="shopanalytics-export-box">';
        echo '<h3>📤 Export Options <span style="color: #d54e21;">(Pro)</span></h3>';
        echo '<form method="post" style="margin-top:10px;">';
        echo '<input type="hidden" name="from" value="' . esc_attr($from) . '" />';
        echo '<input type="hidden" name="to" value="' . esc_attr($to) . '" />';
        echo '<input type="submit" name="export_csv" class="button" value="Export Top Customers to CSV (Pro)" />';
        echo '<input type="submit" name="export_metrics" class="button" value="Export Key Metrics to CSV (Pro)" />';
        echo '<input type="submit" name="export_orders" class="button" value="Export Orders to CSV (Pro)" />';
        echo '</form>';
        echo '</div>';

        if ( ! shopanalytics_fs()->can_use_premium_code() ) {
            echo '<div class="notice notice-info"><p>';
            echo 'Export features are available in the <strong>Pro version</strong>. ';
            //echo shopanalytics_fs()->get_upgrade_link_html( 'Upgrade now &rarr;' );
            echo '</p></div>';
        }

        $from_date = $from ? date('Y-m-d', strtotime($from)) : null;
        $to_date = $to ? date('Y-m-d', strtotime($to)) : null;
        
        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>Note:</strong> Charts are disabled due to the "Lightweight Charts" setting. Disable this option in Settings to view visualizations.</p></div>';
            return;
        }

        $total_revenue = $this->engine->get_total_revenue( $from_date, $to_date );
        $total_orders  = $this->engine->get_total_orders( $from_date, $to_date );
        $aov           = $this->engine->get_average_order_value( $from_date, $to_date );
        $repeat_rate   = $this->engine->get_repeat_purchase_rate( $from_date, $to_date );
        $top_customers = $this->engine->get_top_customers( 5, $from_date, $to_date );

        echo '<h2>📈 Key Metrics</h2>';
        echo '<div class="shopanalytics-metrics"><ul>';
        echo '<li><strong>Total Revenue:</strong> ' . wc_price( $total_revenue ) . '</li>';
        echo '<li><strong>Total Orders:</strong> ' . esc_html( $total_orders ) . '</li>';
        echo '<li><strong>Average Order Value:</strong> ' . wc_price( $aov ) . '</li>';
        echo '<li><strong>Repeat Purchase Rate:</strong> ' . round( $repeat_rate, 2 ) . '%</li>';
        echo '</ul></div>';

        echo '<h2 style="margin-top: 40px;">📦 Order & Repeat Customer Trends</h2>';
        echo '<canvas id="orderTrendChart" width="100%" height="60"></canvas>';

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
                            error_log( '[ShopAnalytics] Invalid "month_start" date: ' . print_r( $month_start, true ) );
                        }
                    }
                    if ( $month_end ) {
                        try {
                            $args['date_created_before'] = ( new \WC_DateTime( $month_end ) )->format( DATE_ATOM );
                        } catch ( Exception $e ) {
                            error_log( '[ShopAnalytics] Invalid "month_end" date: ' . print_r( $month_end, true ) );
                        }
                    }
                }
            $orders = wc_get_orders( $args );

            $monthly_orders[$current->format('M Y')] = count($orders);

            // Repeat customer count
            $repeat_customers = [];
            foreach ( $orders as $order_id ) {
                $order = wc_get_order($order_id);
                if ( ! $order || ! method_exists( $order, 'get_customer_id' ) ) {
                    continue;
                }
                $customer_id = $order->get_customer_id();
                if ( $customer_id && wc_get_customer_order_count($customer_id) > 1 ) {
                    $repeat_customers[$customer_id] = true;
                }
            }
            $monthly_repeat[$current->format('M Y')] = count($repeat_customers);

            $current->modify('+1 month');
        }

        // Output chart script
        echo '<script>
        const ctx2 = document.getElementById("orderTrendChart").getContext("2d");
        const orderTrendChart = new Chart(ctx2, {
            type: "bar",
            data: {
                labels: ' . json_encode(array_keys($monthly_orders)) . ',
                datasets: [
                    {
                        label: "Total Orders",
                        data: ' . json_encode(array_values($monthly_orders)) . ',
                        backgroundColor: "rgba(54, 162, 235, 0.6)"
                    },
                    {
                        label: "Repeat Customers",
                        data: ' . json_encode(array_values($monthly_repeat)) . ',
                        backgroundColor: "rgba(255, 206, 86, 0.6)"
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
        </script>';

        echo '<h2 style="margin-top: 40px;">💡 Revenue vs. AOV</h2>';
        echo '<canvas id="revenueAOVChart" width="100%" height="60"></canvas>';

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

        echo '<script>
        const ctx3 = document.getElementById("revenueAOVChart").getContext("2d");
        const revenueAOVChart = new Chart(ctx3, {
            type: "line",
            data: {
                labels: ' . json_encode(array_keys($monthly_revenue)) . ',
                datasets: [
                    {
                        label: "Revenue",
                        data: ' . json_encode(array_values($monthly_revenue)) . ',
                        borderColor: "rgba(75, 192, 192, 1)",
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        fill: true,
                        tension: 0.3,
                        yAxisID: "y"
                    },
                    {
                        label: "AOV",
                        data: ' . json_encode(array_values($monthly_aov)) . ',
                        borderColor: "rgba(255, 99, 132, 1)",
                        backgroundColor: "rgba(255, 99, 132, 0.2)",
                        fill: true,
                        tension: 0.3,
                        yAxisID: "y1"
                    }
                ]
            },
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
        </script>';

        echo '<h2 style="margin-top: 30px;">🏅 Top Customers</h2>';
        echo '<table class="widefat fixed striped" style="margin-top: 10px;">';
        echo '<thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Total Spent</th></tr></thead><tbody>';

        foreach ( $top_customers as $customer ) {
            echo '<tr>';
            echo '<td>' . esc_html( $customer['name'] ) . '</td>';
            echo '<td>' . esc_html( $customer['email'] ) . '</td>';
            echo '<td>' . esc_html( $customer['orders'] ) . '</td>';
            echo '<td>' . wc_price( $customer['total_spent'] ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        
        echo '<h2 style="margin-top:40px;">📊 Customer Segmentation</h2>';
        $segments = ['1 Order' => 0, '2-3 Orders' => 0, '4-5 Orders' => 0, '6+ Orders' => 0];
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
        
        echo '<canvas id="segmentChart" width="100%" height="50"></canvas>';
        echo '<canvas id="locationChart" width="100%" height="50" style="margin-top:30px;"></canvas>';
        echo '<canvas id="clvChart" width="100%" height="50" style="margin-top:30px;"></canvas>';
        
        echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    new Chart(document.getElementById("segmentChart").getContext("2d"), {
        type: "bar",
        data: {
            labels: ' . json_encode(array_keys($segments)) . ',
            datasets: [{
                label: "Customer Count",
                data: ' . json_encode(array_values($segments)) . ',
                backgroundColor: "rgba(54, 162, 235, 0.6)"
            }]
        }
    });
    
    new Chart(document.getElementById("locationChart").getContext("2d"), {
        type: "doughnut",
        data: {
            labels: ' . json_encode(array_keys($locations)) . ',
            datasets: [{
                label: "Customers by Country",
                data: ' . json_encode(array_values($locations)) . ',
                backgroundColor: [
                    "#36a2eb", "#ffcd56", "#4bc0c0", "#ff6384", "#9966ff", "#c9cbcf"
                ]
            }]
        }
    });
    
    new Chart(document.getElementById("clvChart").getContext("2d"), {
        type: "line",
        data: {
            labels: ' . json_encode(array_column($top_customers, 'name')) . ',
            datasets: [{
                label: "CLV",
                data: ' . json_encode(array_column($top_customers, 'total_spent')) . ',
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
</script>';
        
        echo '</div>';
    }

    public function render_sales_trends() {
        $this->maybe_log_event( 'Visited Sales Trends' );
        echo '<div class="wrap"><h1>📈 Sales Trends</h1>';
    
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
    
    $from_date = $from ? date('Y-m-d', strtotime($from)) : null;
    $to_date   = $to ? date('Y-m-d', strtotime($to)) : null;
    
    $use_light_charts = get_option( 'shopanalytics_light_charts' );
    if ( $use_light_charts ) {
        echo '<div class="notice notice-info"><p><strong>Note:</strong> Charts are disabled due to the "Lightweight Charts" setting. Disable this option in Settings to view visualizations.</p></div>';
        return;
    }
    
        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="shopanalytics-sales" />';
        echo '<label for="from">From: </label>';
        echo '<input type="date" name="from" value="' . esc_attr($from) . '" />';
        echo '<label for="to"> To: </label>';
        echo '<input type="date" name="to" value="' . esc_attr($to) . '" />';
        echo '<input type="submit" class="button button-primary" value="Filter" />';
        echo '</form>';
    
        $from_date = $from_date ?: date('Y-m-01', strtotime('-11 months'));
        $to_date   = $to_date ?: date('Y-m-t');
    
        $monthly_orders = [];
        $monthly_revenue = [];
    
        $current = new DateTime($from_date);
        $end = new DateTime($to_date);
    
        while ($current <= $end) {
            $month_start = $current->format('Y-m-01');
            $month_end   = $current->format('Y-m-t');
    
            $revenue = $this->engine->get_total_revenue($month_start, $month_end);
            $orders  = $this->engine->get_total_orders($month_start, $month_end);
    
            $monthly_revenue[$current->format('M Y')] = $revenue;
            $monthly_orders[$current->format('M Y')] = $orders;
    
            $current->modify('+1 month');
        }
    
        echo '<canvas id="salesTrendsChart" width="100%" height="60"></canvas>';
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById("salesTrendsChart").getContext("2d");
        new Chart(ctx, {
            type: "line",
            data: {
                labels: ' . json_encode(array_keys($monthly_revenue)) . ',
                datasets: [
                    {
                        label: "Revenue",
                        data: ' . json_encode(array_values($monthly_revenue)) . ',
                        borderColor: "rgba(75, 192, 192, 1)",
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        fill: true,
                        tension: 0.4,
                        yAxisID: "y"
                    },
                    {
                        label: "Orders",
                        data: ' . json_encode(array_values($monthly_orders)) . ',
                        borderColor: "rgba(255, 159, 64, 1)",
                        backgroundColor: "rgba(255, 159, 64, 0.2)",
                        fill: true,
                        tension: 0.4,
                        yAxisID: "y1"
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
                interaction: {
                    mode: "index",
                    intersect: false
                },
                scales: {
                    y: {
                        type: "linear",
                        position: "left",
                        title: {
                            display: true,
                            text: "Revenue"
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
                            text: "Orders"
                        }
                    }
                }
            }
        });
    });
    </script>';
        echo '</div>';

        $category_sales = $this->engine->get_sales_by_category( $from_date, $to_date );

        $category_quantities = [];

        foreach ( wc_get_orders(shopanalytics_build_order_query_args($from_date, $to_date)) as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) continue;

            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product_id();
                $product = wc_get_product( $product_id );
                if ( ! $product ) continue;

                $categories = wp_get_post_terms( $product_id, 'product_cat', ['fields' => 'names'] );
                $qty = $item->get_quantity();

                foreach ( $categories as $cat ) {
                    if ( ! isset( $category_quantities[ $cat ] ) ) {
                        $category_quantities[ $cat ] = 0;
                    }
                    $category_quantities[ $cat ] += $qty;
                }
            }
        }

        echo '<h2 style="margin-top: 40px;">🗂 Revenue by Product Category</h2>';
        echo '<p>';
        echo '<label><input type="radio" name="cat-toggle" value="revenue" checked> Revenue</label> ';
        echo '<label><input type="radio" name="cat-toggle" value="quantity"> Quantity Sold</label>';
        echo '</p>';
        echo '<canvas id="categorySalesChart" width="100%" height="60"></canvas>';

        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctxCat = document.getElementById("categorySalesChart").getContext("2d");

            const categoryRevenueData = ' . json_encode(array_values($category_sales)) . ';
            const categoryQuantityData = ' . json_encode(array_values($category_quantities)) . ';

            let categoryChart = new Chart(ctxCat, {
                type: "bar",
                data: {
                    labels: ' . json_encode(array_keys($category_sales)) . ',
                    datasets: [{
                        label: "Revenue by Category",
                        data: categoryRevenueData,
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
                                text: "Revenue"
                            }
                        }
                    }
                }
            });

            document.querySelectorAll("input[name=\'cat-toggle\']").forEach(el => {
                el.addEventListener("change", function() {
                    const type = this.value;
                    categoryChart.data.datasets[0].label = type === "quantity" ? "Quantity Sold by Category" : "Revenue by Category";
                    categoryChart.data.datasets[0].data = type === "quantity" ? categoryQuantityData : categoryRevenueData;
                    categoryChart.options.scales.y.title.text = type === "quantity" ? "Quantity" : "Revenue";
                    categoryChart.update();
                });
            });
        });
        </script>';

    }

    public function render_order_trends() {
        $this->maybe_log_event( 'Visited Order Trends' );
        echo '<div class="wrap"><h1>📊 Revenue vs. Order Trend</h1>';
    
        $from_date = date('Y-m-01', strtotime('-11 months'));
        $to_date = date('Y-m-t');
    
        $monthly_orders = [];
        $monthly_revenue = [];
    
        $current = new DateTime($from_date);
        $end = new DateTime($to_date);
    
        while ($current <= $end) {
            $month_start = $current->format('Y-m-01');
            $month_end = $current->format('Y-m-t');
    
            $revenue = $this->engine->get_total_revenue($month_start, $month_end);
            $orders = $this->engine->get_total_orders($month_start, $month_end);
    
            $monthly_revenue[$current->format('M Y')] = $revenue;
            $monthly_orders[$current->format('M Y')] = $orders;
    
            $current->modify('+1 month');
        }
    
        echo '<p>
            <label><input type="radio" name="trend-toggle" value="revenue" checked> Revenue</label>
            <label><input type="radio" name="trend-toggle" value="orders"> Orders</label>
        </p>';
        echo '<canvas id="trendChart" width="100%" height="60"></canvas>';
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById("trendChart").getContext("2d");
    
            const revenueData = ' . json_encode(array_values($monthly_revenue)) . ';
            const orderData = ' . json_encode(array_values($monthly_orders)) . ';
            const labels = ' . json_encode(array_keys($monthly_revenue)) . ';
    
            let trendChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: labels,
                    datasets: [{
                        label: "Revenue",
                        data: revenueData,
                        fill: true,
                        borderColor: "rgba(75, 192, 192, 1)",
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: "top" }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
    
            document.querySelectorAll("input[name=\'trend-toggle\']").forEach(el => {
                el.addEventListener("change", function() {
                    const type = this.value;
                    trendChart.data.datasets[0].label = type === "orders" ? "Orders" : "Revenue";
                    trendChart.data.datasets[0].data = type === "orders" ? orderData : revenueData;
                    trendChart.update();
                });
            });
        });
        </script>';
    
        echo '</div>';
    }

    public function render_customers() {
        $this->maybe_log_event( 'Visited Customers Insights' );
        echo '<div class="wrap"><h1>👥 Customer Insights</h1>';
 
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
 
        $from_date = $from ? date('Y-m-d', strtotime($from)) : null;
        $to_date   = $to ? date('Y-m-d', strtotime($to)) : null;
        
        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>Note:</strong> Charts are disabled due to the "Lightweight Charts" setting. Disable this option in Settings to view visualizations.</p></div>';
            return;
        }
 
        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="shopanalytics-customers" />';
        echo '<label for="from">From: </label>';
        echo '<input type="date" name="from" value="' . esc_attr($from) . '" />';
        echo '<label for="to"> To: </label>';
        echo '<input type="date" name="to" value="' . esc_attr($to) . '" />';
        echo '<input type="submit" class="button button-primary" value="Filter" />';
        echo '</form>';
 
        $top_customers = $this->engine->get_top_customers( 100, $from_date, $to_date );
 
        echo '<h2>🏅 Top 100 Customers</h2>';
        echo '<table class="widefat fixed striped" style="margin-top:10px;">';
        echo '<thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Total Spent</th><th>Average Order</th><th>CLV</th><th>Customer Since</th></tr></thead><tbody>';
 
        foreach ( $top_customers as $cust ) {
            $average_order = $cust['orders'] > 0 ? $cust['total_spent'] / $cust['orders'] : 0;
            $user = get_user_by( 'email', $cust['email'] );
            $registered = $user ? date( 'Y-m-d', strtotime( $user->user_registered ) ) : 'N/A';
 
            echo '<tr>';
            echo '<td>' . esc_html( $cust['name'] ) . '</td>';
            echo '<td>' . esc_html( $cust['email'] ) . '</td>';
            echo '<td>' . esc_html( $cust['orders'] ) . '</td>';
            echo '<td>' . wc_price( $cust['total_spent'] ) . '</td>';
            echo '<td>' . wc_price( $average_order ) . '</td>';
            echo '<td>' . wc_price( $cust['clv'] ) . '</td>';
            echo '<td>' . esc_html( $registered ) . '</td>';
            echo '</tr>';
        }
 
        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_top_products() {
        $this->maybe_log_event( 'Visited Top Products' );
        echo '<div class="wrap"><h1>🏆 Top Products</h1>';
    
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
    
        $from_date = $from ? date('Y-m-d', strtotime($from)) : null;
        $to_date   = $to ? date('Y-m-d', strtotime($to)) : null;

        $use_light_charts = get_option( 'shopanalytics_light_charts' );
        if ( $use_light_charts ) {
            echo '<div class="notice notice-info"><p><strong>Note:</strong> Product visualizations are disabled due to the "Lightweight Charts" setting. Disable this option in Settings to view charts.</p></div>';
            return;
        }
    
        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="shopanalytics-products" />';
        echo '<label for="from">From: </label>';
        echo '<input type="date" name="from" value="' . esc_attr($from) . '" />';
        echo '<label for="to"> To: </label>';
        echo '<input type="date" name="to" value="' . esc_attr($to) . '" />';
        echo '<input type="submit" class="button button-primary" value="Filter" />';
        echo '</form>';
    
        $args = [
            'status' => ['wc-completed', 'wc-processing'],
            'limit'  => -1,
            'return' => 'ids',
        ];
    
        if ( ! empty( $from_date ) ) {
            $args['date_created_after'] = ( new \WC_DateTime( $from_date ) )->format( DATE_ATOM );
        }
    
        if ( ! empty( $to_date ) ) {
            $args['date_created_before'] = ( new \WC_DateTime( $to_date ) )->format( DATE_ATOM );
        }
    
        $orders = wc_get_orders( $args );
        $product_sales = [];
    
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product_id();
                $product_name = $item->get_name();
                $qty = $item->get_quantity();
                $total = $item->get_total();
    
                if ( ! isset( $product_sales[ $product_id ] ) ) {
                    $product_sales[ $product_id ] = [
                        'name'  => $product_name,
                        'qty'   => 0,
                        'total' => 0,
                    ];
                }
    
                $product_sales[ $product_id ]['qty'] += $qty;
                $product_sales[ $product_id ]['total'] += $total;
            }
        }
    
        uasort( $product_sales, function( $a, $b ) {
            return $b['total'] <=> $a['total'];
        });
    
        echo '<table class="widefat fixed striped" style="margin-top:20px;"><thead><tr><th>Product</th><th>Quantity Sold</th><th>Total Revenue</th></tr></thead><tbody>';
    
        foreach ( $product_sales as $product ) {
            echo '<tr>';
            echo '<td>' . esc_html( $product['name'] ) . '</td>';
            echo '<td>' . esc_html( $product['qty'] ) . '</td>';
            echo '<td>' . wc_price( $product['total'] ) . '</td>';
            echo '</tr>';
        }
    
        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_reports() {
        $this->maybe_log_event( 'Visited Reports (Log Viewer)' );
        if ( isset($_GET['export_logs']) && current_user_can('manage_woocommerce') ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'shopanalytics_logs';
            $where = "WHERE 1=1";
            if ( isset($_GET['log_user_id']) && $_GET['log_user_id'] !== '' ) {
                $user_id = intval($_GET['log_user_id']);
                $where .= $wpdb->prepare(" AND user_id = %d", $user_id);
            }
            if ( isset($_GET['log_keyword']) && $_GET['log_keyword'] !== '' ) {
                $keyword = sanitize_text_field($_GET['log_keyword']);
                $where .= $wpdb->prepare(" AND event LIKE %s", '%' . $wpdb->esc_like($keyword) . '%');
            }
            $logs = $wpdb->get_results( "SELECT * FROM $table_name $where ORDER BY created_at DESC" );
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="shopanalytics-logs.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Time', 'User ID', 'Event']);
            foreach ( $logs as $log ) {
                fputcsv($output, [ $log->created_at, $log->user_id, $log->event ]);
            }
            fclose($output);
            exit;
        }
        if ( isset($_POST['clear_logs']) && current_user_can('manage_woocommerce') ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'shopanalytics_logs';
            $wpdb->query( "DELETE FROM $table_name" );
            echo '<div class="updated notice is-dismissible"><p>All logs have been cleared.</p></div>';
        }
        echo '<div class="wrap"><h1>📤 Reports – Log Viewer</h1>';
        
        $filter_user_id = isset($_GET['log_user_id']) ? intval($_GET['log_user_id']) : '';
        $filter_keyword = isset($_GET['log_keyword']) ? sanitize_text_field($_GET['log_keyword']) : '';
        
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="shopanalytics-reports" />';
        echo '<label for="log_user_id">User ID: </label>';
        echo '<input type="text" name="log_user_id" value="' . esc_attr($filter_user_id) . '" />';
        echo '<label for="log_keyword"> Keyword: </label>';
        echo '<input type="text" name="log_keyword" value="' . esc_attr($filter_keyword) . '" />';
        echo '<input type="submit" class="button" value="Filter" />';
        echo '<input type="submit" name="export_logs" class="button button-secondary" value="Export to CSV" />';
        echo '</form>';
        echo '<form method="post" style="margin-top: 10px;">';
        echo '<input type="submit" name="clear_logs" class="button button-secondary" value="Clear Logs Now" onclick="return confirm(\'Are you sure you want to delete all logs?\')" />';
        echo '</form>';
 
        global $wpdb;
        $table_name = $wpdb->prefix . 'shopanalytics_logs';
        $where = "WHERE 1=1";
        if ( $filter_user_id ) {
            $where .= $wpdb->prepare(" AND user_id = %d", $filter_user_id);
        }
        if ( $filter_keyword ) {
            $where .= $wpdb->prepare(" AND event LIKE %s", '%' . $wpdb->esc_like($filter_keyword) . '%');
        }
        $logs = $wpdb->get_results( "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT 100" );
 
        if ( empty( $logs ) ) {
            echo '<p>No logs available.</p>';
        } else {
            echo '<table class="widefat fixed striped" style="margin-top: 20px;">';
            echo '<thead><tr><th>Time</th><th>User ID</th><th>Event</th></tr></thead><tbody>';
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
        echo '<div class="wrap"><h1>⚙️ Settings</h1>';
 
        echo '<form method="post" action="options.php">';
        settings_fields( 'shopanalytics_settings_group' );
        do_settings_sections( 'shopanalytics-lite' );
 
        echo '<table class="form-table">';
        echo '<tr><th scope="row">Enable Data Logging</th><td><input type="checkbox" name="shopanalytics_enable_logging" value="1" ' . checked(1, get_option('shopanalytics_enable_logging'), false) . ' /> Log usage and events for diagnostics.</td></tr>';
        echo '<tr><th scope="row">Enable Pro Feature Previews</th><td><input type="checkbox" name="shopanalytics_enable_pro_previews" value="1" ' . checked(1, get_option('shopanalytics_enable_pro_previews'), false) . ' /> Show UI elements for Pro-only features.</td></tr>';
        echo '<tr><th scope="row">Use Lightweight Charts</th><td><input type="checkbox" name="shopanalytics_light_charts" value="1" ' . checked(1, get_option('shopanalytics_light_charts'), false) . ' /> Optimize charts for performance.</td></tr>';
        echo '<tr><th scope="row">Log Retention Period (days)</th><td><input type="number" name="shopanalytics_log_retention_days" value="' . esc_attr( get_option("shopanalytics_log_retention_days", 90) ) . '" min="1" /> Automatically delete logs older than this number of days.</td></tr>';
        echo '</table>';
 
        submit_button();
 
        echo '</form>';
        echo '</div>';
    }
}

register_activation_hook( __FILE__, function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'shopanalytics_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        event text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
});

new ShopAnalytics_Admin_UI();
