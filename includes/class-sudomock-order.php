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
		// Order-item meta is written by SudoMock_Cart::add_order_item_meta
		// (the AJAX add-to-cart path). This class only handles admin display.

		// Display customization in admin order view.
		add_action(
			'woocommerce_after_order_itemmeta',
			array( $this, 'display_admin_order_item_meta' ),
			10,
			3
		);
	}

	/**
	 * Customization preview URL for an order item.
	 * `_sudomock_render_url` is the legacy key of a retired flow; read fallback only.
	 *
	 * @param object $item Order item object.
	 * @return string
	 */
	public static function get_preview_url( $item ) {
		$preview_url = $item->get_meta( '_sudomock_preview_url' );
		if ( empty( $preview_url ) ) {
			$preview_url = $item->get_meta( '_sudomock_render_url' );
		}
		return $preview_url;
	}

	/**
	 * Original artwork file URLs for an order item (_sudomock_artwork_url, _2 ... _10).
	 *
	 * @param object $item Order item object.
	 * @return string[]
	 */
	public static function get_artwork_urls( $item ) {
		return self::get_numbered_urls( $item, '_sudomock_artwork_url' );
	}

	/**
	 * Print file URLs for an order item (_sudomock_print_file_url, _2, _3 ...).
	 * Their count has no limit.
	 *
	 * @param object $item Order item object.
	 * @return string[]
	 */
	public static function get_print_file_urls( $item ) {
		return self::get_numbered_urls( $item, '_sudomock_print_file_url', PHP_INT_MAX );
	}

	/**
	 * URLs stored under a key and its numbered siblings (_2 ... _$max).
	 *
	 * @param object $item Order item object.
	 * @param string $key  First key.
	 * @param int    $max  Highest number read.
	 * @return string[]
	 */
	private static function get_numbered_urls( $item, $key, $max = 10 ) {
		$urls  = array();
		$first = $item->get_meta( $key );
		if ( ! empty( $first ) ) {
			$urls[] = $first;
		}
		for ( $i = 2; $i <= $max; $i++ ) {
			$extra = $item->get_meta( $key . '_' . $i );
			if ( empty( $extra ) ) {
				break;
			}
			$urls[] = $extra;
		}
		return $urls;
	}

	/**
	 * Show the customization preview thumbnail, the original artwork
	 * file link(s) and the print file link(s) in the admin order screen.
	 *
	 * @param int            $item_id  Order item ID.
	 * @param object         $item     Order item object.
	 * @param \WC_Product|null $product Product object (may be null).
	 */
	public function display_admin_order_item_meta( $item_id, $item, $product ) {
		$preview_url     = self::get_preview_url( $item );
		$artwork_urls    = self::get_artwork_urls( $item );
		$print_file_urls = self::get_print_file_urls( $item );

		if ( empty( $preview_url ) && empty( $artwork_urls ) && empty( $print_file_urls ) ) {
			return;
		}

		echo '<div class="sudomock-order-preview" style="margin-top:8px;">';

		if ( ! empty( $preview_url ) ) {
			printf(
				'<strong>%s</strong><br>'
				. '<a href="%s" target="_blank" rel="noopener">'
				. '<img src="%s" alt="%s" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;" />'
				. '</a>',
				esc_html__( 'Customer Design:', 'sudomock-product-customizer' ),
				esc_url( $preview_url ),
				esc_url( $preview_url ),
				esc_attr__( 'Customer customization preview', 'sudomock-product-customizer' )
			);
		}

		if ( ! empty( $artwork_urls ) ) {
			echo '<div style="margin-top:6px;"><strong>'
				. esc_html__( 'Source design file(s):', 'sudomock-product-customizer' )
				. '</strong><br>';
			foreach ( $artwork_urls as $idx => $artwork_url ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s</a><br>',
					esc_url( $artwork_url ),
					/* translators: %d: artwork file number */
					esc_html( sprintf( __( 'Download artwork %d', 'sudomock-product-customizer' ), $idx + 1 ) )
				);
			}
			echo '</div>';
		}

		if ( ! empty( $print_file_urls ) ) {
			echo '<div style="margin-top:6px;"><strong>'
				. esc_html__( 'Print file(s):', 'sudomock-product-customizer' )
				. '</strong><br>';
			foreach ( $print_file_urls as $idx => $print_file_url ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener">%s</a><br>',
					esc_url( $print_file_url ),
					/* translators: %d: print file number */
					esc_html( sprintf( __( 'Download print file %d', 'sudomock-product-customizer' ), $idx + 1 ) )
				);
			}
			echo '</div>';
		}

		echo '</div>';
	}
}
