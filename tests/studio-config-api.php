<?php
define( 'ABSPATH', __DIR__ );
define( 'SUDOMOCK_API_BASE', 'https://api.example.test' );
define( 'SUDOMOCK_VERSION', 'test' );

$sudomock_test_request  = null;
$sudomock_test_response = null;

function __( $value ) { return $value; }
function get_option() { return 'encrypted'; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_request( $url, $args ) {
    global $sudomock_test_request, $sudomock_test_response;
    $sudomock_test_request = array( 'url' => $url, 'args' => $args );
    return $sudomock_test_response;
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }

class WP_Error {
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->message = $message;
        $this->data    = $data;
    }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

class SudoMock_Encryption {
    public static function decrypt() { return 'server-only-key'; }
}

require_once __DIR__ . '/../includes/class-sudomock-api-client.php';

function sudomock_expect_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        throw new RuntimeException( $label . ': expected ' . json_encode( $expected ) . ', got ' . json_encode( $actual ) );
    }
}

// The version read by GET goes back with the PUT, the config as an object.
$sudomock_test_response = array(
    'response' => array( 'code' => 200 ),
    'body'     => json_encode( array( 'success' => true, 'config' => array( 'psdLayout' => 'full' ), 'config_version' => 5 ) ),
);
$read = SudoMock_API_Client::get_studio_config();
sudomock_expect_same( 5, $read['data']['config_version'], 'version read' );

$sudomock_test_response = array(
    'response' => array( 'code' => 200 ),
    'body'     => json_encode( array( 'success' => true, 'config' => array( 'psdLayout' => 'compact' ), 'config_version' => 6 ) ),
);
$saved = SudoMock_API_Client::update_studio_config( array( 'psdLayout' => 'compact' ), $read['data']['config_version'] );
sudomock_expect_same( 'PUT', $sudomock_test_request['args']['method'], 'PUT sent' );
sudomock_expect_same( 'https://api.example.test/api/v1/studio/config', $sudomock_test_request['url'], 'PUT address' );
sudomock_expect_same( '{"config_version":5,"config":{"psdLayout":"compact"}}', $sudomock_test_request['args']['body'], 'PUT body' );
sudomock_expect_same( array( true, 6 ), array( $saved['ok'], $saved['data']['config_version'] ), 'new version returned' );

// A save with nothing changed still sends an object.
SudoMock_API_Client::update_studio_config( array(), 6 );
sudomock_expect_same( '{"config_version":6,"config":{}}', $sudomock_test_request['args']['body'], 'empty config is an object' );

// A stale version's 409 reaches the caller with its status.
$sudomock_test_response = array(
    'response' => array( 'code' => 409 ),
    'body'     => json_encode( array( 'detail' => 'Studio config changed. Reload and try again.' ) ),
);
$stale = SudoMock_API_Client::update_studio_config( array( 'psdLayout' => 'compact' ), 4 );
sudomock_expect_same( array( false, 409 ), array( $stale['ok'], $stale['status'] ), 'stale version answers 409' );

echo "studio config API checks passed\n";
