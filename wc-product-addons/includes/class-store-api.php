<?php
/**
 * Store API: add-on data on cart items, and a cart/extensions callback to set add-on quantities.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Exception;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store API extension `a8csp_wcpa`.
 */
class Store_API {

	/**
	 * Extension namespace on cart items (`extensions.a8csp_wcpa`).
	 */
	const DATA_NAMESPACE = 'a8csp_wcpa';

	/**
	 * Namespace for `POST /wc/store/v1/cart/extensions`.
	 */
	const UPDATE_NAMESPACE = 'a8csp-wcpa';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// The plugin boots on `init`, after `woocommerce_blocks_loaded`.
		self::register();

		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( __CLASS__, 'quantity_maximum' ), 10, 3 );
	}

	/**
	 * Register the cart item data and the update callback.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => self::DATA_NAMESPACE,
				'data_callback'   => array( __CLASS__, 'cart_item_data' ),
				'schema_callback' => array( __CLASS__, 'cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::UPDATE_NAMESPACE,
				'callback'  => array( __CLASS__, 'update' ),
			)
		);
	}

	/**
	 * Add-on data for a cart item.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 *
	 * @return array<string, mixed>
	 */
	public static function cart_item_data( $cart_item ) {
		$parent_key = a8csp_wcpa_get_parent_key( $cart_item );
		$data       = array(
			'parent_key' => $parent_key,
			'is_addon'   => '' !== $parent_key,
			'addons'     => array(),
		);

		if ( '' !== $parent_key || ! isset( $cart_item['data'], $cart_item['key'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $data;
		}

		$children = array();
		foreach ( Cart::get_children( $cart_item['key'] ) as $key => $child ) {
			$children[ (int) $child['product_id'] ] = array(
				'key'      => (string) $key,
				'quantity' => (int) $child['quantity'],
			);
		}

		foreach ( a8csp_wcpa_get_addons( $cart_item['data'] ) as $addon ) {
			// The add-on's own limit (stock, set number). 'max_parent' add-ons also stop at the line's quantity, which the client tracks live.
			$max     = a8csp_wcpa_get_addon_limit( $addon );
			$details = a8csp_wcpa_get_addon_details( $addon );

			$data['addons'][] = array(
				'id'                => $addon->get_id(),
				'name'              => a8csp_wcpa_plain_text( $addon->get_name() ),
				'short_description' => a8csp_wcpa_get_addon_summary( $addon ),
				'has_details'       => '' !== $details,
				'details'           => $details,
				'price'             => self::price( $addon ),
				'price_formatted'   => self::format_price( (float) self::display_price( $addon ) ),
				'quantity_mode'     => a8csp_wcpa_get_quantity_mode( $addon ),
				'max'               => $max < 0 ? null : (int) $max,
				'in_cart'           => $children[ $addon->get_id() ] ?? null,
			);
		}

		/**
		 * Filters the add-on data of a cart item in the Store API.
		 *
		 * @param array<string, mixed> $data      Add-on data.
		 * @param array<string, mixed> $cart_item Cart item.
		 */
		return (array) apply_filters( 'a8csp_wcpa_store_api_cart_item_data', $data, $cart_item );
	}

	/**
	 * Schema for the cart item data.
	 *
	 * @return array<string, mixed>
	 */
	public static function cart_item_schema() {
		return array(
			'parent_key' => array(
				'description' => __( 'Cart key of the line this add-on belongs to; empty for other lines.', 'wc-product-addons' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'is_addon'   => array(
				'description' => __( 'Whether this line is an add-on.', 'wc-product-addons' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'addons'     => array(
				'description' => __( 'Add-ons offered with this line.', 'wc-product-addons' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => array( 'type' => 'integer' ),
						'name'              => array( 'type' => 'string' ),
						'short_description' => array(
							'description' => __( 'The short description as plain text, shown with the add-on; empty when not set.', 'wc-product-addons' ),
							'type'        => 'string',
						),
						'has_details'       => array(
							'description' => __( 'Whether the add-on has a description (for a details link).', 'wc-product-addons' ),
							'type'        => 'boolean',
						),
						'details'           => array(
							'description' => __( 'The description as HTML, for the details dialog; empty when has_details is false.', 'wc-product-addons' ),
							'type'        => 'string',
						),
						'price'             => array(
							'description' => __( 'Unit price in the currency’s minor unit, as shown in the cart.', 'wc-product-addons' ),
							'type'        => 'string',
						),
						'price_formatted'   => array( 'type' => 'string' ),
						'quantity_mode'     => array(
							'type' => 'string',
							'enum' => array( 'independent', 'max_parent', 'fixed' ),
						),
						'max'               => array(
							'description' => __( 'The add-on’s own quantity limit per line (stock, set number), or null. With max_parent it also stops at the line’s quantity.', 'wc-product-addons' ),
							'type'        => array( 'integer', 'null' ),
						),
						'in_cart'           => array(
							'type'       => array( 'object', 'null' ),
							'properties' => array(
								'key'      => array( 'type' => 'string' ),
								'quantity' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * `POST /wc/store/v1/cart/extensions` with `namespace: a8csp-wcpa` and
	 * `data: { items: [ { parent_key, addon_id, quantity } ] }` (or a single item as `data`).
	 * Quantity 0 removes the add-on.
	 *
	 * @param array<string, mixed> $data Request data.
	 *
	 * @return void
	 * @throws RouteException When an add-on can't be set.
	 */
	public static function update( $data ) {
		$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array( $data );

		foreach ( $items as $item ) {
			$parent_key = isset( $item['parent_key'] ) ? sanitize_key( (string) $item['parent_key'] ) : '';
			$addon_id   = isset( $item['addon_id'] ) ? absint( $item['addon_id'] ) : 0;
			$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

			if ( '' === $parent_key || ! $addon_id ) {
				throw new RouteException( 'a8csp_wcpa_invalid_request', esc_html__( 'Missing cart item or add-on.', 'wc-product-addons' ), 400 );
			}

			try {
				Cart::set_addon_quantity( $parent_key, $addon_id, $quantity );
			} catch ( Exception $e ) {
				throw new RouteException( 'a8csp_wcpa_set_failed', esc_html( $e->getMessage() ), 400 );
			}
		}
	}

	/**
	 * Cart block / mini-cart quantity limit for add-on lines.
	 *
	 * @param mixed                     $value     Maximum.
	 * @param WC_Product                $product   Product.
	 * @param array<string, mixed>|null $cart_item Cart item.
	 *
	 * @return mixed
	 */
	public static function quantity_maximum( $value, $product, $cart_item ) {
		$parent_key = is_array( $cart_item ) ? a8csp_wcpa_get_parent_key( $cart_item ) : '';
		if ( '' === $parent_key || ! WC()->cart ) {
			return $value;
		}

		$parent_item = WC()->cart->get_cart_item( $parent_key );
		if ( array() === $parent_item || ! $product instanceof WC_Product ) {
			return $value;
		}

		return min( (int) $value, Cart::get_max_quantity( $product, $parent_item ) );
	}

	/**
	 * Unit price as displayed in the cart (tax setting applied).
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return float|string
	 */
	private static function display_price( $product ) {
		return 'incl' === WC()->cart->get_tax_price_display_mode()
			? wc_get_price_including_tax( $product )
			: wc_get_price_excluding_tax( $product );
	}

	/**
	 * Unit price in the currency's minor unit.
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return string
	 */
	private static function price( $product ) {
		return (string) (int) round( (float) self::display_price( $product ) * ( 10 ** wc_get_price_decimals() ) );
	}

	/**
	 * Plain-text money, e.g. "$45.00".
	 *
	 * @param float $amount Amount.
	 *
	 * @return string
	 */
	private static function format_price( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
