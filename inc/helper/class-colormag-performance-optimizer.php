<?php
/**
 * Performance Optimizer for ColorMag Theme
 *
 * Implements advanced caching and optimization strategies:
 * - Cache category colors in transients or static array
 * - Optimize font loading with only required weights
 * - Query optimization for category retrieval
 *
 * @package    ThemeGrill
 * @subpackage ColorMag
 * @since      ColorMag 4.1.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Static cache for category colors during single request.
 *
 * @var array
 */
$colormag_category_colors_cache = array();

/**
 * Get category color with caching optimization.
 *
 * Caches category colors in a static array during single request
 * to avoid repeated get_term_meta() calls.
 *
 * @param int $category_id Category ID.
 *
 * @return string Category color hex code.
 */
function colormag_get_category_color_optimized( $category_id ) {
global $colormag_category_colors_cache;

// Check static cache first.
if ( isset( $colormag_category_colors_cache[ $category_id ] ) ) {
return $colormag_category_colors_cache[ $category_id ];
}

// Get color from term meta.
$color = get_term_meta( $category_id, 'colormag_category_color', true );

// Fallback to default if not set.
if ( empty( $color ) ) {
$color = '#e74c3c'; // Default ColorMag red.
}

// Cache for this request.
$colormag_category_colors_cache[ $category_id ] = $color;

return $color;
}

/**
 * Get multiple category colors with batch optimization.
 *
 * Retrieves colors for multiple categories in a single operation
 * when possible, using static caching.
 *
 * @param array $category_ids Array of category IDs.
 *
 * @return array Associative array of category_id => color.
 */
function colormag_get_category_colors_batch( $category_ids ) {
global $colormag_category_colors_cache;

$result = array();
$missing = array();

// Check cache first.
foreach ( $category_ids as $cat_id ) {
if ( isset( $colormag_category_colors_cache[ $cat_id ] ) ) {
$result[ $cat_id ] = $colormag_category_colors_cache[ $cat_id ];
} else {
$missing[] = $cat_id;
}
}

// Fetch missing colors.
if ( ! empty( $missing ) ) {
foreach ( $missing as $cat_id ) {
$color = get_term_meta( $cat_id, 'colormag_category_color', true );

if ( empty( $color ) ) {
$color = '#e74c3c';
}

$colormag_category_colors_cache[ $cat_id ] = $color;
$result[ $cat_id ] = $color;
}
}

return $result;
}

/**
 * Optimized category query with proper conditions and caching.
 *
 * Uses transient caching for expensive category queries.
 *
 * @param array $args Query arguments.
 * @param bool  $use_cache Whether to use cache.
 *
 * @return array Array of category objects.
 */
function colormag_get_categories_optimized( $args = array(), $use_cache = true ) {
$cache_key = 'colormag_cats_' . md5( serialize( $args ) );

// Try to get from transient cache.
if ( $use_cache ) {
$cached = get_transient( $cache_key );
if ( false !== $cached ) {
return $cached;
}
}

// Set default args with optimizations.
$defaults = array(
'taxonomy'   => 'category',
'hide_empty' => false,
'number'     => 0,
);

$args = wp_parse_args( $args, $defaults );

// Use get_terms with optimized parameters.
$categories = get_terms( $args );

if ( is_wp_error( $categories ) || empty( $categories ) ) {
return array();
}

// Cache for 1 hour.
if ( $use_cache ) {
set_transient( $cache_key, $categories, HOUR_IN_SECONDS );
}

return $categories;
}

/**
 * Invalidate category cache when terms are updated.
 *
 * @param int $term_id Term ID.
 */
function colormag_invalidate_category_cache( $term_id ) {
// Delete all category-related transients.
delete_transient( 'colormag_cats_' );

// Clear static cache.
global $colormag_category_colors_cache;
$colormag_category_colors_cache = array();
}

add_action( 'edited_terms', 'colormag_invalidate_category_cache' );
add_action( 'created_term', 'colormag_invalidate_category_cache' );
add_action( 'delete_term', 'colormag_invalidate_category_cache' );

/**
 * Preload critical category data on init.
 *
 * Loads frequently accessed categories into static cache.
 */
function colormag_preload_category_data() {
global $colormag_category_colors_cache;

// Get main categories used in theme.
$main_cats = get_terms( array(
'taxonomy' => 'category',
'number'   => 20,
'orderby'  => 'count',
'order'    => 'DESC',
) );

if ( ! is_wp_error( $main_cats ) && ! empty( $main_cats ) ) {
foreach ( $main_cats as $cat ) {
$color = get_term_meta( $cat->term_id, 'colormag_category_color', true );
if ( ! empty( $color ) ) {
$colormag_category_colors_cache[ $cat->term_id ] = $color;
}
}
}
}

