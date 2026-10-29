/**
 * Add-ons under each Mini-Cart line: Add button, then a quantity selector.
 *
 * Quantity clicks on the same add-on are grouped: the number changes at once, and one request is sent
 * with the final quantity about 400 ms after the last click. A request is never sent twice in
 * parallel for the same add-on; a newer value waits and is sent next. "Add" and the trash button send
 * right away. A failed request puts the quantity back and shows the error under the line.
 */
import {
	store,
	getContext,
	getConfig,
	getElement,
} from '@wordpress/interactivity';

import { setAddonQuantity, notifyCartChanged } from './cart-api';

const NAMESPACE = 'a8csp/wc-product-addons';

const timers = new Map();

// Last quantity the server accepted, by "parentKey:addonId", while a change is being made.
const confirmed = new Map();

// Directions of the changes sent since WooCommerce last re-read the cart ('added', 'removed').
const changed = new Set();

let retryTimer;

/**
 * Whether an add-on has a quantity waiting for the server. Read by property (not `in`), so getters
 * that use it update when it changes.
 *
 * @param {string} key "parentKey:addonId".
 * @return {boolean} Whether there is a pending quantity.
 */
const hasPending = ( key ) => state.pending[ key ] !== undefined;
const inFlight = new Set();

/**
 * Whether any add-on still shows a quantity the cart hasn't caught up with.
 *
 * @return {boolean} Whether there are pending quantities.
 */
const hasAnyPending = () =>
	Object.values( state.pending ).some( ( value ) => value !== undefined );

/**
 * The cart line this element belongs to (WooCommerce's `data-wp-each` context).
 *
 * @return {Object|undefined} Cart item.
 */
const getCartItem = () => getContext( 'woocommerce' )?.cartItem;

/**
 * Key for an add-on under a line.
 *
 * @param {string} parentKey Parent cart key.
 * @param {number} addonId   Add-on ID.
 * @return {string} Key.
 */
const keyOf = ( parentKey, addonId ) => `${ parentKey }:${ addonId }`;

/**
 * "%s" label with the add-on name.
 *
 * @param {string} name Label config key.
 * @return {string} Label.
 */
const label = ( name ) => {
	const { addon } = getContext( NAMESPACE );
	return ( getConfig( NAMESPACE )[ name ] || '' ).replace(
		'%s',
		addon?.name || ''
	);
};

/**
 * Split an amount into the parts of the price markup, formatted like the cart line's own prices
 * (currency, separators and decimals from the Store API, i.e. WooCommerce's settings).
 *
 * @param {number} amount Amount in the currency's minor unit.
 * @param {Object} prices Store API prices object (currency settings).
 * @return {Object} { prefix, integer, decimals, isZero, suffix }. `decimals` includes the separator, '' with no decimals.
 */
const priceParts = ( amount, prices = {} ) => {
	const minorUnit = prices.currency_minor_unit ?? 2;
	const [ whole, fraction = '' ] = ( amount / 10 ** minorUnit )
		.toFixed( minorUnit )
		.split( '.' );

	return {
		prefix: prices.currency_prefix ?? '',
		integer: whole.replace(
			/\B(?=(\d{3})+(?!\d))/g,
			prices.currency_thousand_separator ?? ','
		),
		decimals: fraction
			? `${ prices.currency_decimal_separator ?? '.' }${ fraction }`
			: '',
		isZero: fraction !== '' && /^0+$/.test( fraction ),
		suffix: prices.currency_suffix ?? '',
	};
};

/**
 * Convert a Store API raw price (higher precision) to the currency's minor unit, like WooCommerce.
 *
 * @param {string|number} price     Raw price.
 * @param {number}        precision Raw price precision.
 * @param {number}        minorUnit Currency minor unit.
 * @return {number} Amount in minor units.
 */
const fromRaw = ( price, precision, minorUnit ) =>
	Math.round( parseInt( price, 10 ) * 10 ** ( minorUnit - precision ) );

/**
 * Amount for the price markup's context key (`data-wp-context` `{ "money": "…" }`), in minor units.
 * The cart line amounts follow WooCommerce's mini-cart (raw prices, line subtotal with or without tax).
 *
 * @param {string} key Amount key.
 * @return {number|null} Amount, or null when it doesn't apply.
 */
const moneyAmount = ( key ) => {
	const cartItem = getCartItem();

	if ( ! cartItem?.prices ) {
		return null;
	}

	const prices = cartItem.prices;
	const minorUnit = prices.currency_minor_unit ?? 2;
	const raw = prices.raw_prices || {};
	const unit = () => fromRaw( raw.price, raw.precision, minorUnit );
	const regular = () =>
		fromRaw( raw.regular_price, raw.precision, minorUnit );

	switch ( key ) {
		case 'unit':
			return unit();
		case 'regular':
			return regular();
		case 'discount':
			return ( regular() - unit() ) * ( cartItem.quantity || 0 );
		case 'lineTotal': {
			const totals = cartItem.totals || {};
			const withTax = getConfig(
				'woocommerce/mini-cart'
			)?.displayCartPriceIncludingTax;
			return (
				parseInt( totals.line_subtotal, 10 ) +
				( withTax ? parseInt( totals.line_subtotal_tax, 10 ) : 0 )
			);
		}
		case 'addonUnit':
			return Number( getContext( NAMESPACE ).addon?.price || 0 );
		case 'addonTotal':
			return (
				Number( getContext( NAMESPACE ).addon?.price || 0 ) *
				state.quantity
			);
	}

	return null;
};

