/**
 * Default block build, plus the cart and checkout script (not a block).
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const [ scriptConfig, moduleConfig ] = Array.isArray( defaultConfig )
	? defaultConfig
	: [ defaultConfig ];

module.exports = [
	{
		...scriptConfig,
		entry: () => ( {
			...( typeof scriptConfig.entry === 'function'
				? scriptConfig.entry()
				: scriptConfig.entry ),
			'cart-checkout/index': './src/cart-checkout/index.js',
		} ),
	},
	moduleConfig,
].filter( Boolean );
