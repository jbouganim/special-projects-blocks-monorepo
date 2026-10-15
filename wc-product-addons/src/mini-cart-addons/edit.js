/**
 * Editor preview: a placeholder, since the products table is built from the shopper's cart.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<div className="a8csp-wcpa-mini-cart__placeholder">
				<strong>
					{ __(
						'Mini-Cart products with add-ons',
						'wc-product-addons'
					) }
				</strong>
				<p>
					{ __(
						'Shows the products in the cart. Lines that offer add-ons list them underneath, with an Add button or a quantity selector.',
						'wc-product-addons'
					) }
				</p>
			</div>
		</div>
	);
}
