<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShopAnalytics_Data_Exporter {

    /**
     * Export an array of data to CSV output.
     *
     * @param string $filename
     * @param array $headers
     * @param array $rows
     */
    public static function export_to_csv( $filename, $headers, $rows ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

        if ( $headers ) {
            echo implode(',', array_map('esc_html', $headers)) . "\n";
        }

        foreach ( $rows as $row ) {
            echo implode(',', array_map('esc_html', $row)) . "\n";
        }
        exit;
    }
}

class ShopAnalytics_Pro_Data_Exporter extends ShopAnalytics_Data_Exporter {
    // Future enhancements for Pro CSV export logic will be added here.
}
