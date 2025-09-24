<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Plugins_Curcy {
	public static $settings, $is_pro, $enable, $cache = array();

	public function __construct() {
		if ( ! is_plugin_active( 'woocommerce-multi-currency/woocommerce-multi-currency.php' ) &&
		     ! is_plugin_active( 'woo-multi-currency/woo-multi-currency.php' ) ) {
			return;
		}
		add_action( 'viwcpr_reminder_before_get_product_html', array(
			__CLASS__,
			'viwcpr_reminder_before_get_product_html'
		), 10, 2 );
		add_action( 'viwcpr_reminder_after_get_product_html', array(
			__CLASS__,
			'viwcpr_reminder_after_get_product_html'
		), 10, 2 );
	}

	public static function set_current_currency( $result ) {
		if ( ! empty( self::$cache['currency'] ) ) {
			$result = self::$cache['currency'];
		}

		return $result;
	}

	public static function viwcpr_reminder_before_get_product_html( $order, $products ) {
		if ( ! is_a( $order, 'WC_Order' ) || empty( $products ) ) {
			return;
		}
		if ( ! self::get_enable() ) {
			return;
		}
		$currency = $order->get_currency();
		if ( $currency !== self::$settings->get_default_currency() ) {
			self::$cache['currency'] = $currency;
			add_filter( 'woocommerce_currency', array( __CLASS__, 'set_current_currency' ), PHP_INT_MAX, 1 );
			self::$settings->set_current_currency( self::$cache['currency'] );
		}
	}

	public static function viwcpr_reminder_after_get_product_html( $order, $products ) {
		remove_filter( 'woocommerce_currency', array( __CLASS__, 'set_current_currency' ), PHP_INT_MAX );
		if ( ! is_a( $order, 'WC_Order' ) || empty( $products ) || empty( self::$cache['currency'] ) ) {
			return;
		}
		if ( ! self::get_enable() ) {
			return;
		}
		self::$settings->set_current_currency( self::$settings->get_default_currency() );
	}

	public static function get_enable() {
		if ( self::$enable !== null ) {
			return self::$enable;
		}
		self::$settings = self::get_settings();
		if ( ! self::$settings ) {
			return self::$enable = null;
		}

		return apply_filters( 'viwcpr_curcy_enable', self::$settings->get_default_currency() );
	}

	public static function get_settings() {
		if ( self::$settings !== null ) {
			return self::$settings;
		}
		if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			self::$settings = WOOMULTI_CURRENCY_Data::get_ins( true );
			self::$is_pro   = true;
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			self::$settings = WOOMULTI_CURRENCY_F_Data::get_ins();
		}

		return self::$settings;
	}
}