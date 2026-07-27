<?php
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

final class JsonReply extends RuntimeException {
    public $success;
    public $data;
    public $status;
    public function __construct( $success, $data, $status ) {
        parent::__construct( 'json reply' );
        $this->success = $success;
        $this->data    = $data;
        $this->status  = $status;
    }
}

function __( $value ) { return $value; }
function check_ajax_referer() {}
function home_url() { return 'https://shop.example/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wc_get_notices() { return array(); }
function wc_clear_notices() {}
function wc_get_cart_url() { return '/cart'; }
function wp_send_json_error( $data, $status = 400 ) { throw new JsonReply( false, $data, $status ); }
function wp_send_json_success( $data, $status = 200 ) { throw new JsonReply( true, $data, $status ); }

$transients = array();
function get_transient( $key ) {
    global $transients;
    return isset( $transients[ $key ] ) ? $transients[ $key ] : false;
}
function set_transient( $key, $value ) {
    global $transients;
    $transients[ $key ] = $value;
    return true;
}

final class ProductStub {
    private $type;
    private $parent;
    public function __construct( $type, $parent = 0 ) {
        $this->type = $type;
        $this->parent = $parent;
    }
    public function is_type( $type ) { return $type === $this->type; }
    public function get_parent_id() { return $this->parent; }
    public function get_id() { return 100; }
}

$products = array(
    100 => null,
    201 => null,
);
function wc_get_product( $id ) {
    global $products;
    return isset( $products[ $id ] ) ? $products[ $id ] : false;
}

final class CartStub {
    public $adds = array();
    public function add_to_cart( $product_id, $quantity, $variation_id, $attributes, $data ) {
        $this->adds[] = compact( 'product_id', 'quantity', 'variation_id', 'data' );
        return 'cart-key';
    }
    public function get_cart_contents_count() { return count( $this->adds ); }
}
$wc = (object) array( 'cart' => new CartStub() );
function WC() {
    global $wc;
    return $wc;
}

final class SudoMock_Product {
    public static $uuid = '44444444-4444-4444-8444-444444444444';
    public static $type = '2d';
    public static function is_customizable( $id ) { return 100 === $id; }
    public static function get_mockup_uuid( $id ) { return self::$uuid; }
    public static function get_mockup_type( $id ) { return self::$type; }
}

final class SudoMock_API_Client {
    public static $calls = array();
    public static $response = array();
    public static $session_response = array();
    public static function create_session() {
        return self::$session_response;
    }
    public static function consume_studio_action( $request ) {
        self::$calls[] = $request;
        return self::$response;
    }
}

require_once __DIR__ . '/../includes/class-sudomock-storefront.php';

function check( $condition, $message ) {
    if ( ! $condition ) throw new RuntimeException( $message );
}

function receipt( $type = '2d', $overrides = array() ) {
    return array_merge( array(
        'version'            => 1,
        'request_id'         => '22222222-2222-4222-8222-222222222222',
        'message_session_id' => '11111111-1111-4111-8111-111111111111',
        'type'               => 'studio.design-submitted',
        'mockup_type'        => $type,
        'session_kind'       => 'customize',
        'action_id'          => 'add-to-cart',
        'action_context'     => array(
            'shop'       => 'shop.example',
            'product_id' => '100',
            'variant_id' => '201',
        ),
        'mockup_uuid'        => SudoMock_Product::$uuid,
        'render_uuid'        => '55555555-5555-4555-8555-555555555555',
    ), $overrides );
}

function post_data( $overrides = array() ) {
    return array_merge( array(
        'action'             => 'sudomock_add_to_cart',
        'nonce'              => 'nonce',
        'version'            => '1',
        'request_id'         => '22222222-2222-4222-8222-222222222222',
        'message_session_id' => '11111111-1111-4111-8111-111111111111',
        'type'               => 'studio.design-submitted',
        'mockup_uuid'        => SudoMock_Product::$uuid,
        'render_uuid'        => '55555555-5555-4555-8555-555555555555',
        'action_id'          => 'add-to-cart',
        'quantity'           => '2',
    ), $overrides );
}

function reset_case( $type = '2d', $variation_parent = 100 ) {
    global $transients, $products, $wc;
    $transients = array();
    $products[100] = new ProductStub( 'variable' );
    $products[201] = new ProductStub( 'variation', $variation_parent );
    $wc->cart = new CartStub();
    SudoMock_Product::$type = $type;
    SudoMock_API_Client::$calls = array();
    SudoMock_API_Client::$session_response = array();
    SudoMock_API_Client::$response = array(
        'ok'   => true,
        'data' => array(
            'success'  => true,
            'replayed' => false,
            'receipt'  => receipt( $type ),
        ),
    );
    $key = 'sudomock_studio_' . hash( 'sha256', '11111111-1111-4111-8111-111111111111' );
    $transients[ $key ] = array(
        'product_id'   => 100,
        'variation_id' => 201,
        'mockup_uuid'  => SudoMock_Product::$uuid,
        'mockup_type'  => $type,
        'action_id'    => 'add-to-cart',
        'shop'         => 'shop.example',
    );
    $_SERVER['HTTP_ORIGIN'] = 'https://shop.example';
    $_POST = post_data();
}

function invoke() {
    $instance = ( new ReflectionClass( SudoMock_Storefront::class ) )->newInstanceWithoutConstructor();
    try {
        $instance->ajax_add_to_cart();
    } catch ( JsonReply $reply ) {
        return $reply;
    }
    throw new RuntimeException( 'no response' );
}

function invoke_session() {
    $instance = ( new ReflectionClass( SudoMock_Storefront::class ) )->newInstanceWithoutConstructor();
    try {
        $instance->ajax_create_session();
    } catch ( JsonReply $reply ) {
        return $reply;
    }
    throw new RuntimeException( 'no response' );
}

reset_case();
$_POST = array( 'product_id' => '100', 'variation_id' => '201' );
SudoMock_API_Client::$session_response = array(
    'ok'         => false,
    'error'      => 'private service detail',
    'status'     => 502,
    'error_code' => 'INTERNAL_FAILURE',
);
$reply = invoke_session();
check(
    array(
        'message' => 'Could not open customizer. Please try again.',
        'status'  => 503,
    ) === $reply->data,
    'raw session failure reached the browser wire'
);

reset_case();
$_POST = array( 'product_id' => '100', 'variation_id' => '201' );
SudoMock_API_Client::$session_response = array(
    'ok'         => false,
    'error'      => 'private service detail',
    'status'     => 409,
    'error_code' => 'SETUP_REQUIRED',
);
$reply = invoke_session();
check(
    array(
        'message'    => 'Could not open customizer. Please try again.',
        'status'     => 409,
        'error_code' => 'SETUP_REQUIRED',
    ) === $reply->data,
    'terminal session outcome was not safely preserved'
);

reset_case();
$reply = invoke();
check( true === $reply->success, '2D action did not add to cart' );
check( 1 === count( WC()->cart->adds ), 'cart add missing' );
check( 201 === WC()->cart->adds[0]['variation_id'], 'unbound variation used' );
check(
    '22222222-2222-4222-8222-222222222222'
        === WC()->cart->adds[0]['data']['sudomock_customization']['action_receipt_id'],
    'receipt id missing from cart'
);
check(
    array(
        'shop'       => 'shop.example',
        'product_id' => '100',
        'variant_id' => '201',
    ) === SudoMock_API_Client::$calls[0]['payload']['action_context'],
    'trusted action context was not constructed server-side'
);
check(
    array(
        'mockup_uuid'   => SudoMock_Product::$uuid,
        'render_uuid'   => '55555555-5555-4555-8555-555555555555',
        'action_id'     => 'add-to-cart',
        'action_context' => array(
            'shop'       => 'shop.example',
            'product_id' => '100',
            'variant_id' => '201',
        ),
    ) === SudoMock_API_Client::$calls[0]['payload'],
    'consume request did not use the exact opaque receipt handle'
);

$reply = invoke();
check( true === $reply->success, 'exact replay failed' );
check( 1 === count( WC()->cart->adds ), 'exact replay duplicated cart item' );

reset_case();
$_POST['mockup_uuid'] = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$reply = invoke();
check( false === $reply->success && 0 === count( SudoMock_API_Client::$calls ), 'forged mockup reached receipt service' );

reset_case( '2d', 999 );
$reply = invoke();
check( false === $reply->success && 0 === count( SudoMock_API_Client::$calls ), 'cross-product variation reached receipt service' );

foreach ( array(
    array( 'request_id' => 'bad' ),
    array( 'message_session_id' => 'bad' ),
    array( 'type' => 'studio.mockup-saved' ),
    array( 'action_id' => 'delete-product' ),
    array( 'unexpected_context' => 'forged' ),
) as $mutation ) {
    reset_case();
    $_POST = post_data( $mutation );
    $reply = invoke();
    check( false === $reply->success && 0 === count( SudoMock_API_Client::$calls ), 'invalid envelope reached receipt service' );
}

reset_case();
SudoMock_API_Client::$response['data']['receipt']['action_context']['variant_id'] = '999';
$reply = invoke();
check( false === $reply->success && 0 === count( WC()->cart->adds ), 'substituted receipt reached cart' );

reset_case();
SudoMock_API_Client::$response['data']['receipt']['internal_debug'] = 'must-not-cross';
$reply = invoke();
check( false === $reply->success && 0 === count( WC()->cart->adds ), 'private receipt field reached cart' );

reset_case();
SudoMock_API_Client::$response = array( 'ok' => false, 'status' => 503, 'error' => 'down' );
$reply = invoke();
check( false === $reply->success && 0 === count( WC()->cart->adds ), 'API failure reached cart' );

reset_case( 'psd' );
SudoMock_API_Client::$response['data']['receipt']['action_context']['variant_id'] = '201';
$reply = invoke();
check( true === $reply->success, 'legacy PSD mapping receipt failed' );

echo "action receipt checks passed\n";
