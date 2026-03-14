<?php
/**
 * SudoMock API Client — all server-to-server calls from WP → api.sudomock.com.
 *
 * SECURITY: API key is decrypted server-side, sent via x-api-key header.
 * The browser NEVER sees the API key. Studio sessions use JWT tokens.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_API_Client {

    /**
     * Get the decrypted API key.
     *
     * @return string|false API key or false if not configured.
     */
    public static function get_api_key() {
        $encrypted = get_option( 'sudomock_api_key', '' );
        if ( empty( $encrypted ) ) {
            return false;
        }
        $decrypted = SudoMock_Encryption::decrypt( $encrypted );
        return ! empty( $decrypted ) ? $decrypted : false;
    }

    /**
     * Save (encrypt) the API key.
     *
     * @param string $api_key Plain text API key.
     * @return bool
     */
    public static function save_api_key( $api_key ) {
        $encrypted = SudoMock_Encryption::encrypt( sanitize_text_field( $api_key ) );
        return update_option( 'sudomock_api_key', $encrypted );
    }

    /**
     * Validate API key by calling GET /api/v1/me.
     *
     * @param string|null $api_key Optional key to validate (uses stored if null).
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function validate_key( $api_key = null ) {
        if ( null === $api_key ) {
            $api_key = self::get_api_key();
        }
        if ( empty( $api_key ) ) {
            return array( 'ok' => false, 'error' => __( 'No API key configured.', 'sudomock-product-customizer' ) );
        }

        $response = self::request( 'GET', '/api/v1/me', array(), $api_key );
        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        if ( ! empty( $response['success'] ) && ! empty( $response['data'] ) ) {
            return array( 'ok' => true, 'data' => $response['data'] );
        }

        return array( 'ok' => false, 'error' => __( 'Invalid API key.', 'sudomock-product-customizer' ) );
    }

    /**
     * List mockups from the merchant's account.
     *
     * @param array $args Query args (name, limit, offset).
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function list_mockups( $args = array() ) {
        $query = array();
        if ( ! empty( $args['name'] ) ) {
            $query['name'] = $args['name'];
        }
        $query['limit']  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
        $query['offset'] = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $path     = '/api/v1/mockups?' . http_build_query( $query );
        $response = self::request( 'GET', $path );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        if ( ! empty( $response['success'] ) && isset( $response['data'] ) ) {
            return array( 'ok' => true, 'data' => $response['data'] );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to fetch mockups.', 'sudomock-product-customizer' ) );
    }

    /**
     * Get a single mockup by UUID.
     *
     * @param string $uuid Mockup UUID.
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function get_mockup( $uuid ) {
        if ( empty( $uuid ) ) {
            return array( 'ok' => false, 'error' => __( 'No mockup UUID provided.', 'sudomock-product-customizer' ) );
        }

        $path     = '/api/v1/mockups/' . sanitize_text_field( $uuid );
        $response = self::request( 'GET', $path );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        if ( ! empty( $response['success'] ) && isset( $response['data'] ) ) {
            return array( 'ok' => true, 'data' => $response['data'] );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to fetch mockup.', 'sudomock-product-customizer' ) );
    }

    /**
     * Notify backend about WooCommerce disconnect.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function notify_disconnect() {
        $response = self::request( 'POST', '/api/v1/woocommerce/disconnect' );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        return array( 'ok' => true );
    }

    /**
     * Create a Studio session for the iframe.
     * Returns an opaque session token (sess_xxx) - no API key in the token.
     *
     * @param string $mockup_uuid Mockup UUID.
     * @param int    $product_id  WooCommerce product ID.
     * @return array{ok: bool, session?: string, displayMode?: string, error?: string}
     */
    public static function create_session( $mockup_uuid, $product_id = 0 ) {
        $response = self::request( 'POST', '/api/v1/studio/create-session', array(
            'mockup_uuid' => $mockup_uuid,
            'product_id'  => (string) $product_id,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        if ( ! empty( $response['success'] ) && ! empty( $response['session'] ) ) {
            return array(
                'ok'          => true,
                'session'     => $response['session'],
                'displayMode' => isset( $response['displayMode'] ) ? $response['displayMode'] : 'iframe',
            );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to create session.', 'sudomock-product-customizer' ) );
    }

    /**
     * Get Studio config from the backend.
     *
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function get_studio_config() {
        $response = self::request( 'GET', '/api/v1/studio/config' );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        if ( ! empty( $response['success'] ) && isset( $response['data'] ) ) {
            return array( 'ok' => true, 'data' => $response['data'] );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to fetch studio config.', 'sudomock-product-customizer' ) );
    }

    /**
     * Update Studio config on the backend.
     *
     * @param array $config Studio configuration key-value pairs.
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function update_studio_config( $config ) {
        $response = self::request( 'PUT', '/api/v1/studio/config', array( 'config' => $config ) );

        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }

        if ( ! empty( $response['success'] ) ) {
            return array( 'ok' => true, 'data' => isset( $response['data'] ) ? $response['data'] : array() );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to update studio config.', 'sudomock-product-customizer' ) );
    }

    /**
     * Core HTTP request method — all API calls route through here.
     *
     * @param string      $method   HTTP method (GET, POST, PUT, DELETE).
     * @param string      $path     API path (e.g. /api/v1/me).
     * @param array       $body     Request body (for POST/PUT).
     * @param string|null $api_key  Override API key.
     * @return array|WP_Error Decoded response body or WP_Error.
     */
    private static function request( $method, $path, $body = array(), $api_key = null ) {
        if ( null === $api_key ) {
            $api_key = self::get_api_key();
        }
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'sudomock_no_key', __( 'API key not configured.', 'sudomock-product-customizer' ) );
        }

        $url  = SUDOMOCK_API_BASE . $path;
        $args = array(
            'method'  => $method,
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key'   => $api_key,
                'User-Agent'  => 'SudoMock-WooCommerce/' . SUDOMOCK_VERSION,
            ),
        );

        if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = isset( $body['detail'] ) ? $body['detail'] : sprintf(
                /* translators: %d: HTTP status code */
                __( 'API error (HTTP %d)', 'sudomock-product-customizer' ),
                $code
            );
            return new \WP_Error( 'sudomock_api_error', $message, array( 'status' => $code ) );
        }

        return is_array( $body ) ? $body : array();
    }
}
