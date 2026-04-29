<?php
/**
 * Reading time estimator functionality.
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
 * Calculate reading time for a post with caching optimization.
 *
 * Caches word count and reading time in post meta on first calculation
 * to avoid repeated expensive string operations.
 *
 * @param int  $post_id Post ID.
 * @param bool $force   Force recalculation.
 *
 * @return int Reading time in minutes.
 */
function colormag_calculate_reading_time( $post_id = null, $force = false ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}

	// Check cache first unless force recalculation.
	if ( ! $force ) {
		$cached_time = get_post_meta( $post_id, '_colormag_reading_time', true );
		if ( ! empty( $cached_time ) && is_numeric( $cached_time ) ) {
			return (int) $cached_time;
		}
	}

	$content = get_post_field( 'post_content', $post_id );
	
	// Add excerpt to calculation if available.
	$excerpt = get_post_field( 'post_excerpt', $post_id );
	if ( ! empty( $excerpt ) ) {
		$content .= ' ' . $excerpt;
	}

	// Strip shortcodes and HTML tags.
	$content = strip_shortcodes( $content );
	$content = wp_strip_all_tags( $content );

	// Count words with caching.
	$word_count = get_post_meta( $post_id, '_colormag_word_count', true );
	
	if ( empty( $word_count ) || $force ) {
		// Use preg_split for better accuracy with Unicode.
		$words = preg_split( '/[\s\p{P}]+/u', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );
		$word_count = is_array( $words ) ? count( $words ) : str_word_count( $content );
		
		// Cache word count.
		update_post_meta( $post_id, '_colormag_word_count', $word_count );
	}

	// Average reading speed: 200 words per minute.
	$reading_speed = apply_filters( 'colormag_reading_speed', 200 );
	
	// Calculate time.
	$reading_time = ceil( $word_count / $reading_speed );

	// Minimum 1 minute.
	$reading_time = max( 1, $reading_time );

	// Cache reading time.
	update_post_meta( $post_id, '_colormag_reading_time', $reading_time );

	return $reading_time;
}

/**
 * Display reading time.
 *
 * @param int  $post_id Post ID.
 * @param bool $echo   Whether to echo or return.
 *
 * @return string|void
 */
function colormag_display_reading_time( $post_id = null, $echo = true ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}

	$minutes = colormag_calculate_reading_time( $post_id );

	$output = sprintf(
		'<span class="cm-reading-time" title="%s">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor"/>
			</svg>
			<span class="cm-time-text">%s %s</span>
		</span>',
		esc_attr__( 'Estimated reading time', 'colormag' ),
		$minutes,
		esc_html( _n( 'min read', 'min read', $minutes, 'colormag' ) )
	);

	if ( $echo ) {
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		return $output;
	}
}

/**
 * Add reading time meta box to post editor.
 */
function colormag_add_reading_time_meta_box() {
	add_meta_box(
		'colormag_reading_time_box',
		esc_html__( 'Reading Time', 'colormag' ),
		'colormag_reading_time_meta_box_callback',
		'post',
		'side',
		'default'
	);
}

add_action( 'add_meta_boxes', 'colormag_add_reading_time_meta_box' );

/**
 * Invalidate reading time and word count cache when post is updated.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function colormag_invalidate_reading_time_cache( $post_id, $post ) {
	// Only for published posts.
	if ( get_post_status( $post_id ) !== 'publish' ) {
		return;
	}

	delete_post_meta( $post_id, '_colormag_reading_time' );
	delete_post_meta( $post_id, '_colormag_word_count' );
}

add_action( 'save_post', 'colormag_invalidate_reading_time_cache', 10, 2 );

/**
 * Pre-calculate reading time for existing posts without cache.
 * Useful for bulk optimization.
 *
 * @param int $limit Number of posts to process.
 */
function colormag_bulk_calculate_reading_time( $limit = 100 ) {
	global $wpdb;

	$posts = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			 WHERE post_type = 'post' 
			 AND post_status = 'publish'
			 AND ID NOT IN (
				 SELECT post_id FROM {$wpdb->postmeta} 
				 WHERE meta_key = '_colormag_reading_time'
			 )
			 LIMIT %d",
			$limit
		)
	);

	foreach ( $posts as $post_id ) {
		colormag_calculate_reading_time( $post_id, true );
	}

	return count( $posts );
}

/**
 * Reading time meta box callback.
 *
 * @param WP_Post $post Post object.
 */
function colormag_reading_time_meta_box_callback( $post ) {
	wp_nonce_field( 'colormag_reading_time_nonce', 'colormag_reading_time_nonce_field' );
	
	$reading_time = colormag_calculate_reading_time( $post->ID );
	
	echo '<p>';
	printf(
		/* translators: %d: reading time in minutes */
		esc_html__( 'Estimated reading time: %d minutes', 'colormag' ),
		$reading_time
	);
	echo '</p>';
	
	echo '<p class="howto">';
	esc_html_e( 'This is automatically calculated based on word count.', 'colormag' );
	echo '</p>';
}

/**
 * Add reading time to post meta display.
 *
 * @param array $meta Post meta items.
 *
 * @return array
 */
function colormag_add_reading_time_to_meta( $meta ) {
	if ( is_single() && 'post' === get_post_type() ) {
		$meta['reading_time'] = array(
			'icon'    => 'time',
			'html'    => colormag_display_reading_time( null, false ),
			'enabled' => true,
		);
	}
	
	return $meta;
}

add_filter( 'colormag_post_meta', 'colormag_add_reading_time_to_meta' );
