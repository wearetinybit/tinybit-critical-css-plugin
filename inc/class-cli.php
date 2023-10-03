<?php
/**
 * TinyBit Critical CSS Utilities
 *
 * @package TinyBit_Critical_Css
 */

namespace TinyBit_Critical_Css;

use WP_CLI;

/**
 * TinyBit Critical CSS Utilities.
 */
class CLI {

	/**
	 * Generate critical CSS for a given URL.
	 *
	 * ## OPTIONS
	 *
	 * --url=<url>
	 * : URL to generate critical CSS for.
	 */
	public function generate( $_, $assoc_args ) {

		$url = isset( $assoc_args['url'] ) ? $assoc_args['url'] : WP_CLI::get_config( 'url' );
		$ret = Core::generate( $url );
		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret );
		}
		WP_CLI::success( 'Generated critical CSS' );
	}

	/**
	 * Clears all critical CSS files.
	 */
	public function clear() {
		foreach ( Core::get_page_configs() as $page ) {
			if ( file_exists( $page['critical'] ) ) {
				unlink( $page['critical'] );
				WP_CLI::log( sprintf( 'Deleted critical css at %s', str_replace( ABSPATH, '', $page['critical'] ) ) );
			}
		}
		WP_CLI::success( 'Cleared critical CSS' );
	}

	/**
	 * Generates a webhook that can be used for triggering a refresh.
	 *
	 * @subcommand refresh-webhook
	 */
	public function refresh_webhook() {
		WP_CLI::log( sprintf( home_url( Refresh_Webhook::get_path() ) ) );
	}
}
