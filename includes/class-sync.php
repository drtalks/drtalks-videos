<?php
/**
 * Import/sync logic for DrTalks videos.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Sync {

	private DrTalks_API_Client $api;

	public function __construct() {
		$this->api = new DrTalks_API_Client();
	}

	/**
	 * Sync videos for an expert.
	 *
	 * @param  string $expert_slug     The expert's DrTalks slug.
	 * @param  int    $max_videos      Hard cap on videos to process this call. 0 = no limit (cron use).
	 * @param  bool   $update_existing When true (cron), re-fetches and updates existing posts.
	 *                                 When false (initial add), inserts only new videos.
	 * @return array  { created: int, updated: int, skipped: int, error: string|null }
	 */
	public function sync_expert( string $expert_slug, int $max_videos = 0, bool $update_existing = false ): array {
		DrTalks_Debug::info( "sync_expert start", [
			'expert_slug'     => $expert_slug,
			'max_videos'      => $max_videos,
			'update_existing' => $update_existing,
		] );
		set_time_limit( 120 );

		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$page     = 1;
		$has_more = true;
		$all_slugs = [];
		$page_size = $max_videos > 0 ? min( $max_videos, 50 ) : 50;

		while ( $has_more ) {
			if ( $max_videos > 0 && count( $all_slugs ) >= $max_videos ) {
				break;
			}
			$result = $this->api->get_expert_videos( $expert_slug, $page, $page_size );
			if ( is_wp_error( $result ) ) {
				$msg = $result->get_error_message();
				set_transient( 'drtalks_sync_error', $msg, DAY_IN_SECONDS );
				return [ 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'error' => $msg ];
			}

			$videos   = $result['videos'] ?? [];
			$has_more = (bool) ( $result['has_more'] ?? false );
			$page++;

		foreach ( $videos as $v ) {
			$all_slugs[] = sanitize_title( $v['slug'] ?? '' );
		}
		error_log( '[DrTalks sync_expert] page=' . ( $page - 1 ) . ' got ' . count( $videos ) . ' videos. has_more=' . var_export( $has_more, true ) );
	}

	error_log( '[DrTalks sync_expert] total slugs from API for ' . $expert_slug . ': ' . count( $all_slugs ) . ' → ' . implode( ', ', $all_slugs ) );

	if ( $max_videos > 0 ) {
		$all_slugs = array_slice( $all_slugs, 0, $max_videos );
	}

	// Persist the full list of video slugs for this expert so the panel can display them
	// correctly regardless of _drtalks_expert_slug meta on individual posts.
	$meta_all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
	if ( ! is_array( $meta_all ) ) {
		$meta_all = [];
	}
	$meta_all[ $expert_slug ] = array_merge( $meta_all[ $expert_slug ] ?? [], [
		'video_slugs' => array_values( array_filter( $all_slugs ) ),
	] );
	update_option( 'drtalks_experts_meta', wp_json_encode( $meta_all ), false );

	// Fetch existing posts by video slug — a video is unique by its slug, not by expert.
	$existing_posts = $this->get_existing_posts_by_slugs( $all_slugs );

	// Build skip-list from hidden videos option.
	$hidden_raw   = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
	$hidden_slugs = [];
	if ( is_array( $hidden_raw ) ) {
		foreach ( $hidden_raw as $item ) {
			if ( ! empty( $item['slug'] ) ) {
				$hidden_slugs[ sanitize_title( $item['slug'] ) ] = true;
			}
		}
	}

	foreach ( $all_slugs as $slug ) {
		if ( ! $slug ) {
			continue;
		}

		if ( isset( $hidden_slugs[ $slug ] ) ) {
			$skipped++;
			continue;
		}

		error_log( '[DrTalks sync_expert] processing slug=' . $slug . ' post_exists=' . var_export( isset( $existing_posts[ $slug ] ), true ) );

		if ( isset( $existing_posts[ $slug ] ) ) {
			$post = $existing_posts[ $slug ];

			// Restore trashed posts.
			if ( $post->post_status === 'trash' ) {
				wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'publish' ] );
				error_log( '[DrTalks sync_expert] restored trashed post_id=' . $post->ID . ' slug=' . $slug );
			}

			if ( $update_existing ) {
				$video_data = $this->api->get_video( $slug );
				if ( is_wp_error( $video_data ) ) {
					$skipped++;
					continue;
				}
				$this->update_post_meta_from_data( $post->ID, $video_data );
				$updated++;
			} else {
				$skipped++;
			}
		} else {
			$video_data = $this->api->get_video( $slug );
			if ( is_wp_error( $video_data ) ) {
				$skipped++;
				continue;
			}
			$post_id = $this->create_post( $slug, $video_data );
			error_log( '[DrTalks sync_expert] created new post_id=' . ( $post_id ?: 'FAILED' ) . ' for slug=' . $slug );
			if ( $post_id ) {
				$created++;
			} else {
				$skipped++;
			}
		}
	}

		delete_transient( 'drtalks_sync_error' );

		$result = [ 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'error' => null ];
		DrTalks_Debug::log_sync( $expert_slug, $result );
		return $result;
	}

	/**
	 * Import a single video by slug.
	 *
	 * @return int|WP_Error  Post ID on success.
	 */
	public function sync_video( string $slug ) {
		$video_data = $this->api->get_video( $slug );
		if ( is_wp_error( $video_data ) ) {
			return $video_data;
		}

		// Check for existing post (any status, including trash).
		$existing = $this->get_post_by_video_slug( $slug );
		if ( $existing ) {
			// If the post was trashed (e.g. from a previous remove_video call),
			// restore it to publish so get_permalink() returns a proper URL and
			// the admin panel query (post_status=publish) can find it again.
			$update_args = [ 'ID' => $existing->ID ];
			if ( $existing->post_status === 'trash' ) {
				$update_args['post_status'] = 'publish';
				error_log( '[DrTalks sync_video] restoring trashed post_id=' . $existing->ID . ' for slug=' . $slug );
			}
			// Always sync the title from the API in case it changed.
			if ( ! empty( $video_data['_title'] ) ) {
				$update_args['post_title'] = $video_data['_title'];
			}
			if ( count( $update_args ) > 1 ) {
				wp_update_post( $update_args );
			}
			$this->update_post_meta_from_data( $existing->ID, $video_data );
			return $existing->ID;
		}

		return $this->create_post( $slug, $video_data );
	}

	/**
	 * Create a new drtalks_video CPT post.
	 *
	 * @return int|false
	 */
	private function create_post( string $slug, array $data ) {
		DrTalks_Debug::info( "create_post: $slug", [ 'title' => $data['_title'] ?? $slug ] );
		$post_id = wp_insert_post( [
			'post_type'   => 'drtalks_video',
			'post_status' => 'publish',
			'post_title'  => $data['_title'] ?? $slug,
		], true );

		if ( is_wp_error( $post_id ) ) {
			DrTalks_Debug::error( "create_post failed: $slug", [ 'error' => $post_id->get_error_message() ] );
			return false;
		}

		$this->update_post_meta_from_data( $post_id, $data );

		return $post_id;
	}

	/**
	 * Write all meta fields from sanitized API data.
	 */
	private function update_post_meta_from_data( int $post_id, array $data ): void {
		$meta_keys = [
			'_drtalks_video_slug',
			'_drtalks_embed_url',
			'_drtalks_thumbnail',
			'_drtalks_description',
			'_drtalks_transcript',
			'_drtalks_duration',
			'_drtalks_synced_at',
			'_drtalks_expert_slug',
			'_drtalks_expert_name',
			'_drtalks_expert_photo',
			'_drtalks_expert_bio',
			'_drtalks_expert_credentials',
			'_drtalks_expert_title',
		];

		foreach ( $meta_keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $post_id, $key, $data[ $key ] );
			}
		}
	}

	/**
	 * Return existing drtalks_video posts keyed by video slug.
	 * Videos are unique by their slug — not by expert.
	 *
	 * @param  string[] $slugs  Video slugs to look up.
	 * @return WP_Post[]  Keyed by _drtalks_video_slug value.
	 */
	private function get_existing_posts_by_slugs( array $slugs ): array {
		$slugs = array_values( array_filter( $slugs ) );
		if ( empty( $slugs ) ) {
			return [];
		}

		$posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => [ 'publish', 'trash' ],
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_drtalks_video_slug',
					'value'   => $slugs,
					'compare' => 'IN',
				],
			],
		] );

		$indexed = [];
		foreach ( $posts as $post ) {
			$slug = get_post_meta( $post->ID, '_drtalks_video_slug', true );
			if ( $slug ) {
				$indexed[ $slug ] = $post;
			}
		}

		error_log( '[DrTalks get_existing_posts_by_slugs] found ' . count( $indexed ) . ' / ' . count( $slugs ) . ' posts' );
		return $indexed;
	}

	/**
	 * Find a single existing post by video slug.
	 *
	 * @return WP_Post|null
	 */
	private function get_post_by_video_slug( string $slug ): ?WP_Post {
		$posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => [ 'publish', 'draft', 'trash' ],
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => '_drtalks_video_slug',
					'value' => sanitize_title( $slug ),
				],
			],
		] );

		return $posts[0] ?? null;
	}
}
