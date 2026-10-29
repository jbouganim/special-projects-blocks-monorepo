<?php
/**
 * Cart rules: add-ons are their own cart lines, each linked to a parent line by its cart key.
 *
 * @package A8csp
 */

namespace A8CSP\WC_Product_Addons;

use Exception;
use WC_Cart;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add-on cart lines.
 */
class Cart {

	/**
	 * Set while this plugin adds an add-on line, so standalone add-to-cart attempts can be refused.
	 *
	 * @var bool
	 */
	private static $adding = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'after_add_to_cart' ), 20, 6 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'tidy' ), 20 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'remove_children' ), 10, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'after_quantity_update' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'addon_permalink' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'addon_description_item_data' ), 10, 2 );
	}

	/**
	 * Add an add-on line under a parent line, or change its quantity (0 removes it).
	 *
	 * @param string $parent_key Parent cart item key.
	 * @param int    $addon_id   Add-on product ID.
	 * @param int    $quantity   New quantity.
	 *
	 * @return string The add-on line's cart key, or '' when it was removed.
	 * @throws Exception When the add-on can't be added to that line.
	 */
	public static function set_addon_quantity( $parent_key, $addon_id, $quantity ) {
		$cart        = WC()->cart;
		$parent_item = $cart->get_cart_item( $parent_key );
		$quantity    = max( 0, (int) $quantity );

		if ( array() === $parent_item || '' !== a8csp_wcpa_get_parent_key( $parent_item ) ) {
			throw new Exception( esc_html__( 'This cart item no longer exists.', 'wc-product-addons' ) );
		}

		$addon = self::get_offered_addon( $parent_item, $addon_id );
		if ( ! $addon instanceof WC_Product ) {
			throw new Exception( esc_html__( 'This add-on is not available for this item.', 'wc-product-addons' ) );
		}

		$quantity = min( $quantity, self::get_max_quantity( $addon, $parent_item ) );
		$key      = self::find_addon_key( $parent_key, $addon->get_id() );

		if ( '' !== $key ) {
			if ( 0 === $quantity ) {
				$cart->remove_cart_item( $key );
				return '';
			}

			$cart->set_quantity( $key, $quantity );
			return $key;
		}

		if ( 0 === $quantity ) {
			return '';
		}

		wc_clear_notices();
		self::$adding = true;

		try {
			$key = $cart->add_to_cart( $addon->get_id(), $quantity, 0, array(), array( A8CSP_WCPA_CART_KEY => array( 'parent_key' => $parent_key ) ) );
		} finally {
			self::$adding = false;
		}

		if ( ! $key ) {
			$errors = wc_get_notices( 'error' );
			wc_clear_notices();
			$message = isset( $errors[0]['notice'] ) ? wp_strip_all_tags( $errors[0]['notice'] ) : __( 'This add-on could not be added.', 'wc-product-addons' );
			throw new Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text, escaped on output.
		}

		self::sort();

		return (string) $key;
	}

	/**
	 * An add-on offered on a parent line, or null.
	 *
	 * @param array<string, mixed> $parent_item   Parent cart item.
	 * @param int                  $addon_id Add-on product ID.
	 *
	 * @return WC_Product|null
	 */
	public static function get_offered_addon( $parent_item, $addon_id ) {
		if ( ! isset( $parent_item['data'] ) || ! $parent_item['data'] instanceof WC_Product ) {
			return null;
		}

		foreach ( a8csp_wcpa_get_addons( $parent_item['data'] ) as $addon ) {
			if ( (int) $addon->get_id() === (int) $addon_id ) {
				return $addon;
			}
		}

		return null;
	}

	/**
	 * Highest quantity of an add-on for a parent line: stock, the set number ('fixed' rule), and for
	 * 'max_parent' the parent's quantity.
	 *
	 * @param WC_Product           $addon  Add-on product.
	 * @param array<string, mixed> $parent_item Parent cart item.
	 *
	 * @return int
	 */
	public static function get_max_quantity( $addon, $parent_item ) {
		$max = a8csp_wcpa_get_addon_limit( $addon );
		$max = $max < 0 ? PHP_INT_MAX : $max;

		if ( 'max_parent' === a8csp_wcpa_get_quantity_mode( $addon ) ) {
			$max = min( $max, (int) $parent_item['quantity'] );
		}

		/**
		 * Filters the highest quantity of an add-on for a parent cart line.
		 *
		 * @param int                  $max    Maximum quantity.
		 * @param WC_Product           $addon  Add-on product.
		 * @param array<string, mixed> $parent_item Parent cart item.
		 */
		return max( 0, (int) apply_filters( 'a8csp_wcpa_addon_max_quantity', $max, $addon, $parent_item ) );
	}

	/**
	 * The cart key of an add-on under a parent line, or ''.
	 *
	 * @param string $parent_key Parent cart key.
	 * @param int    $addon_id   Add-on product ID.
	 *
	 * @return string
	 */
	public static function find_addon_key( $parent_key, $addon_id ) {
		foreach ( self::get_children( $parent_key ) as $key => $item ) {
			if ( (int) $item['product_id'] === (int) $addon_id ) {
				return (string) $key;
			}
		}

		return '';
	}

	/**
	 * The add-on lines of a parent line, keyed by cart key.
	 *
	 * @param string $parent_key Parent cart key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_children( $parent_key ) {
		$children = array();

		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( a8csp_wcpa_get_parent_key( $item ) === $parent_key ) {
				$children[ $key ] = $item;
			}
		}

		return $children;
	}

	/**
	 * Refuse add-ons added on their own (product forms, ?add-to-cart, Store API add-item).
	 *
	 * @param bool $passed     Whether validation passed.
	 * @param int  $product_id Product ID.
	 *
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id ) {
		if ( $passed && ! self::$adding && a8csp_wcpa_is_addon( (int) $product_id ) ) {
			wc_add_notice( __( 'This item can only be added as an extra to another item in your cart.', 'wc-product-addons' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Lines added by other code (for example "Order again") that are add-ons without a parent are removed;
	 * new add-on lines are moved under their parent.
	 *
	 * @param string               $cart_item_key  Cart key.
	 * @param int                  $product_id     Product ID.
	 * @param int                  $quantity       Quantity.
	 * @param int                  $variation_id   Variation ID.
	 * @param array                $variation      Variation attributes.
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 *
	 * @return void
	 */
	public static function after_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		unset( $quantity, $variation_id, $variation );

		if ( self::$adding || ! a8csp_wcpa_is_addon( (int) $product_id ) ) {
			return;
		}

		$parent_key = a8csp_wcpa_get_parent_key( $cart_item_data );
		if ( '' === $parent_key || array() === WC()->cart->get_cart_item( $parent_key ) ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		}
	}

	/**
	 * On every cart load: drop orphaned or no-longer-offered add-ons, apply caps, keep add-ons after their parent.
	 *
	 * @param WC_Cart $cart Cart.
	 *
	 * @return void
	 */
	public static function tidy( $cart ) {
		$contents = $cart->get_cart_contents();
		$changed  = false;

		foreach ( $contents as $key => $item ) {
			$is_addon   = isset( $item['data'] ) && a8csp_wcpa_is_addon( $item['data'] );
			$parent_key = a8csp_wcpa_get_parent_key( $item );

			if ( ! $is_addon && '' === $parent_key ) {
				continue;
			}

			$parent_item = '' !== $parent_key && isset( $contents[ $parent_key ] ) ? $contents[ $parent_key ] : null;
			$addon       = $parent_item ? self::get_offered_addon( $parent_item, $item['product_id'] ) : null;

			if ( ! $is_addon || ! $addon ) {
				unset( $contents[ $key ] );
				$changed = true;
				continue;
			}

			$max = self::get_max_quantity( $addon, $parent_item );
			if ( $item['quantity'] > $max ) {
				if ( 0 === $max ) {
					unset( $contents[ $key ] );
				} else {
					$contents[ $key ]['quantity'] = $max;
				}
				$changed = true;
			}
		}

		$sorted = self::sorted( $contents );

		if ( $changed || array_keys( $sorted ) !== array_keys( $cart->get_cart_contents() ) ) {
			$cart->set_cart_contents( $sorted );
		}
	}

	/**
	 * Sort the cart so each add-on line follows its parent line.
	 *
	 * @return void
	 */
	public static function sort() {
		$cart     = WC()->cart;
		$contents = $cart->get_cart_contents();
		$sorted   = self::sorted( $contents );

		if ( array_keys( $sorted ) !== array_keys( $contents ) ) {
			$cart->set_cart_contents( $sorted );
		}
	}

	/**
	 * Cart contents with each add-on after its parent (add-ons keep their relative order).
	 *
	 * @param array<string, array<string, mixed>> $contents Cart contents.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function sorted( $contents ) {
		$children = array();

		foreach ( $contents as $key => $item ) {
			$parent_key = a8csp_wcpa_get_parent_key( $item );
			if ( '' !== $parent_key && isset( $contents[ $parent_key ] ) ) {
				$children[ $parent_key ][ $key ] = $item;
			}
		}

		$sorted = array();

		foreach ( $contents as $key => $item ) {
			$parent_key = a8csp_wcpa_get_parent_key( $item );
			if ( '' !== $parent_key && isset( $contents[ $parent_key ] ) ) {
				continue;
			}

			$sorted[ $key ] = $item;
			if ( isset( $children[ $key ] ) ) {
				$sorted += $children[ $key ];
			}
		}

		return $sorted;
	}

	/**
	 * Removing a line removes its add-ons.
	 *
	 * @param string  $cart_item_key Removed cart key.
	 * @param WC_Cart $cart          Cart.
	 *
	 * @return void
	 */
	public static function remove_children( $cart_item_key, $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( a8csp_wcpa_get_parent_key( $item ) === $cart_item_key ) {
				$cart->remove_cart_item( $key );
			}
		}
	}

	/**
	 * Keep capped add-ons within their parent's quantity when either line changes.
	 *
	 * @param string  $cart_item_key Cart key.
	 * @param int     $quantity      New quantity.
	 * @param int     $old_quantity  Old quantity.
	 * @param WC_Cart $cart          Cart.
	 *
	 * @return void
	 */
	public static function after_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
		unset( $old_quantity );

		$item       = $cart->get_cart_item( $cart_item_key );
		$parent_key = a8csp_wcpa_get_parent_key( $item );

		// An add-on changed: clamp it.
		if ( '' !== $parent_key ) {
			$parent_item = $cart->get_cart_item( $parent_key );
			$addon       = array() !== $parent_item ? self::get_offered_addon( $parent_item, $item['product_id'] ) : null;

			if ( $addon instanceof WC_Product ) {
				$max = self::get_max_quantity( $addon, $parent_item );
				if ( $quantity > $max ) {
					$cart->set_quantity( $cart_item_key, $max, false );
				}
			}
			return;
		}

		// A parent changed: clamp its capped add-ons.
		foreach ( self::get_children( $cart_item_key ) as $key => $child ) {
			$addon = self::get_offered_addon( $item, $child['product_id'] );
			if ( $addon instanceof WC_Product && $child['quantity'] > self::get_max_quantity( $addon, $item ) ) {
				$cart->set_quantity( $key, self::get_max_quantity( $addon, $item ), false );
			}
		}
	}

	/**
	 * Add-on lines link to their parent line's product (add-on product pages are disabled).
	 *
	 * @param string               $permalink Product permalink.
	 * @param array<string, mixed> $cart_item Cart item.
	 *
	 * @return string
	 */
	public static function addon_permalink( $permalink, $cart_item ) {
		$parent_key = a8csp_wcpa_get_parent_key( $cart_item );
		if ( '' === $parent_key || ! WC()->cart ) {
			return $permalink;
		}

		$parent_item = WC()->cart->get_cart_item( $parent_key );
		if ( ! isset( $parent_item['data'] ) || ! $parent_item['data'] instanceof WC_Product ) {
			return $permalink;
		}

		return (string) apply_filters( 'woocommerce_cart_item_permalink', $parent_item['data']->get_permalink( $parent_item ), $parent_item, $parent_key );
	}

	/**
	 * Add-on lines: the add-on's short description as an item detail.
	 * The Cart block cuts product descriptions to a few words; item details are shown in full.
	 * The detail has the class `wc-block-components-product-details__description`.
	 *
	 * @param array<int, array<string, string>> $item_data Item details.
	 * @param array<string, mixed>              $cart_item Cart item.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function addon_description_item_data( $item_data, $cart_item ) {
		if ( '' === a8csp_wcpa_get_parent_key( $cart_item ) || ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $item_data;
		}

		$summary = a8csp_wcpa_get_addon_summary( $cart_item['data'] );

		/**
		 * Filters whether add-on lines show the add-on's text as an item detail (Cart and Checkout blocks).
		 *
		 * @param bool                 $show      Whether to show it.
		 * @param array<string, mixed> $cart_item Cart item.
		 */
		if ( '' !== $summary && apply_filters( 'a8csp_wcpa_show_description_item_data', true, $cart_item ) ) {
			$item_data[] = array(
				'key'   => 'Description',
				'value' => $summary,
			);
		}

		$details_link = self::details_link_item_data( $cart_item );
		if ( array() !== $details_link ) {
			$item_data[] = $details_link;
		}

		return $item_data;
	}

	/**
	 * Add-on lines: a details link as an item detail, for add-ons with a description. Off by default
	 * (`a8csp_wcpa_show_details_link_item_data`). In the Cart and Checkout blocks the link opens the
	 * description in a dialog (build/cart-checkout); it has no name, so only the link shows.
	 *
	 * @param array<string, mixed> $cart_item Add-on cart item.
	 *
	 * @return array<string, string> Item detail, or an empty array.
	 */
	private static function details_link_item_data( $cart_item ) {
		$addon = $cart_item['data'];

		/**
		 * Filters whether add-on lines show a details link as an item detail (Cart and Checkout blocks).
		 * Off by default; the link only shows for add-ons with a description.
		 *
		 * @param bool                 $show      Whether to show it.
		 * @param array<string, mixed> $cart_item Cart item.
		 */
		if ( empty( $cart_item['key'] ) || '' === a8csp_wcpa_get_addon_details( $addon ) || ! apply_filters( 'a8csp_wcpa_show_details_link_item_data', false, $cart_item ) ) {
			return array();
		}

		return array(
			'key'       => '',
			'name'      => '',
			'value'     => sprintf(
				'<a href="#a8csp-wcpa-details-%1$s" class="a8csp-wcpa-cart-details" title="%2$s">%3$s</a>',
				esc_attr( (string) $cart_item['key'] ),
				esc_attr( a8csp_wcpa_get_details_aria_label( $addon ) ),
				esc_html( a8csp_wcpa_get_details_label( $addon ) )
			),
			'className' => 'wc-block-components-product-details__a8csp-wcpa-details',
		);
	}
}
