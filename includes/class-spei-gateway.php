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
        add_action( 'woocommerce_view_order', array( $this, 'thankyou_page' ) );
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
            'webhook_public_keys' => array(
                'title' => __( 'Llaves públicas del webhook (JSON)', 'tkila-sinergypay' ),
                'type' => 'textarea',
                'description' => __( 'Mapa JSON de security.key a PEM público, obtenido del ZIP oficial de SinergyPay. Sin llaves se rechazan webhooks.', 'tkila-sinergypay' ),
                'default' => '{}',
            ),
            'debug' => array(
                'title'   => __( 'Debug', 'tkila-sinergypay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activar logs sanitizados', 'tkila-sinergypay' ),
                'default' => 'no',
            ),
        );
    }

    public function is_available(): bool {
        return parent::is_available() && 'sandbox' === $this->get_option( 'environment', 'sandbox' ) &&
            '' !== trim( $this->get_option( 'sandbox_api_key', '' ) ) && 'MXN' === get_woocommerce_currency();
    }

    public function validate_webhook_public_keys_field( $key, $value ): string {
        $keys = json_decode( $value, true );
        if ( ! is_array( $keys ) || ! function_exists( 'openssl_pkey_get_public' ) ) {
            throw new \Exception( 'Se requiere un mapa JSON de llaves públicas y OpenSSL.' );
        }
        foreach ( $keys as $name => $pem ) {
            if ( ! is_string( $name ) || ! is_string( $pem ) || false === openssl_pkey_get_public( $pem ) ) {
                throw new \Exception( 'Llave pública inválida. Use el identificador y PEM del archivo oficial.' );
            }
        }
        return wp_json_encode( (object) $keys );
    }

    public function process_payment( $order_id ): array {
        $id = absint( $order_id );
        if ( ! Order_Lock::acquire( $id ) ) {
            wc_add_notice( __( 'Este pedido tiene una operación en curso. Intenta nuevamente más tarde.', 'tkila-sinergypay' ), 'error' );
            return array( 'result' => 'failure' );
        }
        try {
            return $this->prepare_payment( $id );
        } catch ( \Throwable $error ) {
            Logger::error( 'SPEI checkout interrupted.', array( 'order_id' => $id, 'exception_type' => get_class( $error ) ) );
            wc_add_notice( __( 'No se pudo preparar el pago. Contacta a la tienda antes de volver a intentarlo.', 'tkila-sinergypay' ), 'error' );
            return array( 'result' => 'failure' );
        } finally {
            Order_Lock::release( $id );
        }
    }

    private function prepare_payment( int $id ): array {
        $order = wc_get_order( $id );
        if ( ! $order || ! $this->is_available() || 'MXN' !== $order->get_currency() ||
            'tkila_sinergypay_spei' !== $order->get_payment_method() ||
            ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
            return $this->payment_error();
        }
        $amount = wc_format_decimal( $order->get_total(), 2 );
        if ( ! preg_match( '/^\d+\.\d{2}$/D', $amount ) || (float) $amount <= 0 || strlen( str_replace( '.', '', $amount ) ) > 10 ) {
            return $this->payment_error();
        }
        if ( $order->get_meta( '_tkila_sinergypay_reference' ) ) {
            if ( $amount !== $order->get_meta( '_tkila_sinergypay_amount' ) ||
                'sandbox' !== $order->get_meta( '_tkila_sinergypay_environment' ) ) {
                return $this->payment_error();
            }
        } else {
            $order->update_meta_data( '_tkila_sinergypay_reference', 'wc-' . $id . '-' . wp_generate_uuid4() );
            $order->update_meta_data( '_tkila_sinergypay_description', 'WooCommerce ' . $id );
            $order->update_meta_data( '_tkila_sinergypay_amount', $amount );
            $order->update_meta_data( '_tkila_sinergypay_environment', 'sandbox' );
            $order->save();
        }
        $client = new Api_Client( 'sandbox', $this->get_option( 'sandbox_api_key', '' ) );
        if ( ! $order->get_meta( '_tkila_sinergypay_order_id' ) ) {
            if ( $order->get_meta( '_tkila_sinergypay_create_attempted' ) ) {
                return $this->payment_error();
            }
            // TODO SinergyPay: document idempotency/reconciliation for an uncertain POST.
            // Persist intent before network I/O; never blindly create another operation.
            $order->update_meta_data( '_tkila_sinergypay_create_attempted', 'yes' );
            $order->save();
            $data = $client->create_order( $amount, $order->get_meta( '_tkila_sinergypay_description' ), $order->get_meta( '_tkila_sinergypay_reference' ) );
            if ( is_wp_error( $data ) ) {
                $error_data = $data->get_error_data();
                // Retry only when the API definitively rejected the request.
                // Keep the guard for transport/5xx/invalid-response ambiguity.
                if ( is_array( $error_data ) && ! empty( $error_data['definitive'] ) ) {
                    $order->delete_meta_data( '_tkila_sinergypay_create_attempted' );
                    $order->save();
                }
                return $this->payment_error();
            }
            if ( ! is_string( $data['id'] ?? null ) || '' === $data['id'] ) {
                return $this->payment_error();
            }
            $order->update_meta_data( '_tkila_sinergypay_order_id', $data['id'] );
            $order->save();
            if ( 'MXN' !== ( $data['currency'] ?? null ) || ! is_string( $data['amount'] ?? null ) ||
                ! preg_match( '/^\d+(?:\.\d{1,2})?$/D', $data['amount'] ) ||
                wc_format_decimal( $data['amount'], 2 ) !== $amount ) {
                return $this->payment_error();
            }
            foreach ( array( 'reference_num', 'creation_date', 'expiration_date' ) as $field ) {
                if ( is_string( $data[ $field ] ?? null ) ) {
                    $order->update_meta_data( '_tkila_sinergypay_' . $field, sanitize_text_field( $data[ $field ] ) );
                }
            }
            $order->update_meta_data( '_tkila_sinergypay_order_validated', 'yes' );
            $order->save();
        }
        if ( 'yes' !== $order->get_meta( '_tkila_sinergypay_order_validated' ) ) {
            return $this->payment_error();
        }
        if ( ! $order->get_meta( '_tkila_sinergypay_clabe' ) ) {
            if ( $order->get_meta( '_tkila_sinergypay_clabe_attempted' ) ) {
                return $this->payment_error();
            }
            $order->update_meta_data( '_tkila_sinergypay_clabe_attempted', 'yes' );
            $order->save();
            $data = $client->create_clabe( $order->get_meta( '_tkila_sinergypay_order_id' ) );
            if ( is_wp_error( $data ) ) {
                $error_data = $data->get_error_data();
                // A definitive rejection is safe to retry; ambiguous outcomes are not.
                if ( is_array( $error_data ) && ! empty( $error_data['definitive'] ) ) {
                    $order->delete_meta_data( '_tkila_sinergypay_clabe_attempted' );
                    $order->save();
                }
                return $this->payment_error();
            }
            if ( ! is_string( $data['clabe'] ?? null ) || '' === trim( sanitize_text_field( $data['clabe'] ) ) ) {
                return $this->payment_error();
            }
            // TODO SinergyPay: clarify the inconsistent 16-digit example; no length assumption.
            $order->update_meta_data( '_tkila_sinergypay_clabe', sanitize_text_field( $data['clabe'] ) );
            $order->save();
        }
        $expiration = $order->get_meta( '_tkila_sinergypay_expiration_date' );
        if ( $expiration && ( false === strtotime( $expiration ) || strtotime( $expiration ) <= time() ) ) {
            return $this->payment_error();
        }
        if ( $order->has_status( 'pending' ) ) {
            $order->update_status( 'on-hold', __( 'SPEI Sandbox: instrucciones generadas; pago pendiente de acreditación.', 'tkila-sinergypay' ) );
        }
        if ( WC()->cart ) {
            WC()->cart->empty_cart();
        }
        return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
    }

    private function payment_error(): array {
        wc_add_notice( __( 'No fue posible preparar SPEI. Contacta a la tienda para revisar la operación antes de reintentar.', 'tkila-sinergypay' ), 'error' );
        return array( 'result' => 'failure' );
    }

    public function thankyou_page( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order || $this->id !== $order->get_payment_method() ) {
            return;
        }
        $authorized = current_user_can( 'manage_woocommerce' ) ||
            ( $order->get_customer_id() && get_current_user_id() === $order->get_customer_id() );
        if ( ! $authorized ) {
            $key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
            if ( '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
                return;
            }
        }

        $clabe = $order->get_meta( '_tkila_sinergypay_clabe' );
        if ( ! $clabe ) {
            return;
        }

        echo '<section class="tkila-sinergypay-instructions">';
        echo '<h2>' . esc_html__( 'Instrucciones de pago SPEI', 'tkila-sinergypay' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'CLABE:', 'tkila-sinergypay' ) . '</strong> ' . esc_html( $clabe ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Importe:', 'tkila-sinergypay' ) . '</strong> ' . wp_kses_post( $order->get_formatted_order_total() ) . '</p>';
        foreach ( array( 'reference_num' => __( 'Referencia', 'tkila-sinergypay' ), 'expiration_date' => __( 'Vencimiento', 'tkila-sinergypay' ) ) as $field => $label ) {
            $value = $order->get_meta( '_tkila_sinergypay_' . $field );
            if ( $value ) {
                echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
        echo '<p>' . esc_html__( 'Sandbox: datos de prueba. La acreditación requiere revisión de la tienda.', 'tkila-sinergypay' ) . '</p>';
        echo '</section>';
    }
}
