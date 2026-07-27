<?php
define( 'ABSPATH', __DIR__ );
define( 'SUDOMOCK_API_BASE', 'https://api.example.test' );
define( 'SUDOMOCK_VERSION', 'test' );

$sudomock_test_url = '';
$sudomock_test_response = array(
    'response' => array( 'code' => 200 ),
    'body'     => wp_json_encode( array(
        'success' => true,
        'data'    => array(
            array(
                'mockup_id'    => '11111111-1111-4111-8111-111111111111',
                'name'         => 'Eligible zero-row full surface',
                'customizable' => true,
                'print_areas'  => array(),
            ),
            array(
                'mockup_id'    => '22222222-2222-4222-8222-222222222222',
                'name'         => 'Legacy missing outcome',
                'print_areas'  => array( array( 'print_area_id' => 'front' ) ),
            ),
            array(
                'mockup_id'    => '33333333-3333-4333-8333-333333333333',
                'name'         => 'Malformed outcome',
                'customizable' => 'true',
                'print_areas'  => array( array( 'print_area_id' => 'front' ) ),
            ),
            array(
                'mockup_id'    => '44444444-4444-4444-8444-444444444444',
                'name'         => 'Explicitly ineligible',
                'customizable' => false,
                'print_areas'  => array( array( 'print_area_id' => 'front' ) ),
            ),
        ),
        'total'    => 1,
        'limit'    => 100,
        'offset'   => 0,
    ) ),
);

function __( $value ) {
    return $value;
}

function absint( $value ) {
    return abs( (int) $value );
}

function get_option() {
    return 'encrypted';
}

function is_wp_error( $value ) {
    return $value instanceof WP_Error;
}

function wp_json_encode( $value ) {
    return json_encode( $value );
}

function wp_parse_url( $url, $component = -1 ) {
    return parse_url( $url, $component );
}

function wp_remote_request( $url ) {
    global $sudomock_test_response, $sudomock_test_url;
    $sudomock_test_url = $url;
    return $sudomock_test_response;
}

function wp_remote_retrieve_response_code( $response ) {
    return $response['response']['code'];
}

function wp_remote_retrieve_body( $response ) {
    return $response['body'];
}

class WP_Error {
    private $message;
    private $data;

    public function __construct( $code = '', $message = '', $data = null ) {
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_message() {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
    }
}

class SudoMock_Encryption {
    public static function decrypt() {
        return 'server-only-key';
    }
}

require_once __DIR__ . '/../includes/class-sudomock-api-client.php';

$result = SudoMock_API_Client::list_2d_mockups( array(
    'limit'  => 100,
    'offset' => 0,
) );

if ( empty( $result['ok'] ) ) {
    throw new RuntimeException( 'Expected eligible list success.' );
}
if ( 1 !== count( $result['data']['mockups'] ) ) {
    throw new RuntimeException( '2D eligibility did not fail closed.' );
}
if ( '11111111-1111-4111-8111-111111111111' !== $result['data']['mockups'][0]['uuid'] ) {
    throw new RuntimeException( 'Eligible zero-row full surface was not selectable.' );
}
if ( 0 !== $result['data']['mockups'][0]['layer_count'] ) {
    throw new RuntimeException( 'Zero-row full surface must remain zero-row.' );
}

$query = array();
parse_str( (string) parse_url( $sudomock_test_url, PHP_URL_QUERY ), $query );
if ( 'true' !== ( isset( $query['customizable_only'] ) ? $query['customizable_only'] : null ) ) {
    throw new RuntimeException( 'customizable_only=true was not requested.' );
}

$sudomock_test_response = array(
    'response' => array( 'code' => 409 ),
    'body'     => wp_json_encode( array(
        'success' => false,
        'error'   => array(
            'code'      => 'SETUP_REQUIRED',
            'message'   => 'Setup required.',
            'retryable' => false,
        ),
    ) ),
);
$session = SudoMock_API_Client::create_session(
    '11111111-1111-4111-8111-111111111111',
    '2d',
    10,
    0,
    'https://shop.example'
);
if (
    409 !== ( isset( $session['status'] ) ? $session['status'] : null )
    || 'SETUP_REQUIRED' !== ( isset( $session['error_code'] ) ? $session['error_code'] : null )
    || 'Could not open customizer. Please try again.' !== ( isset( $session['error'] ) ? $session['error'] : null )
) {
    throw new RuntimeException( 'Typed 409 outcome was not safely preserved.' );
}

$sudomock_test_response = array(
    'response' => array( 'code' => 502 ),
    'body'     => wp_json_encode( array(
        'success' => false,
        'error'   => array(
            'code'    => 'INTERNAL_FAILURE',
            'message' => 'private service detail',
            'debug'   => array( 'worker' => 'internal-worker-7' ),
        ),
    ) ),
);
$session = SudoMock_API_Client::create_session(
    '11111111-1111-4111-8111-111111111111',
    '2d',
    10,
    0,
    'https://shop.example'
);
if (
    503 !== ( isset( $session['status'] ) ? $session['status'] : null )
    || '' !== ( isset( $session['error_code'] ) ? $session['error_code'] : null )
    || 'Could not open customizer. Please try again.' !== ( isset( $session['error'] ) ? $session['error'] : null )
    || false !== strpos( wp_json_encode( $session ), 'private service detail' )
) {
    throw new RuntimeException( 'Backend failure detail reached the storefront result.' );
}

$studio_response = array(
    'success'            => true,
    'mockup_type'        => '2d',
    'session'            => 'sess_opaque',
    'message_session_id' => '11111111-1111-4111-8111-111111111111',
    'bootstrap_secret'   => str_repeat( 'A', 43 ),
    'expires_in'         => 900,
);
$sudomock_test_response = array(
    'response' => array( 'code' => 200 ),
    'body'     => wp_json_encode( $studio_response ),
);
$session = SudoMock_API_Client::create_session(
    '11111111-1111-4111-8111-111111111111',
    '2d',
    10,
    0,
    'https://shop.example'
);
if ( empty( $session['ok'] ) || isset( $session['displayMode'] ) ) {
    throw new RuntimeException( 'Iframe-only Studio response was rejected or leaked a fixed mode.' );
}

echo "API client eligibility checks passed\n";
