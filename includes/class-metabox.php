<?php
/**
 * Admin metabox on the drtalks_video CPT edit screen.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Metabox {

	public static function register(): void {
		add_meta_box(
			'drtalks-video-data',
			'DrTalks Video',
			[ __CLASS__, 'render' ],
			'drtalks_video',
			'normal',
			'high'
		);
	}

	public static function render( WP_Post $post ): void {
		require DRTALKS_VIDEOS_DIR . 'admin/views/metabox.php';
	}
}
