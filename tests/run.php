<?php
// Offline integration contract tests; no WordPress install or network credentials.
define( 'ABSPATH', __DIR__ );
define( 'TKILA_SINERGYPAY_VERSION', 'test' );
class WP_Error {
    private string $code;
    public function __construct( $code, ...$args ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
class WP_REST_Request {
    private string $body;
    public function __construct( $payload ) { $this->body = json_encode( $payload ); }
    public function get_body() { return $this->body; }
}
class WP_REST_Response {
    public $data; public $status;
    public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
}
class WC_Payment_Gateway {
    public $id, $method_title, $method_description, $has_fields, $supports, $title, $description, $enabled, $form_fields;
    public $settings = array( 'environment' => 'sandbox', 'sandbox_api_key' => 'offline-test-value' );
    public function init_settings() {}
    public function get_option( $key, $default = '' ) { return $this->settings[$key] ?? $default; }
    public function is_available() { return true; }
    public function get_return_url( $order ) { return '/thankyou'; }
}
function __( $s, ...$args ) { return $s; }
function add_action( ...$args ) {}
function absint( $v ) { return abs( (int) $v ); }
function get_woocommerce_currency() { return 'MXN'; }
function wp_generate_uuid4() { return 'unique-test-reference'; }
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function wc_add_notice( ...$args ) {}
function WC() { return (object) array( 'cart' => null ); }
class WC_Order {
    public $meta = array(); public $saves = 0;
    public function has_status( $statuses ) { return in_array( 'on-hold', (array) $statuses, true ); }
    public function get_id() { return 7; }
    public function get_payment_method() { return 'tkila_sinergypay_spei'; }
    public function get_currency() { return 'MXN'; }
    public function get_total() { return '5.00'; }
    public function get_meta( $key ) { return $this->meta[$key] ?? ''; }
    public function update_meta_data( $key, $value ) { $this->meta[$key] = $value; }
    public function save() { ++$this->saves; }
    public function payment_complete() { throw new Exception( 'Must never credit payment.' ); }
    public function update_status() { throw new Exception( 'Webhook must not change status.' ); }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_request( $url, $args ) { ++$GLOBALS['http_count']; if ( ! empty( $GLOBALS['queue'] ) ) { $GLOBALS['response'] = array_shift( $GLOBALS['queue'] ); } $GLOBALS['http'] = array( $url, $args ); return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $r ) { return $r['status']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function add_option( $key, $value, ...$args ) { if ( isset( $GLOBALS['options'][$key] ) ) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][$key] ); }
function wc_get_orders( $args ) { return ( $args['meta_value'] ?? $args['meta_query'][0]['value'] ?? '' ) === 'wc-7-test' ? array( $GLOBALS['order'] ) : array(); }
function wc_get_order( $id ) { return $GLOBALS['order']; }
function wc_format_decimal( $value, $dp ) { return number_format( (float) $value, $dp, '.', '' ); }
function check( $condition, $label ) { if ( ! $condition ) { throw new Exception( $label ); } echo "PASS: $label\n"; }
foreach ( array( 'logger', 'api-client', 'order-lock', 'payment-confirmation', 'webhook-handler' ) as $file ) {
    require __DIR__ . '/../includes/class-' . $file . '.php';
}
use TKILA_SinergyPay\Api_Client;
use TKILA_SinergyPay\Webhook_Handler;
use TKILA_SinergyPay\Order_Lock;
$GLOBALS['http_count'] = 0;
$GLOBALS['response'] = array( 'status' => 200, 'body' => '{"rc":0,"data":{"id":"test"}}' );
$client = new Api_Client( 'sandbox', 'offline-test-value' );
check( $client->create_order( '5.00', 'Test', 'wc-7-test' )['id'] === 'test', 'Create order response' );
[$url, $args] = $GLOBALS['http'];
check( $url === 'https://sandbox.sinergypay.mx/v2/orders/' && $args['method'] === 'POST', 'Documented order endpoint' );
check( $args['headers']['Authorization'] === 'Basic ' . base64_encode( 'offline-test-value:' ) && isset( $args['headers']['User-Agent'] ) && $args['redirection'] === 0, 'Basic auth, User-Agent, redirects disabled' );
check( json_decode( $args['body'], true ) === array( 'amount' => '5.00', 'description' => 'Test', 'reference' => 'wc-7-test' ), 'Documented payload only' );
$client->create_clabe( 'abc/xyz' );
check( $GLOBALS['http'][0] === 'https://sandbox.sinergypay.mx/v2/orders/abc%2Fxyz/clabe' && $GLOBALS['http'][1]['body'] === '{}', 'CLABE endpoint and empty JSON object' );
check( is_wp_error( (new Api_Client( 'production', 'test' ))->create_clabe( 'abc' ) ), 'Production blocked' );
$GLOBALS['response']['body'] = 'invalid';
check( is_wp_error( $client->create_clabe( 'abc' ) ), 'Malformed API response rejected' );
$GLOBALS['response']['body'] = '{"rc":1,"data":{}}';
check( is_wp_error( $client->create_clabe( 'abc' ) ), 'API failure code rejected' );
$private = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
$pem = openssl_pkey_get_details( $private )['key'];
$payload = array( 'id' => 'ticket', 'currency' => 'MXN', 'amount' => '5.00', 'description' => 'Test', 'reference' => 'wc-7-test', 'date' => '2026-10-20T12:00:00+00:00' );
openssl_sign( implode( '|', $payload ), $signature, $private, OPENSSL_ALGO_SHA512 );
$payload['security'] = array( 'key' => 'test-key', 'version' => 1, 'signature' => base64_encode( $signature ) );
$keys = array( 'test-key' => $pem );
check( ! is_wp_error( Webhook_Handler::verify( $payload, $keys ) ), 'RSA SHA-512 signature verified' );
$changed = $payload; $changed['amount'] = '50.00';
check( is_wp_error( Webhook_Handler::verify( $changed, $keys ) ), 'Tampered signed amount rejected' );
$changed = $payload; $changed['security']['key'] = '../test-key';
check( is_wp_error( Webhook_Handler::verify( $changed, $keys ) ), 'Untrusted key rejected' );
$changed = $payload; $changed['security']['version'] = 2;
check( is_wp_error( Webhook_Handler::verify( $changed, $keys ) ), 'Unknown algorithm version rejected' );
$null = $payload; $null['reference'] = null;
$fields = $null; unset( $fields['security'] );
openssl_sign( implode( '|', $fields ), $sig, $private, OPENSSL_ALGO_SHA512 );
$null['security']['signature'] = base64_encode( $sig );
check( Webhook_Handler::verify( $null, $keys )['reference'] === '', 'Null encoded as empty signed value' );
$GLOBALS['options']['woocommerce_tkila_sinergypay_spei_settings'] = array( 'webhook_public_keys' => json_encode( $keys ) );
$order = new WC_Order();
foreach ( array( 'reference' => 'wc-7-test', 'description' => 'Test', 'amount' => '5.00', 'environment' => 'sandbox' ) as $k => $v ) { $order->meta['_tkila_sinergypay_' . $k] = $v; }
$GLOBALS['order'] = $order;
$payload['code'] = 'attacker-order'; $payload['payments'] = array( array( 'amount' => '1000000.00' ) );
$handler = new Webhook_Handler();
$r = $handler->handle( new WP_REST_Request( $payload ) );
check( $r->status === 202 && $r->data['payment_confirmed'] === false, 'Authenticated webhook recorded without payment approval' );
check( ! isset( $order->meta['_tkila_sinergypay_verified_notification']['payments'] ) && ! isset( $order->meta['_tkila_sinergypay_verified_notification']['code'] ), 'Unsigned data not persisted as trusted evidence' );
$saves = $order->saves;
$handler->handle( new WP_REST_Request( $payload ) );
check( $order->saves === $saves, 'Repeated webhook has no duplicate write' );
check( Order_Lock::acquire( 7 ) && ! Order_Lock::acquire( 7 ), 'Concurrent order work excluded' );
check( $handler->handle( new WP_REST_Request( $payload ) )->status === 503, 'Busy webhook retryable' );
Order_Lock::release( 7 );
$order->meta['_tkila_sinergypay_amount'] = '6.00';
check( $handler->handle( new WP_REST_Request( $payload ) )->status === 409, 'Order amount mismatch rejected' );
require __DIR__ . '/../includes/class-spei-gateway.php';
$gateway = new TKILA_SinergyPay\Spei_Gateway();
$GLOBALS['order'] = new WC_Order();
$GLOBALS['queue'] = array(
    array( 'status' => 200, 'body' => '{"rc":0,"data":{"id":"remote-order","amount":"5.00","currency":"MXN","reference_num":"123456"}}' ),
    array( 'status' => 200, 'body' => '{"rc":0,"data":{"clabe":"5461845114189012"}}' ),
);
check( $gateway->process_payment( 7 )['result'] === 'success', 'Checkout creates operation and CLABE' );
check( $GLOBALS['order']->get_meta( '_tkila_sinergypay_clabe' ) === '5461845114189012', 'API CLABE preserved without length assumption' );
$count = $GLOBALS['http_count'];
check( $gateway->process_payment( 7 )['result'] === 'success' && $count === $GLOBALS['http_count'], 'Checkout retry reuses operation and CLABE' );
$GLOBALS['order'] = new WC_Order();
$GLOBALS['queue'] = array( new WP_Error( 'timeout' ) );
check( $gateway->process_payment( 7 )['result'] === 'failure', 'Checkout transport error fails safely' );
$count = $GLOBALS['http_count'];
check( $gateway->process_payment( 7 )['result'] === 'failure' && $count === $GLOBALS['http_count'], 'Uncertain POST is never blindly retried' );
$GLOBALS['order'] = new WC_Order();
$GLOBALS['queue'] = array( array( 'status' => 200, 'body' => '{"rc":0,"data":{"id":"remote-order","amount":"9.00","currency":"MXN"}}' ) );
check( $gateway->process_payment( 7 )['result'] === 'failure', 'Remote amount mismatch fails checkout' );
$count = $GLOBALS['http_count'];
check( $gateway->process_payment( 7 )['result'] === 'failure' && $count === $GLOBALS['http_count'], 'Invalid operation cannot advance to CLABE on retry' );
echo "All tests passed.\n";
