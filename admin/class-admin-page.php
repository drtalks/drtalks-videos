<?php
/**
 * Top-level admin page: registers the menu, enqueues assets, and passes
 * initial state to JavaScript via wp_localize_script.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Admin_Page {

	public static function add_menu(): void {
		add_menu_page(
			'DrTalks Videos',
			'DrTalks Videos',
			'manage_options',
			'drtalks-videos',
			[ __CLASS__, 'render_page' ],
			'dashicons-video-alt3',
			25
		);
	}

	public static function init(): void {
		// Handle retry request from admin notice link.
		if ( isset( $_GET['drtalks_retry'] ) && current_user_can( 'manage_options' ) ) {
			delete_transient( 'drtalks_sync_error' );
			$slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
			if ( is_array( $slugs ) ) {
				$sync = new DrTalks_Sync();
				foreach ( $slugs as $slug ) {
					$slug = sanitize_title( $slug );
					if ( $slug ) {
						$sync->sync_expert( $slug, 0, true );
					}
				}
			}
		}

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( $hook !== 'toplevel_page_drtalks-videos' ) {
			return;
		}

		wp_enqueue_style(
			'drtalks-admin-page',
			DRTALKS_VIDEOS_URL . 'admin/css/admin-page.css',
			[],
			DRTALKS_VIDEOS_VERSION
		);

		wp_enqueue_script(
			'drtalks-admin-page',
			DRTALKS_VIDEOS_URL . 'admin/js/admin-page.js',
			[],
			DRTALKS_VIDEOS_VERSION,
			true
		);

		$selected_videos = drtalks_get_selected_video_data();
		$expert_slugs    = drtalks_get_expert_data();
		$expert_videos   = drtalks_get_all_expert_videos_data();

		error_log( '[DrTalks enqueue_scripts] archiveEnabled=' . var_export( (bool) get_option( 'drtalks_archive_enabled', false ), true )
			. ' selectedVideos count=' . count( $selected_videos )
			. ' expertSlugs count=' . count( $expert_slugs )
			. ' expertVideos count=' . count( $expert_videos )
		);
		error_log( '[DrTalks enqueue_scripts] selectedVideos payload: ' . wp_json_encode( $selected_videos ) );

		wp_localize_script( 'drtalks-admin-page', 'drtalksAdmin', [
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'drtalks_admin_nonce' ),
			'archiveEnabled'      => (bool) get_option( 'drtalks_archive_enabled', false ),
			'archiveSlug'         => get_option( 'drtalks_archive_slug', 'videos' ),
			'syncSchedule'        => get_option( 'drtalks_sync_schedule', 'daily' ),
			'videoTemplateStyle'  => get_option( 'drtalks_video_template_style', 'theme' ),
			'siteUrl'             => home_url( '/' ),
			'selectedVideos'      => $selected_videos,
			'expertSlugs'         => $expert_slugs,
			'expertVideos'        => $expert_videos,
			'hiddenVideos'        => drtalks_get_hidden_video_data(),
		] );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require DRTALKS_VIDEOS_DIR . 'admin/views/admin-page.php';
	}
}

/**
 * Returns data for manually selected videos.
 * Queries CPT posts whose _drtalks_video_slug is in the drtalks_selected_videos option (D2).
 *
 * @return array[]
 */
