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

        foreach ( array( 'api_key', 'authorization', 'cvv', 'cvc', 'pan', 'card_number' ) as $key ) {
            if ( array_key_exists( $key, $context ) ) {
                $context[ $key ] = '[REDACTED]';
            }
        }

        $context['source'] = self::SOURCE;
        wc_get_logger()->log( $level, $message, $context );
    }
}
