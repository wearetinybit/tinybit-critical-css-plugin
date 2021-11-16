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

		$config = null;
		foreach ( apply_filters( 'tinybit_critical_css_pages', [] ) as $page ) {
			if ( empty( $page['when'] ) || ! is_callable( $page['when'] ) ) {
				continue;
			}
			if ( $page['when']() ) {
				$config = $page;
				break;
			}
		}

		if ( ! empty( $config['handle'] )
			&& $handle === $config['handle']
			&& ! empty( $config['critical'] )
			&& file_exists( $config['critical'] ) ) {
			$inline = file_get_contents( $config['critical'] );
			$tag    = preg_replace( '#media=[\'"]all[\'"]#', 'media="print" onload="this.media=\'all\'; this.onload=null;"', $tag );
			$tag    = '<style>' . $inline . '</style>' . PHP_EOL . $tag;
		}
		return $tag;
	}

}
