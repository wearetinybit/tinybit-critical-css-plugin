<?php
/**
 * Manages the refresh webhook.
 *
 * @package TinyBit_Critical_Css
 */

namespace TinyBit_Critical_Css;

/**
 * Manages the refresh webhook.
 */
class Refresh_Webhook {

	/**
	 * Name of the webhook key.
	 *
	 * @var string
	 */
	const KEY_NAME = 'tinybit_critical_css_webhook_key';

	/**
	 * Gets the path to the refresh webhook.
	 *
	 * @return string
	 */
	public static function get_path() {
		$key = get_option( self::KEY_NAME );
		if ( ! $key ) {
			$key = substr( md5( mt_rand() ), 0, 8 );
			update_option( self::KEY_NAME, $key );
			$key = get_option( self::KEY_NAME );
		}
		return 'tinybit-critical-css-refresh/' . $key;
	}

	/**
	 * Handles webhook to queue refresh jobs.
	 */
	public static function action_template_redirect() {
		global $wp;

		if ( ! get_option( self::KEY_NAME )
			|| self::get_path() !== $wp->request ) {
			return;
		}
		$count = 0;
		$pages = apply_filters( 'tinybit_critical_css_pages', [] );
		foreach ( $pages as $page ) {
			$event = 'tinybit_generate_critical_css';
			$args  = [ 'url' => $page['url'] ];
			if ( wp_next_scheduled( $event, $args ) ) {
				continue;
			}
			wp_schedule_single_event(
				time(),
				$event,
				$args
			);
			$count++;
		}

		status_header( 200 );
		echo sprintf( 'Queued %d refresh jobs.', $count );
		exit;
	}

	/**
	 * Handles a cron event to generate critical CSS.
	 *
	 * @param string $url URL to generate critical CSS for.
	 */
	public static function handle_tinybit_generate_critical_css( $url ) {
		if ( ! defined( 'WP_CLI' ) ) {
			return;
		}
		\WP_CLI::runcommand(
			sprintf( 'tinybit-critical-css generate --url=%s', $url ),
			[
				'launch' => true,
			]
		);
	}

}
