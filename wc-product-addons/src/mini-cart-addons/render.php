<?php
/**
 * Mini-Cart products table with add-ons.
 *
 * Renders WooCommerce's own Mini-Cart Products Table block, so product lines keep WooCommerce's
 * markup, quantity selectors and styles, then adds an add-ons cell to each line's row template.
 * Add-on lines are hidden as rows and shown under their parent line instead.
 *
 * @package A8csp
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$a8csp_wcpa_namespace = 'a8csp/wc-product-addons';

$a8csp_wcpa_table = render_block(
	array(
		'blockName'    => 'woocommerce/mini-cart-products-table-block',
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	)
);

/**
 * Position of the row template's closing </tr>: the first one after the `data-wp-each` template
 * (the row holds no nested rows, only nested templates for item details).
 *
 * @param string $html Table HTML.
 *
 * @return int|false
 */
$a8csp_wcpa_find_row_end = static function ( $html ) {
	$each = strpos( $html, 'data-wp-each--cart-item' );
	return false === $each ? false : strpos( $html, '</tr>', $each );
};

$a8csp_wcpa_row_end = $a8csp_wcpa_find_row_end( $a8csp_wcpa_table );

// WooCommerce changed its markup: show the plain table rather than break the mini-cart.
if ( false === $a8csp_wcpa_row_end ) {
	echo $a8csp_wcpa_table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core block output.
	return;
}

wp_interactivity_config(
	$a8csp_wcpa_namespace,
	array(
		'restUrl'       => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
		'nonce'         => wp_create_nonce( 'wc_store_api' ),
		/* translators: %s: add-on name. */
		'addLabel'      => __( 'Add %s', 'wc-product-addons' ),
		/* translators: %s: add-on name. */
		'reduceLabel'   => __( 'Reduce quantity of %s', 'wc-product-addons' ),
		/* translators: %s: add-on name. */
		'increaseLabel' => __( 'Increase quantity of %s', 'wc-product-addons' ),
		/* translators: %s: add-on name. */
		'quantityLabel' => __( 'Quantity of %s in your cart.', 'wc-product-addons' ),
		/* translators: %s: add-on name. */
		'removeLabel'   => __( 'Remove %s from cart', 'wc-product-addons' ),
		/* translators: %s: add-on name. */
		'detailsLabel'  => apply_filters( 'a8csp_wcpa_details_label', __( 'Details for %s', 'wc-product-addons' ) ),
		'genericError'  => __( 'Something went wrong. Please try again.', 'wc-product-addons' ),
		'debounceMs'    => 400,
	)
);

/*
 * Server versions of the state the add-ons markup needs, so rows WooCommerce renders on the server
 * include their add-ons (the browser then takes them over instead of redrawing them). They read the
 * row's `cartItem` from WooCommerce's context; everything else is filled in by view.js.
 */
$a8csp_wcpa_cart_item = static function () {
	$context = wp_interactivity_get_context( 'woocommerce' );
	$item    = $context['cartItem'] ?? array();

	// The cart state comes from a Store API response: nested values may still be objects.
	$item = json_decode( (string) wp_json_encode( $item ), true );

	return is_array( $item ) ? $item : array();
};

$a8csp_wcpa_addon = static function () {
	$context = wp_interactivity_get_context( 'a8csp/wc-product-addons' );

	return is_array( $context['addon'] ?? null ) ? $context['addon'] : array();
};

wp_interactivity_state(
	$a8csp_wcpa_namespace,
	array(
		'addons'       => static function () use ( $a8csp_wcpa_cart_item ) {
			$item = $a8csp_wcpa_cart_item();

			return array_values( (array) ( $item['extensions']['a8csp_wcpa']['addons'] ?? array() ) );
		},
		'hasAddons'    => static function () use ( $a8csp_wcpa_cart_item ) {
			$item = $a8csp_wcpa_cart_item();

			return ! empty( $item['extensions']['a8csp_wcpa']['addons'] );
		},
		'isAddonRow'   => static function () use ( $a8csp_wcpa_cart_item ) {
			$item = $a8csp_wcpa_cart_item();

			return ! empty( $item['extensions']['a8csp_wcpa']['is_addon'] );
		},
		'rowKey'       => static function () use ( $a8csp_wcpa_cart_item ) {
			$item = $a8csp_wcpa_cart_item();

			return (string) ( $item['key'] ?? '' );
		},
		'rowProductId' => static function () use ( $a8csp_wcpa_cart_item ) {
			$item = $a8csp_wcpa_cart_item();

			return (int) ( $item['id'] ?? 0 );
		},
		'quantity'     => static function () use ( $a8csp_wcpa_addon ) {
			$addon = $a8csp_wcpa_addon();

			return (int) ( $addon['in_cart']['quantity'] ?? 0 );
		},
		'isAdded'      => static function () use ( $a8csp_wcpa_addon ) {
			$addon = $a8csp_wcpa_addon();

			return (int) ( $addon['in_cart']['quantity'] ?? 0 ) > 0;
		},
	)
);

