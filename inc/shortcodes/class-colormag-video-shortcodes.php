<?php
/**
 * Shortcode for displaying video galleries.
 *
 * @package    ThemeGrill
 * @subpackage ColorMag
 * @since      ColorMag 3.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Video Gallery Shortcode.
 *
 * Usage: [colormag_video_gallery number="6" category="videos" layout="grid"]
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function colormag_video_gallery_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'number'       => 6,
			'category'     => '',
			'tag'          => '',
			'layout'       => 'grid', // grid, list, carousel
			'columns'      => 3,
			'show_title'   => 'true',
			'show_excerpt' => 'false',
			'class'        => '',
		),
		$atts,
		'colormag_video_gallery'
	);

	$query_args = array(
		'post_type'      => 'post',
		'posts_per_page' => intval( $atts['number'] ),
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array( 'post-format-video' ),
			),
		),
	);

	if ( ! empty( $atts['category'] ) ) {
		$category = get_category_by_slug( $atts['category'] );
		if ( $category ) {
			$query_args['cat'] = $category->term_id;
		}
	}

	if ( ! empty( $atts['tag'] ) ) {
		$query_args['tag'] = $atts['tag'];
	}

	$video_query = new WP_Query( $query_args );

	if ( ! $video_query->have_posts() ) {
		return '<p>' . esc_html__( 'No videos found.', 'colormag' ) . '</p>';
	}

	ob_start();
	?>
	
	<div class="cm-video-gallery cm-video-gallery--<?php echo esc_attr( $atts['layout'] ); ?> <?php echo esc_attr( $atts['class'] ); ?>" 
	     data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
		
		<?php if ( 'carousel' === $atts['layout'] ) : ?>
			<div class="cm-video-carousel">
		<?php endif; ?>

		<?php while ( $video_query->have_posts() ) : $video_query->the_post(); ?>
			
			<div class="cm-video-gallery-item">
				<div class="cm-video-thumbnail">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large' ); ?>
					<?php else : ?>
						<div class="cm-video-placeholder">
							<svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M8 5v14l11-7z" fill="currentColor"/>
							</svg>
						</div>
					<?php endif; ?>
					
					<div class="cm-video-play-overlay">
						<span class="cm-play-button">
							<svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M8 5v14l11-7z" fill="currentColor"/>
							</svg>
						</span>
					</div>
				</div>

				<?php if ( 'true' === $atts['show_title'] ) : ?>
					<div class="cm-video-info">
						<h3 class="cm-video-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>
						
						<div class="cm-video-meta">
							<?php colormag_colored_category(); ?>
							<span class="cm-video-date"><?php echo get_the_date(); ?></span>
						</div>

						<?php if ( 'true' === $atts['show_excerpt'] ) : ?>
							<div class="cm-video-excerpt">
								<?php echo wp_trim_words( get_the_excerpt(), 15 ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

		<?php endwhile; ?>

		<?php if ( 'carousel' === $atts['layout'] ) : ?>
			</div>
		<?php endif; ?>
		
	</div>

	<?php
	wp_reset_postdata();

	// Enqueue necessary scripts for carousel.
	if ( 'carousel' === $atts['layout'] ) {
		wp_enqueue_script( 'jquery-bxslider' );
		wp_enqueue_style( 'jquery-bxslider-css' );
	}

	return ob_get_clean();
}
add_shortcode( 'colormag_video_gallery', 'colormag_video_gallery_shortcode' );
