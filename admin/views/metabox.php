<?php
/**
 * Metabox HTML — rendered on the drtalks_video CPT edit screen.
 *
 * @package DrTalksVideos
 * @var WP_Post $post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_slug   = get_post_meta( $post->ID, '_drtalks_video_slug', true );
$embed_url    = get_post_meta( $post->ID, '_drtalks_embed_url', true );
$thumbnail    = get_post_meta( $post->ID, '_drtalks_thumbnail', true );
$expert_name  = get_post_meta( $post->ID, '_drtalks_expert_name', true );
$synced_at    = (int) get_post_meta( $post->ID, '_drtalks_synced_at', true );
?>

<div class="drtalks-metabox" id="drtalks-metabox-root">

	<?php if ( $video_slug ) : ?>
	<div class="drtalks-current-video">
		<p>
			<strong><?php esc_html_e( 'Current video:', 'drtalks-videos' ); ?></strong>
			<?php echo esc_html( $video_slug ); ?>
		</p>
		<?php if ( $synced_at ) : ?>
			<p class="description">
				<?php printf( esc_html__( 'Last synced: %s', 'drtalks-videos' ), esc_html( wp_date( 'Y-m-d H:i:s', $synced_at ) ) ); ?>
			</p>
		<?php endif; ?>
		<button type="button" class="button" id="drtalks-refresh-single" data-slug="<?php echo esc_attr( $video_slug ); ?>">
			<?php esc_html_e( 'Refresh from DrTalks', 'drtalks-videos' ); ?>
		</button>
	</div>
	<hr />
	<?php endif; ?>

	<!-- Mode 1: Search & Select -->
	<div class="drtalks-mode" id="drtalks-mode-search">
		<h4><?php esc_html_e( 'Mode 1 — Search & Select a Video', 'drtalks-videos' ); ?></h4>
		<input
			type="text"
			id="drtalks-video-search"
			placeholder="<?php esc_attr_e( 'Search videos…', 'drtalks-videos' ); ?>"
			class="widefat"
		/>
		<div id="drtalks-video-results" class="drtalks-results-list" style="display:none;"></div>
	</div>

	<hr />

	<!-- Mode 2: Expert Auto-Load -->
	<div class="drtalks-mode" id="drtalks-mode-expert">
		<h4><?php esc_html_e( 'Mode 2 — Load All Videos for an Expert', 'drtalks-videos' ); ?></h4>
		<input
			type="text"
			id="drtalks-expert-search"
			placeholder="<?php esc_attr_e( 'Search experts…', 'drtalks-videos' ); ?>"
			class="widefat"
		/>
		<div id="drtalks-expert-results" class="drtalks-results-list" style="display:none;"></div>

		<div id="drtalks-expert-selected" style="display:none;">
			<p>
				<strong><?php esc_html_e( 'Selected expert:', 'drtalks-videos' ); ?></strong>
				<span id="drtalks-expert-name"></span>
				<input type="hidden" id="drtalks-expert-slug-hidden" value="" />
			</p>
			<button type="button" id="drtalks-load-all-videos" class="button button-primary">
				<?php esc_html_e( 'Load All Videos', 'drtalks-videos' ); ?>
			</button>
		</div>

		<div id="drtalks-import-progress" style="display:none;">
			<div class="drtalks-progress-bar">
				<div class="drtalks-progress-fill" style="width:0%;"></div>
			</div>
			<p id="drtalks-import-status"></p>
		</div>
	</div>

</div>
