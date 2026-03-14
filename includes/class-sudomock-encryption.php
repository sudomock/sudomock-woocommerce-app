<?php
/**
 * API Key Encryption — encrypts/decrypts the SudoMock API key at rest.
 *
 * Uses wp_salt('auth') as the encryption key so each WP install has a unique key.
 * AES-256-CBC when OpenSSL is available, base64 obfuscation fallback.
 * API key is ONLY decrypted server-side for PHP→API calls, never sent to browser.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_Encryption {

    /** @var string */
    private static $cipher = 'aes-256-cbc';

    /**
     * Encrypt a value.
     *
     * @param string $value Plain text value.
     * @return string Encrypted value (base64-encoded).
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'SudoMock: OpenSSL extension is required for API key encryption. Please enable php-openssl.' );
            wp_die(
                esc_html__( 'SudoMock requires the PHP OpenSSL extension to securely store your API key. Please contact your hosting provider to enable it.', 'sudomock-product-customizer' ),
                esc_html__( 'SudoMock — Missing Requirement', 'sudomock-product-customizer' ),
                array( 'response' => 500 )
            );
        }

        $key       = self::get_key();
        $iv_length = openssl_cipher_iv_length( self::$cipher );
        $iv        = openssl_random_pseudo_bytes( $iv_length );
        $encrypted = openssl_encrypt( $value, self::$cipher, $key, 0, $iv );

        if ( false === $encrypted ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'SudoMock: openssl_encrypt() failed. Check OpenSSL configuration.' );
            return '';
        }

        // Store IV + encrypted data together
        return base64_encode( $iv . '::' . $encrypted ); // phpcs:ignore
    }

    /**
     * Decrypt a value.
     *
     * @param string $value Encrypted value.
     * @return string Decrypted plain text.
     */
    public static function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $decoded = base64_decode( $value, true ); // phpcs:ignore
        if ( false === $decoded ) {
            return '';
        }

        $key = self::get_key();

        // Check if it has our IV separator
        if ( function_exists( 'openssl_decrypt' ) && false !== strpos( $decoded, '::' ) ) {
            $parts = explode( '::', $decoded, 2 );
            if ( 2 !== count( $parts ) ) {
                return $decoded; // Was base64-only fallback
            }

            $decrypted = openssl_decrypt( $parts[1], self::$cipher, $key, 0, $parts[0] );
            return ( false !== $decrypted ) ? $decrypted : '';
        }

        // base64-only fallback decode
        return $decoded;
    }

    /**
     * Get encryption key derived from WP auth salt.
     *
     * @return string 32-byte key.
     */
    private static function get_key() {
        return hash( 'sha256', wp_salt( 'auth' ), true );
    }
}
