<?php
/**
 * WPFactory Cross-Selling - My Plugins - Installer.
 *
 * AJAX install (free versions from wp.org, catalog plugins only) and activation.
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Installer', false ) ) {

	/**
	 * Installer.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Installer {

		/**
		 * Nonce action.
		 *
		 * @since 1.2.0
		 */
		const NONCE = 'wpf_myplugins';

		/**
		 * init.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function init() {
			add_action( 'wp_ajax_wpf_myplugins_install', array( __CLASS__, 'ajax_install' ) );
			add_action( 'wp_ajax_wpf_myplugins_activate', array( __CLASS__, 'ajax_activate' ) );
		}

		/**
		 * can_install.
		 *
		 * False with DISALLOW_FILE_MODS, on multisite (installs belong to the network admin),
		 * or without the install_plugins capability. The page then links to wp.org instead.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return bool
		 */
		static function can_install() {
			if ( is_multisite() || ! current_user_can( 'install_plugins' ) ) {
				return false;
			}
			if ( function_exists( 'wp_is_file_mod_allowed' ) ) {
				return wp_is_file_mod_allowed( 'wpf_myplugins_install' );
			}
			return ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
		}

		/**
		 * wporg_url.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 *
		 * @return string
		 */
		static function wporg_url( $item ) {
			return 'https://wordpress.org/plugins/' . $item['wporg'] . '/';
		}

		/**
		 * get_settings_url.
		 *
		 * The "Settings" link the plugin adds to its row on the Plugins screen, if any.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $file Plugin file.
		 *
		 * @return string
		 */
		static function get_settings_url( $file ) {
			if ( '' === $file ) {
				return '';
			}
			$links = apply_filters( 'plugin_action_links_' . $file, array(), $file, array(), 'all' );
			$links = apply_filters( 'plugin_action_links', $links, $file, array(), 'all' );
			$label = __( 'Settings' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress core string, as plugins use it.
			foreach ( (array) $links as $link ) {
				if ( ! is_string( $link ) || ! preg_match( '/<a\s[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $link, $m ) ) {
					continue;
				}
				$text = trim( wp_strip_all_tags( $m[3] ) );
				if ( 0 === strcasecmp( $text, $label ) || 0 === strcasecmp( $text, 'Settings' ) ) {
					return html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
				}
			}
			return '';
		}

		/**
		 * get_request_item.
		 *
		 * Checks the nonce and returns the catalog item from the request, or sends an error.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return array
		 */
		protected static function get_request_item() {
			if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) ) {
				self::error( __( 'Your session expired. Reload the page and try again.', 'wpfactory-cross-selling' ), 403 );
			}
			$id   = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
			$item = Catalog::get_item( $id );
			if ( ! $item ) {
				self::error( __( 'Unknown plugin.', 'wpfactory-cross-selling' ), 400 );
			}
			return $item;
		}

		/**
		 * error.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $message Message.
		 * @param int    $status  HTTP status.
		 * @param array  $extra   Extra data.
		 */
		protected static function error( $message, $status = 200, $extra = array() ) {
			wp_send_json_error( array_merge( array( 'message' => $message ), $extra ), $status );
		}

		/**
		 * ajax_install.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function ajax_install() {
			$item = self::get_request_item();
			if ( '' === $item['wporg'] ) {
				self::error( __( 'This plugin has no free version to install.', 'wpfactory-cross-selling' ), 400 );
			}
			if ( ! self::can_install() ) {
				self::error(
					__( 'Plugins can\'t be installed from here on this site. Install it from WordPress.org.', 'wpfactory-cross-selling' ),
					403,
					array( 'wporg_url' => self::wporg_url( $item ) )
				);
			}
			if ( Site_State::from_wp()->is_installed( $item ) ) {
				wp_send_json_success( array( 'installed' => true ) );
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$api = plugins_api( 'plugin_information', array(
				'slug'   => $item['wporg'],
				'fields' => array( 'sections' => false ),
			) );
			if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
				self::error(
					__( 'WordPress.org could not be reached. Try again in a minute.', 'wpfactory-cross-selling' ),
					200,
					array( 'wporg_url' => self::wporg_url( $item ) )
				);
			}

			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $api->download_link );

			$message = '';
			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
			} elseif ( is_wp_error( $skin->result ) ) {
				$message = $skin->result->get_error_message();
			} elseif ( $skin->get_errors()->has_errors() ) {
				$message = $skin->get_error_messages();
			} elseif ( is_null( $result ) ) {
				$message = __( 'WordPress could not write to the plugins folder. Install it from Plugins, Add New.', 'wpfactory-cross-selling' );
			}
			if ( '' !== $message ) {
				self::error( wp_strip_all_tags( $message ), 200, array( 'wporg_url' => self::wporg_url( $item ) ) );
			}

			Rules_Engine::clear_cache();
			wp_send_json_success( array( 'installed' => true ) );
		}

		/**
		 * ajax_activate.
		 *
		 * Activates the installed Pro version if there is one, else the free version.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function ajax_activate() {
			$item = self::get_request_item();
			if ( ! current_user_can( 'activate_plugins' ) ) {
				self::error( __( 'You are not allowed to activate plugins.', 'wpfactory-cross-selling' ), 403 );
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			wp_clean_plugins_cache( false );

			$found = Site_State::from_wp()->find( $item );
			$file  = ( '' !== $found['pro'] ? $found['pro'] : $found['free'] );
			if ( '' === $file ) {
				self::error( __( 'The plugin is not installed.', 'wpfactory-cross-selling' ), 400 );
			}
			if ( ! is_plugin_active( $file ) ) {
				$result = activate_plugin( $file );
				if ( is_wp_error( $result ) ) {
					self::error( wp_strip_all_tags( $result->get_error_message() ) );
				}
			}

			Rules_Engine::clear_cache();
			$settings = self::get_settings_url( $file );
			wp_send_json_success( array(
				'settings_url' => ( '' !== $settings ? $settings : admin_url( 'plugins.php?plugin_status=active' ) ),
			) );
		}

	}

}
