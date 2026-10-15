<?php
/**
 * Orders: add-on lines keep a link to their parent order line.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add-on order lines.
 */
class Order {

	/**
	 * Order item meta: the cart key of a line that has add-ons (temporary link, resolved at checkout).
	 */
	const META_CART_KEY = '_a8csp_wcpa_cart_key';

	/**
	 * Order item meta: the parent's cart key, on add-on lines.
	 */
	const META_PARENT_KEY = '_a8csp_wcpa_parent_key';

	/**
	 * Order item meta: the parent's order item ID, on add-on lines.
	 */
	const META_PARENT_ITEM_ID = '_a8csp_wcpa_parent_item_id';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_line_item_meta' ), 10, 3 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'link_lines' ), 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'link_lines' ), 1 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'formatted_meta' ), 10, 2 );
	}

	/**
	 * Store the cart keys on new order lines.
	 *
	 * @param WC_Order_Item_Product $item          Order line.
	 * @param string                $cart_item_key Cart key.
	 * @param array<string, mixed>  $values        Cart item.
	 *
	 * @return void
	 */
	public static function add_line_item_meta( $item, $cart_item_key, $values ) {
		$parent_key = a8csp_wcpa_get_parent_key( $values );

		if ( '' !== $parent_key ) {
			$item->update_meta_data( self::META_PARENT_KEY, $parent_key );
			return;
		}

		if ( WC()->cart && array() !== Cart::get_children( $cart_item_key ) ) {
			$item->update_meta_data( self::META_CART_KEY, $cart_item_key );
		}
	}

	/**
	 * Once the order lines have IDs, point each add-on line at its parent line.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public static function link_lines( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$ids_by_key = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$key = (string) $item->get_meta( self::META_CART_KEY );
			if ( '' !== $key ) {
				$ids_by_key[ $key ] = (int) $item_id;
			}
		}

		foreach ( $order->get_items() as $item ) {
			$parent_key = (string) $item->get_meta( self::META_PARENT_KEY );
			if ( '' !== $parent_key && isset( $ids_by_key[ $parent_key ] ) && (int) $item->get_meta( self::META_PARENT_ITEM_ID ) !== $ids_by_key[ $parent_key ] ) {
				$item->update_meta_data( self::META_PARENT_ITEM_ID, $ids_by_key[ $parent_key ] );
				$item->save();
			}
		}
	}

	/**
	 * Hide the link meta in the admin order screen.
	 *
	 * @param string[] $keys Hidden meta keys.
	 *
	 * @return string[]
	 */
	public static function hidden_meta( $keys ) {
		return array_merge( $keys, array( self::META_CART_KEY, self::META_PARENT_KEY, self::META_PARENT_ITEM_ID ) );
	}

	/**
	 * Show "Add-on for: {parent line}" on add-on lines (admin, emails, order pages).
	 *
	 * @param array<int|string, object> $formatted_meta Formatted meta.
	 * @param WC_Order_Item             $item           Order line.
	 *
	 * @return array<int|string, object>
	 */
	public static function formatted_meta( $formatted_meta, $item ) {
		$parent_id = $item instanceof WC_Order_Item_Product ? (int) $item->get_meta( self::META_PARENT_ITEM_ID ) : 0;
		$order     = $parent_id ? $item->get_order() : null;
		$parent    = $order instanceof WC_Order ? $order->get_item( $parent_id ) : null;

		if ( ! $parent instanceof WC_Order_Item ) {
			return $formatted_meta;
		}

		/**
		 * Filters whether add-on order lines show the line they belong to.
		 *
		 * @param bool          $show   Whether to show it.
		 * @param WC_Order_Item $item   Add-on order line.
		 * @param WC_Order_Item $parent Parent order line.
		 */
		if ( ! apply_filters( 'a8csp_wcpa_show_addon_for_meta', true, $item, $parent ) ) {
			return $formatted_meta;
		}

		// Not a stored meta key, so the admin's hidden-meta list doesn't drop it.
		$formatted_meta['a8csp_wcpa_addon_for'] = (object) array(
			'key'           => 'a8csp_wcpa_addon_for',
			'value'         => $parent_id,
			'display_key'   => __( 'Add-on for', 'wc-product-addons' ),
			'display_value' => wpautop( esc_html( $parent->get_name() ) ),
		);

		return $formatted_meta;
	}
}
