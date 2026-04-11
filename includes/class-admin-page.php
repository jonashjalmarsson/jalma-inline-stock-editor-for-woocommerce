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
	}

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Quick Stock', 'jalma-quick-stock-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Update stock quantities and low-stock thresholds without opening each product. Inline edit, keyboard navigation, full variation support.', 'jalma-quick-stock-for-woocommerce' ); ?></p>

			<div id="jqsw-root">
				<p class="description"><?php esc_html_e( 'The interactive Quick Stock table will be rendered here. (Scaffold version — table renderer coming in the next iteration.)', 'jalma-quick-stock-for-woocommerce' ); ?></p>
			</div>
		</div>
		<?php
	}
}
