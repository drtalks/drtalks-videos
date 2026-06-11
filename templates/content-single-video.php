<?php
/**
 * Single-video body — "video" layout (YouTube-style two-column).
 *
 * Override: copy to yourtheme/drtalks-videos/content-single-video.php
 *
 * Available variables:
 *
 * @var int   $post_id
 * @var array $m          Video meta from drtalks_get_video_meta().
 * @var bool  $show_watch Whether the "Watch on DrTalks" CTA should render.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="drtalks-single-video drtalks-layout-video">
	<div class="drtalks-yt-wrap">

		<div class="drtalks-yt-main">

			<?php if ( $m['embed_url'] ) : ?>
			<div class="drtalks-video-player">
				<iframe
					src="<?php echo esc_url( $m['embed_url'] ); ?>"
					frameborder="0"
					allow="autoplay; fullscreen; picture-in-picture"
					allowfullscreen
				></iframe>
			</div>
			<?php elseif ( $m['thumbnail'] ) : ?>
			<div class="drtalks-player-outer">
				<img src="<?php echo esc_url( $m['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" class="drtalks-video-thumbnail-fallback">
			</div>
			<?php endif; ?>

		<?php if ( $show_watch && $m['drtalks_url'] ) : ?>
		<div class="drtalks-watch-cta">
			<a href="<?php echo esc_url( $m['drtalks_url'] ); ?>" class="drtalks-watch-link" target="_blank" rel="noopener noreferrer">Watch on DrTalks</a>
		</div>
		<?php endif; ?>

		<h1 class="drtalks-video-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>

		<?php if ( $m['description'] ) : ?>
		<div class="drtalks-video-description">
			<?php echo wp_kses( $m['description'], $m['allowed_html'] ); ?>
		</div>
		<?php endif; ?>

		<?php drtalks_render_expert_bio( $m ); ?>

		<?php drtalks_render_guests( $m ); ?>

		</div>

		<?php if ( $m['transcript'] ) : ?>
		<aside class="drtalks-yt-sidebar">
			<div class="drtalks-sidebar-transcript">
				<h2 class="drtalks-sidebar-heading"><?php esc_html_e( 'Transcript', 'drtalks-videos' ); ?></h2>
				<div class="drtalks-transcript-content">
					<?php echo wp_kses( $m['transcript'], $m['allowed_html'] ); ?>
				</div>
			</div>
		</aside>
		<?php endif; ?>

	</div>
</div>
