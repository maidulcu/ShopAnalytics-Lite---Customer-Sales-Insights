<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Format a float as a currency value using WooCommerce settings.
 *
 * @param float $amount
 * @return string
 */
function shopanalytics_format_currency( $amount ) {
    return wc_price( $amount );
}

/**
 * Get a safe datetime range for WooCommerce queries.
 *
 * @param string|null $from
 * @param string|null $to
 * @return array
 */
function shopanalytics_get_date_range( $from = null, $to = null ) {
    $range = [];

    if ( $from ) {
        $range['after'] = date( 'Y-m-d 00:00:00', strtotime( $from ) );
    }

    if ( $to ) {
        $range['before'] = date( 'Y-m-d 23:59:59', strtotime( $to ) );
    }

    return $range;
}

/**
 * Utility function to check if a value is empty.
 *
 * @param mixed $value
 * @return bool
 */
function shopanalytics_is_empty( $value ) {
    return empty( $value );
}

/**
 * Utility function to sanitize a string.
 *
 * @param string $string
 * @return string
 */
function shopanalytics_sanitize_string( $string ) {
    return sanitize_text_field( $string );
}

/**
 * Build standardized WooCommerce order query args using date filters.
 *
 * @param string|null $from
 * @param string|null $to
 * @return array
 */
function shopanalytics_build_order_query_args( $from = null, $to = null ) {
    $args = [
        'status' => [ 'wc-completed', 'wc-processing' ],
        'limit'  => -1,
        'return' => 'ids',
    ];

    if ( ! empty( $from ) && is_string( $from ) && ! is_array( $from ) ) {
        try {
            $args['date_created_after'] = ( new \WC_DateTime( $from ) )->format( DATE_ATOM );
        } catch ( Exception $e ) {
            error_log( '[ShopAnalytics] Invalid "from" date: ' . print_r( $from, true ) );
        }
    }

    if ( ! empty( $to ) && is_string( $to ) && ! is_array( $to ) ) {
        try {
            $args['date_created_before'] = ( new \WC_DateTime( $to ) )->format( DATE_ATOM );
        } catch ( Exception $e ) {
            error_log( '[ShopAnalytics] Invalid "to" date: ' . print_r( $to, true ) );
        }
    }

    return $args;
}
