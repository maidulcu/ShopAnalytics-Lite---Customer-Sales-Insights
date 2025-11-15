<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShopAnalytics_Engine {

    // Order statuses constant to avoid hardcoding throughout
    const ORDER_STATUSES = array( 'wc-completed', 'wc-processing' );

    public function get_total_revenue( $from = null, $to = null ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT SUM(meta.meta_value)
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN (%s, %s)
            AND meta.meta_key = '_order_total'
            AND posts.post_date >= %s
            AND posts.post_date <= %s",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $from ? gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ) : '1970-01-01 00:00:00',
            $to ? gmdate( 'Y-m-d 23:59:59', strtotime( $to ) ) : gmdate( 'Y-m-d H:i:s' )
        );

        $total = $wpdb->get_var( $query );

        return $total ? (float) $total : 0;
    }

    public function get_total_orders( $from = null, $to = null ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(ID)
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_order'
            AND post_status IN (%s, %s)
            AND post_date >= %s
            AND post_date <= %s",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $from ? gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ) : '1970-01-01 00:00:00',
            $to ? gmdate( 'Y-m-d 23:59:59', strtotime( $to ) ) : gmdate( 'Y-m-d H:i:s' )
        );

        $count = $wpdb->get_var( $query );
        return $count ? (int) $count : 0;
    }

    public function get_average_order_value( $from = null, $to = null ) {
        $total_orders  = $this->get_total_orders( $from, $to ); // This is efficient.
        $total_revenue = $this->get_total_revenue( $from, $to ); // Now this is also efficient.

        if ( $total_orders === 0 ) {
            return 0;
        }

        return $total_revenue / $total_orders;
    }

    public function get_repeat_purchase_rate( $from = null, $to = null ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT meta.meta_value as customer_id, COUNT(posts.ID) as order_count
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN (%s, %s)
            AND meta.meta_key = '_customer_user'
            AND meta.meta_value > 0
            AND posts.post_date >= %s
            AND posts.post_date <= %s
            GROUP BY meta.meta_value",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $from ? gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ) : '1970-01-01 00:00:00',
            $to ? gmdate( 'Y-m-d 23:59:59', strtotime( $to ) ) : gmdate( 'Y-m-d H:i:s' )
        );

        $customer_orders = $wpdb->get_results( $query );

        if ( empty( $customer_orders ) ) {
            return 0;
        }

        $repeat_customers = 0;
        foreach ( $customer_orders as $customer ) {
            if ( $customer->order_count > 1 ) {
                $repeat_customers++;
            }
        }

        $total_customers = count( $customer_orders );

        if ( $total_customers === 0 ) {
            return 0;
        }

        return ( $repeat_customers / $total_customers ) * 100;
    }

    public function get_top_customers( $limit = 10, $from = null, $to = null ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT
                customer_meta.meta_value AS customer_id,
                MAX(first_name_meta.meta_value) AS first_name,
                MAX(last_name_meta.meta_value) AS last_name,
                MAX(email_meta.meta_value) AS email,
                COUNT(DISTINCT posts.ID) AS orders,
                SUM(total_meta.meta_value) AS total_spent
            FROM
                {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS customer_meta ON posts.ID = customer_meta.post_id AND customer_meta.meta_key = '_customer_user'
            INNER JOIN {$wpdb->postmeta} AS total_meta ON posts.ID = total_meta.post_id AND total_meta.meta_key = '_order_total'
            INNER JOIN {$wpdb->postmeta} AS first_name_meta ON posts.ID = first_name_meta.post_id AND first_name_meta.meta_key = '_billing_first_name'
            INNER JOIN {$wpdb->postmeta} AS last_name_meta ON posts.ID = last_name_meta.post_id AND last_name_meta.meta_key = '_billing_last_name'
            INNER JOIN {$wpdb->postmeta} AS email_meta ON posts.ID = email_meta.post_id AND email_meta.meta_key = '_billing_email'
            WHERE
                posts.post_type = 'shop_order'
                AND posts.post_status IN (%s, %s)
                AND customer_meta.meta_value > 0
                AND posts.post_date >= %s
                AND posts.post_date <= %s
            GROUP BY
                customer_id
            ORDER BY
                total_spent DESC
            LIMIT %d",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $from ? gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ) : '1970-01-01 00:00:00',
            $to ? gmdate( 'Y-m-d 23:59:59', strtotime( $to ) ) : gmdate( 'Y-m-d H:i:s' ),
            absint( $limit )
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        // Add CLV to each customer
        foreach ( $results as $key => $customer ) {
            $results[ $key ]['name'] = $customer['first_name'] . ' ' . $customer['last_name'];
            $results[ $key ]['clv'] = $this->get_customer_lifetime_value( $customer['customer_id'] );
        }

        return $results;
    }

    public function get_sales_by_product( $from = null, $to = null ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT
                p_id_meta.meta_value AS product_id,
                oi.order_item_name AS name,
                SUM(qty_meta.meta_value) AS qty,
                SUM(total_meta.meta_value) AS total
            FROM
                {$wpdb->prefix}woocommerce_order_items AS oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS p_id_meta ON oi.order_item_id = p_id_meta.order_item_id AND p_id_meta.meta_key = '_product_id'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS qty_meta ON oi.order_item_id = qty_meta.order_item_id AND qty_meta.meta_key = '_qty'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS total_meta ON oi.order_item_id = total_meta.order_item_id AND total_meta.meta_key = '_line_total'
            JOIN {$wpdb->posts} AS p ON oi.order_id = p.ID
            WHERE
                oi.order_item_type = 'line_item'
                AND p.post_type = 'shop_order'
                AND p.post_status IN (%s, %s)
                AND p.post_date >= %s
                AND p.post_date <= %s
            GROUP BY
                p_id_meta.meta_value
            ORDER BY
                total DESC",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $from ? gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ) : '1970-01-01 00:00:00',
            $to ? gmdate( 'Y-m-d 23:59:59', strtotime( $to ) ) : gmdate( 'Y-m-d H:i:s' )
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        // Cast numeric values
        return array_map( function($row) {
            $row['qty'] = (int) $row['qty'];
            $row['total'] = (float) $row['total'];
            return $row;
        }, $results );
    }

    public function get_customer_lifetime_value( $customer_id ) {
        if ( ! $customer_id ) {
            return 0;
        }

        global $wpdb;

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(total_meta.meta_value)
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS customer_meta ON posts.ID = customer_meta.post_id
            INNER JOIN {$wpdb->postmeta} AS total_meta ON posts.ID = total_meta.post_id
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN (%s, %s)
            AND customer_meta.meta_key = '_customer_user'
            AND customer_meta.meta_value = %d
            AND total_meta.meta_key = '_order_total'",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $customer_id
        ) );

        return $total ? (float) $total : 0;
    }

    public function get_sales_by_category( $from = null, $to = null ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT
                terms.name AS category_name,
                SUM(total_meta.meta_value) AS total_sales
            FROM
                {$wpdb->prefix}woocommerce_order_items AS oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS p_id_meta ON oi.order_item_id = p_id_meta.order_item_id AND p_id_meta.meta_key = '_product_id'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS total_meta ON oi.order_item_id = total_meta.order_item_id AND total_meta.meta_key = '_line_total'
            JOIN {$wpdb->posts} AS p ON oi.order_id = p.ID
            JOIN {$wpdb->term_relationships} AS tr ON p_id_meta.meta_value = tr.object_id
            JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->terms} AS terms ON tt.term_id = terms.term_id
            WHERE
                oi.order_item_type = 'line_item'
                AND p.post_type = 'shop_order'
                AND p.post_status IN (%s, %s)
                AND p.post_date >= %s
                AND p.post_date <= %s
            GROUP BY
                terms.term_id
            ORDER BY
                total_sales DESC",
            self::ORDER_STATUSES[0],
            self::ORDER_STATUSES[1],
            $from ? gmdate( 'Y-m-d 00:00:00', strtotime( $from ) ) : '1970-01-01 00:00:00',
            $to ? gmdate( 'Y-m-d 23:59:59', strtotime( $to ) ) : gmdate( 'Y-m-d H:i:s' )
        );

        $results = $wpdb->get_results( $query, OBJECT_K );

        return array_map( function($row) {
            return (float) $row->total_sales;
        }, $results );
    }
}
