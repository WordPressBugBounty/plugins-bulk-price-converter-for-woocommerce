<?php
/**
 * Bulk Price Converter - Undo Class
 *
 * Saves the regular, sale and active prices a price change is about to overwrite,
 * and puts them back on request. Only the last change is kept.
 *
 * The saved prices live in options that are not autoloaded, split in chunks of
 * CHUNK_SIZE products, so a big catalog never creates one huge database row.
 *
 * @version 2.1.0
 * @since   2.1.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_Bulk_Price_Converter_Undo' ) ) :

class Alg_WC_Bulk_Price_Converter_Undo {

	/**
	 * Option that describes the saved change (time, counts, number of chunks).
	 *
	 * @since 2.1.0
	 */
	const OPTION = 'alg_wc_bulk_price_converter_undo';

	/**
	 * Prefix of the options that hold the saved prices.
	 *
	 * @since 2.1.0
	 */
	const CHUNK_OPTION = 'alg_wc_bulk_price_converter_undo_chunk_';

	/**
	 * Products and variations per chunk option.
	 *
	 * @since 2.1.0
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Price meta keys the tool writes, in the order they are saved.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 *
	 * @var     array
	 */
	public $meta_keys = array( '_regular_price', '_sale_price', '_price' );

	/**
	 * State of the change that is being saved, or null.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 *
	 * @var     array|null
	 */
	protected $run = null;

	/**
	 * begin.
	 *
	 * Starts saving a new change. The previous saved change is kept until the
	 * new change touches its first product.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 */
	function begin() {
		$this->run = array(
			'started' => false,
			'buffer'  => array(),
			'seen'    => array(),
			'info'    => array(
				'time'       => time(),
				'user_id'    => get_current_user_id(),
				'products'   => 0,
				'variations' => 0,
				'items'      => 0,
				'chunks'     => 0,
			),
		);
	}

	/**
	 * add.
	 *
	 * Saves the current prices of a product, or of the variations of a variable product.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 *
	 * @param   int   $product_id  Product ID (the parent for variable products).
	 * @param   array $item_ids    IDs whose prices are about to change.
	 * @param   bool  $is_variable Whether $item_ids are variations.
	 */
	function add( $product_id, $item_ids, $is_variable ) {
		if ( null === $this->run || empty( $item_ids ) ) {
			return;
		}
		if ( ! $this->run['started'] ) {
			$this->clear();
			$this->run['started'] = true;
		}
		$added = 0;
		foreach ( $item_ids as $item_id ) {
			$item_id = (int) $item_id;
			if ( isset( $this->run['seen'][ $item_id ] ) ) {
				continue;
			}
			$this->run['seen'][ $item_id ] = true;
			$prices = array();
			foreach ( $this->meta_keys as $meta_key ) {
				$values   = get_post_meta( $item_id, $meta_key, false );
				$prices[] = ( empty( $values ) ? null : $values[0] );
			}
			$this->run['buffer'][ $item_id ] = $prices;
			$added++;
			if ( count( $this->run['buffer'] ) >= self::CHUNK_SIZE ) {
				$this->flush();
			}
		}
		if ( $added > 0 ) {
			$this->run['info']['products']++;
			if ( $is_variable ) {
				$this->run['info']['variations'] += $added;
			}
		}
	}

	/**
	 * flush.
	 *
	 * Writes the buffered prices to a new chunk option, then updates the summary,
	 * so the saved change stays usable even if the run stops halfway.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 */
	function flush() {
		if ( null === $this->run || empty( $this->run['buffer'] ) ) {
			return;
		}
		update_option( self::CHUNK_OPTION . $this->run['info']['chunks'], $this->run['buffer'], false );
		$this->run['info']['chunks']++;
		$this->run['info']['items'] += count( $this->run['buffer'] );
		$this->run['buffer']         = array();
		update_option( self::OPTION, $this->run['info'], false );
	}

	/**
	 * end.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 */
	function end() {
		if ( null !== $this->run && $this->run['started'] ) {
			$this->flush();
		}
		$this->run = null;
	}

	/**
	 * get_info.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 *
	 * @return  array|false Summary of the saved change, or false if there is nothing to undo.
	 */
	function get_info() {
		$info = get_option( self::OPTION, false );
		if ( ! is_array( $info ) || empty( $info['items'] ) || empty( $info['chunks'] ) ) {
			return false;
		}
		return array_merge(
			array(
				'time'       => 0,
				'user_id'    => 0,
				'products'   => 0,
				'variations' => 0,
			),
			$info
		);
	}

	/**
	 * restore.
	 *
	 * Puts the saved prices back, re-syncs variable products, clears the price
	 * caches, then forgets the saved change.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 *
	 * @return  array|false Restored counts, or false if there was nothing to undo.
	 */
	function restore() {
		$info = $this->get_info();
		if ( ! $info ) {
			return false;
		}

		$time_limit   = get_option( 'alg_wc_bulk_price_converter_time_limit', -1 );
		$all_stati    = array_keys( get_post_stati() );
		$variable_ids = array();
		$products     = 0;
		$variations   = 0;

		for ( $i = 0; $i < (int) $info['chunks']; $i++ ) {
			if ( -1 != $time_limit && function_exists( 'wc_set_time_limit' ) ) {
				wc_set_time_limit( $time_limit );
			}
			$chunk = get_option( self::CHUNK_OPTION . $i, array() );
			if ( ! is_array( $chunk ) || empty( $chunk ) ) {
				continue;
			}

			// One query per type for the whole chunk: products and variations that still exist, and the variations' parents
			$chunk_ids     = array_map( 'intval', array_keys( $chunk ) );
			$query_args    = array(
				'post__in'               => $chunk_ids,
				'post_status'            => $all_stati,
				'posts_per_page'         => -1,
				'orderby'                => 'none',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			);
			$product_ids   = array_flip( get_posts( array_merge( $query_args, array( 'post_type' => 'product', 'fields' => 'ids' ) ) ) );
			$variation_ids = get_posts( array_merge( $query_args, array( 'post_type' => 'product_variation', 'fields' => 'id=>parent' ) ) );
			update_meta_cache( 'post', $chunk_ids );

			foreach ( $chunk as $item_id => $prices ) {
				$item_id = (int) $item_id;
				if ( isset( $variation_ids[ $item_id ] ) ) {
					$variable_ids[ (int) $variation_ids[ $item_id ] ] = true;
					$variations++;
				} elseif ( isset( $product_ids[ $item_id ] ) ) {
					$products++;
				} else {
					continue; // Deleted since the change.
				}
				foreach ( $this->meta_keys as $index => $meta_key ) {
					if ( ! array_key_exists( $index, $prices ) || null === $prices[ $index ] ) {
						if ( metadata_exists( 'post', $item_id, $meta_key ) ) {
							delete_post_meta( $item_id, $meta_key );
						}
					} else {
						update_post_meta( $item_id, $meta_key, wp_slash( $prices[ $index ] ) );
					}
				}
			}
		}

		// Variable products: re-sync the price range, clear their cached prices
		$core = alg_wc_bulk_price_converter()->core;
		foreach ( array_keys( $variable_ids ) as $variable_id ) {
			$core->sync_variable_product( wc_get_product( $variable_id ) );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $variable_id );
			}
		}
		// Shop-wide price caches
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}

		$this->clear();

		return array(
			'products'   => $products + count( $variable_ids ),
			'variations' => $variations,
		);
	}

	/**
	 * clear.
	 *
	 * Deletes the saved change, including chunks left over by a run that stopped halfway.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 */
	function clear() {
		$info   = get_option( self::OPTION, array() );
		$chunks = ( is_array( $info ) && isset( $info['chunks'] ) ? (int) $info['chunks'] : 0 );
		delete_option( self::OPTION );
		for ( $i = 0; $i < $chunks; $i++ ) {
			delete_option( self::CHUNK_OPTION . $i );
		}
		while ( delete_option( self::CHUNK_OPTION . $i ) ) {
			$i++;
		}
	}

}

endif;

return new Alg_WC_Bulk_Price_Converter_Undo();
