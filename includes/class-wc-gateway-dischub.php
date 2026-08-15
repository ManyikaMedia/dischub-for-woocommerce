<?php
/**
 * DiscHub WooCommerce Payment Gateway Class
 *
 * Implements WC_Payment_Gateway for DiscHub Zimbabwean payment gateway.
 * Supports Seamless On-Site Mobile Money (EcoCash & InnBucks).
 *
 * @package DiscHub_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Dischub extends WC_Payment_Gateway {

	/**
	 * DiscHub API Client instance.
	 *
	 * @var Dischub_API
	 */
	public $api;

	/**
	 * Gateway Mode: 'test' or 'live'.
	 *
	 * @var string
	 */
	public $mode;

	/**
	 * DiscHub API Key.
	 *
	 * @var string
	 */
	public $api_key;

	/**
	 * DiscHub Merchant Recipient Email.
	 *
	 * @var string
	 */
	public $recipient_email;

	/**
	 * Debug logging enabled.
	 *
	 * @var string
	 */
	public $debug;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'dischub';
		$this->has_fields         = true;
		$this->method_title       = __( 'DiscHub (EcoCash & InnBucks)', 'dischub-for-woocommerce' );
		$this->method_description = __( 'Accept seamless mobile payments via EcoCash and InnBucks securely through DiscHub.', 'dischub-for-woocommerce' );
		$this->icon               = DISCHUB_WC_URL . 'assets/images/dischub-badge.svg';
		$this->supports           = array( 'products' );

		// Load form fields and settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user configuration variables.
		$this->title           = $this->get_option( 'title', __( 'DiscHub (EcoCash & InnBucks)', 'dischub-for-woocommerce' ) );
		$this->description     = $this->get_option( 'description', __( 'Pay securely on this website with EcoCash or InnBucks.', 'dischub-for-woocommerce' ) );
		$this->mode            = $this->get_option( 'mode', 'live' );
		$this->api_key         = $this->get_option( 'api_key' );
		$this->recipient_email = $this->get_option( 'recipient_email' );
		$this->debug           = $this->get_option( 'debug' );

		// Initialize API client.
		$this->api = new Dischub_API(
			$this->api_key,
			$this->recipient_email,
			$this->mode,
			'yes' === $this->debug
		);

		// Actions & Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_dischub', array( $this, 'handle_webhook' ) );

		// Render Payment Prompt at TOP of order confirmation page.
		add_action( 'woocommerce_before_thankyou', array( $this, 'render_onsite_thankyou_widget' ), 5 );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_onsite_thankyou_widget' ), 5 );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'dischub-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable DiscHub (EcoCash & InnBucks)', 'dischub-for-woocommerce' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'dischub-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment gateway title visible to customers on checkout.', 'dischub-for-woocommerce' ),
				'default'     => __( 'DiscHub (EcoCash & InnBucks)', 'dischub-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'dischub-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description visible on checkout.', 'dischub-for-woocommerce' ),
				'default'     => __( 'Pay securely on this website with EcoCash or InnBucks.', 'dischub-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'mode' => array(
				'title'       => __( 'Environment Mode', 'dischub-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select Test mode for sandbox simulation or Live mode for production payments.', 'dischub-for-woocommerce' ),
				'default'     => 'live',
				'options'     => array(
					'live' => __( 'Live mode (Production)', 'dischub-for-woocommerce' ),
					'test' => __( 'Test mode (Sandbox simulation)', 'dischub-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'api_key' => array(
				'title'       => __( 'DiscHub API Key', 'dischub-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Enter your DiscHub API Key generated from your DiscHub Merchant Dashboard.', 'dischub-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => 'e.g. 6945f57cdbf14b00812cb15df1933f2a',
			),
			'recipient_email' => array(
				'title'       => __( 'DiscHub Merchant Email', 'dischub-for-woocommerce' ),
				'type'        => 'email',
				'description' => __( 'The email address associated with your DiscHub merchant account.', 'dischub-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => 'e.g. merchant@yourdomain.com',
			),
			'debug' => array(
				'title'       => __( 'Debug Logging', 'dischub-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable debug logging to WooCommerce logs', 'dischub-for-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf(
					/* translators: %s: URL to WooCommerce logs */
					__( 'View logs in <a href="%s">WooCommerce &gt; Status &gt; Logs</a> (look for dischub-for-woocommerce).', 'dischub-for-woocommerce' ),
					esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) )
				),
			),
		);
	}

	/**
	 * Render payment fields on Classic Checkout page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
		$billing_phone = ( is_object( WC()->checkout ) && is_callable( array( WC()->checkout, 'get_value' ) ) ) ? WC()->checkout->get_value( 'billing_phone' ) : '';
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-payment-form" style="background:transparent; border:none; padding:0; margin-top:10px;">
			<div style="margin-bottom:12px; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
				<label style="display:block; font-weight:700; margin-bottom:8px; color:#0f172a; font-size:0.9rem;">
					<?php esc_html_e( 'Select Payment Method:', 'dischub-for-woocommerce' ); ?>
				</label>
				<div style="display:flex; flex-direction:column; gap:8px;">
					<label style="display:flex; align-items:center; gap:8px; font-size:0.92rem; cursor:pointer;">
						<input type="radio" name="dischub_selected_method" value="ecocash" checked="checked" />
						<span><strong><?php esc_html_e( 'EcoCash', 'dischub-for-woocommerce' ); ?></strong> (<?php esc_html_e( 'PIN prompt sent directly to your phone', 'dischub-for-woocommerce' ); ?>)</span>
					</label>
					<label style="display:flex; align-items:center; gap:8px; font-size:0.92rem; cursor:pointer;">
						<input type="radio" name="dischub_selected_method" value="innbucks" />
						<span><strong><?php esc_html_e( 'InnBucks', 'dischub-for-woocommerce' ); ?></strong> (<?php esc_html_e( 'Instant Payment Code & QR Code', 'dischub-for-woocommerce' ); ?>)</span>
					</label>
				</div>
			</div>

			<p class="form-row form-row-wide" style="margin-bottom:8px;">
				<label for="dischub_phone_number" style="font-weight:600; color:#1f2937; font-size:0.88rem;">
					<?php esc_html_e( 'Mobile Phone Number for Payment:', 'dischub-for-woocommerce' ); ?>
				</label>
				<input type="tel" class="input-text" name="dischub_phone_number" id="dischub_phone_number" placeholder="<?php esc_attr_e( 'e.g. 0774822032 or +263774822032', 'dischub-for-woocommerce' ); ?>" value="<?php echo esc_attr( $billing_phone ); ?>" style="width:100%; padding:10px 12px; border-radius:6px; border:1px solid #cbd5e1; margin-top:4px; font-size:0.95rem;" />
				<small style="display:block; color:#64748b; margin-top:3px; font-size:0.8rem;">
					<?php esc_html_e( 'If left empty, your billing address phone number will be used automatically.', 'dischub-for-woocommerce' ); ?>
				</small>
			</p>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Custom Admin Settings Page.
	 */
	public function admin_options() {
		$webhook_url = add_query_arg( 'wc-api', 'wc_gateway_dischub', home_url( '/' ) );
		?>
		<div class="dischub-admin-wrapper">
			<div class="dischub-header-card">
				<div class="dischub-header-brand">
					<h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Official WooCommerce integration for DiscHub — Accept payments in Zimbabwe via EcoCash and InnBucks.', 'dischub-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="dischub-header-badges">
					<span class="dischub-badge dischub-badge-ver"><?php echo esc_html( 'v' . DISCHUB_WC_VERSION ); ?></span>
					<span class="dischub-badge dischub-badge-mode <?php echo 'test' === $this->mode ? 'mode-test' : 'mode-live'; ?>">
						<?php echo 'test' === $this->mode ? esc_html__( 'Test mode', 'dischub-for-woocommerce' ) : esc_html__( 'Live mode', 'dischub-for-woocommerce' ); ?>
					</span>
				</div>
			</div>

			<div class="dischub-webhook-notice">
				<strong><?php esc_html_e( 'Instant Payment Notification (IPN) Webhook URL:', 'dischub-for-woocommerce' ); ?></strong>
				<div class="dischub-webhook-box">
					<code><?php echo esc_html( $webhook_url ); ?></code>
				</div>
				<small><?php esc_html_e( 'DiscHub automatically sends real-time payment notifications to this URL on public sites. Seamless on-site mode also verifies status live on checkout.', 'dischub-for-woocommerce' ); ?></small>
			</div>

			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Check if gateway is available for checkout.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = parent::is_available();

		if ( ! $is_available ) {
			return false;
		}

		// Ensure currency is supported (USD or ZWG).
		$currency = get_woocommerce_currency();
		if ( ! in_array( $currency, array( 'USD', 'ZWG' ), true ) ) {
			return false;
		}

		// Check credentials.
		if ( empty( $this->api_key ) || empty( $this->recipient_email ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Process the payment and initialize transaction.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( esc_html__( 'Order not found. Please try again.', 'dischub-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		// Generate a unique, clean DiscHub order reference <= 30 characters.
		$unique_suffix    = substr( (string) time(), -6 );
		$dischub_order_id = 'WC' . $order->get_id() . '-' . $unique_suffix;
		$dischub_order_id = substr( $dischub_order_id, 0, 30 );

		// Selected payment method (EcoCash or InnBucks).
		$selected_method = 'ecocash';
		if ( ! empty( $_POST['dischub_selected_method'] ) ) {
			$selected_method = sanitize_text_field( wp_unslash( $_POST['dischub_selected_method'] ) );
		} elseif ( ! empty( $_POST['payment_method_data']['dischub_selected_method'] ) ) {
			$selected_method = sanitize_text_field( wp_unslash( $_POST['payment_method_data']['dischub_selected_method'] ) );
		}

		if ( ! in_array( $selected_method, array( 'ecocash', 'innbucks' ), true ) ) {
			$selected_method = 'ecocash';
		}

		// Customer Phone Number from custom field, REST body, or billing phone.
		$sender_phone = '';
		if ( ! empty( $_POST['dischub_phone_number'] ) ) {
			$sender_phone = sanitize_text_field( wp_unslash( $_POST['dischub_phone_number'] ) );
		} elseif ( ! empty( $_POST['payment_method_data']['dischub_phone_number'] ) ) {
			$sender_phone = sanitize_text_field( wp_unslash( $_POST['payment_method_data']['dischub_phone_number'] ) );
		}

		if ( empty( $sender_phone ) ) {
			$sender_phone = $order->get_billing_phone();
		}

		if ( empty( $sender_phone ) ) {
			$sender_phone = '+263780000000'; // Fallback.
		} else {
			$order->update_meta_data( '_dischub_sender_phone', $sender_phone );
			if ( empty( $order->get_billing_phone() ) ) {
				$order->set_billing_phone( $sender_phone );
			}
		}

		// API Request payload parameters.
		$args = array(
			'order_id'       => $dischub_order_id,
			'sender'         => $sender_phone,
			'amount'         => $order->get_total(),
			'currency'       => $order->get_currency(),
			'payment_method' => $selected_method,
		);

		$response = $this->api->create_order( $args );

		if ( ! $response['success'] ) {
			$error_msg = $response['error'] ?? esc_html__( 'An error occurred while communicating with DiscHub.', 'dischub-for-woocommerce' );
			$order->add_order_note(
				sprintf(
					/* translators: %s: Error message */
					__( 'DiscHub initialization failed: %s', 'dischub-for-woocommerce' ),
					$error_msg
				)
			);
			wc_add_notice( $error_msg, 'error' );
			return array( 'result' => 'failure' );
		}

		// Save DiscHub transaction reference and data to order meta.
		$order->update_meta_data( '_dischub_order_id', $dischub_order_id );
		$order->update_meta_data( '_dischub_mode', $this->mode );
		$order->update_meta_data( '_dischub_method', $selected_method );
		$order->update_meta_data( '_dischub_status', 'pending' );

		if ( ! empty( $response['data']['code'] ) ) {
			$order->update_meta_data( '_dischub_code', sanitize_text_field( $response['data']['code'] ) );
		}
		if ( ! empty( $response['data']['qrcode'] ) ) {
			$order->update_meta_data( '_dischub_qrcode', sanitize_text_field( $response['data']['qrcode'] ) );
		}

		$order->save();

		// Set order status to pending payment.
		$order->update_status(
			'pending',
			sprintf(
				/* translators: 1: Method name, 2: DiscHub reference */
				__( 'DiscHub %1$s transaction initiated. Reference: %2$s. Awaiting payment.', 'dischub-for-woocommerce' ),
				ucfirst( $selected_method ),
				$dischub_order_id
			)
		);

		// Reduce stock levels.
		wc_maybe_reduce_stock_levels( $order_id );

		// Empty cart.
		if ( isset( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

		// Redirect directly to on-site Thank You page.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Render On-Site Payment widget on the Thank You / Order Received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_onsite_thankyou_widget( $order_id ) {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || 'dischub' !== $order->get_payment_method() ) {
			return;
		}

		$rendered         = true;
		$dischub_order_id = $order->get_meta( '_dischub_order_id' );
		$method           = $order->get_meta( '_dischub_method' ) ?: 'ecocash';
		$phone            = $order->get_meta( '_dischub_sender_phone' ) ?: $order->get_billing_phone();
		$current_status   = $order->get_status();

		// If already completed or processing, show success state.
		if ( in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
			?>
			<div class="dischub-success-state" style="margin: 20px 0;">
				<h3 style="margin-top:0; color:#15803d;"><?php esc_html_e( 'Payment Confirmed Successfully', 'dischub-for-woocommerce' ); ?></h3>
				<p style="margin-bottom:0; font-size:1.05rem;"><?php esc_html_e( 'Your payment has been received and verified via DiscHub. Thank you for your order!', 'dischub-for-woocommerce' ); ?></p>
			</div>
			<?php
			return;
		}

		// Enqueue scripts and styles.
		wp_enqueue_style( 'dischub-onsite-css', DISCHUB_WC_URL . 'assets/css/dischub-onsite.css', array(), DISCHUB_WC_VERSION );
		wp_enqueue_script( 'dischub-onsite-js', DISCHUB_WC_URL . 'assets/js/dischub-onsite.js', array(), DISCHUB_WC_VERSION, true );
		?>
		<div id="dischub-onsite-widget"
			class="dischub-onsite-card"
			data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
			data-order-key="<?php echo esc_attr( $order->get_order_key() ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'dischub_poll_order_nonce' ) ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">

			<div class="dischub-onsite-header">
				<div style="margin-bottom: 14px;">
					<span class="dischub-onsite-badge">
						<span class="dischub-pulse-circle"></span>
						<?php esc_html_e( 'Awaiting Mobile Payment Approval', 'dischub-for-woocommerce' ); ?>
					</span>
				</div>

				<?php if ( 'ecocash' === $method ) : ?>
					<h2 class="dischub-onsite-title" style="font-size:1.6rem; margin-bottom:10px;">
						<?php esc_html_e( 'EcoCash PIN Prompt Sent', 'dischub-for-woocommerce' ); ?>
					</h2>
					<p class="dischub-onsite-desc" style="font-size:1.1rem;">
						<?php
						printf(
							/* translators: 1: Phone number, 2: Currency, 3: Amount */
							esc_html__( 'Please check your phone %1$s now and enter your EcoCash PIN to approve the payment of %2$s %3$s.', 'dischub-for-woocommerce' ),
							'<strong class="dischub-phone-highlight">' . esc_html( $phone ) . '</strong>',
							'<strong>' . esc_html( $order->get_currency() ) . '</strong>',
							'<strong>' . esc_html( $order->get_total() ) . '</strong>'
						);
						?>
					</p>
				<?php elseif ( 'innbucks' === $method ) : ?>
					<?php
					$code   = $order->get_meta( '_dischub_code' );
					$qrcode = $order->get_meta( '_dischub_qrcode' );
					?>
					<h2 class="dischub-onsite-title"><?php esc_html_e( 'Complete with InnBucks', 'dischub-for-woocommerce' ); ?></h2>
					<div class="dischub-innbucks-box">
						<p><?php esc_html_e( 'Enter this authorization code in your InnBucks App:', 'dischub-for-woocommerce' ); ?></p>
						<div class="dischub-innbucks-code"><?php echo esc_html( $code ?: '---' ); ?></div>
						<?php if ( ! empty( $qrcode ) ) : ?>
							<img src="data:image/png;base64,<?php echo esc_attr( $qrcode ); ?>" alt="<?php esc_attr_e( 'InnBucks QR Code', 'dischub-for-woocommerce' ); ?>" class="dischub-qr-img" />
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Countdown & Auto-Check Bar -->
				<div class="dischub-countdown-wrapper">
					<div class="dischub-polling-status">
						<span class="dischub-spinner" id="dischub-spinner-icon"></span>
						<span id="dischub-status-label"><?php esc_html_e( 'Checking for payment confirmation in:', 'dischub-for-woocommerce' ); ?></span>
						<strong id="dischub-countdown-sec" style="font-size:1.15rem; color:#0d6efd; margin-left:4px;">15s</strong>
					</div>
					<div class="dischub-progress-bar">
						<div id="dischub-progress-fill" class="dischub-progress-fill"></div>
					</div>
				</div>

				<div style="margin-top: 14px;">
					<button type="button" id="dischub-manual-check-btn" class="dischub-action-btn">
						<?php esc_html_e( "I've Approved on My Phone - Check Status Now", 'dischub-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle Instant Payment Notification (IPN) webhook from DiscHub.
	 */
	public function handle_webhook() {
		$raw_input = file_get_contents( 'php://input' );
		$this->api->log( 'Incoming DiscHub Webhook payload: ' . $raw_input );

		$data = json_decode( $raw_input, true );
		if ( empty( $data ) && ! empty( $_POST ) ) {
			$data = wp_unslash( $_POST );
		}

		$dischub_order_id = sanitize_text_field( $data['order_id'] ?? ( $_GET['order_id'] ?? '' ) );

		if ( empty( $dischub_order_id ) ) {
			$this->api->log( 'Webhook error: Missing order_id in request.', 'error' );
			wp_send_json_error( array( 'message' => esc_html__( 'Missing order ID', 'dischub-for-woocommerce' ) ), 400 );
			exit;
		}

		$order = $this->get_order_by_dischub_id( $dischub_order_id );

		if ( ! $order ) {
			$this->api->log( "Webhook error: No matching WooCommerce order found for DiscHub ID: {$dischub_order_id}", 'error' );
			wp_send_json_error( array( 'message' => esc_html__( 'Order not found', 'dischub-for-woocommerce' ) ), 404 );
			exit;
		}

		$verification = $this->api->check_payment_status( $dischub_order_id );

		if ( ! $verification['success'] ) {
			$this->api->log( "Webhook verification failed for Order ID {$dischub_order_id}: " . ( $verification['error'] ?? '' ), 'error' );
			wp_send_json_error( array( 'message' => esc_html__( 'Status verification failed', 'dischub-for-woocommerce' ) ), 400 );
			exit;
		}

		$status = $verification['status'];
		$this->update_order_payment_status( $order, $status, $dischub_order_id, $verification['data'] ?? array() );

		wp_send_json_success( array( 'message' => esc_html__( 'Webhook processed successfully', 'dischub-for-woocommerce' ) ), 200 );
		exit;
	}

	/**
	 * Helper: Update WooCommerce order status based on verified DiscHub status.
	 *
	 * @param WC_Order $order WooCommerce Order object.
	 * @param string   $status Status string to normalize.
	 * @param string   $dischub_order_id Transaction ID.
	 * @param array    $data Additional DiscHub payload details.
	 */
	public function update_order_payment_status( $order, $status, $dischub_order_id, array $data = array() ) {
		$normalized_status = Dischub_API::normalize_status( $status );
		$current_status    = $order->get_status();
		$order->update_meta_data( '_dischub_status', $normalized_status );

		if ( ! empty( $data['timestamp'] ) ) {
			$order->update_meta_data( '_dischub_paid_at', sanitize_text_field( $data['timestamp'] ) );
		}
		if ( ! empty( $data['sender'] ) ) {
			$order->update_meta_data( '_dischub_sender', sanitize_text_field( $data['sender'] ) );
		}

		if ( 'success' === $normalized_status ) {
			if ( ! in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
				$amount   = $data['amount'] ?? $order->get_total();
				$currency = $data['currency'] ?? $order->get_currency();

				$order->payment_complete( $dischub_order_id );
				$order->add_order_note(
					sprintf(
						/* translators: 1: Currency, 2: Amount, 3: DiscHub Order ID */
						__( 'DiscHub Payment Confirmed: %1$s %2$s received successfully. DiscHub Ref: %3$s', 'dischub-for-woocommerce' ),
						$currency,
						$amount,
						$dischub_order_id
					)
				);
				$this->api->log( "Order #{$order->get_id()} marked as paid via DiscHub Ref {$dischub_order_id}." );
			}
		} elseif ( 'failed' === $normalized_status ) {
			if ( 'failed' !== $current_status && ! in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
				$order->update_status(
					'failed',
					sprintf(
						/* translators: %s: DiscHub Order ID */
						__( 'DiscHub: Payment failed for transaction reference %s.', 'dischub-for-woocommerce' ),
						$dischub_order_id
					)
				);
				$this->api->log( "Order #{$order->get_id()} marked as failed via DiscHub Ref {$dischub_order_id}." );
			}
		} elseif ( 'pending' === $normalized_status ) {
			$this->api->log( "Order #{$order->get_id()} DiscHub status is pending." );
		}

		$order->save();
	}

	/**
	 * Find WooCommerce Order by DiscHub Order ID meta.
	 *
	 * @param string $dischub_order_id DiscHub ID.
	 * @return WC_Order|false
	 */
	public function get_order_by_dischub_id( $dischub_order_id ) {
		$orders = wc_get_orders(
			array(
				'limit'        => 1,
				'meta_key'     => '_dischub_order_id',
				'meta_value'   => $dischub_order_id,
				'meta_compare' => '=',
			)
		);

		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		if ( preg_match( '/^WC(\d+)-/i', $dischub_order_id, $matches ) ) {
			$wc_order_id = absint( $matches[1] );
			$order       = wc_get_order( $wc_order_id );
			if ( $order ) {
				return $order;
			}
		}

		return false;
	}
}
