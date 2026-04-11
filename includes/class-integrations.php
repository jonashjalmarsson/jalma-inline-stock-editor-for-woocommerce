<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soft integration points with other Jalma plugins.
 *
 * This class adds lightweight cross-promotion links between Jalma Quick Stock
 * and Jalma Category Notifications for WooCommerce — with no hard dependencies.
 * If the companion plugin isn't installed, nothing is injected.
 *
 * Policy: show an "also installed" note when both are active, and a "you might
 * also like" suggestion when the companion is missing. Never nag, never block.
 */
class JQSW_Integrations {

	const CATEGORY_NOTIFICATIONS_BASENAME = 'jalma-wc-category-notifications/jalma-wc-category-notifications.php';

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'maybe_show_integration_notice' ] );
	}

	public static function is_category_notifications_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::CATEGORY_NOTIFICATIONS_BASENAME );
	}

	public function maybe_show_integration_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'woocommerce_page_jqsw-quick-stock' ) {
			return;
		}

		if ( self::is_category_notifications_active() ) {
			$notifications_url = admin_url( 'admin.php?page=jwccn-settings' );
			echo '<div class="notice notice-info"><p>' .
				wp_kses(
					sprintf(
						/* translators: %s: URL to Category Notifications settings */
						__( '💡 You\'re also using <strong>Jalma Category Notifications for WooCommerce</strong> — stock updates you make here will trigger your configured notification rules automatically. <a href="%s">Manage rules</a>.', 'jalma-quick-stock-for-woocommerce' ),
						esc_url( $notifications_url )
					),
					[ 'strong' => [], 'a' => [ 'href' => [] ] ]
				) .
				'</p></div>';
		} else {
			echo '<div class="notice notice-info is-dismissible"><p>' .
				wp_kses(
					sprintf(
						/* translators: %s: URL to wp.org plugin page */
						__( '💡 Want email alerts for low stock, routed to different recipients per category? Try <strong>Jalma Category Notifications for WooCommerce</strong> — a companion plugin by the same author. <a href="%s" target="_blank" rel="noopener">Learn more</a>.', 'jalma-quick-stock-for-woocommerce' ),
						'https://wordpress.org/plugins/jalma-wc-category-notifications/'
					),
					[ 'strong' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
				) .
				'</p></div>';
		}
	}
}
