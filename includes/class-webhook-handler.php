<?php

namespace TKILA_SinergyPay;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Webhook_Handler {
    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( 'tkila-sinergypay/v1', '/webhook', array(
            'methods' => 'POST', 'callback' => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /** Verify exact original values, never sanitized/reformatted values (PDF pp. 7-8). */
    public static function verify( array $payload, array $keys ) {
        if ( ! function_exists( 'openssl_verify' ) ) {
            return new WP_Error( 'unavailable', 'OpenSSL unavailable.' );
        }
        $security = $payload['security'] ?? null;
        if ( ! is_array( $security ) || 1 !== ( $security['version'] ?? null ) ||
            ! is_string( $security['key'] ?? null ) || ! is_string( $security['signature'] ?? null ) ) {
            return new WP_Error( 'invalid_signature', 'Invalid security envelope.' );
        }
        // Lookup only in administrator-provisioned keys: never fetch a payload URL/path.
        $pem = $keys[ $security['key'] ] ?? null;
        if ( ! is_string( $pem ) ) {
            return new WP_Error( 'unknown_key', 'Unknown signing key.' );
        }
        $signed = array();
        foreach ( array( 'id', 'currency', 'amount', 'description', 'reference', 'date' ) as $field ) {
            if ( ! array_key_exists( $field, $payload ) ||
                ( null !== $payload[ $field ] && ! is_string( $payload[ $field ] ) ) ) {
                // Reject numeric JSON amounts: coercion could change the signed representation.
                return new WP_Error( 'invalid_payload', 'Signed fields must be strings or null.' );
            }
            $signed[ $field ] = $payload[ $field ] ?? '';
        }
        $signature = base64_decode( $security['signature'], true );
        $key       = openssl_pkey_get_public( $pem );
        $details = false !== $key ? openssl_pkey_get_details( $key ) : false;
        if ( false === $signature || false === $key || ! $details || OPENSSL_KEYTYPE_RSA !== $details['type'] ||
            1 !== openssl_verify( implode( '|', $signed ), $signature, $key, OPENSSL_ALGO_SHA512 ) ) {
            return new WP_Error( 'invalid_signature', 'Signature verification failed.' );
        }
        return $signed;
    }

    public function handle( WP_REST_Request $request ): WP_REST_Response {
        if ( strlen( $request->get_body() ) > 65536 ) {
            return new WP_REST_Response( array( 'ok' => false ), 413 );
        }
        $settings = get_option( 'woocommerce_tkila_sinergypay_spei_settings', array() );
        $keys     = json_decode( $settings['webhook_public_keys'] ?? '{}', true );
        if ( ! function_exists( 'openssl_verify' ) || ! is_array( $keys ) || empty( $keys ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'message' => 'Verification not configured.' ), 503 );
        }
        $payload = json_decode( $request->get_body(), true );
        $signed  = is_array( $payload ) ? self::verify( $payload, $keys ) : new WP_Error( 'invalid_payload' );
        if ( is_wp_error( $signed ) ) {
            Logger::error( 'SPEI webhook rejected.', array( 'reason' => $signed->get_error_code() ) );
            return new WP_REST_Response( array( 'ok' => false ), 401 );
        }
        // Correlate ONLY through signed reference; unsigned code is intentionally ignored.
        $query = array( 'limit' => 2, 'type' => 'shop_order' );
        $hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        if ( $hpos ) {
            $query['meta_query'] = array( array( 'key' => '_tkila_sinergypay_reference', 'value' => $signed['reference'], 'compare' => '=' ) );
        } else {
            $query['meta_key'] = '_tkila_sinergypay_reference';
            $query['meta_value'] = $signed['reference'];
        }
        $orders = wc_get_orders( $query );
        if ( '' === $signed['reference'] || 1 !== count( $orders ) ) {
            return new WP_REST_Response( array( 'ok' => false ), 409 );
        }
        $id = $orders[0]->get_id();
        if ( ! Order_Lock::acquire( $id ) ) {
            return new WP_REST_Response( array( 'ok' => false ), 503 );
        }
        try {
            $order = wc_get_order( $id );
            if ( ! $order || 'tkila_sinergypay_spei' !== $order->get_payment_method() ||
                'sandbox' !== $order->get_meta( '_tkila_sinergypay_environment' ) ||
                $signed['reference'] !== $order->get_meta( '_tkila_sinergypay_reference' ) ||
                $signed['description'] !== $order->get_meta( '_tkila_sinergypay_description' ) ||
                $signed['currency'] !== $order->get_currency() ||
                ! preg_match( '/^\d+(?:\.\d{1,2})?$/D', $signed['amount'] ) ||
                wc_format_decimal( $signed['amount'], 2 ) !== $order->get_meta( '_tkila_sinergypay_amount' ) ||
                wc_format_decimal( $order->get_total(), 2 ) !== $order->get_meta( '_tkila_sinergypay_amount' ) ) {
                return new WP_REST_Response( array( 'ok' => false ), 409 );
            }
            if ( $order->get_meta( '_tkila_sinergypay_verified_notification' ) !== $signed ) {
                Payment_Confirmation::record( $order, $signed );
            }
            return new WP_REST_Response( array( 'ok' => true, 'payment_confirmed' => false ), 202 );
        } finally {
            Order_Lock::release( $id );
        }
    }
}
