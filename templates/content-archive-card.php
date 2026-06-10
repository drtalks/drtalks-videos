<?php
/**
 * Single archive video card.
 *
 * Override: copy to yourtheme/drtalks-videos/content-archive-card.php
 *
 * Available variables:
 *
 * @var int    $post_id
 * @var string $title
 * @var string $permalink
 * @var string $thumbnail
 * @var string $duration   Pre-formatted MM:SS string (may be empty).
 * @var string $expert_name
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="drtalks-video-card">
	<a href="<?php echo esc_url( $permalink ); ?>" class="drtalks-card-link">
		<div class="drtalks-card-thumbnail">
			<?php if ( $thumbnail ) : ?>
			<img src="<?php echo esc_url( $thumbnail ); ?>"
				alt="<?php echo esc_attr( $title ); ?>"
				loading="lazy">
			<?php else : ?>
			<div class="drtalks-card-thumbnail-placeholder"></div>
			<?php endif; ?>
			<?php if ( $duration ) : ?>
			<span class="drtalks-card-duration"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
		</div>
		<div class="drtalks-card-body">
			<h2 class="drtalks-card-title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( $expert_name ) : ?>
			<p class="drtalks-card-expert"><?php echo esc_html( $expert_name ); ?></p>
			<?php endif; ?>
		</div>
	</a>
</article>
