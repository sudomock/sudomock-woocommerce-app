<?php
/**
 * WooCommerce Blocks (Checkout Blocks) compatibility.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SudoMock_Blocks
 *
 * Ensures SudoMock cart-item data (render preview, customization flag)
 * survives the new Block-based Cart / Checkout experience introduced
 * in WooCommerce 8.3+.
 *
 * This works by extending the Store API schema with our custom data
 * so the React-rendered cart can display the preview thumbnail.
 */
final class SudoMock_Blocks {

	/** @var self|null */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Register Store API integration when the Blocks package is available.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_integration' ) );
	}

	/**
	 * Register custom data with the WooCommerce Store API.
	 */
	public function register_blocks_integration() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => 'sudomock',
				'data_callback'   => array( $this, 'extend_cart_item_data' ),
				'schema_callback' => array( $this, 'extend_cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Provide custom data for each cart item in the Store API response.
	 *
	 * @param array $cart_item Cart item data from WooCommerce.
	 * @return array
	 */
	public function extend_cart_item_data( $cart_item ) {
		$data = isset( $cart_item['sudomock_customization'] ) ? $cart_item['sudomock_customization'] : array();
		return array(
			'render_url'  => isset( $data['preview_url'] ) ? $data['preview_url'] : '',
			'mockup_uuid' => isset( $data['mockup_uuid'] ) ? $data['mockup_uuid'] : '',
		);
	}

	/**
	 * Define the schema for our custom cart-item data.
	 *
	 * @return array
	 */
	public function extend_cart_item_schema() {
		return array(
			'render_url'  => array(
				'description' => __( 'URL of the rendered customization preview.', 'sudomock-product-customizer' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'mockup_uuid' => array(
				'description' => __( 'UUID of the mockup used for customization.', 'sudomock-product-customizer' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}
}
