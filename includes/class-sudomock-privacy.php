<?php
/**
 * WordPress Privacy / GDPR integration.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SudoMock_Privacy
 *
 * Registers a privacy-policy suggestion and handles personal-data
 * export / erasure requests for customization data stored in order meta.
 */
final class SudoMock_Privacy {

	/** @var self|null */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggest privacy-policy text for the site owner.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html__(
				'When you customise a product using the SudoMock editor, we transmit your uploaded image(s) to the SudoMock rendering service (api.sudomock.com) to generate a preview. The rendered image URL is stored alongside your order.',
				'sudomock-product-customizer'
			),
			esc_html__(
				'No personal data beyond order information is shared with SudoMock. Rendered images are retained according to the store data-retention policy and can be deleted upon request.',
				'sudomock-product-customizer'
			)
		);

		wp_add_privacy_policy_content(
			'SudoMock Product Customizer',
			wp_kses_post( $content )
		);
	}

	/**
	 * Register personal-data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['sudomock'] = array(
			'exporter_friendly_name' => __( 'SudoMock Customization Data', 'sudomock-product-customizer' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register personal-data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['sudomock'] = array(
			'eraser_friendly_name' => __( 'SudoMock Customization Data', 'sudomock-product-customizer' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export SudoMock data attached to orders belonging to the given email.
	 *
	 * @param string $email_address Customer email.
	 * @param int    $page          Page number (pagination).
	 * @return array
	 */
	public function export_personal_data( $email_address, $page = 1 ) {
		$data_to_export = array();

		$orders = wc_get_orders(
			array(
				'billing_email' => $email_address,
				'limit'         => 50,
				'page'          => $page,
			)
		);

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$render_url = $item->get_meta( '_sudomock_render_url' );
				if ( ! empty( $render_url ) ) {
					$data_to_export[] = array(
						'group_id'    => 'sudomock',
						'group_label' => __( 'SudoMock Customizations', 'sudomock-product-customizer' ),
						'item_id'     => "sudomock-order-{$order->get_id()}-{$item->get_id()}",
						'data'        => array(
							array(
								'name'  => __( 'Order', 'sudomock-product-customizer' ),
								'value' => $order->get_order_number(),
							),
							array(
								'name'  => __( 'Render URL', 'sudomock-product-customizer' ),
								'value' => $render_url,
							),
						),
					);
				}
			}
		}

		return array(
			'data' => $data_to_export,
			'done' => count( $orders ) < 50,
		);
	}

	/**
	 * Erase SudoMock data for the given email.
	 *
	 * @param string $email_address Customer email.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public function erase_personal_data( $email_address, $page = 1 ) {
		$removed = 0;

		$orders = wc_get_orders(
			array(
				'billing_email' => $email_address,
				'limit'         => 50,
				'page'          => $page,
			)
		);

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item->get_meta( '_sudomock_render_url' ) ) {
					$item->delete_meta_data( '_sudomock_render_url' );
					$item->delete_meta_data( '_sudomock_mockup_uuid' );
					$item->delete_meta_data( '_sudomock_session_token' );
					$item->save();
					$removed++;
				}
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => count( $orders ) < 50,
		);
	}
}
