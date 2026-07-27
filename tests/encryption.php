<?php
/** Minimal compatibility check for current and legacy saved API keys. */

if ( 'cli' !== PHP_SAPI ) {
    exit;
}

define( 'ABSPATH', dirname( __DIR__ ) );

function wp_salt( $scheme ) {
    return 'sudomock-encryption-test-salt';
}

function esc_html__( $text, $domain ) {
    return $text;
}

function wp_die( $message, $title = '', $args = array() ) {
    throw new RuntimeException( $message );
}

require dirname( __DIR__ ) . '/includes/class-sudomock-encryption.php';

$plaintext = 'sm_test_key';
$key       = hash( 'sha256', wp_salt( 'auth' ), true );

foreach ( array( '0123456789abcdef', 'AB::CDEFGHIJKLMN', 'ABCDEFGHIJKLMNO:' ) as $iv ) {
    $ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, 0, $iv );
    $legacy     = base64_encode( $iv . '::' . $ciphertext );
    if ( $plaintext !== SudoMock_Encryption::decrypt( $legacy ) ) {
        throw new RuntimeException( 'Legacy key recovery failed.' );
    }
}

if ( $plaintext !== SudoMock_Encryption::decrypt( SudoMock_Encryption::encrypt( $plaintext ) ) ) {
    throw new RuntimeException( 'Current key round-trip failed.' );
}

echo "encryption compatibility: ok\n";
