<?php
/**
 * WPFactory Cross-Selling - My Plugins - Plugin.
 *
 * Boots the My Plugins page from the copy the loader picked.
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Plugin', false ) ) {

	/**
	 * Plugin.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Plugin {

		/**
		 * Library root directory of the booted copy.
		 *
		 * @since 1.2.0
		 *
		 * @var string
		 */
		protected static $root = '';

		/**
		 * boot.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $root Library root directory.
		 */
		static function boot( $root ) {
			if ( '' !== self::$root ) {
				return;
			}
			self::$root = wp_normalize_path( $root );

			// Cache invalidation must run wherever settings or plugins change (admin, REST, WP-CLI).
			Rules_Engine::register_hooks();

			if ( is_admin() ) {
				add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
				Page::init();
				Installer::init();
			}
		}

		/**
		 * load_textdomain.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function load_textdomain() {
			$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
			$mofile = self::path( 'langs/wpfactory-cross-selling-' . $locale . '.mo' );
			if ( is_readable( $mofile ) ) {
				load_textdomain( 'wpfactory-cross-selling', $mofile );
			}
		}

		/**
		 * path.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $relative Path inside the library.
		 *
		 * @return string
		 */
		static function path( $relative ) {
			return self::$root . '/' . ltrim( $relative, '/' );
		}

		/**
		 * url.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $relative Path inside the library.
		 *
		 * @return string
		 */
		static function url( $relative ) {
			// Any file name inside the library root works: plugins_url() only uses its folder.
			return plugins_url( ltrim( $relative, '/' ), self::$root . '/wpfactory-cross-selling.php' );
		}

		/**
		 * version.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return string
		 */
		static function version() {
			return ( defined( 'WPFACTORY_MY_PLUGINS_VERSION' ) ? WPFACTORY_MY_PLUGINS_VERSION : '1.2.0' );
		}

		/**
		 * capability.
		 *
		 * Same as the old Recommendations page.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return string
		 */
		static function capability() {
			return ( class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' );
		}

	}

}
