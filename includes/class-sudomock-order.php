<?php
/**
 * Order handling — save render URLs and customization data to order items.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SudoMock_Order
 *
 * Persists customization metadata (render URL, mockup UUID, smart-object
 * selections) into WooCommerce order-item meta so that fulfilment and
 * admin can always reference the final rendered image.
 */
final class SudoMock_Order {

	/** @var self|null */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Copy cart-item meta → order-item meta during checkout.
		add_action(
			'woocommerce_checkout_create_order_line_item',
			array( $this, 'save_customization_to_order' ),
			10,
			4
		);

		// Display customization in admin order view.
		add_action(
			'woocommerce_after_order_itemmeta',
			array( $this, 'display_admin_order_item_meta' ),
			10,
			3
		);
	}

	/**
	 * Persist customization data from cart item to order item.
	 *
	 * @param \WC_Order_Item_Product $item          Order line item.
	 * @param string                 $cart_item_key  Cart item key.
	 * @param array                  $values         Cart item data.
	 * @param \WC_Order              $order          Order object.
	 */
	public function save_customization_to_order( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['sudomock_render_url'] ) ) {
			return;
		}

		$item->add_meta_data( '_sudomock_render_url', sanitize_url( $values['sudomock_render_url'] ) );

		if ( ! empty( $values['sudomock_mockup_uuid'] ) ) {
			$item->add_meta_data( '_sudomock_mockup_uuid', sanitize_text_field( $values['sudomock_mockup_uuid'] ) );
		}
		if ( ! empty( $values['sudomock_session_token'] ) ) {
			$item->add_meta_data( '_sudomock_session_token', sanitize_text_field( $values['sudomock_session_token'] ) );
		}
	}

	/**
	 * Show a small render thumbnail in the admin order screen.
	 *
	 * @param int            $item_id  Order item ID.
	 * @param object         $item     Order item object.
	 * @param \WC_Product|null $product Product object (may be null).
	 */
	public function display_admin_order_item_meta( $item_id, $item, $product ) {
		$render_url = $item->get_meta( '_sudomock_render_url' );
		if ( empty( $render_url ) ) {
			return;
		}
		printf(
			'<div class="sudomock-order-preview" style="margin-top:8px;">'
			. '<strong>%s</strong><br>'
			. '<a href="%s" target="_blank" rel="noopener">'
			. '<img src="%s" alt="%s" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;" />'
			. '</a></div>',
			esc_html__( 'Customer Design:', 'sudomock-product-customizer' ),
			esc_url( $render_url ),
			esc_url( $render_url ),
			esc_attr__( 'Customer customization preview', 'sudomock-product-customizer' )
		);
	}
}
