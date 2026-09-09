<?php
/**
 * Single-video body — "theme" layout (standard content flow).
 *
 * Override: copy to yourtheme/drtalks-videos/content-single-theme.php
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
<div class="drtalks-single-video drtalks-layout-theme">

	<div class="drtalks-video-content">

		<?php if ( $m['embed_url'] ) : ?>
		<div class="drtalks-video-player">
			<iframe
				src="<?php echo esc_url( drtalks_embed_url_with_time( $m['embed_url'] ) ); ?>"
				frameborder="0"
				allow="autoplay; fullscreen; picture-in-picture"
				allowfullscreen
			></iframe>
		</div>
		<?php elseif ( $m['thumbnail'] ) : ?>
		<img src="<?php echo esc_url( $m['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" class="drtalks-video-thumbnail-fallback">
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

	<?php drtalks_render_media_panel( $m['chapters'], $m['cues'], $m['transcript'], $m['allowed_html'] ); ?>

		<?php drtalks_render_expert_bio( $m ); ?>

		<?php drtalks_render_guests( $m ); ?>

	</div>

</div>
