<?php
define( 'ABSPATH', __DIR__ );

final class JsonReply extends RuntimeException {
    public $success;
    public $data;
    public function __construct( $success, $data ) {
        parent::__construct( 'json reply' );
        $this->success = $success;
        $this->data    = $data;
    }
}

function __( $value ) { return $value; }
function add_action() {}
function check_ajax_referer() {}
function current_user_can() { return true; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_send_json_error( $data ) { throw new JsonReply( false, $data ); }
function wp_send_json_success( $data ) { throw new JsonReply( true, $data ); }

final class SudoMock_API_Client {
    public static $sent = null;
    public static function update_studio_config( $config ) {
        self::$sent = $config;
        return array( 'ok' => true, 'data' => array( 'config_version' => 1 ) );
    }
}

require_once __DIR__ . '/../includes/class-sudomock-admin.php';

function sudomock_save_config( array $config ) {
    $_POST['config']           = json_encode( $config );
    SudoMock_API_Client::$sent = null;
    try {
        SudoMock_Admin::get_instance()->ajax_save_studio_config();
    } catch ( JsonReply $reply ) {
        if ( ! $reply->success ) {
            throw new RuntimeException( 'Save failed: ' . json_encode( $reply->data ) );
        }
    }
    return SudoMock_API_Client::$sent;
}

function sudomock_expect_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        throw new RuntimeException( $label . ': expected ' . json_encode( $expected ) . ', got ' . json_encode( $actual ) );
    }
}

// Both seam settings are saved with the rest of the config.
sudomock_expect_same(
    array( 'showSeamCheck' => true, 'showSeamFix' => false ),
    sudomock_save_config( array( 'showSeamCheck' => true, 'showSeamFix' => false, 'unknownKey' => true ) ),
    'seam settings saved'
);

// Seam fix needs Seam check.
sudomock_expect_same(
    array( 'showSeamCheck' => false, 'showSeamFix' => false ),
    sudomock_save_config( array( 'showSeamCheck' => false, 'showSeamFix' => true ) ),
    'fix follows the check'
);

echo "studio config seam checks passed\n";
