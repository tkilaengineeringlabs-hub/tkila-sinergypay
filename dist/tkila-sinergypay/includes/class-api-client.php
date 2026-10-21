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
            Logger::error( 'SPEI transport failed; outcome requires reconciliation.' );
            return new WP_Error( 'tkila_sinergypay_transport', 'No se pudo confirmar la respuesta de SinergyPay.' );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $data ) ||
            ! isset( $data['rc'] ) || 0 !== $data['rc'] || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            Logger::error( 'SPEI API response rejected.', array( 'http_status' => $status ) );
            return new WP_Error( 'tkila_sinergypay_response', 'SinergyPay no devolvió una respuesta válida.' );
        }

        return $data['data'];
    }
}
