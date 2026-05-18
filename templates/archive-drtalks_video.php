<?php
/**
 * Archive template for drtalks_video CPT.
 *
 * @package DrTalksVideos
 */

get_header();
wp_enqueue_style( 'drtalks-frontend', DRTALKS_VIDEOS_URL . 'assets/frontend.css', [], DRTALKS_VIDEOS_VERSION );
?>

<main class="drtalks-archive">
	<div class="drtalks-archive-inner">
		<?php if ( have_posts() ) : ?>
			<h1 class="drtalks-archive-title"><?php post_type_archive_title(); ?></h1>
			<div class="drtalks-video-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php
					$video_slug    = get_post_meta( get_the_ID(), '_drtalks_video_slug', true );
					$thumbnail     = get_post_meta( get_the_ID(), '_drtalks_thumbnail', true );
					$expert_name   = get_post_meta( get_the_ID(), '_drtalks_expert_name', true );
					$duration_secs = (int) get_post_meta( get_the_ID(), '_drtalks_duration', true );
					$duration      = drtalks_format_duration( $duration_secs );
					?>
					<article class="drtalks-video-card">
						<a href="<?php the_permalink(); ?>" class="drtalks-card-link">
							<?php if ( $thumbnail ) : ?>
								<div class="drtalks-card-thumbnail">
									<img
										src="<?php echo esc_url( $thumbnail ); ?>"
										alt="<?php echo esc_attr( get_the_title() ); ?>"
										loading="lazy"
									/>
									<?php if ( $duration ) : ?>
										<span class="drtalks-card-duration"><?php echo esc_html( $duration ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="drtalks-card-body">
								<h2 class="drtalks-card-title"><?php the_title(); ?></h2>
								<?php if ( $expert_name ) : ?>
									<p class="drtalks-card-expert"><?php echo esc_html( $expert_name ); ?></p>
								<?php endif; ?>
							</div>
						</a>
					</article>
				<?php endwhile; ?>
			</div>
			<div class="drtalks-pagination">
				<?php the_posts_pagination(); ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No videos found.', 'drtalks-videos' ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php get_footer();
