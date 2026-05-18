<?php
/**
 * Archive template for drtalks_video CPT.
 * Mirrors the single-drtalks_video.php structure exactly so the block theme's
 * header and footer render identically on the archive.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'drtalks-frontend', DRTALKS_VIDEOS_URL . 'assets/frontend.css', [], DRTALKS_VIDEOS_VERSION );
wp_enqueue_script( 'drtalks-archive', DRTALKS_VIDEOS_URL . 'assets/archive.js', [], DRTALKS_VIDEOS_VERSION, true );
wp_localize_script( 'drtalks-archive', 'drtalksArchive', [
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'nonce'   => wp_create_nonce( 'drtalks_archive_search' ),
] );

get_header();

global $wp_query;
$total = (int) $wp_query->found_posts;
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
		<?php if ( have_posts() ) : ?>
		<div class="drtalks-video-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php echo drtalks_render_archive_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endwhile; ?>
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

<?php get_footer(); ?>
