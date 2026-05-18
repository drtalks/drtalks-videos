<?php
/**
 * Custom post type registration.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Post_Type {

	public static function init_templates(): void {
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

		if ( $is_block_theme && function_exists( 'register_block_template' ) ) {
			// Block theme (WP 6.7+): register a native block template.
			// WordPress renders the header/footer template parts automatically;
			// video content is injected via the_content filter in functions.php.
			$style    = get_option( 'drtalks_video_template_style', 'theme' );
			$html     = ( $style === 'video' )
				? DRTALKS_VIDEOS_DIR . 'templates/single-drtalks_video-video.html'
				: DRTALKS_VIDEOS_DIR . 'templates/single-drtalks_video.html';
			register_block_template(
				'drtalks-videos//single-drtalks_video',
				[
					'title'      => __( 'Single DrTalks Video', 'drtalks-videos' ),
					'post_types' => [ 'drtalks_video' ],
					'content'    => file_exists( $html ) ? file_get_contents( $html ) : '',
				]
			);
		} else {
			// Classic theme or pre-6.7 block theme: PHP template override.
			add_filter( 'template_include', [ __CLASS__, 'template_include' ] );
		}
	}

	public static function template_include( string $template ): string {
		if ( is_singular( 'drtalks_video' ) ) {
			$plugin_tpl = DRTALKS_VIDEOS_DIR . 'templates/single-drtalks_video.php';
			if ( file_exists( $plugin_tpl ) ) {
				return $plugin_tpl;
			}
		}
		if ( is_post_type_archive( 'drtalks_video' ) ) {
			$plugin_tpl = DRTALKS_VIDEOS_DIR . 'templates/archive-drtalks_video.php';
			if ( file_exists( $plugin_tpl ) ) {
				return $plugin_tpl;
			}
		}
		return $template;
	}

	/**
	 * Filter the archive query so it only shows videos that are actively tracked
	 * (selected individually or belonging to a synced expert) and not hidden.
	 *
	 * This prevents orphaned posts — left behind from old syncs or incomplete
	 * expert removals — from appearing on the public archive.
	 */
	public static function filter_archive_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( 'drtalks_video' ) ) {
			return;
		}

		// Build the full set of video slugs that should be visible.
		$allowed_slugs = [];

		// 1. Manually selected individual videos.
		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		if ( is_array( $selected ) ) {
			$allowed_slugs = array_merge( $allowed_slugs, $selected );
		}

		// 2. Videos belonging to active experts (new model).
		$meta_all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		if ( is_array( $meta_all ) ) {
			foreach ( $meta_all as $expert_data ) {
				$slugs = $expert_data['video_slugs'] ?? [];
				if ( is_array( $slugs ) ) {
					$allowed_slugs = array_merge( $allowed_slugs, $slugs );
				}
			}
		}

		$allowed_slugs = array_values( array_unique( array_filter( $allowed_slugs ) ) );

		if ( empty( $allowed_slugs ) ) {
			// Nothing is tracked yet — show nothing.
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		// Exclude hidden videos.
		$hidden = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
		if ( is_array( $hidden ) && ! empty( $hidden ) ) {
			$allowed_slugs = array_values( array_diff( $allowed_slugs, $hidden ) );
		}

		// Restrict the query to only posts whose _drtalks_video_slug is in the allowed list.
		$meta_query = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
			'key'     => '_drtalks_video_slug',
			'value'   => $allowed_slugs,
			'compare' => 'IN',
		];
		$query->set( 'meta_query', $meta_query );
	}

	public static function register(): void {
		$archive_enabled = (bool) get_option( 'drtalks_archive_enabled', false );
		$archive_slug    = get_option( 'drtalks_archive_slug', 'videos' );

		error_log( '[DrTalks post_type] register drtalks_video: archive_enabled=' . var_export( $archive_enabled, true )
			. ' archive_slug=' . $archive_slug
			. ' → public=' . var_export( $archive_enabled, true )
			. ' rewrite_slug=' . ( $archive_enabled ? sanitize_title( $archive_slug ) : 'FALSE (not public)' )
		);

		register_post_type( 'drtalks_video', [
			'label'        => 'DrTalks Videos',
			'labels'       => [
				'name'               => 'DrTalks Videos',
				'singular_name'      => 'DrTalks Video',
				'add_new_item'       => 'Add New DrTalks Video',
				'edit_item'          => 'Edit DrTalks Video',
				'search_items'       => 'Search DrTalks Videos',
				'not_found'          => 'No DrTalks Videos found',
				'not_found_in_trash' => 'No DrTalks Videos found in Trash',
			],
			'public'       => $archive_enabled,
			'has_archive'  => $archive_enabled ? sanitize_title( $archive_slug ) : false,
			'rewrite'      => $archive_enabled ? [ 'slug' => sanitize_title( $archive_slug ) ] : false,
			'supports'     => [ 'title' ],
			'show_in_rest' => false,
			'show_in_menu' => false,
		] );
	}
}