function drtalks_get_selected_video_data(): array {
	$raw_option = get_option( 'drtalks_selected_videos', '[]' );
	error_log( '[DrTalks get_selected_video_data] raw option value: ' . $raw_option );

	$slugs = json_decode( $raw_option, true );
	error_log( '[DrTalks get_selected_video_data] decoded slugs: ' . wp_json_encode( $slugs ) . ' (json_last_error=' . json_last_error() . ')' );

	if ( ! is_array( $slugs ) || empty( $slugs ) ) {
		error_log( '[DrTalks get_selected_video_data] slugs is empty/invalid — returning []' );
		return [];
	}

	$posts = get_posts( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => [
			[
				'key'     => '_drtalks_video_slug',
				'value'   => $slugs,
				'compare' => 'IN',
			],
		],
	] );

	error_log( '[DrTalks get_selected_video_data] CPT query found ' . count( $posts ) . ' posts for slugs: ' . implode( ', ', $slugs ) );

	// If no posts found, do a broader check to diagnose why.
	if ( empty( $posts ) ) {
		$all_posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => 'any',
			'posts_per_page' => 10,
		] );
		error_log( '[DrTalks get_selected_video_data] DIAGNOSTIC: total drtalks_video posts (any status): ' . count( $all_posts ) );
		foreach ( $all_posts as $p ) {
			error_log( '[DrTalks get_selected_video_data]   post_id=' . $p->ID
				. ' status=' . $p->post_status
				. ' slug_meta=' . get_post_meta( $p->ID, '_drtalks_video_slug', true )
			);
		}
	}

	$result = [];
	foreach ( $posts as $post ) {
		$slug      = get_post_meta( $post->ID, '_drtalks_video_slug', true );
		$permalink = get_permalink( $post->ID );
		error_log( '[DrTalks get_selected_video_data] building card for post_id=' . $post->ID . ' slug=' . $slug . ' permalink=' . ( $permalink ?: '(false)' ) );
		$result[] = [
			'slug'          => $slug,
			'title'         => get_the_title( $post->ID ),
			'thumbnail_url' => get_post_meta( $post->ID, '_drtalks_thumbnail', true ),
			'wp_post_url'   => $permalink ?: '',
			'drtalks_url'   => 'https://drtalks.com/videos/' . rawurlencode( $slug ),
			'expert_slug'   => get_post_meta( $post->ID, '_drtalks_expert_slug', true ),
			'expert_name'   => get_post_meta( $post->ID, '_drtalks_expert_name', true ),
		];
	}

	error_log( '[DrTalks get_selected_video_data] returning ' . count( $result ) . ' cards' );
	return $result;
}

/**
 * Returns expert card data for each expert in drtalks_expert_slugs.
 * Reads name/photo from the drtalks_experts_meta option (persisted on add_expert),
 * so it survives any sync state. Falls back to CPT post meta for legacy data.
 *
 * @return array[]
 */
function drtalks_get_expert_data(): array {
	$slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
	if ( ! is_array( $slugs ) || empty( $slugs ) ) {
		return [];
	}

	$meta_all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
	if ( ! is_array( $meta_all ) ) {
		$meta_all = [];
	}

	$result = [];
	foreach ( $slugs as $expert_slug ) {
		$expert_slug = sanitize_title( $expert_slug );
		if ( ! $expert_slug ) {
			continue;
		}

		$meta = $meta_all[ $expert_slug ] ?? [];

		// Count synced videos using the stored video_slugs list (accurate even when a
		// video belongs to multiple experts).
		$video_slugs_for_count = $meta['video_slugs'] ?? [];
		if ( ! empty( $video_slugs_for_count ) ) {
			$synced_count = (int) ( new WP_Query( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => '_drtalks_video_slug',
						'value'   => $video_slugs_for_count,
						'compare' => 'IN',
					],
				],
			] ) )->found_posts;
		} else {
			// Legacy fallback: no video_slugs stored yet.
			$synced_count = (int) ( new WP_Query( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[ 'key' => '_drtalks_expert_slug', 'value' => $expert_slug ],
				],
			] ) )->found_posts;
		}

		// Legacy fallback: if no metadata in the option, derive from a CPT post.
		if ( empty( $meta['name'] ) || empty( $meta['photo_url'] ) ) {
			$posts = get_posts( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => [
					[ 'key' => '_drtalks_expert_slug', 'value' => $expert_slug ],
				],
			] );
			if ( $posts ) {
				if ( empty( $meta['name'] ) ) {
					$meta['name'] = get_post_meta( $posts[0]->ID, '_drtalks_expert_name', true );
				}
				if ( empty( $meta['photo_url'] ) ) {
					$meta['photo_url'] = get_post_meta( $posts[0]->ID, '_drtalks_expert_photo', true );
				}
			}
		}

		$result[] = [
			'slug'         => $expert_slug,
			'name'         => ( ! empty( $meta['name'] ) ) ? $meta['name'] : $expert_slug,
			'photo_url'    => $meta['photo_url'] ?? '',
			'video_count'  => isset( $meta['video_count'] ) ? (int) $meta['video_count'] : $synced_count,
			'synced_count' => $synced_count,
		];
	}
	return $result;
}

