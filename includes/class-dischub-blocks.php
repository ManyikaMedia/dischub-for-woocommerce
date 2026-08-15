<?php
/**
 * DiscHub Payment Gateway Blocks Integration
 *
 * @package DiscHub_For_WooCommerce
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dischub_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = 'dischub';

	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_dischub_settings', array() );
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_handle = 'dischub-blocks-integration';

		wp_register_script(
			$script_handle,
			DISCHUB_WC_URL . 'assets/js/dischub-blocks.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			DISCHUB_WC_VERSION,
			true
		);

		return array( $script_handle );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'DiscHub (EcoCash & InnBucks)', 'dischub-for-woocommerce' ) ),
			'description' => $this->get_setting( 'description', __( 'Pay securely on this website with EcoCash or InnBucks.', 'dischub-for-woocommerce' ) ),
			'icon'        => DISCHUB_WC_URL . 'assets/images/dischub-badge.svg',
			'supports'    => array( 'products' ),
		);
	}
}
