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
		add_action( 'wp_ajax_drtalks_get_sync_status',    static function() { DrTalks_Debug::log_ajax( 'drtalks_get_sync_status' ); } );
		add_action( 'wp_ajax_drtalks_hide_video',         static function() { DrTalks_Debug::log_ajax( 'drtalks_hide_video' ); } );
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
		add_action( 'wp_ajax_drtalks_get_sync_status',  [ __CLASS__, 'get_sync_status' ] );
		add_action( 'wp_ajax_drtalks_hide_video',       [ __CLASS__, 'hide_video' ] );
		add_action( 'wp_ajax_drtalks_unhide_video',     [ __CLASS__, 'unhide_video' ] );
		add_action( 'wp_ajax_drtalks_get_orphans',      [ __CLASS__, 'get_orphans' ] );
		add_action( 'wp_ajax_drtalks_delete_orphans',   [ __CLASS__, 'delete_orphans' ] );
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

		$archive_enabled = ! empty( $_POST['archive_enabled'] );
		$archive_slug    = sanitize_title( $_POST['archive_slug'] ?? 'videos' ) ?: 'videos';

		update_option( 'drtalks_archive_enabled', $archive_enabled ? 1 : 0 );
		update_option( 'drtalks_archive_slug', $archive_slug );

		// Re-register CPT with updated settings, then flush so rules are correct immediately.
		DrTalks_Post_Type::register();
		flush_rewrite_rules( false );

		$sync_schedule = sanitize_key( $_POST['sync_schedule'] ?? '' );
		if ( $sync_schedule ) {
			$old = get_option( 'drtalks_sync_schedule', 'daily' );
			if ( $old !== $sync_schedule ) {
				update_option( 'drtalks_sync_schedule', $sync_schedule );
				wp_clear_scheduled_hook( 'drtalks_sync_cron' );
				if ( $sync_schedule !== 'manual' ) {
					wp_schedule_event( time(), $sync_schedule, 'drtalks_sync_cron' );
				}
			}
		}

		$template_style = sanitize_key( $_POST['video_template_style'] ?? '' );
		if ( in_array( $template_style, [ 'theme', 'video' ], true ) ) {
			update_option( 'drtalks_video_template_style', $template_style );
		}

		wp_send_json_success( [
			'archive_url'          => home_url( '/' . $archive_slug . '/' ),
			'pretty_permalinks'    => ! empty( get_option( 'permalink_structure' ) ),
		] );
	}

	public static function add_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		error_log( '[DrTalks add_video] raw slug from POST: ' . ( $_POST['slug'] ?? '(empty)' ) . ' → sanitized: ' . $slug );
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
		error_log( '[DrTalks add_video] sync_video("' . $slug . '") returned: ' . ( is_wp_error( $result ) ? 'WP_Error: ' . $result->get_error_message() : 'post_id=' . $result ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$post = get_post( $result );
		error_log( '[DrTalks add_video] get_post(' . $result . '): ' . ( $post ? 'found status=' . $post->post_status : 'NULL' ) );
		if ( ! $post ) {
			wp_send_json_error( 'Video post could not be created for slug: ' . $slug );
		}

		// Add to selected list AFTER confirming post exists.
		if ( ! in_array( $slug, $selected, true ) ) {
			$selected[] = $slug;
			$saved = update_option( 'drtalks_selected_videos', wp_json_encode( $selected ), false );
			error_log( '[DrTalks add_video] update_option drtalks_selected_videos result: ' . ( $saved ? 'true' : 'false (may mean value unchanged)' ) );
		} else {
			error_log( '[DrTalks add_video] slug already in selected list, skipping option update.' );
		}

		error_log( '[DrTalks add_video] selected list is now: ' . wp_json_encode( $selected ) );

		$card = self::format_video_card( $post );
		error_log( '[DrTalks add_video] format_video_card result: ' . wp_json_encode( $card ) );
		wp_send_json_success( $card );
	}

	public static function remove_video(): void {
		self::verify();

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		error_log( '[DrTalks remove_video] called for slug: ' . $slug );
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
		error_log( '[DrTalks remove_video] selected list after removal: ' . wp_json_encode( $selected ) );

		// Delete CPT post if it exists (safe: if also an expert video it will be re-synced by cron).
		$post = self::get_cpt_post_by_slug( $slug );
		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}

		wp_send_json_success( [ 'slug' => $slug ] );
	}

	public static function add_expert(): void {
		error_log( '[DrTalks add_expert] handler reached. POST=' . wp_json_encode( $_POST ) );
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		error_log( '[DrTalks add_expert] nonce passed. raw=' . ( $_POST['expert_slug'] ?? '(missing)' ) . ' sanitized=' . $expert_slug );
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
			// Always true for experts with >1 video — cron handles the remainder.
			$sync_status = 'partial';
			wp_schedule_single_event( time() + 5, 'drtalks_sync_single_expert', [ $expert_slug ] );
			spawn_cron();
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
		error_log( '[DrTalks remove_expert] called. raw=' . ( $_POST['expert_slug'] ?? '(missing)' ) . ' sanitized=' . $expert_slug );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		// Cancel any pending cron sync immediately — prevents new posts being created
		// after we've deleted them.
		wp_clear_scheduled_hook( 'drtalks_sync_single_expert', [ $expert_slug ] );

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

		error_log( '[DrTalks remove_expert] done. expert_slug=' . $expert_slug . ' deleted_count=' . $deleted_count . ' remaining_experts=' . wp_json_encode( $slugs ) );
		wp_send_json_success( [
			'slug'          => $expert_slug,
			'deleted_count' => $deleted_count,
		] );
	}

	// -----------------------------------------------------------------------
	// Orphan helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns all drtalks_video post IDs/slugs/titles that are not tracked by
	 * any active expert or in drtalks_selected_videos.
	 */
	private static function get_orphan_posts(): array {
		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		if ( ! is_array( $selected ) ) {
			$selected = [];
		}

		$meta_all      = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		$tracked_slugs = $selected;
		if ( is_array( $meta_all ) ) {
			foreach ( $meta_all as $expert_data ) {
				$slugs = $expert_data['video_slugs'] ?? [];
				if ( is_array( $slugs ) ) {
					$tracked_slugs = array_merge( $tracked_slugs, $slugs );
				}
			}
		}
		$tracked_slugs = array_values( array_unique( array_filter( $tracked_slugs ) ) );

		// Fetch ALL drtalks_video posts.
		$all_posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => [ 'publish', 'trash' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$orphans = [];
		foreach ( $all_posts as $post_id ) {
			$video_slug = get_post_meta( $post_id, '_drtalks_video_slug', true );
			if ( empty( $video_slug ) || ! in_array( $video_slug, $tracked_slugs, true ) ) {
				$orphans[] = [
					'id'    => $post_id,
					'slug'  => $video_slug ?: '(no slug)',
					'title' => get_the_title( $post_id ),
				];
			}
		}
		return $orphans;
	}

	public static function get_orphans(): void {
		self::verify();
		$orphans = self::get_orphan_posts();
		wp_send_json_success( [ 'orphans' => $orphans, 'count' => count( $orphans ) ] );
	}

	public static function delete_orphans(): void {
		self::verify();
		$orphans = self::get_orphan_posts();
		$deleted = 0;
		foreach ( $orphans as $o ) {
			wp_delete_post( (int) $o['id'], true );
			$deleted++;
		}
		error_log( '[DrTalks delete_orphans] permanently deleted ' . $deleted . ' orphaned posts.' );
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	public static function sync_expert_now(): void {
		error_log( '[DrTalks sync_expert_now] handler reached. POST=' . wp_json_encode( $_POST ) );
		self::verify();

		$expert_slug = sanitize_title( $_POST['expert_slug'] ?? '' );
		error_log( '[DrTalks sync_expert_now] nonce passed. expert_slug=' . $expert_slug );
		if ( ! $expert_slug ) {
			wp_send_json_error( 'expert_slug is required', 400 );
		}

		set_time_limit( 120 );
		$sync   = new DrTalks_Sync();
		$result = $sync->sync_expert( $expert_slug, 0, true ); // 0 = no limit
		error_log( '[DrTalks sync_expert_now] sync_expert result: ' . wp_json_encode( $result ) );

		if ( $result['error'] ) {
			set_transient( 'drtalks_sync_status_' . $expert_slug, [ 'status' => 'error', 'error' => $result['error'] ], HOUR_IN_SECONDS );
			wp_send_json_error( $result );
		}

		// Update synced count using the expert's video_slugs list (accurate for shared videos).
		$meta_all_refresh = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		$video_slugs_list = ( $meta_all_refresh[ $expert_slug ] ?? [] )['video_slugs'] ?? [];
		if ( ! empty( $video_slugs_list ) ) {
			$synced_count = (int) ( new WP_Query( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => '_drtalks_video_slug',
						'value'   => $video_slugs_list,
						'compare' => 'IN',
					],
				],
			] ) )->found_posts;
		} else {
			$synced_count = 0;
		}
		self::set_expert_meta( $expert_slug, [ 'synced_count' => $synced_count ] );

		set_transient( 'drtalks_sync_status_' . $expert_slug, [
			'status'  => 'done',
			'created' => $result['created'],
			'updated' => $result['updated'],
			'skipped' => $result['skipped'],
		], HOUR_IN_SECONDS );

		wp_send_json_success( array_merge(
			[ 'slug' => $expert_slug, 'synced_count' => $synced_count ],
			$result
		) );
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
			error_log( '[DrTalks format_video_card] called with null post!' );
			return [];
		}
		$slug        = get_post_meta( $post->ID, '_drtalks_video_slug', true );
		$permalink   = get_permalink( $post->ID );
		$post_status = get_post_status( $post->ID );
		$post_type   = get_post_type( $post->ID );

		error_log( '[DrTalks format_video_card] post_id=' . $post->ID
			. ' slug=' . $slug
			. ' status=' . $post_status
			. ' type=' . $post_type
			. ' permalink=' . ( $permalink ?: '(false/empty)' )
			. ' permalink_structure=' . ( get_option( 'permalink_structure' ) ?: '(plain/empty)' )
			. ' cpt_public=' . ( get_post_type_object( 'drtalks_video' ) ? var_export( get_post_type_object( 'drtalks_video' )->public, true ) : 'CPT not found' )
		);

		return [
			'slug'          => $slug,
			'title'         => get_the_title( $post->ID ),
			'thumbnail_url' => get_post_meta( $post->ID, '_drtalks_thumbnail', true ),
			'expert_slug'   => get_post_meta( $post->ID, '_drtalks_expert_slug', true ),
			'expert_name'   => get_post_meta( $post->ID, '_drtalks_expert_name', true ),
			'wp_post_url'   => $permalink ?: '',
			'drtalks_url'   => 'https://drtalks.com/videos/' . rawurlencode( $slug ),
			'synced'        => true,
		];
	}
}

