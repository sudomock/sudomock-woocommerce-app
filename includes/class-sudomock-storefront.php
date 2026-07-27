<?php
/**
 * Storefront — renders the customize button and Studio iframe modal on product pages.
 *
 * SECURITY: Creates session via WP AJAX → PHP → API (server-to-server).
 * API key NEVER reaches the browser. Browser only gets a short-lived, opaque session token; the API key stays server-side.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_Storefront {

    /** @var self|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Block themes use Site Editor block, classic themes use PHP hook + Customizer.
        // Skip PHP hook registration for block themes to avoid duplicate buttons.
        if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
            $position = SudoMock_Customizer::get( 'position' );
            if ( 'shortcode' !== $position ) {
                $hook = 'woocommerce_after_add_to_cart_button';
                $priority = 20;
                if ( 'before_add_to_cart' === $position ) {
                    $hook = 'woocommerce_before_add_to_cart_button';
                    $priority = 10;
                } elseif ( 'after_summary' === $position ) {
                    $hook = 'woocommerce_after_single_product_summary';
                    $priority = 5;
                }
                add_action( $hook, array( $this, 'render_button' ), $priority );
            }
        }

        // Shortcode always available for manual placement
        add_shortcode( 'sudomock_button', array( $this, 'shortcode_button' ) );

        // AJAX: Create session (server-side, for logged-in and guest users)
        add_action( 'wp_ajax_sudomock_create_session', array( $this, 'ajax_create_session' ) );
        add_action( 'wp_ajax_nopriv_sudomock_create_session', array( $this, 'ajax_create_session' ) );

        // AJAX: Add to cart with customization data
        add_action( 'wp_ajax_sudomock_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_sudomock_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

        // AJAX: Mint a fresh nonce. On full-page-cached storefronts the nonce
        // baked into the page can go stale and break Customize/add-to-cart with
        // a generic error; the storefront fetches a fresh one before acting.
        // Minting a nonce is not a state-changing action, so this endpoint
        // itself needs no nonce.
        add_action( 'wp_ajax_sudomock_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
        add_action( 'wp_ajax_nopriv_sudomock_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );

        // Enqueue storefront assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Resolve the WooCommerce $product global to a real WC_Product.
     * The global can still be a post slug (string) depending on hook timing;
     * calling methods on it then fatals (same class of bug as the fixed
     * enqueue_assets crash).
     *
     * @return WC_Product|null
     */
    private static function current_product() {
        global $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce standard global
        if ( $product instanceof WC_Product ) {
            return $product;
        }
        $resolved = wc_get_product( get_the_ID() );
        return $resolved instanceof WC_Product ? $resolved : null;
    }

    /**
     * Render the customize button on customizable products.
     */
    public function render_button() {
        $product = self::current_product();
        if ( ! $product || ! SudoMock_Product::is_customizable( $product->get_id() ) ) {
            return;
        }

        $this->output_button( $product );
    }

    /**
     * Shortcode: [sudomock_button]
     *
     * @return string
     */
    public function shortcode_button() {
        $product = self::current_product();
        if ( ! $product || ! SudoMock_Product::is_customizable( $product->get_id() ) ) {
            return '';
        }
        ob_start();
        $this->output_button( $product );
        return ob_get_clean();
    }

    /**
     * Output the button HTML with all Customizer options applied.
     *
     * @param WC_Product $product
     */
    private function output_button( $product ) {
        $opts = SudoMock_Customizer::get_all();

        // Icon SVGs
        $icons = array(
            'pencil'  => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
            'palette' => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.14-.74-.39-1.04-.24-.3-.39-.65-.39-1.04 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-5.52-4.48-9.96-10-9.96z"/></svg>',
            'wand'    => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 4-1 1 4 4 1-1a2.83 2.83 0 1 0-4-4z"/><path d="m13 6-8.5 8.5a2.12 2.12 0 1 0 3 3L16 9"/><path d="m8 2 1 4-4 1"/><path d="m2 8 4-1 1-4"/></svg>',
            'brush'   => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>',
            'sparkle' => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
        );

        $icon_html = '';
        if ( $opts['show_icon'] && isset( $icons[ $opts['icon_style'] ] ) ) {
            $icon_html = $icons[ $opts['icon_style'] ];
        }

        // Whitelist CSS values to prevent injection.
        $sudomock_allowed_weights   = array( '500', '600', '700' );
        $sudomock_allowed_transform = array( 'none', 'uppercase' );
        $sudomock_allowed_align     = array( 'left', 'center', 'right' );

        $sudomock_font_weight   = in_array( $opts['font_weight'], $sudomock_allowed_weights, true ) ? $opts['font_weight'] : '600';
        $sudomock_text_transform = in_array( $opts['text_transform'], $sudomock_allowed_transform, true ) ? $opts['text_transform'] : 'none';
        $sudomock_alignment     = in_array( $opts['alignment'], $sudomock_allowed_align, true ) ? $opts['alignment'] : 'center';

        $btn_style = sprintf(
            'display:inline-flex;align-items:center;justify-content:center;gap:8px;'
            . 'width:%s;min-height:48px;box-sizing:border-box;'
            . 'padding:%dpx %dpx;font-size:%dpx;font-weight:%s;'
            . 'font-family:inherit;line-height:1.4;'
            . 'border:%dpx solid %s;border-radius:%dpx;'
            . 'background:%s;color:%s;'
            . 'cursor:pointer;transition:all 0.15s ease;'
            . 'text-transform:%s;-webkit-font-smoothing:antialiased;'
            . '%s',
            $opts['full_width'] ? '100%' : 'auto',
            absint( $opts['padding_y'] ), absint( $opts['padding_x'] ),
            absint( $opts['font_size'] ), $sudomock_font_weight,
            absint( $opts['border_width'] ), esc_attr( $opts['border_color'] ),
            absint( $opts['border_radius'] ),
            esc_attr( $opts['bg_color'] ), esc_attr( $opts['text_color'] ),
            $sudomock_text_transform,
            $opts['shadow'] ? 'box-shadow:0 2px 8px rgba(0,0,0,0.12);' : ''
        );

        ?>
        <div class="sudomock-customizer-root" style="margin:<?php echo intval( $opts['margin_top'] ); ?>px 0 <?php echo intval( $opts['margin_bottom'] ); ?>px;text-align:<?php echo esc_attr( $sudomock_alignment ); ?>;">

            <?php if ( $opts['divider_top'] ) : ?>
                <hr class="sudomock-divider-top" style="border:none;border-top:1px solid <?php echo esc_attr( $opts['divider_color'] ); ?>;margin:0 0 <?php echo intval( $opts['margin_top'] ); ?>px;">
            <?php endif; ?>

            <?php if ( ! empty( $opts['heading'] ) ) : ?>
                <p class="sudomock-heading" style="font-size:14px;font-weight:600;color:<?php echo esc_attr( $opts['heading_color'] ); ?>;margin:0 0 8px;font-family:inherit;">
                    <?php echo esc_html( $opts['heading'] ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $opts['subtext'] ) ) : ?>
                <p class="sudomock-subtext" style="font-size:13px;color:<?php echo esc_attr( $opts['subtext_color'] ); ?>;margin:0 0 10px;line-height:1.5;font-family:inherit;">
                    <?php echo esc_html( $opts['subtext'] ); ?>
                </p>
            <?php endif; ?>

            <button type="button"
                    class="sudomock-customize-btn button"
                    data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                    style="<?php echo esc_attr( $btn_style ); ?>"
            >
                <?php
                $sudomock_svg_allowed = array(
                    'svg'    => array( 'aria-hidden' => true, 'width' => true, 'height' => true, 'viewBox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
                    'path'   => array( 'd' => true, 'fill' => true, 'stroke' => true ),
                    'circle' => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ),
                );
                if ( $opts['show_icon'] && 'left' === $opts['icon_position'] ) echo wp_kses( $icon_html, $sudomock_svg_allowed );
                ?>
                <?php echo esc_html( $opts['label'] ); ?>
                <?php if ( $opts['show_icon'] && 'right' === $opts['icon_position'] ) echo wp_kses( $icon_html, $sudomock_svg_allowed ); ?>
            </button>

            <?php if ( ! empty( $opts['bottom_text'] ) ) : ?>
                <p class="sudomock-bottom-text" style="font-size:11px;color:<?php echo esc_attr( $opts['subtext_color'] ); ?>;margin:6px 0 0;font-family:inherit;">
                    <?php echo esc_html( $opts['bottom_text'] ); ?>
                </p>
            <?php endif; ?>

            <?php if ( $opts['divider_bottom'] ) : ?>
                <hr class="sudomock-divider-bottom" style="border:none;border-top:1px solid <?php echo esc_attr( $opts['divider_color'] ); ?>;margin:<?php echo intval( $opts['margin_bottom'] ); ?>px 0 0;">
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue storefront JS/CSS only on customizable product pages.
     */
    public function enqueue_assets() {
        if ( ! is_product() ) {
            return;
        }

        $product = self::current_product();
        if ( ! $product || ! SudoMock_Product::is_customizable( $product->get_id() ) ) {
            return;
        }

        wp_enqueue_style(
            'sudomock-storefront',
            SUDOMOCK_PLUGIN_URL . 'assets/css/storefront.css',
            array(),
            SUDOMOCK_VERSION
        );

        // Add dynamic hover styles via wp_add_inline_style (avoids inline <style> tags).
        $sudomock_opts       = SudoMock_Customizer::get_all();
        $sudomock_hover_css  = '.sudomock-customize-btn:hover {';
        $sudomock_hover_css .= 'background:' . esc_attr( $sudomock_opts['hover_bg_color'] ) . ' !important;';
        $sudomock_hover_css .= 'color:' . esc_attr( $sudomock_opts['hover_text_color'] ) . ' !important;';
        $sudomock_hover_css .= '}';
        wp_add_inline_style( 'sudomock-storefront', $sudomock_hover_css );

        wp_enqueue_script(
            'sudomock-storefront',
            SUDOMOCK_PLUGIN_URL . 'assets/js/storefront.js',
            array(),  // No jQuery — vanilla JS only
            SUDOMOCK_VERSION,
            array( 'in_footer' => true, 'strategy' => 'defer' )
        );

        wp_localize_script( 'sudomock-storefront', 'sudomockStorefront', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'sudomock_storefront' ),
            'studioBase'  => SUDOMOCK_STUDIO_BASE,
            'i18n'        => array(
                'loading'          => __( 'Loading...', 'sudomock-product-customizer' ),
                'unavailable'      => __( 'Customizer is temporarily unavailable. Please try again.', 'sudomock-product-customizer' ),
                'addedToCart'       => __( 'Added to cart!', 'sudomock-product-customizer' ),
                'cartError'        => __( 'Failed to add to cart. Please try again.', 'sudomock-product-customizer' ),
                'sessionError'     => __( 'Could not open customizer. Please try again.', 'sudomock-product-customizer' ),
                'networkCartError' => __( 'Network error adding to cart.', 'sudomock-product-customizer' ),
                'chooseOptions'    => __( 'Please choose the product options first.', 'sudomock-product-customizer' ),
                'noProduct'        => __( 'Cannot add to cart: no product ID.', 'sudomock-product-customizer' ),
                'missingData'      => __( 'Missing product-id or mockup-uuid on button.', 'sudomock-product-customizer' ),
            ),
        ) );
    }

    /**
     * AJAX: Return a fresh nonce for the storefront actions. Safe without a
     * nonce check — it only mints a token, it changes no state.
     */
    public function ajax_refresh_nonce() {
        wp_send_json_success( array( 'nonce' => wp_create_nonce( 'sudomock_storefront' ) ) );
    }

    /**
     * Exact storefront origin used by the Studio parent bridge.
     *
     * @return string
     */
    private static function storefront_origin() {
        return self::url_origin( home_url( '/' ) );
    }

    /**
     * Normalize an HTTP(S) URL to scheme + host + optional port.
     *
     * @param string $url URL or Origin header.
     * @return string
     */
    private static function url_origin( $url ) {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }
        $scheme = strtolower( $parts['scheme'] );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return '';
        }
        $origin = $scheme . '://' . strtolower( $parts['host'] );
        if ( ! empty( $parts['port'] ) ) {
            $port = absint( $parts['port'] );
            if ( ( 'http' === $scheme && 80 !== $port ) || ( 'https' === $scheme && 443 !== $port ) ) {
                $origin .= ':' . $port;
            }
        }
        return $origin;
    }

    /**
     * Fail closed when a guest AJAX write did not originate on this storefront.
     *
     * WordPress guest nonces share user ID 0, so the nonce alone is not a
     * cross-site boundary. Modern fetch sends Origin; Referer is the fallback.
     *
     * @return bool
     */
    private static function request_has_storefront_origin() {
        $expected = self::storefront_origin();
        if ( '' === $expected ) {
            return false;
        }

        if ( isset( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ) {
            $origin = self::url_origin( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
            return '' !== $origin && hash_equals( $expected, $origin );
        }
        if ( isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ) {
            $origin = self::url_origin( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
            return '' !== $origin && hash_equals( $expected, $origin );
        }
        return false;
    }

    /**
     * Transient key for a server-minted Studio message session.
     *
     * @param string $message_session_id Message-session UUID.
     * @return string
     */
    private static function session_binding_key( $message_session_id ) {
        return 'sudomock_studio_' . hash( 'sha256', $message_session_id );
    }

    /**
     * Transient key for a completed cart action retry.
     *
     * @param string $request_id Studio action request UUID.
     * @return string
     */
    private static function cart_action_key( $request_id ) {
        return 'sudomock_cart_' . hash( 'sha256', $request_id );
    }

    /**
     * Whether a value is a canonical UUID accepted by the Studio protocol.
     *
     * @param string $value Candidate UUID.
     * @return bool
     */
    private static function is_protocol_uuid( $value ) {
        return is_string( $value )
            && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value );
    }

    /**
     * AJAX: Create Studio session (PHP → API, server-to-server).
     * Browser gets the opaque session and its one-time parent bootstrap secret.
     * The merchant API key never leaves this server.
     */
    public function ajax_create_session() {
        check_ajax_referer( 'sudomock_storefront', 'nonce' );
        if ( ! self::request_has_storefront_origin() ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'sudomock-product-customizer' ) ), 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $product    = $product_id ? wc_get_product( $product_id ) : false;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

        if ( ! $product || ! SudoMock_Product::is_customizable( $product_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'sudomock-product-customizer' ) ) );
        }
        if ( $product->is_type( 'variable' ) ) {
            $variation = $variation_id ? wc_get_product( $variation_id ) : false;
            if (
                ! $variation
                || ! $variation->is_type( 'variation' )
                || (int) $variation->get_parent_id() !== $product_id
            ) {
                wp_send_json_error( array( 'message' => __( 'Invalid product option.', 'sudomock-product-customizer' ) ), 400 );
            }
        } elseif ( 0 !== $variation_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product option.', 'sudomock-product-customizer' ) ), 400 );
        }

        // Resolve the binding on the server. A forged browser request cannot
        // pair another mockup with this product.
        $mockup_uuid   = SudoMock_Product::get_mockup_uuid( $product_id );
        $mockup_type   = SudoMock_Product::get_mockup_type( $product_id );
        $allowed_origin = self::storefront_origin();
        if ( empty( $mockup_uuid ) || empty( $mockup_type ) || empty( $allowed_origin ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'sudomock-product-customizer' ) ) );
        }

        $result = SudoMock_API_Client::create_session(
            $mockup_uuid,
            $mockup_type,
            $product_id,
            $variation_id,
            $allowed_origin
        );

        if ( ! $result['ok'] ) {
            $status = isset( $result['status'] ) ? (int) $result['status'] : 503;
            $status = in_array( $status, array( 401, 403, 404, 409 ), true ) ? $status : 503;
            $error = array(
                'message' => __( 'Could not open customizer. Please try again.', 'sudomock-product-customizer' ),
                'status'  => $status,
            );
            if (
                409 === $status
                && isset( $result['error_code'] )
                && in_array( $result['error_code'], array( 'SETUP_REQUIRED', 'MOCKUP_TERMINAL' ), true )
            ) {
                $error['error_code'] = $result['error_code'];
            }
            wp_send_json_error( $error );
        }

        $ttl = max( 1, min( DAY_IN_SECONDS, (int) $result['expires_in'] ) );
        $binding_saved = set_transient(
            self::session_binding_key( $result['message_session_id'] ),
            array(
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'mockup_uuid'  => $mockup_uuid,
                'mockup_type'  => $mockup_type,
                'action_id'    => 'add-to-cart',
                'shop'         => strtolower( (string) wp_parse_url( $allowed_origin, PHP_URL_HOST ) ),
            ),
            $ttl
        );
        if ( ! $binding_saved ) {
            wp_send_json_error( array(
                'message' => __( 'Could not open customizer. Please try again.', 'sudomock-product-customizer' ),
                'status'  => 503,
            ) );
        }

        // These are the exact browser-side handshake fields. No merchant
        // credential, proof key, product binding, or action binding is exposed.
        wp_send_json_success( array(
            'session'            => $result['session'],
            'message_session_id' => $result['message_session_id'],
            'bootstrap_secret'   => $result['bootstrap_secret'],
            'expires_in'         => $result['expires_in'],
        ) );
    }

    /**
     * AJAX: Add customized product to WooCommerce cart.
     */
    public function ajax_add_to_cart() {
        check_ajax_referer( 'sudomock_storefront', 'nonce' );
        if ( ! self::request_has_storefront_origin() ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'sudomock-product-customizer' ) ), 403 );
        }

        $allowed_fields = array(
            'action',
            'nonce',
            'version',
            'request_id',
            'message_session_id',
            'type',
            'mockup_uuid',
            'render_uuid',
            'action_id',
            'quantity',
        );
        if ( array_diff( array_keys( $_POST ), $allowed_fields ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid customization action.', 'sudomock-product-customizer' ) ), 400 );
        }

        $version = isset( $_POST['version'] ) && is_string( $_POST['version'] )
            ? wp_unslash( $_POST['version'] )
            : '';
        $request_id = isset( $_POST['request_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) )
            : '';
        $message_session_id = isset( $_POST['message_session_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['message_session_id'] ) )
            : '';
        $type = isset( $_POST['type'] )
            ? sanitize_text_field( wp_unslash( $_POST['type'] ) )
            : '';
        $mockup_uuid = isset( $_POST['mockup_uuid'] )
            ? sanitize_text_field( wp_unslash( $_POST['mockup_uuid'] ) )
            : '';
        $render_uuid = isset( $_POST['render_uuid'] )
            ? sanitize_text_field( wp_unslash( $_POST['render_uuid'] ) )
            : '';
        $action_id = isset( $_POST['action_id'] )
            ? sanitize_text_field( wp_unslash( $_POST['action_id'] ) )
            : '';
        if (
            '1' !== $version
            || ! self::is_protocol_uuid( $request_id )
            || ! self::is_protocol_uuid( $message_session_id )
            || 'studio.design-submitted' !== $type
            || ! self::is_protocol_uuid( $mockup_uuid )
            || ! self::is_protocol_uuid( $render_uuid )
            || 'add-to-cart' !== $action_id
        ) {
            wp_send_json_error( array( 'message' => __( 'Invalid customization action.', 'sudomock-product-customizer' ) ), 400 );
        }

        $binding = get_transient( self::session_binding_key( $message_session_id ) );
        if (
            ! is_array( $binding )
            || empty( $binding['product_id'] )
            || empty( $binding['mockup_uuid'] )
            || ! is_string( $binding['mockup_uuid'] )
            || empty( $binding['mockup_type'] )
            || ! is_string( $binding['mockup_type'] )
            || ! isset( $binding['variation_id'] )
            || ! isset( $binding['shop'] )
            || ! is_string( $binding['shop'] )
            || ! isset( $binding['action_id'] )
            || 'add-to-cart' !== $binding['action_id']
        ) {
            wp_send_json_error( array( 'message' => __( 'Invalid customization session.', 'sudomock-product-customizer' ) ) );
        }

        $product_id  = absint( $binding['product_id'] );
        $variation_id = absint( $binding['variation_id'] );
        $bound_mockup_uuid = sanitize_text_field( $binding['mockup_uuid'] );
        $mockup_type = sanitize_text_field( $binding['mockup_type'] );
        $origin      = self::storefront_origin();
        $shop        = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
        if (
            empty( $product_id )
            || ! in_array( $mockup_type, array( 'psd', '2d' ), true )
            || empty( $origin )
            || empty( $shop )
            || ! hash_equals( $binding['shop'], $shop )
            || ! SudoMock_Product::is_customizable( $product_id )
            || SudoMock_Product::get_mockup_uuid( $product_id ) !== $bound_mockup_uuid
            || SudoMock_Product::get_mockup_type( $product_id ) !== $mockup_type
            || ! hash_equals( $bound_mockup_uuid, $mockup_uuid )
        ) {
            wp_send_json_error( array( 'message' => __( 'Invalid customization session.', 'sudomock-product-customizer' ) ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'sudomock-product-customizer' ) ) );
        }
        if ( $product->is_type( 'variable' ) ) {
            $variation = $variation_id ? wc_get_product( $variation_id ) : false;
            if (
                ! $variation
                || ! $variation->is_type( 'variation' )
                || (int) $variation->get_parent_id() !== $product_id
            ) {
                wp_send_json_error( array( 'message' => __( 'Product mapping changed.', 'sudomock-product-customizer' ) ), 409 );
            }
        } elseif ( 0 !== $variation_id ) {
            wp_send_json_error( array( 'message' => __( 'Product mapping changed.', 'sudomock-product-customizer' ) ), 409 );
        }

        $payload = array(
            'mockup_uuid'   => $mockup_uuid,
            'render_uuid'   => $render_uuid,
            'action_id'     => $action_id,
            'action_context' => array(
                'shop'       => $shop,
                'product_id' => (string) $product_id,
                'variant_id' => (string) $variation_id,
            ),
        );

        $consume_request = array(
            'version'            => 1,
            'request_id'         => $request_id,
            'message_session_id' => $message_session_id,
            'type'               => $type,
            'payload'            => $payload,
        );
        $consumed = SudoMock_API_Client::consume_studio_action( $consume_request );
        $receipt = ! empty( $consumed['ok'] ) && isset( $consumed['data']['receipt'] ) && is_array( $consumed['data']['receipt'] )
            ? $consumed['data']['receipt']
            : array();
        $receipt_context = isset( $receipt['action_context'] ) && is_array( $receipt['action_context'] )
            ? $receipt['action_context']
            : array();
        if (
            empty( $consumed['ok'] )
            || 1 !== ( isset( $receipt['version'] ) ? $receipt['version'] : null )
            || $request_id !== ( isset( $receipt['request_id'] ) ? $receipt['request_id'] : null )
            || $message_session_id !== ( isset( $receipt['message_session_id'] ) ? $receipt['message_session_id'] : null )
            || $type !== ( isset( $receipt['type'] ) ? $receipt['type'] : null )
            || $mockup_type !== ( isset( $receipt['mockup_type'] ) ? $receipt['mockup_type'] : null )
            || 'customize' !== ( isset( $receipt['session_kind'] ) ? $receipt['session_kind'] : null )
            || $action_id !== ( isset( $receipt['action_id'] ) ? $receipt['action_id'] : null )
            || $mockup_uuid !== ( isset( $receipt['mockup_uuid'] ) ? $receipt['mockup_uuid'] : null )
            || $render_uuid !== ( isset( $receipt['render_uuid'] ) ? $receipt['render_uuid'] : null )
            || $shop !== ( isset( $receipt_context['shop'] ) ? $receipt_context['shop'] : null )
            || (string) $product_id !== ( isset( $receipt_context['product_id'] ) ? $receipt_context['product_id'] : null )
            || (string) $variation_id !== ( isset( $receipt_context['variant_id'] ) ? $receipt_context['variant_id'] : null )
            || count( $receipt_context ) !== 3
            || count( $receipt ) !== 10
        ) {
            wp_send_json_error( array( 'message' => __( 'Could not verify this customization.', 'sudomock-product-customizer' ) ), 403 );
        }

        $quantity     = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
        if ( $quantity < 1 ) {
            $quantity = 1;
        }

        $previous_cart = get_transient( self::cart_action_key( $request_id ) );
        if ( is_array( $previous_cart ) ) {
            wp_send_json_success( $previous_cart );
        }

        // Cart item data — stored in WC session, visible in cart/order
        $cart_item_data = array(
            'sudomock_customization' => array(
                'mockup_uuid'      => $mockup_uuid,
                'render_uuid'      => $render_uuid,
                'action_receipt_id' => $request_id,
            ),
        );

        // Variable products need the parent product_id AND a real variation_id
        // (never the variation id as the product_id, which silently fails).
        if ( $product->is_type( 'variable' ) ) {
            if ( ! $variation_id ) {
                wp_send_json_error( array( 'message' => __( 'Please choose the product options before adding to cart.', 'sudomock-product-customizer' ) ) );
            }
            $cart_item_key = WC()->cart->add_to_cart( $product->get_id(), $quantity, $variation_id, array(), $cart_item_data );
        } else {
            $cart_item_key = WC()->cart->add_to_cart( $product->get_id(), $quantity, 0, array(), $cart_item_data );
        }

        if ( ! $cart_item_key ) {
            // Surface WooCommerce's own reason (out of stock, max quantity, etc.)
            // instead of a generic message so the shopper knows what to fix.
            $reason = '';
            if ( function_exists( 'wc_get_notices' ) ) {
                $errors = wc_get_notices( 'error' );
                if ( ! empty( $errors ) ) {
                    $texts = array();
                    foreach ( $errors as $e ) {
                        $texts[] = is_array( $e ) && isset( $e['notice'] ) ? wp_strip_all_tags( $e['notice'] ) : wp_strip_all_tags( (string) $e );
                    }
                    $reason = trim( implode( ' ', array_filter( $texts ) ) );
                    wc_clear_notices();
                }
            }
            wp_send_json_error( array(
                'message' => $reason !== '' ? $reason : __( 'Failed to add to cart. Please try again.', 'sudomock-product-customizer' ),
            ) );
        }

        $cart_response = array(
            'message'  => __( 'Added to cart!', 'sudomock-product-customizer' ),
            'cart_url' => wc_get_cart_url(),
            'count'    => WC()->cart->get_cart_contents_count(),
        );
        set_transient( self::cart_action_key( $request_id ), $cart_response, DAY_IN_SECONDS );
        wp_send_json_success( $cart_response );
    }
}
