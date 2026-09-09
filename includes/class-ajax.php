<?php
/**
 * All wp_ajax_* handlers — CSRF + capability gate enforced on every handler.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Ajax {

	public static function init(): void {
		// Log all DrTalks AJAX calls when debug is enabled.
		add_action( 'wp_ajax_drtalks_search_videos',      static function() { DrTalks_Debug::log_ajax( 'drtalks_search_videos' ); } );
		add_action( 'wp_ajax_drtalks_search_experts',     static function() { DrTalks_Debug::log_ajax( 'drtalks_search_experts' ); } );
		add_action( 'wp_ajax_drtalks_load_expert_videos', static function() { DrTalks_Debug::log_ajax( 'drtalks_load_expert_videos' ); } );
		add_action( 'wp_ajax_drtalks_import_video',       static function() { DrTalks_Debug::log_ajax( 'drtalks_import_video' ); } );
		add_action( 'wp_ajax_drtalks_save_settings',      static function() { DrTalks_Debug::log_ajax( 'drtalks_save_settings' ); } );
		add_action( 'wp_ajax_drtalks_add_video',          static function() { DrTalks_Debug::log_ajax( 'drtalks_add_video' ); } );
		add_action( 'wp_ajax_drtalks_remove_video',       static function() { DrTalks_Debug::log_ajax( 'drtalks_remove_video' ); } );
		add_action( 'wp_ajax_drtalks_add_expert',         static function() { DrTalks_Debug::log_ajax( 'drtalks_add_expert' ); } );
		add_action( 'wp_ajax_drtalks_remove_expert',      static function() { DrTalks_Debug::log_ajax( 'drtalks_remove_expert' ); } );
		add_action( 'wp_ajax_drtalks_sync_expert_now',    static function() { DrTalks_Debug::log_ajax( 'drtalks_sync_expert_now' ); } );
		add_action( 'wp_ajax_drtalks_fetch_missing_videos', static function() { DrTalks_Debug::log_ajax( 'drtalks_fetch_missing_videos' ); } );
		add_action( 'wp_ajax_drtalks_get_sync_status',        static function() { DrTalks_Debug::log_ajax( 'drtalks_get_sync_status' ); } );
		add_action( 'wp_ajax_drtalks_global_sync_status',     static function() { DrTalks_Debug::log_ajax( 'drtalks_global_sync_status' ); } );
		add_action( 'wp_ajax_drtalks_hide_video',         static function() { DrTalks_Debug::log_ajax( 'drtalks_hide_video' ); } );
		add_action( 'wp_ajax_drtalks_trash_video',        static function() { DrTalks_Debug::log_ajax( 'drtalks_trash_video' ); } );
		add_action( 'wp_ajax_drtalks_unhide_video',       static function() { DrTalks_Debug::log_ajax( 'drtalks_unhide_video' ); } );
		add_action( 'wp_ajax_drtalks_get_orphans',        static function() { DrTalks_Debug::log_ajax( 'drtalks_get_orphans' ); } );
		add_action( 'wp_ajax_drtalks_delete_orphans',     static function() { DrTalks_Debug::log_ajax( 'drtalks_delete_orphans' ); } );

		// Existing handlers.
		add_action( 'wp_ajax_drtalks_search_videos',      [ __CLASS__, 'search_videos' ] );
		add_action( 'wp_ajax_drtalks_search_experts',     [ __CLASS__, 'search_experts' ] );
		add_action( 'wp_ajax_drtalks_load_expert_videos', [ __CLASS__, 'load_expert_videos' ] );
		add_action( 'wp_ajax_drtalks_import_video',       [ __CLASS__, 'import_video' ] );

		// New admin-page handlers.
		add_action( 'wp_ajax_drtalks_save_settings',    [ __CLASS__, 'save_settings' ] );
		add_action( 'wp_ajax_drtalks_add_video',        [ __CLASS__, 'add_video' ] );
		add_action( 'wp_ajax_drtalks_remove_video',     [ __CLASS__, 'remove_video' ] );
		add_action( 'wp_ajax_drtalks_add_expert',       [ __CLASS__, 'add_expert' ] );
		add_action( 'wp_ajax_drtalks_remove_expert',    [ __CLASS__, 'remove_expert' ] );
		add_action( 'wp_ajax_drtalks_sync_expert_now',  [ __CLASS__, 'sync_expert_now' ] );
		add_action( 'wp_ajax_drtalks_fetch_missing_videos', [ __CLASS__, 'fetch_missing_videos' ] );
		add_action( 'wp_ajax_drtalks_get_sync_status',        [ __CLASS__, 'get_sync_status' ] );
		add_action( 'wp_ajax_drtalks_global_sync_status',     [ __CLASS__, 'global_sync_status' ] );
		add_action( 'wp_ajax_drtalks_hide_video',       [ __CLASS__, 'hide_video' ] );
		add_action( 'wp_ajax_drtalks_trash_video',      [ __CLASS__, 'trash_video' ] );
		add_action( 'wp_ajax_drtalks_unhide_video',     [ __CLASS__, 'unhide_video' ] );
		add_action( 'wp_ajax_drtalks_get_orphans',      [ __CLASS__, 'get_orphans' ] );
		add_action( 'wp_ajax_drtalks_delete_orphans',   [ __CLASS__, 'delete_orphans' ] );

		// Frontend (public) search for the archive page.
		add_action( 'wp_ajax_drtalks_archive_search',        [ __CLASS__, 'archive_search' ] );
		add_action( 'wp_ajax_nopriv_drtalks_archive_search', [ __CLASS__, 'archive_search' ] );
	}

	// --- Security gate -------------------------------------------------------

	private static function verify(): void {
		check_ajax_referer( 'drtalks_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
	}

	// --- Existing handlers ---------------------------------------------------

	public static function search_videos(): void {
		self::verify();

		$q        = sanitize_text_field( $_POST['q'] ?? '' );
		$per_page = absint( $_POST['per_page'] ?? 20 );
		$page     = absint( $_POST['page'] ?? 1 );

		$api    = new DrTalks_API_Client();
		$result = $api->search_videos( $q, $per_page ?: 20, $page ?: 1 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public static function search_experts(): void {
		self::verify();

		$q        = sanitize_text_field( $_POST['q'] ?? '' );
		$per_page = absint( $_POST['per_page'] ?? 20 );

		$api    = new DrTalks_API_Client();
		$result = $api->search_experts( $q, $per_page ?: 20 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Enrich first 5 results with a video count (one extra API call each).
		// Skipped if the search endpoint already provides video_count.
		if ( isset( $result['experts'] ) && is_array( $result['experts'] ) ) {
			$enriched = 0;
			foreach ( $result['experts'] as &$expert ) {
				if ( isset( $expert['video_count'] ) ) {
					continue;
				}
				if ( $enriched >= 5 ) {
					$expert['video_count'] = null;
					continue;
				}
				$slug   = sanitize_title( $expert['slug'] ?? '' );
				$videos = $slug ? $api->get_expert_videos( $slug, 1, 1 ) : null;
				$expert['video_count'] = ( is_array( $videos ) && isset( $videos['total'] ) ) ? (int) $videos['total'] : null;
				$enriched++;
			}
			unset( $expert );
		}

		wp_send_json_success( $result );
	}

	public static function load_expert_videos(): void {
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		$per_page    = absint( $_POST['per_page'] ?? 100 ) ?: 100;

		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required' );
		}

		// 1. Get all synced WP posts for this expert.
		$posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				[ 'key' => '_drtalks_expert_slug', 'value' => $expert_slug ],
			],
		] );

		$synced_by_slug = [];
		foreach ( $posts as $post ) {
			$slug = get_post_meta( $post->ID, '_drtalks_video_slug', true );
			if ( $slug ) {
				$card           = self::format_video_card( $post );
				$card['synced'] = true;
				$synced_by_slug[ $slug ] = $card;
			}
		}

		// 2. Fetch full video list from the API to catch unsynced entries.
		$api        = new DrTalks_API_Client();
		$api_result = $api->get_expert_videos( $expert_slug, 1, $per_page );
		$api_videos = ( ! is_wp_error( $api_result ) ) ? ( $api_result['videos'] ?? [] ) : [];

		// 3. Merge: synced WP posts first, then API-only (pending) entries.
		$merged = array_values( $synced_by_slug );

		foreach ( $api_videos as $v ) {
			$slug = sanitize_title( $v['slug'] ?? '' );
			if ( $slug && ! isset( $synced_by_slug[ $slug ] ) ) {
				$merged[] = [
					'slug'          => $slug,
					'title'         => sanitize_text_field( $v['title'] ?? $slug ),
					'thumbnail_url' => esc_url_raw( $v['thumbnail_url'] ?? '' ),
					'expert_slug'   => $expert_slug,
					'expert_name'   => sanitize_text_field( $v['expert_name'] ?? '' ),
					'wp_post_url'   => '',
					'drtalks_url'   => 'https://drtalks.com/videos/' . rawurlencode( $slug ),
					'synced'        => false,
				];
			}
		}

		wp_send_json_success( [
			'videos'   => $merged,
			'total'    => count( $merged ),
			'has_more' => false,
		] );
	}

	public static function import_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( 'slug is required', 400 );
		}

		$sync   = new DrTalks_Sync();
		$result = $sync->sync_video( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'error' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'post_id'  => $result,
			'edit_url' => get_edit_post_link( $result, 'raw' ),
		] );
	}

	// --- New admin-page handlers ---------------------------------------------

	public static function save_settings(): void {
		self::verify();

		$show_watch_button = ! empty( $_POST['show_watch_button'] );
		update_option( 'drtalks_show_watch_button', $show_watch_button ? 1 : 0 );

		$archive_enabled = ! empty( $_POST['archive_enabled'] );
		$archive_slug    = sanitize_title( $_POST['archive_slug'] ?? 'videos' ) ?: 'videos';

		update_option( 'drtalks_archive_enabled', $archive_enabled ? 1 : 0 );
		update_option( 'drtalks_archive_slug', $archive_slug );

		$archive_heading = sanitize_text_field( wp_unslash( $_POST['archive_heading'] ?? '' ) );
		if ( '' === $archive_heading ) {
			$archive_heading = 'Videos Archive';
		}
		update_option( 'drtalks_archive_heading', $archive_heading );

		// Re-register CPT with updated settings, then flush so rules are correct immediately.
		DrTalks_Post_Type::register();
		flush_rewrite_rules( false );

		$sync_schedule = sanitize_key( $_POST['sync_schedule'] ?? '' );
		if ( $sync_schedule ) {
			$old = get_option( 'drtalks_sync_schedule', 'daily' );
			if ( $old !== $sync_schedule ) {
				update_option( 'drtalks_sync_schedule', $sync_schedule );
				DrTalks_Scheduler::reschedule_recurring();
			}
		}

		$template_style = sanitize_key( $_POST['video_template_style'] ?? '' );
		if ( in_array( $template_style, [ 'theme', 'video' ], true ) ) {
			update_option( 'drtalks_video_template_style', $template_style );
		}

		$videos_per_page = (int) ( $_POST['videos_per_page'] ?? 12 );
		if ( $videos_per_page >= 1 && $videos_per_page <= 200 ) {
			update_option( 'drtalks_videos_per_page', $videos_per_page );
		}

		$videos_per_row = (int) ( $_POST['videos_per_row'] ?? 4 );
		if ( $videos_per_row >= 1 && $videos_per_row <= 6 ) {
			update_option( 'drtalks_videos_per_row', $videos_per_row );
		}

		wp_send_json_success( [
			'archive_url'          => home_url( '/' . $archive_slug . '/' ),
			'pretty_permalinks'    => ! empty( get_option( 'permalink_structure' ) ),
		] );
	}

	public static function add_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( 'slug is required', 400 );
		}

		// Reject if this slug belongs to an active expert's library (D3).
		$expert_slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
		if ( is_array( $expert_slugs ) && ! empty( $expert_slugs ) ) {
			$posts = get_posts( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => '_drtalks_video_slug',
						'value' => $slug,
					],
					[
						'key'     => '_drtalks_expert_slug',
						'value'   => $expert_slugs,
						'compare' => 'IN',
					],
				],
			] );
			if ( ! empty( $posts ) ) {
				wp_send_json_error( 'This video is managed by expert sync. Remove the expert first to add it manually.' );
			}
		}

		// Idempotent: if already selected, return existing data.
		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		if ( ! is_array( $selected ) ) {
			$selected = [];
		}

		if ( in_array( $slug, $selected, true ) ) {
			$post = self::get_cpt_post_by_slug( $slug );
			if ( $post ) {
				wp_send_json_success( self::format_video_card( $post ) );
			}
			// Slug was in list but post is missing — fall through to re-create.
		}

		// Create or update CPT post.
		$sync   = new DrTalks_Sync();
		$result = $sync->sync_video( $slug );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$post = get_post( $result );
		if ( ! $post ) {
			wp_send_json_error( 'Video post could not be created for slug: ' . $slug );
		}

		// Add to selected list AFTER confirming post exists.
		if ( ! in_array( $slug, $selected, true ) ) {
			$selected[] = $slug;
			update_option( 'drtalks_selected_videos', wp_json_encode( $selected ), false );
		}

		wp_send_json_success( self::format_video_card( $post ) );
	}

	public static function remove_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( 'slug is required', 400 );
		}

		// Remove from selected list.
		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		if ( ! is_array( $selected ) ) {
			$selected = [];
		}
		$selected = array_values( array_filter( $selected, fn( $s ) => $s !== $slug ) );
		update_option( 'drtalks_selected_videos', wp_json_encode( $selected ), false );

		// Delete CPT post if it exists (safe: if also an expert video it will be re-synced by cron).
		$post = self::get_cpt_post_by_slug( $slug );
		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}

		wp_send_json_success( [ 'slug' => $slug ] );
	}

	public static function add_expert(): void {
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		$slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
		if ( ! is_array( $slugs ) ) {
			$slugs = [];
		}

		// Idempotent.
		if ( in_array( $expert_slug, $slugs, true ) ) {
			wp_send_json_error( 'Expert already added.' );
		}

		if ( count( $slugs ) >= 5 ) {
			wp_send_json_error( 'Maximum 5 experts allowed.' );
		}

		// Name and photo come from the search result already shown to the user.
		$expert_name  = sanitize_text_field( $_POST['expert_name']  ?? '' );
		$expert_photo = esc_url_raw( $_POST['expert_photo'] ?? '' );

		// Validate the expert exists and get total video count via the videos endpoint.
		$api         = new DrTalks_API_Client();
		$videos_page = $api->get_expert_videos( $expert_slug, 1, 1 );
		if ( is_wp_error( $videos_page ) ) {
			wp_send_json_error( 'Expert not found: ' . $videos_page->get_error_message() );
		}
		$video_count = (int) ( $videos_page['total'] ?? 0 );

		// Persist slug + full metadata BEFORE sync — so name/photo survive
		// regardless of whether sync completes, errors, or times out.
		$slugs[] = $expert_slug;
		update_option( 'drtalks_expert_slugs', wp_json_encode( $slugs ), false );

		self::set_expert_meta( $expert_slug, [
			'name'        => $expert_name ?: $expert_slug,
			'photo_url'   => $expert_photo,
			'video_count' => $video_count,
		] );

		// Auto-migrate: remove any slugs in selected_videos that belong to this expert.
		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		if ( is_array( $selected ) && ! empty( $selected ) ) {
			$expert_posts = get_posts( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => '_drtalks_expert_slug',
						'value' => $expert_slug,
					],
				],
			] );
			$expert_video_slugs = [];
			foreach ( $expert_posts as $pid ) {
				$vs = get_post_meta( $pid, '_drtalks_video_slug', true );
				if ( $vs ) {
					$expert_video_slugs[] = $vs;
				}
			}
			if ( ! empty( $expert_video_slugs ) ) {
				$selected = array_values( array_filter( $selected, fn( $s ) => ! in_array( $s, $expert_video_slugs, true ) ) );
				update_option( 'drtalks_selected_videos', wp_json_encode( $selected ), false );
			}
		}

		// Sync just 1 video synchronously — enough to confirm the integration works
		// and give the user immediate feedback. The rest is handled by cron.
		$sync        = new DrTalks_Sync();
		$sync_result = $sync->sync_expert( $expert_slug, 1, false );

		$processed = ( $sync_result['created'] ?? 0 ) + ( $sync_result['skipped'] ?? 0 );
		if ( $sync_result['error'] ) {
			$sync_status = 'error';
			DrTalks_Debug::error( "add_expert sync error for $expert_slug", $sync_result );
		} elseif ( $video_count > $processed ) {
			// More videos remain — Action Scheduler picks up where we left off in 10-video chunks.
			$sync_status = 'partial';
			DrTalks_Scheduler::queue_sync_expert( $expert_slug, $processed );
		} else {
			$sync_status = 'done';
		}

		set_transient( 'drtalks_sync_status_' . $expert_slug, [
			'status'  => $sync_status,
			'created' => $sync_result['created'] ?? 0,
			'error'   => $sync_result['error'] ?? null,
		], HOUR_IN_SECONDS );

		wp_send_json_success( [
			'slug'         => $expert_slug,
			'name'         => $expert_name ?: $expert_slug,
			'photo_url'    => $expert_photo,
			'video_count'  => $video_count,
			'synced_count' => $sync_result['created'] ?? 0,
			'sync_status'  => $sync_status,
		] );
	}

	// --- Expert metadata helpers --------------------------------------------

	private static function set_expert_meta( string $slug, array $patch ): void {
		$all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		if ( ! is_array( $all ) ) {
			$all = [];
		}
		$existing       = $all[ $slug ] ?? [];
		$all[ $slug ]   = array_merge( $existing, $patch );
		update_option( 'drtalks_experts_meta', wp_json_encode( $all ), false );
	}

	private static function delete_expert_meta( string $slug ): void {
		$all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		if ( ! is_array( $all ) || ! isset( $all[ $slug ] ) ) {
			return;
		}
		unset( $all[ $slug ] );
		update_option( 'drtalks_experts_meta', wp_json_encode( $all ), false );
	}

	public static function remove_expert(): void {
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		// Cancel all pending Action Scheduler batches for this expert.
		// We iterate pending actions so we only cancel this expert's chunks,
		// not other experts that may be queued at the same time.
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$pending = as_get_scheduled_actions( [
				'hook'     => DrTalks_Scheduler::HOOK_SYNC_EXPERT,
				'group'    => DrTalks_Scheduler::GROUP,
				'status'   => 'pending',
				'per_page' => -1,
			] );
			foreach ( $pending as $action ) {
				$args = $action->get_args();
				if ( ! empty( $args[0] ) && sanitize_title( $args[0] ) === $expert_slug ) {
					as_unschedule_action( DrTalks_Scheduler::HOOK_SYNC_EXPERT, $args, DrTalks_Scheduler::GROUP );
				}
			}
		}

		// Remove from expert slugs list.
		$slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
		if ( ! is_array( $slugs ) ) {
			$slugs = [];
		}
		$slugs = array_values( array_filter( $slugs, fn( $s ) => $s !== $expert_slug ) );
		update_option( 'drtalks_expert_slugs', wp_json_encode( $slugs ), false );

		// Build the list of video slugs for this expert from stored metadata (new model),
		// falling back to querying by _drtalks_expert_slug (legacy).
		$meta_all    = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		$video_slugs = is_array( $meta_all ) ? ( $meta_all[ $expert_slug ]['video_slugs'] ?? [] ) : [];

		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		if ( ! is_array( $selected ) ) {
			$selected = [];
		}

		$deleted_count = 0;

		if ( ! empty( $video_slugs ) ) {
			// New model: find posts by their video slug list.
			$posts = get_posts( [
				'post_type'      => 'drtalks_video',
				'post_status'    => [ 'publish', 'trash' ],
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'     => '_drtalks_video_slug',
						'value'   => $video_slugs,
						'compare' => 'IN',
					],
				],
			] );
		} else {
			// Legacy fallback: find posts by _drtalks_expert_slug.
			$posts = get_posts( [
				'post_type'      => 'drtalks_video',
				'post_status'    => [ 'publish', 'trash' ],
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'   => '_drtalks_expert_slug',
						'value' => $expert_slug,
					],
				],
			] );
		}

		foreach ( $posts as $post ) {
			$video_slug = get_post_meta( $post->ID, '_drtalks_video_slug', true );
			if ( in_array( $video_slug, $selected, true ) ) {
				continue; // Manually selected — preserve.
			}
			wp_delete_post( $post->ID, true );
			$deleted_count++;
		}

		// Drop transient cache and metadata for this expert.
		delete_transient( 'drtalks_expert_meta_' . $expert_slug );
		delete_transient( 'drtalks_sync_status_' . $expert_slug );
		self::delete_expert_meta( $expert_slug );

		wp_send_json_success( [
			'slug'          => $expert_slug,
			'deleted_count' => $deleted_count,
		] );
	}

	// -----------------------------------------------------------------------
	// Orphan helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns orphan posts with id/slug/title for display in the debug page.
	 * Detection logic lives in DrTalks_Scheduler::get_orphan_post_ids().
	 */
	private static function get_orphan_posts(): array {
		$orphans = [];
		foreach ( DrTalks_Scheduler::get_orphan_post_ids() as $post_id ) {
			$orphans[] = [
				'id'    => $post_id,
				'slug'  => get_post_meta( $post_id, '_drtalks_video_slug', true ) ?: '(no slug)',
				'title' => get_the_title( $post_id ),
			];
		}
		return $orphans;
	}

	public static function get_orphans(): void {
		self::verify();
		$orphans = self::get_orphan_posts();
		wp_send_json_success( [ 'orphans' => $orphans, 'count' => count( $orphans ) ] );
	}

	/**
	 * Schedules an immediate Action Scheduler job to delete orphans.
	 * Does NOT delete synchronously.
	 */
	public static function delete_orphans(): void {
		self::verify();
		DrTalks_Scheduler::queue_cleanup_orphans();
		wp_send_json_success( [ 'scheduled' => true, 'message' => 'Orphan cleanup has been queued and will run shortly.' ] );
	}

	public static function sync_expert_now(): void {
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		// Run as a background batch (Action Scheduler) so large experts don't time out.
		// update_existing = true → re-fetch + overwrite existing posts AND create any
		// missing ones, in 10-video chunks. The UI polls drtalks_get_sync_status.
		set_transient( 'drtalks_sync_status_' . $expert_slug, [ 'status' => 'running' ], HOUR_IN_SECONDS );
		DrTalks_Scheduler::queue_sync_expert( $expert_slug, 0, true );

		wp_send_json_success( [
			'slug'        => $expert_slug,
			'sync_status' => 'running',
		] );
	}

	/**
	 * "Fetch Missing Videos" — import any of the expert's DrTalks videos that
	 * aren't on the site yet. Runs as a background batch (Action Scheduler),
	 * insert-only: existing posts are left untouched, so it's the cheap fix for
	 * an "X of Y" gap. The UI polls drtalks_get_sync_status for progress.
	 */
	public static function fetch_missing_videos(): void {
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		set_transient( 'drtalks_sync_status_' . $expert_slug, [ 'status' => 'running' ], HOUR_IN_SECONDS );
		DrTalks_Scheduler::queue_sync_expert( $expert_slug, 0, false ); // update_existing = false → create missing only.

		wp_send_json_success( [
			'slug'        => $expert_slug,
			'sync_status' => 'running',
		] );
	}

	public static function get_sync_status(): void {
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		$status = get_transient( 'drtalks_sync_status_' . $expert_slug );
		if ( ! $status ) {
			$status = [ 'status' => 'pending' ];
		}

		wp_send_json_success( array_merge( [ 'slug' => $expert_slug ], $status ) );
	}

	public static function global_sync_status(): void {
		self::verify();
		wp_send_json_success( DrTalks_Scheduler::get_global_sync_status() );
	}

	public static function hide_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( 'slug is required', 400 );
		}

		// Read metadata from CPT post before deletion.
		$post  = self::get_cpt_post_by_slug( $slug );
		$title         = $post ? get_the_title( $post->ID ) : $slug;
		$thumbnail_url = $post ? get_post_meta( $post->ID, '_drtalks_thumbnail', true ) : '';
		$expert_slug   = $post ? get_post_meta( $post->ID, '_drtalks_expert_slug', true ) : '';

		// Add to hidden list.
		$hidden = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
		if ( ! is_array( $hidden ) ) {
			$hidden = [];
		}
		$already_hidden = false;
		foreach ( $hidden as $item ) {
			if ( isset( $item['slug'] ) && $item['slug'] === $slug ) {
				$already_hidden = true;
				break;
			}
		}
		if ( ! $already_hidden ) {
			$hidden[] = [
				'slug'          => $slug,
				'title'         => $title,
				'thumbnail_url' => $thumbnail_url,
				'expert_slug'   => $expert_slug,
			];
			update_option( 'drtalks_hidden_videos', wp_json_encode( $hidden ), false );
		}

		// Delete CPT post.
		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}

		wp_send_json_success( [
			'slug'          => $slug,
			'title'         => $title,
			'thumbnail_url' => $thumbnail_url,
			'expert_slug'   => $expert_slug,
		] );
	}

	/**
	 * Permanently delete a synced video's CPT post. Unlike hide_video this adds
	 * no hidden-list entry, so the video is fully re-fetched from the API by
	 * "Fetch missing videos" or any other sync. "Hide" is the durable removal.
	 */
	public static function trash_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( 'slug is required', 400 );
		}

		// Already gone (e.g. clicked again after a reload) counts as done.
		$post = self::get_cpt_post_by_slug( $slug );
		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}

		wp_send_json_success( [ 'slug' => $slug ] );
	}

	public static function unhide_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( 'slug is required', 400 );
		}

		// Read current hidden list and find the entry.
		$hidden = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
		if ( ! is_array( $hidden ) ) {
			$hidden = [];
		}

		// Recreate the CPT post via API before modifying state.
		$sync   = new DrTalks_Sync();
		$result = $sync->sync_video( $slug );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Only remove from hidden list after successful post creation.
		$hidden = array_values( array_filter( $hidden, fn( $item ) => ( $item['slug'] ?? '' ) !== $slug ) );
		update_option( 'drtalks_hidden_videos', wp_json_encode( $hidden ), false );

		$post = get_post( $result );
		wp_send_json_success( array_merge(
			self::format_video_card( $post ),
			[ 'wp_post_url' => get_permalink( $post->ID ) ?: '' ]
		) );
	}

	// --- Helpers -------------------------------------------------------------

	private static function get_cpt_post_by_slug( string $slug ): ?WP_Post {
		$posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => '_drtalks_video_slug',
					'value' => $slug,
				],
			],
		] );
		return $posts[0] ?? null;
	}

	private static function format_video_card( ?WP_Post $post ): array {
		if ( ! $post ) {
			DrTalks_Debug::error( 'format_video_card called with null post' );
			return [];
		}
		$slug = get_post_meta( $post->ID, '_drtalks_video_slug', true );

		return [
			'slug'          => $slug,
			'title'         => get_the_title( $post->ID ),
			'thumbnail_url' => get_post_meta( $post->ID, '_drtalks_thumbnail', true ),
			'expert_slug'   => get_post_meta( $post->ID, '_drtalks_expert_slug', true ),
			'expert_name'   => get_post_meta( $post->ID, '_drtalks_expert_name', true ),
			'wp_post_url'   => get_permalink( $post->ID ) ?: '',
			'drtalks_url'   => 'https://drtalks.com/videos/' . rawurlencode( $slug ),
			'synced'        => true,
		];
	}

	// --- Frontend archive search (public, no login required) -----------------

	/**
	 * Live search handler for the public archive page.
	 * Returns rendered HTML card fragments so the client-side template stays
	 * consistent with the server-side loop output.
	 */
	public static function archive_search(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'drtalks_archive_search' ) ) {
			wp_send_json_error( 'invalid_nonce', 403 );
		}

		$q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		if ( $q === '' ) {
			wp_send_json_success( [ 'cards' => [], 'total' => 0 ] );
		}

		$allowed_slugs = DrTalks_Post_Type::get_allowed_video_slugs();
		if ( empty( $allowed_slugs ) ) {
			wp_send_json_success( [ 'cards' => [], 'total' => 0 ] );
		}

		$query = new WP_Query( [
			'post_type'      => 'drtalks_video',
			'post_status'    => 'publish',
			'posts_per_page' => 48,
			's'              => $q,
			'meta_query'     => [ [
				'key'     => '_drtalks_video_slug',
				'value'   => $allowed_slugs,
				'compare' => 'IN',
			] ],
		] );

		$cards = [];
		foreach ( $query->posts as $post ) {
			$cards[] = drtalks_render_archive_card( (int) $post->ID );
		}

		wp_send_json_success( [ 'cards' => $cards, 'total' => $query->found_posts ] );
	}
}

// Background expert syncs run via Action Scheduler (DrTalks_Scheduler::run_sync_expert)
// in 10-video chunks. No WP-Cron is used by this plugin.
