<?php
/**
 * WPFactory Cross-Selling - My Plugins - Page.
 *
 * WPFactory menu > My Plugins. Replaces the Recommendations page (its URL redirects here).
 *
 * @version 1.2.0
 * @since   1.2.0
 * @author  WPFactory
 */

namespace WPFactory\WPFactory_Cross_Selling\My_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WPFactory\WPFactory_Cross_Selling\My_Plugins\Page', false ) ) {

	/**
	 * Page.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	class Page {

		/**
		 * Page slug.
		 *
		 * @since 1.2.0
		 */
		const SLUG = 'wpfactory-my-plugins';

		/**
		 * Old Recommendations page slug (redirects here).
		 *
		 * @since 1.2.0
		 */
		const OLD_SLUG = 'wpfactory-cross-selling';

		/**
		 * User meta: last catalog version the user saw.
		 *
		 * @since 1.2.0
		 */
		const SEEN_META = 'wpf_myplugins_seen';

		/**
		 * All Access offer.
		 *
		 * @since 1.2.0
		 */
		const ALL_ACCESS_URL = 'https://wpfactory.com/all-access-lifetime-deal/';

		/**
		 * Contact page.
		 *
		 * @since 1.2.0
		 */
		const CONTACT_URL = 'https://wpfactory.com/contact-us/';

		/**
		 * Page hook suffix.
		 *
		 * @since 1.2.0
		 *
		 * @var string
		 */
		protected static $hook = '';

		/**
		 * The menu dot was shown in this request (the page marks the catalog as seen on load).
		 *
		 * @since 1.2.0
		 *
		 * @var bool
		 */
		protected static $dot = false;

		/**
		 * init.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function init() {
			if ( class_exists( '\WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu' ) ) {
				\WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu::get_instance();
			}
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 99 );
			add_action( 'admin_menu', array( __CLASS__, 'pin_last' ), PHP_INT_MAX );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		}

		/**
		 * url.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return string
		 */
		static function url() {
			return admin_url( 'admin.php?page=' . self::SLUG );
		}

		/**
		 * parent_slug.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return string
		 */
		static function parent_slug() {
			if ( class_exists( '\WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu' ) ) {
				return \WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu::get_instance()->get_menu_slug();
			}
			return 'wpfactory';
		}

		/**
		 * has_unseen.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return bool
		 */
		static function has_unseen() {
			return ( Catalog::CATALOG_VERSION > (int) get_user_meta( get_current_user_id(), self::SEEN_META, true ) );
		}

		/**
		 * dot_html.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @return string
		 */
		static function dot_html() {
			return ' <span class="wpf-myplugins-menu-dot" aria-hidden="true" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6BBFFF;margin-left:4px;vertical-align:middle"></span>' .
				'<span class="screen-reader-text">' . esc_html__( 'New plugins available', 'wpfactory-cross-selling' ) . '</span>';
		}

		/**
		 * register_menu.
		 *
		 * Runs after the old copies (priority 10): their Recommendations submenu is removed,
		 * and its URL redirects here before WordPress checks access to it.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function register_menu() {
			if ( isset( $_GET['page'] ) && self::OLD_SLUG === $_GET['page'] && current_user_can( Plugin::capability() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( self::url() );
				exit;
			}
			$parent = self::parent_slug();
			remove_submenu_page( $parent, self::OLD_SLUG );

			self::$dot  = self::has_unseen();
			$title      = __( 'My Plugins', 'wpfactory-cross-selling' );
			self::$hook = add_submenu_page( $parent, $title, $title . ( self::$dot ? self::dot_html() : '' ), Plugin::capability(), self::SLUG, array( __CLASS__, 'render' ) );
			if ( self::$hook ) {
				add_action( 'load-' . self::$hook, array( __CLASS__, 'on_load' ) );
			}
		}

		/**
		 * pin_last.
		 *
		 * Moves My Plugins to the end of the WPFactory submenu after every plugin has added its items,
		 * so it stays last in the sidebar and in the top bar (built from the same submenu).
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function pin_last() {
			global $submenu;
			$parent = self::parent_slug();
			if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
				return;
			}
			$submenu[ $parent ] = self::move_last( $submenu[ $parent ], self::SLUG ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reorders, nothing is removed.
		}

		/**
		 * move_last.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array  $entries Submenu entries ( title, capability, slug, ... ).
		 * @param string $slug    Slug to move.
		 *
		 * @return array
		 */
		static function move_last( array $entries, $slug ) {
			foreach ( $entries as $key => $entry ) {
				if ( isset( $entry[2] ) && $slug === $entry[2] ) {
					unset( $entries[ $key ] );
					$entries[] = $entry;
					break;
				}
			}
			return $entries;
		}

		/**
		 * on_load.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function on_load() {
			update_user_meta( get_current_user_id(), self::SEEN_META, Catalog::CATALOG_VERSION );
		}

		/**
		 * enqueue.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $hook_suffix Current admin page.
		 */
		static function enqueue( $hook_suffix ) {
			if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
				return;
			}
			$ver = Plugin::version();
			wp_enqueue_style( 'wpf-myplugins-kit', Plugin::url( 'assets/css/my-plugins-ui-kit.css' ), array(), $ver );
			wp_enqueue_style( 'wpf-myplugins', Plugin::url( 'assets/css/my-plugins.css' ), array( 'wpf-myplugins-kit' ), $ver );
			wp_enqueue_script( 'wpf-myplugins', Plugin::url( 'assets/js/my-plugins.js' ), array(), $ver, true );
			wp_localize_script( 'wpf-myplugins', 'wpfMyPlugins', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Installer::NONCE ),
				'i18n'    => array(
					'installing' => __( 'Installing...', 'wpfactory-cross-selling' ),
					'activate'   => __( 'Activate', 'wpfactory-cross-selling' ),
					'activating' => __( 'Activating...', 'wpfactory-cross-selling' ),
					'activated'  => __( 'Activated', 'wpfactory-cross-selling' ),
					'settings'   => __( 'Settings', 'wpfactory-cross-selling' ),
					'wporg'      => __( 'View on WordPress.org', 'wpfactory-cross-selling' ),
					'newTab'     => __( '(opens in a new tab)', 'wpfactory-cross-selling' ),
					'error'      => __( 'Something went wrong. Reload the page and try again.', 'wpfactory-cross-selling' ),
				),
			) );
		}

		/**
		 * render.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function render() {
			if ( ! current_user_can( Plugin::capability() ) ) {
				return;
			}
			$state  = Site_State::from_wp();
			$woo    = class_exists( 'WooCommerce' );
			$items  = Catalog::get_items();
			$picks  = Rules_Engine::get_picks( $state );
			$owned  = self::sort_owned( $items, $state );
			$picked_ids = wp_list_pluck( $picks, 'id' );
			?>
			<div class="wpf-myplugins">
				<?php self::render_topbar(); ?>
				<main class="ak-page wpf-mp-page">
					<hr class="wp-header-end">
					<section class="wpf-mp-section" aria-labelledby="wpf-mp-title">
						<div>
							<h1 id="wpf-mp-title" class="wpf-mp-h1"><?php esc_html_e( 'My Plugins', 'wpfactory-cross-selling' ); ?></h1>
							<p class="wpf-mp-lead"><?php echo esc_html( self::get_subline( count( $owned ) ) ); ?></p>
						</div>
						<?php self::render_owned( $owned, $state ); ?>
					</section>
					<?php if ( ! empty( $picks ) ) : ?>
						<?php self::render_picks( $picks ); ?>
					<?php endif; ?>
					<?php self::render_catalog( $items, $state, $woo, $picked_ids ); ?>
				</main>
			</div>
			<?php
		}

		/**
		 * is_owned_active.
		 *
		 * Pro decides when it is installed (a free copy left active next to an inactive Pro counts as inactive).
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array      $item  Catalog item.
		 * @param Site_State $state Site state.
		 *
		 * @return bool
		 */
		static function is_owned_active( $item, $state ) {
			$found = $state->find( $item );
			return ( '' !== $found['pro'] ? $found['pro_active'] : $found['free_active'] );
		}

		/**
		 * sort_owned.
		 *
		 * Installed catalog items, active first and inactive last, catalog order inside each group.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array      $items Catalog items.
		 * @param Site_State $state Site state.
		 *
		 * @return array
		 */
		static function sort_owned( array $items, $state ) {
			$active   = array();
			$inactive = array();
			foreach ( $items as $id => $item ) {
				if ( ! $state->is_installed( $item ) ) {
					continue;
				}
				if ( self::is_owned_active( $item, $state ) ) {
					$active[ $id ] = $item;
				} else {
					$inactive[ $id ] = $item;
				}
			}
			return $active + $inactive;
		}

		/**
		 * get_subline.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param int $installed Installed WPFactory and WBW plugins, active or not.
		 *
		 * @return string
		 */
		static function get_subline( $installed ) {
			if ( $installed < 1 ) {
				return __( 'Here\'s what fits how you sell.', 'wpfactory-cross-selling' );
			}
			/* translators: %s: number of installed WPFactory plugins. */
			$have = sprintf( _n( 'You have %s WPFactory plugin.', 'You have %s WPFactory plugins.', $installed, 'wpfactory-cross-selling' ), number_format_i18n( $installed ) );
			return $have . ' ' . __( 'Here\'s what else fits how you sell.', 'wpfactory-cross-selling' );
		}

		/**
		 * render_topbar.
		 *
		 * The WPFactory submenu as a top bar, as in the AI Assistant and Helpdesk screens.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		static function render_topbar() {
			global $submenu;
			$parent  = self::parent_slug();
			$entries = isset( $submenu[ $parent ] ) ? (array) $submenu[ $parent ] : array();
			?>
			<header class="ak-topbar wpf-mp-topbar">
				<div class="ak-brand"><span class="ak-brand-mark wpf-mp-brand-mark" aria-hidden="true">W</span><?php esc_html_e( 'WPFactory', 'wpfactory-cross-selling' ); ?></div>
				<nav class="ak-nav" aria-label="<?php esc_attr_e( 'WPFactory', 'wpfactory-cross-selling' ); ?>">
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						if ( empty( $entry[2] ) || ( ! empty( $entry[1] ) && ! current_user_can( $entry[1] ) ) ) {
							continue;
						}
						$slug    = $entry[2];
						$current = ( self::SLUG === $slug );
						$label   = trim( wp_strip_all_tags( preg_replace( '/<span class="screen-reader-text">.*?<\/span>/s', '', $entry[0] ) ) );
						$href    = ( false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug ) );
						if ( '' === $label ) {
							continue;
						}
						?>
						<a href="<?php echo esc_url( $href ); ?>"<?php echo $current ? ' class="on" aria-current="page"' : ''; ?>>
							<?php echo esc_html( $label ); ?>
							<?php if ( $current && self::$dot ) : ?>
								<span class="wpf-mp-dot" role="img" aria-label="<?php esc_attr_e( 'New plugins available', 'wpfactory-cross-selling' ); ?>"></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</header>
			<?php
		}

		/**
		 * render_owned.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array      $owned Installed catalog items.
		 * @param Site_State $state Site state.
		 */
		static function render_owned( array $owned, $state ) {
			?>
			<div class="ak-card wpf-mp-owned">
				<?php foreach ( $owned as $item ) : ?>
					<?php
					$found     = $state->find( $item );
					$is_active = self::is_owned_active( $item, $state );
					if ( ! $is_active ) {
						$chip = array( 'neutral', __( 'Inactive', 'wpfactory-cross-selling' ) );
					} elseif ( '' !== $found['pro'] ) {
						$chip = array( 'ok', __( 'Pro', 'wpfactory-cross-selling' ) );
					} else {
						$chip = array( 'neutral', __( 'Free', 'wpfactory-cross-selling' ) );
					}
					$file = ( '' !== $found['pro'] ? $found['pro'] : $found['free'] );
					?>
					<div class="wpf-mp-owned-row" data-id="<?php echo esc_attr( $item['id'] ); ?>">
						<div class="wpf-mp-owned-name"><b><?php echo esc_html( $item['name'] ); ?></b> <span class="wpf-mp-muted"><?php echo esc_html( $item['desc'] ); ?></span></div>
						<span class="ak-chip ak-chip-<?php echo esc_attr( $chip[0] ); ?> wpf-mp-chip"><?php echo esc_html( $chip[1] ); ?></span>
						<div class="wpf-mp-owned-action">
							<?php
							if ( ! $is_active ) {
								if ( current_user_can( 'activate_plugins' ) ) {
									printf( '<button type="button" class="ak-btn ak-btn-secondary ak-btn-sm wpf-mp-activate" data-id="%s">%s</button>', esc_attr( $item['id'] ), esc_html__( 'Activate', 'wpfactory-cross-selling' ) );
								}
							} elseif ( '' === $found['pro'] && '' !== $item['pro_url'] ) {
								echo self::external_link( $item['pro_url'], __( 'Upgrade to Pro', 'wpfactory-cross-selling' ), 'ak-btn ak-btn-secondary ak-btn-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
							} else {
								$settings = Installer::get_settings_url( $file );
								if ( '' !== $settings ) {
									printf( '<a class="ak-a" href="%s">%s</a>', esc_url( $settings ), esc_html__( 'Settings', 'wpfactory-cross-selling' ) );
								}
							}
							?>
						</div>
						<p class="wpf-mp-error" role="alert" hidden></p>
					</div>
				<?php endforeach; ?>
				<div class="wpf-mp-allaccess">
					<div class="wpf-mp-allaccess-text">
						<b><?php esc_html_e( 'Get every WPFactory plugin with All Access', 'wpfactory-cross-selling' ); ?></b>
						<span class="wpf-mp-muted">
							<?php esc_html_e( 'Pro versions of all our plugins, for life. Bought a plugin from us in the last 60 days?', 'wpfactory-cross-selling' ); ?>
							<?php
							printf(
								/* translators: %s: "Contact us" link. */
								esc_html__( '%s and we\'ll count it toward your upgrade.', 'wpfactory-cross-selling' ),
								self::external_link( self::CONTACT_URL, __( 'Contact us', 'wpfactory-cross-selling' ), 'ak-a' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
							);
							?>
						</span>
					</div>
					<?php
					/* translators: %s: price. */
					echo self::external_link( self::ALL_ACCESS_URL, sprintf( __( 'Get All Access: %s', 'wpfactory-cross-selling' ), '$499' ), 'ak-btn ak-btn-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
					?>
				</div>
			</div>
			<?php
		}

		/**
		 * render_picks.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $picks Rules_Engine::get_picks().
		 */
		static function render_picks( array $picks ) {
			?>
			<section class="wpf-mp-section" aria-labelledby="wpf-mp-picks-title">
				<div>
					<h2 id="wpf-mp-picks-title" class="wpf-mp-h2"><?php esc_html_e( 'Picked for your store', 'wpfactory-cross-selling' ); ?></h2>
					<p class="wpf-mp-note"><?php esc_html_e( 'Based on your store settings. Checked on this site, nothing is sent anywhere.', 'wpfactory-cross-selling' ); ?></p>
				</div>
				<div class="wpf-mp-picks">
					<?php foreach ( $picks as $pick ) : ?>
						<?php $item = Catalog::get_item( $pick['id'] ); ?>
						<article class="ak-card wpf-mp-pick" data-id="<?php echo esc_attr( $item['id'] ); ?>">
							<div class="wpf-mp-reason"><?php echo esc_html( $pick['reason'] ); ?></div>
							<div class="wpf-mp-pick-body">
								<h3 class="wpf-mp-h3"><?php echo esc_html( $item['name'] ); ?></h3>
								<p><?php echo esc_html( $item['desc'] ); ?></p>
							</div>
							<div class="wpf-mp-pick-foot">
								<?php self::render_actions( $item, 'pick' ); ?>
							</div>
							<p class="wpf-mp-error" role="alert" hidden></p>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		/**
		 * render_catalog.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array      $items      Catalog items.
		 * @param Site_State $state      Site state.
		 * @param bool       $woo        WooCommerce is active.
		 * @param string[]   $picked_ids Ids shown in "Picked for your store".
		 */
		static function render_catalog( array $items, $state, $woo, array $picked_ids ) {
			$shown = array();
			foreach ( $items as $id => $item ) {
				if ( Catalog::is_available( $item ) && ! $state->is_installed( $item ) && ( $woo || ! $item['store'] ) ) {
					$shown[ $id ] = $item;
				}
			}
			if ( empty( $shown ) ) {
				return;
			}
			$shown = self::top_first( $shown );
			$tabs  = array();
			foreach ( Catalog::get_jobs() as $job => $label ) {
				$count = count( array_filter( $shown, function ( $item ) use ( $job ) {
					return ( 'top' === $job ? $item['top'] : $job === $item['job'] );
				} ) );
				if ( $count > 0 ) {
					$tabs[ $job ] = $label;
				}
			}
			$first = key( $tabs );
			?>
			<section class="wpf-mp-section wpf-mp-catalog" aria-labelledby="wpf-mp-catalog-title">
				<div class="wpf-mp-catalog-head">
					<h2 id="wpf-mp-catalog-title" class="wpf-mp-h2"><?php esc_html_e( 'All WPFactory plugins', 'wpfactory-cross-selling' ); ?></h2>
					<label class="wpf-mp-search">
						<span><?php esc_html_e( 'Search', 'wpfactory-cross-selling' ); ?></span>
						<input type="search" class="ak-input" placeholder="<?php esc_attr_e( 'VAT, quantity, barcode...', 'wpfactory-cross-selling' ); ?>" aria-controls="wpf-mp-catalog-list">
					</label>
				</div>
				<div class="ak-seg wpf-mp-tabs" role="tablist" aria-label="<?php esc_attr_e( 'What you want to do', 'wpfactory-cross-selling' ); ?>">
					<?php foreach ( $tabs as $job => $label ) : ?>
						<button type="button" role="tab" id="wpf-mp-tab-<?php echo esc_attr( $job ); ?>" data-job="<?php echo esc_attr( $job ); ?>" aria-controls="wpf-mp-catalog-list" aria-selected="<?php echo $job === $first ? 'true' : 'false'; ?>" tabindex="<?php echo $job === $first ? '0' : '-1'; ?>"<?php echo $job === $first ? ' class="on"' : ''; ?>><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<div class="ak-card wpf-mp-list" id="wpf-mp-catalog-list" role="tabpanel" aria-labelledby="wpf-mp-tab-<?php echo esc_attr( $first ); ?>">
					<?php foreach ( $shown as $item ) : ?>
						<div class="wpf-mp-row" data-id="<?php echo esc_attr( $item['id'] ); ?>" data-job="<?php echo esc_attr( $item['job'] ); ?>" data-top="<?php echo $item['top'] ? '1' : '0'; ?>" data-search="<?php echo esc_attr( strtolower( $item['name'] . ' ' . $item['desc'] ) ); ?>">
							<div class="wpf-mp-row-main">
								<?php self::render_logo( $item ); ?>
								<div>
									<b><?php echo esc_html( $item['name'] ); ?></b>
									<?php if ( in_array( $item['id'], $picked_ids, true ) ) : ?>
										<span class="ak-chip ak-chip-info"><?php esc_html_e( 'Picked above', 'wpfactory-cross-selling' ); ?></span>
									<?php endif; ?>
									<div class="wpf-mp-muted"><?php echo esc_html( $item['desc'] ); ?></div>
								</div>
							</div>
							<?php self::render_actions( $item, 'row' ); ?>
							<p class="wpf-mp-error" role="alert" hidden></p>
						</div>
					<?php endforeach; ?>
					<p class="wpf-mp-empty" hidden><?php esc_html_e( 'No plugin matches that search. Try a shorter word.', 'wpfactory-cross-selling' ); ?></p>
				</div>
			</section>
			<?php
		}

		/**
		 * top_first.
		 *
		 * Top plugins first, in Catalog::TOP order, then the rest in catalog order.
		 * Every tab and search result keeps this order.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $items Catalog items keyed by id.
		 *
		 * @return array
		 */
		static function top_first( array $items ) {
			$top = array();
			foreach ( Catalog::TOP as $id ) {
				if ( isset( $items[ $id ] ) ) {
					$top[ $id ] = $items[ $id ];
				}
			}
			return $top + $items;
		}

		/**
		 * render_logo.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array $item Catalog item.
		 */
		static function render_logo( $item ) {
			$src = '';
			if ( '' !== $item['icon_file'] ) {
				$src = Plugin::url( 'assets/img/icons/' . $item['icon_file'] );
			} elseif ( '' !== $item['icon_url'] ) {
				$src = $item['icon_url'];
			}
			if ( '' !== $src ) {
				printf( '<img class="wpf-mp-logo" src="%s" width="40" height="40" alt="" loading="lazy" decoding="async">', esc_url( $src ) );
			} else {
				printf( '<span class="wpf-mp-logo wpf-mp-logo-badge" aria-hidden="true">%s</span>', esc_html( $item['abbr'] ) );
			}
		}

		/**
		 * render_actions.
		 *
		 * Install free (or the wp.org link when installs aren't allowed) and Get Pro.
		 * Pro-only plugins get a single Get Pro button.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param array  $item    Catalog item.
		 * @param string $context "pick" or "row".
		 */
		static function render_actions( $item, $context ) {
			$main = ( 'pick' === $context ? 'ak-btn-primary' : 'ak-btn-secondary' );
			echo '<div class="wpf-mp-actions">';
			if ( Catalog::is_pro_only( $item ) ) {
				echo self::external_link( $item['pro_url'], __( 'Get Pro', 'wpfactory-cross-selling' ), 'ak-btn ' . $main . ' ak-btn-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
			} else {
				if ( Installer::can_install() ) {
					printf( '<button type="button" class="ak-btn %s ak-btn-sm wpf-mp-install" data-id="%s">%s</button>', esc_attr( $main ), esc_attr( $item['id'] ), esc_html__( 'Install free', 'wpfactory-cross-selling' ) );
				} else {
					echo self::external_link( Installer::wporg_url( $item ), __( 'View on WordPress.org', 'wpfactory-cross-selling' ), 'ak-btn ' . $main . ' ak-btn-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
				}
				if ( '' !== $item['pro_url'] ) {
					echo self::external_link( $item['pro_url'], __( 'Get Pro', 'wpfactory-cross-selling' ), 'ak-btn ak-btn-text ak-btn-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
				}
			}
			echo '</div>';
		}

		/**
		 * external_link.
		 *
		 * New tab, rel="noopener noreferrer", no tracking params.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 *
		 * @param string $url   URL.
		 * @param string $text  Link text.
		 * @param string $class CSS classes.
		 *
		 * @return string Escaped HTML.
		 */
		static function external_link( $url, $text, $class ) {
			return sprintf(
				'<a class="%s" href="%s" target="_blank" rel="noopener noreferrer">%s<span class="screen-reader-text"> %s</span></a>',
				esc_attr( $class ),
				esc_url( $url ),
				esc_html( $text ),
				esc_html__( '(opens in a new tab)', 'wpfactory-cross-selling' )
			);
		}

	}

}
