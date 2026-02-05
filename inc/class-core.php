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
	 * Start time
	 *
	 * @var integer
	 */
	private static $start_time;

	/**
	 * Records log messages.
	 *
	 * @var string
	 */
	private static $log_messages = '';

	/**
	 * Generates the critical CSS for a given URL.
	 *
	 * @param string $url URL to generate critical CSS for.
	 * @return mixed
	 */
	public static function generate( $url ) {

		self::$start_time = microtime( true );

		if ( ! defined( 'TINYBIT_CRITICAL_CSS_SERVER' ) ) {
			return new WP_Error(
				'missing-constant',
				'TINYBIT_CRITICAL_CSS_SERVER constant must be defined to an instance of tinybit-critical-css-server.'
			);
		}

		$url_parts = wp_parse_url( $url );

		$get_flag_value = function ( $assoc_args, $flag, $default_value = null ) {
			return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default_value;
		};

		$f = function ( $key ) use ( $url_parts, $get_flag_value ) {
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

		$config = self::get_page_config_for_url( $url );
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

		if ( ! self::use_database_storage() && ! wp_mkdir_p( dirname( $config['critical'] ) ) ) {
			return new WP_Error(
				'destination-error',
				sprintf( 'Unable to create directory for critical CSS: %s', str_replace( ABSPATH, '', $config['critical'] ) )
			);
		}

		self::log( sprintf( 'Rendering WordPress output for %s [%s]', $url, self::format_timestamp( microtime( true ) - self::$start_time ) ) );

		/**
		 * Filter the HTML output to be used for critical CSS.
		 *
		 * @param string $output HTML output.
		 * @param string $url URL to generate critical CSS for.
		 */
		$output = apply_filters( 'tinybit_critical_css_html', self::load_wordpress_with_template(), $url );

		$css = file_get_contents( $config['source'] );

		$output_size     = round( ( strlen( $output ) / 1000 ), 2 );
		$stylesheet_size = round( ( strlen( $css ) / 1000 ), 2 );
		self::log( sprintf( 'Posting WordPress output (%skb) and stylesheet (%skb) to %s [%s]', $output_size, $stylesheet_size, TINYBIT_CRITICAL_CSS_SERVER, self::format_timestamp( microtime( true ) - self::$start_time ) ) );
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
				'timeout' => 90,
			)
		);
		$code     = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 === $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['css'] ) ) {
				$critical_size = round( ( strlen( $body['css'] ) / 1000 ), 2 );
				if ( self::use_database_storage() ) {
					$option_name = self::get_option_name_for_url( $url );
					update_option( $option_name, $body['css'], false );
					self::log( sprintf( 'Saved critical css (%skb) to database option %s [%s]', $critical_size, $option_name, self::format_timestamp( microtime( true ) - self::$start_time ) ) );
				} else {
					file_put_contents( $config['critical'], $body['css'] );
					self::log( sprintf( 'Saved critical css (%skb) to %s [%s]', $critical_size, str_replace( ABSPATH, '', $config['critical'] ), self::format_timestamp( microtime( true ) - self::$start_time ) ) );
				}
				if ( $critical_size >= 14 ) {
					return new WP_Error(
						'size-exceeded',
						sprintf( 'Critical CSS size exceeds 14kb (actual %skb)', $critical_size )
					);
				}
			} else {
				return new WP_Error(
					'empty-response',
					sprintf( 'Critical CSS response is unexpectedly empty [%s]', self::format_timestamp( microtime( true ) - self::$start_time ) )
				);
			}
		} else {
			return new WP_Error(
				'unexpected-response',
				sprintf( 'Unexpected response from critical CSS server: %s (HTTP %d) [%s]', trim( wp_remote_retrieve_body( $response ) ), $code, self::format_timestamp( microtime( true ) - self::$start_time ) )
			);
		}
		return true;
	}

	/**
	 * Gets the critical CSS page configs.
	 *
	 * @return array
	 */
	public static function get_page_configs() {
		$configs = apply_filters( 'tinybit_critical_css_pages', [] );
		$new     = [];
		foreach ( $configs as $config_url => $config ) {
			if ( ! wp_parse_url( $config_url, PHP_URL_HOST ) ) {
				$config_url = home_url( $config_url );
			}
			$new[ $config_url ] = $config;
		}
		return $new;
	}

	/**
	 * Gets the critical CSS page config for a given URL.
	 *
	 * @param string $url URL to match.
	 * @return array
	 */
	public static function get_page_config_for_url( $url ) {
		$configs = self::get_page_configs();
		foreach ( $configs as $config_url => $config ) {
			if ( $url === $config_url ) {
				return $config;
			}
		}
		return false;
	}

	/**
	 * Checks if database storage is enabled for critical CSS.
	 *
	 * @return bool
	 */
	public static function use_database_storage() {
		return apply_filters( 'tinybit_critical_css_use_database', false );
	}

	/**
	 * Gets the option name for storing critical CSS for a given URL.
	 *
	 * @param string $url URL to generate option name for.
	 * @return string
	 */
	public static function get_option_name_for_url( $url ) {
		return 'tinybit_critical_css_' . md5( $url );
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
		self::$log_messages .= PHP_EOL . $message;
	}

	/**
	 * Gets all log messages.
	 *
	 * @return string
	 */
	public static function get_log_messages() {
		return trim( self::$log_messages );
	}

	/**
	 * Clears all log messages.
	 */
	public static function clear_log_messages() {
		self::$log_messages = '';
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
	 * Runs through the entirety of the WP bootstrap process
	 */
	private static function load_wordpress_with_template() {

		// Set up main_query main WordPress query.
		wp();

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		return self::get_rendered_template();
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
		// phpcs:disable Generic.CodeAnalysis.AssignmentInCondition.Found
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
			include $template;
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
		// phpcs:enable Generic.CodeAnalysis.AssignmentInCondition.Found

		return;
	}

	/**
	 * Formats seconds into H:i:s
	 *
	 * @param integer $seconds Time in seconds.
	 * @return string
	 */
	protected static function format_timestamp( $seconds ) {
		return floor( $seconds / 3600 ) . gmdate( ':i:s', $seconds % 3600 );
	}
}
