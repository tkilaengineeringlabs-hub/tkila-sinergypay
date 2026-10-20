<?php

namespace TKILA_SinergyPay;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Api_Client {
    private string $base_url;
    private string $api_key;

    public function __construct( string $environment, string $api_key ) {
        // TODO: Confirm exact production URL from official SinergyPay documentation before enabling production.
        $this->base_url = 'sandbox' === $environment
            ? 'https://sandbox.sinergypay.mx/v2/'
            : 'https://sinergypay.mx/v2/';
        $this->api_key = $api_key;
    }

    /**
     * Low-level request helper. Endpoint-specific payloads/headers must follow official docs.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function request( string $method, string $path, array $body = array() ) {
        if ( '' === trim( $this->api_key ) ) {
            return new WP_Error( 'tkila_sinergypay_missing_key', __( 'SinergyPay API key is missing.', 'tkila-sinergypay' ) );
        }

        $url = trailingslashit( $this->base_url ) . ltrim( $path, '/' );

        // TODO: Replace Authorization header format only after confirming it against official docs.
        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 30,
            'headers' => array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => $this->api_key,
            ),
        );

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            Logger::error( 'SinergyPay HTTP request failed.', array( 'path' => $path, 'error' => $response->get_error_message() ) );
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $raw    = wp_remote_retrieve_body( $response );
        $data   = json_decode( $raw, true );

        if ( $status < 200 || $status >= 300 ) {
            Logger::error( 'SinergyPay returned a non-success status.', array( 'path' => $path, 'status' => $status ) );
            return new WP_Error( 'tkila_sinergypay_api_error', __( 'SinergyPay rejected the request.', 'tkila-sinergypay' ), array( 'status' => $status ) );
        }

        return is_array( $data ) ? $data : array();
    }
}
