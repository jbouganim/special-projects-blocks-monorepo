<?php
/**
 * Public helpers. Safe to call from themes and other plugins.
 *
 * @package A8csp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product meta: 'yes' when the product is an add-on.
 */
const A8CSP_WCPA_META_IS_ADDON = '_a8csp_wcpa_is_addon';

/**
 * Product meta: an add-on's quantity rule ('independent', 'max_parent' or 'fixed').
 */
const A8CSP_WCPA_META_QUANTITY = '_a8csp_wcpa_quantity_mode';

/**
 * Product meta: the highest quantity per line when the quantity rule is 'fixed'.
 */
const A8CSP_WCPA_META_QUANTITY_LIMIT = '_a8csp_wcpa_quantity_limit';

/**
 * Product / variation meta: IDs of the add-ons offered with it.
 */
const A8CSP_WCPA_META_ADDONS = '_a8csp_wcpa_addons';

/**
 * Variation meta: 'yes' when the variation offers no add-ons, not even its product's.
 */
const A8CSP_WCPA_META_NO_ADDONS = '_a8csp_wcpa_no_addons';

/**
 * Product meta: the text of an add-on's details link (empty for the default, "Details").
 */
const A8CSP_WCPA_META_DETAILS_LABEL = '_a8csp_wcpa_details_label';

/**
 * Cart item data key holding `parent_key` on add-on lines.
 */
const A8CSP_WCPA_CART_KEY = 'a8csp_wcpa';

/**
 * Whether a product is an add-on.
 *
 * @param WC_Product|int|null $product Product or ID.
 *
 * @return bool
 */
function a8csp_wcpa_is_addon( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	$is      = $product instanceof WC_Product && $product->is_type( 'simple' ) && 'yes' === $product->get_meta( A8CSP_WCPA_META_IS_ADDON );

	/**
	 * Filters whether a product is treated as an add-on.
	 *
	 * @param bool            $is      Whether it is an add-on.
	 * @param WC_Product|null $product Product.
	 */
	return (bool) apply_filters( 'a8csp_wcpa_is_addon', $is, $product );
}

/**
 * An add-on's quantity rule: 'independent' (any quantity), 'max_parent' (no more than its parent
 * line) or 'fixed' (no more than a set number per line, see a8csp_wcpa_get_quantity_limit()).
 *
 * @param WC_Product|int $addon Add-on product or ID.
 *
 * @return string
 */
function a8csp_wcpa_get_quantity_mode( $addon ) {
	$addon = is_numeric( $addon ) ? wc_get_product( $addon ) : $addon;
	$mode  = $addon instanceof WC_Product ? (string) $addon->get_meta( A8CSP_WCPA_META_QUANTITY ) : '';

	return in_array( $mode, array( 'max_parent', 'fixed' ), true ) ? $mode : 'independent';
}

/**
 * The set number of an add-on with the 'fixed' quantity rule (at least 1).
 *
 * @param WC_Product|int $addon Add-on product or ID.
 *
 * @return int
 */
function a8csp_wcpa_get_quantity_limit( $addon ) {
	$addon = is_numeric( $addon ) ? wc_get_product( $addon ) : $addon;

	return $addon instanceof WC_Product ? max( 1, absint( $addon->get_meta( A8CSP_WCPA_META_QUANTITY_LIMIT ) ) ) : 1;
}

/**
 * The most of an add-on one line can have, before any cap by the line's quantity: its stock (or
 * sold individually) and, with the 'fixed' rule, the set number. -1 when there is no limit.
 *
 * @param WC_Product $addon Add-on product.
 *
 * @return int
 */
function a8csp_wcpa_get_addon_limit( $addon ) {
	$max = (int) $addon->get_max_purchase_quantity();

	if ( 'fixed' === a8csp_wcpa_get_quantity_mode( $addon ) ) {
		$limit = a8csp_wcpa_get_quantity_limit( $addon );
		$max   = $max < 0 ? $limit : min( $max, $limit );
	}

	return $max;
}

