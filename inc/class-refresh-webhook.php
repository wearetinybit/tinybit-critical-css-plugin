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

		$pages         = Core::get_page_configs();
		$urls          = array_keys( $pages );
		$existing      = Core::get_queue();
		$urls_to_add   = array_diff( $urls, $existing );
		$count         = count( $urls_to_add );

		if ( $count > 0 ) {
			Core::add_to_queue( $urls_to_add );
			Core::schedule_queue_processing();
		}

		status_header( 200 );
		echo sprintf( 'Queued %d refresh jobs.', $count );
		exit;
	}
}