const { state } = store( NAMESPACE, {
	state: {
		// Quantities shown before the server confirms them, by "parentKey:addonId" (undefined when none).
		pending: {},
		busy: {},
		// Error messages, by parent cart key.
		errors: {},
		details: {
			title: '',
			html: '',
		},

		get addons() {
			return getCartItem()?.extensions?.a8csp_wcpa?.addons || [];
		},
		get hasAddons() {
			return state.addons.length > 0;
		},
		// Row attributes, so other scripts can find a cart line in the drawer (e.g. to scroll to it).
		get rowKey() {
			return getCartItem()?.key;
		},
		get rowProductId() {
			return getCartItem()?.id;
		},
		get isAddonRow() {
			return !! getCartItem()?.extensions?.a8csp_wcpa?.is_addon;
		},
		get addonKey() {
			return keyOf(
				getCartItem()?.key,
				getContext( NAMESPACE ).addon?.id
			);
		},
		get serverQuantity() {
			return getContext( NAMESPACE ).addon?.in_cart?.quantity || 0;
		},
		get quantity() {
			const key = state.addonKey;
			return hasPending( key )
				? state.pending[ key ]
				: state.serverQuantity;
		},
		get isAdded() {
			return state.quantity > 0;
		},
		get isBusy() {
			return !! state.busy[ state.addonKey ];
		},
		get max() {
			const { addon } = getContext( NAMESPACE );
			let max = addon?.max ?? Infinity;
			if ( addon?.quantity_mode === 'max_parent' ) {
				max = Math.min( max, getCartItem()?.quantity ?? 0 );
			}
			return max;
		},
		get maximumReached() {
			return state.quantity >= state.max;
		},
		// Price markup parts (see a8csp_wcpa_price_markup()).
		get moneyParts() {
			const amount = moneyAmount( getContext( NAMESPACE )?.money );
			return amount === null || Number.isNaN( amount )
				? priceParts( 0 )
				: priceParts( amount, getCartItem()?.prices );
		},
		get moneyPrefix() {
			return state.moneyParts.prefix;
		},
		get moneyInteger() {
			return state.moneyParts.integer;
		},
		get moneyDecimals() {
			return state.moneyParts.decimals;
		},
		get moneyIsZero() {
			return state.moneyParts.isZero;
		},
		get moneySuffix() {
			return state.moneyParts.suffix;
		},
		get error() {
			return state.errors[ getCartItem()?.key ] || '';
		},
		get addLabel() {
			return label( 'addLabel' );
		},
		get reduceLabel() {
			return label( 'reduceLabel' );
		},
		get increaseLabel() {
			return label( 'increaseLabel' );
		},
		get quantityLabel() {
			return label( 'quantityLabel' );
		},
		get removeLabel() {
			return label( 'removeLabel' );
		},
		// The add-on's own link text and accessible name (Store API), with the config's as fallback.
		get detailsText() {
			return (
				getContext( NAMESPACE ).addon?.details_label ||
				getConfig( NAMESPACE ).detailsText
			);
		},
		get detailsLabel() {
			return (
				getContext( NAMESPACE ).addon?.details_aria_label ||
				label( 'detailsLabel' )
			);
		},
	},

	actions: {
		add() {
			setQuantity( 1, true );
		},
		increment() {
			setQuantity( state.quantity + 1 );
		},
		decrement() {
			setQuantity( state.quantity - 1 );
		},
		remove() {
			setQuantity( 0, true );
		},
		changeQuantity( event ) {
			const value = parseInt( event.target.value, 10 );
			setQuantity( Number.isNaN( value ) ? state.quantity : value );
		},

		openDetails() {
			const dialog = getElement()
				.ref.closest( '.a8csp-wcpa-mini-cart' )
				?.querySelector( '.a8csp-wcpa-details' );

			if ( ! dialog ) {
				return;
			}

			const { addon } = getContext( NAMESPACE );

			// The description comes with the cart data (`details`), so it shows at once.
			state.details.title = addon.name;
			state.details.html = addon.details || '';
			dialog.showModal();
		},
		closeDetails() {
			getElement().ref.closest( 'dialog' )?.close();
		},
		closeDetailsOnBackdrop( event ) {
			// Clicks on the dialog itself (not its content) are on the backdrop.
			if ( event.target === event.currentTarget ) {
				event.currentTarget.close();
			}
		},
		detailsKeydown( event ) {
			// Escape closes the dialog only, not the mini-cart drawer behind it.
			if ( event.key === 'Escape' ) {
				event.stopPropagation();
			}
		},
		detailsClosed() {
			state.details.html = '';
		},
	},

	callbacks: {
		// Once the cart refresh shows the quantity we sent, drop the optimistic value.
		syncPending() {
			const key = state.addonKey;
			const serverQuantity = state.serverQuantity;

			if (
				hasPending( key ) &&
				state.pending[ key ] === serverQuantity &&
				! timers.has( key ) &&
				! inFlight.has( key )
			) {
				state.pending[ key ] = undefined;
				confirmed.delete( key );
			}
		},
		renderDetails() {
			// Add-on description HTML from the cart data (run through wp_kses_post on the server).
			getElement().ref.innerHTML = state.details.html;
		},
	},
} );