/**
 * Returns expert-synced video data for the two-column Section 2 left column.
 * Capped at 50 per expert to keep the initial page payload reasonable.
 *
 * @return array[]
 */
function drtalks_get_all_expert_videos_data(): array {
	$expert_slugs = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
	if ( ! is_array( $expert_slugs ) || empty( $expert_slugs ) ) {
		return [];
	}

	$meta_all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
	if ( ! is_array( $meta_all ) ) {
		$meta_all = [];
	}

	$hidden_raw   = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
	$hidden_slugs = [];
	if ( is_array( $hidden_raw ) ) {
		foreach ( $hidden_raw as $item ) {
			if ( ! empty( $item['slug'] ) ) {
				$hidden_slugs[ $item['slug'] ] = true;
			}
		}
	}

	// Build per-expert video slug lists. Videos are identified by _drtalks_video_slug —
	// a single video may appear under multiple experts' lists.
	$expert_video_map = [];
	$all_video_slugs  = [];
	foreach ( $expert_slugs as $expert_slug ) {
		$expert_slug = sanitize_title( $expert_slug );
		if ( ! $expert_slug ) {
			continue;
		}
		$meta        = $meta_all[ $expert_slug ] ?? [];
		$video_slugs = $meta['video_slugs'] ?? [];

		if ( empty( $video_slugs ) ) {
			// Legacy fallback: video_slugs not stored yet — derive from _drtalks_expert_slug meta.
			$fallback = get_posts( [
				'post_type'      => 'drtalks_video',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => [
					[ 'key' => '_drtalks_expert_slug', 'value' => $expert_slug ],
				],
			] );
			foreach ( $fallback as $_p ) {
				$vs = get_post_meta( $_p->ID, '_drtalks_video_slug', true );
				if ( $vs ) {
					$video_slugs[] = $vs;
				}
			}
		}

		$expert_video_map[ $expert_slug ] = $video_slugs;
		foreach ( $video_slugs as $vs ) {
			$all_video_slugs[ $vs ] = true;
		}
	}

	if ( empty( $all_video_slugs ) ) {
		return [];
	}

	// Fetch all relevant posts in one query, keyed by video slug.
	$posts = get_posts( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'publish',
		'posts_per_page' => count( $all_video_slugs ) + 50,
		'meta_query'     => [
			[
				'key'     => '_drtalks_video_slug',
				'value'   => array_keys( $all_video_slugs ),
				'compare' => 'IN',
			],
		],
	] );

	$posts_by_slug = [];
	foreach ( $posts as $post ) {
		$vs = get_post_meta( $post->ID, '_drtalks_video_slug', true );
		if ( $vs ) {
			$posts_by_slug[ $vs ] = $post;
		}
	}

	// Build result: iterate experts in order, then their video slugs in API order.
	$result = [];
	foreach ( $expert_slugs as $expert_slug ) {
		$expert_slug = sanitize_title( $expert_slug );
		if ( ! $expert_slug || empty( $expert_video_map[ $expert_slug ] ) ) {
			continue;
		}
		$expert_name = $meta_all[ $expert_slug ]['name'] ?? $expert_slug;

		foreach ( $expert_video_map[ $expert_slug ] as $video_slug ) {
			if ( isset( $hidden_slugs[ $video_slug ] ) ) {
				continue;
			}
			if ( ! isset( $posts_by_slug[ $video_slug ] ) ) {
				continue;
			}
			$post   = $posts_by_slug[ $video_slug ];
			$result[] = [
				'slug'          => $video_slug,
				'title'         => get_the_title( $post->ID ),
				'thumbnail_url' => get_post_meta( $post->ID, '_drtalks_thumbnail', true ),
				'expert_slug'   => $expert_slug,
				'expert_name'   => $expert_name,
				'wp_post_url'   => get_permalink( $post->ID ) ?: '',
				'drtalks_url'   => 'https://drtalks.com/videos/' . rawurlencode( $video_slug ),
			];
		}
	}

	return $result;
}

/**
 * Returns the hidden video list directly from the stored option.
 * No API call needed — metadata is stored with the entry.
 *
 * @return array[]
 */
function drtalks_get_hidden_video_data(): array {
	$hidden = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
	return is_array( $hidden ) ? $hidden : [];
}
