<?php
/**
 * Plugin Name:       WC Product Add-ons
 * Description:       Sell add-on products (a dinner, a bottle of wine, any extra) attached to a parent cart line, such as an event ticket. Any simple product can be marked as an add-on and offered on chosen products or variations. Customers add, change and remove add-ons in the WooCommerce mini-cart, under the line they belong to; add-ons are their own cart and order lines, linked to that parent line.
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Version:           0.1.0
 * Author:            Automattic Special Projects Team
 * Author URI:        https://specialprojects.automattic.com/
 * Update URI:        https://github.com/a8cteam51/special-projects-blocks-monorepo/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-product-addons
 *
 * @package           A8csp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'A8CSP_WCPA_VERSION', '0.1.0' );
define( 'A8CSP_WCPA_FILE', __FILE__ );
define( 'A8CSP_WCPA_DIR', __DIR__ );

require_once __DIR__ . '/includes/functions.php';

/**
 * Boot the plugin once WooCommerce is loaded.
 *
 * Everything hooks on `init` (not `plugins_loaded`) so the plugin behaves the same whether it is
 * installed on its own or included by the monorepo autoloader, which runs during `plugins_loaded`.
 *
 * @return void
 */
function a8csp_wcpa_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/class-admin.php';
	require_once __DIR__ . '/includes/class-catalog.php';
	require_once __DIR__ . '/includes/class-cart.php';
	require_once __DIR__ . '/includes/class-store-api.php';
	require_once __DIR__ . '/includes/class-order.php';
	require_once __DIR__ . '/includes/class-blocks.php';

	A8CSP\WC_Product_Addons\Admin::init();
	A8CSP\WC_Product_Addons\Catalog::init();
	A8CSP\WC_Product_Addons\Cart::init();
	A8CSP\WC_Product_Addons\Store_API::init();
	A8CSP\WC_Product_Addons\Order::init();
	A8CSP\WC_Product_Addons\Blocks::init();
}
add_action( 'init', 'a8csp_wcpa_boot', 0 );
