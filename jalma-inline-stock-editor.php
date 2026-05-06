<?php
/**
 * Plugin Name: Jalma Inline Stock Editor
 * Plugin URI: https://wordpress.org/plugins/jalma-inline-stock-editor/
 * Description: Edit WooCommerce stock quantities and low-stock thresholds directly from a single table — no more clicking into each product. Inline edit, keyboard navigation, category filter, full variation support.
 * Version: 1.1.3
 * Author: jonashjalmarsson
 * Author URI: https://jonashjalmarsson.se
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Text Domain: jalma-inline-stock-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Declare HPOS (High-Performance Order Storage) compatibility. This plugin
 * only touches product data — never orders — so it's safe with both the
 * legacy post-type storage and the new custom order tables. Without this
 * declaration WooCommerce shows a yellow "incompatible" warning on the
 * plugins screen even though the plugin works fine in both modes.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

define( 'JISE_VERSION', '1.1.3' );
define( 'JISE_SLUG', 'jalma-inline-stock-editor' );
define( 'JISE_PATH', plugin_dir_path( __FILE__ ) );
define( 'JISE_URL', plugin_dir_url( __FILE__ ) );

/* @wporg-strip-start */
/* jhwpl — self-hosted update checker (stripped from wp.org build) */
require_once JISE_PATH . 'jhwpl/update_checker.php';
new \JHWPL\UpdateChecker( [
	'version'       => JISE_VERSION,
	'basename_dir'  => plugin_basename( __DIR__ ),
	'basename_file' => plugin_basename( __FILE__ ),
	'slug'          => JISE_SLUG,
	'info_url'      => 'https://plugins.jonashjalmarsson.se/jalma-inline-stock-editor/info.json',
] );
/* @wporg-strip-end */

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				sprintf(
					/* translators: %s: plugin name */
					esc_html__( '%s requires WooCommerce to be active.', 'jalma-inline-stock-editor' ),
					'<strong>Jalma Inline Stock Editor for WooCommerce</strong>'
				) .
				'</p></div>';
		} );
		return;
	}

	require_once JISE_PATH . 'includes/class-admin-page.php';
	require_once JISE_PATH . 'includes/class-rest-controller.php';

	new JISE_Admin_Page();
	new JISE_Rest_Controller();
} );

/*
 * PRO upsell + auto-install flow lives in the reusable JHLSQ\Purchase
 * module bundled at jhlsq-purchase/. Loaded late (admin_init) so the
 * Free plugin's settings page hook + the WooCommerce menu have already
 * been registered.
 */
require_once JISE_PATH . 'jhlsq-purchase/jhlsq-purchase.php';

add_action( 'admin_init', function () {
	if ( ! class_exists( '\\JHLSQ\\Purchase' ) ) {
		return;
	}
	new \JHLSQ\Purchase( [
		'free_basename'        => 'jalma-inline-stock-editor/jalma-inline-stock-editor.php',
		'pro_basename'         => 'jalma-quick-stock-for-woocommerce-pro/jalma-quick-stock-for-woocommerce-pro.php',
		'pro_class_check'      => 'JQSWP\\Tabs',
		'pro_label'            => 'Stock Editor for WooCommerce PRO',
		'checkout_url'         => 'https://pay.jonashjalmarsson.se/checkout/buy/03c85738-f8d1-40ef-ae72-046503763ecb',
		'download_url'         => 'https://plugins.jonashjalmarsson.se/jalma-quick-stock-for-woocommerce-pro/jalma-quick-stock-for-woocommerce-pro.zip',
		'bridge_base'          => 'https://jonashjalmarsson.se/wp-json/lsq-bridge/v1',
		'license_option'       => 'lsq_jalma-quick-stock-for-woocommerce-pro',
		'license_page_url'     => admin_url( 'admin.php?page=jise-license' ),
		'settings_page_hook'   => 'woocommerce_page_jise-stock-editor',
		'after_heading_action' => 'jise_before_filters',
		'pitch_text'           => 'CSV export and import for product stock data — filter by category, include variations, two-step preview-before-apply flow',
		'landing_page_url'     => 'https://jonashjalmarsson.se/plugins/jalma-quick-stock-for-woocommerce-pro/',
		'install_action_name'  => 'jise_install_pro',
	] );
} );
