<?php
/**
 * Shared render function used by both block and shortcode.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the DrTalks video embed HTML for a given slug.
 *
 * Embed URL source priority:
 *  1. CPT post meta (_drtalks_embed_url) — authoritative, set during sync.
 *  2. Fallback constructed URL (https://drtalks.com/embed/videos/{slug}).
 *
 * Transcript source priority:
 *  1. CPT post meta (_drtalks_transcript) — no API call.
 *  2. Transient drtalks_transcript_{slug} — no API call.
 *  3. Miss → render without transcript + schedule background fetch.
 */
function drtalks_render_video_embed( string $slug ): string {
	$slug = sanitize_title( $slug );

	// Prefer the stored embed URL from the CPT post if it exists.
	$embed_url = '';
	$cpt_posts = get_posts( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => [
			[ 'key' => '_drtalks_video_slug', 'value' => $slug ],
		],
	] );
	if ( $cpt_posts ) {
		$embed_url = get_post_meta( $cpt_posts[0]->ID, '_drtalks_embed_url', true );
	}
	if ( ! $embed_url ) {
		// Fallback: construct embed URL from slug.
		$embed_url = 'https://drtalks.com/embed/videos/' . rawurlencode( $slug );
		DrTalks_Debug::warn( "drtalks_render_video_embed: no stored embed_url for slug '$slug', using fallback." );
	}

	$transcript = drtalks_get_transcript( $slug );

	ob_start();
	?>
	<div class="drtalks-video-embed" data-slug="<?php echo esc_attr( $slug ); ?>">
		<div class="drtalks-video-player" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">
			<iframe
				src="<?php echo esc_url( $embed_url ); ?>"
				style="position:absolute;top:0;left:0;width:100%;height:100%;"
				frameborder="0"
				allow="autoplay; fullscreen; picture-in-picture"
				allowfullscreen
			></iframe>
		</div>
		<?php if ( $transcript ) : ?>
		<div class="drtalks-video-transcript">
			<details>
				<summary><?php esc_html_e( 'View Transcript', 'drtalks-videos' ); ?></summary>
				<div class="drtalks-transcript-content">
					<?php
					$allowed_tags = [
						'p'      => [],
						'strong' => [],
						'em'     => [],
						'ul'     => [],
						'ol'     => [],
						'li'     => [],
						'br'     => [],
						'a'      => [ 'href' => [], 'title' => [] ],
					];
					echo wp_kses( $transcript, $allowed_tags );
					?>
				</div>
			</details>
		</div>
		<?php endif; ?>
	</div>
	<?php
	wp_enqueue_style(
		'drtalks-frontend',
		DRTALKS_VIDEOS_URL . 'assets/frontend.css',
		[],
		DRTALKS_VIDEOS_VERSION
	);

	return ob_get_clean();
}

/**
 * Retrieve transcript for a slug, scheduling a background fetch if needed.
 */
function drtalks_get_transcript( string $slug ): string {
	// 1. CPT post meta.
	$posts = get_posts( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => [
			[
				'key'   => '_drtalks_video_slug',
				'value' => $slug,
			],
		],
	] );
	if ( $posts ) {
		$transcript = get_post_meta( $posts[0]->ID, '_drtalks_transcript', true );
		if ( $transcript ) {
			return $transcript;
		}
	}

	// 2. Transient cache.
	$cache_key  = 'drtalks_transcript_' . sanitize_key( $slug );
	$transient  = get_transient( $cache_key );
	if ( $transient !== false ) {
		return $transient;
	}

	// 3. Background refresh — do not block page render.
	if ( ! wp_next_scheduled( 'drtalks_fetch_transcript', [ $slug ] ) ) {
		wp_schedule_single_event( time() + 5, 'drtalks_fetch_transcript', [ $slug ] );
	}

	return '';
}

// Background event: fetch + cache transcript.
add_action( 'drtalks_fetch_transcript', function ( string $slug ) {
	$api    = new DrTalks_API_Client();
	$result = $api->get_video( $slug );
	if ( is_wp_error( $result ) ) {
		return;
	}

	$transcript = $result['_drtalks_transcript'] ?? '';
	if ( $transcript ) {
		set_transient( 'drtalks_transcript_' . sanitize_key( $slug ), $transcript, DAY_IN_SECONDS );
	}
} );

/**
 * Inject the full video layout into the block theme's wp:post-content slot.
 * Also used by the classic-theme PHP template.
 */
