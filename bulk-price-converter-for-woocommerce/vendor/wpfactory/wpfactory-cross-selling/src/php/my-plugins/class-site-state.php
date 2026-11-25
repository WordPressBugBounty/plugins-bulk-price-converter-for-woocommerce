<?php
/**
 * WPFactory Cross-Selling - My Plugins - Site State.
 *
 * Which catalog plugins (and which other plugins) are installed and active on this site.
 * Built from get_plugins() and the active plugin lists, or from arrays in tests.
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Site_State', false ) ) {

	/**
	 * Site_State.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Site_State {

		/**
		 * Installed plugin files ("folder/file.php"), keyed by file.
		 *
		 * @since 1.2.0
		 *
		 * @var array
		 */
		protected $installed = array();

		/**
		 * Active plugin files (site and network), keyed by file.
		 *
		 * @since 1.2.0
		 *
		 * @var array
		 */
		protected $active = array();

		/**
		 * Installed files by lowercase basename.
		 *
		 * @since 1.2.0
		 *
		 * @var array
		 */
		protected $by_basename = array();

		/**
		 * Installed files by lowercase folder.
		 *
		 * @since 1.2.0
		 *
		 * @var array
		 */
		protected $by_folder = array();

		/**
		 * Lookup results per catalog id.
		 *
		 * @since 1.2.0
		 *
		 * @var array
		 */
		protected $cache = array();

		/**
		 * Constructor.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string[] $installed_files Installed plugin files.
		 * @param string[] $active_files    Active plugin files (site and network).
		 */
		function __construct( array $installed_files, array $active_files ) {
			foreach ( $installed_files as $file ) {
				$file                     = wp_normalize_path( $file );
				$this->installed[ $file ] = true;
				$this->by_basename[ strtolower( basename( $file ) ) ][] = $file;
				$folder = dirname( $file );
				if ( '.' !== $folder ) {
					$this->by_folder[ strtolower( $folder ) ][] = $file;
				}
			}
			foreach ( $active_files as $file ) {
				$this->active[ wp_normalize_path( $file ) ] = true;
			}
		}

		/**
		 * from_wp.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return Site_State
		 */
		static function from_wp() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$active = (array) get_option( 'active_plugins', array() );
			if ( is_multisite() ) {
				$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			}
			return new self( array_keys( get_plugins() ), $active );
		}

		/**
		 * find.
		 *
		 * Where a catalog plugin is installed. A copy counts when its file matches exactly,
		 * or when the same main file sits in another folder (GitHub zips, renamed folders),
		 * or (free only) when the wp.org folder holds a plugin with another main file.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 *
		 * @return array { free: string, pro: string, free_active: bool, pro_active: bool }
		 */
		function find( $item ) {
			if ( isset( $this->cache[ $item['id'] ] ) ) {
				return $this->cache[ $item['id'] ];
			}
			$pro  = $this->match( $item['pro_file'], '' );
			$free = $this->match( $item['free_file'], $item['wporg'], $pro );

			return ( $this->cache[ $item['id'] ] = array(
				'free'        => $free,
				'pro'         => $pro,
				'free_active' => ( '' !== $free && isset( $this->active[ $free ] ) ),
				'pro_active'  => ( '' !== $pro && isset( $this->active[ $pro ] ) ),
			) );
		}

		/**
		 * is_installed.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 *
		 * @return bool
		 */
		function is_installed( $item ) {
			$found = $this->find( $item );
			return ( '' !== $found['free'] || '' !== $found['pro'] );
		}

		/**
		 * is_active.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 *
		 * @return bool
		 */
		function is_active( $item ) {
			$found = $this->find( $item );
			return ( $found['free_active'] || $found['pro_active'] );
		}

		/**
		 * any_folder_active.
		 *
		 * For plugins outside the catalog (competitors), matched by folder name.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string[] $folders Plugin folder names.
		 *
		 * @return bool
		 */
		function any_folder_active( array $folders ) {
			foreach ( $folders as $folder ) {
				$folder = strtolower( $folder );
				if ( empty( $this->by_folder[ $folder ] ) ) {
					continue;
				}
				foreach ( $this->by_folder[ $folder ] as $file ) {
					if ( isset( $this->active[ $file ] ) ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * match.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $file    Expected "folder/file.php", or ''.
		 * @param string $folder  wp.org folder to accept with any main file, or ''.
		 * @param string $exclude A file already matched as Pro, never returned here.
		 *
		 * @return string Installed file, or ''.
		 */
		protected function match( $file, $folder, $exclude = '' ) {
			$candidates = array();
			if ( '' !== $file ) {
				if ( isset( $this->installed[ $file ] ) ) {
					return $file;
				}
				$base = strtolower( basename( $file ) );
				if ( isset( $this->by_basename[ $base ] ) ) {
					$candidates = array_merge( $candidates, $this->by_basename[ $base ] );
				}
			}
			if ( '' !== $folder && isset( $this->by_folder[ strtolower( $folder ) ] ) ) {
				$candidates = array_merge( $candidates, $this->by_folder[ strtolower( $folder ) ] );
			}
			foreach ( $candidates as $candidate ) {
				if ( $candidate !== $exclude ) {
					return $candidate;
				}
			}
			return '';
		}

	}

}
