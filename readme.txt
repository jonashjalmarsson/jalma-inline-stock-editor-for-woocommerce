=== Jalma Quick Stock for WooCommerce ===
Contributors: jonashjalmarsson
Tags: woocommerce, stock, inventory, bulk edit, low stock
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.6
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

= 1.0.6 =
* Housekeeping: moved admin runtime JavaScript and CSS from `assets/js/` and `assets/css/` to `admin/js/` and `admin/css/`. Keeps the `assets/` folder reserved for WordPress.org listing material (banner, icon, screenshots) so the plugin zip doesn't ship unnecessary bytes. No user-visible changes.

= 1.0.5 =
* Developer: added extension hooks for add-on plugins. Actions: `jqsw_before_register_routes`, `jqsw_after_register_routes`, `jqsw_after_product_update`, `jqsw_before_filters`, `jqsw_filters_extra`, `jqsw_before_table`, `jqsw_after_table`. Filter: `jqsw_product_row_data`. No visible changes for end users.

= 1.0.4 =
* Improvement: replaced the Start tracking / Stop tracking button pair with a single "Track stock" checkbox per row. Consistent with the existing "Manage stock per variation" toggle for variable products and semantically correct for a boolean on/off state. Cleaner visually and less ambiguous than two near-identical buttons.

= 1.0.3 =
* Improvement: clearer labels in the stock-tracking column. The column header is now "Stock tracking" and the buttons read "Start tracking" and "Stop tracking" so it's obvious what they toggle.
* Compatibility: declared HPOS (High-Performance Order Storage) compatibility. This plugin only touches product data so it's safe in both the legacy and the new custom order tables — WooCommerce will no longer show an incompatibility warning on the plugins screen.

= 1.0.2 =
* New: dedicated Actions column with Enable/Disable buttons for each product. You can now stop tracking stock on a product straight from the table, and re-enable it later — the last known stock value is preserved.
* Change: the "Manage stock per variation" checkbox for variable products moved from the product cell to the Actions column for consistency.

= 1.0.1 =
* Improvement: "Enable stock management" and the variable-product stock-mode toggle now update just the affected row via AJAX, instead of reloading the whole table. Keeps scroll position and visual context.

= 1.0.0 =
* Initial release.
* Single-table view of all products with inline-editable stock and low-stock threshold.
* Category filter with hierarchical dropdown.
* Stock status filter (in stock, out of stock, on backorder, not tracked).
* Search by name or SKU.
* Variable product support with toggle between parent-level and per-variation stock management.
* Variations expand inline under their parent in per-variation mode.
* One-click enable for products that have stock management disabled.
* Auto-save via REST API with visual confirmation per field.
* Keyboard navigation: Tab between fields, Enter to save and jump to next row.
* Soft integration with Jalma Category Notifications for WooCommerce.
* Translation-ready, Swedish (sv_SE) included.

== Upgrade Notice ==

= 1.0.4 =
Stock tracking toggle is now a single checkbox instead of two button variants.

= 1.0.3 =
Clearer Stock tracking column labels and formal HPOS compatibility declaration.

= 1.0.2 =
New Actions column with Enable/Disable buttons and a cleaner layout for variable products.

= 1.0.1 =
Per-row updates for Enable and variation toggle (no more full table reloads).

= 1.0.0 =
Initial release.
