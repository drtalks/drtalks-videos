<?php
/**
 * Guest bios block — one card per guest.
 *
 * Override: copy to yourtheme/drtalks-videos/partials/guests.php
 *
 * Available variables:
 *
 * @var array $m Video meta from drtalks_get_video_meta(). $m['guests'] is a list
 *               of guests, each: { slug, name, photo_url, bio, credentials, title }.
 *               Renders nothing when there are no guests.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$guests = isset( $m['guests'] ) && is_array( $m['guests'] ) ? $m['guests'] : [];
if ( empty( $guests ) ) {
	return;
}
?>
<div class="drtalks-guest-bios">
	<h2 class="drtalks-expert-heading drtalks-guest-heading">
		<?php echo esc_html( _n( 'Guest', 'Guests', count( $guests ), 'drtalks-videos' ) ); ?>
	</h2>
	<?php foreach ( $guests as $guest ) : ?>
		<?php if ( empty( $guest['name'] ) ) { continue; } ?>
	<div class="drtalks-expert-card drtalks-guest-card">
		<?php if ( ! empty( $guest['photo_url'] ) ) : ?>
		<img src="<?php echo esc_url( $guest['photo_url'] ); ?>" alt="<?php echo esc_attr( $guest['name'] ); ?>" class="drtalks-expert-photo">
		<?php endif; ?>
		<div class="drtalks-expert-info">
			<p class="drtalks-expert-name"><?php echo esc_html( $guest['name'] ); ?></p>
			<?php if ( ! empty( $guest['title'] ) ) : ?>
			<p class="drtalks-expert-title"><?php echo esc_html( $guest['title'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $guest['credentials'] ) ) : ?>
			<p class="drtalks-expert-credentials"><?php echo esc_html( $guest['credentials'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $guest['bio'] ) ) : ?>
			<div class="drtalks-expert-bio-text"><?php echo nl2br( esc_html( $guest['bio'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $guest['slug'] ) ) : ?>
			<a href="<?php echo esc_url( 'https://drtalks.com/experts/' . rawurlencode( $guest['slug'] ) ); ?>" class="drtalks-expert-profile-link" target="_blank" rel="noopener noreferrer">
				<?php printf( esc_html__( 'More from %s on DrTalks', 'drtalks-videos' ), esc_html( $guest['name'] ) ); ?>
			</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>
