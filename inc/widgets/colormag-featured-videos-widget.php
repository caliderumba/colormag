<?php
/**
 * TG: Featured Videos widget.
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
 * TG: Featured Videos widget class.
 *
 * Class colormag_featured_videos_widget
 */
class colormag_featured_videos_widget extends ColorMag_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->widget_cssclass    = 'cm-featured-videos cm-featured-videos--style-1';
		$this->widget_description = esc_html__( 'Display featured videos from posts with video format or video category.', 'colormag' );
		$this->widget_name        = esc_html__( 'TG: Featured Videos', 'colormag' );
		$this->settings           = array(
			'widget_layout' => array(
				'type'      => 'custom',
				'default'   => '',
				'label'     => esc_html__( 'Layout will be as below:', 'colormag' ),
				'image_url' => get_template_directory_uri() . '/assets/img/style-1.jpg',
			),
			'title'         => array(
				'type'    => 'text',
				'default' => '',
				'label'   => esc_html__( 'Title:', 'colormag' ),
			),
			'text'          => array(
				'type'    => 'textarea',
				'default' => '',
				'label'   => esc_html__( 'Description', 'colormag' ),
			),
			'number'        => array(
				'type'    => 'number',
				'default' => 4,
				'label'   => esc_html__( 'Number of videos to display:', 'colormag' ),
			),
			'type'          => array(
				'type'    => 'radio',
				'default' => 'latest',
				'label'   => '',
				'choices' => array(
					'latest'   => esc_html__( 'Show latest Videos', 'colormag' ),
					'category' => esc_html__( 'Show videos from a category', 'colormag' ),
					'tag'      => esc_html__( 'Show videos from a tag', 'colormag' ),
				),
			),
			'category'      => array(
				'type'    => 'dropdown_categories',
				'default' => '',
				'label'   => esc_html__( 'Select category', 'colormag' ),
			),
			'tag'           => array(
				'type'    => 'dropdown_tags',
				'default' => '',
				'label'   => esc_html__( 'Select tag', 'colormag' ),
			),
			'video_source'  => array(
				'type'    => 'select',
				'default' => 'all',
				'label'   => esc_html__( 'Video Source', 'colormag' ),
				'choices' => array(
					'all'       => esc_html__( 'All Sources', 'colormag' ),
					'youtube'   => esc_html__( 'YouTube Only', 'colormag' ),
					'vimeo'     => esc_html__( 'Vimeo Only', 'colormag' ),
					'dailymotion' => esc_html__( 'Dailymotion Only', 'colormag' ),
					'local'     => esc_html__( 'Local/Uploaded Videos Only', 'colormag' ),
				),
			),
		);

		parent::__construct();
	}

	/**
	 * Output widget.
	 *
	 * @param array $args     Arguments.
	 * @param array $instance Widget instance.
	 *
	 * @see WP_Widget
	 */
	public function widget( $args, $instance ) {

		global $post;
		$title        = apply_filters( 'widget_title', isset( $instance['title'] ) ? $instance['title'] : '' );
		$text         = isset( $instance['text'] ) ? $instance['text'] : '';
		$number       = empty( $instance['number'] ) ? 4 : $instance['number'];
		$type         = isset( $instance['type'] ) ? $instance['type'] : 'latest';
		$category     = isset( $instance['category'] ) ? $instance['category'] : '';
		$tag          = isset( $instance['tag'] ) ? $instance['tag'] : '';
		$video_source = isset( $instance['video_source'] ) ? $instance['video_source'] : 'all';

		// Create the posts query for videos.
		$get_featured_videos = $this->query_videos( $number, $type, $category, $tag, $video_source );

		$this->widget_start( $args );
		?>

		<?php
		// Displays the widget title.
		$this->widget_title( $title, $type, $category );

		// Display the description.
		$this->widget_description( $text );

		if ( $get_featured_videos->have_posts() ) :
			?>
			<div class="cm-video-grid">
				<?php
				$i = 1;
				while ( $get_featured_videos->have_posts() ) :
					$get_featured_videos->the_post();
					?>

					<div class="cm-video-item <?php echo ( 1 == $i ) ? 'cm-video-item--featured' : ''; ?>">
						<?php
						if ( has_post_thumbnail() ) {
							$this->the_post_thumbnail( $post->ID, 'colormag-featured-post-medium' );
						}
						?>

						<div class="cm-video-overlay">
							<span class="cm-play-icon">
								<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M8 5v14l11-7z" fill="currentColor"/>
								</svg>
							</span>
						</div>

						<div class="cm-video-content">
							<?php
							colormag_colored_category();

							// Displays the post title.
							$this->the_title();

							// Displays the post meta.
							$this->entry_meta();
							?>
						</div>
					</div>

					<?php
					++$i;
				endwhile;
				?>
			</div>
			<?php
		else :
			echo '<p>' . esc_html__( 'No videos found.', 'colormag' ) . '</p>';
		endif;

		// Reset Post Data.
		wp_reset_postdata();

		$this->widget_end( $args );
	}

	/**
	 * Query videos based on parameters.
	 *
	 * @param int    $number       Number of videos.
	 * @param string $type         Query type.
	 * @param int    $category     Category ID.
	 * @param int    $tag          Tag ID.
	 * @param string $video_source Video source filter.
	 *
	 * @return WP_Query
	 */
	public function query_videos( $number, $type, $category, $tag, $video_source ) {
		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => $number,
			'no_found_rows'  => true,
		);

		// Add tax_query for video format.
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array( 'post-format-video' ),
			),
		);

		if ( 'category' == $type && $category ) {
			$args['cat'] = $category;
		}

		if ( 'tag' == $type && $tag ) {
			$args['tag_id'] = $tag;
		}

		// Filter by video source if specified.
		if ( 'all' !== $video_source ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'video_source',
					'value'   => $video_source,
					'compare' => '=',
				),
			);
		}

		return new WP_Query( $args );
	}
}
