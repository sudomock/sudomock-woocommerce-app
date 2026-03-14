<?php
/**
 * Product Integration — mockup mapping via WC product meta.
 *
 * Adds a "SudoMock" tab in the WooCommerce product edit panel.
 * Stores mockup_uuid + customization_enabled in wp_postmeta (native WC).
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_Product {

    /** @var self|null */
    private static $instance = null;

    /** Meta key prefix */
    const META_MOCKUP_UUID  = '_sudomock_mockup_uuid';
    const META_CUSTOMIZABLE = '_sudomock_customization_enabled';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add product data tab
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

        // Admin scripts on product edit
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_assets' ) );
    }

    /**
     * Add SudoMock tab to WC product data tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public function add_product_tab( $tabs ) {
        $tabs['sudomock'] = array(
            'label'    => __( 'SudoMock', 'sudomock-product-customizer' ),
            'target'   => 'sudomock_product_data',
            'class'    => array( 'sudomock_tab' ),
            'priority' => 80,
        );
        return $tabs;
    }

    /**
     * Render the product data panel content.
     */
    public function render_product_panel() {
        global $post;

        $product_id    = $post->ID;
        $mockup_uuid   = get_post_meta( $product_id, self::META_MOCKUP_UUID, true );
        $mockup_name   = get_post_meta( $product_id, '_sudomock_mockup_name', true );
        $is_enabled    = get_post_meta( $product_id, self::META_CUSTOMIZABLE, true );
        $is_connected  = ! empty( SudoMock_API_Client::get_api_key() );

        wp_nonce_field( 'sudomock_product_meta', 'sudomock_product_nonce' );
        ?>
        <div id="sudomock_product_data" class="panel woocommerce_options_panel" style="padding: 12px 12px 12px 20%;">
            <?php if ( ! $is_connected ) : ?>
                <div style="padding:20px;background:#fef3c7;border-radius:6px;margin-bottom:12px;">
                    <p style="margin:0;">
                        <strong><?php esc_html_e( 'SudoMock not connected', 'sudomock-product-customizer' ); ?></strong><br>
                        <?php
                        printf(
                            /* translators: %s: settings page link */
                            esc_html__( 'Connect your account in %s to enable product customization.', 'sudomock-product-customizer' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=sudomock-settings' ) ) . '">' .
                            esc_html__( 'SudoMock Settings', 'sudomock-product-customizer' ) . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>
                <?php
                woocommerce_wp_checkbox( array(
                    'id'          => self::META_CUSTOMIZABLE,
                    'label'       => __( 'Enable Customization', 'sudomock-product-customizer' ),
                    'description' => __( 'Show the customize button on this product page.', 'sudomock-product-customizer' ),
                    'value'       => $is_enabled,
                    'wrapper_class' => '',
                ) );
                ?>

                <div class="form-field" style="margin-top:8px;">
                    <p style="margin:0 0 8px;"><strong><?php esc_html_e( 'PSD Mockup Template', 'sudomock-product-customizer' ); ?></strong></p>

                    <!-- Current Selection -->
                    <input type="hidden" id="sudomock_mockup_uuid" name="<?php echo esc_attr( self::META_MOCKUP_UUID ); ?>" value="<?php echo esc_attr( $mockup_uuid ); ?>" />
                    <input type="hidden" id="sudomock_mockup_name" name="_sudomock_mockup_name" value="<?php echo esc_attr( $mockup_name ); ?>" />

                    <div id="sudomock-selected-mockup" style="<?php echo empty( $mockup_uuid ) ? 'display:none;' : ''; ?>margin-bottom:10px;">
                        <div style="display:flex;align-items:center;gap:12px;padding:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                            <div id="sudomock-selected-thumb" style="width:60px;height:60px;border-radius:6px;overflow:hidden;background:#e5e7eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <span style="color:#9ca3af;font-size:11px;">PSD</span>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div id="sudomock-selected-name" style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?php echo esc_html( $mockup_name ? $mockup_name : $mockup_uuid ); ?>
                                </div>
                                <div id="sudomock-selected-uuid" style="font-size:11px;color:#6b7280;font-family:monospace;">
                                    <?php echo esc_html( $mockup_uuid ? substr( $mockup_uuid, 0, 8 ) . '...' : '' ); ?>
                                </div>
                            </div>
                            <button type="button" id="sudomock-change-mockup" class="button button-small"><?php esc_html_e( 'Change', 'sudomock-product-customizer' ); ?></button>
                            <button type="button" id="sudomock-remove-mockup" class="button button-small" style="color:#dc2626;"><?php esc_html_e( 'Remove', 'sudomock-product-customizer' ); ?></button>
                        </div>
                    </div>

                    <!-- Mockup Picker (shown when no mockup or when changing) -->
                    <div id="sudomock-mockup-picker" style="<?php echo ! empty( $mockup_uuid ) ? 'display:none;' : ''; ?>">
                        <input type="text" id="sudomock-product-search" class="regular-text" style="width:100%;margin-bottom:8px;"
                            placeholder="<?php esc_attr_e( 'Search mockups by name...', 'sudomock-product-customizer' ); ?>" />
                        <div id="sudomock-product-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-height:320px;overflow-y:auto;padding:2px;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save product meta on post save.
     *
     * @param int $product_id Product ID.
     */
    public function save_product_meta( $product_id ) {
        if ( ! isset( $_POST['sudomock_product_nonce'] ) ||
             ! wp_verify_nonce( $_POST['sudomock_product_nonce'], 'sudomock_product_meta' ) ) { // phpcs:ignore
            return;
        }

        // Customization enabled checkbox
        $enabled = isset( $_POST[ self::META_CUSTOMIZABLE ] ) ? 'yes' : 'no';
        update_post_meta( $product_id, self::META_CUSTOMIZABLE, $enabled );

        // Mockup UUID
        $uuid = isset( $_POST[ self::META_MOCKUP_UUID ] )
            ? sanitize_text_field( wp_unslash( $_POST[ self::META_MOCKUP_UUID ] ) )
            : '';
        update_post_meta( $product_id, self::META_MOCKUP_UUID, $uuid );

        // Mockup name (for display)
        $name = isset( $_POST['_sudomock_mockup_name'] )
            ? sanitize_text_field( wp_unslash( $_POST['_sudomock_mockup_name'] ) )
            : '';
        update_post_meta( $product_id, '_sudomock_mockup_name', $name );
    }

    /**
     * Enqueue assets on product edit screen.
     *
     * @param string $hook_suffix Current admin page.
     */
    public function enqueue_product_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'product' !== $screen->id ) {
            return;
        }

        wp_enqueue_style(
            'sudomock-admin',
            SUDOMOCK_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SUDOMOCK_VERSION
        );

        wp_enqueue_script(
            'sudomock-product',
            SUDOMOCK_PLUGIN_URL . 'assets/js/product.js',
            array(),
            SUDOMOCK_VERSION,
            array( 'in_footer' => true, 'strategy' => 'defer' )
        );

        $localize_data = array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sudomock_admin' ),
            'i18n'    => array(
                'loadingMockups' => __( 'Loading mockups...', 'sudomock-product-customizer' ),
                'failedToLoad'   => __( 'Failed to load mockups.', 'sudomock-product-customizer' ),
                'noMockupsMatch' => __( 'No mockups match', 'sudomock-product-customizer' ),
                'noMockupsYet'   => __( 'No mockups yet.', 'sudomock-product-customizer' ),
                'uploadFirstPsd' => __( 'Upload your first PSD', 'sudomock-product-customizer' ),
                'networkError'   => __( 'Network error.', 'sudomock-product-customizer' ),
                'noPreview'      => __( 'No preview', 'sudomock-product-customizer' ),
                'smartObject'    => __( 'smart object', 'sudomock-product-customizer' ),
                'smartObjects'   => __( 'smart objects', 'sudomock-product-customizer' ),
            ),
        );

        // Fetch current mockup info from API for thumbnail display
        global $post;
        if ( $post ) {
            $mockup_uuid = get_post_meta( $post->ID, self::META_MOCKUP_UUID, true );
            if ( ! empty( $mockup_uuid ) ) {
                $result = SudoMock_API_Client::get_mockup( $mockup_uuid );
                if ( $result['ok'] && ! empty( $result['data'] ) ) {
                    $mockup    = $result['data'];
                    $thumbnail = '';
                    if ( ! empty( $mockup['thumbnails'] ) ) {
                        $thumb_480 = null;
                        foreach ( $mockup['thumbnails'] as $t ) {
                            if ( ( isset( $t['label'] ) && '480' === $t['label'] ) || ( isset( $t['width'] ) && 480 === $t['width'] ) ) {
                                $thumb_480 = $t;
                                break;
                            }
                        }
                        $thumbnail = $thumb_480 ? $thumb_480['url'] : $mockup['thumbnails'][0]['url'];
                    } elseif ( ! empty( $mockup['thumbnail'] ) ) {
                        $thumbnail = $mockup['thumbnail'];
                    }
                    $localize_data['currentMockup'] = array(
                        'uuid'      => $mockup_uuid,
                        'name'      => isset( $mockup['name'] ) ? $mockup['name'] : '',
                        'thumbnail' => $thumbnail,
                    );
                }
            }
        }

        wp_localize_script( 'sudomock-product', 'sudomockProduct', $localize_data );
    }

    /**
     * Check if a product has customization enabled.
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public static function is_customizable( $product_id ) {
        return 'yes' === get_post_meta( $product_id, self::META_CUSTOMIZABLE, true )
            && ! empty( get_post_meta( $product_id, self::META_MOCKUP_UUID, true ) );
    }

    /**
     * Get the mockup UUID for a product.
     *
     * @param int $product_id Product ID.
     * @return string
     */
    public static function get_mockup_uuid( $product_id ) {
        return (string) get_post_meta( $product_id, self::META_MOCKUP_UUID, true );
    }
}
