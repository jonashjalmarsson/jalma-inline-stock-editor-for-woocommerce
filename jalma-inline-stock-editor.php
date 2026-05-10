<?php
/**
 * Plugin Name: Jalma Inline Stock Editor
 * Plugin URI: https://wordpress.org/plugins/jalma-inline-stock-editor/
 * Description: Edit WooCommerce stock quantities and low-stock thresholds directly from a single table — no more clicking into each product. Inline edit, keyboard navigation, category filter, full variation support.
 * Version: 1.1.6
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

define( 'JISE_VERSION', '1.1.6' );
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
 * Pro upsell — a simple "Get Pro" link in the Plugins-screen row, plus a
 * small pill above the stock table that points users to the Pro landing
 * page. The free plugin does NOT call any external service of its own;
 * the Pro purchase, license activation and install all happen on the
 * project landing page (URL below) and inside the Pro plugin's own
 * License tab once installed. Keeps Free focused on stock editing only.
 */
const JISE_PRO_LANDING_URL = 'https://jonashjalmarsson.se/plugins/jalma-inline-stock-editor-pro/';

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	if ( class_exists( 'JISEP\\Tabs' ) ) {
		return $links; // Pro is loaded — no need to upsell.
	}
	$links[] = '<a href="' . esc_url( JISE_PRO_LANDING_URL ) . '" target="_blank" rel="noopener">'
		. esc_html__( 'Get Pro', 'jalma-inline-stock-editor' )
		. '</a>';
	return $links;
} );

add_action( 'jise_before_filters', function () {
	if ( class_exists( 'JISEP\\Tabs' ) ) {
		return; // Pro is loaded — hide the upsell.
	}
	?>
	<div class="jise-pro-pill" style="background:#f5f7ff;border:1px solid #d6dffd;border-radius:6px;padding:1em 1.25em;margin:1em 0 1.5em;max-width:760px;">
		<span style="display:inline-block;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#3858e9;background:#e7ecfb;padding:.15em .55em;border-radius:3px;margin-bottom:.6em;"><?php esc_html_e( 'Pro upgrade', 'jalma-inline-stock-editor' ); ?></span>
		<p style="margin:.2em 0 .8em;"><?php esc_html_e( 'CSV export and import for product stock data — filter by category, include variations, two-step preview-before-apply flow.', 'jalma-inline-stock-editor' ); ?></p>
		<p style="margin:0;">
			<a class="button" href="<?php echo esc_url( JISE_PRO_LANDING_URL ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Learn more →', 'jalma-inline-stock-editor' ); ?>
			</a>
		</p>
	</div>
	<?php
} );