// Directives on WooCommerce's row template (cross-namespace): hide add-on lines' own rows, mark lines with add-ons.
$a8csp_wcpa_rows = new WP_HTML_Tag_Processor( $a8csp_wcpa_table );
if ( $a8csp_wcpa_rows->next_tag(
	array(
		'tag_name'   => 'tr',
		'class_name' => 'wc-block-cart-items__row',
	)
) ) {
	$a8csp_wcpa_rows->set_attribute( 'data-wp-class--a8csp-wcpa-is-addon', $a8csp_wcpa_namespace . '::state.isAddonRow' );
	$a8csp_wcpa_rows->set_attribute( 'data-wp-class--a8csp-wcpa-has-addons', $a8csp_wcpa_namespace . '::state.hasAddons' );
	// Which cart line a row shows, so scripts can find it (e.g. scroll to a line just added).
	$a8csp_wcpa_rows->set_attribute( 'data-wp-bind--data-cart-item-key', $a8csp_wcpa_namespace . '::state.rowKey' );
	$a8csp_wcpa_rows->set_attribute( 'data-wp-bind--data-product-id', $a8csp_wcpa_namespace . '::state.rowProductId' );
	$a8csp_wcpa_table = $a8csp_wcpa_rows->get_updated_html();

	// WooCommerce's prices in the row (unit, regular, line total, savings) use the split price markup.
	// Prices not found keep WooCommerce's own text.
	$a8csp_wcpa_money = array(
		'itemPrice'            => 'unit',
		'priceWithoutDiscount' => 'regular',
		'lineItemTotal'        => 'lineTotal',
		'lineItemDiscount'     => 'discount',
	);
	$a8csp_wcpa_table = (string) preg_replace_callback(
		'#(<(span|del|ins)\b[^>]*?)\sdata-wp-text="state\.(itemPrice|priceWithoutDiscount|lineItemTotal|lineItemDiscount)"([^>]*>)\s*(</\2>)#',
		static function ( $matches ) use ( $a8csp_wcpa_money ) {
			return $matches[1] . $matches[4] . a8csp_wcpa_price_markup( $a8csp_wcpa_money[ $matches[3] ] ) . $matches[5];
		},
		$a8csp_wcpa_table
	);

	// Positions moved: find the row end again.
	$a8csp_wcpa_row_end = $a8csp_wcpa_find_row_end( $a8csp_wcpa_table );
}

$a8csp_wcpa_trash = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 5.5A2.25 2.25 0 0 0 9.878 7h4.244A2.251 2.251 0 0 0 12 5.5ZM12 4a3.751 3.751 0 0 0-3.675 3H5v1.5h1.27l.818 8.997a2.75 2.75 0 0 0 2.739 2.501h4.347a2.75 2.75 0 0 0 2.738-2.5L17.73 8.5H19V7h-3.325A3.751 3.751 0 0 0 12 4Zm4.224 4.5H7.776l.806 8.861a1.25 1.25 0 0 0 1.245 1.137h4.347a1.25 1.25 0 0 0 1.245-1.137l.805-8.861Z"/></svg>';

ob_start();
/*
 * The cell is not its own `data-wp-interactive` region: it must stay inside WooCommerce's row so it
 * gets the row's `cartItem` context. Directives name this plugin's namespace instead.
 */
