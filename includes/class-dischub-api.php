<?php
/**
 * DiscHub API Client
 *
 * Handles HTTP requests and responses to DiscHub payment gateway REST endpoints.
 *
 * @package DiscHub_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dischub_API {

	const CREATE_ORDER_ENDPOINT = 'https://dischub.co.zw/api/orders/create/';
	const STATUS_CHECK_ENDPOINT = 'https://dischub.co.zw/api/payment/status/3/step/';
	const PAYMENT_REDIRECT_BASE = 'https://dischub.co.zw/api/make/payment/to/';

	/**
	 * DiscHub API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * DiscHub Merchant Email.
	 *
	 * @var string
	 */
	private $recipient_email;

	/**
	 * Gateway Mode: 'test' or 'live'.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Debug logging enabled.
	 *
	 * @var bool
	 */
	private $debug_logging;

	/**
	 * WC_Logger instance.
	 *
	 * @var WC_Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param string $api_key API key.
	 * @param string $recipient_email Merchant account email.
	 * @param string $mode 'test' or 'live'.
	 * @param bool   $debug_logging Whether to log debug messages.
	 */
	public function __construct( $api_key, $recipient_email, $mode = 'live', $debug_logging = false ) {
		$this->api_key         = trim( (string) $api_key );
		$this->recipient_email = trim( (string) $recipient_email );
		$this->mode            = ( 'test' === $mode ) ? 'test' : 'live';
		$this->debug_logging   = (bool) $debug_logging;
	}

	/**
	 * Log message if debugging is enabled.
	 *
	 * @param string $message Message to log.
	 * @param string $level 'info', 'error', 'debug', etc.
	 */
	public function log( $message, $level = 'info' ) {
		if ( ! $this->debug_logging ) {
			return;
		}

		if ( null === $this->logger && function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}

		if ( $this->logger ) {
			$this->logger->log( $level, $message, array( 'source' => 'dischub-for-woocommerce' ) );
		}
	}

	/**
	 * Normalize status strings from DiscHub API into canonical status values:
	 * 'success', 'failed', or 'pending'.
	 *
	 * @param mixed $status_raw Raw status value from DiscHub.
	 * @return string 'success'|'failed'|'pending'
	 */
	public static function normalize_status( $status_raw ) {
		if ( is_bool( $status_raw ) ) {
			return $status_raw ? 'success' : 'failed';
		}

		$status = strtolower( trim( (string) $status_raw ) );

		if ( in_array( $status, array( 'success', 'successful', 'paid', 'completed', 'complete', 'approved', 'done', 'cleared', 'settled', 'ok', 'true', '1' ), true ) ) {
			return 'success';
		}

		if ( in_array( $status, array( 'failed', 'failure', 'cancelled', 'canceled', 'declined', 'rejected', 'expired', 'timed_out', 'timeout', 'invalid', 'false', '0' ), true ) ) {
			return 'failed';
		}

		return 'pending';
	}

	/**
	 * Create a new payment order on DiscHub.
	 *
	 * @param array $args Order parameters.
	 * @return array
	 */
	public function create_order( array $args ) {
		if ( empty( $this->api_key ) || empty( $this->recipient_email ) ) {
			return array(
				'success' => false,
				'error'   => __( 'DiscHub API Key or Merchant Email is missing in settings.', 'dischub-for-woocommerce' ),
			);
		}

		// Ensure order_id is string and max 30 characters.
		$order_id       = substr( (string) ( $args['order_id'] ?? '' ), 0, 30 );
		$sender         = self::sanitize_phone_number( $args['sender'] ?? '' );
		$amount         = round( (float) ( $args['amount'] ?? 0 ), 2 );
		$currency       = strtoupper( trim( (string) ( $args['currency'] ?? 'USD' ) ) );
		$payment_method = sanitize_text_field( $args['payment_method'] ?? '' );

		if ( ! in_array( $currency, array( 'USD', 'ZWG' ), true ) ) {
			$currency = 'USD';
		}

		if ( $amount <= 0 || $amount >= 100000 ) {
			return array(
				'success' => false,
				'error'   => __( 'Amount must be greater than 0 and less than 100,000.', 'dischub-for-woocommerce' ),
			);
		}

		$payload = array(
			'order_id'  => (string) $order_id,
			'sender'    => $sender,
			'recipient' => $this->recipient_email,
			'amount'    => $amount,
			'currency'  => $currency,
		);

		// Non-redirect vs Hosted flow.
		$is_direct = in_array( $payment_method, array( 'ecocash', 'innbucks', 'omari' ), true );

		if ( $is_direct ) {
			// Direct non-redirect integration.
			$payload['payment_method'] = $payment_method;

			if ( 'ecocash' === $payment_method ) {
				$payload['payment_details'] = array(
					'ecocash_number' => $sender,
				);
			} elseif ( 'omari' === $payment_method ) {
				// Omari requires format: 263780000000 (no leading +).
				$omari_num = ltrim( $sender, '+' );
				if ( 0 === strpos( $omari_num, '0' ) ) {
					$omari_num = '263' . substr( $omari_num, 1 );
				}
				$payload['payment_details'] = array(
					'omari_number' => $omari_num,
				);
			}
		} else {
			// Hosted flow.
			$payload['mode'] = $this->mode;

			// callback_url is optional and DiscHub strictly requires HTTPS on public domains.
			if ( ! empty( $args['callback_url'] ) ) {
				$cb_url   = esc_url_raw( $args['callback_url'] );
				$is_https = ( 'https' === wp_parse_url( $cb_url, PHP_URL_SCHEME ) );
				$host     = wp_parse_url( $cb_url, PHP_URL_HOST );
				$is_local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
					|| ( substr( (string) $host, -6 ) === '.local' )
					|| ( substr( (string) $host, -5 ) === '.test' )
					|| ( false === strpos( (string) $host, '.' ) );

				if ( $is_https && ! $is_local ) {
					$payload['callback_url'] = $cb_url;
				} else {
					$this->log( 'Skipping callback_url (' . $cb_url . ') because DiscHub strictly requires HTTPS on a public domain.' );
				}
			}

			// redirect_url is optional and DiscHub strictly requires a valid public domain.
			if ( ! empty( $args['redirect_url'] ) ) {
				$redir_url = esc_url_raw( $args['redirect_url'] );
				$host      = wp_parse_url( $redir_url, PHP_URL_HOST );
				$is_local  = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
					|| ( substr( (string) $host, -6 ) === '.local' )
					|| ( substr( (string) $host, -5 ) === '.test' )
					|| ( false === strpos( (string) $host, '.' ) );

				if ( ! $is_local ) {
					$payload['redirect_url'] = $redir_url;
				} else {
					$this->log( 'Skipping redirect_url (' . $redir_url . ') because DiscHub strictly requires a public domain name.' );
				}
			}
		}

		$this->log( 'Initiating DiscHub Order Creation: ' . wp_json_encode( $payload ) );

		$response = wp_remote_post(
			self::CREATE_ORDER_ENDPOINT,
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'X-Api-Key'    => $this->api_key,
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log( 'DiscHub Order Creation WP_Error: ' . $error_message, 'error' );
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: Error message from WP_Error */
					__( 'Unable to connect to DiscHub: %s', 'dischub-for-woocommerce' ),
					$error_message
				),
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$this->log( "DiscHub Order Creation Response (HTTP {$http_code}): " . $body );

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid response received from DiscHub API.', 'dischub-for-woocommerce' ),
			);
		}

		$status = strtolower( (string) ( $data['status'] ?? '' ) );

		if ( 'success' === $status || 201 === $http_code || 200 === $http_code ) {
			return array(
				'success'        => true,
				'is_direct'      => $is_direct,
				'payment_method' => $payment_method,
				'data'           => $data,
				'redirect_url'   => self::PAYMENT_REDIRECT_BASE . rawurlencode( $order_id ),
			);
		}

		$error_message = $data['message'] ?? __( 'DiscHub transaction could not be initialized.', 'dischub-for-woocommerce' );
		return array(
			'success' => false,
			'error'   => $error_message,
			'data'    => $data,
		);
	}

	/**
	 * Check payment status of an order on DiscHub.
	 *
	 * @param string $order_id DiscHub order ID.
	 * @return array Array with 'success' (bool), 'status' (string: 'success'|'pending'|'failed'|'error'), 'data' (array), 'error' (string).
	 */
	public function check_payment_status( $order_id ) {
		if ( empty( $this->api_key ) || empty( $this->recipient_email ) ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'error'   => __( 'DiscHub API Key or Merchant Email is missing in settings.', 'dischub-for-woocommerce' ),
			);
		}

		$order_id = substr( (string) $order_id, 0, 30 );
		$payload  = array(
			'order_id'  => (string) $order_id,
			'recipient' => $this->recipient_email,
		);

		$this->log( 'Checking DiscHub Payment Status for Order ID: ' . $order_id );

		$response = wp_remote_post(
			self::STATUS_CHECK_ENDPOINT,
			array(
				'method'      => 'POST',
				'timeout'     => 30,
				'httpversion' => '1.1',
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'X-Api-Key'    => $this->api_key,
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log( 'DiscHub Status Check WP_Error: ' . $error_message, 'error' );
			return array(
				'success' => false,
				'status'  => 'error',
				'error'   => $error_message,
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$this->log( "DiscHub Status Check Response (HTTP {$http_code}): " . $body );

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'error'   => __( 'Invalid response format from DiscHub status endpoint.', 'dischub-for-woocommerce' ),
			);
		}

		// Extract status from all possible response keys.
		$raw_status = '';
		if ( isset( $data['status'] ) && is_string( $data['status'] ) ) {
			$raw_status = $data['status'];
		} elseif ( isset( $data['payment_status'] ) ) {
			$raw_status = $data['payment_status'];
		} elseif ( isset( $data['data']['status'] ) ) {
			$raw_status = $data['data']['status'];
		} elseif ( isset( $data['data']['payment_status'] ) ) {
			$raw_status = $data['data']['payment_status'];
		} elseif ( isset( $data['order_status'] ) ) {
			$raw_status = $data['order_status'];
		} elseif ( isset( $data['status'] ) && is_bool( $data['status'] ) ) {
			$raw_status = $data['status'] ? 'success' : 'failed';
		}

		$normalized = self::normalize_status( $raw_status );

		return array(
			'success' => true,
			'status'  => $normalized,
			'raw'     => $raw_status,
			'data'    => $data,
			'error'   => ( 'failed' === $normalized || 'error' === strtolower( (string) $raw_status ) ) ? ( $data['message'] ?? '' ) : '',
		);
	}

	/**
	 * Validate Omari OTP on DiscHub status check endpoint.
	 *
	 * @param string $order_id DiscHub order ID.
	 * @param string $otp 6-digit OTP received by customer.
	 * @return array
	 */
	public function verify_omari_otp( $order_id, $otp ) {
		$payload = array(
			'order_id'        => (string) substr( $order_id, 0, 30 ),
			'recipient'       => $this->recipient_email,
			'payment_method'  => 'omari',
			'payment_details' => array(
				'omari_otp' => trim( (string) $otp ),
			),
		);

		$this->log( 'Submitting Omari OTP Verification: ' . wp_json_encode( $payload ) );

		$response = wp_remote_post(
			self::STATUS_CHECK_ENDPOINT,
			array(
				'method'      => 'POST',
				'timeout'     => 30,
				'httpversion' => '1.1',
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'X-Api-Key'    => $this->api_key,
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'error'   => $response->get_error_message(),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( is_array( $data ) ) {
			$raw_status = $data['status'] ?? ( $data['payment_status'] ?? '' );
			$normalized = self::normalize_status( $raw_status );
			return array(
				'success' => ( 'success' === $normalized ),
				'status'  => $normalized,
				'data'    => $data,
				'error'   => ( 'success' !== $normalized ) ? ( $data['message'] ?? __( 'Invalid or expired OTP.', 'dischub-for-woocommerce' ) ) : '',
			);
		}

		return array(
			'success' => false,
			'status'  => 'error',
			'error'   => __( 'Invalid response from Omari OTP verification.', 'dischub-for-woocommerce' ),
		);
	}

	/**
	 * Normalize phone numbers to DiscHub-accepted formats:
	 * (+263780070488, 0263780070488, 0780070488).
	 *
	 * @param string $phone Raw phone number.
	 * @return string Normalized phone number.
	 */
	public static function sanitize_phone_number( $phone ) {
		$phone = trim( (string) $phone );
		$clean = preg_replace( '/[^\d\+]/', '', $phone );

		if ( 0 === strpos( $clean, '00263' ) ) {
			return '+' . substr( $clean, 2 );
		}

		if ( 0 === strpos( $clean, '263' ) ) {
			return '+' . $clean;
		}

		if ( 0 === strpos( $clean, '+263' ) ) {
			return $clean;
		}

		if ( 0 === strpos( $clean, '0' ) ) {
			return $clean;
		}

		if ( 9 === strlen( $clean ) && 0 !== strpos( $clean, '+' ) ) {
			return '+263' . $clean;
		}

		return $clean;
	}
}
