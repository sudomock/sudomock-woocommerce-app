<?php
/**
 * SudoMock API Client — all server-to-server calls from WP → api.sudomock.com.
 *
 * SECURITY: API key is decrypted server-side, sent via x-api-key header.
 * The browser NEVER sees the API key. Studio sessions use short-lived, server-issued session tokens.
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
            $data = $response['data'];
            if ( ! empty( $data['mockups'] ) && is_array( $data['mockups'] ) ) {
                foreach ( $data['mockups'] as &$mockup ) {
                    $mockup['mockup_type'] = 'psd';
                    $mockup['layer_count'] = ! empty( $mockup['smart_objects'] ) && is_array( $mockup['smart_objects'] )
                        ? count( $mockup['smart_objects'] )
                        : 0;
                }
                unset( $mockup );
            }
            return array( 'ok' => true, 'data' => $data );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to fetch mockups.', 'sudomock-product-customizer' ) );
    }

    /**
     * List and normalize 2D mockups into the existing picker shape.
     *
     * @param array $args Query args (limit, offset).
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function list_2d_mockups( $args = array() ) {
        $query = array(
            'customizable_only' => 'true',
            'limit'              => isset( $args['limit'] ) ? absint( $args['limit'] ) : 50,
            'offset'             => isset( $args['offset'] ) ? absint( $args['offset'] ) : 0,
        );
        $response = self::request( 'GET', '/api/v1/sudoai/2d-mockups?' . http_build_query( $query ) );
        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'error' => $response->get_error_message() );
        }
        if ( empty( $response['success'] ) || ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
            return array( 'ok' => false, 'error' => __( 'Failed to fetch 2D mockups.', 'sudomock-product-customizer' ) );
        }

        $eligible = array_values( array_filter( $response['data'], static function ( $mockup ) {
            return is_array( $mockup )
                && isset( $mockup['customizable'] )
                && true === $mockup['customizable'];
        } ) );

        return array(
            'ok'   => true,
            'data' => array(
                'mockups' => array_map( array( __CLASS__, 'normalize_2d_mockup' ), $eligible ),
                'total'   => isset( $response['total'] ) ? absint( $response['total'] ) : count( $response['data'] ),
                'limit'   => isset( $response['limit'] ) ? absint( $response['limit'] ) : $query['limit'],
                'offset'  => isset( $response['offset'] ) ? absint( $response['offset'] ) : $query['offset'],
            ),
        );
    }

    /**
     * Combined PSD + 2D picker list with local search/pagination.
     *
     * @param array $args Query args (name, limit, offset).
     * @return array{ok: bool, data?: array, error?: string}
     */
    public static function list_picker_mockups( $args = array() ) {
        $all       = array();
        $successes = 0;

        foreach ( array( 'psd', '2d' ) as $type ) {
            $type_mockups = array();
            for ( $offset = 0; $offset < 10000; $offset += 100 ) {
                $page = 'psd' === $type
                    ? self::list_mockups( array( 'limit' => 100, 'offset' => $offset ) )
                    : self::list_2d_mockups( array( 'limit' => 100, 'offset' => $offset ) );
                if ( ! $page['ok'] || empty( $page['data'] ) ) {
                    break;
                }
                $items        = isset( $page['data']['mockups'] ) ? $page['data']['mockups'] : array();
                $type_mockups = array_merge( $type_mockups, $items );
                $total        = isset( $page['data']['total'] ) ? absint( $page['data']['total'] ) : count( $type_mockups );
                if (
                    ( '2d' === $type && $offset + 100 >= $total )
                    || ( 'psd' === $type && ( count( $type_mockups ) >= $total || count( $items ) < 100 ) )
                ) {
                    break;
                }
            }
            if ( isset( $page ) && $page['ok'] ) {
                ++$successes;
                $all = array_merge( $all, $type_mockups );
            }
        }

        if ( 0 === $successes ) {
            return array( 'ok' => false, 'error' => __( 'Failed to fetch mockups.', 'sudomock-product-customizer' ) );
        }

        $name = isset( $args['name'] ) ? trim( (string) $args['name'] ) : '';
        if ( '' !== $name ) {
            $all = array_values( array_filter( $all, static function ( $mockup ) use ( $name ) {
                return false !== stripos( isset( $mockup['name'] ) ? (string) $mockup['name'] : '', $name );
            } ) );
        }

        $limit  = isset( $args['limit'] ) ? max( 1, min( 50, absint( $args['limit'] ) ) ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        // ponytail: load the bounded account library before slicing; add merged
        // backend cursors only if large-account admin latency becomes real.
        return array(
            'ok'   => true,
            'data' => array(
                'mockups' => array_slice( $all, $offset, $limit ),
                'total'   => count( $all ),
                'limit'   => $limit,
                'offset'  => $offset,
            ),
        );
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
            $data   = $response->get_error_data();
            $status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 0;
            return array( 'ok' => false, 'error' => $response->get_error_message(), 'status' => $status );
        }

        if ( ! empty( $response['success'] ) && isset( $response['data'] ) ) {
            $response['data']['mockup_type'] = 'psd';
            return array( 'ok' => true, 'data' => $response['data'] );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to fetch mockup.', 'sudomock-product-customizer' ), 'status' => 0 );
    }

    /**
     * Get a normalized 2D mockup by UUID.
     *
     * @param string $uuid Mockup UUID.
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public static function get_2d_mockup( $uuid ) {
        $response = self::request( 'GET', '/api/v1/sudoai/2d-mockups/' . sanitize_text_field( $uuid ) );
        if ( is_wp_error( $response ) ) {
            $data   = $response->get_error_data();
            $status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 0;
            return array( 'ok' => false, 'error' => $response->get_error_message(), 'status' => $status );
        }
        if ( ! empty( $response['success'] ) && isset( $response['data'] ) && is_array( $response['data'] ) ) {
            return array( 'ok' => true, 'data' => self::normalize_2d_mockup( $response['data'] ) );
        }
        return array( 'ok' => false, 'error' => __( 'Failed to fetch mockup.', 'sudomock-product-customizer' ), 'status' => 0 );
    }

    /**
     * Get a mapped mockup using its stored, validated type.
     *
     * @param string $uuid Mockup UUID.
     * @param string $type Mockup type.
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public static function get_mapped_mockup( $uuid, $type ) {
        if ( 'psd' === $type ) {
            return self::get_mockup( $uuid );
        }
        if ( '2d' === $type ) {
            return self::get_2d_mockup( $uuid );
        }
        return array( 'ok' => false, 'error' => __( 'Invalid mockup type.', 'sudomock-product-customizer' ), 'status' => 400 );
    }

    /**
     * Delete stored order artwork/preview files (GDPR erasure).
     *
     * Batches to the backend's 500-URL limit, so a large erasure never fails
     * wholesale. Returns which URLs could not be deleted so the caller can
     * queue them for retry.
     *
     * @param string[] $urls Stored order-asset URLs to delete.
     * @return array{ok: bool, deleted: int, failed_urls: string[]}
     */
    public static function delete_order_assets( $urls ) {
        if ( empty( $urls ) || ! is_array( $urls ) ) {
            return array( 'ok' => true, 'deleted' => 0, 'failed_urls' => array() );
        }

        $urls        = array_values( array_unique( $urls ) );
        $deleted     = 0;
        $failed_urls = array();

        foreach ( array_chunk( $urls, 500 ) as $chunk ) {
            $response = self::request( 'POST', '/api/v1/artworks/delete', array(
                'urls' => $chunk,
            ) );
            if ( is_wp_error( $response ) ) {
                // Whole chunk unconfirmed — keep every URL for retry.
                $failed_urls = array_merge( $failed_urls, $chunk );
                continue;
            }
            $deleted += isset( $response['deleted'] ) ? (int) $response['deleted'] : 0;
        }

        return array(
            'ok'          => empty( $failed_urls ),
            'deleted'     => $deleted,
            'failed_urls' => $failed_urls,
        );
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
     * @param string $mockup_uuid   Mockup UUID.
     * @param string $mockup_type   Stored mockup type (psd|2d).
     * @param int    $product_id    WooCommerce product ID.
     * @param int    $variation_id  Bound variation ID, or 0 for a simple product.
     * @param string $allowed_origin Exact storefront origin allowed to host Studio.
     * @return array{ok: bool, session?: string, message_session_id?: string, bootstrap_secret?: string, expires_in?: int, error?: string}
     */
    public static function create_session( $mockup_uuid, $mockup_type, $product_id, $variation_id, $allowed_origin ) {
        if ( ! in_array( $mockup_type, array( 'psd', '2d' ), true ) ) {
            return array( 'ok' => false, 'error' => __( 'Invalid mockup type.', 'sudomock-product-customizer' ), 'status' => 400 );
        }
        $shop = wp_parse_url( $allowed_origin, PHP_URL_HOST );
        if ( ! is_string( $shop ) || '' === $shop ) {
            return array( 'ok' => false, 'error' => __( 'Invalid store.', 'sudomock-product-customizer' ), 'status' => 400 );
        }
        $response = self::request( 'POST', '/api/v1/studio/create-session', array(
            'mockup_type'    => $mockup_type,
            'session_kind'   => 'customize',
            'mockup_uuid'    => $mockup_uuid,
            'shop'           => strtolower( $shop ),
            'product_id'     => (string) $product_id,
            'variant_id'     => (string) $variation_id,
            'allowed_origin' => $allowed_origin,
            'action_id'      => 'add-to-cart',
        ) );

        if ( is_wp_error( $response ) ) {
            $data   = $response->get_error_data();
            $status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 0;
            $error_code = ( is_array( $data ) && isset( $data['error_code'] ) && is_string( $data['error_code'] ) )
                ? $data['error_code']
                : '';
            $safe_status = in_array( $status, array( 401, 403, 404, 409 ), true ) ? $status : 503;
            $safe_code = 409 === $safe_status
                && in_array( $error_code, array( 'SETUP_REQUIRED', 'MOCKUP_TERMINAL' ), true )
                ? $error_code
                : '';
            return array(
                'ok'         => false,
                'error'      => __( 'Could not open customizer. Please try again.', 'sudomock-product-customizer' ),
                'status'     => $safe_status,
                'error_code' => $safe_code,
            );
        }

        $valid_message_session = isset( $response['message_session_id'] )
            && is_string( $response['message_session_id'] )
            && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $response['message_session_id'] );
        $valid_bootstrap_secret = isset( $response['bootstrap_secret'] )
            && is_string( $response['bootstrap_secret'] )
            && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $response['bootstrap_secret'] );
        $valid_expiry = isset( $response['expires_in'] ) && is_numeric( $response['expires_in'] ) && (int) $response['expires_in'] > 0;

        if (
            ! empty( $response['success'] )
            && isset( $response['mockup_type'] )
            && $mockup_type === $response['mockup_type']
            && ! empty( $response['session'] )
            && is_string( $response['session'] )
            && strlen( $response['session'] ) <= 4096
            && $valid_message_session
            && $valid_bootstrap_secret
            && $valid_expiry
            && ! isset( $response['api_key'] )
            && ! isset( $response['proof_key'] )
        ) {
            return array(
                'ok'                 => true,
                'session'            => $response['session'],
                'message_session_id' => $response['message_session_id'],
                'bootstrap_secret'   => $response['bootstrap_secret'],
                'expires_in'         => (int) $response['expires_in'],
            );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to create session.', 'sudomock-product-customizer' ), 'status' => 0 );
    }

    /**
     * Consume one server-bound Studio action receipt.
     *
     * @param array $action Exact canonical action request.
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public static function consume_studio_action( $action ) {
        $response = self::request( 'POST', '/api/v1/studio/actions/consume', $action );
        if ( is_wp_error( $response ) ) {
            $data   = $response->get_error_data();
            $status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 0;
            return array(
                'ok'     => false,
                'error'  => $response->get_error_message(),
                'status' => $status,
            );
        }
        if (
            true !== ( isset( $response['success'] ) ? $response['success'] : false )
            || ! isset( $response['replayed'] )
            || ! is_bool( $response['replayed'] )
            || empty( $response['receipt'] )
            || ! is_array( $response['receipt'] )
        ) {
            return array( 'ok' => false, 'error' => __( 'Invalid action receipt.', 'sudomock-product-customizer' ), 'status' => 502 );
        }
        return array( 'ok' => true, 'data' => $response );
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

        // Backend returns {success, config, config_version} - not nested under 'data'
        if ( ! empty( $response['success'] ) && isset( $response['config'] ) ) {
            return array(
                'ok'   => true,
                'data' => array(
                    'config'         => $response['config'],
                    'config_version' => isset( $response['config_version'] ) ? $response['config_version'] : 0,
                ),
            );
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

        // Backend returns {success, config, config_version}
        if ( ! empty( $response['success'] ) ) {
            return array(
                'ok'   => true,
                'data' => array(
                    'config'         => isset( $response['config'] ) ? $response['config'] : array(),
                    'config_version' => isset( $response['config_version'] ) ? $response['config_version'] : 0,
                ),
            );
        }

        return array( 'ok' => false, 'error' => __( 'Failed to update studio config.', 'sudomock-product-customizer' ) );
    }

    /**
     * Normalize a 2D response item into the PSD picker shape.
     *
     * @param array $mockup Raw 2D mockup.
     * @return array
     */
    private static function normalize_2d_mockup( $mockup ) {
        $areas = ! empty( $mockup['print_areas'] ) && is_array( $mockup['print_areas'] )
            ? $mockup['print_areas']
            : ( ! empty( $mockup['quads'] ) && is_array( $mockup['quads'] ) ? $mockup['quads'] : array() );

        return array(
            'uuid'          => isset( $mockup['mockup_id'] ) ? (string) $mockup['mockup_id'] : '',
            'mockup_type'   => '2d',
            'name'          => ! empty( $mockup['name'] ) ? (string) $mockup['name'] : __( 'Untitled 2D mockup', 'sudomock-product-customizer' ),
            'thumbnail'     => ! empty( $mockup['thumbnail_url'] )
                ? (string) $mockup['thumbnail_url']
                : ( ! empty( $mockup['watermarked_source_url'] ) ? (string) $mockup['watermarked_source_url'] : '' ),
            'width'         => isset( $mockup['source_width'] ) ? absint( $mockup['source_width'] ) : null,
            'height'        => isset( $mockup['source_height'] ) ? absint( $mockup['source_height'] ) : null,
            'layer_count'   => count( $areas ),
            'smart_objects' => array(),
            'text_layers'   => array(),
            'thumbnails'    => array(),
        );
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
            $error = isset( $body['error'] ) && is_array( $body['error'] )
                ? $body['error']
                : array();
            $error_code = isset( $error['code'] ) && is_string( $error['code'] )
                ? $error['code']
                : '';
            $message = isset( $error['message'] ) && is_string( $error['message'] )
                ? $error['message']
                : ( isset( $body['detail'] ) && is_string( $body['detail'] ) ? $body['detail'] : sprintf(
                /* translators: %d: HTTP status code */
                __( 'API error (HTTP %d)', 'sudomock-product-customizer' ),
                $code
            ) );
            return new \WP_Error(
                'sudomock_api_error',
                $message,
                array(
                    'status'     => $code,
                    'error_code' => $error_code,
                )
            );
        }

        return is_array( $body ) ? $body : array();
    }
}