/**
 * IDs of the add-ons offered with a product. A variation with its own list uses it; a variation set
 * to "No add-ons" offers none; otherwise it inherits its parent product's list.
 *
 * @param WC_Product|int $product Product, variation or ID.
 *
 * @return int[]
 */
function a8csp_wcpa_get_addon_ids( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$no_addons = 'yes' === $product->get_meta( A8CSP_WCPA_META_NO_ADDONS );
	$ids       = $no_addons ? array() : array_filter( array_map( 'absint', (array) $product->get_meta( A8CSP_WCPA_META_ADDONS ) ) );

	if ( ! $no_addons && array() === $ids && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );
		$ids    = $parent instanceof WC_Product ? array_filter( array_map( 'absint', (array) $parent->get_meta( A8CSP_WCPA_META_ADDONS ) ) ) : array();
	}

	/**
	 * Filters the add-on IDs offered with a product or variation.
	 *
	 * @param int[]      $ids     Add-on product IDs, in display order.
	 * @param WC_Product $product Product or variation.
	 */
	return array_values( array_unique( array_map( 'absint', (array) apply_filters( 'a8csp_wcpa_addon_ids', array_values( $ids ), $product ) ) ) );
}

/**
 * The add-on products offered with a product: published, purchasable add-ons only.
 *
 * @param WC_Product|int $product Product, variation or ID.
 *
 * @return WC_Product[]
 */
function a8csp_wcpa_get_addons( $product ) {
	$addons = array();

	foreach ( a8csp_wcpa_get_addon_ids( $product ) as $id ) {
		$addon = wc_get_product( $id );

		if ( $addon instanceof WC_Product && 'publish' === $addon->get_status() && a8csp_wcpa_is_addon( $addon ) && $addon->is_purchasable() && $addon->is_in_stock() ) {
			$addons[] = $addon;
		}
	}

	/**
	 * Filters the add-on products offered with a product.
	 *
	 * @param WC_Product[]       $addons  Add-on products.
	 * @param WC_Product|int     $product Product, variation or ID.
	 */
	return (array) apply_filters( 'a8csp_wcpa_addons', $addons, $product );
}

/**
 * The parent cart key of a cart line, or '' for a line that isn't an add-on.
 *
 * @param array<string, mixed> $cart_item Cart item.
 *
 * @return string
 */
function a8csp_wcpa_get_parent_key( $cart_item ) {
	return isset( $cart_item[ A8CSP_WCPA_CART_KEY ]['parent_key'] ) ? (string) $cart_item[ A8CSP_WCPA_CART_KEY ]['parent_key'] : '';
}

/**
 * The add-on lines of an order line.
 *
 * @param WC_Order_Item_Product $item Parent order line.
 *
 * @return WC_Order_Item_Product[]
 */
function a8csp_wcpa_get_order_item_addons( $item ) {
	$order = $item instanceof WC_Order_Item ? $item->get_order() : null;

	if ( ! $order instanceof WC_Order ) {
		return array();
	}

	$addons = array();

	foreach ( $order->get_items() as $line ) {
		if ( $line instanceof WC_Order_Item_Product && (int) $line->get_meta( '_a8csp_wcpa_parent_item_id' ) === (int) $item->get_id() ) {
			$addons[] = $line;
		}
	}

	return $addons;
}

/**
 * HTML to plain text (entities decoded), for text shown with `data-wp-text` or as cart item data.
 *
 * @param string $html HTML.
 *
 * @return string
 */
function a8csp_wcpa_plain_text( $html ) {
	return trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
}

/**
 * The text shown with an add-on: its short description (plain text), or ''. The description is
 * not used as a fallback; it goes behind the "Details" link (a8csp_wcpa_get_addon_details()).
 *
 * @param WC_Product $addon Add-on product.
 *
 * @return string
 */
