<?php
/**
 * WPFactory Cross-Selling - My Plugins - Signals.
 *
 * Cheap reads of store settings for the rules engine: options and cached
 * WooCommerce helpers only. No order table queries, nothing leaves the site.
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Signals', false ) ) {

	/**
	 * Signals.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Signals {

		/**
		 * defaults.
		 *
		 * free_ship_min is the lowest "minimum order amount" of an enabled free shipping
		 * method, or null when no enabled free shipping method has one.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		static function defaults() {
			return array(
				'woo'           => false,
				'eu'            => false,
				'products'      => 0,
				'attributes'    => 0,
				'free_ship'     => false,
				'free_ship_min' => null,
				'coupons'       => false,
				'on_sale'       => 0,
				'gateways'      => 0,
				'countries'     => 0,
				'registration'  => false,
			);
		}

		/**
		 * collect.
		 *
		 * Each read is isolated, so one failing WooCommerce helper can't break the page.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		static function collect() {
			$s = self::defaults();
			if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
				return $s;
			}
			$s['woo'] = true;

			$reads = array(
				'eu'           => array( __CLASS__, 'read_eu' ),
				'countries'    => array( __CLASS__, 'read_countries' ),
				'products'     => array( __CLASS__, 'read_products' ),
				'attributes'   => array( __CLASS__, 'read_attributes' ),
				'free_ship'    => array( __CLASS__, 'read_free_shipping' ),
				'coupons'      => array( __CLASS__, 'read_coupons' ),
				'on_sale'      => array( __CLASS__, 'read_on_sale' ),
				'gateways'     => array( __CLASS__, 'read_gateways' ),
				'registration' => array( __CLASS__, 'read_registration' ),
			);
			foreach ( $reads as $key => $callback ) {
				try {
					$value = call_user_func( $callback );
				} catch ( \Throwable $e ) {
					continue;
				}
				if ( 'free_ship' === $key ) {
					$s['free_ship']     = $value['enabled'];
					$s['free_ship_min'] = $value['min'];
				} else {
					$s[ $key ] = $value;
				}
			}
			return $s;
		}

		/**
		 * read_eu.
		 *
		 * The store is based in the EU (its base country). Countries it sells or ships to don't count:
		 * WooCommerce sells to all countries by default, which would make almost every store "EU".
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return bool
		 */
		static function read_eu() {
			$countries = WC()->countries;
			return in_array( $countries->get_base_country(), (array) $countries->get_european_union_countries(), true );
		}

		/**
		 * read_countries.
		 *
		 * Number of selling countries. "Sell to all countries" returns every country, so it counts as many.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return int
		 */
		static function read_countries() {
			return count( (array) WC()->countries->get_allowed_countries() );
		}

		/**
		 * read_products.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return int
		 */
		static function read_products() {
			$counts = wp_count_posts( 'product' );
			return ( isset( $counts->publish ) ? (int) $counts->publish : 0 );
		}

		/**
		 * read_attributes.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return int
		 */
		static function read_attributes() {
			return count( (array) wc_get_attribute_taxonomies() );
		}

		/**
		 * read_free_shipping.
		 *
		 * Any shipping zone (including "Rest of the world") with an enabled free_shipping method.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array { enabled: bool, min: float|null }
		 */
		static function read_free_shipping() {
			$result = array( 'enabled' => false, 'min' => null );
			if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
				return $result;
			}
			$methods = array();
			foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
				if ( ! empty( $zone['shipping_methods'] ) ) {
					$methods = array_merge( $methods, (array) $zone['shipping_methods'] );
				}
			}
			$rest_of_world = \WC_Shipping_Zones::get_zone( 0 );
			if ( $rest_of_world ) {
				$methods = array_merge( $methods, (array) $rest_of_world->get_shipping_methods() );
			}
			foreach ( $methods as $method ) {
				if ( ! is_object( $method ) || 'free_shipping' !== $method->id || ! $method->is_enabled() ) {
					continue;
				}
				$result['enabled'] = true;
				$requires          = $method->get_option( 'requires' );
				$min               = (float) $method->get_option( 'min_amount' );
				if ( in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) && $min > 0 ) {
					$result['min'] = ( null === $result['min'] ? $min : min( $result['min'], $min ) );
				}
			}
			return $result;
		}

		/**
		 * read_coupons.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return bool
		 */
		static function read_coupons() {
			return (bool) wc_coupons_enabled();
		}

		/**
		 * read_on_sale.
		 *
		 * Cached by WooCommerce in the wc_products_onsale transient.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return int
		 */
		static function read_on_sale() {
			return count( (array) wc_get_product_ids_on_sale() );
		}

		/**
		 * read_gateways.
		 *
		 * Enabled gateways from settings, not "available" ones (those depend on the cart).
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return int
		 */
		static function read_gateways() {
			$count = 0;
			foreach ( (array) WC()->payment_gateways()->payment_gateways() as $gateway ) {
				if ( is_object( $gateway ) && isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
					$count++;
				}
			}
			return $count;
		}

		/**
		 * read_registration.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return bool
		 */
		static function read_registration() {
			return ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) );
		}

	}

}
