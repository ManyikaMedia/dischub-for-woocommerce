<?php
/**
 * DiscHub Admin Functions & Meta Boxes
 *
 * Adds DiscHub transaction meta box to WooCommerce Order Edit screens (Classic and HPOS).
 *
 * @package DiscHub_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dischub_Admin {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_order_meta_box' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_status_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_admin_notices' ) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_order_screen = in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
		$is_settings     = 'woocommerce_page_wc-settings' === $screen->id;

		if ( $is_order_screen || $is_settings ) {
			wp_enqueue_style(
				'dischub-admin-css',
				DISCHUB_WC_URL . 'assets/css/dischub-admin.css',
				array(),
				DISCHUB_WC_VERSION
			);
		}
	}

	/**
	 * Display transient admin notices if available.
	 */
	public static function display_admin_notices() {
		$notice = get_transient( 'dischub_admin_notice_' . get_current_user_id() );
		if ( ! empty( $notice ) && is_array( $notice ) ) {
			delete_transient( 'dischub_admin_notice_' . get_current_user_id() );
			$class   = ( 'error' === ( $notice['type'] ?? '' ) ) ? 'notice-error' : 'notice-success';
			$message = $notice['message'] ?? '';
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Register Meta Box on WooCommerce Order screen.
	 */
	public static function add_order_meta_box() {
		$screen = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'dischub_order_details',
			__( 'DiscHub Payment Information', 'dischub-for-woocommerce' ),
			array( __CLASS__, 'render_order_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or Order object.
	 */
	public static function render_order_meta_box( $post_or_order ) {
		$order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order || 'dischub' !== $order->get_payment_method() ) {
			echo '<p>' . esc_html__( 'This order was not placed with DiscHub.', 'dischub-for-woocommerce' ) . '</p>';
			return;
		}

		$dischub_order_id = $order->get_meta( '_dischub_order_id' );
		$dischub_mode     = $order->get_meta( '_dischub_mode' ) ? $order->get_meta( '_dischub_mode' ) : 'live';
		$dischub_status   = $order->get_meta( '_dischub_status' ) ? $order->get_meta( '_dischub_status' ) : 'pending';
		$dischub_paid_at  = $order->get_meta( '_dischub_paid_at' );
		$dischub_sender   = $order->get_meta( '_dischub_sender' );

		$refresh_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'dischub_refresh_status',
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			'dischub_refresh_status_' . $order->get_id()
		);
		?>
		<div class="dischub-meta-container">
			<div class="dischub-meta-row">
				<strong><?php esc_html_e( 'DiscHub Reference:', 'dischub-for-woocommerce' ); ?></strong>
				<code><?php echo esc_html( $dischub_order_id ? $dischub_order_id : __( 'Not generated', 'dischub-for-woocommerce' ) ); ?></code>
			</div>
			<div class="dischub-meta-row">
				<strong><?php esc_html_e( 'Gateway Mode:', 'dischub-for-woocommerce' ); ?></strong>
				<span class="dischub-badge <?php echo 'test' === $dischub_mode ? 'mode-test' : 'mode-live'; ?>">
					<?php echo 'test' === $dischub_mode ? esc_html__( 'Test', 'dischub-for-woocommerce' ) : esc_html__( 'Live', 'dischub-for-woocommerce' ); ?>
				</span>
			</div>
			<div class="dischub-meta-row">
				<strong><?php esc_html_e( 'Payment Status:', 'dischub-for-woocommerce' ); ?></strong>
				<span class="dischub-status-badge status-<?php echo esc_attr( $dischub_status ); ?>">
					<?php echo esc_html( ucfirst( $dischub_status ) ); ?>
				</span>
			</div>
			<?php if ( ! empty( $dischub_sender ) ) : ?>
				<div class="dischub-meta-row">
					<strong><?php esc_html_e( 'Sender Phone:', 'dischub-for-woocommerce' ); ?></strong>
					<span><?php echo esc_html( $dischub_sender ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $dischub_paid_at ) ) : ?>
				<div class="dischub-meta-row">
					<strong><?php esc_html_e( 'Timestamp:', 'dischub-for-woocommerce' ); ?></strong>
					<span><?php echo esc_html( $dischub_paid_at ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $dischub_order_id ) ) : ?>
				<hr class="dischub-divider" />
				<div class="dischub-meta-actions">
					<a href="<?php echo esc_url( $refresh_url ); ?>" class="button button-secondary dischub-refresh-btn">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Check Status on DiscHub', 'dischub-for-woocommerce' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle manual status refresh button click.
	 */
	public static function handle_manual_status_refresh() {
		if ( ! isset( $_GET['action'] ) || 'dischub_refresh_status' !== $_GET['action'] ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id || ! ( current_user_can( 'edit_shop_order', $order_id ) || current_user_can( 'manage_woocommerce' ) ) ) {
			wp_die( esc_html__( 'Unauthorized action.', 'dischub-for-woocommerce' ) );
		}

		check_admin_referer( 'dischub_refresh_status_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'dischub-for-woocommerce' ) );
		}

		$gateway          = new WC_Gateway_Dischub();
		$dischub_order_id = $order->get_meta( '_dischub_order_id' );

		if ( empty( $dischub_order_id ) ) {
			wp_safe_redirect( $order->get_edit_order_url() );
			exit;
		}

		$result = $gateway->api->check_payment_status( $dischub_order_id );

		if ( ! empty( $result['success'] ) ) {
			$gateway->update_order_payment_status( $order, $result['status'], $dischub_order_id, $result['data'] ?? array() );
			$message = sprintf(
				/* translators: 1: DiscHub status, 2: DiscHub order ID */
				__( 'DiscHub Status: %1$s for %2$s', 'dischub-for-woocommerce' ),
				ucfirst( $result['status'] ),
				$dischub_order_id
			);
			set_transient(
				'dischub_admin_notice_' . get_current_user_id(),
				array(
					'type'    => 'success',
					'message' => $message,
				),
				45
			);
		} else {
			$error_msg = ! empty( $result['error'] ) ? $result['error'] : __( 'Unable to retrieve status from DiscHub.', 'dischub-for-woocommerce' );
			set_transient(
				'dischub_admin_notice_' . get_current_user_id(),
				array(
					'type'    => 'error',
					'message' => $error_msg,
				),
				45
			);
		}

		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}
}
