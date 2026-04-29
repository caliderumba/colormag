<?php
/**
 * Post view counter functionality.
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
 * Initialize post views counter.
 *
 * @param int $post_id Post ID.
 */
function colormag_initialize_post_views( $post_id ) {
	if ( ! is_numeric( $post_id ) || empty( $post_id ) ) {
		return;
	}

	$views_key = 'colormag_post_views';
	$views     = get_post_meta( $post_id, $views_key, true );

	if ( '' === $views || ! is_numeric( $views ) ) {
		$views = 0;
		add_post_meta( $post_id, $views_key, $views );
	}
}

/**
 * Track and update post views.
 *
 * @param int $post_id Post ID.
 */
function colormag_track_post_views( $post_id ) {
	if ( ! is_single() || ! is_singular( 'post' ) ) {
		return;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return; // Don't count admin views.
	}

	// Check if we already counted this view in this session.
	if ( isset( $_COOKIE['colormag_viewed_' . $post_id] ) ) {
		return;
	}

	colormag_initialize_post_views( $post_id );

	$views_key = 'colormag_post_views';
	$views     = (int) get_post_meta( $post_id, $views_key, true );
	$views++;
	update_post_meta( $post_id, $views_key, $views );

	// Set cookie to prevent duplicate counting for 24 hours.
	setcookie( 'colormag_viewed_' . $post_id, '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
}

add_action( 'wp_head', 'colormag_track_post_views' );

/**
 * Display post view count.
 *
 * @param int  $post_id Post ID.
 * @param bool $echo   Whether to echo or return.
 *
 * @return string|void
 */
function colormag_display_post_views( $post_id = null, $echo = true ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}

	$views_key = 'colormag_post_views';
	$views     = get_post_meta( $post_id, $views_key, true );

	if ( '' === $views || ! is_numeric( $views ) ) {
		$views = 0;
	}

	$output = sprintf(
		'<span class="cm-post-views" title="%s">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" fill="currentColor"/>
			</svg>
			<span class="cm-views-count">%s</span>
		</span>',
		esc_attr__( 'Total views', 'colormag' ),
		number_format_i18n( $views )
	);

	if ( $echo ) {
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		return $output;
	}
}

/**
 * Get most viewed posts.
 *
 * @param int $number Number of posts to retrieve.
 *
 * @return WP_Query
 */
function colormag_get_most_viewed_posts( $number = 5 ) {
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $number,
		'meta_key'       => 'colormag_post_views',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	return new WP_Query( $args );
}

/**
 * Add custom column to posts list table.
 *
 * @param array $columns Existing columns.
 *
 * @return array
 */
function colormag_add_views_column( $columns ) {
	$columns['colormag_views'] = esc_html__( 'Views', 'colormag' );
	return $columns;
}

add_filter( 'manage_post_posts_columns', 'colormag_add_views_column' );

/**
 * Display views count in posts list table.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function colormag_display_views_column( $column, $post_id ) {
	if ( 'colormag_views' === $column ) {
		$views = get_post_meta( $post_id, 'colormag_post_views', true );
		echo number_format_i18n( $views ?? 0 );
	}
}

add_action( 'manage_post_posts_custom_column', 'colormag_display_views_column', 10, 2 );

/**
 * Make views column sortable.
 *
 * @param array $columns Sortable columns.
 *
 * @return array
 */
function colormag_make_views_column_sortable( $columns ) {
	$columns['colormag_views'] = 'colormag_views';
	return $columns;
}

add_filter( 'manage_edit-post_sortable_columns', 'colormag_make_views_column_sortable' );

/**
 * Handle sorting by views.
 *
 * @param WP_Query $query Query object.
 */
function colormag_sort_by_views( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'colormag_views' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', 'colormag_post_views' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}

add_action( 'pre_get_posts', 'colormag_sort_by_views' );
