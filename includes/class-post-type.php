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
			// Block theme (WP 6.7+): register native block templates for single + archive.
			// The block template system handles header/footer via template-part blocks,
			// giving the content proper theme wrapper (group block, padding, etc.).

			// Single video template.
			$style = get_option( 'drtalks_video_template_style', 'theme' );
			$html  = ( $style === 'video' )
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

			// Archive template: register the drtalks-videos/archive block (render callback
			// outputs the full search + grid + pagination HTML) then register the block
			// template that places it inside the theme's normal main-group wrapper.
			register_block_type( 'drtalks-videos/archive', [
				'render_callback' => [ __CLASS__, 'render_archive_block' ],
			] );

			$archive_html = DRTALKS_VIDEOS_DIR . 'templates/archive-drtalks_video.html';
			register_block_template(
				'drtalks-videos//archive-drtalks_video',
				[
					'title'   => __( 'DrTalks Videos Archive', 'drtalks-videos' ),
					'content' => file_exists( $archive_html ) ? file_get_contents( $archive_html ) : '',
				]
			);
		} else {
			// Classic theme or pre-6.7 block theme: PHP templates for both.
			add_filter( 'template_include', [ __CLASS__, 'template_include_single' ] );
			add_filter( 'template_include', [ __CLASS__, 'template_include_archive' ], 99 );
		}
	}

	/**
	 * Render callback for the drtalks-videos/archive block (block themes only).
	 * Outputs the full archive UI: search bar, video grid, pagination.
	 * The global $wp_query is already filtered by filter_archive_query() at this point.
	 *
	 * @param array    $attrs   Block attributes.
	 * @param string   $content Inner block content.
	 * @param WP_Block $block   Block instance.
	 * @return string Rendered HTML.
	 */
	public static function render_archive_block( array $attrs, string $content, WP_Block $block ): string {
		global $wp_query;

		wp_enqueue_style( 'drtalks-frontend', DRTALKS_VIDEOS_URL . 'assets/frontend.css', [], DRTALKS_VIDEOS_VERSION );
		wp_enqueue_script( 'drtalks-archive', DRTALKS_VIDEOS_URL . 'assets/archive.js', [], DRTALKS_VIDEOS_VERSION, true );
		wp_localize_script( 'drtalks-archive', 'drtalksArchive', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'drtalks_archive_search' ),
		] );

		$posts = $wp_query->posts ?? [];
		$total = (int) ( $wp_query->found_posts ?? 0 );

		ob_start();
		?>
		<div class="drtalks-archive-inner">

			<!-- Page header -->
			<div class="drtalks-archive-header">
				<h1 class="drtalks-archive-title"><?php esc_html_e( 'Videos Archive', 'drtalks-videos' ); ?></h1>
				<?php if ( $total > 0 ) : ?>
				<span class="drtalks-archive-count"><?php echo esc_html( $total ); ?> video<?php echo $total !== 1 ? 's' : ''; ?></span>
				<?php endif; ?>
			</div>

			<!-- Search bar -->
			<div class="drtalks-archive-search-wrap">
				<div class="drtalks-archive-search-box">
					<svg class="drtalks-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
						<circle cx="8.5" cy="8.5" r="5.75" stroke="currentColor" stroke-width="1.75"/>
						<line x1="13" y1="13" x2="18" y2="18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
					</svg>
					<input
						type="search"
						id="drtalks-archive-search"
						class="drtalks-archive-search-input"
						placeholder="Search videos…"
						value=""
						autocomplete="off"
						spellcheck="false"
					>
					<button type="button" id="drtalks-search-clear" class="drtalks-search-clear" aria-label="Clear search" hidden>
						<svg viewBox="0 0 14 14" fill="none" width="14" height="14" aria-hidden="true">
							<line x1="1" y1="1" x2="13" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							<line x1="13" y1="1" x2="1" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
				</div>
			</div>

			<!-- Loading indicator -->
			<div id="drtalks-search-loading" class="drtalks-search-loading" hidden></div>

			<!-- Normal paginated loop -->
			<div id="drtalks-loop-wrap">
				<?php if ( ! empty( $posts ) ) : ?>
				<div class="drtalks-video-grid">
					<?php foreach ( $posts as $post_obj ) : ?>
						<?php echo drtalks_render_archive_card( (int) $post_obj->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</div>
				<nav class="drtalks-pagination">
					<?php the_posts_pagination( [ 'prev_text' => '&lsaquo; Previous', 'next_text' => 'Next &rsaquo;' ] ); ?>
				</nav>
				<?php else : ?>
				<p class="drtalks-no-results">No videos found.</p>
				<?php endif; ?>
			</div>

			<!-- AJAX search results -->
			<div id="drtalks-search-results" hidden>
				<div class="drtalks-video-grid" id="drtalks-search-grid"></div>
				<p id="drtalks-search-empty" class="drtalks-no-results" hidden>No videos matched your search.</p>
			</div>

		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function template_include_single( string $template ): string {
		if ( is_singular( 'drtalks_video' ) ) {
			// Theme override: yourtheme/drtalks-videos/single-drtalks_video.php,
			// falling back to the plugin's bundled template.
			$located = drtalks_locate_template( 'single-drtalks_video.php' );
			if ( file_exists( $located ) ) {
				return $located;
			}
		}
		return $template;
	}

	public static function template_include_archive( string $template ): string {
		if ( is_post_type_archive( 'drtalks_video' ) ) {
			// Theme override: yourtheme/drtalks-videos/archive-drtalks_video.php,
			// falling back to the plugin's bundled template.
			$located = drtalks_locate_template( 'archive-drtalks_video.php' );
			if ( file_exists( $located ) ) {
				return $located;
			}
		}
		return $template;
	}

	/**
	 * Return the deduplicated list of video slugs that should be publicly visible:
	 * all tracked slugs (individually selected + expert-owned) minus hidden ones.
	 *
	 * @return string[]
	 */
	public static function get_allowed_video_slugs(): array {
		// 1. Manually selected individual videos.
		$selected = json_decode( get_option( 'drtalks_selected_videos', '[]' ), true );
		$allowed  = is_array( $selected ) ? $selected : [];

		// 2. Videos belonging to active experts.
		$meta_all = json_decode( get_option( 'drtalks_experts_meta', '{}' ), true );
		if ( is_array( $meta_all ) ) {
			foreach ( $meta_all as $expert_data ) {
				$slugs = $expert_data['video_slugs'] ?? [];
				if ( is_array( $slugs ) ) {
					$allowed = array_merge( $allowed, $slugs );
				}
			}
		}

		// 3. Exclude hidden videos.
		$hidden = json_decode( get_option( 'drtalks_hidden_videos', '[]' ), true );
		if ( is_array( $hidden ) && ! empty( $hidden ) ) {
			$allowed = array_values( array_diff( $allowed, $hidden ) );
		}

		return array_values( array_unique( array_filter( $allowed ) ) );
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

		$allowed_slugs = self::get_allowed_video_slugs();

		if ( empty( $allowed_slugs ) ) {
			// Nothing is tracked yet — show nothing.
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		// 12 per page gives a clean 4×3 or 3×4 grid layout.
		$query->set( 'posts_per_page', 12 );

		// Restrict to posts whose _drtalks_video_slug is in the allowed list.
		$meta_query   = $query->get( 'meta_query' ) ?: [];
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
