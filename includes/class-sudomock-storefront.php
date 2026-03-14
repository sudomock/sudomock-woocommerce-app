<?php
/**
 * Storefront — renders the customize button and Studio iframe/popup on product pages.
 *
 * SECURITY: Creates session via WP AJAX → PHP → API (server-to-server).
 * API key NEVER reaches the browser. Browser only gets a 30-min JWT.
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
        // Button placement hook — configurable via Customizer
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

        // Shortcode always available for manual placement
        add_shortcode( 'sudomock_button', array( $this, 'shortcode_button' ) );

        // AJAX: Create session (server-side, for logged-in and guest users)
        add_action( 'wp_ajax_sudomock_create_session', array( $this, 'ajax_create_session' ) );
        add_action( 'wp_ajax_nopriv_sudomock_create_session', array( $this, 'ajax_create_session' ) );

        // AJAX: Add to cart with customization data
        add_action( 'wp_ajax_sudomock_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_sudomock_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

        // Enqueue storefront assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Render the customize button on customizable products.
     */
    public function render_button() {
        global $product;

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
        global $product;
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
        $mockup_uuid = SudoMock_Product::get_mockup_uuid( $product->get_id() );

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
            $opts['padding_y'], $opts['padding_x'],
            $opts['font_size'], $opts['font_weight'],
            $opts['border_width'], esc_attr( $opts['border_color'] ),
            $opts['border_radius'],
            esc_attr( $opts['bg_color'] ), esc_attr( $opts['text_color'] ),
            $opts['text_transform'],
            $opts['shadow'] ? 'box-shadow:0 2px 8px rgba(0,0,0,0.12);' : ''
        );

        $hover_style = sprintf(
            'background:%s;color:%s;opacity:1;',
            esc_attr( $opts['hover_bg_color'] ),
            esc_attr( $opts['hover_text_color'] )
        );

        ?>
        <div class="sudomock-customizer-root" style="margin:<?php echo intval( $opts['margin_top'] ); ?>px 0 <?php echo intval( $opts['margin_bottom'] ); ?>px;text-align:<?php echo esc_attr( $opts['alignment'] ); ?>;">

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
                    data-mockup-uuid="<?php echo esc_attr( $mockup_uuid ); ?>"
                    style="<?php echo esc_attr( $btn_style ); ?>"
                    onmouseover="this.style.cssText=this.style.cssText.replace(/background:[^;]+/,'background:<?php echo esc_js( $opts['hover_bg_color'] ); ?>').replace(/color:[^;]+/,'color:<?php echo esc_js( $opts['hover_text_color'] ); ?>');"
                    onmouseout="this.style.cssText=this.style.cssText.replace(/background:[^;]+/,'background:<?php echo esc_js( $opts['bg_color'] ); ?>').replace(/color:[^;]+/,'color:<?php echo esc_js( $opts['text_color'] ); ?>');"
            >
                <?php if ( $opts['show_icon'] && 'left' === $opts['icon_position'] ) echo $icon_html; ?>
                <?php echo esc_html( $opts['label'] ); ?>
                <?php if ( $opts['show_icon'] && 'right' === $opts['icon_position'] ) echo $icon_html; ?>
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

        global $product;
        if ( ! $product ) {
            $product = wc_get_product( get_the_ID() );
        }

        if ( ! $product || ! SudoMock_Product::is_customizable( $product->get_id() ) ) {
            return;
        }

        wp_enqueue_style(
            'sudomock-storefront',
            SUDOMOCK_PLUGIN_URL . 'assets/css/storefront.css',
            array(),
            SUDOMOCK_VERSION
        );

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
            'displayMode' => get_option( 'sudomock_display_mode', 'iframe' ),
            'i18n'        => array(
                'loading'          => __( 'Loading...', 'sudomock-product-customizer' ),
                'unavailable'      => __( 'Customizer is temporarily unavailable. Please try again.', 'sudomock-product-customizer' ),
                'addedToCart'       => __( 'Added to cart!', 'sudomock-product-customizer' ),
                'cartError'        => __( 'Failed to add to cart. Please try again.', 'sudomock-product-customizer' ),
                'sessionError'     => __( 'Could not open customizer. Please try again.', 'sudomock-product-customizer' ),
                'networkCartError' => __( 'Network error adding to cart.', 'sudomock-product-customizer' ),
                'noProduct'        => __( 'Cannot add to cart: no product ID.', 'sudomock-product-customizer' ),
                'missingData'      => __( 'Missing product-id or mockup-uuid on button.', 'sudomock-product-customizer' ),
            ),
        ) );
    }

    /**
     * AJAX: Create Studio session (PHP → API, server-to-server).
     * Browser gets back a JWT token, NEVER the API key.
     */
    public function ajax_create_session() {
        check_ajax_referer( 'sudomock_storefront', 'nonce' );

        $mockup_uuid = isset( $_POST['mockup_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['mockup_uuid'] ) ) : '';
        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( empty( $mockup_uuid ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'sudomock-product-customizer' ) ) );
        }

        $result = SudoMock_API_Client::create_session( $mockup_uuid, $product_id );

        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        // Return ONLY the opaque session token - API key never leaves the server
        wp_send_json_success( array(
            'session'     => $result['session'],
            'studioUrl'   => SUDOMOCK_STUDIO_BASE . '/editor?session=' . urlencode( $result['session'] ),
            'displayMode' => $result['displayMode'],
        ) );
    }

    /**
     * AJAX: Add customized product to WooCommerce cart.
     */
    public function ajax_add_to_cart() {
        check_ajax_referer( 'sudomock_storefront', 'nonce' );

        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $mockup_uuid = isset( $_POST['mockup_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['mockup_uuid'] ) ) : '';
        $preview_url = isset( $_POST['preview_url'] ) ? esc_url_raw( wp_unslash( $_POST['preview_url'] ) ) : '';

        if ( empty( $product_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'sudomock-product-customizer' ) ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'sudomock-product-customizer' ) ) );
        }

        // Determine the correct variant/product ID to add
        $add_id = $product->is_type( 'variable' )
            ? ( isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : $product->get_id() )
            : $product->get_id();

        // Cart item data — stored in WC session, visible in cart/order
        $cart_item_data = array(
            'sudomock_customization' => array(
                'mockup_uuid' => $mockup_uuid,
                'preview_url' => $preview_url,
            ),
        );

        $cart_item_key = WC()->cart->add_to_cart( $add_id, 1, 0, array(), $cart_item_data );

        if ( ! $cart_item_key ) {
            wp_send_json_error( array( 'message' => __( 'Failed to add to cart.', 'sudomock-product-customizer' ) ) );
        }

        wp_send_json_success( array(
            'message'  => __( 'Added to cart!', 'sudomock-product-customizer' ),
            'cart_url' => wc_get_cart_url(),
            'count'    => WC()->cart->get_cart_contents_count(),
        ) );
    }
}
