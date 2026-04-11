<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JQSW_Admin_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Quick Stock', 'jalma-quick-stock-for-woocommerce' ),
			__( 'Quick Stock', 'jalma-quick-stock-for-woocommerce' ),
			'manage_woocommerce',
			'jqsw-quick-stock',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_jqsw-quick-stock' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'select2' );

		wp_enqueue_style(
			'jqsw-admin',
			JQSW_URL . 'assets/css/quick-stock.css',
			[],
			JQSW_VERSION
		);

		wp_enqueue_script(
			'jqsw-admin',
			JQSW_URL . 'assets/js/quick-stock.js',
			[ 'jquery', 'wc-enhanced-select' ],
			JQSW_VERSION,
			true
		);

		wp_localize_script( 'jqsw-admin', 'jqswData', [
			'restUrl'             => esc_url_raw( rest_url( 'jalma-quick-stock/v1/' ) ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'categories'          => $this->get_hierarchical_categories(),
			'globalLowStockAmount' => (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ),
			'strings'             => [
				'allCategories'      => __( 'All categories', 'jalma-quick-stock-for-woocommerce' ),
				'anyStatus'          => __( 'Any stock status', 'jalma-quick-stock-for-woocommerce' ),
				'inStock'            => __( 'In stock', 'jalma-quick-stock-for-woocommerce' ),
				'outOfStock'         => __( 'Out of stock', 'jalma-quick-stock-for-woocommerce' ),
				'onBackorder'        => __( 'On backorder', 'jalma-quick-stock-for-woocommerce' ),
				'notManaged'         => __( 'Not tracked', 'jalma-quick-stock-for-woocommerce' ),
				'searchPlaceholder'  => __( 'Search name or SKU…', 'jalma-quick-stock-for-woocommerce' ),
				'product'            => __( 'Product', 'jalma-quick-stock-for-woocommerce' ),
				'stock'              => __( 'Stock', 'jalma-quick-stock-for-woocommerce' ),
				'lowStockThreshold'  => __( 'Low stock threshold', 'jalma-quick-stock-for-woocommerce' ),
				'globalHint'         => __( '%d (global)', 'jalma-quick-stock-for-woocommerce' ),
				'loading'            => __( 'Loading products…', 'jalma-quick-stock-for-woocommerce' ),
				'noResults'          => __( 'No products match your filters.', 'jalma-quick-stock-for-woocommerce' ),
				'trackStock'         => __( 'Track stock', 'jalma-quick-stock-for-woocommerce' ),
				'actions'            => __( 'Stock tracking', 'jalma-quick-stock-for-woocommerce' ),
				'notTracked'         => __( 'Not tracked', 'jalma-quick-stock-for-woocommerce' ),
				'perVariation'       => __( '(managed per variation)', 'jalma-quick-stock-for-woocommerce' ),
				'managePerVariation' => __( 'Manage stock per variation', 'jalma-quick-stock-for-woocommerce' ),
				'prevPage'           => __( '‹ Previous', 'jalma-quick-stock-for-woocommerce' ),
				'nextPage'           => __( 'Next ›', 'jalma-quick-stock-for-woocommerce' ),
				'pageOf'             => __( 'Page %1$d of %2$d (%3$d products)', 'jalma-quick-stock-for-woocommerce' ),
				'saving'             => __( 'Saving…', 'jalma-quick-stock-for-woocommerce' ),
				'saved'              => __( 'Saved', 'jalma-quick-stock-for-woocommerce' ),
				'saveError'          => __( 'Save failed', 'jalma-quick-stock-for-woocommerce' ),
				'sku'                => __( 'SKU', 'jalma-quick-stock-for-woocommerce' ),
				'noSku'              => __( '(no SKU)', 'jalma-quick-stock-for-woocommerce' ),
			],
		] );
	}

	public function render_page() {
		?>
		<div class="wrap jqsw-wrap">
			<h1><?php esc_html_e( 'Quick Stock', 'jalma-quick-stock-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Update stock quantities and low-stock thresholds without opening each product. Click a field, type a new value, tab to the next. Changes save automatically.', 'jalma-quick-stock-for-woocommerce' ); ?></p>

			<div class="jqsw-filters">
				<select class="jqsw-filter-category wc-enhanced-select" data-placeholder="<?php esc_attr_e( 'All categories', 'jalma-quick-stock-for-woocommerce' ); ?>"></select>
				<select class="jqsw-filter-stock-status"></select>
				<input type="search" class="jqsw-filter-search" placeholder="<?php esc_attr_e( 'Search name or SKU…', 'jalma-quick-stock-for-woocommerce' ); ?>">
			</div>

			<div class="jqsw-table-wrap">
				<table class="widefat striped jqsw-table">
					<thead>
						<tr>
							<th class="jqsw-col-product"><?php esc_html_e( 'Product', 'jalma-quick-stock-for-woocommerce' ); ?></th>
							<th class="jqsw-col-stock"><?php esc_html_e( 'Stock', 'jalma-quick-stock-for-woocommerce' ); ?></th>
							<th class="jqsw-col-threshold"><?php esc_html_e( 'Low stock threshold', 'jalma-quick-stock-for-woocommerce' ); ?></th>
							<th class="jqsw-col-status"></th>
							<th class="jqsw-col-actions"><?php esc_html_e( 'Stock tracking', 'jalma-quick-stock-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody id="jqsw-tbody">
						<tr class="jqsw-loading-row"><td colspan="5"><?php esc_html_e( 'Loading products…', 'jalma-quick-stock-for-woocommerce' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="jqsw-pagination">
				<button type="button" class="button jqsw-prev" disabled>&lsaquo; <?php esc_html_e( 'Previous', 'jalma-quick-stock-for-woocommerce' ); ?></button>
				<span class="jqsw-page-info"></span>
				<button type="button" class="button jqsw-next" disabled><?php esc_html_e( 'Next', 'jalma-quick-stock-for-woocommerce' ); ?> &rsaquo;</button>
			</div>
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
