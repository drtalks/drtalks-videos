<?php
/**
 * Expert bio block.
 *
 * Override: copy to yourtheme/drtalks-videos/partials/expert-bio.php
 *
 * Available variables:
 *
 * @var array $m Video meta from drtalks_get_video_meta(). Renders nothing when
 *               $m['expert_name'] is empty.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $m['expert_name'] ) ) {
	return;
}
?>
<div class="drtalks-expert-bio">
	<h2 class="drtalks-expert-heading"><?php esc_html_e( 'About the Expert', 'drtalks-videos' ); ?></h2>
	<div class="drtalks-expert-card">
		<?php if ( $m['expert_photo'] ) : ?>
		<img src="<?php echo esc_url( $m['expert_photo'] ); ?>" alt="<?php echo esc_attr( $m['expert_name'] ); ?>" class="drtalks-expert-photo">
		<?php endif; ?>
		<div class="drtalks-expert-info">
			<p class="drtalks-expert-name"><?php echo esc_html( $m['expert_name'] ); ?></p>
			<?php if ( $m['expert_title'] ) : ?>
			<p class="drtalks-expert-title"><?php echo esc_html( $m['expert_title'] ); ?></p>
			<?php endif; ?>
			<?php if ( $m['expert_creds'] ) : ?>
			<p class="drtalks-expert-credentials"><?php echo esc_html( $m['expert_creds'] ); ?></p>
			<?php endif; ?>
			<?php if ( $m['expert_bio'] ) : ?>
			<div class="drtalks-expert-bio-text"><?php echo nl2br( esc_html( $m['expert_bio'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( $m['expert_slug'] ) : ?>
			<a href="<?php echo esc_url( 'https://drtalks.com/experts/' . rawurlencode( $m['expert_slug'] ) ); ?>" class="drtalks-expert-profile-link" target="_blank" rel="noopener noreferrer">
				<?php printf( esc_html__( 'More from %s on DrTalks', 'drtalks-videos' ), esc_html( $m['expert_name'] ) ); ?>
			</a>
			<?php endif; ?>
		</div>
	</div>
</div>
