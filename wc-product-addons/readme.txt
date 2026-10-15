=== WC Product Add-ons ===
Contributors:      wpspecialprojects
Tags:              woocommerce, add-ons, mini-cart, extras
Requires Plugins:  woocommerce
Tested up to:      7.1
Stable tag:        0.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Sell add-on products (dinner, drinks, extras) attached to a cart line, chosen in the WooCommerce mini-cart.

== Description ==

Any simple product can be marked as an add-on and offered with chosen products or variations, for example a dinner or a bottle of wine with an event ticket. Customers add, change and remove add-ons in the mini-cart, under the line they belong to. Add-ons are their own cart and order lines, linked to that line.

* **Add-on products:** tick **Add-on** next to Virtual / Downloadable on a simple product. Add-ons are hidden from the shop and search, their product page returns a 404, and they can't be added to the cart on their own.
* **Quantity rule** (General tab): any quantity, no more than the line it is added to, or no more than a set number per line. Stock limits always apply.
* **Offering add-ons:** pick them in **Linked Products > Add-ons** on any product, or on each variation (a variation's own list replaces the product's).
* **Mini-cart:** the **Mini-Cart Products with Add-ons** block replaces WooCommerce's Mini-Cart Products Table block in the mini-cart template part. Each line that offers add-ons lists them with name, price and short description (when set), an **Add** button that becomes a quantity selector, and a **Menu details** link when the add-on has a description, which opens it in a dialog (the description comes with the cart data, so the dialog opens instantly). Neither text falls back to the other. Prices in the block (add-on unit price and line total, and the cart line's unit, regular, line total and savings prices) use split markup: `a8csp-wcpa-price__prefix`, `__integer`, `__decimals` (separator and digits, left out with 0 decimals, `is-zero` class when the digits are all zeros) and `__suffix`, so themes can hide ".00". Rows have `data-cart-item-key` and `data-product-id` attributes so scripts can find a line, for example to scroll to one just added.
* **Cart and Checkout blocks:** add-on lines come right after their line and get the `is-addon` class; lines with add-ons get `has-addons`. Their quantity selector and remove link work as usual. Removing a line removes its add-ons. Add-on lines show the add-on's short description in full as an item detail (`wc-block-components-product-details__description`); the Cart block would otherwise cut the description to a few words.
* **Orders:** add-on order lines store `_a8csp_wcpa_parent_item_id` and show "Add-on for: …" in the admin and emails. `a8csp_wcpa_get_order_item_addons( $item )` returns a line's add-ons.

Styling is structural only; themes style the add-ons. Taxes use each add-on product's own tax settings.

= Store API =

* Cart items have `extensions.a8csp_wcpa`: `parent_key`, `is_addon`, and `addons` (the add-ons offered with the line: text, price, quantity rule, `details` HTML for the dialog, and `in_cart` when added).
* `POST /wc/store/v1/cart/extensions` with `namespace: "a8csp-wcpa"` and `data: { items: [ { parent_key, addon_id, quantity } ] }` sets add-on quantities (0 removes).

The mini-cart block makes these calls itself because WooCommerce's Interactivity API cart store is private, then fires `wc-blocks_added_to_cart` / `wc-blocks_removed_from_cart` so the mini-cart re-reads the cart. Clicks on a quantity selector are grouped into one request.

= Filters =

* `a8csp_wcpa_is_addon`, `a8csp_wcpa_addon_ids`, `a8csp_wcpa_addons`: which products are add-ons and which are offered.
* `a8csp_wcpa_addon_max_quantity`: the highest quantity of an add-on for a line.
* `a8csp_wcpa_store_api_cart_item_data`: the Store API add-on data of a cart item.
* `a8csp_wcpa_disable_product_page`: whether an add-on's product page returns a 404.
* `a8csp_wcpa_show_addon_for_meta`: whether order lines show "Add-on for".
* `a8csp_wcpa_show_description_item_data`: whether add-on cart lines show the add-on's short description as an item detail.

== Installation ==

1. Upload the `wc-product-addons` folder to `/wp-content/plugins/` and activate it (WooCommerce is required), or load it with the monorepo autoloader.
2. In the mini-cart template part (**Appearance > Editor > Patterns > Mini-Cart**, or the theme's `parts/mini-cart.html`), replace the Mini-Cart Products Table block with **Mini-Cart Products with Add-ons** (`<!-- wp:a8csp/wc-product-addons-mini-cart /-->`).
3. Mark add-on products and link them to products or variations.

= Building from source =

Requirements: Node.js 18+.

1. `cd wc-product-addons`
2. `npm install`
3. `npm run build` — production build
4. `npm start` — development build with file watching

== Changelog ==

= 0.1.0 =
* Initial release.
