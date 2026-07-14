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

	const RETRY_OPTION = 'sudomock_pending_asset_deletions';
	const RETRY_HOOK   = 'sudomock_retry_asset_deletions';

	private function __construct() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );

		// Retry any deletions that could not be confirmed during erasure.
		add_action( self::RETRY_HOOK, array( $this, 'retry_queued_deletions' ) );
		if ( ! wp_next_scheduled( self::RETRY_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETRY_HOOK );
		}
	}

	/**
	 * Add stored-asset URLs to the retry queue (bounded so it cannot grow
	 * without limit if the backend stays unreachable).
	 *
	 * @param string[] $urls URLs that could not be deleted.
	 */
	public static function queue_asset_deletions( $urls ) {
		if ( empty( $urls ) ) {
			return;
		}
		$queue = get_option( self::RETRY_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		$queue = array_slice( array_values( array_unique( array_merge( $queue, $urls ) ) ), -5000 );
		update_option( self::RETRY_OPTION, $queue, false );
	}

	/**
	 * Scheduled: retry queued stored-asset deletions; drop the ones confirmed.
	 */
	public function retry_queued_deletions() {
		$queue = get_option( self::RETRY_OPTION, array() );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			return;
		}
		$result = SudoMock_API_Client::delete_order_assets( $queue );
		update_option( self::RETRY_OPTION, isset( $result['failed_urls'] ) ? $result['failed_urls'] : array(), false );
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
				'When you customise a product using the SudoMock editor, we transmit your uploaded image(s) to the SudoMock rendering service (api.sudomock.com) to generate a preview. The rendered preview URL and your uploaded design file(s) are stored alongside your order so the merchant can produce the item.',
				'sudomock-product-customizer'
			),
			esc_html__(
				'No personal data beyond order information is shared with SudoMock. Stored images are retained for order fulfilment and can be deleted upon request.',
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
				$preview_url  = SudoMock_Order::get_preview_url( $item );
				$artwork_urls = SudoMock_Order::get_artwork_urls( $item );

				if ( empty( $preview_url ) && empty( $artwork_urls ) ) {
					continue;
				}

				$row = array(
					array(
						'name'  => __( 'Order', 'sudomock-product-customizer' ),
						'value' => $order->get_order_number(),
					),
				);
				if ( ! empty( $preview_url ) ) {
					$row[] = array(
						'name'  => __( 'Customization Preview URL', 'sudomock-product-customizer' ),
						'value' => $preview_url,
					);
				}
				foreach ( $artwork_urls as $idx => $artwork_url ) {
					$row[] = array(
						/* translators: %d: artwork file number */
						'name'  => sprintf( __( 'Uploaded Design URL %d', 'sudomock-product-customizer' ), $idx + 1 ),
						'value' => $artwork_url,
					);
				}

				$data_to_export[] = array(
					'group_id'    => 'sudomock',
					'group_label' => __( 'SudoMock Customizations', 'sudomock-product-customizer' ),
					'item_id'     => "sudomock-order-{$order->get_id()}-{$item->get_id()}",
					'data'        => $row,
				);
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

		// Pass 1: collect every stored file URL (the order meta is the only
		// record of which objects exist, so we must delete them remotely BEFORE
		// erasing the meta — otherwise a failed remote delete orphans the file
		// with no reference).
		$remote_urls = array();
		$items_with_data = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$has_data = $item->get_meta( '_sudomock_preview_url' )
					|| $item->get_meta( '_sudomock_artwork_url' )
					|| $item->get_meta( '_sudomock_render_url' );
				if ( ! $has_data ) {
					continue;
				}
				foreach ( array( '_sudomock_preview_url', '_sudomock_artwork_url' ) as $k ) {
					$v = $item->get_meta( $k );
					if ( ! empty( $v ) ) {
						$remote_urls[] = $v;
					}
				}
				for ( $i = 2; $i <= 10; $i++ ) {
					$v = $item->get_meta( '_sudomock_artwork_url_' . $i );
					if ( ! empty( $v ) ) {
						$remote_urls[] = $v;
					}
				}
				$items_with_data[] = $item;
			}
		}

		// Delete the stored files from SudoMock FIRST. Any URL that could not be
		// confirmed deleted is queued for a scheduled retry, so no file is ever
		// orphaned even though the local meta below is (correctly) erased.
		$messages = array();
		if ( ! empty( $remote_urls ) ) {
			$result = SudoMock_API_Client::delete_order_assets( $remote_urls );
			if ( ! empty( $result['failed_urls'] ) ) {
				self::queue_asset_deletions( $result['failed_urls'] );
				$messages[] = __( 'Local order data was erased. Some stored design files could not be deleted from SudoMock immediately and are queued for automatic retry.', 'sudomock-product-customizer' );
			}
		}

		// Pass 2: erase the local order meta (merchant-side PII).
		foreach ( $items_with_data as $item ) {
			// Hidden keys (current model + legacy fallbacks).
			$item->delete_meta_data( '_sudomock_preview_url' );
			$item->delete_meta_data( '_sudomock_artwork_url' );
			for ( $i = 2; $i <= 10; $i++ ) {
				$item->delete_meta_data( '_sudomock_artwork_url_' . $i );
			}
			$item->delete_meta_data( '_sudomock_render_uuid' );
			$item->delete_meta_data( '_sudomock_mockup_uuid' );
			$item->delete_meta_data( '_sudomock_render_url' );
			$item->delete_meta_data( '_sudomock_session_token' );

			// Merchant-visible labels written alongside the hidden keys.
			$item->delete_meta_data( __( 'Customization Preview', 'sudomock-product-customizer' ) );
			$item->delete_meta_data( __( 'Source Design', 'sudomock-product-customizer' ) );
			for ( $i = 2; $i <= 10; $i++ ) {
				/* translators: %d: artwork file number */
				$item->delete_meta_data( sprintf( __( 'Source Design %d', 'sudomock-product-customizer' ), $i ) );
			}

			$item->save();
			$removed++;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => $messages,
			'done'           => count( $orders ) < 50,
		);
	}
}
