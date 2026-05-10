<?php
/**
 * JHLSQ\Purchase — reusable LemonSqueezy purchase + auto-install flow
 * for free WordPress plugins that have a paid PRO add-on.
 *
 * Renders an in-admin upsell with a lemon.js overlay-checkout button
 * and a paste-the-key fallback. After Checkout.Success polls the LSQ
 * Order Bridge endpoint for up to 5s to fetch the license key, then
 * validates against LSQ's public /licenses/validate, downloads the
 * PRO zip from the configured update URL, installs and activates it,
 * and seeds the PRO plugin's license option so it picks up as
 * already-keyed on first load.
 *
 * Designed to be dropped into multiple free plugins. Constructor
 * config drives everything plugin-specific; no static state, safe to
 * instantiate once per consumer.
 *
 * Localization: the module does NOT call __() / _e() — all
 * user-facing strings are passed in via the `strings` config so the
 * consuming plugin can wrap them in its own text domain. This keeps
 * wp.org Plugin Check happy (it would flag any inline 'jhlsq-purchase'
 * domain inside another plugin's distribution).
 *
 * Canonical source: published/src/infra/jhlsq-purchase/.
 * Each consuming plugin bundles a copy at <plugin>/jhlsq-purchase/
 * synced at build time via publish.sh.
 */

namespace JHLSQ;

defined( 'ABSPATH' ) || exit;

if ( class_exists( __NAMESPACE__ . '\\Purchase' ) ) {
	return; // Another plugin already loaded a copy of this module.
}

class Purchase {

	/** @var array<string,mixed> */
	private $config;

	/**
	 * @param array $config Required keys:
	 *   - free_basename:        e.g. 'really-simple-under-construction/really-simple-under-construction.php'
	 *   - pro_basename:         e.g. 'really-simple-under-construction-pro/really-simple-under-construction-pro.php'
	 *   - pro_class_check:      e.g. 'RSUCP\\DesignPage' — used to detect if PRO is loaded
	 *   - pro_label:            human-readable name, e.g. 'Really Simple Under Construction PRO'
	 *   - checkout_url:         LSQ overlay-friendly buy URL
	 *   - download_url:         direct .zip URL on the update server
	 *   - bridge_base:          e.g. 'https://jonashjalmarsson.se/wp-json/lsq-bridge/v1'
	 *   - license_option:       e.g. 'lsq_<pro-slug>' — option key PRO reads
	 *   - license_page_url:     where to land after install, e.g. admin_url('admin.php?page=rsucp-license')
	 *   - settings_page_hook:   e.g. 'settings_page_rsuc-submenu-page' — for lemon.js enqueue
	 *   - after_heading_action: e.g. 'rsuc_render_after_heading' — where the upsell renders
	 *   - strings:              array of pre-translated strings (see below) — required keys:
	 *       'get_pro_link', 'pitch', 'get_pro_button', 'paste_summary', 'paste_placeholder',
	 *       'install_button', 'err_empty', 'err_invalid', 'err_network', 'err_install',
	 *       'err_limit', 'err_activate',
	 *       'js_thanks', 'js_license_found', 'js_poll_failed', 'js_poll_network',
	 *       'js_paste_prompt', 'install_perm_denied'
	 *
	 *   Optional (with defaults):
	 *   - landing_page_url:    if set, renders a "Read more" link next to Get PRO
	 *   - landing_link_text:   text for the landing link (default 'Read more →')
	 *   - install_action_name: admin-post action name (default 'jhlsq_purchase_install_pro')
	 */
	public function __construct( array $config ) {
		$defaults = [
			'install_action_name' => 'jhlsq_purchase_install_pro',
			'landing_page_url'    => '',
			'landing_link_text'   => 'Read more →',
			'strings'             => [],
		];
		$this->config = array_merge( $defaults, $config );
		$this->register_hooks();
	}