// Hook for async single-expert sync triggered by drtalks_add_expert.
// Runs a full sync (no cap) since this is in the background.
add_action( 'drtalks_sync_single_expert', function ( string $expert_slug ) {
	// Bail if the expert was removed before cron got to run.
	$active_slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
	if ( ! is_array( $active_slugs ) || ! in_array( $expert_slug, $active_slugs, true ) ) {
		return;
	}

	$sync   = new DrTalks_Sync();
	$result = $sync->sync_expert( $expert_slug, 0, true );

	$status = $result['error']
		? [ 'status' => 'error', 'error' => $result['error'] ]
		: [ 'status' => 'done', 'created' => $result['created'], 'updated' => $result['updated'], 'skipped' => $result['skipped'] ];

	// Include synced_count in the transient so the polling UI can update the card count.
	$status['synced_count'] = 0;
	set_transient( 'drtalks_sync_status_' . $expert_slug, $status, HOUR_IN_SECONDS );

	// Update synced_count using the expert's video_slugs list.
	$meta_all_cron = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
	if ( ! is_array( $meta_all_cron ) ) { $meta_all_cron = []; }
	$video_slugs_cron = ( $meta_all_cron[ $expert_slug ] ?? [] )['video_slugs'] ?? [];
	if ( ! empty( $video_slugs_cron ) ) {
		$synced_count = (int) ( new WP_Query( [
			'post_type'      => 'drtalks_video',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_drtalks_video_slug',
					'value'   => $video_slugs_cron,
					'compare' => 'IN',
				],
			],
		] ) )->found_posts;
	} else {
		$synced_count = 0;
	}
	$meta_all_cron[ $expert_slug ] = array_merge( $meta_all_cron[ $expert_slug ] ?? [], [ 'synced_count' => $synced_count ] );
	update_option( 'drtalks_experts_meta', wp_json_encode( $meta_all_cron ), false );

	// Update the transient with the final synced_count so the poller gets it.
	$status['synced_count'] = $synced_count;
	set_transient( 'drtalks_sync_status_' . $expert_slug, $status, HOUR_IN_SECONDS );
} );
