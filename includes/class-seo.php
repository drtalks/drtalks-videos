<?php
/**
 * SEO / AEO output for single drtalks_video pages.
 *
 * The host site has no dedicated SEO plugin, and the CPT only supports `title`,
 * so this class is the sole source of machine-readable metadata for video pages:
 *   - Schema.org VideoObject JSON-LD (transcript, publisher, host + guests as
 *     Person actors, duration, embed URL, thumbnail) — the key signal for video
 *     rich results and answer engines (AEO).
 *   - Meta description, Open Graph and Twitter Card tags.
 *   - Canonical URL, routed through a filter so the syndication strategy
 *     (self-canonical vs. canonical to drtalks.com) can be switched centrally.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_SEO {

	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'render_head' ], 5 );
		add_filter( 'get_canonical_url', [ __CLASS__, 'filter_canonical' ], 10, 2 );
		// Feed the post's stored thumbnail to themes/queries that ask for a
		// featured image URL (the image is remote, so there's no attachment).
		add_filter( 'post_thumbnail_html', [ __CLASS__, 'filter_thumbnail_html' ], 10, 2 );
	}

	/**
	 * Output meta description, Open Graph, Twitter, and JSON-LD in <head>.
	 */
	public static function render_head(): void {
		if ( ! is_singular( 'drtalks_video' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$m           = drtalks_get_video_meta( $post_id );
		$title       = get_the_title( $post_id );
		$description = self::meta_description( $post_id, $m );
		$thumbnail   = $m['thumbnail'] ?? '';
		$permalink   = (string) get_permalink( $post_id );

		echo "\n<!-- DrTalks Videos SEO -->\n";

		if ( $description ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $description ) );
		}

		// --- Open Graph ---
		printf( "<meta property=\"og:type\" content=\"%s\">\n", 'video.other' );
		printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $title ) );
		if ( $description ) {
			printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $description ) );
		}
		printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $permalink ) );
		$site_name = get_bloginfo( 'name' );
		if ( $site_name ) {
			printf( "<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr( $site_name ) );
		}
		if ( $thumbnail ) {
			printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $thumbnail ) );
		}
		if ( ! empty( $m['embed_url'] ) ) {
			printf( "<meta property=\"og:video\" content=\"%s\">\n", esc_url( $m['embed_url'] ) );
			printf( "<meta property=\"og:video:secure_url\" content=\"%s\">\n", esc_url( $m['embed_url'] ) );
			printf( "<meta property=\"og:video:type\" content=\"%s\">\n", 'text/html' );
		}

		// --- Twitter Card ---
		printf( "<meta name=\"twitter:card\" content=\"%s\">\n", 'summary_large_image' );
		printf( "<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr( $title ) );
		if ( $description ) {
			printf( "<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr( $description ) );
		}
		if ( $thumbnail ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\">\n", esc_url( $thumbnail ) );
		}

		// --- JSON-LD VideoObject ---
		$schema = self::video_object_schema( $post_id, $m, $title, $description );
		if ( $schema ) {
			$json = wp_json_encode( $schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( $json ) {
				echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}

		echo "<!-- /DrTalks Videos SEO -->\n";
	}

	/**
	 * Build the VideoObject schema array. Filterable via `drtalks_video_schema`.
	 *
	 * @return array<string,mixed>
	 */
	private static function video_object_schema( int $post_id, array $m, string $title, string $description ): array {
		$thumbnail = $m['thumbnail'] ?? '';
		$duration  = (int) get_post_meta( $post_id, '_drtalks_duration', true );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'VideoObject',
			'name'     => $title,
			// Schema needs a non-empty description; fall back to the title.
			'description' => $description ?: $title,
		];

		// Prefer the original DrTalks publish date; fall back to the WP post date.
		$upload_date = ! empty( $m['published_at'] ) ? $m['published_at'] : get_post_time( 'c', true, $post_id );
		if ( $upload_date ) {
			$schema['uploadDate'] = $upload_date;
		}

		if ( $thumbnail ) {
			$schema['thumbnailUrl'] = [ $thumbnail ];
			$schema['image']        = $thumbnail;
		}
		if ( ! empty( $m['embed_url'] ) ) {
			$schema['embedUrl'] = $m['embed_url'];
		}
		if ( $duration > 0 ) {
			$schema['duration'] = self::iso8601_duration( $duration );
		}

		// Full transcript text — high-value for answer engines (AEO).
		if ( ! empty( $m['transcript'] ) ) {
			$transcript = self::plain_text( (string) $m['transcript'] );
			if ( $transcript !== '' ) {
				$schema['transcript'] = $transcript;
			}
		}

		// Publisher — the DrTalks network.
		$schema['publisher'] = [
			'@type' => 'Organization',
			'name'  => 'DrTalks',
			'url'   => 'https://drtalks.com',
		];

		// People — host (provider) first, then guests — as Person actors.
		$people = [];
		if ( ! empty( $m['expert_name'] ) ) {
			$people[] = self::person_node(
				$m['expert_name'],
				$m['expert_slug'] ?? '',
				$m['expert_photo'] ?? '',
				$m['expert_title'] ?? '',
				$m['expert_bio'] ?? ''
			);
		}
		if ( ! empty( $m['guests'] ) && is_array( $m['guests'] ) ) {
			foreach ( $m['guests'] as $guest ) {
				if ( empty( $guest['name'] ) ) {
					continue;
				}
				$people[] = self::person_node(
					$guest['name'],
					$guest['slug'] ?? '',
					$guest['photo_url'] ?? '',
					$guest['title'] ?? '',
					$guest['bio'] ?? ''
				);
			}
		}
		if ( $people ) {
			$schema['actor'] = $people;
		}

		/**
		 * Filter the VideoObject schema before output.
		 *
		 * @param array $schema  The schema array.
		 * @param int   $post_id The video post ID.
		 * @param array $m       drtalks_get_video_meta() output.
		 */
		return (array) apply_filters( 'drtalks_video_schema', $schema, $post_id, $m );
	}

	/**
	 * Build a schema.org Person node for an expert.
	 *
	 * @return array<string,mixed>
	 */
	private static function person_node( string $name, string $slug, string $photo, string $job_title, string $bio ): array {
		$person = [
			'@type' => 'Person',
			'name'  => $name,
		];
		if ( $slug ) {
			$person['url'] = 'https://drtalks.com/experts/' . rawurlencode( $slug );
		}
		if ( $photo ) {
			$person['image'] = $photo;
		}
		if ( $job_title ) {
			$person['jobTitle'] = $job_title;
		}
		if ( $bio ) {
			$person['description'] = self::plain_text( $bio );
		}
		return $person;
	}

	/**
	 * Derive a meta description: prefer the short description, else trim the
	 * full description to a sensible length.
	 */
	private static function meta_description( int $post_id, array $m ): string {
		$short = (string) get_post_meta( $post_id, '_short_description', true );
		if ( $short !== '' ) {
			return self::clip( wp_strip_all_tags( $short ), 160 );
		}
		$desc = wp_strip_all_tags( (string) ( $m['description'] ?? '' ) );
		$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
		return self::clip( $desc, 160 );
	}

	/**
	 * Truncate to a length on a word boundary, appending an ellipsis.
	 */
	private static function clip( string $text, int $limit ): string {
		$text = trim( $text );
		if ( $text === '' || mb_strlen( $text ) <= $limit ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $limit );
		$sp  = mb_strrpos( $cut, ' ' );
		if ( $sp !== false && $sp > 0 ) {
			$cut = mb_substr( $cut, 0, $sp );
		}
		return rtrim( $cut, ",.;:!?-" ) . '…';
	}

	/**
	 * Strip HTML to plain text, inserting spaces at block boundaries so adjacent
	 * paragraphs/list items don't run together (e.g. "Hello</p><p>World").
	 */
	private static function plain_text( string $html ): string {
		$html = preg_replace( '#</?(p|div|li|ul|ol|h[1-6]|br)[^>]*>#i', ' ', $html );
		$text = wp_strip_all_tags( (string) $html );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Convert seconds to an ISO 8601 duration (e.g. 3725 -> PT1H2M5S).
	 */
	private static function iso8601_duration( int $seconds ): string {
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		$out = 'PT';
		if ( $h ) {
			$out .= $h . 'H';
		}
		if ( $m ) {
			$out .= $m . 'M';
		}
		if ( $s || $out === 'PT' ) {
			$out .= $s . 'S';
		}
		return $out;
	}

	/**
	 * Canonical URL for video pages. Defaults to the page itself (self-canonical);
	 * route through `drtalks_video_canonical_url` to point at drtalks.com instead.
	 *
	 * @param string  $canonical_url Default canonical from WordPress.
	 * @param WP_Post $post          The post.
	 */
	public static function filter_canonical( $canonical_url, $post ) {
		if ( ! $post instanceof WP_Post || $post->post_type !== 'drtalks_video' ) {
			return $canonical_url;
		}

		$drtalks_url = '';
		$slug        = get_post_meta( $post->ID, '_drtalks_video_slug', true );
		if ( $slug ) {
			$drtalks_url = 'https://drtalks.com/videos/' . rawurlencode( $slug );
		}

		/**
		 * Filter the canonical URL for a DrTalks video page.
		 *
		 * Return $canonical_url (default) to self-canonicalize, or $drtalks_url
		 * to consolidate ranking signals onto the original drtalks.com video.
		 *
		 * @param string $canonical_url Self (host-site) permalink.
		 * @param int    $post_id       The video post ID.
		 * @param string $drtalks_url   The original drtalks.com video URL.
		 */
		return (string) apply_filters( 'drtalks_video_canonical_url', $canonical_url, $post->ID, $drtalks_url );
	}

	/**
	 * The video thumbnail is a remote URL, not a media-library attachment, so
	 * has_post_thumbnail() is false. Provide the stored thumbnail as the
	 * featured-image markup so themes/cards that call the_post_thumbnail() work.
	 *
	 * @param string $html    Existing thumbnail markup (empty for these posts).
	 * @param int    $post_id The post ID.
	 */
	public static function filter_thumbnail_html( $html, $post_id ) {
		if ( $html !== '' || get_post_type( $post_id ) !== 'drtalks_video' ) {
			return $html;
		}
		$thumb = get_post_meta( $post_id, '_drtalks_thumbnail', true );
		if ( ! $thumb ) {
			return $html;
		}
		return sprintf(
			'<img src="%s" alt="%s" class="attachment-post-thumbnail wp-post-image drtalks-remote-thumbnail" loading="lazy">',
			esc_url( $thumb ),
			esc_attr( get_the_title( $post_id ) )
		);
	}
}
