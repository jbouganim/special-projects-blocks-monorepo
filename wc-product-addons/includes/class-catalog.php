<?php
/**
 * Catalog: add-ons are never sold on their own, so they stay out of the shop, search and product pages.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalog visibility for add-on products.
 */
class Catalog {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'hide_from_catalog' ) );
		add_action( 'template_redirect', array( __CLASS__, 'disable_product_page' ) );
	}

	/**
	 * Keep add-ons hidden from the shop and search, however the product is saved (admin, REST, CLI).
	 * WooCommerce's own queries and the product blocks respect catalog visibility.
	 *
	 * @param WC_Product $product Product about to be saved.
	 *
	 * @return void
	 */
	public static function hide_from_catalog( $product ) {
		if ( $product instanceof WC_Product && 'hidden' !== $product->get_catalog_visibility() && a8csp_wcpa_is_addon( $product ) ) {
			$product->set_catalog_visibility( 'hidden' );
		}
	}

	/**
	 * Add-on product pages return a 404 for visitors. Shop managers can still preview them.
	 *
	 * @return void
	 */
	public static function disable_product_page() {
		if ( ! is_singular( 'product' ) || current_user_can( 'edit_products' ) ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		/**
		 * Filters whether an add-on's single product page is disabled (404).
		 *
		 * @param bool            $disable Whether to return a 404.
		 * @param WC_Product|null $product Product.
		 */
		if ( ! apply_filters( 'a8csp_wcpa_disable_product_page', a8csp_wcpa_is_addon( $product ), $product ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