?>
<td class="a8csp-wcpa-mini-cart__addons" data-wp-bind--hidden="a8csp/wc-product-addons::!state.hasAddons">
	<div class="a8csp-wcpa-addons">
		<p class="a8csp-wcpa-addons__label"><?php esc_html_e( 'Add-ons', 'wc-product-addons' ); ?></p>
		<ul class="a8csp-wcpa-addons__list">
			<template data-wp-each--addon="a8csp/wc-product-addons::state.addons" data-wp-each-key="a8csp/wc-product-addons::context.addon.id">
				<li
					class="a8csp-wcpa-addon"
					data-wp-class--is-added="a8csp/wc-product-addons::state.isAdded"
					data-wp-class--is-offer="a8csp/wc-product-addons::!state.isAdded"
					data-wp-class--is-busy="a8csp/wc-product-addons::state.isBusy"
					data-wp-bind--aria-busy="a8csp/wc-product-addons::state.isBusy"
					data-wp-watch="a8csp/wc-product-addons::callbacks.syncPending"
				>
					<div class="a8csp-wcpa-addon__header">
						<span class="a8csp-wcpa-addon__name" data-wp-text="a8csp/wc-product-addons::context.addon.name"></span>
						<span class="a8csp-wcpa-addon__price"><?php echo a8csp_wcpa_price_markup( 'addonUnit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped markup. ?></span>
					</div>
					<p class="a8csp-wcpa-addon__description" data-wp-text="a8csp/wc-product-addons::context.addon.short_description" data-wp-bind--hidden="a8csp/wc-product-addons::!context.addon.short_description"></p>
					<button type="button" class="a8csp-wcpa-addon__details" data-wp-bind--hidden="a8csp/wc-product-addons::!context.addon.has_details" data-wp-bind--aria-label="a8csp/wc-product-addons::state.detailsLabel" data-wp-on--click="a8csp/wc-product-addons::actions.openDetails">
						<?php echo apply_filters( 'a8csp_wcpa_details_label', esc_html__( 'Details', 'wc-product-addons' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
					</button>
					<div class="a8csp-wcpa-addon__actions">
						<button type="button" class="a8csp-wcpa-addon__add wp-element-button" data-wp-bind--hidden="a8csp/wc-product-addons::state.isAdded" data-wp-bind--aria-label="a8csp/wc-product-addons::state.addLabel" data-wp-on--click="a8csp/wc-product-addons::actions.add">
							<?php esc_html_e( 'Add', 'wc-product-addons' ); ?>
						</button>
						<div class="a8csp-wcpa-addon__quantity wc-block-components-quantity-selector" data-wp-bind--hidden="a8csp/wc-product-addons::!state.isAdded">
							<button type="button" class="wc-block-components-quantity-selector__button wc-block-components-quantity-selector__button--minus" data-wp-bind--aria-label="a8csp/wc-product-addons::state.reduceLabel" data-wp-on--click="a8csp/wc-product-addons::actions.decrement">&minus;</button>
							<input class="wc-block-components-quantity-selector__input" name="a8csp_wcpa_quantity" type="number" step="1" min="0" data-wp-bind--max="a8csp/wc-product-addons::state.max" data-wp-bind--value="a8csp/wc-product-addons::state.quantity" data-wp-bind--aria-label="a8csp/wc-product-addons::state.quantityLabel" data-wp-on--change="a8csp/wc-product-addons::actions.changeQuantity">
							<button type="button" class="wc-block-components-quantity-selector__button wc-block-components-quantity-selector__button--plus" data-wp-bind--disabled="a8csp/wc-product-addons::state.maximumReached" data-wp-bind--aria-label="a8csp/wc-product-addons::state.increaseLabel" data-wp-on--click="a8csp/wc-product-addons::actions.increment">+</button>
						</div>
						<span class="a8csp-wcpa-addon__total" data-wp-bind--hidden="a8csp/wc-product-addons::!state.isAdded"><?php echo a8csp_wcpa_price_markup( 'addonTotal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped markup. ?></span>
						<button type="button" class="a8csp-wcpa-addon__remove" data-wp-bind--hidden="a8csp/wc-product-addons::!state.isAdded" data-wp-bind--aria-label="a8csp/wc-product-addons::state.removeLabel" data-wp-on--click="a8csp/wc-product-addons::actions.remove">
							<?php echo $a8csp_wcpa_trash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
						</button>
					</div>
				</li>
			</template>
		</ul>
		<p class="a8csp-wcpa-addons__error" role="alert" data-wp-bind--hidden="a8csp/wc-product-addons::!state.error" data-wp-text="a8csp/wc-product-addons::state.error"></p>
	</div>
</td>
<?php
$a8csp_wcpa_cell = ob_get_clean();

$a8csp_wcpa_table = substr_replace( $a8csp_wcpa_table, $a8csp_wcpa_cell, $a8csp_wcpa_row_end, 0 );

ob_start();
?>
<dialog class="a8csp-wcpa-details" aria-labelledby="a8csp-wcpa-details-title" data-wp-interactive="<?php echo esc_attr( $a8csp_wcpa_namespace ); ?>" data-wp-on--click="actions.closeDetailsOnBackdrop" data-wp-on--keydown="actions.detailsKeydown" data-wp-on--close="actions.detailsClosed">
	<div class="a8csp-wcpa-details__header">
		<h2 id="a8csp-wcpa-details-title" class="a8csp-wcpa-details__title" data-wp-text="state.details.title"></h2>
		<button type="button" class="a8csp-wcpa-details__close" data-wp-on--click="actions.closeDetails" aria-label="<?php esc_attr_e( 'Close', 'wc-product-addons' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="currentColor"><path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"/></svg>
		</button>
	</div>
	<div class="a8csp-wcpa-details__content" data-wp-watch="callbacks.renderDetails"></div>
</dialog>
<?php
$a8csp_wcpa_dialog = ob_get_clean();

printf(
	'<div %1$s>%2$s%3$s</div>',
	get_block_wrapper_attributes( array( 'class' => 'a8csp-wcpa-mini-cart' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core.
	$a8csp_wcpa_table, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core block output.
	$a8csp_wcpa_dialog // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
