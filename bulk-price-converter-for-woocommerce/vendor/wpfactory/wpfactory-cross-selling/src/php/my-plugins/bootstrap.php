<?php
/**
 * WPFactory Cross-Selling - My Plugins - Bootstrap.
 *
 * Loaded by loader.php from the newest copy only. The classes are required here,
 * before anything can autoload an older copy of them (their guards skip autoload).
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'class-catalog.php', 'class-site-state.php', 'class-signals.php', 'class-rules-engine.php', 'class-installer.php', 'class-page.php', 'class-plugin.php' ) as $wpfactory_my_plugins_file ) {
	require_once __DIR__ . '/' . $wpfactory_my_plugins_file;
}
unset( $wpfactory_my_plugins_file );

// The loader's path, not __DIR__: PHP resolves symlinks and junctions in __DIR__, which would
// point outside the plugins folder and break plugins_url().
\WPFactory\WPFactory_Cross_Selling\My_Plugins\Plugin::boot( dirname( defined( 'WPFACTORY_MY_PLUGINS_DIR' ) ? WPFACTORY_MY_PLUGINS_DIR : __DIR__, 3 ) );
