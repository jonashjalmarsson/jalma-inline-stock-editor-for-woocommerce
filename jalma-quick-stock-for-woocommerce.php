<?php
/**
 * Plugin Name: Jalma Quick Stock for WooCommerce
 * Plugin URI:
 * Description: Edit WooCommerce stock quantities and low-stock thresholds directly from a single table — no more clicking into each product. Inline edit, keyboard navigation, category filter, full variation support.
 * Version: 1.1.0
 * Author: jonashjalmarsson
 * Author URI: https://jonashjalmarsson.se
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Text Domain: jalma-quick-stock-for-woocommerce
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

define( 'JQSW_VERSION', '1.1.0' );
define( 'JQSW_SLUG', 'jalma-quick-stock-for-woocommerce' );
define( 'JQSW_PATH', plugin_dir_path( __FILE__ ) );
define( 'JQSW_URL', plugin_dir_url( __FILE__ ) );

/* @wporg-strip-start */
/* jhwpl — self-hosted update checker (stripped from wp.org build) */
require_once JQSW_PATH . 'jhwpl/update_checker.php';
new \JHWPL\UpdateChecker( [
	'version'       => JQSW_VERSION,
	'basename_dir'  => plugin_basename( __DIR__ ),
	'basename_file' => plugin_basename( __FILE__ ),
	'slug'          => JQSW_SLUG,
	'info_url'      => 'https://plugins.jonashjalmarsson.se/jalma-quick-stock-for-woocommerce/info.json',
] );
/* @wporg-strip-end */

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				sprintf(
					/* translators: %s: plugin name */
					esc_html__( '%s requires WooCommerce to be active.', 'jalma-quick-stock-for-woocommerce' ),
					'<strong>Jalma Quick Stock for WooCommerce</strong>'
				) .
				'</p></div>';
		} );
		return;
	}

	require_once JQSW_PATH . 'includes/class-admin-page.php';
	require_once JQSW_PATH . 'includes/class-rest-controller.php';
	require_once JQSW_PATH . 'includes/class-integrations.php';

	new JQSW_Admin_Page();
	new JQSW_Rest_Controller();
	new JQSW_Integrations();
} );
