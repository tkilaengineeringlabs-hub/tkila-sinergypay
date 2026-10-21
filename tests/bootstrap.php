<?php
// Isolated bootstrap/registration smoke test, not a real WordPress installation.
define( 'ABSPATH', __DIR__ );
$hooks = array();
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.invalid/plugins/tkila-sinergypay/'; }
function add_action( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter( $hook, $callback, ...$args ) { add_action( $hook, $callback ); }
if ( ! in_array( '--without-woocommerce', $argv, true ) ) {
    class WooCommerce {}
    class WC_Payment_Gateway {}
}
$entry = $argv[1] ?? __DIR__ . '/../tkila-sinergypay.php';
require $entry;
foreach ( $hooks['plugins_loaded'] as $callback ) { $callback(); }
if ( ! class_exists( 'WooCommerce' ) ) {
    if ( empty( $hooks['admin_notices'] ) || isset( $hooks['woocommerce_payment_gateways'] ) ) {
        throw new Exception( 'Missing-dependency handling failed.' );
    }
    echo "PASS: dependency notice, no fatal error without WooCommerce\n";
    exit;
}
$gateways = array();
foreach ( $hooks['woocommerce_payment_gateways'] ?? array() as $callback ) { $gateways = $callback( $gateways ); }
if ( $gateways !== array( TKILA_SinergyPay\Spei_Gateway::class ) || ! is_subclass_of( $gateways[0], 'WC_Payment_Gateway' ) || empty( $hooks['rest_api_init'] ) ) {
    throw new Exception( 'Gateway/webhook registration failed.' );
}
echo "PASS: all dependencies loaded, SPEI gateway registered, REST initialization registered\n";
