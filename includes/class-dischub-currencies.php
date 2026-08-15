<?php
/**
 * DiscHub Currency Handler
 * Adds Zimbabwean Currency (ZWG - Zimbabwe Gold) support to WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dischub_Currencies {

	/**
	 * Hook currency filters.
	 */
	public static function init() {
		add_filter( 'woocommerce_currencies', array( __CLASS__, 'add_zwg_currency' ) );
		add_filter( 'woocommerce_currency_symbols', array( __CLASS__, 'add_zwg_currency_symbol' ) );
	}

	/**
	 * Add ZWG currency to WooCommerce list.
	 *
	 * @param array $currencies Current currencies list.
	 * @return array
	 */
	public static function add_zwg_currency( $currencies ) {
		if ( ! isset( $currencies['ZWG'] ) ) {
			$currencies['ZWG'] = __( 'Zimbabwe Gold (ZWG)', 'dischub-for-woocommerce' );
		}
		return $currencies;
	}

	/**
	 * Add ZWG currency symbol.
	 *
	 * @param array $symbols Current currency symbols.
	 * @return array
	 */
	public static function add_zwg_currency_symbol( $symbols ) {
		if ( ! isset( $symbols['ZWG'] ) ) {
			$symbols['ZWG'] = 'ZWG';
		}
		return $symbols;
	}
}
