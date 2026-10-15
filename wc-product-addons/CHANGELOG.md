# Changelog

All notable changes to this project will be documented in this file.

## 0.1.0

### Added

- Add-on products: an "Add-on" product option and a quantity rule (any quantity, no more than the line it is added to, or no more than a set number per line). Add-ons are hidden from the catalog and can't be bought on their own.
- Add-ons offered per product or per variation (Linked Products).
- Add-ons as their own cart lines, linked to and sorted after their parent line; removed with it; capped add-ons follow its quantity.
- Mini-Cart Products with Add-ons block: Add button, quantity selector with grouped requests, and a details dialog filled from the cart data (no extra request). Row attributes `data-cart-item-key` / `data-product-id`. Split price markup (`a8csp_wcpa_price_markup()`) with an `is-zero` decimals class, for add-on prices and the cart line's prices.
- Store API cart item data (`a8csp_wcpa`) and a `cart/extensions` update callback (`a8csp-wcpa`).
- Cart and Checkout block classes (`is-addon`, `has-addons`), and the add-on's full text as an item detail on add-on lines.
- Order lines linked to their parent line (`_a8csp_wcpa_parent_item_id`), "Add-on for" in admin and emails.
