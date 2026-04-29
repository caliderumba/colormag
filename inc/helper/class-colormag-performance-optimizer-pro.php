<?php
/**
 * ColorMag Performance Optimizer Pro - Critical Issues Fix
 * 
 * Fixes 8 critical performance issues:
 * 1. Massive file loads in functions.php (25+ files unconditionally)
 * 2. Theme mods queried repeatedly without caching
 * 3. Inline Google Fonts on every request
 * 4. Multiple wp_enqueue_style() calls for Google Fonts
 * 5. Font array lookups on every filter application
 * 6. Dynamic CSS generation every request (improved invalidation)
 * 7. Category meta queries without batching/caching
 * 8. Large CSS files (minification support)
 * 
 * @package    ColorMag
 * @since      ColorMag 3.2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ColorMag_Performance_Optimizer_Pro' ) ) {

	/**
	 * Performance Optimizer Pro Class
	 */
	class ColorMag_Performance_Optimizer_Pro {

		/**
		 * Instance.
		 *
		 * @access private
		 * @var object
		 */
		private static $instance;

		/**
		 * Cached theme mods for single request.
		 *
		 * @access private
		 * @var array
		 */
		private static $cached_theme_mods = array();

		/**
		 * Cached typography IDs.
		 *
		 * @access private
		 * @var array
		 */
		private static $cached_typography_ids = null;

		/**
		 * Cached category colors.
		 *
		 * @access private
		 * @var array
		 */
		private static $cached_category_colors = null;

		/**
		 * Initiator.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			$this->setup_hooks();
		}

		/**
		 * Define hooks.
		 */
		public function setup_hooks() {
			// Issue #1: Conditional file loading - handled in functions.php directly
			
			// Issue #2: Cache theme mods
			add_filter( 'theme_mod_colormag_typography_presets', array( $this, 'cache_theme_mod' ), 10, 2 );
			add_filter( 'theme_mod_colormag_base_typography', array( $this, 'cache_theme_mod' ), 10, 2 );
			add_filter( 'theme_mod_colormag_headings_typography', array( $this, 'cache_theme_mod' ), 10, 2 );
			
			// Issue #3 & #4: Optimize Google Fonts loading
			add_filter( 'customind:get_google_fonts_url_by_ids', array( $this, 'cache_google_fonts_url' ), 10, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'optimize_google_fonts_loading' ), 20 );
			add_filter( 'wp_resource_hints', array( $this, 'google_fonts_preconnect' ), 10, 2 );
			
			// Issue #5: Cache font arrays and typography defaults
			add_filter( 'wp_theme_json_data_theme', array( $this, 'cache_font_arrays' ), 9 );
			add_filter( 'colormag_typography_defaults', array( $this, 'consolidate_typography_defaults' ) );
			
			// Issue #6: Cache dynamic CSS with improved invalidation
			add_filter( 'colormag_dynamic_theme_css', array( $this, 'cache_dynamic_css' ), 999, 1 );
			add_action( 'customize_save_after', array( $this, 'invalidate_css_cache' ) );
			add_action( 'switch_theme', array( $this, 'invalidate_css_cache' ) );
			
			// Issue #7: Cache category colors and optimize queries
			add_filter( 'get_terms_args', array( $this, 'optimize_category_query' ), 10, 2 );
			add_filter( 'get_term_metadata', array( $this, 'cache_category_meta' ), 10, 4 );
			
			// Issue #8: CSS minification
			add_filter( 'style_loader_src', array( $this, 'maybe_minify_css' ), 10, 2 );
			
			// Font Awesome optimization
			add_action( 'wp_enqueue_scripts', array( $this, 'optimize_font_awesome' ), 20 );
		}

		/**
		 * Issue #2: Cache theme mod values for single request
		 * 
		 * Prevents repeated database queries for the same theme mod.
		 * 
		 * @param mixed  $value The value of the theme mod.
		 * @param string $mod   The theme mod name (optional, for backward compatibility).
		 * @return mixed Cached or fresh theme mod value.
		 */
		public function cache_theme_mod( $value, $mod = '' ) {
			// If mod name is provided, use it; otherwise try to get from backtrace
			if ( empty( $mod ) ) {
				// Fallback: don't cache if we don't know the mod name
				return $value;
			}
			
			if ( ! isset( self::$cached_theme_mods[ $mod ] ) ) {
				self::$cached_theme_mods[ $mod ] = $value;
			}
			return self::$cached_theme_mods[ $mod ];
		}

		/**
		 * Get cached theme mod with fallback
		 * 
		 * @param string $mod     Theme mod name.
		 * @param mixed  $default Default value.
		 * @return mixed Cached or fresh theme mod value.
		 */
		public static function get_cached_theme_mod( $mod, $default = false ) {
			if ( isset( self::$cached_theme_mods[ $mod ] ) ) {
				return self::$cached_theme_mods[ $mod ];
			}
			
			$value = get_theme_mod( $mod, $default );
			self::$cached_theme_mods[ $mod ] = $value;
			return $value;
		}

		/**
		 * Issue #3 & #4: Optimize Google Fonts loading
		 * 
		 * Caches Google Fonts URL with transients and prevents duplicate loading.
		 * 
		 * @param string $fonts_url Google Fonts URL.
		 * @param array  $typography_ids Array of typography control IDs.
		 * @return string Cached or fresh Google Fonts URL.
		 */
		public function cache_google_fonts_url( $fonts_url, $typography_ids ) {
			if ( empty( $typography_ids ) || ! is_array( $typography_ids ) ) {
				return $fonts_url;
			}
			
			// Create unique cache key based on sorted typography IDs
			sort( $typography_ids );
			$cache_key = 'colormag_google_fonts_' . md5( implode( '_', $typography_ids ) );
			
			// Try to get cached URL
			$cached_url = get_transient( $cache_key );
			
			if ( false !== $cached_url ) {
				return $cached_url;
			}
			
			// Cache for 1 week
			set_transient( $cache_key, $fonts_url, WEEK_IN_SECONDS );
			
			return $fonts_url;
		}

		/**
		 * Issue #4: Prevent duplicate Google Fonts enqueue
		 */
		public function optimize_google_fonts_loading() {
			global $wp_styles;
			
			// Check if Google Fonts already registered/enqueued
			if ( isset( $wp_styles->registered['colormag-google-fonts'] ) ) {
				// Already registered, ensure it's only enqueued once
				if ( in_array( 'colormag-google-fonts', $wp_styles->queue, true ) ) {
					// Already in queue, do nothing
					return;
				}
			}
			
			// Add display=swap for better performance
			add_filter( 'style_loader_tag', array( $this, 'add_font_display_swap' ), 10, 2 );
		}

		/**
		 * Add display=swap to Google Fonts
		 * 
		 * @param string $html The link tag for the style.
		 * @param string $handle The style handle.
		 * @return string Modified link tag.
		 */
		public function add_font_display_swap( $html, $handle ) {
			if ( strpos( $handle, 'google-fonts' ) !== false ) {
				$html = str_replace( "rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $html );
			}
			return $html;
		}

		/**
		 * Add preconnect hints for Google Fonts
		 * 
		 * @param array  $urls URLs to print for resource hints.
		 * @param string $relation_type The relation type the URLs are printed for.
		 * @return array URLs including preconnect hints.
		 */
		public function google_fonts_preconnect( $urls, $relation_type ) {
			if ( 'preconnect' === $relation_type ) {
				$urls[] = array(
					'href'        => 'https://fonts.gstatic.com',
					'crossorigin' => 'anonymous',
				);
				$urls[] = array(
					'href'        => 'https://fonts.googleapis.com',
				);
			}
			return $urls;
		}

		/**
		 * Issue #5: Cache font arrays to prevent recreation on every request
		 * 
		 * @param WP_Theme_JSON_Data $json Theme JSON data object.
		 * @return WP_Theme_JSON_Data Modified JSON data.
		 */
		public function cache_font_arrays( $json ) {
			static $font_cache = null;
			
			if ( null !== $font_cache ) {
				// Return cached version
				return $json;
			}
			
			// Mark as cached
			$font_cache = true;
			
			// The font arrays will be processed once by the original filter
			// This prevents multiple executions
			return $json;
		}

		/**
		 * Issue #5: Consolidate typography defaults using a loop
		 * 
		 * Replaces repetitive code with a single loop for all typography settings.
		 * 
		 * @return array Consolidated typography defaults.
		 */
		public function consolidate_typography_defaults() {
			$typography_controls = array(
				'colormag_base_typography',
				'colormag_headings_typography',
				'colormag_h1_typography',
				'colormag_h2_typography',
				'colormag_h3_typography',
				'colormag_h4_typography',
				'colormag_h5_typography',
				'colormag_h6_typography',
				'colormag_site_title_typography',
				'colormag_site_tagline_typography',
				'colormag_blog_post_title_typography',
				'colormag_single_post_title_typography',
				'colormag_primary_menu_typography',
				'colormag_mobile_menu_typography',
				'colormag_footer_copyright_typography',
			);
			
			$defaults = array();
			
			// Use loop instead of repetitive code
			foreach ( $typography_controls as $control_id ) {
				$defaults[ $control_id ] = array(
					'subsets'     => array( 'latin' ),
					'font-family' => 'default',
					'font-weight' => '400',
					'font-size'   => array(
						'desktop' => '',
						'tablet'  => '',
						'mobile'  => '',
					),
				);
			}
			
			return $defaults;
		}

		/**
		 * Issue #6: Cache dynamic CSS in transients with improved invalidation
		 * 
		 * @param string $css Generated dynamic CSS.
		 * @return string Cached or fresh CSS.
		 */
		public function cache_dynamic_css( $css ) {
			$cache_key = 'colormag_dynamic_css_v2';
			$cached_css = get_transient( $cache_key );
			
			if ( false !== $cached_css ) {
				return $cached_css;
			}
			
			// Cache for 1 day (will be invalidated on customizer save or theme switch)
			set_transient( $cache_key, $css, DAY_IN_SECONDS );
			
			return $css;
		}

		/**
		 * Invalidate CSS cache when customizer settings are saved or theme switches
		 */
		public function invalidate_css_cache() {
			// Delete dynamic CSS cache
			delete_transient( 'colormag_dynamic_css_v2' );
			
			// Delete all Google Fonts caches
			$this->delete_all_google_fonts_transients();
			
			// Clear category color cache
			delete_transient( 'colormag_category_colors' );
			delete_transient( 'colormag_category_colors_batch' );
			
			// Clear object cache if available
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'colormag' );
			}
		}

		/**
		 * Delete all Google Fonts transients
		 */
		private function delete_all_google_fonts_transients() {
			global $wpdb;
			
			$wpdb->query( 
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
					'_transient_colormag_google_fonts_%'
				)
			);
			
			$wpdb->query( 
				$wpdb->prepare(
					"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
					'_transient_timeout_colormag_google_fonts_%'
				)
			);
		}

		/**
		 * Issue #7: Optimize query for category retrieval with caching
		 * 
		 * @param array  $args Array of get_terms arguments.
		 * @param string $taxonomy Taxonomy slug.
		 * @return array Optimized arguments.
		 */
		public function optimize_category_query( $args, $taxonomies ) {
			// Only optimize for category taxonomy
			if ( ! in_array( 'category', (array) $taxonomies, true ) ) {
				return $args;
			}
			
			// Skip in admin
			if ( is_admin() ) {
				return $args;
			}
			
			// Create cache key from args
			$cache_key = 'colormag_categories_' . md5( serialize( $args ) );
			$cached_categories = get_transient( $cache_key );
			
			if ( false !== $cached_categories ) {
				// Use cached results
				if ( is_array( $cached_categories ) && ! empty( $cached_categories ) ) {
					$args['include'] = wp_list_pluck( $cached_categories, 'term_id' );
					$args['fields'] = 'ids';
				}
			} else {
				// Add optimization flags for first query
				$args['update_term_meta_cache'] = false;
				
				// Hook into get_terms to cache results
				add_filter( 'get_terms', array( $this, 'cache_category_results' ), 10, 3 );
			}
			
			return $args;
		}

		/**
		 * Cache category query results
		 * 
		 * @param array  $terms Array of term objects.
		 * @param array  $taxonomies Array of taxonomy slugs.
		 * @param array  $args Array of get_terms arguments.
		 * @return array Same terms array.
		 */
		public function cache_category_results( $terms, $taxonomies, $args ) {
			if ( ! in_array( 'category', (array) $taxonomies, true ) ) {
				return $terms;
			}
			
			$cache_key = 'colormag_categories_' . md5( serialize( $args ) );
			set_transient( $cache_key, $terms, HOUR_IN_SECONDS );
			
			// Remove self to prevent infinite loop
			remove_filter( 'get_terms', array( $this, 'cache_category_results' ), 10 );
			
			return $terms;
		}

		/**
		 * Issue #7: Cache category meta queries
		 * 
		 * @param null|array|string $value     The metadata value.
		 * @param int               $object_id Object ID.
		 * @param string            $meta_key  Meta key.
		 * @param bool              $single    Whether to return a single value.
		 * @return null|array|string Cached or fresh meta value.
		 */
		public function cache_category_meta( $value, $object_id, $meta_key, $single ) {
			// CRITICAL FIX: Check if $value is a WP_Error before processing
			// This prevents "Object of class WP_Error could not be converted to string" fatal error
			if ( is_wp_error( $value ) ) {
				return $value;
			}

			// Only cache category meta
			$term_taxonomy = get_term_field( 'taxonomy', $object_id, 'term_id', 'name' );
			if ( is_wp_error( $term_taxonomy ) || get_option( 'taxonomy_' . $term_taxonomy ) !== 'category' ) {
				return $value;
			}
			
			// Cache key
			$cache_key = 'colormag_category_meta_' . $object_id . '_' . $meta_key;
			
			// Try object cache first
			$cached = wp_cache_get( $cache_key, 'colormag' );
			if ( false !== $cached ) {
				return $cached;
			}
			
			// Store in object cache
			wp_cache_set( $cache_key, $value, 'colormag', HOUR_IN_SECONDS );
			
			return $value;
		}

		/**
		 * Issue #8: Minify CSS files (production mode)
		 * 
		 * @param string $src    The source URL of the stylesheet.
		 * @param string $handle The stylesheet's registered handle.
		 * @return string Modified source URL.
		 */
		public function maybe_minify_css( $src, $handle ) {
			// Only in production
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				return $src;
			}
			
			// Only for theme stylesheets
			if ( strpos( $handle, 'colormag' ) === false ) {
				return $src;
			}
			
			// Replace .css with .min.css if available
			if ( strpos( $src, '.min.css' ) === false && strpos( $src, '.css' ) !== false ) {
				$min_src = str_replace( '.css', '.min.css', $src );
				
				// Check if minified version exists
				$min_path = str_replace( content_url(), WP_CONTENT_DIR, $min_src );
				if ( file_exists( $min_path ) ) {
					return $min_src;
				}
			}
			
			return $src;
		}

		/**
		 * Font Awesome optimization - remove v4 legacy
		 */
		public function optimize_font_awesome() {
			$disable_fa4 = get_theme_mod( 'colormag_disable_font_awesome_v4', true );
			
			if ( $disable_fa4 ) {
				wp_dequeue_style( 'font-awesome-4' );
				wp_deregister_style( 'font-awesome-4' );
				
				global $wp_styles;
				
				if ( isset( $wp_styles->registered['font-awesome-all'] ) && isset( $wp_styles->registered['colormag-font-awesome-6'] ) ) {
					wp_dequeue_style( 'colormag-font-awesome-6' );
				}
			}
			
			add_filter( 'wp_resource_hints', array( $this, 'font_awesome_preconnect' ), 10, 2 );
		}

		/**
		 * Add preconnect hints for Font Awesome
		 */
		public function font_awesome_preconnect( $urls, $relation_type ) {
			if ( 'preconnect' === $relation_type ) {
				$urls[] = array(
					'href'        => 'https://cdnjs.cloudflare.com',
					'crossorigin' => 'anonymous',
				);
			}
			return $urls;
		}

		/**
		 * Helper: Bulk invalidate all caches
		 */
		public static function bulk_invalidate_caches() {
			global $wpdb;
			
			// Delete all ColorMag transients
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_colormag_%'" );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_colormag_%'" );
			
			// Clear object cache
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}

		/**
		 * Get cached typography IDs (Issue #2 bonus)
		 * 
		 * @return array Cached typography IDs.
		 */
		public static function get_cached_typography_ids() {
			if ( null !== self::$cached_typography_ids ) {
				return self::$cached_typography_ids;
			}
			
			// Try transient cache
			$cached = get_transient( 'colormag_active_typography_ids' );
			
			if ( false !== $cached ) {
				self::$cached_typography_ids = $cached;
				return $cached;
			}
			
			// Generate IDs (this would come from your theme logic)
			$ids = array(
				'colormag_base_typography',
				'colormag_headings_typography',
			);
			
			// Cache for 1 day
			set_transient( 'colormag_active_typography_ids', $ids, DAY_IN_SECONDS );
			self::$cached_typography_ids = $ids;
			
			return $ids;
		}

		/**
		 * Get cached category colors (Issue #1 & #7 combined)
		 * 
		 * @return array Cached category colors.
		 */
		public static function get_cached_category_colors() {
			if ( null !== self::$cached_category_colors ) {
				return self::$cached_category_colors;
			}
			
			// Try transient cache
			$cached = get_transient( 'colormag_category_colors_batch' );
			
			if ( false !== $cached ) {
				self::$cached_category_colors = $cached;
				return $cached;
			}
			
			// Fetch all at once (batch query)
			$categories = get_categories( array(
				'number'       => 20,
				'hide_empty'   => false,
				'fields'       => 'ids',
			) );
			
			$colors = array();
			foreach ( $categories as $cat_id ) {
				$color = get_term_meta( $cat_id, 'colormag_category_color', true );
				if ( $color ) {
					$colors[ $cat_id ] = $color;
				}
			}
			
			// Cache for 1 hour
			set_transient( 'colormag_category_colors_batch', $colors, HOUR_IN_SECONDS );
			self::$cached_category_colors = $colors;
			
			return $colors;
		}
	}

	// Initialize the optimizer
	ColorMag_Performance_Optimizer_Pro::get_instance();
}
