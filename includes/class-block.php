<?php
/**
 * Gutenberg block registration.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Block {

	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// block.json references editorScript: file:../build/index.js.
		// If the build directory doesn't exist yet (un-built plugin), WordPress will
		// throw a fatal error when it tries to resolve the asset file. We guard
		// against this and fall back to registering the block without the editor
		// script so at least the front-end render_callback still works.
		$build_index = DRTALKS_VIDEOS_DIR . 'blocks/build/index.js';
		if ( ! file_exists( $build_index ) ) {
			DrTalks_Debug::warn( 'blocks/build/index.js missing — Gutenberg block editor UI disabled. Run `npm run build` inside the plugin\'s blocks/ directory.' );

			// Register a minimal block definition so existing block instances
			// can still render on the front end.
			register_block_type( 'drtalks/video', [
				'render_callback' => [ __CLASS__, 'render' ],
				'attributes'      => [
					'videoSlug'      => [ 'type' => 'string', 'default' => '' ],
					'videoTitle'     => [ 'type' => 'string', 'default' => '' ],
					'videoThumbnail' => [ 'type' => 'string', 'default' => '' ],
				],
			] );
			return;
		}

		register_block_type(
			DRTALKS_VIDEOS_DIR . 'blocks/src/block.json',
			[
				'render_callback' => [ __CLASS__, 'render' ],
			]
		);

		// Only enqueue block editor script when the editor is active.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_assets' ] );
	}

	public static function enqueue_editor_assets(): void {
		$build_dir = DRTALKS_VIDEOS_DIR . 'blocks/build/';
		$build_url = DRTALKS_VIDEOS_URL . 'blocks/build/';

		if ( file_exists( $build_dir . 'index.js' ) ) {
			wp_enqueue_script(
				'drtalks-block-editor',
				$build_url . 'index.js',
				[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ],
				DRTALKS_VIDEOS_VERSION,
				true
			);
			wp_localize_script( 'drtalks-block-editor', 'drtalksAdmin', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'drtalks_admin_nonce' ),
			] );
		}
	}

	public static function render( array $attributes ): string {
		$slug = sanitize_title( $attributes['videoSlug'] ?? '' );
		if ( ! $slug ) {
			return '<p>' . esc_html__( 'Select a DrTalks video.', 'drtalks-videos' ) . '</p>';
		}

		$options = [
			'show_video'       => (bool) ( $attributes['showVideo']       ?? true ),
			'show_title'       => (bool) ( $attributes['showTitle']       ?? true ),
			'show_description' => (bool) ( $attributes['showDescription'] ?? true ),
			'show_transcript'  => (bool) ( $attributes['showTranscript']  ?? true ),
			'show_author'      => (bool) ( $attributes['showAuthor']      ?? true ),
			'show_guests'      => (bool) ( $attributes['showGuests']      ?? true ),
		];

		return drtalks_render_block_video( $slug, $options );
	}
}
