<?php
/**
 * Blocks: the mini-cart products table with add-ons, and the Cart / Checkout block integration.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block registration.
 */
class Blocks {

	/**
	 * Hooks. Runs on `init` priority 0, so blocks register in time.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_blocks();

		add_action( 'woocommerce_blocks_cart_block_registration', array( __CLASS__, 'register_integration' ) );
		add_action( 'woocommerce_blocks_checkout_block_registration', array( __CLASS__, 'register_integration' ) );
		add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'enqueue_cart_checkout_style' ) );
		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'enqueue_cart_checkout_style' ) );
	}

	/**
	 * Enqueue the Cart / Checkout styles (details dialog) when one of those blocks renders.
	 *
	 * @param string $block_content Block HTML.
	 *
	 * @return string
	 */
	public static function enqueue_cart_checkout_style( $block_content ) {
		require_once __DIR__ . '/class-blocks-integration.php';

		if ( wp_style_is( Blocks_Integration::NAME, 'registered' ) ) {
			wp_enqueue_style( Blocks_Integration::NAME );
		}

		return $block_content;
	}

	/**
	 * Register the blocks in build/.
	 *
	 * @return void
	 */
	private static function register_blocks() {
		$build    = A8CSP_WCPA_DIR . '/build';
		$manifest = $build . '/blocks-manifest.php';

		if ( ! file_exists( $manifest ) ) {
			return;
		}

		if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
			wp_register_block_types_from_metadata_collection( $build, $manifest );
			return;
		}

		foreach ( array_keys( require $manifest ) as $block ) {
			register_block_type( $build . '/' . $block );
		}
	}

	/**
	 * Register the Cart / Checkout integration (add-on line classes).
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $registry Registry.
	 *
	 * @return void
	 */
	public static function register_integration( $registry ) {
		require_once __DIR__ . '/class-blocks-integration.php';

		if ( ! $registry->is_registered( Blocks_Integration::NAME ) ) {
			$registry->register( new Blocks_Integration() );
		}
	}
}
