<?php
/**
 * Manages asset modifications.
 *
 * @package TinyBit_Critical_Css
 */

namespace TinyBit_Critical_Css;

/**
 * Manages asset modifications.
 */
class Assets {

	/**
	 * Inline specific styles instead of enqueueing them.
	 *
	 * @param string $tag    Style to be printed.
	 * @param string $handle Handle of the style.
	 */
	public static function filter_style_loader_tag( $tag, $handle ) {

		$config     = null;
		$config_url = null;
		foreach ( Core::get_page_configs() as $url => $page ) {
			if ( empty( $page['when'] ) || ! is_callable( $page['when'] ) ) {
				continue;
			}
			if ( $page['when']() ) {
				$config     = $page;
				$config_url = $url;
				break;
			}
		}

		if ( empty( $config['handle'] ) || $handle !== $config['handle'] ) {
			return $tag;
		}

		$inline = null;

		if ( Core::use_database_storage() ) {
			$option_name = Core::get_option_name_for_url( $config_url );
			$inline      = get_option( $option_name );
		} elseif ( ! empty( $config['critical'] ) && file_exists( $config['critical'] ) ) {
			$inline = file_get_contents( $config['critical'] );
		}

		if ( $inline ) {
			$tag = preg_replace( '#media=[\'"]all[\'"]#', 'media="print" onload="this.media=\'all\'; this.onload=null;"', $tag );
			$tag = '<style>' . $inline . '</style>' . PHP_EOL . $tag;
		}

		return $tag;
	}
}
