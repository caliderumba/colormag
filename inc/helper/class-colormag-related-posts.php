<?php
/**
 * Related posts functionality.
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
 * Get related posts based on categories or tags.
 *
 * @param int    $post_id Post ID.
 * @param int    $number  Number of posts to retrieve.
 * @param string $by      Relation type: 'category' or 'tag'.
 *
 * @return WP_Query
 */
function colormag_get_related_posts( $post_id = null, $number = 3, $by = 'category' ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Query();
	}

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $number,
		'post__not_in'   => array( $post_id ),
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	);

	if ( 'category' === $by ) {
		$categories = get_the_category( $post_id );
		
		if ( empty( $categories ) ) {
			return new WP_Query();
		}

		$category_ids = wp_list_pluck( $categories, 'term_id' );
		
		$args['category__in'] = $category_ids;
	} elseif ( 'tag' === $by ) {
		$tags = get_the_tags( $post_id );
		
		if ( empty( $tags ) ) {
			return new WP_Query();
		}

		$tag_ids = wp_list_pluck( $tags, 'term_id' );
		
		$args['tag__in'] = $tag_ids;
	}

	// Prioritize posts with same categories/tags.
	$args['orderby'] = 'date';
	$args['order']   = 'DESC';

	return new WP_Query( $args );
}

/**
 * Display related posts section.
 *
 * @param int    $post_id Post ID.
 * @param int    $number  Number of posts.
 * @param string $by      Relation type.
 * @param bool   $echo    Whether to echo or return.
 *
 * @return string|void
 */
function colormag_display_related_posts( $post_id = null, $number = 3, $by = 'category', $echo = true ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}

	$related_posts = colormag_get_related_posts( $post_id, $number, $by );

	if ( ! $related_posts->have_posts() ) {
		return '';
	}

	ob_start();
	?>
	
	<div class="cm-related-posts cm-related-posts--<?php echo esc_attr( $by ); ?>">
		<h3 class="cm-related-posts-title">
			<?php echo esc_html( apply_filters( 'colormag_related_posts_title', esc_html__( 'Related Posts', 'colormag' ) ) ); ?>
		</h3>
		
		<div class="cm-related-posts-grid">
			<?php while ( $related_posts->have_posts() ) : $related_posts->the_post(); ?>
				
				<article class="cm-related-post-item" id="post-<?php the_ID(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="cm-related-post-thumbnail">
							<a href="<?php the_permalink(); ?>">
								<?php the_post_thumbnail( 'medium' ); ?>
							</a>
						</div>
					<?php endif; ?>
					
					<div class="cm-related-post-content">
						<?php colormag_colored_category(); ?>
						
						<h4 class="cm-related-post-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h4>
						
						<div class="cm-related-post-meta">
							<span class="cm-post-date">
								<?php echo get_the_date(); ?>
							</span>
							
							<?php colormag_display_post_views( get_the_ID(), true ); ?>
						</div>
					</div>
				</article>
				
			<?php endwhile; ?>
		</div>
	</div>
	
	<?php
	wp_reset_postdata();
	
	$output = ob_get_clean();
	
	if ( $echo ) {
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		return $output;
	}
}

/**
 * Add related posts to single post template.
 */
function colormag_add_related_posts_to_content() {
	if ( ! is_single() || 'post' !== get_post_type() ) {
		return;
	}

	// Check if related posts are enabled.
	$show_related = get_theme_mod( 'colormag_show_related_posts', true );
	
	if ( ! $show_related ) {
		return;
	}

	$related_by   = get_theme_mod( 'colormag_related_posts_by', 'category' );
	$related_num  = get_theme_mod( 'colormag_related_posts_number', 3 );
	
	// Hook into content after the post.
	add_action( 'colormag_after_post_content', 'colormag_render_related_posts_section' );
}

add_action( 'wp', 'colormag_add_related_posts_to_content' );

/**
 * Render related posts section.
 */
function colormag_render_related_posts_section() {
	$related_by  = get_theme_mod( 'colormag_related_posts_by', 'category' );
	$related_num = get_theme_mod( 'colormag_related_posts_number', 3 );
	
	colormag_display_related_posts( null, $related_num, $related_by, true );
}
