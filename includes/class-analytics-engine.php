<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShopAnalytics_Engine {

    public function get_total_revenue( $from = null, $to = null ) {
        $args = shopanalytics_build_order_query_args( $from, $to );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[ShopAnalytics - Total Revenue] Final Orders Query Args: ' . print_r( $args, true ) );
        }
    
        $orders = wc_get_orders( $args );
        $total  = 0;
    
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! method_exists( $order, 'get_customer_id' ) ) {
                continue;
            }
            $total += $order->get_total();
        }
    
        return $total;
    }

    public function get_total_orders( $from = null, $to = null ) {
        $args = shopanalytics_build_order_query_args( $from, $to );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[ShopAnalytics - Total Orders] Final Orders Query Args: ' . print_r( $args, true ) );
        }

        $orders = wc_get_orders( $args );
        return count( $orders );
    }

    public function get_average_order_value( $from = null, $to = null ) {
        $total_orders  = $this->get_total_orders( $from, $to );
        $total_revenue = $this->get_total_revenue( $from, $to );

        if ( $total_orders === 0 ) {
            return 0;
        }

        return $total_revenue / $total_orders;
    }

    public function get_repeat_purchase_rate( $from = null, $to = null ) {
        $args = shopanalytics_build_order_query_args( $from, $to );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[ShopAnalytics - Repeat Purchase Rate] Final Orders Query Args: ' . print_r( $args, true ) );
        }

        $orders = wc_get_orders( $args );
        $customer_orders = [];

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! method_exists( $order, 'get_customer_id' ) ) {
                continue;
            }
            $customer_id = $order->get_customer_id();

            if ( $customer_id ) {
                if ( ! isset( $customer_orders[ $customer_id ] ) ) {
                    $customer_orders[ $customer_id ] = 0;
                }
                $customer_orders[ $customer_id ]++;
            }
        }

        $repeat_customers = array_filter( $customer_orders, function( $count ) {
            return $count > 1;
        });

        $total_customers = count( $customer_orders );

        if ( $total_customers === 0 ) {
            return 0;
        }

        return ( count( $repeat_customers ) / $total_customers ) * 100;
    }

    public function get_top_customers( $limit = 10, $from = null, $to = null ) {
        $args = shopanalytics_build_order_query_args( $from, $to );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[ShopAnalytics - Top Customers] Final Orders Query Args: ' . print_r( $args, true ) );
        }

        $orders = wc_get_orders( $args );
        $customer_data = [];

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! method_exists( $order, 'get_customer_id' ) ) {
                continue;
            }
            $customer_id = $order->get_customer_id();

            if ( $customer_id ) {
                if ( ! isset( $customer_data[ $customer_id ] ) ) {
                    $customer_data[ $customer_id ] = [
                        'id' => $customer_id,
                        'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'email' => $order->get_billing_email(),
                        'orders' => 0,
                        'total_spent' => 0,
                        'clv' => $this->get_customer_lifetime_value( $customer_id ),
                    ];
                }

                $customer_data[ $customer_id ]['orders']++;
                $customer_data[ $customer_id ]['total_spent'] += $order->get_total();
            }
        }

        usort( $customer_data, function( $a, $b ) {
            return $b['total_spent'] <=> $a['total_spent'];
        });

        return array_slice( $customer_data, 0, $limit );
    }

    public function get_sales_by_product( $from = null, $to = null ) {
        $args = shopanalytics_build_order_query_args( $from, $to );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[ShopAnalytics - Sales by Product] Final Orders Query Args: ' . print_r( $args, true ) );
        }

        $orders = wc_get_orders( $args );
        $sales = [];

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order || ! $order->get_items() ) {
                continue;
            }

            foreach ( $order->get_items() as $item ) {
                if ( ! is_callable( [ $item, 'get_product_id' ] ) ) {
                    continue;
                }

                $product_id = $item->get_product_id();
                $name       = $item->get_name();
                $qty        = $item->get_quantity();
                $total      = $item->get_total();

                if ( ! isset( $sales[ $product_id ] ) ) {
                    $sales[ $product_id ] = [
                        'product_id' => $product_id,
                        'name'       => $name,
                        'qty'        => 0,
                        'total'      => 0,
                    ];
                }

                $sales[ $product_id ]['qty']   += $qty;
                $sales[ $product_id ]['total'] += $total;
            }
        }

        uasort( $sales, function( $a, $b ) {
            return $b['total'] <=> $a['total'];
        });

        return $sales;
    }

    public function get_customer_lifetime_value( $customer_id ) {
        if ( ! $customer_id ) {
            return 0;
        }

        $args = [
            'status'      => [ 'wc-completed', 'wc-processing' ],
            'limit'       => -1,
            'return'      => 'ids',
            'customer_id' => $customer_id,
        ];

        $orders = wc_get_orders( $args );
        $total  = 0;

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && method_exists( $order, 'get_total' ) ) {
                $total += $order->get_total();
            }
        }

        return $total;
    }

    public function get_sales_by_category( $from = null, $to = null ) {
        $args = shopanalytics_build_order_query_args( $from, $to );
        $orders = wc_get_orders( $args );
        $sales = [];

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order || ! $order->get_items() ) {
                continue;
            }

            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product_id();
                $product    = wc_get_product( $product_id );
                if ( ! $product ) {
                    continue;
                }

                $categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
                $total = $item->get_total();

                foreach ( $categories as $category ) {
                    if ( ! isset( $sales[ $category ] ) ) {
                        $sales[ $category ] = 0;
                    }
                    $sales[ $category ] += $total;
                }
            }
        }

        arsort( $sales );
        return $sales;
    }
}
