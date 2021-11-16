<?php
/**
 * Plugin Name:     Tinybit Critical Css
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     tinybit-critical-css
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Tinybit_Critical_Css
 */

/**
 * Register the class autoloader
 */
spl_autoload_register(
	function( $class ) {
		$class = ltrim( $class, '\\' );
		if ( 0 !== stripos( $class, 'TinyBit_Critical_Css\\' ) ) {
			return;
		}

		$parts = explode( '\\', $class );
		array_shift( $parts ); // Don't need "TinyBit_Critical_Css".
		$last    = array_pop( $parts ); // File should be 'class-[...].php'.
		$last    = 'class-' . $last . '.php';
		$parts[] = $last;
		$file    = dirname( __FILE__ ) . '/inc/' . str_replace( '_', '-', strtolower( implode( '/', $parts ) ) );
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

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tinybit-critical-css', 'TinyBit_Critical_Css\CLI' );
}