	private function register_hooks() {
		add_filter( 'plugin_action_links_' . $this->config['free_basename'], [ $this, 'add_pro_action_link' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_lemon_js' ] );
		add_action( $this->config['after_heading_action'], [ $this, 'render_upsell' ], 99 );
		add_action( 'admin_post_' . $this->config['install_action_name'], [ $this, 'handle_install' ] );
	}

	private function s( $key, $fallback = '' ) {
		return isset( $this->config['strings'][ $key ] ) ? (string) $this->config['strings'][ $key ] : $fallback;
	}

	/**
	 * Detect if the PRO add-on is currently loaded by checking for one
	 * of its classes. class_exists is cheap and avoids depending on
	 * is_plugin_active() which requires wp-admin/includes/plugin.php.
	 */
	private function pro_active() {
		$cls = $this->config['pro_class_check'];
		return $cls && class_exists( $cls );
	}

	/**
	 * Append a "Get Pro" link to the Free plugin's row on Plugins.php.
	 * Hidden when PRO is already loaded.
	 */
	public function add_pro_action_link( $links ) {
		if ( $this->pro_active() ) {
			return $links;
		}
		$pro_link = '<a href="' . esc_url( $this->config['checkout_url'] ) . '" target="_blank" rel="noopener">'
			. esc_html( $this->s( 'get_pro_link', 'Get Pro' ) )
			. '</a>';
		array_push( $links, $pro_link );
		return $links;
	}

	/**
	 * Enqueue lemon.js + the upsell-card JS on Free's settings page only.
	 * No point loading either elsewhere or once PRO is active.
	 */
	public function enqueue_lemon_js( $hook ) {
		if ( $hook !== $this->config['settings_page_hook'] ) {
			return;
		}
		if ( $this->pro_active() ) {
			return;
		}
		// Pin a version so wp.org Plugin Check is happy. LSQ rolls
		// their own cache-busting on assets.lemonsqueezy.com so the
		// actual value here is just a marker for our own enqueue.
		wp_enqueue_script(
			'lemonsqueezy',
			'https://assets.lemonsqueezy.com/lemon.js',
			[],
			'1.0',
			true
		);
		wp_enqueue_script(
			'jhlsq-purchase-upsell',
			plugins_url( 'upsell.js', __FILE__ ),
			[ 'lemonsqueezy' ],
			'1.0.0',
			true
		);
		wp_localize_script(
			'jhlsq-purchase-upsell',
			'jhlsqUpsell',
			[
				'bridgeBase'  => $this->config['bridge_base'],
				'expandLabel' => $this->s( 'expand_label', 'Click to expand' ),
				'text'        => [
					'thanks'       => $this->s( 'js_thanks' ),
					'licenseFound' => $this->s( 'js_license_found' ),
					'pollFailed'   => $this->s( 'js_poll_failed' ),
					'pollNetwork'  => $this->s( 'js_poll_network' ),
					'pastePrompt'  => $this->s( 'js_paste_prompt' ),
				],
			]
		);
	}

	/**
	 * Render the PRO upsell on Free's settings page. The block is a
	 * pitch + an always-available "Already have a license? Paste it
	 * here" expander. JS swaps in an installing UI on Checkout.Success.
	 */
	public function render_upsell() {
		if ( $this->pro_active() ) {
			return;
		}

		$error = isset( $_GET['jhlsq_install_error'] ) ? sanitize_key( wp_unslash( $_GET['jhlsq_install_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved = isset( $_GET['jhlsq_license_saved'] ) && '1' === $_GET['jhlsq_license_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_messages = [
			'empty'    => $this->s( 'err_empty' ),
			'invalid'  => $this->s( 'err_invalid' ),
			'network'  => $this->s( 'err_network' ),
			'install'  => $this->s( 'err_install' ),
			'limit'    => $this->s( 'err_limit', 'This license has reached its activation limit. Deactivate it from another site in your LemonSqueezy account first, then try again.' ),
			'activate' => $this->s( 'err_activate', 'License activation failed. Please try again or contact support.' ),
		];

		// License-saved success state (wp.org build path: license activated against
		// LSQ + saved locally; user installs the PRO add-on manually). Renders in
		// place of the upsell so the next step is unmistakable.
		if ( $saved ) {
			$pro_label    = isset( $this->config['pro_label'] ) ? (string) $this->config['pro_label'] : 'PRO';
			$download_url = isset( $this->config['download_url'] ) ? (string) $this->config['download_url'] : '';
			?>
			<div class="jhlsq-pro-saved" style="background:#eff7ee;border:1px solid #b9dcb6;border-radius:6px;padding:1em 1.25em;margin:1em 0 1.5em;max-width:760px;">
				<p style="margin:0 0 .5em;font-weight:600;color:#22592a;">
					<?php echo esc_html( $this->s( 'saved_heading', 'License activated. One step to go.' ) ); ?>
				</p>
				<p style="margin:0 0 .8em;">
					<?php
					echo esc_html(
						sprintf(
							/* %s = Pro plugin label */
							$this->s( 'saved_intro', 'Your license is saved on this site. To finish, install %s manually:' ),
							$pro_label
						)
					);
					?>
				</p>
				<ol style="margin:.2em 0 .8em 1.4em;">
					<li><?php echo esc_html( $this->s( 'saved_step1', 'Download the Pro plugin zip from your purchase email — or use the button below.' ) ); ?></li>
					<li><?php
						/* translators: %s: WordPress admin menu path */
						echo esc_html( sprintf( $this->s( 'saved_step2', 'In WordPress, go to %s.' ), 'Plugins → Add New Plugin → Upload Plugin' ) );
					?></li>
					<li><?php echo esc_html( $this->s( 'saved_step3', 'Upload the zip and activate the plugin. The license you just saved will pick up automatically.' ) ); ?></li>
				</ol>
				<?php if ( '' !== $download_url ) : ?>
					<p style="margin:0;">
						<a class="button button-primary" href="<?php echo esc_url( $download_url ); ?>" rel="noopener">
							<?php echo esc_html( $this->s( 'saved_download_button', 'Download Pro zip' ) ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}
		?>
		<div id="jhlsq-pro-upsell" class="jhlsq-pro-upsell" style="position:relative;background:#f5f7ff;border:1px solid #d6dffd;border-radius:6px;padding:1em 1.25em;margin:1em 0 1.5em;max-width:760px;">
			<button type="button" id="jhlsq-pro-collapse" aria-label="<?php echo esc_attr( $this->s( 'collapse_label', 'Hide' ) ); ?>" title="<?php echo esc_attr( $this->s( 'collapse_label', 'Hide' ) ); ?>" style="position:absolute;top:.4em;right:.55em;background:none;border:none;font-size:18px;line-height:1;cursor:pointer;color:#94a3c8;padding:.1em .35em;">×</button>
			<span id="jhlsq-pro-pill" style="display:inline-block;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#3858e9;background:#e7ecfb;padding:.15em .55em;border-radius:3px;margin-bottom:.6em;"><?php echo esc_html( $this->s( 'upsell_label', 'Pro upgrade' ) ); ?></span>
			<div id="jhlsq-pro-content">
				<p style="margin:.2em 0 .6em;"><?php echo esc_html( $this->config['pitch_text'] ?? $this->s( 'pitch' ) ); ?></p>
				<p style="margin:0 0 .6em;">
					<a class="lemonsqueezy-button button button-primary" href="<?php echo esc_url( $this->config['checkout_url'] ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( $this->s( 'get_pro_button', 'Get PRO →' ) ); ?>
					</a>
					<?php if ( ! empty( $this->config['landing_page_url'] ) ) : ?>
						<a href="<?php echo esc_url( $this->config['landing_page_url'] ); ?>" target="_blank" rel="noopener" style="margin-left:1em;">
							<?php echo esc_html( $this->config['landing_link_text'] ); ?>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $error && isset( $error_messages[ $error ] ) ) : ?>
						<span style="color:#d63638;margin-left:1em;"><?php echo esc_html( $error_messages[ $error ] ); ?></span>
					<?php endif; ?>
				</p>
				<p id="jhlsq-pro-status" hidden style="margin:0 0 .6em;"></p>
				<details id="jhlsq-pro-paste" style="margin-top:.4em;">
					<summary style="cursor:pointer;color:#3858e9;font-size:.95em;"><?php echo esc_html( $this->s( 'paste_summary', 'Already have a license key? Paste it here' ) ); ?></summary>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:.6em;display:flex;gap:.5em;flex-wrap:wrap;">
						<input type="hidden" name="action" value="<?php echo esc_attr( $this->config['install_action_name'] ); ?>" />
						<?php wp_nonce_field( $this->config['install_action_name'], 'jhlsq_nonce' ); ?>
						<input type="text" name="license_key" id="jhlsq-license-key-input" required placeholder="<?php echo esc_attr( $this->s( 'paste_placeholder', 'License key from your purchase email' ) ); ?>" style="flex:1;min-width:280px;" />
						<button type="submit" class="button button-primary"><?php echo esc_html( $this->s( 'install_button', 'Install PRO' ) ); ?></button>
					</form>
				</details>
			</div>
		</div>
		<?php
		// Upsell-card behavior (collapse, LSQ overlay polling, license auto-submit)
		// lives in upsell.js — enqueued via wp_enqueue_script in enqueue_lemon_js().
	}

	/**
	 * Receive a license key (auto-submitted from JS or pasted by the
	 * admin), validate it against LSQ's public licenses API, download +
	 * install the PRO plugin from the configured update URL, activate
	 * it, and seed the license option so PRO's own License tab shows
	 * as already-keyed on first load.
	 */
	public function handle_install() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html( $this->s( 'install_perm_denied', 'You do not have permission to install plugins.' ) ) );
		}
		check_admin_referer( $this->config['install_action_name'], 'jhlsq_nonce' );

		$referer = wp_get_referer();
		$back    = $referer ? $referer : admin_url();

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( '' === $license_key ) {
			wp_safe_redirect( add_query_arg( 'jhlsq_install_error', 'empty', $back ) );
			exit;
		}

		$response = wp_remote_post(
			'https://api.lemonsqueezy.com/v1/licenses/validate',
			[
				'timeout' => 15,
				'headers' => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => [ 'license_key' => $license_key ],
			]
		);
		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( add_query_arg( 'jhlsq_install_error', 'network', $back ) );
			exit;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['valid'] ) ) {
			wp_safe_redirect( add_query_arg( 'jhlsq_install_error', 'invalid', $back ) );
			exit;
		}

		// Activate the license against LSQ before installing PRO. Surfacing
		// "activation limit reached" here means the user sees the real error
		// during install, not three steps later when they manually click
		// Activate on PRO's License tab. On success we save state straight
		// to status=active with the real instance_id — no pending state.
		$activate_response = wp_remote_post(
			'https://api.lemonsqueezy.com/v1/licenses/activate',
			[
				'timeout' => 15,
				'headers' => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => [
					'license_key'   => $license_key,
					'instance_name' => get_site_url(),
				],
			]
		);
		if ( is_wp_error( $activate_response ) ) {
			wp_safe_redirect( add_query_arg( 'jhlsq_install_error', 'network', $back ) );
			exit;
		}
		$activate_body = json_decode( wp_remote_retrieve_body( $activate_response ), true );
		if ( empty( $activate_body['activated'] ) ) {
			$msg  = isset( $activate_body['error'] ) ? (string) $activate_body['error'] : '';
			$code = ( '' !== $msg && false !== stripos( $msg, 'limit' ) ) ? 'limit' : 'activate';
			wp_safe_redirect( add_query_arg( 'jhlsq_install_error', $code, $back ) );
			exit;
		}
		$instance_id = isset( $activate_body['instance']['id'] ) ? (string) $activate_body['instance']['id'] : '';
		$expires_at  = $activate_body['license_key']['expires_at'] ?? null;

		// Save the license straight away. Pro's License tab picks this up on
		// first load — no pending state. Done before any (optional) auto-install
		// so a failed install still leaves a usable license behind.
		update_option(
			$this->config['license_option'],
			[
				'key'         => $license_key,
				'instance_id' => $instance_id,
				'status'      => 'active',
				'expires_at'  => $expires_at,
				'last_check'  => time(),
			]
		);

		/* @wporg-strip-start */
		// Self-hosted-only convenience: download + install + activate the Pro
		// add-on automatically right after the license is saved, so the user
		// lands on Pro's License tab pre-keyed. Stripped from wp.org build —
		// wp.org guidelines forbid plugins changing the activation status of
		// other plugins. wp.org users follow the manual download flow below.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		WP_Filesystem();

		$upgrader  = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$installed = $upgrader->install( $this->config['download_url'], [ 'clear_destination' => true ] );
		if ( ! is_wp_error( $installed ) && $installed ) {
			$activate = activate_plugin( $this->config['pro_basename'] );
			if ( ! is_wp_error( $activate ) ) {
				wp_safe_redirect( add_query_arg( 'jhlsq_pro_installed', '1', $this->config['license_page_url'] ) );
				exit;
			}
		}
		/* @wporg-strip-end */

		// wp.org build (auto-install stripped) OR auto-install failed: send the
		// user back to the upsell page with a "license saved, here's the
		// download link + manual install instructions" success state.
		wp_safe_redirect( add_query_arg( 'jhlsq_license_saved', '1', $back ) );
		exit;
	}
}
