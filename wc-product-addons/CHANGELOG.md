# Changelog

All notable changes to this project will be documented in this file.

## 0.2.0

### Added

- "No add-ons" option on variations: the variation offers no add-ons, not even its product's (`_a8csp_wcpa_no_addons`). Its own list is kept, so unticking brings it back.
- "Details link text" on add-on products (`_a8csp_wcpa_details_label`), e.g. "Menu details". In the Store API data as `details_label` and `details_aria_label`; `a8csp_wcpa_get_details_label()` / `a8csp_wcpa_get_details_aria_label()`.
- Details link on add-on lines in the Cart and Checkout blocks, opening the same dialog as the mini-cart. Off by default: `add_filter( 'a8csp_wcpa_show_details_link_item_data', '__return_true' )`.
- `a8csp_wcpa_details_aria_label` filter.

### Fixed

- `a8csp_wcpa_details_label` filtered both the visible "Details" text and the "Details for %s" accessible name, so changing it broke the accessible name. It now filters the link text only (and gets the add-on as a second argument); the accessible name has its own filter.

## 0.1.0

### Added

- Add-on products: an "Add-on" product option and a quantity rule (any quantity, no more than the line it is added to, or no more than a set number per line). Add-ons are hidden from the catalog and can't be bought on their own.
- Add-ons offered per product or per variation (Linked Products).
- Add-ons as their own cart lines, linked to and sorted after their parent line; removed with it; capped add-ons follow its quantity.
- Mini-Cart Products with Add-ons block: Add button, quantity selector with grouped requests, and a details dialog filled from the cart data (no extra request). Row attributes `data-cart-item-key` / `data-product-id`. Split price markup (`a8csp_wcpa_price_markup()`) with an `is-zero` decimals class, for add-on prices and the cart line's prices.
- Store API cart item data (`a8csp_wcpa`) and a `cart/extensions` update callback (`a8csp-wcpa`).
- Cart and Checkout block classes (`is-addon`, `has-addons`), and the add-on's full text as an item detail on add-on lines.
- Order lines linked to their parent line (`_a8csp_wcpa_parent_item_id`), "Add-on for" in admin and emails.