add_filter( 'the_content', function ( string $content ): string {
	if ( ! is_singular( 'drtalks_video' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	wp_enqueue_style( 'drtalks-frontend', DRTALKS_VIDEOS_URL . 'assets/frontend.css', [], DRTALKS_VIDEOS_VERSION );
	return drtalks_render_single_video_content( get_the_ID() );
} );

/**
 * Render the complete single-video page content (title, player, description,
 * transcript, expert bio, Watch-on-DrTalks CTA) as an HTML string.
 */
function drtalks_render_single_video_content( int $post_id ): string {
	$style = get_option( 'drtalks_video_template_style', 'theme' );
	return $style === 'video'
		? drtalks_render_single_video_content_video( $post_id )
		: drtalks_render_single_video_content_theme( $post_id );
}

/**
 * Shared meta + allowed_html loader for the two render functions.
 */
function drtalks_get_video_meta( int $post_id ): array {
	$video_slug = get_post_meta( $post_id, '_drtalks_video_slug', true );
	return [
		'video_slug'   => $video_slug,
		'embed_url'    => get_post_meta( $post_id, '_drtalks_embed_url', true ),
		'thumbnail'    => get_post_meta( $post_id, '_drtalks_thumbnail', true ),
		'description'  => get_post_meta( $post_id, '_drtalks_description', true ),
		'transcript'   => get_post_meta( $post_id, '_drtalks_transcript', true ),
		'expert_name'  => get_post_meta( $post_id, '_drtalks_expert_name', true ),
		'expert_slug'  => get_post_meta( $post_id, '_drtalks_expert_slug', true ),
		'expert_title' => get_post_meta( $post_id, '_drtalks_expert_title', true ),
		'expert_creds' => get_post_meta( $post_id, '_drtalks_expert_credentials', true ),
		'expert_photo' => get_post_meta( $post_id, '_drtalks_expert_photo', true ),
		'expert_bio'   => get_post_meta( $post_id, '_drtalks_expert_bio', true ),
		'drtalks_url'  => $video_slug ? 'https://drtalks.com/videos/' . rawurlencode( $video_slug ) : '',
		'allowed_html' => [
			'p'      => [],
			'strong' => [],
			'em'     => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'br'     => [],
			'a'      => [ 'href' => [], 'title' => [], 'rel' => [], 'target' => [] ],
		],
	];
}

/**
 * "Theme" layout — standard content flow with max-width body.
 */
function drtalks_render_single_video_content_theme( int $post_id ): string {
	$m = drtalks_get_video_meta( $post_id );
	ob_start();
	?>
	<div class="drtalks-single-video drtalks-layout-theme">

		<div class="drtalks-video-content">

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
			<img src="<?php echo esc_url( $m['thumbnail'] ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" class="drtalks-video-thumbnail-fallback">
			<?php endif; ?>

			<?php if ( $m['drtalks_url'] ) : ?>
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

			<?php if ( $m['transcript'] ) : ?>
			<div class="drtalks-video-transcript">
				<details>
					<summary><?php esc_html_e( 'View Transcript', 'drtalks-videos' ); ?></summary>
					<div class="drtalks-transcript-content">
						<?php echo wp_kses( $m['transcript'], $m['allowed_html'] ); ?>
					</div>
				</details>
			</div>
			<?php endif; ?>

			<?php drtalks_render_expert_bio( $m ); ?>

		</div>

	</div>
	<?php
	return ob_get_clean();
}

/**
 * "Video Page" layout — YouTube-style two-column:
 *   Left:  player, title, Watch CTA, description, expert bio
 *   Right: transcript sidebar
 */
function drtalks_render_single_video_content_video( int $post_id ): string {
	$m = drtalks_get_video_meta( $post_id );
	ob_start();
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

				<?php if ( $m['drtalks_url'] ) : ?>
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
	<?php
	return ob_get_clean();
}

/**
 * Shared expert bio partial — used by both layout functions.
 */
function drtalks_render_expert_bio( array $m ): void {
	if ( ! $m['expert_name'] ) {
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
	<?php
}

/**
 * Render a video embed for use inside a Gutenberg block, with per-section toggles.
 *
 * Options (all bool, default true):
 *   show_title, show_description, show_transcript, show_author
 */
function drtalks_render_block_video( string $slug, array $options = [] ): string {
	$show_video       = $options['show_video']       ?? true;
	$show_title       = $options['show_title']       ?? true;
	$show_description = $options['show_description'] ?? true;
	$show_transcript  = $options['show_transcript']  ?? true;
	$show_author      = $options['show_author']      ?? true;

	// Find the CPT post.
	$posts = get_posts( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => [
			[ 'key' => '_drtalks_video_slug', 'value' => $slug ],
		],
	] );

	if ( ! $posts ) {
		// No local post yet — sync it now so description/transcript/author are available.
		error_log( '[DrTalks render_block_video] slug=' . $slug . ' — no CPT post found, syncing now.' );
		$sync = new DrTalks_Sync();
		$sync->sync_video( $slug );

		// Re-fetch after sync.
		$posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => [
				[ 'key' => '_drtalks_video_slug', 'value' => $slug ],
			],
		] );
	}

	if ( $posts ) {
		$post_id = $posts[0]->ID;
		$m       = drtalks_get_video_meta( $post_id );
		$title   = get_the_title( $post_id );
		error_log( '[DrTalks render_block_video] slug=' . $slug . ' post_id=' . $post_id
			. ' has_desc=' . ( ! empty( $m['description'] ) ? 'yes' : 'no' )
			. ' has_transcript=' . ( ! empty( $m['transcript'] ) ? 'yes' : 'no' )
			. ' has_expert=' . ( ! empty( $m['expert_name'] ) ? 'yes(' . $m['expert_name'] . ')' : 'no' ) );
	} else {
		// Sync failed — render player-only fallback.
		error_log( '[DrTalks render_block_video] slug=' . $slug . ' — sync failed, using embed fallback.' );
		$m             = [];
		$title         = $slug;
		$m['embed_url']    = 'https://drtalks.com/embed/videos/' . rawurlencode( $slug );
		$m['drtalks_url']  = 'https://drtalks.com/videos/' . rawurlencode( $slug );
		$m['description']  = '';
		$m['transcript']   = '';
		$m['expert_name']  = '';
		$m['allowed_html'] = [];
	}

	wp_enqueue_style( 'drtalks-frontend', DRTALKS_VIDEOS_URL . 'assets/frontend.css', [], DRTALKS_VIDEOS_VERSION );

	ob_start();
	?>
	<div class="drtalks-single-video drtalks-layout-theme drtalks-block-embed">

		<div class="drtalks-video-content">

			<?php if ( $show_video && ! empty( $m['embed_url'] ) ) : ?>
			<div class="drtalks-video-player">
				<iframe
					src="<?php echo esc_url( $m['embed_url'] ); ?>"
					frameborder="0"
					allow="autoplay; fullscreen; picture-in-picture"
					allowfullscreen
				></iframe>
			</div>
			<?php endif; ?>

			<?php if ( $show_video && ! empty( $m['drtalks_url'] ) ) : ?>
			<div class="drtalks-watch-cta">
				<a href="<?php echo esc_url( $m['drtalks_url'] ); ?>" class="drtalks-watch-link" target="_blank" rel="noopener noreferrer">Watch on DrTalks</a>
			</div>
			<?php endif; ?>

			<?php if ( $show_title ) : ?>
			<h2 class="drtalks-video-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( $show_description && ! empty( $m['description'] ) ) : ?>
			<div class="drtalks-video-description">
				<?php echo wp_kses( $m['description'], $m['allowed_html'] ); ?>
			</div>
			<?php endif; ?>

			<?php if ( $show_transcript && ! empty( $m['transcript'] ) ) : ?>
			<div class="drtalks-video-transcript">
				<details>
					<summary><?php esc_html_e( 'View Transcript', 'drtalks-videos' ); ?></summary>
					<div class="drtalks-transcript-content">
						<?php echo wp_kses( $m['transcript'], $m['allowed_html'] ); ?>
					</div>
				</details>
			</div>
			<?php endif; ?>

			<?php if ( $show_author && ! empty( $m['expert_name'] ) ) : ?>
				<?php drtalks_render_expert_bio( $m ); ?>
			<?php endif; ?>

		</div>

	</div>
	<?php
	return ob_get_clean();
}

/**
 * Format seconds to MM:SS display string.
 */
function drtalks_format_duration( int $seconds ): string {
	if ( $seconds <= 0 ) {
		return '';
	}
	$minutes = (int) floor( $seconds / 60 );
	$secs    = $seconds % 60;

	return sprintf( '%d:%02d', $minutes, $secs );
}
