<?php
/**
 * Cart and Checkout block integration.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds `is-addon` / `has-addons` classes to Cart and Checkout block lines.
 */
class Blocks_Integration implements IntegrationInterface {

	/**
	 * Integration and script handle name.
	 */
	const NAME = 'a8csp-wc-product-addons';

	/**
	 * Integration name.
	 *
	 * @return string
	 */
	public function get_name() {
		return self::NAME;
	}

	/**
	 * Register the script.
	 *
	 * @return void
	 */
	public function initialize() {
		$asset_file = A8CSP_WCPA_DIR . '/build/cart-checkout/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script(
			self::NAME,
			plugins_url( 'build/cart-checkout/index.js', A8CSP_WCPA_FILE ),
			array_unique( array_merge( $asset['dependencies'], array( 'wc-blocks-checkout', 'wp-data' ) ) ),
			$asset['version'],
			true
		);

		// The details dialog's structural styles, enqueued when the Cart or Checkout block renders (Blocks).
		if ( file_exists( A8CSP_WCPA_DIR . '/build/cart-checkout/index.css' ) ) {
			wp_register_style(
				self::NAME,
				plugins_url( 'build/cart-checkout/index.css', A8CSP_WCPA_FILE ),
				array(),
				$asset['version']
			);
		}
	}

	/**
	 * Front-end script handles.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return wp_script_is( self::NAME, 'registered' ) ? array( self::NAME ) : array();
	}

	/**
	 * Editor script handles.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Script data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data() {
		return array();
	}
}
