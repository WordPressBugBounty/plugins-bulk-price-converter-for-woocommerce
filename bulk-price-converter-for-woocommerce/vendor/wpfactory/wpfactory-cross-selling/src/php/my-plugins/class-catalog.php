<?php
/**
 * WPFactory Cross-Selling - My Plugins - Catalog.
 *
 * Every WPFactory (and WBW) plugin the My Plugins page knows about.
 * The catalog is bundled with the library and only changes with a release
 * (no remote catalog, wp.org guideline 7).
 *
 * Sources (checked 2026-11-24):
 * - wp.org slugs, names and icons: api.wordpress.org, authors "wpcodefactory" and "woobewoo".
 * - Main file names: "Plugin Name" headers in the github.com/wpcodefactory repos.
 * - Pro links: live product pages listed by wpfactory.com/wp-json/wp/v2/product.
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Catalog', false ) ) {

	/**
	 * Catalog.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Catalog {

		/**
		 * Catalog version. Bump only when a new plugin is added (drives the menu dot).
		 *
		 * @since 1.2.0
		 */
		const CATALOG_VERSION = 1;

		/**
		 * "Top plugins" tab: catalog ids, in the order they are listed.
		 *
		 * 9 top WPFactory plugins plus Product Filter by WBW, by wordpress.org active installs
		 * (checked 2026-11-24). To change the tab, edit this list.
		 *
		 * @since 1.2.0
		 */
		const TOP = array(
			'product-filter',       // 50,000+ active installs.
			'download-plugins',     // 30,000+.
			'ean',                  // 10,000+.
			'order-min-max-amount', // 10,000+.
			'cost-of-goods',        // 8,000+.
			'email-verification',   // 8,000+.
			'eu-vat',               // 7,000+.
			'payment-methods',      // 4,000+.
			'min-max-quantity',     // 3,000+.
			'wishlist',             // 2,000+.
		);

		/**
		 * Item defaults.
		 *
		 * - name, desc:  short display copy (no em or en dashes).
		 * - job:         tab id from get_jobs() (any tab but "top", which comes from TOP).
		 * - top:         computed, not set here: the id is in TOP.
		 * - store:       needs WooCommerce (hidden when WooCommerce is not active).
		 * - vendor:      "wpfactory" or "wbw".
		 * - wporg:       wp.org slug, so the free version can be installed. Empty for Pro-only.
		 * - free_file:   free plugin file as WordPress stores it ("folder/file.php").
		 * - pro_file:    Pro plugin file. Matching also accepts the same file name in another folder.
		 * - pro_url:     Pro product page, no tracking params. Empty when there is no Pro.
		 * - icon_url:    wp.org icon (ps.w.org).
		 * - icon_file:   bundled icon, relative to the library's assets/img/icons/ (plugins without a wp.org listing).
		 * - abbr:        two letters for the fallback badge when there is no icon.
		 *
		 * @since 1.2.0
		 *
		 * @var array
		 */
		protected static $defaults = array(
			'name'      => '',
			'desc'      => '',
			'job'       => '',
			'store'     => true,
			'vendor'    => 'wpfactory',
			'wporg'     => '',
			'free_file' => '',
			'pro_file'  => '',
			'pro_url'   => '',
			'icon_url'  => '',
			'icon_file' => '',
			'abbr'      => '',
		);

		/**
		 * Cached items (per request, per locale).
		 *
		 * @since 1.2.0
		 *
		 * @var array|null
		 */
		protected static $items = null;

		/**
		 * get_jobs.
		 *
		 * Tab ids and labels, in display order. "top" is a tab, not a job.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		static function get_jobs() {
			return array(
				'top'     => __( 'Top plugins', 'wpfactory-cross-selling' ),
				'sell'    => __( 'Sell more per order', 'wpfactory-cross-selling' ),
				'display' => __( 'Store display', 'wpfactory-cross-selling' ),
				'rules'   => __( 'Set order rules', 'wpfactory-cross-selling' ),
				'numbers' => __( 'Know your numbers', 'wpfactory-cross-selling' ),
				'support' => __( 'Support customers', 'wpfactory-cross-selling' ),
				'tidy'    => __( 'Tidy up WordPress', 'wpfactory-cross-selling' ),
				'manage'  => __( 'Manage your store', 'wpfactory-cross-selling' ),
			);
		}

		/**
		 * get_items.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array Items keyed by catalog id, in display order.
		 */
		static function get_items() {
			if ( null === self::$items ) {
				$items = array();
				foreach ( self::get_raw_items() as $id => $item ) {
					$item         = array_merge( self::$defaults, $item, array( 'id' => $id ) );
					$item['top']  = in_array( $id, self::TOP, true );
					$items[ $id ] = $item;
				}
				self::$items = $items;
			}
			return self::$items;
		}

		/**
		 * get_item.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $id Catalog id.
		 *
		 * @return array|null
		 */
		static function get_item( $id ) {
			$items = self::get_items();
			return isset( $items[ $id ] ) ? $items[ $id ] : null;
		}

		/**
		 * is_available.
		 *
		 * An item can be offered only if it can be installed from wp.org or bought.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 *
		 * @return bool
		 */
		static function is_available( $item ) {
			return ( '' !== $item['wporg'] || '' !== $item['pro_url'] );
		}

		/**
		 * is_pro_only.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 *
		 * @return bool
		 */
		static function is_pro_only( $item ) {
			return ( '' === $item['wporg'] && '' !== $item['pro_url'] );
		}

		/**
		 * reset.
		 *
		 * Drops the per-request cache (after a locale switch, and in tests).
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function reset() {
			self::$items = null;
		}

		/**
		 * get_raw_items.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		protected static function get_raw_items() {
			$wpf = 'https://wpfactory.com/item/';
			$psw = 'https://ps.w.org/';

			return array(

				'ai-image'                     => array(
					'name'      => __( 'AI Product Image Generator', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Turn plain product photos into professional images with AI, in bulk.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'pro_file'  => 'ai-product-image-generator/ai-product-image-generator.php',
					'pro_url'   => $wpf . 'ai-product-image-generator-for-woocommerce/',
					'icon_file' => 'ai-image.png',
					'abbr'      => 'AI',
				),
				'helpdesk'                     => array(
					'name'      => __( 'Helpdesk Support Tickets', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Let customers open support tickets from their account, linked to their orders.', 'wpfactory-cross-selling' ),
					'job'       => 'support',
					'wporg'     => 'support-ticket-system-for-woocommerce',
					'free_file' => 'support-ticket-system-for-woocommerce/support-ticket-system-for-woocommerce.php',
					'pro_file'  => 'helpdesk-support-ticket-system-for-woocommerce-pro/support-ticket-system-woocommerce-pro.php',
					'pro_url'   => $wpf . 'helpdesk-support-ticketing-system-for-woocommerce/',
					'icon_url'  => $psw . 'support-ticket-system-for-woocommerce/assets/icon.svg?rev=3309458',
					'abbr'      => 'HD',
				),
				// Not on wp.org yet and no live product page: kept for detection, not offered (is_available() is false).
				// free_file follows the wpf-pro-to-free naming (X-pro.php becomes X.php), not confirmed.
				'ai-assistant'                 => array(
					'name'      => __( 'AI Assistant and MCP Server', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Run site tasks in plain language, or connect Claude and ChatGPT.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'store'     => false,
					'free_file' => 'ai-assistant-mcp/ai-assistant-mcp.php',
					'pro_file'  => 'ai-assistant-mcp-pro/ai-assistant-mcp-pro.php',
					'abbr'      => 'AA',
				),
				'product-filter'               => array(
					'name'      => __( 'Product Filter by WBW', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Product filters that work with any theme and are built for mobile.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'vendor'    => 'wbw',
					'wporg'     => 'woo-product-filter',
					'free_file' => 'woo-product-filter/woo-product-filter.php',
					'pro_file'  => 'woofilter-pro/woofilter-pro.php',
					'pro_url'   => 'https://woobewoo.com/woocommerce-product-filter/',
					'icon_url'  => $psw . 'woo-product-filter/assets/icon-256x256.png?rev=3326294',
					'abbr'      => 'PF',
				),

				// Sell more per order.
				'wishlist'                     => array(
					'name'      => __( 'Wishlist', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Let customers save products to multiple named wishlists and share them.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'wish-list-for-woocommerce',
					'free_file' => 'wish-list-for-woocommerce/wish-list-for-woocommerce.php',
					'pro_file'  => 'wish-list-for-woocommerce-pro/wish-list-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'wish-list-woocommerce/',
					'icon_url'  => $psw . 'wish-list-for-woocommerce/assets/icon.svg?rev=3078494',
					'abbr'      => 'WL',
				),
				'free-shipping-bar'            => array(
					'name'      => __( 'Free Shipping Bar', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show shoppers how much more to spend to get free shipping.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'amount-left-free-shipping-woocommerce',
					'free_file' => 'amount-left-free-shipping-woocommerce/left-to-free-shipping-for-woocommerce.php',
					'pro_file'  => 'left-to-free-shipping-for-woocommerce-pro/left-to-free-shipping-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'amount-left-free-shipping-woocommerce/',
					'icon_url'  => $psw . 'amount-left-free-shipping-woocommerce/assets/icon.svg?rev=2971497',
					'abbr'      => 'FS',
				),
				'popups'                       => array(
					'name'      => __( 'Popups for Notices', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Turn cart, add to cart and checkout notices into popups shoppers notice.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'popup-notices-for-woocommerce',
					'free_file' => 'popup-notices-for-woocommerce/popup-notices-for-woocommerce.php',
					'pro_file'  => 'popup-notices-for-woocommerce-pro/popup-notices-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'popup-notices-for-woocommerce/',
					'icon_url'  => $psw . 'popup-notices-for-woocommerce/assets/icon.svg?rev=2971612',
					'abbr'      => 'PU',
				),
				'msrp'                         => array(
					'name'      => __( 'MSRP Pricing', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show the MSRP or RRP next to your price so the saving is clear.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'msrp-for-woocommerce',
					'free_file' => 'msrp-for-woocommerce/msrp-for-woocommerce.php',
					'pro_file'  => 'msrp-for-woocommerce-pro/msrp-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'msrp-for-woocommerce/',
					'icon_url'  => $psw . 'msrp-for-woocommerce/assets/icon.svg?rev=2971599',
					'abbr'      => 'MS',
				),
				'coupons-by-url'               => array(
					'name'      => __( 'Coupons and Add to Cart by URL', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Apply a coupon and add products to the cart from one link.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'url-coupons-for-woocommerce-by-algoritmika',
					'free_file' => 'url-coupons-for-woocommerce-by-algoritmika/url-coupons-woocommerce.php',
					'pro_file'  => 'url-coupons-woocommerce-pro/url-coupons-woocommerce-pro.php',
					'pro_url'   => $wpf . 'url-coupons-woocommerce/',
					'icon_url'  => $psw . 'url-coupons-for-woocommerce-by-algoritmika/assets/icon.svg?rev=2970932',
					'abbr'      => 'CU',
				),
				'price-by-quantity'            => array(
					'name'      => __( 'Price by Quantity', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Set tiered bulk prices by quantity, per product, category or user role.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'wholesale-pricing-woocommerce',
					'free_file' => 'wholesale-pricing-woocommerce/wholesale-pricing-woocommerce.php',
					'pro_file'  => 'wholesale-pricing-woocommerce-pro/wholesale-pricing-woocommerce-pro.php',
					'pro_url'   => $wpf . 'product-price-by-quantity-for-woocommerce/',
					'icon_url'  => $psw . 'wholesale-pricing-woocommerce/assets/icon.svg?rev=2971455',
					'abbr'      => 'PQ',
				),
				'name-your-price'              => array(
					'name'      => __( 'Name Your Price', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Let shoppers make an offer, then accept, reject or counter it.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'price-offerings-for-woocommerce',
					'free_file' => 'price-offerings-for-woocommerce/price-offerings-for-woocommerce.php',
					'pro_file'  => 'price-offerings-for-woocommerce-pro/price-offerings-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'price-offers-for-woocommerce/',
					'icon_url'  => $psw . 'price-offerings-for-woocommerce/assets/icon.svg?rev=2971616',
					'abbr'      => 'NP',
				),
				'sitewide-discount'            => array(
					'name'      => __( 'Sitewide Discount', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Run scheduled store-wide discounts with the old price struck through.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'global-shop-discount-for-woocommerce',
					'free_file' => 'global-shop-discount-for-woocommerce/global-shop-discount-for-woocommerce.php',
					'pro_file'  => 'global-shop-discount-for-woocommerce-pro/global-shop-discount-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'global-shop-discount-for-woocommerce/',
					'icon_url'  => $psw . 'global-shop-discount-for-woocommerce/assets/icon.svg?rev=2971516',
					'abbr'      => 'SD',
				),
				'coupon-by-user-role'          => array(
					'name'      => __( 'Coupons by User Role', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Limit coupons to certain user roles, or give each role its own discount.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'coupon-by-user-role-for-woocommerce',
					'free_file' => 'coupon-by-user-role-for-woocommerce/coupon-by-user-role-for-woocommerce.php',
					'pro_file'  => 'coupon-by-user-role-for-woocommerce-pro/coupon-by-user-role-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'coupon-by-user-role-for-woocommerce/',
					'icon_url'  => $psw . 'coupon-by-user-role-for-woocommerce/assets/icon.svg?rev=2970923',
					'abbr'      => 'CR',
				),
				'sales-bump'                   => array(
					'name'      => __( 'Sales Bump Popup', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show upsells and cross-sells in a popup on product, cart and checkout pages.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'pro_file'  => 'upsell-cross-sell-related-products-popup-for-woocommerce-pro/webd-woocommerce-crosell-popup-pro.php',
					'pro_url'   => $wpf . 'woocommerce-upsell-cross-sell-related-products-popup/',
					'icon_file' => 'sales-bump.png',
					'abbr'      => 'SB',
				),
				'variation-swatches'           => array(
					'name'      => __( 'Variation Swatches', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Replace variation dropdowns with color and image swatches.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'wporg'     => 'color-or-image-variation-swatches-for-woocommerce',
					'free_file' => 'color-or-image-variation-swatches-for-woocommerce/color-or-image-variation-swatches-for-woocommerce.php',
					'pro_file'  => 'color-or-image-variation-swatches-for-woocommerce-pro/color-or-image-variation-swatches-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'color-or-image-variation-swatches-for-woocommerce/',
					'icon_url'  => $psw . 'color-or-image-variation-swatches-for-woocommerce/assets/icon.svg?rev=2969964',
					'abbr'      => 'VS',
				),
				'related-categories'           => array(
					'name'      => __( 'Related Categories', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show related categories on product and shop pages.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'wporg'     => 'related-categories-for-woocommerce',
					'free_file' => 'related-categories-for-woocommerce/related-categories-for-woocommerce.php',
					'pro_file'  => 'related-categories-for-woocommerce-pro/related-categories-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'related-categories-for-woocommerce/',
					'icon_url'  => $psw . 'related-categories-for-woocommerce/assets/icon.svg?rev=2971949',
					'abbr'      => 'RC',
				),
				'add-to-cart-labels'           => array(
					'name'      => __( 'Add to Cart Button Labels', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Change the Add to Cart text per product, user role or language.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'wporg'     => 'add-to-cart-button-labels-for-woocommerce',
					'free_file' => 'add-to-cart-button-labels-for-woocommerce/add-to-cart-button-labels-for-woocommerce.php',
					'pro_file'  => 'add-to-cart-button-labels-for-woocommerce-pro/add-to-cart-button-labels-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'add-to-cart-button-labels-woocommerce/',
					'icon_url'  => $psw . 'add-to-cart-button-labels-for-woocommerce/assets/icon.svg?rev=2969902',
					'abbr'      => 'AC',
				),
				'product-tabs'                 => array(
					'name'      => __( 'Custom Product Tabs', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add custom tabs to product pages, for all products or just one.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'wporg'     => 'product-tabs-for-woocommerce',
					'free_file' => 'product-tabs-for-woocommerce/product-tabs-for-woocommerce.php',
					'pro_file'  => 'product-tabs-for-woocommerce-pro/product-tabs-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'product-tabs-for-woocommerce/',
					'icon_url'  => $psw . 'product-tabs-for-woocommerce/assets/icon.svg?rev=2971447',
					'abbr'      => 'PT',
				),
				'products-per-page'            => array(
					'name'      => __( 'Products per Page', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Let shoppers choose how many products show on each page.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'wporg'     => 'products-per-page-for-woocommerce',
					'free_file' => 'products-per-page-for-woocommerce/products-per-page-for-woocommerce.php',
					'pro_file'  => 'products-per-page-for-woocommerce-pro/products-per-page-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'products-per-page-woocommerce/',
					'icon_url'  => $psw . 'products-per-page-for-woocommerce/assets/icon.svg?rev=2971631',
					'abbr'      => 'PP',
				),
				'external-products'            => array(
					'name'      => __( 'External Products URLs and Currencies', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add several buy links and a custom currency to external and affiliate products.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'external-products-currency-for-woocommerce',
					'free_file' => 'external-products-currency-for-woocommerce/external-products-currency-for-woocommerce.php',
					'pro_file'  => 'external-products-currency-for-woocommerce-pro/external-products-currency-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'advanced-external-products-for-woocommerce/',
					'icon_url'  => $psw . 'external-products-currency-for-woocommerce/assets/icon.svg?rev=2970734',
					'abbr'      => 'EP',
				),
				// WBW Pro-only (free versions are closed on wp.org; free_file is there to detect old installs).
				'currency-switcher'            => array(
					'name'      => __( 'Currency Switcher by WBW', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Let shoppers switch product prices to their own currency.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'vendor'    => 'wbw',
					'free_file' => 'woo-currency/wcu.php',
					'pro_file'  => 'woocurrency-by-woobewoo-pro/woocurrency-by-woobewoo-pro.php',
					'pro_url'   => 'https://woobewoo.com/woocommerce-currency-switcher/',
					'abbr'      => 'CW',
				),
				'product-table'                => array(
					'name'      => __( 'Product Table by WBW', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show products in a searchable, sortable table or order form.', 'wpfactory-cross-selling' ),
					'job'       => 'display',
					'vendor'    => 'wbw',
					'free_file' => 'woo-product-tables/woo-producttables.php',
					'pro_file'  => 'woo-producttables-pro/woo-producttables-pro.php',
					'pro_url'   => 'https://woobewoo.com/product-table-for-woocommerce/',
					'abbr'      => 'TB',
				),
				'instant-checkout-chatgpt'     => array(
					'name'      => __( 'Instant Checkout for ChatGPT', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Check if your store is ready for ChatGPT shopping and build its product feed.', 'wpfactory-cross-selling' ),
					'job'       => 'sell',
					'wporg'     => 'instant-checkout-for-chatgpt-openai-readiness-for-woocommerce',
					'free_file' => 'instant-checkout-for-chatgpt-openai-readiness-for-woocommerce/instant-checkout-for-chatgpt-openai-readiness-for-woocommerce.php',
					'icon_url'  => $psw . 'instant-checkout-for-chatgpt-openai-readiness-for-woocommerce/assets/icon.svg?rev=3478298',
					'abbr'      => 'IC',
				),

				// Set order rules.
				'min-max-quantity'             => array(
					'name'      => __( 'Min Max Step Quantity', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Set minimum, maximum and step quantities, or sell in decimals.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'product-quantity-for-woocommerce',
					'free_file' => 'product-quantity-for-woocommerce/product-quantity-for-woocommerce.php',
					'pro_file'  => 'product-quantity-for-woocommerce-pro/product-quantity-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'product-quantity-for-woocommerce/',
					'icon_url'  => $psw . 'product-quantity-for-woocommerce/assets/icon.svg?rev=2971558',
					'abbr'      => 'MM',
				),
				'max-products-per-user'        => array(
					'name'      => __( 'Maximum Products per User', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Limit how much of a product each customer can buy over time.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'maximum-products-per-user-for-woocommerce',
					'free_file' => 'maximum-products-per-user-for-woocommerce/maximum-products-per-user-for-woocommerce.php',
					'pro_file'  => 'maximum-products-per-user-for-woocommerce-pro/maximum-products-per-user-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'maximum-products-per-user-for-woocommerce/',
					'icon_url'  => $psw . 'maximum-products-per-user-for-woocommerce/assets/icon.svg?rev=2969370',
					'abbr'      => 'MU',
				),
				'order-min-max-amount'         => array(
					'name'      => __( 'Order Min/Max Amount', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Set minimum and maximum order limits by total, quantity or weight.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'order-minimum-amount-for-woocommerce',
					'free_file' => 'order-minimum-amount-for-woocommerce/order-minimum-amount-for-woocommerce.php',
					'pro_file'  => 'order-minimum-amount-for-woocommerce-pro/order-minimum-amount-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'order-minimum-maximum-amount-for-woocommerce/',
					'icon_url'  => $psw . 'order-minimum-amount-for-woocommerce/assets/icon.svg?rev=2971602',
					'abbr'      => 'OA',
				),
				'payment-methods'              => array(
					'name'      => __( 'Payment Methods by Product and Country', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show or hide payment methods by product, category or country.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'payment-gateways-per-product-categories-for-woocommerce',
					'free_file' => 'payment-gateways-per-product-categories-for-woocommerce/payment-gateways-per-product-for-woocommerce.php',
					'pro_file'  => 'payment-gateways-per-product-for-woocommerce-pro/payment-gateways-per-product-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'payment-gateways-per-product-for-woocommerce/',
					'icon_url'  => $psw . 'payment-gateways-per-product-categories-for-woocommerce/assets/icon.svg?rev=2969910',
					'abbr'      => 'PM',
				),
				'conditional-payment-gateways' => array(
					'name'      => __( 'Conditional Payment Gateways', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show payment methods by cart amount, time of day or customer IP.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'conditional-payment-gateways-for-woocommerce',
					'free_file' => 'conditional-payment-gateways-for-woocommerce/conditional-payment-gateways-for-woocommerce.php',
					'pro_file'  => 'conditional-payment-gateways-for-woocommerce-pro/conditional-payment-gateways-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'conditional-payment-gateways-for-woocommerce/',
					'icon_url'  => $psw . 'conditional-payment-gateways-for-woocommerce/assets/icon.svg?rev=2970912',
					'abbr'      => 'CP',
				),
				'payment-gateways-location'    => array(
					'name'      => __( 'Payment Gateways by Location', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show payment methods by country, state or postal code.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'payment-gateways-by-customer-location-for-woocommerce',
					'free_file' => 'payment-gateways-by-customer-location-for-woocommerce/payment-gateways-by-customer-location-for-woocommerce.php',
					'pro_file'  => 'payment-gateways-by-customer-location-for-woocommerce-pro/payment-gateways-by-customer-location-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'payment-gateways-by-customer-location-for-woocommerce/',
					'icon_url'  => $psw . 'payment-gateways-by-customer-location-for-woocommerce/assets/icon.svg?rev=2971607',
					'abbr'      => 'GL',
				),
				'payment-gateways-shipping'    => array(
					'name'      => __( 'Payment Gateways by Shipping', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Match payment methods to the shipping method the customer picks.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'payment-gateways-by-shipping-for-woocommerce',
					'free_file' => 'payment-gateways-by-shipping-for-woocommerce/payment-gateways-by-shipping-for-woocommerce.php',
					'pro_file'  => 'payment-gateways-by-shipping-for-woocommerce-pro/payment-gateways-by-shipping-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'payment-gateways-by-shipping-for-woocommerce/',
					'icon_url'  => $psw . 'payment-gateways-by-shipping-for-woocommerce/assets/icon.svg?rev=2971609',
					'abbr'      => 'GS',
				),
				'payment-gateway-currency'     => array(
					'name'      => __( 'Payment Gateway Currency', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Charge each payment method in its own currency, with live or manual rates.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'payment-gateways-by-currency-for-woocommerce',
					'free_file' => 'payment-gateways-by-currency-for-woocommerce/payment-gateways-by-currency-for-woocommerce.php',
					'pro_file'  => 'payment-gateways-by-currency-for-woocommerce-pro/payment-gateways-by-currency-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'payment-gateway-currency-for-woocommerce/',
					'icon_url'  => $psw . 'payment-gateways-by-currency-for-woocommerce/assets/icon.svg?rev=2971605',
					'abbr'      => 'GC',
				),
				'conditional-shipping'         => array(
					'name'      => __( 'Conditional Shipping', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Show or hide shipping methods by city, product, payment method and more.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'wpfactory-conditional-shipping-for-woocommerce',
					'free_file' => 'wpfactory-conditional-shipping-for-woocommerce/conditional-shipping-for-woocommerce.php',
					'pro_file'  => 'conditional-shipping-for-woocommerce-pro/conditional-shipping-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'conditional-shipping-for-woocommerce/',
					'icon_url'  => $psw . 'wpfactory-conditional-shipping-for-woocommerce/assets/icon.svg?rev=2970917',
					'abbr'      => 'CS',
				),
				'weight-based-shipping'        => array(
					'name'      => __( 'Weight Based Shipping', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Set shipping rates by weight tiers per zone, with handling fees.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'pro_file'  => 'woocommerce-weight-based-shipping/woocommerce-weight-based-shipping.php',
					'pro_url'   => $wpf . 'weight-based-shipping-for-woocommerce/',
					'icon_file' => 'weight-based-shipping.png',
					'abbr'      => 'WS',
				),
				'checkout-fields'              => array(
					'name'      => __( 'Custom Checkout Fields', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add checkout fields that only show when they apply.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'custom-checkout-fields-for-woocommerce',
					'free_file' => 'custom-checkout-fields-for-woocommerce/custom-checkout-fields-for-woocommerce.php',
					'pro_file'  => 'custom-checkout-fields-for-woocommerce-pro/custom-checkout-fields-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'custom-checkout-fields-for-woocommerce/',
					'icon_url'  => $psw . 'custom-checkout-fields-for-woocommerce/assets/icon.svg?rev=2970966',
					'abbr'      => 'CF',
				),
				'order-status-rules'           => array(
					'name'      => __( 'Order Status Rules', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Change order statuses automatically on a schedule you set.', 'wpfactory-cross-selling' ),
					'job'       => 'rules',
					'wporg'     => 'order-status-rules-for-woocommerce',
					'free_file' => 'order-status-rules-for-woocommerce/order-status-rules-for-woocommerce.php',
					'pro_file'  => 'order-status-rules-for-woocommerce-pro/order-status-rules-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'order-status-rules-for-woocommerce/',
					'icon_url'  => $psw . 'order-status-rules-for-woocommerce/assets/icon.svg?rev=2970743',
					'abbr'      => 'SR',
				),

				// Know your numbers.
				'cost-of-goods'                => array(
					'name'      => __( 'Cost of Goods', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Track product costs and see real profit per product and order.', 'wpfactory-cross-selling' ),
					'job'       => 'numbers',
					'wporg'     => 'cost-of-goods-for-woocommerce',
					'free_file' => 'cost-of-goods-for-woocommerce/cost-of-goods-for-woocommerce.php',
					'pro_file'  => 'cost-of-goods-for-woocommerce-pro/cost-of-goods-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'cost-of-goods-for-woocommerce/',
					'icon_url'  => $psw . 'cost-of-goods-for-woocommerce/assets/icon.svg?rev=2970921',
					'abbr'      => 'CG',
				),
				'ean'                          => array(
					'name'      => __( 'EAN Barcode Generator', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add EAN, UPC, ISBN and GTIN codes and print barcodes or QR codes.', 'wpfactory-cross-selling' ),
					'job'       => 'numbers',
					'wporg'     => 'ean-for-woocommerce',
					'free_file' => 'ean-for-woocommerce/ean-for-woocommerce.php',
					'pro_file'  => 'ean-for-woocommerce-pro/ean-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'ean-barcodes-woocommerce/',
					'icon_url'  => $psw . 'ean-for-woocommerce/assets/icon.svg?rev=2971466',
					'abbr'      => 'EB',
				),
				'eu-vat'                       => array(
					'name'      => __( 'EU VAT Manager', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Validate EU VAT numbers at checkout and exempt valid B2B customers.', 'wpfactory-cross-selling' ),
					'job'       => 'numbers',
					'wporg'     => 'eu-vat-for-woocommerce',
					'free_file' => 'eu-vat-for-woocommerce/eu-vat-for-woocommerce.php',
					'pro_file'  => 'eu-vat-for-woocommerce-pro/eu-vat-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'eu-vat-for-woocommerce/',
					'icon_url'  => $psw . 'eu-vat-for-woocommerce/assets/icon.svg?rev=2969433',
					'abbr'      => 'VA',
				),
				'xml-feeds'                    => array(
					'name'      => __( 'Product XML Feeds', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Create auto-updating XML product feeds for Google Shopping, Amazon and more.', 'wpfactory-cross-selling' ),
					'job'       => 'numbers',
					'wporg'     => 'product-xml-feeds-for-woocommerce',
					'free_file' => 'product-xml-feeds-for-woocommerce/product-xml-feeds-for-woocommerce.php',
					'pro_file'  => 'product-xml-feeds-for-woocommerce-pro/product-xml-feeds-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'product-xml-feeds-woocommerce/',
					'icon_url'  => $psw . 'product-xml-feeds-for-woocommerce/assets/icon.svg?rev=2971628',
					'abbr'      => 'XF',
				),
				'advanced-reporting'           => array(
					'name'      => __( 'Advanced Reporting and Statistics', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Sales reports and forecasts by product, country or date, with Excel export.', 'wpfactory-cross-selling' ),
					'job'       => 'numbers',
					'wporg'     => 'webd-woocommerce-advanced-reporting-statistics',
					'free_file' => 'webd-woocommerce-advanced-reporting-statistics/webd-woocommerce-reporting-statistics.php',
					'pro_file'  => 'advanced-reporting-statistics-for-woocommerce-pro/woocommerce-reporting-statistics-pro.php',
					'pro_url'   => $wpf . 'woocommerce-advanced-reporting-and-statistics/',
					'icon_url'  => $psw . 'webd-woocommerce-advanced-reporting-statistics/assets/icon.svg?rev=3309463',
					'abbr'      => 'AR',
				),
				'stock-history'                => array(
					'name'      => __( 'Stock History and Reports', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Keep daily stock snapshots and see how stock changed over time.', 'wpfactory-cross-selling' ),
					'job'       => 'numbers',
					'wporg'     => 'stock-snapshot-for-woocommerce',
					'free_file' => 'stock-snapshot-for-woocommerce/stock-snapshot-for-woocommerce.php',
					'pro_file'  => 'stock-snapshot-for-woocommerce-pro/stock-snapshot-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'stock-snapshot-for-woocommerce/',
					'icon_url'  => $psw . 'stock-snapshot-for-woocommerce/assets/icon.svg?rev=2971962',
					'abbr'      => 'SH',
				),

				// Support customers.
				'email-verification'           => array(
					'name'      => __( 'Email Verification', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Make new customers verify their email and keep bot accounts out.', 'wpfactory-cross-selling' ),
					'job'       => 'support',
					'wporg'     => 'emails-verification-for-woocommerce',
					'free_file' => 'emails-verification-for-woocommerce/email-verification-for-woocommerce.php',
					'pro_file'  => 'email-verification-for-woocommerce-pro/email-verification-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'email-verification-for-woocommerce/',
					'icon_url'  => $psw . 'emails-verification-for-woocommerce/assets/icon.svg?rev=2971475',
					'abbr'      => 'EV',
				),
				'custom-emails'                => array(
					'name'      => __( 'Custom Emails and Recipients', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Send extra emails on order, user or product events, to anyone you choose.', 'wpfactory-cross-selling' ),
					'job'       => 'support',
					'wporg'     => 'custom-emails-for-woocommerce',
					'free_file' => 'custom-emails-for-woocommerce/custom-emails-for-woocommerce.php',
					'pro_file'  => 'custom-emails-for-woocommerce-pro/custom-emails-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'custom-emails-for-woocommerce/',
					'icon_url'  => $psw . 'custom-emails-for-woocommerce/assets/icon.svg?rev=2970983',
					'abbr'      => 'CE',
				),
				'review-replies'               => array(
					'name'      => __( 'Replies to Reviews', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Reply to product reviews right on the product page.', 'wpfactory-cross-selling' ),
					'job'       => 'support',
					'pro_file'  => 'frontend-replies-in-woocommerce-reviews-pro/frontend-replies-in-woocommerce-reviews-pro.php',
					'pro_url'   => $wpf . 'frontend-replies-in-woocommerce-reviews/',
					'icon_file' => 'review-replies.png',
					'abbr'      => 'RR',
				),

				// Manage your store.
				'catalog-health-scanner'       => array(
					'name'      => __( 'Catalog Health Scanner', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Scan every product for issues that stop it from selling.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'free_file' => 'wpfactory-catalog-health-scanner-for-woocommerce/wpfactory-catalog-health-scanner-for-woocommerce.php',
					'pro_file'  => 'wpfactory-catalog-health-scanner-for-woocommerce-pro/wpfactory-catalog-health-scanner-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'catalog-health-scanner-for-woocommerce/',
					'icon_file' => 'catalog-health-scanner.png',
					'abbr'      => 'CH',
				),
				'bulk-price-editor'            => array(
					'name'      => __( 'Bulk Price Editor', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Update prices in bulk, with a preview before anything changes.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'wporg'     => 'bulk-price-converter-for-woocommerce',
					'free_file' => 'bulk-price-converter-for-woocommerce/bulk-price-converter-for-woocommerce.php',
					'pro_file'  => 'bulk-price-converter-for-woocommerce-pro/bulk-price-converter-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'bulk-price-editor-for-woocommerce/',
					'icon_url'  => $psw . 'bulk-price-converter-for-woocommerce/assets/icon.svg?rev=2970903',
					'abbr'      => 'BP',
				),
				'stock-triggers'               => array(
					'name'      => __( 'Stock Update Triggers', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Choose which order statuses reduce or restore stock.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'wporg'     => 'stock-triggers-for-woocommerce',
					'free_file' => 'stock-triggers-for-woocommerce/stock-triggers-for-woocommerce.php',
					'pro_file'  => 'stock-triggers-for-woocommerce-pro/stock-triggers-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'stock-triggers-for-woocommerce/',
					'icon_url'  => $psw . 'stock-triggers-for-woocommerce/assets/icon.svg?rev=2971968',
					'abbr'      => 'ST',
				),
				'export'                       => array(
					'name'      => __( 'Export Products, Orders and Customers', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Export products, orders and customers to CSV or XML, with the columns you pick.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'wporg'     => 'export-woocommerce',
					'free_file' => 'export-woocommerce/export-woocommerce.php',
					'pro_file'  => 'export-woocommerce-pro/export-woocommerce-pro.php',
					'pro_url'   => $wpf . 'export-woocommerce/',
					'icon_url'  => $psw . 'export-woocommerce/assets/icon.svg?rev=2969923',
					'abbr'      => 'EX',
				),
				'pdf-invoices'                 => array(
					'name'      => __( 'PDF Invoices and Packing Slips', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Create PDF invoices, packing slips and credit notes automatically.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'wporg'     => 'pdf-invoicing-for-woocommerce',
					'free_file' => 'pdf-invoicing-for-woocommerce/pdf-invoicing-for-woocommerce.php',
					'pro_file'  => 'pdf-invoicing-for-woocommerce-pro/pdf-invoicing-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'pdf-invoicing-for-woocommerce/',
					'icon_url'  => $psw . 'pdf-invoicing-for-woocommerce/assets/icon.svg?rev=2971610',
					'abbr'      => 'PI',
				),
				'order-status'                 => array(
					'name'      => __( 'Custom Order Status', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add your own order statuses with colors, icons and emails.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'wporg'     => 'order-status-for-woocommerce',
					'free_file' => 'order-status-for-woocommerce/order-status-for-woocommerce.php',
					'pro_file'  => 'order-status-for-woocommerce-pro/order-status-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'order-status-for-woocommerce/',
					'icon_url'  => $psw . 'order-status-for-woocommerce/assets/icon.svg?rev=2971603',
					'abbr'      => 'OS',
				),
				'product-notes'                => array(
					'name'      => __( 'Product Notes', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add private admin notes or public note tabs to products.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'wporg'     => 'product-notes-for-woocommerce',
					'free_file' => 'product-notes-for-woocommerce/internal-product-notes-for-woocommerce.php',
					'pro_file'  => 'internal-product-notes-for-woocommerce-pro/internal-product-notes-for-woocommerce-pro.php',
					'pro_url'   => $wpf . 'product-notes-for-woocommerce/',
					'icon_url'  => $psw . 'product-notes-for-woocommerce/assets/icon.svg?rev=2971624',
					'abbr'      => 'PN',
				),
				'products-excel'               => array(
					'name'      => __( 'Products Import and Export with Excel', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Import and export products straight from Excel files, no CSV step.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'pro_file'  => 'product-excel-import-export-for-woocommerce-pro/wpeiePro.php',
					'pro_url'   => $wpf . 'woocommerce-products-import-export-with-excel/',
					'icon_file' => 'products-excel.png',
					'abbr'      => 'PE',
				),
				'store-migration-excel'        => array(
					'name'      => __( 'Store Migration with Excel', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Move products, orders, customers, coupons and reviews with Excel files.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'pro_file'  => 'migrate-import-export-excel-for-woocommerce-pro/store-migration-excel-pro.php',
					'pro_url'   => $wpf . 'all-in-one-woocommerce-migration-import-export-to-excel/',
					'icon_file' => 'store-migration-excel.png',
					'abbr'      => 'MG',
				),
				'stock-manager-excel'          => array(
					'name'      => __( 'Stock Manager with Excel', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Update stock and prices for many products at once from Excel.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'pro_file'  => 'products-stock-manager-excel-for-woocommerce-pro/stock-manager-woocommerce-pro.php',
					'pro_url'   => $wpf . 'products-stock-manager-with-excel-for-woocommerce/',
					'icon_file' => 'stock-manager-excel.png',
					'abbr'      => 'SE',
				),
				'content-importer-excel'       => array(
					'name'      => __( 'Content Importer with Excel', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Import posts, pages and products from Excel, including SEO fields.', 'wpfactory-cross-selling' ),
					'job'       => 'manage',
					'store'     => false,
					'pro_file'  => 'content-importer-excel-for-wordpress-woocommerce-pro/content-excel-importer-pro.php',
					'pro_url'   => $wpf . 'content-importer-for-wordpress-woocommerce-with-excel/',
					'icon_file' => 'content-importer-excel.png',
					'abbr'      => 'CI',
				),

				// Tidy up WordPress.
				'file-renaming'                => array(
					'name'      => __( 'File Renaming on Upload', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Rename media files automatically on upload for clean, SEO friendly names.', 'wpfactory-cross-selling' ),
					'job'       => 'tidy',
					'store'     => false,
					'wporg'     => 'file-renaming-on-upload',
					'free_file' => 'file-renaming-on-upload/file-renaming-on-upload.php',
					'pro_file'  => 'file-renaming-on-upload-pro/file-renaming-on-upload-pro.php',
					'pro_url'   => $wpf . 'file-renaming-on-upload-wordpress-plugin/',
					'icon_url'  => $psw . 'file-renaming-on-upload/assets/icon.svg?rev=2971495',
					'abbr'      => 'FR',
				),
				'download-plugins'             => array(
					'name'      => __( 'Download Plugins and Themes', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Download any plugin or theme as a ZIP from the dashboard.', 'wpfactory-cross-selling' ),
					'job'       => 'tidy',
					'store'     => false,
					'wporg'     => 'download-plugins-dashboard',
					'free_file' => 'download-plugins-dashboard/download-plugins-from-dashboard.php',
					'pro_file'  => 'download-plugins-from-dashboard-pro/download-plugins-from-dashboard-pro.php',
					'pro_url'   => $wpf . 'download-plugins-and-themes-from-dashboard-wordpress-plugin/',
					'icon_url'  => $psw . 'download-plugins-dashboard/assets/icon.svg?rev=2971450',
					'abbr'      => 'DP',
				),
				'back-button'                  => array(
					'name'      => __( 'Back Button Widget', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Add a Back button anywhere on your site.', 'wpfactory-cross-selling' ),
					'job'       => 'tidy',
					'store'     => false,
					'wporg'     => 'back-button-widget',
					'free_file' => 'back-button-widget/back-button-widget.php',
					'pro_file'  => 'back-button-widget-pro/back-button-widget-pro.php',
					'pro_url'   => $wpf . 'back-button-widget-wordpress-plugin/',
					'icon_url'  => $psw . 'back-button-widget/assets/icon.svg?rev=2970754',
					'abbr'      => 'BB',
				),
				'slugs-manager'                => array(
					'name'      => __( 'Slugs Manager', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Find and delete old permalinks WordPress keeps in the database.', 'wpfactory-cross-selling' ),
					'job'       => 'tidy',
					'store'     => false,
					'wporg'     => 'remove-old-slugspermalinks',
					'free_file' => 'remove-old-slugspermalinks/remove-old-slugs.php',
					'pro_file'  => 'remove-old-slugs-pro/remove-old-slugs-pro.php',
					'pro_url'   => $wpf . 'slugs-manager-wordpress-plugin/',
					'icon_url'  => $psw . 'remove-old-slugspermalinks/assets/icon.svg?rev=2971959',
					'abbr'      => 'SM',
				),
				'remove-special-characters'    => array(
					'name'      => __( 'Remove Special Characters', 'wpfactory-cross-selling' ),
					'desc'      => __( 'Strip special characters from permalinks and redirect the old URLs.', 'wpfactory-cross-selling' ),
					'job'       => 'tidy',
					'store'     => false,
					'wporg'     => 'remove-special-characters-from-permalinks',
					'free_file' => 'remove-special-characters-from-permalinks/remove-special-characters-from-permalinks.php',
					'pro_file'  => 'remove-special-characters-from-permalinks-pro/remove-special-characters-from-permalinks-pro.php',
					'pro_url'   => $wpf . 'remove-special-characters-from-permalinks-wordpress-plugin/',
					'icon_url'  => $psw . 'remove-special-characters-from-permalinks/assets/icon.svg?rev=2971953',
					'abbr'      => 'RS',
				),

			);
		}

	}

}
