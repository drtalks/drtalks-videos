<?php
/**
 * Divi theme compatibility.
 *
 * On Divi sites that use the Theme Builder for the global header/footer, Divi's
 * "Critical CSS" performance feature splits each page's stylesheet into an inline
 * critical (above-the-fold) chunk and a deferred file for the rest. On the
 * plugin's drtalks_video pages — whose body is rendered by the plugin's own
 * templates, not Divi modules — Divi's critical/deferred generator misfires and
 * writes an EMPTY deferred file. The Theme Builder footer (and, before the cache
 * is rebuilt, the header) lives in that deferred chunk, so it renders unstyled.
 *
 * Fix: on drtalks_video single/archive pages only, disable Divi's Critical CSS
 * so it loads the full header + footer CSS up front instead of deferring it.
 * Dynamic CSS stays ON, so the Theme Builder header/footer CSS is still
 * generated. Every other page keeps Divi's performance features untouched.
 *
 * NOTE: after deploying this, clear Divi's static CSS cache once
 * (Divi → Support Center → "Clear cache", or Theme Options → Builder →
 * Advanced → Static CSS File Generation → Clear) so the broken cached files
 * for the custom-post-type context get regenerated.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Divi_Compat {

	public static function init(): void {
		// Run after the main query is parsed (so conditional tags work) but before
		// Divi decides how to split/enqueue its CSS.
		add_action( 'wp', [ __CLASS__, 'maybe_disable_critical_css' ], 1 );
	}

	public static function maybe_disable_critical_css(): void {
		if ( ! self::is_divi_active() || ! self::is_plugin_page() ) {
			return;
		}

		// Turn off Divi's per-page dynamic-asset generation for these pages. This is
		// the lever Divi's own CriticalCSS::enable_builder() checks to cleanly
		// short-circuit the critical/deferred split (avoiding the empty deferred
		// footer/header file AND the "Undefined array key 'deferred'" warnings the
		// half-disabled critical-CSS path throws). Divi falls back to loading the
		// full static feature CSS, so the Theme Builder header + footer style fully.
		add_filter( 'et_should_generate_dynamic_assets', '__return_false' );
		// Avoid serving a stale per-post feature cache for this context.
		add_filter( 'et_builder_post_feature_cache_enabled', '__return_false' );
	}

	private static function is_plugin_page(): bool {
		return is_singular( 'drtalks_video' ) || is_post_type_archive( 'drtalks_video' );
	}

	private static function is_divi_active(): bool {
		// Divi / Extra themes define ET_BUILDER_THEME; the standalone Divi Builder
		// plugin defines ET_BUILDER_PLUGIN_VERSION.
		return defined( 'ET_BUILDER_THEME' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' );
	}
}
