<?php
/**
 * Action Scheduler integration for DrTalks Videos.
 *
 * Replaces WP-Cron with Action Scheduler for the three background jobs:
 *  - drtalks/sync_all_experts     (recurring)
 *  - drtalks/sync_expert          (single, runs in 10-video chunks)
 *  - drtalks/fetch_transcript     (single)
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Scheduler {

	const GROUP                 = 'drtalks';
	const HOOK_SYNC_ALL         = 'drtalks/sync_all_experts';
	const HOOK_SYNC_EXPERT      = 'drtalks/sync_expert';
	const HOOK_FETCH_TRANSCRIPT = 'drtalks/fetch_transcript';
	const HOOK_CLEANUP_ORPHANS  = 'drtalks/cleanup_orphans';
	const BATCH_SIZE            = 10;

	public static function init(): void {
		add_action( self::HOOK_SYNC_ALL,          [ __CLASS__, 'run_sync_all_experts' ] );
		add_action( self::HOOK_SYNC_EXPERT,       [ __CLASS__, 'run_sync_expert' ], 10, 2 );
		add_action( self::HOOK_FETCH_TRANSCRIPT,  [ __CLASS__, 'run_fetch_transcript' ] );
		add_action( self::HOOK_CLEANUP_ORPHANS,   [ __CLASS__, 'run_cleanup_orphans' ] );
	}

	// --- Schedule management -------------------------------------------------

	/**
	 * Ensure recurring actions are scheduled. Idempotent — safe to call repeatedly.
	 *
	 * - Expert sync fan-out: scheduled based on the user's chosen schedule option.
	 *   If schedule is 'manual', any existing fan-out action is cancelled.
	 * - Orphan cleanup: always scheduled daily regardless of sync schedule.
	 */
	public static function ensure_recurring_scheduled(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$schedule = get_option( 'drtalks_sync_schedule', 'daily' );

		// --- Expert sync fan-out ---
		if ( $schedule === 'manual' ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::HOOK_SYNC_ALL, [], self::GROUP );
			}
		} else {
			$interval = self::schedule_to_interval( $schedule );
			if ( ! as_next_scheduled_action( self::HOOK_SYNC_ALL, [], self::GROUP ) ) {
				as_schedule_recurring_action( time() + 60, $interval, self::HOOK_SYNC_ALL, [], self::GROUP );
			}
		}

		// --- Daily orphan cleanup (always on) ---
		if ( ! as_has_scheduled_action( self::HOOK_CLEANUP_ORPHANS, [], self::GROUP ) ) {
			as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_CLEANUP_ORPHANS, [], self::GROUP );
		}
	}

	/**
	 * Cancel all recurring actions (called on plugin deactivation).
	 */
	public static function unschedule_recurring(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		as_unschedule_all_actions( self::HOOK_SYNC_ALL, [], self::GROUP );
		as_unschedule_all_actions( self::HOOK_CLEANUP_ORPHANS, [], self::GROUP );
	}

	/**
	 * Reset the recurring action — called when the user changes the sync schedule.
	 */
	public static function reschedule_recurring(): void {
		self::unschedule_recurring();
		self::ensure_recurring_scheduled();
	}

	/**
	 * Queue a single-expert sync, optionally with an offset for batching.
	 */
	public static function queue_sync_expert( string $expert_slug, int $offset = 0 ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$expert_slug = sanitize_title( $expert_slug );
		if ( ! $expert_slug ) {
			return;
		}

		// Don't pile up duplicates if one is already pending for this expert+offset.
		if ( as_has_scheduled_action( self::HOOK_SYNC_EXPERT, [ $expert_slug, $offset ], self::GROUP ) ) {
			return;
		}

		as_enqueue_async_action( self::HOOK_SYNC_EXPERT, [ $expert_slug, $offset ], self::GROUP );
	}

	/**
	 * Queue a transcript fetch for a video slug.
	 */
	public static function queue_fetch_transcript( string $slug ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return;
		}
		if ( as_has_scheduled_action( self::HOOK_FETCH_TRANSCRIPT, [ $slug ], self::GROUP ) ) {
			return;
		}
		as_enqueue_async_action( self::HOOK_FETCH_TRANSCRIPT, [ $slug ], self::GROUP );
	}

	// --- Action handlers -----------------------------------------------------

	/**
	 * Recurring fan-out: queue one sync_expert action per active expert.
	 */
	public static function run_sync_all_experts(): void {
		$slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
		if ( ! is_array( $slugs ) ) {
			return;
		}
		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( $slug );
			if ( $slug ) {
				self::queue_sync_expert( $slug, 0 );
			}
		}
		if ( ! empty( $slugs ) ) {
			update_option( 'drtalks_last_sync_started_at', time(), false );
		}
	}

	/**
	 * Sync one batch of videos for an expert. Re-schedules itself if more remain.
	 *
	 * @param string $expert_slug
	 * @param int    $offset  Number of videos already processed in prior runs of this sweep.
	 */
	public static function run_sync_expert( string $expert_slug, int $offset = 0 ): void {
		$expert_slug = sanitize_title( $expert_slug );
		if ( ! $expert_slug ) {
			return;
		}

		$sync   = new DrTalks_Sync();
		$result = $sync->sync_expert_batch( $expert_slug, self::BATCH_SIZE, $offset );

		// Update synced_count using the new video_slugs model.
		$meta_all    = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		if ( ! is_array( $meta_all ) ) { $meta_all = []; }
		$video_slugs = $meta_all[ $expert_slug ]['video_slugs'] ?? [];

		$synced_count = 0;
		if ( ! empty( $video_slugs ) ) {
			$synced_count = (int) ( new WP_Query( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[ 'key' => '_drtalks_video_slug', 'value' => $video_slugs, 'compare' => 'IN' ],
				],
			] ) )->found_posts;
		}

		$meta_all[ $expert_slug ] = array_merge( $meta_all[ $expert_slug ] ?? [], [ 'synced_count' => $synced_count ] );
		update_option( 'drtalks_experts_meta', wp_json_encode( $meta_all ), false );

		// If there are more videos to process, queue the next batch.
		if ( $result['has_more'] ) {
			$next_offset = $offset + self::BATCH_SIZE;
			as_enqueue_async_action(
				self::HOOK_SYNC_EXPERT,
				[ $expert_slug, $next_offset ],
				self::GROUP
			);
		} else {
			set_transient( 'drtalks_sync_status_' . $expert_slug, [
				'status'  => 'done',
				'synced'  => $synced_count,
			], HOUR_IN_SECONDS );
			// Record completion time; only update global "last completed" when no
			// more expert sync batches are still pending.
			$still_pending = function_exists( 'as_get_scheduled_actions' )
				? count( as_get_scheduled_actions( [
					'hook'     => self::HOOK_SYNC_EXPERT,
					'group'    => self::GROUP,
					'status'   => 'pending',
					'per_page' => 1,
				] ) )
				: 0;
			if ( ! $still_pending ) {
				update_option( 'drtalks_last_sync_completed_at', time(), false );
			}
		}
	}

	/**
	 * Fetch and cache a single transcript.
	 */
	public static function run_fetch_transcript( string $slug ): void {
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return;
		}
		$api    = new DrTalks_API_Client();
		$result = $api->get_video( $slug );
		if ( is_wp_error( $result ) ) {
			return;
		}
		$transcript = $result['_drtalks_transcript'] ?? '';
		if ( $transcript ) {
			set_transient( 'drtalks_transcript_' . $slug, $transcript, DAY_IN_SECONDS );
		}
	}

	// --- Orphan cleanup ------------------------------------------------------

	/**
	 * Return IDs of all drtalks_video posts not tracked by any expert or
	 * individual video selection.
	 *
	 * @return int[]
	 */
	public static function get_orphan_post_ids(): array {
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

		$all_posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => [ 'publish', 'trash' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$orphan_ids = [];
		foreach ( $all_posts as $post_id ) {
			$slug = get_post_meta( (int) $post_id, '_drtalks_video_slug', true );
			if ( empty( $slug ) || ! in_array( $slug, $tracked_slugs, true ) ) {
				$orphan_ids[] = (int) $post_id;
			}
		}
		return $orphan_ids;
	}

	/**
	 * Schedule an immediate one-off orphan cleanup (idempotent).
	 */
	public static function queue_cleanup_orphans(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		if ( as_has_scheduled_action( self::HOOK_CLEANUP_ORPHANS, [], self::GROUP ) ) {
			return;
		}
		as_enqueue_async_action( self::HOOK_CLEANUP_ORPHANS, [], self::GROUP );
	}

	/**
	 * Action Scheduler handler: delete all current orphans.
	 */
	public static function run_cleanup_orphans(): void {
		$orphan_ids = self::get_orphan_post_ids();
		$deleted    = 0;
		foreach ( $orphan_ids as $post_id ) {
			wp_delete_post( $post_id, true );
			$deleted++;
		}
	}

	// --- Status query --------------------------------------------------------

	/**
	 * Return a concise snapshot of the current global sync state.
	 *
	 * Possible states:
	 *  'running'   — expert sync batches are pending or in-progress right now
	 *  'scheduled' — fan-out is next scheduled at $data['next'] (Unix timestamp)
	 *  'manual'    — schedule is set to "manual only"
	 *  'idle'      — schedule set but no next action found (misconfiguration)
	 *
	 * $data['since']     (int)  — Unix timestamp when the running sync started
	 * $data['next']      (int)  — Unix timestamp of next fan-out (if scheduled)
	 * $data['last']      (int)  — Unix timestamp of last completed fan-out
	 */
	public static function get_global_sync_status(): array {
		$schedule = get_option( 'drtalks_sync_schedule', 'daily' );
		$last     = (int) get_option( 'drtalks_last_sync_completed_at', 0 );

		// Check for any pending / in-progress expert-level sync actions.
		$is_running = false;
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			foreach ( [ 'pending', 'in-progress' ] as $st ) {
				$rows = as_get_scheduled_actions( [
					'hook'     => self::HOOK_SYNC_EXPERT,
					'group'    => self::GROUP,
					'status'   => $st,
					'per_page' => 1,
				] );
				if ( ! empty( $rows ) ) {
					$is_running = true;
					break;
				}
			}
		}

		if ( $is_running ) {
			$since = (int) get_option( 'drtalks_last_sync_started_at', 0 );
			return [
				'state' => 'running',
				'since' => $since ?: time(),
				'last'  => $last ?: null,
			];
		}

		if ( $schedule === 'manual' ) {
			return [
				'state' => 'manual',
				'last'  => $last ?: null,
			];
		}

		$next = function_exists( 'as_next_scheduled_action' )
			? as_next_scheduled_action( self::HOOK_SYNC_ALL, [], self::GROUP )
			: false;

		if ( $next ) {
			return [
				'state' => 'scheduled',
				'next'  => (int) $next,
				'last'  => $last ?: null,
			];
		}

		return [
			'state' => 'idle',
			'last'  => $last ?: null,
		];
	}

	// --- Helpers -------------------------------------------------------------

	/**
	 * Map the user's selected schedule option to seconds.
	 */
	private static function schedule_to_interval( string $schedule ): int {
		switch ( $schedule ) {
			case 'hourly':     return HOUR_IN_SECONDS;
			case 'twicedaily': return 12 * HOUR_IN_SECONDS;
			case 'weekly':     return WEEK_IN_SECONDS;
			case 'daily':
			default:           return DAY_IN_SECONDS;
		}
	}
}
