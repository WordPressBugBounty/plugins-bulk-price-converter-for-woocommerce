<?php
/**
 * Bulk Price Converter - Core Class
 *
 * @version 2.0.5
 * @since   1.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_Bulk_Price_Converter_Core' ) ) :

class Alg_WC_Bulk_Price_Converter_Core {

	/**
	 * attribute_taxonomies.
	 */
	public $attribute_taxonomies;

	/**
	 * atts.
	 *
	 * @version 2.0.5
	 * @since   2.0.5
	 */
	public $atts;

	/**
	 * result.
	 *
	 * @version 2.0.5
	 * @since   2.0.5
	 */
	public $result;

	/**
	 * Constructor.
	 *
	 * @version 2.0.5
	 */
	function __construct() {
		return true;
	}

	/**
	 * alg_wc_get_attribute_taxonomies.
	 *
	 * @version 2.0.5
	 * @since   2.0.5
	 */
	function alg_wc_get_attribute_taxonomies() {
		if ( ! isset( $this->attribute_taxonomies ) ) {
			$this->attribute_taxonomies = ( function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array() );
		}
		return $this->attribute_taxonomies;
	}

	/**
	 * change_price.
	 *
	 * @version 2.0.5
	 */
	function change_price( $product_id, $parent_product_id, $price_type, $min_price = 0, $max_price = 0 ) {
		// Price calculation
		$price          = get_post_meta( $product_id, '_' . $price_type, true );
		$modified_price = $price;
		// Direct price
		if ( '' !== $this->atts['direct_price'] ) {
			$modified_price = $this->atts['direct_price'];
		}
		if ( '' !== $modified_price ) {
			// Percentage
			if ( '' !== $this->atts['percentage_prices_by'] ) {
				$modified_price = ( $modified_price * $this->atts['percentage_prices_by'] / 100 ) + $modified_price;
			}
			// Multiplication
			if ( '' !== $this->atts['multiply_prices_by'] ) {
				$modified_price = $modified_price * $this->atts['multiply_prices_by'];
			}
			// Divide
			if ( '' !== $this->atts['divide_prices_by'] ) {
				$modified_price = apply_filters( 'alg_wc_bpc_divide_price', $modified_price, $this->atts['divide_prices_by'] );
			}
			// Addition
			if ( '' != $this->atts['add_to_price'] ) {
				$modified_price = $modified_price + $this->atts['add_to_price'];
			}
			// Subtract
			if ( '' != $this->atts['minus_to_price'] ) {
				$modified_price = apply_filters( 'alg_wc_bpc_subtract_price', $modified_price, $this->atts['minus_to_price'] );
			}
			// Rounding
			if ( 'none' != $this->atts['round_function'] ) {
				$modified_price = apply_filters( 'alg_wc_bpc_round_price', $modified_price, $this->atts['round_function'], $this->atts['round_coef'] );
			}
			// Final rounding
			$precision          = get_option( 'woocommerce_price_num_decimals', 2 );
			$modified_price     = round( $modified_price, $precision );
			// "Pretty price"
			if ( $this->atts['pretty_prices_threshold'] > 0 ) {
				$modified_price = apply_filters( 'alg_wc_bpc_pretty_price', $modified_price, $this->atts['pretty_prices_threshold'] );
			}
			// Negative, min & max
			if ( $modified_price < 0 ) {
				$modified_price = 0;
			}
			if ( 0 != $min_price && $modified_price < $min_price ) {
				$modified_price = $min_price;
			}
			if ( 0 != $max_price && $modified_price > $max_price ) {
				$modified_price = $max_price;
			}
			// Maybe update to new price
			if ( ! $this->atts['is_preview'] ) {
				update_post_meta( $product_id, '_' . $price_type, $modified_price );
			}
		}
		// Output
		if ( '' != $price || '' != $modified_price ) {
			$_product_cats = array();
			$product_terms = get_the_terms( $parent_product_id, 'product_cat' );
			if ( ! is_wp_error( $product_terms ) && is_array( $product_terms ) ) {
				foreach ( $product_terms as $term ) {
					$_product_cats[] = esc_html( $term->name );
				}
			}
			$_product_tags = array();
			$product_terms = get_the_terms( $parent_product_id, 'product_tag' );
			if ( ! is_wp_error( $product_terms ) && is_array( $product_terms ) ) {
				foreach ( $product_terms as $term ) {
					$_product_tags[] = esc_html( $term->name );
				}
			}
			$this->result[] = array(
				get_the_title( $product_id ) . ' (#' . $product_id . ')',
				implode( ', ', $_product_cats ),
				implode( ', ', $_product_tags ),
				'<code>' . str_replace( '_', ' ', $price_type ) . '</code>',
				( is_numeric( $price ) ? wc_price( $price ) : $price ),
				'<span' . ( $modified_price != $price ? ' style="color:orange;"' : '' ) . '>' . ( is_numeric( $modified_price ) ? wc_price( $modified_price ) : $modified_price ) . '</span>',
			);
		}
	}

	/**
	 * change_all_products_prices.
	 *
	 * @version 2.0.5
	 *
	 * @todo    (dev) `sale_prices`: remove `( get_post_meta( $_product_id, '_' . 'price', true ) === get_post_meta( $_product_id, '_' . 'sale_price', true ) )` check?
	 * @todo    (dev) `regular_prices`: remove `( $price !== $sale_price || $atts['multiply_prices_by'] <= 1 )` check?
	 */
	function change_all_products_prices( $atts ) {

		if ( $atts['multiply_prices_by'] <= 0 ) {
			return;
		}

		$offset               = 0;
		$block_size           = get_option( 'alg_wc_bulk_price_converter_block_size', 1024 );
		$time_limit           = get_option( 'alg_wc_bulk_price_converter_time_limit', -1 );
		$attribute_taxonomies = $this->alg_wc_get_attribute_taxonomies();
		$this->result         = array();
		$this->atts           = $atts;

		while ( true ) {

			if ( -1 != $time_limit ) {
				wc_set_time_limit( $time_limit );
			}

			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => $block_size,
				'offset'         => $offset,
				'fields'         => 'ids',
			);

			if ( 'any' != $atts['product_cats'] ) {
				$terms = $atts['product_cats'];
				$args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( $terms ),
					'operator' => ( 'none' != $terms ? 'IN' : 'NOT EXISTS' ),
				);
			}

			if ( 'any' != $atts['product_tags'] ) {
				$args = apply_filters( 'alg_wc_bpc_product_query', $args, 'product_tag', $atts['product_tags'] );
			}

			if ( ! empty( $attribute_taxonomies ) ) {
				foreach ( $attribute_taxonomies as $taxn ) {
					$attr_slug = 'pa_' . $taxn->attribute_name;
					if ( 'any' != $atts[ $attr_slug ] ) {
						$args = apply_filters( 'alg_wc_bpc_product_query', $args, $attr_slug, $atts[ $attr_slug ] );
					}
				}
			}

			$loop = new WP_Query( $args );

			if ( ! $loop->have_posts() ) {
				break;
			}

			foreach ( $loop->posts as $product_id ) {

				// Getting all product IDs (including variations for variable products)
				$product_ids = array( $product_id );
				$product     = wc_get_product( $product_id );

				if ( $product->is_type( 'variable' ) ) {
					$product_ids = apply_filters(
						'alg_wc_bpc_get_variation_ids',
						$product->get_children(),
						$product,
						$atts,
						$attribute_taxonomies
					);
				}

				// Changing prices
				foreach ( $product_ids as $_product_id ) {
					switch ( $atts['price_types'] ) {

						case 'both':
							$this->change_price( $_product_id, $product_id, 'price',         0, 0 );
							$this->change_price( $_product_id, $product_id, 'sale_price',    0, 0 );
							$this->change_price( $_product_id, $product_id, 'regular_price', 0, 0 );
							break;

						case 'sale_prices':
							$regular_price = get_post_meta( $_product_id, '_' . 'regular_price', true );
							if ( get_post_meta( $_product_id, '_' . 'price', true ) === get_post_meta( $_product_id, '_' . 'sale_price', true ) ) {
								$this->change_price( $_product_id, $product_id, 'price',  0, $regular_price );
							}
							$this->change_price( $_product_id, $product_id, 'sale_price', 0, $regular_price );
							break;

						case 'regular_prices':
							$sale_price = get_post_meta( $_product_id, '_' . 'sale_price', true );
							$price      = get_post_meta( $_product_id, '_' . 'price', true );
							if ( $price === get_post_meta( $_product_id, '_' . 'regular_price', true ) && ( $price !== $sale_price || $atts['multiply_prices_by'] <= 1 ) ) {
								$this->change_price( $_product_id, $product_id, 'price', $sale_price, 0 );
							}
							$this->change_price( $_product_id, $product_id, 'regular_price', $sale_price, 0 );
							break;

					}
				}

				// Sync the parent variable product so its own prices reflect the updated variations.
				if ( $product->is_type( 'variable' ) && ! $atts['is_preview'] && ! empty( $product_ids ) ) {
					if ( method_exists( 'WC_Product_Variable', 'sync' ) ) {
						if ( ( new ReflectionMethod( 'WC_Product_Variable', 'sync' ) )->isStatic() ) {
							WC_Product_Variable::sync( $product );
						} else {
							$product->sync();
						}
					}
				}

			}

			$offset += $block_size;

		}

		return $this->result;

	}

}

endif;

return new Alg_WC_Bulk_Price_Converter_Core();
