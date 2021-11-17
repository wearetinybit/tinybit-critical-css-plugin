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
	public function generate() {

		if ( ! defined( 'TINYBIT_CRITICAL_CSS_SERVER' ) ) {
			WP_CLI::error( 'TINYBIT_CRITICAL_CSS_SERVER constant must be defined to an instance of tinybit-critical-css-server.' );
		}

		$config = null;
		$url    = WP_CLI::get_config( 'url' );
		foreach ( apply_filters( 'tinybit_critical_css_pages', [] ) as $page ) {
			if ( $url === $page['url'] ) {
				$config = $page;
				break;
			}
		}
		if ( ! $config ) {
			WP_CLI::error( sprintf( 'No config found for %s', $url ) );
		}
		if ( ! file_exists( $config['source'] ) ) {
			WP_CLI::error( sprintf( 'Source CSS file does not exist: %s', $config['source'] ) );
		}

		if ( ! is_dir( dirname( $config['critical'] ) ) ) {
			if ( ! wp_mkdir_p( dirname( $config['critical'] ) ) ) {
				WP_CLI::error( sprintf( 'Unable to create directory for critical CSS: %s', str_replace( ABSPATH, '', $config['critical'] ) ) );
			}
		}

		WP_CLI::log( sprintf( 'Rendering WordPress output for %s', $url ) );

		$output = self::load_wordpress_with_template();

		$css = file_get_contents( $config['source'] );

		WP_CLI::log( sprintf( 'Posting WordPress output and stylesheet to %s', TINYBIT_CRITICAL_CSS_SERVER ) );
		$response = wp_remote_post(
			TINYBIT_CRITICAL_CSS_SERVER,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => json_encode(
					array(
						'css'  => $css,
						'html' => $output,
					)
				),
				'timeout' => 60,
			)
		);
		$code     = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $code ) ) {
			WP_CLI::error( $code );
		}
		if ( 200 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['css'] ) ) {
				file_put_contents( $config['critical'], $body['css'] );
				WP_CLI::log( sprintf( 'Saved critical css to %s', str_replace( ABSPATH, '', $config['critical'] ) ) );
			} else {
				WP_CLI::error( 'Critical CSS response is unexpectedly empty' );
			}
		} else {
			WP_CLI::error( sprintf( 'Unexpected response from critical CSS server (HTTP %d)', $code ) );
		}
		WP_CLI::success( 'Generated critical CSS' );
	}

	/**
	 * Clears all critical CSS files.
	 */
	public function clear() {
		foreach ( apply_filters( 'tinybit_critical_css_pages', [] ) as $page ) {
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

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private static function load_wordpress_with_template() {

		// Set up main_query main WordPress query.
		wp();

		define( 'WP_USE_THEMES', true );

		// Template is normally loaded in global scope, so we need to replicate.
		foreach ( $GLOBALS as $key => $value ) {
			global ${$key}; // phpcs:ignore
			// PHPCompatibility.PHP.ForbiddenGlobalVariableVariable.NonBareVariableFound -- Syntax is updated to compatible with php 5 and 7.
		}

		ob_start();
		require_once ABSPATH . WPINC . '/template-loader.php';
		return ob_get_clean();
	}

}
