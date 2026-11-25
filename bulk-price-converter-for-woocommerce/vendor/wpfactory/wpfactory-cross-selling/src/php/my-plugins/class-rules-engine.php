<?php
/**
 * WPFactory Cross-Selling - My Plugins - Rules Engine.
 *
 * Picks up to 3 plugins for "Picked for your store" from store signals.
 * Runs on this site only, nothing is sent anywhere. The result is cached for 12 hours
 * and cleared when plugins or the relevant WooCommerce settings change.
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Rules_Engine', false ) ) {

	/**
	 * Rules_Engine.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Rules_Engine {

		/**
		 * Transient name.
		 *
		 * @since 1.2.0
		 */
		const TRANSIENT = 'wpfactory_my_plugins_picks';

		/**
		 * Cache lifetime in seconds (12 hours).
		 *
		 * @since 1.2.0
		 */
		const CACHE_TTL = 43200;

		/**
		 * Cache format. Bump when the cached array changes shape or the rules change.
		 *
		 * @since 1.2.0
		 */
		const CACHE_SCHEMA = 4;

		/**
		 * Number of picks.
		 *
		 * @since 1.2.0
		 */
		const MAX_PICKS = 3;

		/**
		 * Options whose change clears the cache.
		 *
		 * "woocommerce_*_settings" covers payment gateways and free shipping method instances.
		 *
		 * @since 1.2.0
		 */
		const OPTION_PATTERN = '/^woocommerce_(default_country|allowed_countries|specific_allowed_countries|all_except_countries|ship_to_countries|specific_ship_to_countries|enable_coupons|enable_myaccount_registration|.+_settings)$/';

		/**
		 * Cleared in this request already.
		 *
		 * @since 1.2.0
		 *
		 * @var bool
		 */
		protected static $cleared = false;

		/**
		 * get_competitors.
		 *
		 * Plugin folders that make a rule skip. Folders marked "unverified" are not on wp.org,
		 * so their folder names come from the vendors' usual naming, not from a listing.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		static function get_competitors() {
			return array(
				'filters'   => array(
					'woocommerce-products-filter',                 // HUSKY.
					'yith-woocommerce-ajax-navigation',            // YITH WooCommerce Ajax Product Filter.
					'yith-woocommerce-ajax-product-filter-premium', // YITH premium, unverified.
					'facetwp',                                     // FacetWP, unverified.
					'jet-smart-filters',                           // JetSmartFilters, unverified.
					'woocommerce-ajax-filters',                    // BeRocket Advanced AJAX Product Filters.
				),
				'wishlists' => array(
					'yith-woocommerce-wishlist',
					'yith-woocommerce-wishlist-premium',           // Unverified.
					'ti-woocommerce-wishlist',
					'ti-woocommerce-wishlist-premium',             // Unverified.
				),
				'helpdesks' => array(
					'awesome-support',
					'fluent-support',
					'supportcandy',
				),
			);
		}

		/**
		 * get_rules.
		 *
		 * One rule per catalog id. "when" gets ( $signals, Site_State $state ).
		 * "reason" gets ( $signals ) and returns the text for the card's reason strip
		 * (rules without one use the generic reason).
		 * A rule only fires when its plugin is not installed (checked in evaluate()).
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		static function get_rules() {
			$competitors = self::get_competitors();
			$products    = function ( $s ) {
				/* translators: %s: number of published products. */
				return sprintf( __( 'Your shop has %s products', 'wpfactory-cross-selling' ), number_format_i18n( $s['products'] ) );
			};

			$rules = array(
				'eu-vat'             => array(
					'weight' => 90,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['eu'];
					},
					'reason' => function () {
						return __( 'Your store is in the EU', 'wpfactory-cross-selling' );
					},
				),
				'product-filter'     => array(
					'weight' => 85,
					'when'   => function ( $s, $state ) use ( $competitors ) {
						return $s['woo'] && ( $s['products'] >= 100 || $s['attributes'] >= 3 ) && ! $state->any_folder_active( $competitors['filters'] );
					},
					'reason' => function ( $s ) use ( $products ) {
						if ( $s['products'] >= 100 ) {
							return $products( $s );
						}
						/* translators: %s: number of product attributes. */
						return sprintf( __( 'Your products use %s attributes', 'wpfactory-cross-selling' ), number_format_i18n( $s['attributes'] ) );
					},
				),
				'price-by-quantity'  => array(
					'weight' => 80,
					'when'   => function ( $s, $state ) {
						return $s['woo'] && $state->is_active( Catalog::get_item( 'cost-of-goods' ) );
					},
					'reason' => function () {
						return __( 'You use Cost of Goods', 'wpfactory-cross-selling' );
					},
				),
				'free-shipping-bar'  => array(
					'weight' => 80,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['free_ship'] && null !== $s['free_ship_min'];
					},
					'reason' => function ( $s ) {
						/* translators: %s: minimum order amount for free shipping, with currency. */
						return sprintf( __( 'You offer free shipping over %s', 'wpfactory-cross-selling' ), self::format_price( $s['free_ship_min'] ) );
					},
				),
				'min-max-quantity'   => array(
					'weight' => 75,
					'when'   => function ( $s, $state ) {
						return $s['woo'] && $state->is_active( Catalog::get_item( 'price-by-quantity' ) );
					},
					'reason' => function () {
						return __( 'You use Price by Quantity', 'wpfactory-cross-selling' );
					},
				),
				'msrp'               => array(
					'weight' => 70,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['on_sale'] >= 5;
					},
					'reason' => function ( $s ) {
						/* translators: %s: number of products on sale. */
						return sprintf( __( 'You run sales on %s products', 'wpfactory-cross-selling' ), number_format_i18n( $s['on_sale'] ) );
					},
				),
				'coupons-by-url'     => array(
					'weight' => 65,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['coupons'];
					},
					'reason' => function () {
						return __( 'You use coupons', 'wpfactory-cross-selling' );
					},
				),
				'payment-methods'    => array(
					'weight' => 60,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['gateways'] >= 2 && $s['countries'] >= 2;
					},
					'reason' => function ( $s ) {
						/* translators: %s: number of enabled payment methods. */
						return sprintf( __( 'You take %s payment methods', 'wpfactory-cross-selling' ), number_format_i18n( $s['gateways'] ) );
					},
				),
				'email-verification' => array(
					'weight' => 55,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['registration'];
					},
					'reason' => function () {
						return __( 'Customers can create accounts', 'wpfactory-cross-selling' );
					},
				),
				'wishlist'           => array(
					'weight' => 50,
					'when'   => function ( $s, $state ) use ( $competitors ) {
						return $s['woo'] && $s['products'] >= 20 && ! $state->any_folder_active( $competitors['wishlists'] );
					},
					'reason' => $products,
				),
				'ean'                => array(
					'weight' => 45,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['products'] >= 50;
					},
					'reason' => function ( $s ) {
						/* translators: %s: number of published products. */
						return sprintf( __( 'You manage %s products', 'wpfactory-cross-selling' ), number_format_i18n( $s['products'] ) );
					},
				),
				'cost-of-goods'      => array(
					'weight' => 45,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['products'] >= 20;
					},
					'reason' => function () {
						return __( 'See the real profit on each order', 'wpfactory-cross-selling' );
					},
				),
				'helpdesk'           => array(
					'weight' => 40,
					'when'   => function ( $s, $state ) use ( $competitors ) {
						return $s['woo'] && ! $state->any_folder_active( $competitors['helpdesks'] );
					},
					'reason' => function () {
						return __( 'Handle support inside WooCommerce', 'wpfactory-cross-selling' );
					},
				),
				'ai-image'           => array(
					'weight' => 40,
					'when'   => function ( $s ) {
						return $s['woo'] && $s['products'] >= 20;
					},
				),
				'ai-assistant'       => array(
					'weight' => 35,
					'when'   => function () {
						return true;
					},
				),
			);

			// WordPress utilities, for sites without WooCommerce.
			foreach ( array( 'slugs-manager', 'file-renaming', 'download-plugins', 'remove-special-characters', 'back-button' ) as $id ) {
				$rules[ $id ] = array(
					'weight' => 30,
					'when'   => function ( $s ) {
						return ! $s['woo'];
					},
					'reason' => function () {
						return __( 'For your WordPress site', 'wpfactory-cross-selling' );
					},
				);
			}

			return $rules;
		}

		/**
		 * get_fallback.
		 *
		 * Fills the picks when fewer than 3 rules match.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param bool $woo WooCommerce is active.
		 *
		 * @return string[] Catalog ids.
		 */
		static function get_fallback( $woo ) {
			return ( $woo ?
				array( 'cost-of-goods', 'min-max-quantity', 'free-shipping-bar', 'ai-assistant' ) :
				array( 'ai-assistant', 'slugs-manager', 'file-renaming' )
			);
		}

		/**
		 * evaluate.
		 *
		 * Rules that fire, best first. Skips plugins that are installed, can't be offered,
		 * or need WooCommerce on a site without it.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array      $signals Signals::collect() result.
		 * @param Site_State $state   Site state.
		 *
		 * @return array[] { id, weight, job, source, order }
		 */
		static function evaluate( array $signals, $state ) {
			$signals    = array_merge( Signals::defaults(), $signals );
			$candidates = array();
			$order      = 0;
			foreach ( self::get_rules() as $id => $rule ) {
				$order++;
				$item = Catalog::get_item( $id );
				if ( ! self::can_offer( $item, $signals, $state ) ) {
					continue;
				}
				if ( ! call_user_func( $rule['when'], $signals, $state ) ) {
					continue;
				}
				$candidates[] = array(
					'id'     => $id,
					'weight' => $rule['weight'],
					'job'    => $item['job'],
					'source' => 'rule',
					'order'  => $order,
				);
			}
			usort( $candidates, function ( $a, $b ) {
				return ( $a['weight'] === $b['weight'] ? $a['order'] - $b['order'] : $b['weight'] - $a['weight'] );
			} );
			return $candidates;
		}

		/**
		 * pick.
		 *
		 * Top 3 by weight, and no 2 cards for the same job tab while another option is left.
		 * Then the fallback list fills the gaps.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array      $signals Signals.
		 * @param Site_State $state   Site state.
		 *
		 * @return array[] { id, weight, job, source }
		 */
		static function pick( array $signals, $state ) {
			$signals = array_merge( Signals::defaults(), $signals );
			$picks   = self::select( self::evaluate( $signals, $state ), array() );

			if ( count( $picks ) < self::MAX_PICKS ) {
				$fallback = array();
				foreach ( self::get_fallback( $signals['woo'] ) as $id ) {
					$item = Catalog::get_item( $id );
					if ( ! self::can_offer( $item, $signals, $state ) ) {
						continue;
					}
					$fallback[] = array(
						'id'     => $id,
						'weight' => 0,
						'job'    => $item['job'],
						'source' => 'fallback',
					);
				}
				$picks = self::select( $fallback, $picks );
			}

			return array_map( function ( $pick ) {
				unset( $pick['order'] );
				return $pick;
			}, $picks );
		}

		/**
		 * select.
		 *
		 * Pass 1 takes candidates whose job is not used yet. Pass 2 allows a repeated job.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array[] $candidates Candidates, best first.
		 * @param array[] $picks      Picks so far.
		 *
		 * @return array[]
		 */
		protected static function select( array $candidates, array $picks ) {
			foreach ( array( true, false ) as $unique_jobs ) {
				foreach ( $candidates as $candidate ) {
					if ( count( $picks ) >= self::MAX_PICKS ) {
						return $picks;
					}
					$ids  = wp_list_pluck( $picks, 'id' );
					$jobs = wp_list_pluck( $picks, 'job' );
					if ( in_array( $candidate['id'], $ids, true ) ) {
						continue;
					}
					if ( $unique_jobs && in_array( $candidate['job'], $jobs, true ) ) {
						continue;
					}
					$picks[] = $candidate;
				}
			}
			return $picks;
		}

		/**
		 * can_offer.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array|null $item    Catalog item.
		 * @param array      $signals Signals.
		 * @param Site_State $state   Site state.
		 *
		 * @return bool
		 */
		protected static function can_offer( $item, array $signals, $state ) {
			return (
				$item &&
				Catalog::is_available( $item ) &&
				( ! $item['store'] || $signals['woo'] ) &&
				! $state->is_installed( $item )
			);
		}

		/**
		 * get_reason.
		 *
		 * The rule's own reason for rule picks, else the generic one.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $pick    A pick from pick().
		 * @param array $signals Signals the pick was made with.
		 *
		 * @return string
		 */
		static function get_reason( array $pick, array $signals ) {
			$signals = array_merge( Signals::defaults(), $signals );
			if ( 'rule' === $pick['source'] ) {
				$rules = self::get_rules();
				if ( isset( $rules[ $pick['id'] ]['reason'] ) ) {
					return call_user_func( $rules[ $pick['id'] ]['reason'], $signals );
				}
			}
			return ( $signals['woo'] ?
				__( 'Works with any WooCommerce store', 'wpfactory-cross-selling' ) :
				__( 'For your WordPress site', 'wpfactory-cross-selling' )
			);
		}

		/**
		 * get_picks.
		 *
		 * Cached picks with their reason text (built per request, so it follows the user's locale).
		 * Plugins installed since the cache was built are dropped.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param Site_State|null $state Site state (built from WordPress when null).
		 *
		 * @return array[] { id, reason, source }
		 */
		static function get_picks( $state = null ) {
			if ( null === $state ) {
				$state = Site_State::from_wp();
			}
			$cached = get_transient( self::TRANSIENT );
			if ( ! is_array( $cached ) || ! isset( $cached['schema'] ) || self::CACHE_SCHEMA !== $cached['schema'] ) {
				$signals = Signals::collect();
				$cached  = array(
					'schema'  => self::CACHE_SCHEMA,
					'time'    => time(),
					'signals' => $signals,
					'picks'   => self::pick( $signals, $state ),
				);
				set_transient( self::TRANSIENT, $cached, self::CACHE_TTL );
			}
			$result = array();
			foreach ( $cached['picks'] as $pick ) {
				$item = Catalog::get_item( $pick['id'] );
				if ( ! $item || $state->is_installed( $item ) ) {
					continue;
				}
				$result[] = array(
					'id'     => $pick['id'],
					'reason' => self::get_reason( $pick, $cached['signals'] ),
					'source' => $pick['source'],
				);
			}
			return $result;
		}

		/**
		 * clear_cache.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function clear_cache() {
			if ( ! self::$cleared ) {
				self::$cleared = true;
				delete_transient( self::TRANSIENT );
			}
		}

		/**
		 * maybe_clear_on_option.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $option Option name.
		 */
		static function maybe_clear_on_option( $option ) {
			if ( is_string( $option ) && preg_match( self::OPTION_PATTERN, $option ) ) {
				self::clear_cache();
			}
		}

		/**
		 * register_hooks.
		 *
		 * Cache invalidation. Called on every request by the loader (cheap: adds hooks only).
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function register_hooks() {
			$hooks = array(
				'activated_plugin',
				'deactivated_plugin',
				'deleted_plugin',
				'upgrader_process_complete',
				'woocommerce_settings_saved',
				'woocommerce_attribute_added',
				'woocommerce_attribute_deleted',
				'woocommerce_shipping_zone_method_added',
				'woocommerce_shipping_zone_method_deleted',
				'woocommerce_shipping_zone_method_status_toggled',
			);
			foreach ( $hooks as $hook ) {
				add_action( $hook, array( __CLASS__, 'clear_cache' ), 10, 0 );
			}
			add_action( 'updated_option', array( __CLASS__, 'maybe_clear_on_option' ), 10, 1 );
			add_action( 'added_option', array( __CLASS__, 'maybe_clear_on_option' ), 10, 1 );
		}

		/**
		 * format_price.
		 *
		 * Plain text price ("$50", "50,00 €"), whole amounts without decimals.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param float $amount Amount.
		 *
		 * @return string
		 */
		static function format_price( $amount ) {
			$amount   = (float) $amount;
			$is_whole = ( floor( $amount ) === $amount );
			if ( function_exists( 'wc_price' ) ) {
				$html = wc_price( $amount, array( 'decimals' => ( $is_whole ? 0 : wc_get_price_decimals() ) ) );
				return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
			}
			return number_format_i18n( $amount, ( $is_whole ? 0 : 2 ) );
		}

	}

}
