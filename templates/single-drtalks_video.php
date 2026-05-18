<?php
/**
 * Single template for drtalks_video — classic themes only.
 * Block themes use single-drtalks_video.html via register_block_template().
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'drtalks-frontend', DRTALKS_VIDEOS_URL . 'assets/frontend.css', [], DRTALKS_VIDEOS_VERSION );

get_header();

while ( have_posts() ) :
	the_post();
	echo drtalks_render_single_video_content( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput
endwhile;

get_footer();
