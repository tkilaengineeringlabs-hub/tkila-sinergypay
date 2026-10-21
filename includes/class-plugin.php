<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function boot(): void {
        $this->load_dependencies();

        add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateways' ) );

        ( new Webhook_Handler() )->register();
    }

    private function load_dependencies(): void {
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-logger.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-api-client.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-order-lock.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-payment-confirmation.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-spei-gateway.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-webhook-handler.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-card-gateway.php';
        require_once TKILA_SINERGYPAY_PATH . 'includes/class-tokenization.php';
    }

    /**
     * @param array<int,string> $gateways
     * @return array<int,string>
     */
    public function register_gateways( array $gateways ): array {
        $gateways[] = Spei_Gateway::class;
        return $gateways;
    }
}
