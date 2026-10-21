<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

final class Logger {
    private const SOURCE = 'tkila-sinergypay';

    public static function info( string $message, array $context = array() ): void {
        self::write( 'info', $message, $context );
    }

    public static function error( string $message, array $context = array() ): void {
        self::write( 'error', $message, $context );
    }

    private static function write( string $level, string $message, array $context ): void {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $settings = get_option( 'woocommerce_tkila_sinergypay_spei_settings', array() );
        if ( 'yes' !== ( $settings['debug'] ?? 'no' ) ) {
            return;
        }
        // Allowlist operational context only; never dump remote bodies, headers or errors.
        $context = array_intersect_key( $context, array_flip( array( 'order_id', 'http_status', 'reason' ) ) );

        $context['source'] = self::SOURCE;
        wc_get_logger()->log( $level, $message, $context );
    }
}
