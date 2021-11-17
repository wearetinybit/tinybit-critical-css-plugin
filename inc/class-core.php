<?php
/**
 * Core functionality.
 *
 * @package TinyBit_Critical_Css
 */

namespace TinyBit_Critical_Css;

use WP_Error;

/**
 * Core functionality.
 */
class Core {

	/**
	 * Whether or not the header has been rendered.
	 *
	 * @var boolean
	 */
	private static $rendered_header;

	/**
	 * Whether or not the footer has been rendered.
	 *
	 * @var boolean
	 */
	private static $rendered_footer;

	/**
	 * Generates the critical CSS for a given URL.
	 *
	 * @param string $url URL to generate critical CSS for.
	 * @return mixed
	 */
	public static function generate( $url ) {
		if ( ! defined( 'TINYBIT_CRITICAL_CSS_SERVER' ) ) {
			return new WP_Error(
				'missing-constant',
				'TINYBIT_CRITICAL_CSS_SERVER constant must be defined to an instance of tinybit-critical-css-server.'
			);
		}

		$url_parts = wp_parse_url( $url );

		$get_flag_value = function( $assoc_args, $flag, $default = null ) {
			return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
		};

		$f              = function( $key ) use ( $url_parts, $get_flag_value ) {
			return $get_flag_value( $url_parts, $key, '' );
		};

		if ( isset( $url_parts['host'] ) ) {
			if ( isset( $url_parts['scheme'] ) && 'https' === strtolower( $url_parts['scheme'] ) ) {
				$_SERVER['HTTPS'] = 'on';
			}

			$_SERVER['HTTP_HOST'] = $url_parts['host'];
			if ( isset( $url_parts['port'] ) ) {
				$_SERVER['HTTP_HOST'] .= ':' . $url_parts['port'];
			}

			$_SERVER['SERVER_NAME'] = $url_parts['host'];
		}

		$_SERVER['REQUEST_URI']  = $f( 'path' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
		$_SERVER['SERVER_PORT']  = $get_flag_value( $url_parts, 'port', '80' );
		$_SERVER['QUERY_STRING'] = $f( 'query' );

		$config = null;
		foreach ( apply_filters( 'tinybit_critical_css_pages', [] ) as $page ) {
			if ( $url === $page['url'] ) {
				$config = $page;
				break;
			}
		}
		if ( ! $config ) {
			return new WP_Error(
				'missing-config',
				sprintf( 'No config found for %s', $url )
			);
		}
		if ( ! file_exists( $config['source'] ) ) {
			return new WP_Error(
				'missing-source',
				sprintf( 'Source CSS file does not exist: %s', $config['source'] )
			);
		}

		if ( ! is_dir( dirname( $config['critical'] ) ) ) {
			if ( ! wp_mkdir_p( dirname( $config['critical'] ) ) ) {
				return new WP_Error(
					'destination-error',
					sprintf( 'Unable to create directory for critical CSS: %s', str_replace( ABSPATH, '', $config['critical'] ) )
				);
			}
		}

		self::log( sprintf( 'Rendering WordPress output for %s', $url ) );

		$output = self::load_wordpress_with_template();

		$css = file_get_contents( $config['source'] );

		$output_size     = round( ( strlen( $output ) / 1000 ), 2 );
		$stylesheet_size = round( ( strlen( $css ) / 1000 ), 2 );
		self::log( sprintf( 'Posting WordPress output (%skb) and stylesheet (%skb) to %s', $output_size, $stylesheet_size, TINYBIT_CRITICAL_CSS_SERVER ) );
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
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['css'] ) ) {
				file_put_contents( $config['critical'], $body['css'] );
				$critical_size = round( ( strlen( $body['css'] ) / 1000 ), 2 );
				self::log( sprintf( 'Saved critical css (%skb) to %s', $critical_size, str_replace( ABSPATH, '', $config['critical'] ) ) );
			} else {
				return new WP_Error(
					'empty-response',
					'Critical CSS response is unexpectedly empty'
				);
			}
		} else {
			return new WP_Error(
				'unexpected-response',
				sprintf( 'Unexpected response from critical CSS server: %s (HTTP %d)', trim( wp_remote_retrieve_body( $response ) ), $code )
			);
		}
		return true;
	}

	/**
	 * Logs a response to output.
	 *
	 * @param string $message Message to render.
	 */
	private static function log( $message ) {
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::log( $message );
		}
	}

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private static function load_wordpress_with_template() {

		// Set up main_query main WordPress query.
		wp();

		define( 'WP_USE_THEMES', true );

		return self::get_rendered_template();
	}

	/**
	 * Returns the rendered template.
	 *
	 * @return string
	 */
	protected static function get_rendered_template() {
		ob_start();
		self::load_template();
		return ob_get_clean();
	}

	/**
	 * Copy-pasta of wp-includes/template-loader.php
	 */
	protected static function load_template() {
		// Template is normally loaded in global scope, so we need to replicate.
		foreach ( $GLOBALS as $key => $value ) {
			global ${$key}; // phpcs:ignore
			// PHPCompatibility.PHP.ForbiddenGlobalVariableVariable.NonBareVariableFound -- Syntax is updated to compatible with php 5 and 7.
		}

		do_action( 'template_redirect' );

		$template = false;
		// phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		// phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
		if ( is_404() && $template = get_404_template() ) :
		elseif ( is_search() && $template = get_search_template() ) :
		elseif ( is_front_page() && $template = get_front_page_template() ) :
		elseif ( is_home() && $template = get_home_template() ) :
		elseif ( is_post_type_archive() && $template = get_post_type_archive_template() ) :
		elseif ( is_tax() && $template = get_taxonomy_template() ) :
		elseif ( is_attachment() && $template = get_attachment_template() ) :
			remove_filter( 'the_content', 'prepend_attachment' );
		elseif ( is_single() && $template = get_single_template() ) :
		elseif ( is_page() && $template = get_page_template() ) :
		elseif ( is_category() && $template = get_category_template() ) :
		elseif ( is_tag() && $template = get_tag_template() ) :
		elseif ( is_author() && $template = get_author_template() ) :
		elseif ( is_date() && $template = get_date_template() ) :
		elseif ( is_archive() && $template = get_archive_template() ) :
		elseif ( is_comments_popup() && $template = get_comments_popup_template() ) :
		elseif ( is_paged() && $template = get_paged_template() ) :
		else :
			$template = get_index_template();
		endif;
		/**
		 * Filter the path of the current template before including it.
		 *
		 * @since 3.0.0
		 *
		 * @param string $template The path of the template to include.
		 */

		if ( $template = apply_filters( 'template_include', $template ) ) {
			$template_contents = file_get_contents( $template );
			$included_header   = false;
			$included_footer   = false;
			if ( false !== stripos( $template_contents, 'get_header();' ) ) {
				if ( ! isset( self::$rendered_header ) ) {
					// get_header() will render the first time but not subsequent.
					self::$rendered_header = true;
				} else {
					do_action( 'get_header', null );
					locate_template( 'header.php', true, false );
				}
				$included_header = true;
			}
			include( $template );
			if ( false !== stripos( $template_contents, 'get_footer();' ) ) {
				if ( ! isset( self::$rendered_footer ) ) {
					// get_footer() will render the first time but not subsequent.
					self::$rendered_footer = true;
				} else {
					do_action( 'get_footer', null );
					locate_template( 'footer.php', true, false );
				}
				$included_footer = true;
			}
			if ( $included_header && $included_footer ) {
				global $wp_scripts, $wp_styles;
				$wp_scripts->done = [];
				$wp_styles->done  = [];
			}
		}
		// phpcs:enable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		// phpcs:enable WordPress.CodeAnalysis.AssignmentInCondition.Found

		return;
	}

}
