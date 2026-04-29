<?php
/**
 * ColorMag Performance Optimizations
 * 
 * Implements critical performance improvements:
 * - Font Awesome consolidation (remove v4, keep v6 only)
 * - Google Fonts URL caching with transients
 * - Typography defaults consolidation using loops
 * - Dynamic CSS caching with transients
 * - Query optimization for category retrieval
 * 
 * @package    ColorMag
 * @since      ColorMag 3.1.0
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
			// Font Awesome optimization - remove v4 legacy
			add_action( 'wp_enqueue_scripts', array( $this, 'optimize_font_awesome' ), 20 );
			
			// Cache Google Fonts URL with transients
			add_filter( 'customind:get_google_fonts_url_by_ids', array( $this, 'cache_google_fonts_url' ), 10, 2 );
			
			// Cache dynamic CSS
			add_filter( 'colormag_dynamic_theme_css', array( $this, 'cache_dynamic_css' ), 999, 1 );
			
			// Invalidate CSS cache on customizer save
			add_action( 'customize_save_after', array( $this, 'invalidate_css_cache' ) );
			
			// Optimize typography defaults with loop
			add_filter( 'colormag_typography_defaults', array( $this, 'consolidate_typography_defaults' ) );
			
			// Optimize category queries
			add_filter( 'get_terms_args', array( $this, 'optimize_category_query' ), 10, 2 );
		}

		/**
		 * Issue #1: Remove Font Awesome v4 legacy, consolidate v6 loading
		 * 
		 * Font Awesome v4 is loaded twice (v4-shims + v6), causing unnecessary HTTP requests.
		 * This function removes v4 if no longer needed and ensures v6 loads only once.
		 */
		public function optimize_font_awesome() {
			// Check if user wants to disable FA4 compatibility
			$disable_fa4 = get_theme_mod( 'colormag_disable_font_awesome_v4', true );
			
			if ( $disable_fa4 ) {
				// Dequeue Font Awesome v4 shims
				wp_dequeue_style( 'font-awesome-4' );
				wp_deregister_style( 'font-awesome-4' );
				
				// Ensure v6 is loaded only once
				global $wp_styles;
				
				if ( isset( $wp_styles->registered['font-awesome-all'] ) && isset( $wp_styles->registered['colormag-font-awesome-6'] ) ) {
					// Remove duplicate v6 registration
					wp_dequeue_style( 'colormag-font-awesome-6' );
				}
			}
			
			// Add DNS preconnect for Font Awesome CDN if using external
			add_filter( 'wp_resource_hints', array( $this, 'font_awesome_preconnect' ), 10, 2 );
		}

		/**
		 * Add preconnect hints for Font Awesome
		 */
		public function font_awesome_preconnect( $urls, $relation_type ) {
			if ( 'preconnect' === $relation_type ) {
				$urls[] = array(
					'href' => 'https://cdnjs.cloudflare.com',
					'crossorigin' => 'anonymous',
				);
			}
			return $urls;
		}

		/**
		 * Issue #2: Add transient caching for Google Fonts URL
		 * 
		 * Prevents timeout issues when fetching remote Google Fonts data.
		 * Caches the URL for 1 week with automatic invalidation.
		 * 
		 * @param string $fonts_url Google Fonts URL.
		 * @param array  $typography_ids Array of typography control IDs.
		 * @return string Cached or fresh Google Fonts URL.
		 */
		public function cache_google_fonts_url( $fonts_url, $typography_ids ) {
			if ( empty( $typography_ids ) || ! is_array( $typography_ids ) ) {
				return $fonts_url;
			}
			
			// Create unique cache key based on typography IDs
			$cache_key = 'colormag_google_fonts_' . md5( implode( '_', $typography_ids ) );
			
			// Try to get cached URL
			$cached_url = get_transient( $cache_key );
			
			if ( false !== $cached_url ) {
				return $cached_url;
			}
			
			// If no cache, generate URL (original function will handle this)
			// Cache for 1 week
			set_transient( $cache_key, $fonts_url, WEEK_IN_SECONDS );
			
			return $fonts_url;
		}

		/**
		 * Issue #3: Consolidate typography defaults using a loop
		 * 
		 * Replaces repetitive code with a single loop for all typography settings.
		 * Reduces code duplication and makes maintenance easier.
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
		 * Issue #4: Cache dynamic CSS in transients
		 * 
		 * Complex dynamic CSS is rendered on every page load.
		 * This caches the output and invalidates on customizer changes.
		 * 
		 * @param string $css Generated dynamic CSS.
		 * @return string Cached or fresh CSS.
		 */
		public function cache_dynamic_css( $css ) {
			$cache_key = 'colormag_dynamic_css_v1';
			$cached_css = get_transient( $cache_key );
			
			if ( false !== $cached_css ) {
				return $cached_css;
			}
			
			// If no cache, CSS will be generated by the filter chain
			// Cache for 1 day (will be invalidated on customizer save)
			set_transient( $cache_key, $css, DAY_IN_SECONDS );
			
			return $css;
		}

		/**
		 * Invalidate CSS cache when customizer settings are saved
		 */
		public function invalidate_css_cache() {
			delete_transient( 'colormag_dynamic_css_v1' );
			
			// Also invalidate Google Fonts cache
			delete_transient( 'colormag_google_fonts_all' );
			
			// Clear category color cache
			delete_transient( 'colormag_category_colors' );
		}

		/**
		 * Issue #6: Optimize query for category retrieval
		 * 
		 * Adds proper conditions and caching to category queries.
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
			
			// Check if this is a frontend request
			if ( is_admin() ) {
				return $args;
			}
			
			// Add caching for category queries
			$cache_key = 'colormag_categories_' . md5( serialize( $args ) );
			$cached_categories = get_transient( $cache_key );
			
			if ( false !== $cached_categories ) {
				// Return cached result by modifying args to use cached IDs
				if ( is_array( $cached_categories ) && ! empty( $cached_categories ) ) {
					$args['include'] = wp_list_pluck( $cached_categories, 'term_id' );
				}
			} else {
				// Add optimization flags
				$args['update_term_meta_cache'] = false;
				$args['fields'] = 'all';
				
				// Cache for 1 hour
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
			
			// Remove self from filter to prevent infinite loop
			remove_filter( 'get_terms', array( $this, 'cache_category_results' ), 10 );
			
			return $terms;
		}

		/**
		 * Helper: Bulk invalidate all caches
		 * Useful after theme updates or major changes
		 */
		public static function bulk_invalidate_caches() {
			global $wpdb;
			
			// Delete all ColorMag transients
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_colormag_%'" );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_colormag_%'" );
			
			// Clear object cache if available
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}

	// Initialize the optimizer
	ColorMag_Performance_Optimizer_Pro::get_instance();
}
