<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JISE_Admin_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Stock Editor', 'jalma-inline-stock-editor' ),
			__( 'Stock Editor', 'jalma-inline-stock-editor' ),
			'manage_woocommerce',
			'jise-stock-editor',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_jise-stock-editor' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'select2' );

		wp_enqueue_style(
			'jqsw-admin',
			JISE_URL . 'admin/css/quick-stock.css',
			[],
			JISE_VERSION
		);

		wp_enqueue_script(
			'jqsw-admin',
			JISE_URL . 'admin/js/quick-stock.js',
			[ 'jquery', 'wc-enhanced-select' ],
			JISE_VERSION,
			true
		);

		wp_localize_script( 'jqsw-admin', 'jqswData', [
			'restUrl'             => esc_url_raw( rest_url( 'jalma-quick-stock/v1/' ) ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'categories'          => $this->get_hierarchical_categories(),
			'globalLowStockAmount' => (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ),
			'strings'             => [
				'allCategories'      => __( 'All categories', 'jalma-inline-stock-editor' ),
				'anyStatus'          => __( 'Any stock status', 'jalma-inline-stock-editor' ),
				'inStock'            => __( 'In stock', 'jalma-inline-stock-editor' ),
				'outOfStock'         => __( 'Out of stock', 'jalma-inline-stock-editor' ),
				'onBackorder'        => __( 'On backorder', 'jalma-inline-stock-editor' ),
				'notManaged'         => __( 'Not tracked', 'jalma-inline-stock-editor' ),
				'searchPlaceholder'  => __( 'Search name or SKU…', 'jalma-inline-stock-editor' ),
				'product'            => __( 'Product', 'jalma-inline-stock-editor' ),
				'stock'              => __( 'Stock', 'jalma-inline-stock-editor' ),
				'lowStockThreshold'  => __( 'Low stock threshold', 'jalma-inline-stock-editor' ),
				/* translators: %d: the global low-stock threshold from WooCommerce settings */
				'globalHint'         => __( '%d (global)', 'jalma-inline-stock-editor' ),
				'loading'            => __( 'Loading products…', 'jalma-inline-stock-editor' ),
				'noResults'          => __( 'No products match your filters.', 'jalma-inline-stock-editor' ),
				'trackStock'         => __( 'Track stock', 'jalma-inline-stock-editor' ),
				'actions'            => __( 'Stock tracking', 'jalma-inline-stock-editor' ),
				'notTracked'         => __( 'Not tracked', 'jalma-inline-stock-editor' ),
				'perVariation'       => __( '(managed per variation)', 'jalma-inline-stock-editor' ),
				'managePerVariation' => __( 'Manage stock per variation', 'jalma-inline-stock-editor' ),
				'prevPage'           => __( '‹ Previous', 'jalma-inline-stock-editor' ),
				'nextPage'           => __( 'Next ›', 'jalma-inline-stock-editor' ),
				/* translators: 1: current page number, 2: total page count, 3: total product count */
				'pageOf'             => __( 'Page %1$d of %2$d (%3$d products)', 'jalma-inline-stock-editor' ),
				'saving'             => __( 'Saving…', 'jalma-inline-stock-editor' ),
				'saved'              => __( 'Saved', 'jalma-inline-stock-editor' ),
				'saveError'          => __( 'Save failed', 'jalma-inline-stock-editor' ),
				'sku'                => __( 'SKU', 'jalma-inline-stock-editor' ),
				'noSku'              => __( '(no SKU)', 'jalma-inline-stock-editor' ),
			],
		] );
	}

	public function render_page() {
		?>
		<div class="wrap jqsw-wrap">
			<h1><?php esc_html_e( 'Stock Editor', 'jalma-inline-stock-editor' ); ?></h1>
			<p><?php esc_html_e( 'Update stock quantities and low-stock thresholds without opening each product. Click a field, type a new value, tab to the next. Changes save automatically.', 'jalma-inline-stock-editor' ); ?></p>

			<?php
			/**
			 * Fires right after the page heading, before the filter row.
			 * Add-on plugins can inject announcements, upgrade notices, or
			 * extra controls here.
			 *
			 * @since 1.0.5
			 */
			do_action( 'jise_before_filters' );
			?>

			<div class="jqsw-filters">
				<select class="jqsw-filter-category wc-enhanced-select" data-placeholder="<?php esc_attr_e( 'All categories', 'jalma-inline-stock-editor' ); ?>"></select>
				<select class="jqsw-filter-stock-status"></select>
				<input type="search" class="jqsw-filter-search" placeholder="<?php esc_attr_e( 'Search name or SKU…', 'jalma-inline-stock-editor' ); ?>">
				<?php
				/**
				 * Fires inside the filter row so add-ons can inject extra
				 * filter controls (e.g. supplier, price range, brand).
				 *
				 * @since 1.0.5
				 */
				do_action( 'jise_filters_extra' );
				?>
			</div>

			<?php
			/**
			 * Fires after the filter row and before the table.
			 * Use this to inject a bulk-actions bar, summary counts, or an
			 * export button from an add-on.
			 *
			 * @since 1.0.5
			 */
			do_action( 'jise_before_table' );
			?>

			<div class="jqsw-table-wrap">
				<table class="widefat striped jqsw-table">
					<thead>
						<tr>
							<th class="jqsw-col-product"><?php esc_html_e( 'Product', 'jalma-inline-stock-editor' ); ?></th>
							<th class="jqsw-col-stock"><?php esc_html_e( 'Stock', 'jalma-inline-stock-editor' ); ?></th>
							<th class="jqsw-col-threshold"><?php esc_html_e( 'Low stock threshold', 'jalma-inline-stock-editor' ); ?></th>
							<th class="jqsw-col-status"></th>
							<th class="jqsw-col-actions"><?php esc_html_e( 'Stock tracking', 'jalma-inline-stock-editor' ); ?></th>
						</tr>
					</thead>
					<tbody id="jqsw-tbody">
						<tr class="jqsw-loading-row"><td colspan="5"><?php esc_html_e( 'Loading products…', 'jalma-inline-stock-editor' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="jqsw-pagination">
				<button type="button" class="button jqsw-prev" disabled>&lsaquo; <?php esc_html_e( 'Previous', 'jalma-inline-stock-editor' ); ?></button>
				<span class="jqsw-page-info"></span>
				<button type="button" class="button jqsw-next" disabled><?php esc_html_e( 'Next', 'jalma-inline-stock-editor' ); ?> &rsaquo;</button>
			</div>

			<?php
			/**
			 * Fires after the entire Stock Editor UI. Use this to inject
			 * footer content, upgrade links, or documentation panels from
			 * an add-on.
			 *
			 * @since 1.0.5
			 */
			do_action( 'jise_after_table' );
			?>
		</div>
		<?php
	}

	/**
	 * Build a hierarchically ordered list of product categories.
	 * Shared format with Category Notifications: { id, name, depth }.
	 */
	private function get_hierarchical_categories() {
		$all = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
		] );
		if ( is_wp_error( $all ) || empty( $all ) ) {
			return [];
		}

		$by_parent = [];
		foreach ( $all as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$sorted = [];
		$walk   = function ( $parent_id, $depth ) use ( &$walk, &$sorted, $by_parent ) {
			if ( empty( $by_parent[ $parent_id ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent_id ] as $term ) {
				$sorted[] = [
					'id'    => (int) $term->term_id,
					'name'  => $term->name,
					'depth' => $depth,
				];
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		return $sorted;
	}
}
