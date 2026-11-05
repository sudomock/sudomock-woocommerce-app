<?php
define( 'ABSPATH', __DIR__ );

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
function add_action() {}
function check_ajax_referer() {}
function current_user_can() { return true; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_send_json_error( $data, $status = null ) { throw new JsonReply( false, $data, $status ); }
function wp_send_json_success( $data, $status = null ) { throw new JsonReply( true, $data, $status ); }

// Stands in for the API: records what a save sent and answers as told.
final class SudoMock_API_Client {
    public static $sent = null;
    public static $sent_version = null;
    public static $stored = array();
    public static $stored_version = 0;
    public static function get_studio_config() {
        return array( 'ok' => true, 'data' => array( 'config' => self::$stored, 'config_version' => self::$stored_version ) );
    }
    public static function update_studio_config( $config, $config_version ) {
        self::$sent         = $config;
        self::$sent_version = $config_version;
        if ( $config_version !== self::$stored_version ) {
            return array( 'ok' => false, 'error' => 'Studio config changed. Reload and try again.', 'status' => 409 );
        }
        return array( 'ok' => true, 'data' => array( 'config_version' => $config_version + 1 ) );
    }
}

require_once __DIR__ . '/../includes/class-sudomock-admin.php';

function sudomock_reply( $handler ) {
    try {
        SudoMock_Admin::get_instance()->$handler();
    } catch ( JsonReply $reply ) {
        return $reply;
    }
    throw new RuntimeException( $handler . ' sent no reply' );
}

function sudomock_save_reply( array $config, $config_version = 3 ) {
    $_POST = array( 'config' => json_encode( $config ) );
    if ( null !== $config_version ) {
        $_POST['config_version'] = (string) $config_version;
    }
    SudoMock_API_Client::$sent           = null;
    SudoMock_API_Client::$stored_version = 3;
    return sudomock_reply( 'ajax_save_studio_config' );
}

function sudomock_save_config( array $config ) {
    $reply = sudomock_save_reply( $config );
    if ( ! $reply->success ) {
        throw new RuntimeException( 'Save failed: ' . json_encode( $reply->data ) );
    }
    return SudoMock_API_Client::$sent;
}

function sudomock_load_config( array $stored ) {
    SudoMock_API_Client::$stored         = $stored;
    SudoMock_API_Client::$stored_version = 9;
    return sudomock_reply( 'ajax_get_studio_config' );
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

// The settings are read under their current names; a current name wins over the legacy one beside it.
$loaded = sudomock_load_config( array(
    'psdPrimaryActionLabel' => 'Buy now', 'buttonText' => 'Add to Cart',
    'psdShowFlip' => false, 'showFlip' => true,
    'psdLayout' => 'compact', 'layout' => 'full',
    'showSeamCheck' => true, 'showSeamFix' => true, 'logoUrl' => null,
) );
sudomock_expect_same( true, $loaded->success, 'config loaded' );
sudomock_expect_same(
    array( 'psdPrimaryActionLabel' => 'Buy now', 'psdShowFlip' => false, 'psdLayout' => 'compact', 'showSeamCheck' => true, 'showSeamFix' => true, 'logoUrl' => null ),
    $loaded->data['config'],
    'current names read, legacy names dropped'
);
sudomock_expect_same( 9, $loaded->data['config_version'], 'version read' );

// A config stored only under the legacy names is read through them.
sudomock_expect_same(
    array( 'loadingText' => 'Saving...', 'psdShowUndoRedo' => false, 'psdAutoRender' => false, 'psdAutoRenderDelay' => 1200 ),
    sudomock_load_config( array( 'addingText' => 'Saving...', 'showUndoRedo' => false, 'autoRender' => false, 'autoRenderDelay' => 1200 ) )->data['config'],
    'legacy names read'
);

// A save sends the current names with the version the settings were read at.
sudomock_expect_same(
    array( 'psdShowSize' => false, 'psdLayout' => 'compact', 'psdAutoRenderDelay' => 3000 ),
    sudomock_save_config( array( 'psdShowSize' => 0, 'psdLayout' => 'compact', 'psdAutoRenderDelay' => 9000, 'showSize' => false, 'layout' => 'full' ) ),
    'current names saved, legacy names dropped'
);
sudomock_expect_same( 3, SudoMock_API_Client::$sent_version, 'version sent' );
$saved = sudomock_save_reply( array( 'headerText' => 'Design yours' ) );
sudomock_expect_same( array( true, 4 ), array( $saved->success, $saved->data['config_version'] ), 'new version returned' );

// A changed CTA label, export and zoom setting reach the photo mockup editor too.
sudomock_expect_same(
    array(
        'psdPrimaryActionLabel' => 'Buy now', 'psdShowExportOptions' => false, 'psdShowZoomControls' => true,
        'twoDCustomizePrimaryActionLabel' => 'Buy now', 'twoDShowExport' => false, 'twoDShowZoom' => true,
    ),
    sudomock_save_config( array( 'psdPrimaryActionLabel' => 'Buy now', 'psdShowExportOptions' => false, 'psdShowZoomControls' => true ) ),
    'shared settings mirrored'
);

// A cleared logo is sent as null, never as an empty string.
sudomock_expect_same( array( 'logoUrl' => null ), sudomock_save_config( array( 'logoUrl' => '' ) ), 'empty logo sent as null' );
sudomock_expect_same( array( 'logoUrl' => null ), sudomock_save_config( array( 'logoUrl' => null ) ), 'null logo sent as null' );
sudomock_expect_same( array( 'headerText' => 'Hi' ), sudomock_save_config( array( 'headerText' => 'Hi' ) ), 'untouched logo not sent' );

// A save without a version is refused before it reaches the API.
$refused = sudomock_save_reply( array( 'headerText' => 'Design yours' ), null );
sudomock_expect_same( array( false, null ), array( $refused->success, SudoMock_API_Client::$sent ), 'save without a version refused' );

// A stale version answers a conflict the settings tab reads again.
$conflict = sudomock_save_reply( array( 'headerText' => 'Design yours' ), 2 );
sudomock_expect_same( array( false, 409, true ), array( $conflict->success, $conflict->status, $conflict->data['conflict'] ), 'stale version is a conflict' );
sudomock_expect_same(
    'These settings were changed elsewhere. The latest saved settings are shown. Make your changes again and save.',
    $conflict->data['message'],
    'conflict message'
);

echo "studio config settings checks passed\n";
