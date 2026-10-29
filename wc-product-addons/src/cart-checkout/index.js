/**
 * Cart and Checkout blocks: class names on add-on lines and on lines that have add-ons,
 * so themes can nest them visually (the server keeps each add-on right after its line).
 * Also handles the add-ons' details links (details.js).
 */
import './details';
import './details.scss';

const { registerCheckoutFilters } = window.wc?.blocksCheckout || {};

if ( registerCheckoutFilters ) {
	registerCheckoutFilters( 'a8csp-wc-product-addons', {
		cartItemClass: ( value, extensions ) => {
			const data = extensions?.a8csp_wcpa;
			if ( ! data ) {
				return value;
			}

			const classes = [ value ];
			if ( data.is_addon ) {
				classes.push( 'is-addon' );
			}
			if ( data.addons?.some( ( addon ) => addon.in_cart ) ) {
				classes.push( 'has-addons' );
			}

			return classes.filter( Boolean ).join( ' ' );
		},
	} );
}
