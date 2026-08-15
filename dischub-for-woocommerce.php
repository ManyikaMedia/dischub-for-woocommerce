<?php
/**
 * Plugin Name:        DiscHub for WooCommerce
 * Plugin URI:         https://github.com/ManyikaMedia/dischub-for-woocommerce
 * Description:        Accept EcoCash and InnBucks payments seamlessly via DiscHub Payment Gateway on WooCommerce.
 * Version:            1.0.0
 * Author:             Manyika Media
 * Author URI:         https://manyikamedia.co.zw
 * License:            GPLv2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        dischub-for-woocommerce
 * Domain Path:        /languages
 * Requires at least:  5.8
 * Requires PHP:       7.4
 * WC requires at least: 5.0
 * WC tested up to:    9.5
 *
 * @package DiscHub_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Global Plugin Constants.
define( 'DISCHUB_WC_VERSION', '1.0.0' );
define( 'DISCHUB_WC_FILE', __FILE__ );
define( 'DISCHUB_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DISCHUB_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'DISCHUB_WC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare HPOS and Checkout Block compatibility.
 * Must be executed at global scope to fire before woocommerce_init completes.
 */
function dischub_wc_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DISCHUB_WC_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DISCHUB_WC_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'dischub_wc_declare_compatibility' );

/**
 * Register WooCommerce Blocks support integration at global scope.
 */
function dischub_wc_register_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once DISCHUB_WC_PATH . 'includes/class-dischub-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new Dischub_Blocks_Support() );
			}
		);
	}
}
add_action( 'woocommerce_blocks_loaded', 'dischub_wc_register_blocks_support' );

/**
 * Primary plugin initialization routine hooked to plugins_loaded.
 */
function dischub_wc_init() {
	// Initialize localization textdomain.
	load_plugin_textdomain( 'dischub-for-woocommerce', false, dirname( DISCHUB_WC_BASENAME ) . '/languages' );

	// Verify primary dependency presence.
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'dischub_wc_missing_woocommerce_notice' );
		return;
	}

	// Load auxiliary currency classes after verifying WooCommerce existence.
	require_once DISCHUB_WC_PATH . 'includes/class-dischub-currencies.php';
	if ( class_exists( 'Dischub_Currencies' ) ) {
		Dischub_Currencies::init();
	}

	// Load core gateway logic and administrative interfaces.
	require_once DISCHUB_WC_PATH . 'includes/class-dischub-api.php';
	require_once DISCHUB_WC_PATH . 'includes/class-wc-gateway-dischub.php';
	require_once DISCHUB_WC_PATH . 'includes/class-dischub-admin.php';

	if ( class_exists( 'Dischub_Admin' ) ) {
		Dischub_Admin::init();
	}

	// Register payment gateway with WooCommerce framework.
	add_filter( 'woocommerce_payment_gateways', 'dischub_wc_add_gateway' );

	// Register AJAX polling endpoints.
	add_action( 'wp_ajax_dischub_poll_order_status', 'dischub_wc_ajax_poll_order_status' );
	add_action( 'wp_ajax_nopriv_dischub_poll_order_status', 'dischub_wc_ajax_poll_order_status' );
	add_action( 'wc_ajax_dischub_poll_order_status', 'dischub_wc_ajax_poll_order_status' );

	add_action( 'wp_ajax_dischub_submit_omari_otp', 'dischub_wc_ajax_submit_omari_otp' );
	add_action( 'wp_ajax_nopriv_dischub_submit_omari_otp', 'dischub_wc_ajax_submit_omari_otp' );
	add_action( 'wc_ajax_dischub_submit_omari_otp', 'dischub_wc_ajax_submit_omari_otp' );
}
add_action( 'plugins_loaded', 'dischub_wc_init' );

/**
 * Display error notification if WooCommerce dependency is unmet.
 */
