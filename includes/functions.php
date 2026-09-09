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
 * Enqueue the player postMessage bridge (chapter list + click-to-seek).
 * Call from every render path that outputs the embed iframe.
 */
function drtalks_enqueue_player_bridge(): void {
	wp_enqueue_script(
		'drtalks-player-bridge',
		DRTALKS_VIDEOS_URL . 'assets/player-bridge.js',
		[],
		DRTALKS_VIDEOS_VERSION,
		true
	);
}

/**
 * Forward the page's ?t= deep-link (seconds) onto the embed URL so the player
 * starts at that timestamp. This is what makes the Clip schema "Key Moments"
 * URLs (see class-seo.php) actually land at the right moment — the DrTalks
 * embed player reads ?t= from its own URL and seeks on load.
 */
function drtalks_embed_url_with_time( string $embed_url ): string {
	if ( ! $embed_url || ! isset( $_GET['t'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only deep link.
		return $embed_url;
	}
	$t = absint( wp_unslash( $_GET['t'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return $t > 0 ? add_query_arg( 't', $t, $embed_url ) : $embed_url;
}

/**
 * Format a chapter/cue start time for display: m:ss, or h:mm:ss past an hour.
 */
function drtalks_format_timestamp( float $seconds ): string {
	$seconds = max( 0, (int) floor( $seconds ) );
	$h       = (int) floor( $seconds / 3600 );
	$m       = (int) floor( ( $seconds % 3600 ) / 60 );
	$s       = $seconds % 60;

	return $h > 0
		? sprintf( '%d:%02d:%02d', $h, $m, $s )
		: sprintf( '%d:%02d', $m, $s );
}

/**
 * Print clickable, timestamped rows (shared by chapters and transcript cues).
 * assets/player-bridge.js binds clicks (seek the embed player via postMessage)
 * and highlights the active row from the player's playback position.
 *
 * @param array[] $items      Each: [ 'start' => float, 'end' => float, plus the text key ].
 * @param string  $text_key   'title' (chapters) or 'text' (cues).
 * @param string  $row_class  drtalks-chapter-row | drtalks-cue-row
 * @param string  $time_class drtalks-chapter-time | drtalks-cue-time
 * @param string  $text_class drtalks-chapter-title | drtalks-cue-text
 */
function drtalks_render_seek_rows( array $items, string $text_key, string $row_class, string $time_class, string $text_class ): void {
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['start'], $item[ $text_key ] ) ) {
			continue;
		}
		drtalks_render_seek_row( (float) $item['start'], (float) ( $item['end'] ?? 0 ), (string) $item[ $text_key ], $row_class, $time_class, $text_class );
	}
}

/**
 * Print one clickable, timestamped row.
 */
function drtalks_render_seek_row( float $start, float $end, string $text, string $row_class, string $time_class, string $text_class ): void {
	?>
	<button
		type="button"
		class="<?php echo esc_attr( $row_class ); ?>"
		data-start="<?php echo esc_attr( (string) $start ); ?>"
		data-end="<?php echo esc_attr( (string) $end ); ?>"
	>
		<span class="<?php echo esc_attr( $time_class ); ?>"><?php echo esc_html( drtalks_format_timestamp( $start ) ); ?></span>
		<span class="<?php echo esc_attr( $text_class ); ?>"><?php echo esc_html( $text ); ?></span>
	</button>
	<?php
}

/**
 * Print the synced transcript: cue rows with a non-interactive chapter heading
 * inserted where each chapter begins. The headings give the transcript visible
 * on-page structure (real <h3>s, good for long-tail search) without turning
 * the interactive seek rows themselves into headings.
 */
function drtalks_render_transcript_cues( array $cues, array $chapters ): void {
	// Chapters come start-ascending from the API, but don't rely on it.
	usort( $chapters, static function ( $a, $b ) {
		return ( (float) ( $a['start'] ?? 0 ) ) <=> ( (float) ( $b['start'] ?? 0 ) );
	} );

	$chapter_index = 0;
	$chapter_count = count( $chapters );

	foreach ( $cues as $cue ) {
		if ( ! is_array( $cue ) || ! isset( $cue['start'], $cue['text'] ) ) {
			continue;
		}
		while ( $chapter_index < $chapter_count
			&& (float) ( $chapters[ $chapter_index ]['start'] ?? 0 ) <= (float) $cue['start'] ) {
			$title = (string) ( $chapters[ $chapter_index ]['title'] ?? '' );
			if ( $title !== '' ) {
				printf( '<h3 class="drtalks-transcript-chapter">%s</h3>', esc_html( $title ) );
			}
			$chapter_index++;
		}
		drtalks_render_seek_row( (float) $cue['start'], (float) ( $cue['end'] ?? 0 ), (string) $cue['text'], 'drtalks-cue-row', 'drtalks-cue-time', 'drtalks-cue-text' );
	}
}

/**
 * Chapters/Transcript panel — tabbed when the video has both, single heading
 * when it has one, nothing when it has neither.
 *
 * The transcript is the synced variant (timestamped, click-to-seek cue rows
 * parsed from the video's captions, with search) when cues are stored, and the
 * static transcript HTML otherwise. Tab switching, search, seeking, and the
 * playback-following highlight all live in assets/player-bridge.js.
 */
function drtalks_render_media_panel( array $chapters, array $cues, string $transcript, array $allowed_html ): void {
	$has_chapters   = ! empty( $chapters );
	$has_transcript = ! empty( $cues ) || $transcript !== '';
	if ( ! $has_chapters && ! $has_transcript ) {
		return;
	}
	?>
	<div class="drtalks-media-panel">
		<div class="drtalks-panel-header">
			<?php if ( $has_chapters && $has_transcript ) : ?>
				<button type="button" class="drtalks-panel-tab is-active" data-drtalks-tab="transcript"><?php esc_html_e( 'Transcript', 'drtalks-videos' ); ?></button>
				<button type="button" class="drtalks-panel-tab" data-drtalks-tab="chapters"><?php esc_html_e( 'Chapters', 'drtalks-videos' ); ?></button>
			<?php else : ?>
				<h2 class="drtalks-panel-heading"><?php $has_chapters ? esc_html_e( 'Chapters', 'drtalks-videos' ) : esc_html_e( 'Transcript', 'drtalks-videos' ); ?></h2>
			<?php endif; ?>
		</div>

		<?php if ( $has_chapters ) : ?>
		<div class="drtalks-panel-section" data-drtalks-section="chapters" <?php echo $has_transcript ? 'hidden' : ''; ?>>
			<div class="drtalks-chapters-list">
				<?php drtalks_render_seek_rows( $chapters, 'title', 'drtalks-chapter-row', 'drtalks-chapter-time', 'drtalks-chapter-title' ); ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $has_transcript ) : ?>
		<div class="drtalks-panel-section" data-drtalks-section="transcript">
			<?php if ( ! empty( $cues ) ) : ?>
				<p class="drtalks-transcript-note"><?php esc_html_e( 'Transcripts are generated automatically and may contain errors.', 'drtalks-videos' ); ?></p>
				<div class="drtalks-transcript-search">
					<input type="search" class="drtalks-search-input" placeholder="<?php esc_attr_e( 'Search transcript', 'drtalks-videos' ); ?>" aria-label="<?php esc_attr_e( 'Search transcript', 'drtalks-videos' ); ?>">
					<span class="drtalks-search-count" hidden></span>
					<button type="button" class="drtalks-search-prev" aria-label="<?php esc_attr_e( 'Previous match', 'drtalks-videos' ); ?>" hidden>&#9650;</button>
					<button type="button" class="drtalks-search-next" aria-label="<?php esc_attr_e( 'Next match', 'drtalks-videos' ); ?>" hidden>&#9660;</button>
				</div>
				<div class="drtalks-transcript-content drtalks-transcript-synced">
					<?php drtalks_render_transcript_cues( $cues, $chapters ); ?>
				</div>
			<?php else : ?>
				<div class="drtalks-transcript-content">
					<?php echo wp_kses( $transcript, $allowed_html ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php
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
	$chapters = [];
	$cues     = [];
	if ( $cpt_posts ) {
		$embed_url = get_post_meta( $cpt_posts[0]->ID, '_drtalks_embed_url', true );
		$m         = drtalks_get_video_meta( $cpt_posts[0]->ID );
		$chapters  = $m['chapters'];
		$cues      = $m['cues'];
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
				src="<?php echo esc_url( drtalks_embed_url_with_time( $embed_url ) ); ?>"
				style="position:absolute;top:0;left:0;width:100%;height:100%;"
				frameborder="0"
				allow="autoplay; fullscreen; picture-in-picture"
				allowfullscreen
			></iframe>
		</div>
		<?php
		drtalks_render_media_panel( $chapters, $cues, $transcript, [
			'p'      => [],
			'strong' => [],
			'em'     => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'br'     => [],
			'a'      => [ 'href' => [], 'title' => [] ],
			'h3'     => [ 'class' => [] ],
		] );
		?>
	</div>
	<?php
	wp_enqueue_style(
		'drtalks-frontend',
		DRTALKS_VIDEOS_URL . 'assets/frontend.css',
		[],
		DRTALKS_VIDEOS_VERSION
	);
	drtalks_enqueue_player_bridge();

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
	// Background fetch via Action Scheduler.
	if ( class_exists( 'DrTalks_Scheduler' ) ) {
		DrTalks_Scheduler::queue_fetch_transcript( $slug );
	}

	return '';
}

// Transcript fetch is now handled by DrTalks_Scheduler::run_fetch_transcript()
// via Action Scheduler (hook drtalks/fetch_transcript). See includes/class-scheduler.php.

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
	drtalks_enqueue_player_bridge();
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

	// Self-heal: queue the captions → cues parse for videos synced before cues
	// existed (or whose captions file changed). One-shot per captions URL.
	$captions_url = (string) get_post_meta( $post_id, '_drtalks_captions_url', true );
	if ( $captions_url && get_post_meta( $post_id, '_drtalks_cues_source', true ) !== $captions_url
		&& class_exists( 'DrTalks_Scheduler' ) ) {
		DrTalks_Scheduler::queue_fetch_cues( $post_id );
	}

	$chapters = get_post_meta( $post_id, '_drtalks_chapters', true );
	$cues     = get_post_meta( $post_id, '_drtalks_cues', true );

	return [
		'video_slug'   => $video_slug,
		'embed_url'    => get_post_meta( $post_id, '_drtalks_embed_url', true ),
		'thumbnail'    => get_post_meta( $post_id, '_drtalks_thumbnail', true ),
		'description'  => get_post_meta( $post_id, '_drtalks_description', true ),
		'transcript'   => get_post_meta( $post_id, '_drtalks_transcript', true ),
		'chapters'     => is_array( $chapters ) ? $chapters : [],
		'cues'         => is_array( $cues ) ? $cues : [],
		'published_at' => get_post_meta( $post_id, '_drtalks_published_at', true ),
		'expert_name'  => get_post_meta( $post_id, '_drtalks_expert_name', true ),
		'expert_slug'  => get_post_meta( $post_id, '_drtalks_expert_slug', true ),
		'expert_title' => get_post_meta( $post_id, '_drtalks_expert_title', true ),
		'expert_creds' => get_post_meta( $post_id, '_drtalks_expert_credentials', true ),
		'expert_photo' => get_post_meta( $post_id, '_drtalks_expert_photo', true ),
		'expert_bio'   => get_post_meta( $post_id, '_drtalks_expert_bio', true ),
		'guests'       => ( function () use ( $post_id ) {
			$g = get_post_meta( $post_id, '_drtalks_guests', true );
			return is_array( $g ) ? $g : [];
		} )(),
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
			'h3'     => [ 'class' => [] ],
		],
	];
}

/**
 * "Theme" layout — standard content flow with max-width body.
 */
function drtalks_render_single_video_content_theme( int $post_id ): string {
	return drtalks_get_template_html( 'content-single-theme.php', [
		'post_id'    => $post_id,
		'm'          => drtalks_get_video_meta( $post_id ),
		'show_watch' => (bool) get_option( 'drtalks_show_watch_button', true ),
	] );
}

/**
 * "Video Page" layout — YouTube-style two-column:
 *   Left:  player, title, Watch CTA, description, expert bio
 *   Right: transcript sidebar
 */
function drtalks_render_single_video_content_video( int $post_id ): string {
	return drtalks_get_template_html( 'content-single-video.php', [
		'post_id'    => $post_id,
		'm'          => drtalks_get_video_meta( $post_id ),
		'show_watch' => (bool) get_option( 'drtalks_show_watch_button', true ),
	] );
}

/**
 * Shared expert bio partial — used by both layout functions.
 */
function drtalks_render_expert_bio( array $m ): void {
	drtalks_get_template( 'partials/expert-bio.php', [ 'm' => $m ] );
}

/**
 * Guest bios partial — renders one card per guest. Used by both layout functions
 * and the block. Renders nothing when the video has no guests.
 */
function drtalks_render_guests( array $m ): void {
	if ( empty( $m['guests'] ) || ! is_array( $m['guests'] ) ) {
		return;
	}
	drtalks_get_template( 'partials/guests.php', [ 'm' => $m ] );
}

/**
 * Render a video embed for use inside a Gutenberg block, with per-section toggles.
 *
 * Options (all bool, default true):
 *   show_title, show_description, show_transcript, show_author, show_guests
 */
function drtalks_render_block_video( string $slug, array $options = [] ): string {
	$show_video       = $options['show_video']       ?? true;
	$show_title       = $options['show_title']       ?? true;
	$show_description = $options['show_description'] ?? true;
	$show_transcript  = $options['show_transcript']  ?? true;
	$show_author      = $options['show_author']      ?? true;
	$show_guests      = $options['show_guests']      ?? true;

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
	} else {
		// Sync failed — render player-only fallback.
		DrTalks_Debug::error( 'render_block_video: sync failed, using embed fallback', [ 'slug' => $slug ] );
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
	drtalks_enqueue_player_bridge();

	ob_start();
	?>
	<div class="drtalks-single-video drtalks-layout-theme drtalks-block-embed">

		<div class="drtalks-video-content">

			<?php if ( $show_video && ! empty( $m['embed_url'] ) ) : ?>
			<div class="drtalks-video-player">
				<iframe
					src="<?php echo esc_url( drtalks_embed_url_with_time( $m['embed_url'] ) ); ?>"
					frameborder="0"
					allow="autoplay; fullscreen; picture-in-picture"
					allowfullscreen
				></iframe>
			</div>
			<?php endif; ?>

		<?php if ( $show_video && ! empty( $m['drtalks_url'] ) && get_option( 'drtalks_show_watch_button', true ) ) : ?>
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

			<?php
			// Chapters render whenever present; the transcript tab respects the
			// block's show_transcript toggle.
			drtalks_render_media_panel(
				(array) ( $m['chapters'] ?? [] ),
				$show_transcript ? (array) ( $m['cues'] ?? [] ) : [],
				$show_transcript ? (string) ( $m['transcript'] ?? '' ) : '',
				(array) ( $m['allowed_html'] ?? [] )
			);
			?>

			<?php if ( $show_author && ! empty( $m['expert_name'] ) ) : ?>
				<?php drtalks_render_expert_bio( $m ); ?>
			<?php endif; ?>

			<?php if ( $show_guests && ! empty( $m['guests'] ) ) : ?>
				<?php drtalks_render_guests( $m ); ?>
			<?php endif; ?>

		</div>

	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render a single archive video card as HTML.
 * Used by both the PHP loop and the AJAX search handler (server-side rendering
 * keeps the markup consistent and avoids duplicating the template in JS).
 *
 * @param int $post_id
 * @return string
 */
function drtalks_render_archive_card( int $post_id ): string {
	$duration_secs = (int) get_post_meta( $post_id, '_drtalks_duration', true );
	$guests_raw    = get_post_meta( $post_id, '_drtalks_guests', true );

	return drtalks_get_template_html( 'content-archive-card.php', [
		'post_id'      => $post_id,
		'title'        => get_the_title( $post_id ),
		'permalink'    => (string) get_permalink( $post_id ),
		'thumbnail'    => (string) get_post_meta( $post_id, '_drtalks_thumbnail', true ),
		'duration'     => drtalks_format_duration( $duration_secs ),
		'expert_name'  => (string) get_post_meta( $post_id, '_drtalks_expert_name', true ),
		'expert_photo' => (string) get_post_meta( $post_id, '_drtalks_expert_photo', true ),
		'guests'       => is_array( $guests_raw ) ? $guests_raw : [],
	] );
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
