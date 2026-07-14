<?php
/**
 * Cart Integration — shows customization preview in cart/checkout.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_Cart {

    /** @var self|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Display customization info in cart
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

        // Show preview thumbnail in cart
        add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 10, 3 );

        // Persist customization data to order
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

        // Make each customized item unique in cart (prevent merging)
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'make_unique' ), 10, 3 );
    }

    /**
     * Display customization label in cart item data.
     *
     * @param array $item_data Existing item data.
     * @param array $cart_item Cart item.
     * @return array
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['sudomock_customization'] ) ) {
            $item_data[] = array(
                'key'   => __( 'Customization', 'sudomock-product-customizer' ),
                'value' => __( 'Custom design applied', 'sudomock-product-customizer' ),
            );
        }
        return $item_data;
    }

    /**
     * Replace cart thumbnail with customization preview.
     *
     * @param string $thumbnail Default thumbnail HTML.
     * @param array  $cart_item Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return string
     */
    public function cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['sudomock_customization']['preview_url'] ) ) {
            $preview_url = esc_url( $cart_item['sudomock_customization']['preview_url'] );
            return sprintf(
                '<img src="%s" alt="%s" class="sudomock-cart-preview" width="100" height="100" style="object-fit:contain;" />',
                $preview_url,
                esc_attr__( 'Custom design preview', 'sudomock-product-customizer' )
            );
        }
        return $thumbnail;
    }

    /**
     * Persist customization data to order line item meta.
     *
     * @param WC_Order_Item_Product $item      Order item.
     * @param string                $cart_item_key Cart item key.
     * @param array                 $values    Cart item values.
     * @param WC_Order              $order     Order object.
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['sudomock_customization'] ) ) {
            $custom = $values['sudomock_customization'];

            $item->add_meta_data( '_sudomock_mockup_uuid', $custom['mockup_uuid'], true );

            if ( ! empty( $custom['preview_url'] ) ) {
                $item->add_meta_data( '_sudomock_preview_url', $custom['preview_url'], true );
                // Visible meta for merchant
                $item->add_meta_data(
                    __( 'Customization Preview', 'sudomock-product-customizer' ),
                    $custom['preview_url'],
                    true
                );
            }

            // Original customer artwork file(s) — the durable source files the
            // merchant prints from. First entry keeps the unsuffixed key so
            // fulfillment tooling reading "Source Design" works unchanged.
            if ( ! empty( $custom['artwork_urls'] ) && is_array( $custom['artwork_urls'] ) ) {
                $i = 0;
                foreach ( array_slice( $custom['artwork_urls'], 0, 10 ) as $artwork_url ) {
                    $i++;
                    $suffix = ( 1 === $i ) ? '' : '_' . $i;
                    $label  = ( 1 === $i )
                        ? __( 'Source Design', 'sudomock-product-customizer' )
                        /* translators: %d: artwork file number */
                        : sprintf( __( 'Source Design %d', 'sudomock-product-customizer' ), $i );
                    $item->add_meta_data( '_sudomock_artwork_url' . $suffix, $artwork_url, true );
                    $item->add_meta_data( $label, $artwork_url, true );
                }
            }

            // Render id for merchant cross-reference (support, re-render, audit).
            if ( ! empty( $custom['render_uuid'] ) ) {
                $item->add_meta_data( '_sudomock_render_uuid', $custom['render_uuid'], true );
            }
        }
    }

    /**
     * Add unique key to prevent WC from merging customized cart items.
     *
     * @param array $cart_item_data Cart item data.
     * @param int   $product_id    Product ID.
     * @param int   $variation_id  Variation ID.
     * @return array
     */
    public function make_unique( $cart_item_data, $product_id, $variation_id ) {
        if ( ! empty( $cart_item_data['sudomock_customization'] ) ) {
            $cart_item_data['sudomock_unique_key'] = md5( microtime() . wp_rand() );
        }
        return $cart_item_data;
    }
}