function dischub_wc_missing_woocommerce_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<strong><?php esc_html_e( 'DiscHub for WooCommerce', 'dischub-for-woocommerce' ); ?></strong>
			<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'dischub-for-woocommerce' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Inject DiscHub class into registered WooCommerce gateways.
 *
 * @param array $gateways Registered gateway class names.
 * @return array Modified gateway collection.
 */
function dischub_wc_add_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_Dischub';
	return $gateways;
}

/**
 * AJAX Handler: Poll Order Status.
 */
function dischub_wc_ajax_poll_order_status() {
	$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : ( isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0 );
	$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : ( isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '' );

	if ( ! $order_id || empty( $order_key ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Missing order parameters.', 'dischub-for-woocommerce' ) ), 400 );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Unauthorized order access.', 'dischub-for-woocommerce' ) ), 403 );
	}

	// Return success immediately if already processing or completed (e.g. verified by IPN webhook).
	if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
		wp_send_json_success(
			array(
				'status'       => 'success',
				'order_status' => $order->get_status(),
			)
		);
	}

	$dischub_order_id = $order->get_meta( '_dischub_order_id' );
	if ( empty( $dischub_order_id ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Missing transaction reference.', 'dischub-for-woocommerce' ) ), 400 );
	}

	if ( ! class_exists( 'WC_Gateway_Dischub' ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Payment subsystem unavailable.', 'dischub-for-woocommerce' ) ), 500 );
	}

	$gateway = new WC_Gateway_Dischub();
	$result  = $gateway->api->check_payment_status( $dischub_order_id );

	if ( ! empty( $result['success'] ) && 'error' !== ( $result['status'] ?? '' ) ) {
		$status = $result['status'];
		$gateway->update_order_payment_status( $order, $status, $dischub_order_id, $result['data'] ?? array() );

		// Refresh fresh order state.
		$refreshed_order = wc_get_order( $order_id );
		$fresh_status    = $refreshed_order ? $refreshed_order->get_status() : $order->get_status();

		wp_send_json_success(
			array(
				'status'       => $status,
				'order_status' => $fresh_status,
			)
		);
	}

	wp_send_json_success(
		array(
			'status'       => 'pending',
			'order_status' => $order->get_status(),
		)
	);
}

/**
 * AJAX Handler: Submit Omari OTP.
 */
function dischub_wc_ajax_submit_omari_otp() {
	$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
	$otp       = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';

	if ( ! $order_id || empty( $order_key ) || empty( $otp ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Missing parameters.', 'dischub-for-woocommerce' ) ), 400 );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Unauthorized order access.', 'dischub-for-woocommerce' ) ), 403 );
	}

	$dischub_order_id = $order->get_meta( '_dischub_order_id' );
	if ( ! class_exists( 'WC_Gateway_Dischub' ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Payment subsystem unavailable.', 'dischub-for-woocommerce' ) ), 500 );
	}

	$gateway = new WC_Gateway_Dischub();
	$result  = $gateway->api->verify_omari_otp( $dischub_order_id, $otp );

	if ( ! empty( $result['success'] ) ) {
		$gateway->update_order_payment_status( $order, 'success', $dischub_order_id, $result['data'] ?? array() );
		wp_send_json_success( array( 'status' => 'success' ) );
	}

	wp_send_json_error( array( 'error' => $result['error'] ?? esc_html__( 'OTP verification failed.', 'dischub-for-woocommerce' ) ), 400 );
}

/**
 * Register administrative settings link on Plugins management screen.
 *
 * @param array $links Array of existing action links.
 * @return array Modified list of action links.
 */
function dischub_wc_plugin_action_links( $links ) {
	if ( ! is_array( $links ) ) {
		$links = array();
	}

	$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=dischub' );
	$custom_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'dischub-for-woocommerce' ) . '</a>',
	);
	return array_merge( $custom_links, $links );
}
add_filter( 'plugin_action_links_' . DISCHUB_WC_BASENAME, 'dischub_wc_plugin_action_links' );
