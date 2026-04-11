=== Jalma Quick Stock for WooCommerce ===
Contributors: jeventab
Tags: woocommerce, stock, inventory, bulk edit, low stock
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Edit WooCommerce stock quantities and low-stock thresholds from a single table — no more clicking into each product.

== Description ==

**Jalma Quick Stock for WooCommerce** replaces WooCommerce's per-product click-fest with a single table where you can update stock quantities and low-stock thresholds for all your products at once. Inline edit, keyboard navigation, category filter, variation support.

Ideal for shops that do weekly inventory counts, quick post-delivery updates, manual adjustments, or stock management for hundreds of products.

= Features =

* **Single-table overview** of all WooCommerce products with current stock and low-stock threshold.
* **Inline auto-save** — click a field, type the new value, tab to the next. Changes save via AJAX with visual confirmation.
* **Keyboard-first workflow** — tab between stock and threshold fields, enter to save and move to next row.
* **Low-stock threshold editing** — see the global default as a placeholder, override per product, clear to fall back to global.
* **Full variation support** — toggle between parent-level stock and per-variation stock with a single checkbox. Variations expand inline, editable directly.
* **Category filter** with hierarchical dropdown for navigating deep product trees.
* **Search by name or SKU.**
* **Pagination** for large catalogs.
* Works alongside any stock notification plugin — updates trigger the standard WooCommerce `woocommerce_low_stock` and `woocommerce_no_stock` actions.

= How it works =

1. Install and activate. Requires WooCommerce.
2. Go to **WooCommerce → Quick Stock**.
3. Edit stock and low-stock thresholds directly in the table. Changes save automatically.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install via the **Plugins → Add New** screen.
2. Activate **Jalma Quick Stock for WooCommerce** through the **Plugins** menu.
3. Go to **WooCommerce → Quick Stock** to start editing.

== Frequently Asked Questions ==

= Does this replace WooCommerce's product editor? =

No. You can still edit products the normal way — this is just a faster path for the common case of updating stock numbers. All other product fields (price, description, images) are unaffected.

= How does it handle variable products? =

Variable products can manage stock at the parent level (one shared value for all variations) or per variation. Each variable product row has a "Manage stock per variation" checkbox that toggles between the two modes. When enabled, variations expand inline and are editable individually.

= Does it work with any stock notification plugin? =

Yes. Quick Stock calls WooCommerce's standard stock-update methods, which fire the `woocommerce_low_stock` and `woocommerce_no_stock` actions. Any plugin listening to those — including **Jalma Category Notifications for WooCommerce** — receives the events as usual.

= Does it track stock adjustment history? =

Not in the free version. A stock adjustment log is planned for the Pro version.

== Screenshots ==

1. Quick Stock table with inline-editable stock and low-stock threshold columns.
2. Variable product expanded to show per-variation stock editing.
3. Category filter and search.

== Changelog ==

= 0.1.0 =
* Initial scaffold.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
