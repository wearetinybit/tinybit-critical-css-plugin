<?php
/**
 * Plugin Name:     TinyBit Critical CSS
 * Plugin URI:      https://github.com/wearetinybit/tinybit-critical-css-plugin
 * Description:     Works with tinybit-critical-css-server to generate and serve inline critical CSS.
 * Author:          TinyBit
 * Author URI:      https://tinybit.com
 * Text Domain:     tinybit-critical-css
 * Domain Path:     /languages
 * Version:         0.2.2
 *
 * @package         Tinybit_Critical_Css
 */

/**
 * Register the class autoloader
 */
spl_autoload_register(
	function ( $class_name ) {
		$class_name = ltrim( $class_name, '\\' );
		if ( 0 !== stripos( $class_name, 'TinyBit_Critical_Css\\' ) ) {
			return;
		}

		$parts = explode( '\\', $class_name );
		array_shift( $parts ); // Don't need "TinyBit_Critical_Css".
		$last    = array_pop( $parts ); // File should be 'class-[...].php'.
		$last    = 'class-' . $last . '.php';
		$parts[] = $last;
		$file    = __DIR__ . '/inc/' . str_replace( '_', '-', strtolower( implode( '/', $parts ) ) );
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

add_filter(
	'style_loader_tag',
	array(
		'TinyBit_Critical_Css\Assets',
		'filter_style_loader_tag',
	),
	10,
	2
);

add_action(
	'template_redirect',
	array(
		'TinyBit_Critical_Css\Refresh_Webhook',
		'action_template_redirect',
	)
);

add_action(
	'tinybit_generate_critical_css',
	array(
		'TinyBit_Critical_Css\Refresh_Webhook',
		'handle_tinybit_generate_critical_css',
	)
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tinybit-critical-css', 'TinyBit_Critical_Css\CLI' );
}