/**
 * Show a new quantity at once and send it (now, or after the click group ends).
 *
 * @param {number}  quantity  New quantity.
 * @param {boolean} immediate Send without waiting.
 */
function setQuantity( quantity, immediate = false ) {
	const { addon } = getContext( NAMESPACE );
	const cartItem = getCartItem();

	if ( ! addon || ! cartItem ) {
		return;
	}

	const parentKey = cartItem.key;
	const key = keyOf( parentKey, addon.id );
	const next = Math.max( 0, Math.min( quantity, state.max ) );

	// First change since the last sync: the cart's quantity is what the server has.
	if ( ! confirmed.has( key ) ) {
		confirmed.set( key, state.serverQuantity );
	}

	state.errors[ parentKey ] = undefined;
	state.pending[ key ] = next;

	const change = { key, parentKey, addonId: addon.id };

	clearTimeout( timers.get( key ) );
	timers.delete( key );

	if ( immediate ) {
		send( change );
	} else {
		timers.set(
			key,
			setTimeout(
				() => {
					timers.delete( key );
					send( change );
				},
				getConfig( NAMESPACE ).debounceMs ?? 400
			)
		);
	}
}

/**
 * The quantity of an add-on under a line in a Store API cart response.
 *
 * @param {Object} cart      Store API cart.
 * @param {string} parentKey Parent cart key.
 * @param {number} addonId   Add-on ID.
 * @return {number|undefined} Quantity, or undefined when the line is gone.
 */
const quantityInCart = ( cart, parentKey, addonId ) => {
	const line = cart?.items?.find( ( item ) => item.key === parentKey );
	const addon = line?.extensions?.a8csp_wcpa?.addons?.find(
		( item ) => item.id === addonId
	);

	return line ? ( addon?.in_cart?.quantity ?? 0 ) : undefined;
};

/**
 * Send the latest quantity for an add-on. If a request for it is running, the newest value is sent
 * when it finishes. When nothing is left to send, WooCommerce is told to re-read the cart once.
 *
 * @param {Object} change           Change.
 * @param {string} change.key       "parentKey:addonId".
 * @param {string} change.parentKey Parent cart key.
 * @param {number} change.addonId   Add-on ID.
 */
async function send( change ) {
	const { key, parentKey, addonId } = change;

	if ( inFlight.has( key ) || ! hasPending( key ) ) {
		return;
	}

	const quantity = state.pending[ key ];
	const before = confirmed.get( key );

	if ( quantity === before ) {
		settle( key );
		return;
	}

	inFlight.add( key );
	state.busy[ key ] = true;

	try {
		const cart = await setAddonQuantity( { parentKey, addonId, quantity } );
		// The server may have clamped it (stock, cap).
		const accepted = quantityInCart( cart, parentKey, addonId ) ?? quantity;
		confirmed.set( key, accepted );
		changed.add( accepted > before ? 'added' : 'removed' );

		if ( ! timers.has( key ) && state.pending[ key ] === quantity ) {
			state.pending[ key ] = accepted;
		}
	} catch ( error ) {
		state.errors[ parentKey ] =
			error?.message || getConfig( NAMESPACE ).genericError;

		// A newer click is waiting: let it send. Otherwise show the server's quantity again.
		if ( ! timers.has( key ) ) {
			state.pending[ key ] = undefined;
			confirmed.delete( key );
		}
	} finally {
		inFlight.delete( key );
		state.busy[ key ] = undefined;
	}

	// Clicked again while the request ran: send the newer value now (unless a click group is still waiting).
	if ( timers.has( key ) ) {
		return;
	}

	if ( hasPending( key ) && state.pending[ key ] !== confirmed.get( key ) ) {
		send( change );
		return;
	}

	settle( key );
}

/**
 * Nothing left to send for this add-on: once no request or click group is pending anywhere, tell
 * WooCommerce to re-read the cart (totals, count). WooCommerce skips a refresh while another one is
 * loading, so ask once more shortly after.
 *
 * @param {string} key "parentKey:addonId".
 */
function settle( key ) {
	if ( inFlight.size > 0 || timers.size > 0 ) {
		return;
	}

	if ( changed.size === 0 ) {
		state.pending[ key ] = undefined;
		confirmed.delete( key );
		return;
	}

	const added = changed.has( 'added' );
	changed.clear();
	notifyCartChanged( added );

	clearTimeout( retryTimer );
	retryTimer = setTimeout( () => {
		if ( inFlight.size === 0 && timers.size === 0 && hasAnyPending() ) {
			notifyCartChanged( added );
		}
	}, 1500 );
}