function a8csp_wcpa_get_addon_summary( $addon ) {
	return a8csp_wcpa_plain_text( $addon->get_short_description() );
}

/**
 * The add-on's "details": its description as HTML for the "Details" dialog, or '' when it has
 * none. Independent of the short description (shown with the add-on).
 *
 * @param WC_Product $addon Add-on product.
 *
 * @return string
 */
function a8csp_wcpa_get_addon_details( $addon ) {
	if ( '' === a8csp_wcpa_plain_text( $addon->get_description() ) ) {
		return '';
	}

	return wc_format_content( wp_kses_post( $addon->get_description() ) );
}

/**
 * The text of an add-on's details link: its own (Add-on settings), or "Details".
 *
 * @param WC_Product $addon Add-on product.
 *
 * @return string
 */
function a8csp_wcpa_get_details_label( $addon ) {
	$label = $addon instanceof WC_Product ? trim( (string) $addon->get_meta( A8CSP_WCPA_META_DETAILS_LABEL ) ) : '';

	/**
	 * Filters the text of an add-on's details link (mini-cart and Cart block).
	 *
	 * @param string          $label Link text.
	 * @param WC_Product|null $addon Add-on product.
	 */
	return (string) apply_filters( 'a8csp_wcpa_details_label', '' !== $label ? $label : __( 'Details', 'wc-product-addons' ), $addon );
}

/**
 * The accessible name of an add-on's details link, e.g. "Details for Prix fixe menu".
 *
 * @param WC_Product $addon Add-on product.
 *
 * @return string
 */
function a8csp_wcpa_get_details_aria_label( $addon ) {
	/* translators: 1: details link text, 2: add-on name. */
	$label = sprintf( __( '%1$s for %2$s', 'wc-product-addons' ), a8csp_wcpa_get_details_label( $addon ), a8csp_wcpa_plain_text( $addon->get_name() ) );

	/**
	 * Filters the accessible name of an add-on's details link.
	 *
	 * @param string     $label Accessible name.
	 * @param WC_Product $addon Add-on product.
	 */
	return (string) apply_filters( 'a8csp_wcpa_details_aria_label', $label, $addon );
}

/**
 * Price markup for the mini-cart, filled in the browser (Interactivity API, inside a cart row):
 *
 *     <span class="a8csp-wcpa-price">
 *       <span class="a8csp-wcpa-price__prefix">$</span><span class="a8csp-wcpa-price__integer">13</span>
 *       <span class="a8csp-wcpa-price__decimals is-zero">.00</span><span class="a8csp-wcpa-price__suffix"></span>
 *     </span>
 *
 * The decimals part holds the separator and digits, follows WooCommerce's decimals setting (hidden
 * with 0 decimals), and has `is-zero` when the digits are all zeros, so themes can hide ".00" only.
 *
 * @param string $money Which amount: 'unit', 'regular', 'discount', 'lineTotal' (the cart line),
 *                      'addonUnit' or 'addonTotal' (inside an add-on).
 *
 * @return string
 */
function a8csp_wcpa_price_markup( $money ) {
	$ns = 'a8csp/wc-product-addons::';

	return sprintf(
		'<span class="a8csp-wcpa-price" data-wp-context="%1$s">'
		. '<span class="a8csp-wcpa-price__prefix" data-wp-text="%2$sstate.moneyPrefix"></span>'
		. '<span class="a8csp-wcpa-price__integer" data-wp-text="%2$sstate.moneyInteger"></span>'
		. '<span class="a8csp-wcpa-price__decimals" data-wp-class--is-zero="%2$sstate.moneyIsZero" data-wp-bind--hidden="%2$s!state.moneyDecimals" data-wp-text="%2$sstate.moneyDecimals"></span>'
		. '<span class="a8csp-wcpa-price__suffix" data-wp-text="%2$sstate.moneySuffix"></span>'
		. '</span>',
		esc_attr( $ns . wp_json_encode( array( 'money' => $money ) ) ),
		esc_attr( $ns )
	);
}
