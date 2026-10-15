/**
 * Every cart write made by the add-ons block goes through here, so the transport can change in one place
 * (for example to WooCommerce's own cart store if it becomes public).
 *
 * WooCommerce's Interactivity API cart store is private, so the block calls the Store API itself, then
 * fires the events WooCommerce's mini-cart listens to so it re-reads the cart (totals, count, badge).
 */
import { getConfig } from '@wordpress/interactivity';

const NAMESPACE = 'a8csp/wc-product-addons';

let nonce = null;

/**
 * POST to the Store API with its nonce, retrying once with a fresh nonce if the cached one expired.
 *
 * @param {string}  path    Path below wc/store/v1/.
 * @param {Object}  body    JSON body.
 * @param {boolean} isRetry Whether this is the retry.
 * @return {Promise<Object>} Response JSON.
 */
async function post( path, body, isRetry = false ) {
	const config = getConfig( NAMESPACE );
	nonce = nonce || config.nonce;

	const response = await fetch( `${ config.restUrl }${ path }`, {
		method: 'POST',
		credentials: 'include',
		cache: 'no-store',
		headers: {
			'Content-Type': 'application/json',
			Nonce: nonce,
		},
		body: JSON.stringify( body ),
	} );

	nonce = response.headers.get( 'Nonce' ) || nonce;

	const json = await response.json().catch( () => ( {} ) );

	if ( ! response.ok ) {
		if ( ! isRetry && json?.code === 'woocommerce_rest_invalid_nonce' ) {
			return post( path, body, true );
		}

		// Store API messages are HTML-escaped; the block shows them as text.
		const message = json?.message
			? new window.DOMParser().parseFromString(
					json.message,
					'text/html'
				).body.textContent
			: '';
		const error = new Error( message || config.genericError );
		error.code = json?.code;
		throw error;
	}

	return json;
}

/**
 * Tell WooCommerce's mini-cart (and any React cart block on the page) to re-read the cart.
 *
 * @param {boolean} added Whether the change added items (true) or removed them (false).
 */
export function notifyCartChanged( added ) {
	document.body.dispatchEvent(
		new CustomEvent(
			added ? 'wc-blocks_added_to_cart' : 'wc-blocks_removed_from_cart',
			{
				bubbles: true,
				cancelable: true,
				detail: { preserveCartData: false },
			}
		)
	);
}

/**
 * Set an add-on's quantity under a cart line (0 removes it). The caller tells WooCommerce to
 * re-read the cart (notifyCartChanged) once a group of changes is done.
 *
 * @param {Object} change           The change.
 * @param {string} change.parentKey Parent cart item key.
 * @param {number} change.addonId   Add-on product ID.
 * @param {number} change.quantity  New quantity.
 * @return {Promise<Object>} The updated cart.
 */
export function setAddonQuantity( { parentKey, addonId, quantity } ) {
	return post( 'cart/extensions', {
		namespace: 'a8csp-wcpa',
		data: {
			items: [ { parent_key: parentKey, addon_id: addonId, quantity } ],
		},
	} );
}
