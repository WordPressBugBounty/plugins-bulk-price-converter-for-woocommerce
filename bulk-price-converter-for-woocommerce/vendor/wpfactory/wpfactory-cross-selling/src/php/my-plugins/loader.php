<?php
/**
 * WPFactory Cross-Selling - My Plugins - Loader.
 *
 * Registered as a Composer "files" entry. Composer runs it once per request for this
 * package, however many plugins ship a copy (it dedupes by package name and path),
 * so this file finds every copy itself and loads the newest one on plugins_loaded.
 *
 * Keep this file small and stable: an older copy's loader may be the one that runs,
 * and it must still pick a newer copy. The contract with newer copies is only
 * "version.php returns a version string" and "bootstrap.php loads the page".
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'wpfactory_my_plugins_boot' ) ) {

	/**
	 * wpfactory_my_plugins_register_copy.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 *
	 * @param string $dir Directory of a copy's src/php/my-plugins.
	 */
	function wpfactory_my_plugins_register_copy( $dir ) {
		$GLOBALS['wpfactory_my_plugins_copies'][ str_replace( '\\', '/', $dir ) ] = true;
	}

	/**
	 * wpfactory_my_plugins_find_copies.
	 *
	 * Registered copies plus the copy in each active plugin's vendor folder.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 *
	 * @return array Version by directory.
	 */
	function wpfactory_my_plugins_find_copies() {
		$dirs   = isset( $GLOBALS['wpfactory_my_plugins_copies'] ) ? array_keys( $GLOBALS['wpfactory_my_plugins_copies'] ) : array();
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active as $plugin_file ) {
			$folder = dirname( (string) $plugin_file );
			if ( '.' !== $folder && '' !== $folder ) {
				$dirs[] = str_replace( '\\', '/', WP_PLUGIN_DIR ) . '/' . $folder . '/vendor/wpfactory/wpfactory-cross-selling/src/php/my-plugins';
			}
		}
		// The same copy can be reached by two paths (symlinks, junctions). Keep one, preferring
		// the path inside the plugins folder, so plugins_url() can build its asset URLs.
		$plugins = str_replace( '\\', '/', WP_PLUGIN_DIR ) . '/';
		$unique  = array();
		foreach ( $dirs as $dir ) {
			if ( ! is_file( $dir . '/version.php' ) || ! is_file( $dir . '/bootstrap.php' ) ) {
				continue;
			}
			$real = realpath( $dir );
			$key  = str_replace( '\\', '/', ( false !== $real ? $real : $dir ) );
			if ( ! isset( $unique[ $key ] ) || ( 0 !== strpos( $unique[ $key ], $plugins ) && 0 === strpos( $dir, $plugins ) ) ) {
				$unique[ $key ] = $dir;
			}
		}
		$copies = array();
		foreach ( $unique as $dir ) {
			$version = include $dir . '/version.php';
			if ( is_string( $version ) && '' !== $version ) {
				$copies[ $dir ] = $version;
			}
		}
		return $copies;
	}

	/**
	 * wpfactory_my_plugins_boot.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function wpfactory_my_plugins_boot() {
		if ( defined( 'WPFACTORY_MY_PLUGINS_DIR' ) ) {
			return;
		}
		$best_dir     = '';
		$best_version = '';
		foreach ( wpfactory_my_plugins_find_copies() as $dir => $version ) {
			if ( '' === $best_dir || version_compare( $version, $best_version, '>' ) ) {
				$best_dir     = $dir;
				$best_version = $version;
			}
		}
		if ( '' === $best_dir ) {
			return;
		}
		define( 'WPFACTORY_MY_PLUGINS_DIR', $best_dir );
		define( 'WPFACTORY_MY_PLUGINS_VERSION', $best_version );
		require $best_dir . '/bootstrap.php';
	}

	// Plugins that load their vendor/autoload.php late (during plugins_loaded or after) boot on init instead.
	if ( did_action( 'plugins_loaded' ) ) {
		add_action( 'init', 'wpfactory_my_plugins_boot', 0 );
	} else {
		add_action( 'plugins_loaded', 'wpfactory_my_plugins_boot', 5 );
	}
}

wpfactory_my_plugins_register_copy( __DIR__ );
