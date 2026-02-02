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
		$pages = Core::get_page_configs();
		foreach ( $pages as $url => $config ) {
			$event = 'tinybit_generate_critical_css';
			$args  = [ 'url' => $url ];
			if ( wp_next_scheduled( $event, $args ) ) {
				continue;
			}
			wp_schedule_single_event(
				time(),
				$event,
				$args
			);
			++$count;
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
		$ret   = Core::generate( $url );
		$email = apply_filters( 'tinybit_critical_css_cron_email', '', $ret );
		if ( $email ) {
			$timestamp = gmdate( 'Y-m-d H:i:s' );
			$base      = is_wp_error( $ret ) ? 'Error generating critical CSS for %s [%s]' : 'Successfully generated critical css for %s [%s]';
			$subject   = sprintf( $base, $url, $timestamp );
			if ( is_wp_error( $ret ) ) {
				$body = Core::get_log_messages() . PHP_EOL . $ret->get_error_message();
			} else {
				$body = Core::get_log_messages();
			}
			wp_mail(
				$email,
				$subject,
				$body
			);
		}

		self::maybe_send_slack_notification( $url, $ret );

		Core::clear_log_messages();
	}

	/**
	 * Sends a Slack notification if a webhook URL is configured.
	 *
	 * @param string          $url URL that was processed.
	 * @param string|WP_Error $ret Result of the critical CSS generation.
	 */
	private static function maybe_send_slack_notification( $url, $ret ) {
		$webhook_url = apply_filters( 'tinybit_critical_css_slack_webhook', '' );
		if ( ! $webhook_url ) {
			return;
		}

		$is_error = is_wp_error( $ret );
		$status   = $is_error ? 'Error' : 'Success';
		$color    = $is_error ? 'danger' : 'good';
		$title    = sprintf( 'Critical CSS %s: %s', $status, $url );

		$log_messages = Core::get_log_messages();
		$text         = $log_messages;
		if ( $is_error ) {
			$text .= PHP_EOL . $ret->get_error_message();
		}

		$payload = [
			'attachments' => [
				[
					'fallback'  => $title,
					'color'     => $color,
					'title'     => $title,
					'text'      => $text,
					'footer'    => 'TinyBit Critical CSS',
					'ts'        => time(),
					'mrkdwn_in' => [ 'text' ],
				],
			],
		];

		wp_remote_post(
			$webhook_url,
			[
				'body'     => wp_json_encode( $payload ),
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'timeout'  => 10,
				'blocking' => false,
			]
		);
	}
}
