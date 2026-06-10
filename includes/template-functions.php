<?php
/**
 * WooCommerce-style template loader.
 *
 * Lets a theme override the plugin's classic-PHP templates by placing copies in
 * its own "drtalks-videos/" folder. Resolution order for any template:
 *
 *   1. Child theme:  yourtheme/drtalks-videos/<file>
 *   2. Parent theme: yourtheme/drtalks-videos/<file>
 *   3. Plugin:       <plugin>/templates/<file>
 *
 * Overridable files:
 *   - single-drtalks_video.php      Full single template (classic themes)
 *   - archive-drtalks_video.php     Full archive template (classic themes)
 *   - content-archive-card.php      One video card in the archive/search grid
 *   - content-single-theme.php      Single-video body, "theme" layout
 *   - content-single-video.php      Single-video body, "video" layout
 *   - partials/expert-bio.php       Expert bio block
 *
 * Note: the card and single-content partials are shared by the shortcode, the
 * Gutenberg block, AJAX search, and the block-theme path, so overriding a
 * partial changes that markup everywhere it appears (same as WooCommerce). Only
 * the full single/archive template files are specific to the classic-PHP path.
 *
 * Filters:
 *   - drtalks_locate_template ( $template, $template_name )
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a template path, preferring a theme override over the plugin default.
 *
 * @param string $template_name Path relative to the templates dir, e.g. 'content-archive-card.php'.
 * @return string Absolute path to the template that should be loaded.
 */
function drtalks_locate_template( string $template_name ): string {
	$template = locate_template( [ 'drtalks-videos/' . $template_name ] );

	if ( ! $template ) {
		$template = DRTALKS_VIDEOS_DIR . 'templates/' . $template_name;
	}

	return (string) apply_filters( 'drtalks_locate_template', $template, $template_name );
}

/**
 * Include a template, exposing $args as local variables.
 *
 * @param string $template_name Path relative to the templates dir.
 * @param array  $args          Variables to expose to the template.
 */
function drtalks_get_template( string $template_name, array $args = [] ): void {
	$template = drtalks_locate_template( $template_name );

	if ( ! file_exists( $template ) ) {
		DrTalks_Debug::warn( "drtalks_get_template: template not found '$template_name' (resolved: $template)." );
		return;
	}

	if ( ! empty( $args ) ) {
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	}

	include $template;
}

/**
 * Render a template to a string.
 *
 * @param string $template_name Path relative to the templates dir.
 * @param array  $args          Variables to expose to the template.
 * @return string Rendered HTML.
 */
function drtalks_get_template_html( string $template_name, array $args = [] ): string {
	ob_start();
	drtalks_get_template( $template_name, $args );
	return (string) ob_get_clean();
}
