<?php
/**
 * Plugin Name: Tkila SinergyPay Gateway
 * Description: Integración de SinergyPay para WooCommerce. SPEI primero; tarjetas/tokenización preparadas para futura habilitación PCI.
 * Version: 0.1.0
 * Author: Tkila Engineering Labs
 * Text Domain: tkila-sinergypay
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'TKILA_SINERGYPAY_VERSION', '0.1.0' );
define( 'TKILA_SINERGYPAY_FILE', __FILE__ );
define( 'TKILA_SINERGYPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'TKILA_SINERGYPAY_URL', plugin_dir_url( __FILE__ ) );

require_once TKILA_SINERGYPAY_PATH . 'includes/class-plugin.php';

add_action(
    'plugins_loaded',
    static function (): void {
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
            add_action( 'admin_notices', static function (): void {
                if ( current_user_can( 'activate_plugins' ) ) {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Tkila SinergyPay requiere WooCommerce instalado y activo.', 'tkila-sinergypay' ) . '</p></div>';
                }
            } );
            return;
        }

        \TKILA_SinergyPay\Plugin::instance()->boot();
    },
    20
);
