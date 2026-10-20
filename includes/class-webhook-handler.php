<?php

namespace TKILA_SinergyPay;

use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Webhook_Handler {
    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            'tkila-sinergypay/v1',
            '/webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function handle( WP_REST_Request $request ): WP_REST_Response {
        // TODO: Implement SinergyPay's documented signature verification BEFORE processing events.
        // Intentionally reject events until verification is implemented.
        Logger::info( 'Webhook received but rejected because signature verification is not implemented.' );

        return new WP_REST_Response(
            array(
                'ok'      => false,
                'message' => 'Webhook verification not configured.',
            ),
            503
        );
    }
}