add_action( 'wp', 'colormag_preload_category_data', 1 );

/**
 * Optimize Google Fonts loading.
 *
 * Removes unnecessary font weights and subsets.
 * Only loads required fonts for the theme.
 *
 * @param string $query_args Fonts URL query arguments.
 *
 * @return string Modified query arguments.
 */
function colormag_optimize_font_loading( $query_args ) {
// Only optimize Google Fonts URLs.
if ( strpos( $query_args, 'fonts.googleapis.com' ) === false ) {
return $query_args;
}

// Parse and optimize font weights.
// Remove unnecessary weights, keep only what theme uses.
$allowed_weights = array( '400', '600', '700' );

// Extract family parameter.
if ( preg_match( '/family=([^&]+)/', $query_args, $matches ) ) {
$family = $matches[1];

// Decode URL-encoded characters.
$family = urldecode( $family );

// Check if weights are specified.
if ( strpos( $family, ':' ) !== false ) {
list( $font_name, $weights ) = explode( ':', $family, 2 );

// Filter weights.
$weight_array = explode( ',', $weights );
$filtered_weights = array_intersect( $weight_array, $allowed_weights );

if ( ! empty( $filtered_weights ) ) {
$new_family = $font_name . ':' . implode( ',', $filtered_weights );
$query_args = str_replace( $family, urlencode( $new_family ), $query_args );
}
}
}

// Add display=swap for better performance.
if ( strpos( $query_args, 'display=' ) === false ) {
$query_args .= '&display=swap';
}

return $query_args;
}

add_filter( 'style_loader_src', 'colormag_optimize_font_loading' );

/**
 * Preconnect to Google Fonts domain.
 *
 * Adds DNS preconnect hints for faster font loading.
 *
 * @param array  $urls          URLs to print for resource hints.
 * @param string $relation_type The relation type the URLs are printed.
 *
 * @return array Modified URLs.
 */
function colormag_add_font_preconnect( $urls, $relation_type ) {
if ( 'preconnect' !== $relation_type ) {
return $urls;
}

$urls[] = array(
'href' => 'https://fonts.gstatic.com',
'crossorigin' => true,
);

return $urls;
}

add_filter( 'wp_resource_hints', 'colormag_add_font_preconnect', 10, 2 );

/**
 * Defer non-critical CSS loading.
 *
 * @param string $tag    The link tag for the enqueued style.
 * @param string $handle The style's registered handle.
 *
 * @return string Modified tag.
 */
function colormag_defer_non_critical_css( $tag, $handle ) {
// List of non-critical stylesheets to defer.
$defer_handles = array(
'colormag-block-editor-styles',
);

if ( in_array( $handle, $defer_handles, true ) ) {
$tag = str_replace( " rel='stylesheet'", " rel='preload' onload=\"this.rel='stylesheet'\"", $tag );
$tag = str_replace( "<link", "<link", $tag );
}

return $tag;
}

add_filter( 'style_loader_tag', 'colormag_defer_non_critical_css', 10, 2 );

/**
 * Lazy load images in widget areas.
 *
 * @param array $atts Image attributes.
 *
 * @return array Modified attributes.
 */
function colormag_lazy_load_widget_images( $atts ) {
if ( is_active_widget( false, false, 'colormag', true ) ) {
$atts['loading'] = 'lazy';
}

return $atts;
}

add_filter( 'wp_get_attachment_image_attributes', 'colormag_lazy_load_widget_images' );

/**
 * Minify inline critical CSS.
 *
 * @param string $css CSS content.
 *
 * @return string Minified CSS.
 */
function colormag_minify_inline_css( $css ) {
// Remove comments.
$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );

// Remove whitespace.
$css = preg_replace( '/\s+/', ' ', $css );
$css = preg_replace( '/\s*([{};:,])\s*/', '$1', $css );

return trim( $css );
}

/**
 * Get cached popular posts with query optimization.
 *
 * @param int $number Number of posts.
 * @param int $cache_duration Cache duration in seconds.
 *
 * @return WP_Query Optimized query of popular posts.
 */
function colormag_get_popular_posts_cached( $number = 5, $cache_duration = HOUR_IN_SECONDS ) {
$cache_key = 'colormag_popular_posts_' . $number;

$cached_query = get_transient( $cache_key );

if ( false !== $cached_query ) {
return $cached_query;
}

$args = array(
'post_type'      => 'post',
'posts_per_page' => $number,
'meta_key'       => 'colormag_post_views',
'orderby'        => 'meta_value_num',
'order'          => 'DESC',
'no_found_rows'  => true, // Optimize for pagination not needed.
'update_post_meta_cache' => false,
'update_post_term_cache' => false,
);

$query = new WP_Query( $args );

// Cache the query results.
set_transient( $cache_key, $query, $cache_duration );

return $query;
}
