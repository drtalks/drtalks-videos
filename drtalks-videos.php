<?php
/**
 * Plugin Name:  DrTalks Videos
 * Plugin URI:   https://github.com/drtalks/drtalks-videos-plugin
 * Description:  Sync and embed DrTalks expert videos on any WordPress site.
 * Version:      1.1.0
 * Author:       DrTalks
 * Author URI:   https://drtalks.com
 * License:      GPL-2.0-or-later
 * Text Domain:  drtalks-videos
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DRTALKS_VIDEOS_VERSION', '1.1.0' );
define( 'DRTALKS_VIDEOS_FILE', __FILE__ );
define( 'DRTALKS_VIDEOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRTALKS_VIDEOS_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'DRTALKS_API_URL' ) ) {
	define( 'DRTALKS_API_URL', 'https://account.drtalks.com/wp-json/drtalks/v1' );
}

// Action Scheduler — must load before anything that calls as_* functions.
// The library auto-picks the newest version across all plugins that bundle it.
require_once DRTALKS_VIDEOS_DIR . 'libraries/action-scheduler/action-scheduler.php';

// Debug logger must load first — other classes reference DrTalks_Debug::log().
require_once DRTALKS_VIDEOS_DIR . 'includes/class-debug.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/functions.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/template-functions.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-post-type.php';
require_once DRTALKS_VIDEOS_DIR . 'admin/class-admin-page.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-api-client.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-sync.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-scheduler.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-ajax.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-metabox.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-block.php';
require_once DRTALKS_VIDEOS_DIR . 'includes/class-shortcode.php';

register_activation_hook( __FILE__, 'drtalks_videos_activate' );
register_deactivation_hook( __FILE__, 'drtalks_videos_deactivate' );
register_uninstall_hook( __FILE__, 'drtalks_videos_uninstall' );

function drtalks_videos_activate(): void {
	DrTalks_Post_Type::register();
	flush_rewrite_rules( false );
	if ( class_exists( 'DrTalks_Scheduler' ) ) {
		DrTalks_Scheduler::ensure_recurring_scheduled();
	}
}

function drtalks_videos_deactivate(): void {
	if ( class_exists( 'DrTalks_Scheduler' ) ) {
		DrTalks_Scheduler::unschedule_recurring();
	}
	flush_rewrite_rules( false );
}

function drtalks_videos_uninstall(): void {
	// Delete all drtalks_video CPT posts and their meta.
	$posts = get_posts( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	// Delete all transcript transients.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drtalks_transcript_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_drtalks_transcript_%'" );

	// Delete plugin options.
	delete_option( 'drtalks_archive_slug' );
	delete_option( 'drtalks_show_watch_button' );
	delete_option( 'drtalks_archive_enabled' );
	delete_option( 'drtalks_selected_videos' );
	delete_option( 'drtalks_expert_slugs' );
	delete_option( 'drtalks_experts_meta' );
	delete_option( 'drtalks_hidden_videos' );
	delete_option( 'drtalks_sync_schedule' );
	delete_option( 'drtalks_video_template_style' );
	// Legacy option from pre-rework single-expert design.
	delete_option( 'drtalks_expert_slug' );
}

// Boot all classes.
add_action( 'init', [ 'DrTalks_Post_Type', 'register' ] );
add_action( 'init', [ 'DrTalks_Post_Type', 'init_templates' ] );
add_action( 'pre_get_posts', [ 'DrTalks_Post_Type', 'filter_archive_query' ] );
add_action( 'init', [ 'DrTalks_Shortcode', 'init' ] );
add_action( 'admin_menu', [ 'DrTalks_Admin_Page', 'add_menu' ] );
add_action( 'admin_init', [ 'DrTalks_Admin_Page', 'init' ] );
add_action( 'add_meta_boxes', [ 'DrTalks_Metabox', 'register' ] );
add_action( 'init', [ 'DrTalks_Block', 'register' ] );
DrTalks_Ajax::init();
DrTalks_Debug::init_admin();
DrTalks_Scheduler::init();

// Ensure the Action Scheduler recurring action is always registered.
add_action( 'init', [ 'DrTalks_Scheduler', 'ensure_recurring_scheduled' ], 20 );

add_action( 'admin_notices', function () {
	$error = get_transient( 'drtalks_sync_error' );
	if ( $error ) {
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>DrTalks sync failed:</strong> %s <a href="%s">Retry Now</a></p></div>',
			esc_html( $error ),
			esc_url( admin_url( 'admin.php?page=drtalks-videos&drtalks_retry=1' ) )
		);
	}
} );
