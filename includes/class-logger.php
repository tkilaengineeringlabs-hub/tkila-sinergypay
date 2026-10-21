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

        // Errors are always recorded with sanitized context so a failed checkout
        // remains diagnosable even when optional debug logging is disabled.
        if ( 'info' === $level ) {
            $settings = get_option( 'woocommerce_tkila_sinergypay_spei_settings', array() );
            if ( 'yes' !== ( $settings['debug'] ?? 'no' ) ) {
                return;
            }
        }

        // Strict allowlist: never log response bodies, headers, credentials or bank data.
        $allowed = array( 'order_id', 'http_status', 'reason', 'api_rc', 'response_keys', 'transport_code' );
        $context = array_intersect_key( $context, array_flip( $allowed ) );
        $context['source'] = self::SOURCE;

        wc_get_logger()->log( $level, $message, $context );
    }
}
