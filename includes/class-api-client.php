<?php

namespace TKILA_SinergyPay;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Api_Client {
    private string $environment;
    private string $api_key;

    public function __construct( string $environment, string $api_key ) {
        $this->environment = $environment;
        $this->api_key     = $api_key;
    }

    public function create_order( string $amount, string $description, string $reference ) {
        return $this->request( 'orders/', compact( 'amount', 'description', 'reference' ) );
    }

    public function create_clabe( string $order_id ) {
        return $this->request( 'orders/' . rawurlencode( $order_id ) . '/clabe', array() );
    }

    /** Documented private-key POST endpoints only (PDF pp. 3-5). */
    private function request( string $path, array $body ) {
        if ( 'sandbox' !== $this->environment || '' === trim( $this->api_key ) ) {
            Logger::error( 'SPEI request blocked by local configuration.', array( 'reason' => 'invalid_environment_or_empty_key', 'endpoint' => $path ) );
            return new WP_Error( 'tkila_sinergypay_configuration', 'SPEI Sandbox requires a private API key.' );
        }

        $response = wp_remote_request(
            'https://sandbox.sinergypay.mx/v2/' . $path,
            array(
                'method'      => 'POST',
                'timeout'     => 30,
                'redirection' => 0,
                'headers'     => array(
                    'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' ),
                    'User-Agent'    => 'TkilaSinergyPay/' . TKILA_SINERGYPAY_VERSION,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( (object) $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            Logger::error( 'SPEI transport failed; outcome requires reconciliation.', array(
                'reason'         => 'transport_error',
                'transport_code' => sanitize_key( $response->get_error_code() ),
                'endpoint'       => $path,
            ) );
            return new WP_Error( 'tkila_sinergypay_transport', 'No se pudo confirmar la respuesta de SinergyPay.' );
        }

        $status      = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $raw_body, true );
        $response_keys = is_array( $data ) ? implode( ',', array_slice( array_map( 'sanitize_key', array_keys( $data ) ), 0, 12 ) ) : '';

        $reason = '';
        if ( $status < 200 || $status >= 300 ) {
            $reason = 'http_status_not_success';
        } elseif ( ! is_array( $data ) ) {
            $reason = JSON_ERROR_NONE === json_last_error() ? 'json_not_object' : 'invalid_json';
        } elseif ( ! array_key_exists( 'rc', $data ) ) {
            $reason = 'missing_rc';
        } elseif ( 0 !== $data['rc'] ) {
            $reason = 'nonzero_rc';
        } elseif ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            $reason = 'missing_or_invalid_data';
        }

        if ( '' !== $reason ) {
            $context = array(
                'http_status'   => $status,
                'reason'        => $reason,
                'response_keys' => $response_keys,
                'endpoint'      => $path,
            );
            if ( is_array( $data ) && ( is_int( $data['rc'] ?? null ) || is_string( $data['rc'] ?? null ) ) ) {
                $context['api_rc'] = is_string( $data['rc'] ) ? sanitize_text_field( $data['rc'] ) : $data['rc'];
            }
            Logger::error( 'SPEI API response rejected.', $context );
            return new WP_Error( 'tkila_sinergypay_response', 'SinergyPay no devolvió una respuesta válida.' );
        }

        return $data['data'];
    }
}
