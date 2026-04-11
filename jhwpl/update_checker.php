<?php
namespace JHWPL;
/*** VERSION 2.0 */
/* Based on https://developer.wordpress.org/plugins/plugin-basics/self-hosted-updates/ */
defined( 'ABSPATH' ) or die( '' );

if ( ! class_exists( 'JHWPL\UpdateChecker' ) ) {

	class UpdateChecker {

		private $version;
		private $basename_dir;
		private $basename_file;
		private $info_url;
		private $slug;
		private $cache_key;
		private $license_check;

		public function __construct( $args = [] ) {
			if ( $this->check_args( $args ) ) {
				$this->cache_key     = 'jhwpl_' . md5( $this->info_url );
				$this->license_check = $args['license_check'] ?? null;
				$this->init();
			}
		}

		private function check_args( $args ) {
			$required = [ 'version', 'basename_dir', 'basename_file', 'info_url', 'slug' ];
			foreach ( $required as $key ) {
				if ( empty( $args[ $key ] ) ) {
					return false;
				}
			}
			$this->version       = $args['version'];
			$this->basename_dir  = $args['basename_dir'];
			$this->basename_file = $args['basename_file'];
			$this->info_url      = $args['info_url'];
			$this->slug          = $args['slug'];
			return true;
		}

		private function init() {
			add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'update' ] );
			add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
		}

		/**
		 * Fetch remote plugin info. Cached for 6 hours.
		 * Cache is cleared when WP runs a fresh update check.
		 */
		private function fetch_remote() {
			$cached = get_transient( $this->cache_key );

			if ( false !== $cached && '' !== $cached ) {
				return json_decode( $cached );
			}

			$remote = wp_remote_get( $this->info_url, [
				'timeout' => 10,
				'headers' => [ 'Accept' => 'application/json' ],
			] );

			if (
				is_wp_error( $remote )
				|| 200 !== wp_remote_retrieve_response_code( $remote )
				|| empty( wp_remote_retrieve_body( $remote ) )
			) {
				return false;
			}

			$body = wp_remote_retrieve_body( $remote );
			set_transient( $this->cache_key, $body, 6 * HOUR_IN_SECONDS );

			return json_decode( $body );
		}

		/**
		 * Plugin information popup (View Details link).
		 */
		public function info( $res, $action, $args ) {
			if ( 'plugin_information' !== $action ) {
				return $res;
			}

			if ( $this->basename_dir !== $args->slug ) {
				return $res;
			}

			$remote = $this->fetch_remote();
			if ( ! $remote ) {
				return $res;
			}

			$res = new \stdClass();

			$res->name           = $remote->name ?? '';
			$res->slug           = $remote->slug ?? '';
			$res->version        = $remote->version ?? '';
			$res->tested         = $remote->tested ?? '';
			$res->requires       = $remote->requires ?? '';
			$res->author         = $remote->author ?? '';
			$res->author_profile = $remote->author_profile ?? '';
			$res->download_link  = $remote->download_url ?? '';
			$res->trunk          = $remote->download_url ?? '';
			$res->requires_php   = $remote->requires_php ?? '';
			$res->last_updated   = $remote->last_updated ?? '';

			if ( ! empty( $remote->sections ) ) {
				$res->sections = [
					'description'  => $remote->sections->description ?? '',
					'installation' => $remote->sections->installation ?? '',
					'changelog'    => $remote->sections->changelog ?? '',
				];
			}

			if ( ! empty( $remote->banners ) ) {
				$res->banners = [
					'low'  => $remote->banners->low ?? '',
					'high' => $remote->banners->high ?? '',
				];
			}

			return $res;
		}

		/**
		 * Check for updates. Runs when WP builds the update_plugins transient
		 * (every 12h via cron, or manually via "Check Again").
		 * Always fetches fresh data — this hook only fires on actual update checks.
		 */
		public function update( $transient ) {
			if ( $this->license_check && ! call_user_func( $this->license_check ) ) {
				return $transient;
			}

			// Clear our cache so we always get fresh data during an update check.
			delete_transient( $this->cache_key );

			$remote = $this->fetch_remote();

			if (
				$remote
				&& ! empty( $remote->version )
				&& version_compare( $this->version, $remote->version, '<' )
				&& version_compare( $remote->requires ?? '0', get_bloginfo( 'version' ), '<=' )
				&& version_compare( $remote->requires_php ?? '0', PHP_VERSION, '<=' )
			) {
				$res              = new \stdClass();
				$res->slug        = $remote->slug ?? '';
				$res->plugin      = $this->basename_file;
				$res->new_version = $remote->version;
				$res->tested      = $remote->tested ?? '';
				$res->package     = $remote->download_url ?? '';

				$transient->response[ $res->plugin ] = $res;
			}

			return $transient;
		}

		public function purge( $upgrader, $options ) {
			if (
				'update' === ( $options['action'] ?? '' )
				&& 'plugin' === ( $options['type'] ?? '' )
			) {
				delete_transient( $this->cache_key );
			}
		}
	}
}
