<?php
/**
 * [drtalks_video slug="..."] shortcode.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Shortcode {

	public static function init(): void {
		// Composite shortcode — the whole embed with per-section toggles.
		add_shortcode( 'drtalks_video', [ __CLASS__, 'render' ] );

		// Atomic shortcodes — each outputs one piece, for use inside theme templates.
		add_shortcode( 'drtalks_video_iframe',      [ __CLASS__, 'render_iframe' ] );
		add_shortcode( 'drtalks_video_title',       [ __CLASS__, 'render_title' ] );
		add_shortcode( 'drtalks_video_description', [ __CLASS__, 'render_description' ] );
		add_shortcode( 'drtalks_video_transcript',  [ __CLASS__, 'render_transcript' ] );
		add_shortcode( 'drtalks_expert_name',       [ __CLASS__, 'render_expert_name' ] );
		add_shortcode( 'drtalks_expert_photo',      [ __CLASS__, 'render_expert_photo' ] );
		add_shortcode( 'drtalks_expert_title',      [ __CLASS__, 'render_expert_title' ] );
		add_shortcode( 'drtalks_expert_bio',        [ __CLASS__, 'render_expert_bio' ] );

		// Register TinyMCE plugin + toolbar button.
		add_filter( 'mce_buttons', [ __CLASS__, 'add_tinymce_button' ] );
		add_filter( 'tiny_mce_before_init', [ __CLASS__, 'tinymce_init' ] );
		add_filter( 'tinymce_external_plugins', [ __CLASS__, 'tinymce_plugin' ] );
	}

	public static function render( array $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'        => '',
				'video'       => '1',
				'title'       => '1',
				'description' => '1',
				'transcript'  => '1',
				'author'      => '1',
			],
			$atts,
			'drtalks_video'
		);

		$slug = sanitize_title( $atts['slug'] );
		if ( ! $slug ) {
			return '';
		}

		$options = [
			'show_video'       => self::truthy( $atts['video'] ),
			'show_title'       => self::truthy( $atts['title'] ),
			'show_description' => self::truthy( $atts['description'] ),
			'show_transcript'  => self::truthy( $atts['transcript'] ),
			'show_author'      => self::truthy( $atts['author'] ),
		];

		return drtalks_render_block_video( $slug, $options );
	}

	// --- Atomic shortcodes ----------------------------------------------------

	public static function render_iframe( array $atts ): string {
		$atts = shortcode_atts( [ 'slug' => '' ], $atts, 'drtalks_video_iframe' );
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}

		$embed_url = get_post_meta( $post->ID, '_drtalks_embed_url', true )
			?: 'https://drtalks.com/embed/videos/' . rawurlencode( sanitize_title( $atts['slug'] ) );

		self::enqueue_style();
		return sprintf(
			'<div class="drtalks-video-player"><iframe src="%s" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>',
			esc_url( $embed_url )
		);
	}

	public static function render_title( array $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'  => '',
				'tag'   => '',     // h1..h6 / p / span / div. Empty = plain text, no wrapper.
				'class' => '',
			],
			$atts,
			'drtalks_video_title'
		);
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}

		$title = get_the_title( $post->ID );
		$tag   = strtolower( trim( $atts['tag'] ) );
		$allowed_tags = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' ];

		if ( ! in_array( $tag, $allowed_tags, true ) ) {
			return esc_html( $title );
		}

		self::enqueue_style();
		$class = trim( 'drtalks-video-title ' . sanitize_html_class( $atts['class'] ) );
		return sprintf( '<%1$s class="%3$s">%2$s</%1$s>', $tag, esc_html( $title ), esc_attr( $class ) );
	}

	public static function render_description( array $atts ): string {
		$atts = shortcode_atts( [ 'slug' => '' ], $atts, 'drtalks_video_description' );
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}

		$description = get_post_meta( $post->ID, '_drtalks_description', true );
		if ( ! $description ) {
			return '';
		}

		self::enqueue_style();
		return '<div class="drtalks-video-description">' . wp_kses( $description, self::allowed_html() ) . '</div>';
	}

	public static function render_transcript( array $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'     => '',
				'dropdown' => '0',
				'label'    => 'View Transcript',
			],
			$atts,
			'drtalks_video_transcript'
		);
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}

		$transcript = get_post_meta( $post->ID, '_drtalks_transcript', true );
		if ( ! $transcript ) {
			return '';
		}

		self::enqueue_style();
		$inner = '<div class="drtalks-transcript-content">' . wp_kses( $transcript, self::allowed_html() ) . '</div>';

		if ( self::truthy( $atts['dropdown'] ) ) {
			return sprintf(
				'<div class="drtalks-video-transcript"><details><summary>%s</summary>%s</details></div>',
				esc_html( $atts['label'] ),
				$inner
			);
		}
		return '<div class="drtalks-video-transcript">' . $inner . '</div>';
	}

	public static function render_expert_name( array $atts ): string {
		$atts = shortcode_atts( [ 'slug' => '' ], $atts, 'drtalks_expert_name' );
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}
		return esc_html( (string) get_post_meta( $post->ID, '_drtalks_expert_name', true ) );
	}

	public static function render_expert_photo( array $atts ): string {
		$atts = shortcode_atts(
			[
				'slug'  => '',
				'class' => '',
				'alt'   => '',
			],
			$atts,
			'drtalks_expert_photo'
		);
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}

		$photo_url = get_post_meta( $post->ID, '_drtalks_expert_photo', true );
		if ( ! $photo_url ) {
			return '';
		}

		$alt   = $atts['alt'] !== '' ? $atts['alt'] : (string) get_post_meta( $post->ID, '_drtalks_expert_name', true );
		$class = trim( 'drtalks-expert-photo ' . sanitize_html_class( $atts['class'] ) );

		self::enqueue_style();
		return sprintf(
			'<img src="%s" alt="%s" class="%s">',
			esc_url( $photo_url ),
			esc_attr( $alt ),
			esc_attr( $class )
		);
	}

	public static function render_expert_title( array $atts ): string {
		$atts = shortcode_atts( [ 'slug' => '' ], $atts, 'drtalks_expert_title' );
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}
		return esc_html( (string) get_post_meta( $post->ID, '_drtalks_expert_title', true ) );
	}

	public static function render_expert_bio( array $atts ): string {
		$atts = shortcode_atts( [ 'slug' => '' ], $atts, 'drtalks_expert_bio' );
		$post = self::get_post( $atts['slug'] );
		if ( ! $post ) {
			return '';
		}

		$bio = (string) get_post_meta( $post->ID, '_drtalks_expert_bio', true );
		if ( ! $bio ) {
			return '';
		}

		self::enqueue_style();
		return '<div class="drtalks-expert-bio-text">' . nl2br( esc_html( $bio ) ) . '</div>';
	}

	// --- Helpers --------------------------------------------------------------

	/**
	 * Resolve a video slug to its CPT post. Request-scoped cache so multiple
	 * atomic shortcodes on the same page share one query.
	 */
	private static function get_post( string $slug ): ?WP_Post {
		static $cache = [];
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return null;
		}
		if ( array_key_exists( $slug, $cache ) ) {
			return $cache[ $slug ];
		}

		$posts = get_posts( [
			'post_type'      => 'drtalks_video',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => [
				[ 'key' => '_drtalks_video_slug', 'value' => $slug ],
			],
		] );

		return $cache[ $slug ] = $posts[0] ?? null;
	}

	private static function truthy( $value ): bool {
		return ! in_array( strtolower( (string) $value ), [ '0', 'false', 'no', '' ], true );
	}

	private static function allowed_html(): array {
		return [
			'p'      => [],
			'strong' => [],
			'em'     => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'br'     => [],
			'a'      => [ 'href' => [], 'title' => [], 'rel' => [], 'target' => [] ],
		];
	}

	private static function enqueue_style(): void {
		wp_enqueue_style(
			'drtalks-frontend',
			DRTALKS_VIDEOS_URL . 'assets/frontend.css',
			[],
			DRTALKS_VIDEOS_VERSION
		);
	}

	public static function add_tinymce_button( array $buttons ): array {
		if ( current_user_can( 'edit_posts' ) ) {
			$buttons[] = 'drtalks_video_button';
		}

		return $buttons;
	}

	public static function tinymce_plugin( array $plugins ): array {
		if ( current_user_can( 'edit_posts' ) ) {
			$plugins['drtalks_video_button'] = DRTALKS_VIDEOS_URL . 'admin/js/admin.js';
		}

		return $plugins;
	}

	public static function tinymce_init( array $init ): array {
		// Pass ajaxUrl + nonce to TinyMCE plugin via setup callback.
		return $init;
	}
}
