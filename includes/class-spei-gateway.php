<?php

namespace TKILA_SinergyPay;

defined( 'ABSPATH' ) || exit;

final class Spei_Gateway extends \WC_Payment_Gateway {
    public function __construct() {
        $this->id                 = 'tkila_sinergypay_spei';
        $this->method_title       = __( 'SinergyPay SPEI', 'tkila-sinergypay' );
        $this->method_description = __( 'Transferencias SPEI mediante SinergyPay.', 'tkila-sinergypay' );
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title', __( 'Transferencia SPEI', 'tkila-sinergypay' ) );
        $this->description = $this->get_option( 'description', __( 'Paga mediante transferencia SPEI.', 'tkila-sinergypay' ) );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
    }

    public function init_form_fields(): void {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Activar', 'tkila-sinergypay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activar SinergyPay SPEI', 'tkila-sinergypay' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'   => __( 'Título', 'tkila-sinergypay' ),
                'type'    => 'text',
                'default' => __( 'Transferencia SPEI', 'tkila-sinergypay' ),
            ),
            'description' => array(
                'title'   => __( 'Descripción', 'tkila-sinergypay' ),
                'type'    => 'textarea',
                'default' => __( 'Paga mediante transferencia SPEI.', 'tkila-sinergypay' ),
            ),
            'environment' => array(
                'title'   => __( 'Ambiente', 'tkila-sinergypay' ),
                'type'    => 'select',
                'default' => 'sandbox',
                'options' => array(
                    'sandbox'    => __( 'Sandbox', 'tkila-sinergypay' ),
                    'production' => __( 'Producción', 'tkila-sinergypay' ),
                ),
            ),
            'sandbox_api_key' => array(
                'title' => __( 'Sandbox API Key', 'tkila-sinergypay' ),
                'type'  => 'password',
            ),
            'production_api_key' => array(
                'title' => __( 'Production API Key', 'tkila-sinergypay' ),
                'type'  => 'password',
            ),
            'debug' => array(
                'title'   => __( 'Debug', 'tkila-sinergypay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activar logs sanitizados', 'tkila-sinergypay' ),
                'default' => 'no',
            ),
        );
    }

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'No fue posible crear el pago.', 'tkila-sinergypay' ), 'error' );
            return array( 'result' => 'failure' );
        }

        // TODO: Implement documented SinergyPay SPEI order + CLABE requests here.
        // Fail safely until exact payload/authentication fields from the official docs are wired.
        Logger::info( 'SPEI checkout reached; API operation pending implementation.', array( 'order_id' => $order->get_id() ) );
        wc_add_notice( __( 'La integración SPEI está en configuración de Sandbox.', 'tkila-sinergypay' ), 'error' );

        return array( 'result' => 'failure' );
    }

    public function thankyou_page( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $clabe = $order->get_meta( '_tkila_sinergypay_clabe' );
        if ( ! $clabe ) {
            return;
        }

        echo '<section class="tkila-sinergypay-instructions">';
        echo '<h2>' . esc_html__( 'Instrucciones de pago SPEI', 'tkila-sinergypay' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'CLABE:', 'tkila-sinergypay' ) . '</strong> ' . esc_html( $clabe ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Importe:', 'tkila-sinergypay' ) . '</strong> ' . wp_kses_post( $order->get_formatted_order_total() ) . '</p>';
        echo '</section>';
    }
}
