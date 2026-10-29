/**
 * Cart and Checkout blocks: the details link on add-on lines (see `a8csp_wcpa_show_details_link_item_data`)
 * opens the add-on's description in a dialog, like the mini-cart's.
 *
 * The link is an item detail, so WooCommerce renders it; clicks are handled here, on the document.
 * Its `href` names the add-on's cart key; the description comes from the cart data already loaded
 * (the parent line's `extensions.a8csp_wcpa.addons`), so the dialog opens at once.
 */
import { __ } from '@wordpress/i18n';

const HASH_PREFIX = '#a8csp-wcpa-details-';

let dialog = null;

/**
 * The add-on data (as offered with its parent line) of an add-on cart line.
 *
 * @param {string} key Add-on cart key.
 * @return {Object|undefined} Add-on data.
 */
const findAddon = ( key ) => {
	// WooCommerce's cart data store (wp-data is a dependency of this script, see Blocks_Integration).
	const items =
		window.wp?.data?.select( 'wc/store/cart' )?.getCartData()?.items || [];
	const line = items.find( ( item ) => item.key === key );
	const parentKey = line?.extensions?.a8csp_wcpa?.parent_key;
	const parent = items.find( ( item ) => item.key === parentKey );

	return parent?.extensions?.a8csp_wcpa?.addons?.find(
		( addon ) => addon.id === line.id
	);
};

/**
 * The dialog, created on first use. Same markup and classes as the mini-cart's, so themes style both.
 *
 * @return {HTMLDialogElement} Dialog.
 */
const getDialog = () => {
	if ( dialog ) {
		return dialog;
	}

	dialog = document.createElement( 'dialog' );
	dialog.className = 'a8csp-wcpa-details';
	dialog.setAttribute( 'aria-labelledby', 'a8csp-wcpa-cart-details-title' );
	dialog.innerHTML = `
		<div class="a8csp-wcpa-details__header">
			<h2 id="a8csp-wcpa-cart-details-title" class="a8csp-wcpa-details__title"></h2>
			<button type="button" class="a8csp-wcpa-details__close">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="currentColor"><path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"/></svg>
			</button>
		</div>
		<div class="a8csp-wcpa-details__content"></div>`;

	dialog
		.querySelector( '.a8csp-wcpa-details__close' )
		.setAttribute( 'aria-label', __( 'Close', 'wc-product-addons' ) );

	dialog.addEventListener( 'click', ( event ) => {
		// Clicks on the dialog itself (not its content) are on the backdrop.
		if (
			event.target === dialog ||
			event.target.closest( '.a8csp-wcpa-details__close' )
		) {
			dialog.close();
		}
	} );

	dialog.addEventListener( 'close', () => {
		dialog.querySelector( '.a8csp-wcpa-details__content' ).innerHTML = '';
	} );

	document.body.appendChild( dialog );

	return dialog;
};

document.addEventListener( 'click', ( event ) => {
	const link = event.target.closest?.( 'a.a8csp-wcpa-cart-details' );
	const hash = link?.getAttribute( 'href' ) || '';

	if ( ! hash.startsWith( HASH_PREFIX ) ) {
		return;
	}

	const addon = findAddon( hash.slice( HASH_PREFIX.length ) );

	if ( ! addon?.details ) {
		return;
	}

	event.preventDefault();

	const details = getDialog();
	details.querySelector( '.a8csp-wcpa-details__title' ).textContent =
		addon.name;
	// Add-on description HTML from the cart data (run through wp_kses_post on the server).
	details.querySelector( '.a8csp-wcpa-details__content' ).innerHTML =
		addon.details;
	details.showModal();
} );
