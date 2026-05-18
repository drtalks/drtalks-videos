<?php
/**
 * HTTP client for the DrTalks REST API.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_API_Client {

	private string $base_url;

	public function __construct() {
		$this->base_url = rtrim( DRTALKS_API_URL, '/' );
	}

	/**
	 * Fetch a single video by slug.
	 *
	 * @return array|WP_Error
	 */
	public function get_video( string $slug ) {
		$url      = $this->base_url . '/syndication/videos/' . rawurlencode( $slug );
		$response = $this->get( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$required = [ 'slug', 'title', 'embed_url' ];
		foreach ( $required as $key ) {
			if ( empty( $response[ $key ] ) ) {
				return new WP_Error( 'invalid_response', "Missing field: $key" );
			}
		}

		return $this->sanitize_video( $response );
	}

	/**
	 * Search videos.
	 *
	 * @return array|WP_Error  Shape: { videos: [], total, total_pages, page, per_page, has_more }
	 */
	public function search_videos( string $q = '', int $per_page = 20, int $page = 1 ) {
		$url = add_query_arg( [
			'q'        => $q,
			'per_page' => $per_page,
			'page'     => $page,
		], $this->base_url . '/syndication/videos/search' );

		return $this->get( $url );
	}

	/**
	 * Fetch a single expert by slug.
	 *
	 * @return array|WP_Error  Shape: { slug, name, photo_url, video_count, ... }
	 */
	public function get_expert( string $slug ) {
		$url = $this->base_url . '/syndication/experts/' . rawurlencode( $slug );
		return $this->get( $url );
	}

	/**
	 * Search experts.
	 *
	 * @return array|WP_Error  Shape: { experts: [] }
	 */
	public function search_experts( string $q = '', int $per_page = 20 ) {
		$url = add_query_arg( [
			'q'        => $q,
			'per_page' => $per_page,
		], $this->base_url . '/syndication/experts/search' );

		return $this->get( $url );
	}

	/**
	 * Get paginated videos for an expert.
	 *
	 * @return array|WP_Error  Shape: { videos: [], total, page, per_page, has_more }
	 */
	public function get_expert_videos( string $expert_slug, int $page = 1, int $per_page = 20 ) {
		$url = add_query_arg( [
			'page'     => $page,
			'per_page' => $per_page,
		], $this->base_url . '/syndication/experts/' . rawurlencode( $expert_slug ) . '/videos' );

		return $this->get( $url );
	}

	/**
	 * Perform a GET request and decode JSON.
	 *
	 * @return array|WP_Error
	 */
	private function get( string $url ) {
		DrTalks_Debug::info( 'API request', [ 'url' => $url ] );

		$start    = microtime( true );
		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json',
			],
		] );
		$elapsed = microtime( true ) - $start;

		DrTalks_Debug::log_api( $url, $response, $elapsed );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$body   = wp_remote_retrieve_body( $response );
			$json   = json_decode( $body, true );
			$detail = isset( $json['message'] ) ? $json['message'] : wp_remote_retrieve_response_message( $response );
			$error  = new WP_Error( 'api_error', "DrTalks API HTTP $code: $detail (URL: $url)" );
			DrTalks_Debug::error( 'API non-200', [ 'url' => $url, 'status' => $code, 'detail' => $detail ] );
			return $error;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			DrTalks_Debug::error( 'API parse error', [ 'url' => $url, 'body_preview' => substr( $body, 0, 200 ) ] );
			return new WP_Error( 'parse_error', 'DrTalks API response could not be parsed' );
		}

		return $data;
	}

	/**
	 * Sanitize a raw video API response before any storage.
	 */
	private function sanitize_video( array $v ): array {
		$allowed_transcript_tags = [
			'p'      => [],
			'strong' => [],
			'em'     => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'br'     => [],
			'a'      => [ 'href' => [], 'title' => [] ],
		];

		$expert = is_array( $v['expert'] ) ? $v['expert'] : [];

		return [
			'_drtalks_video_slug'       => sanitize_title( $v['slug'] ?? '' ),
			'_drtalks_embed_url'        => esc_url_raw( $v['embed_url'] ?? '' ),
			'_drtalks_thumbnail'        => esc_url_raw( $v['thumbnail_url'] ?? '' ),
			'_drtalks_description'      => wp_kses_post( $v['description'] ?? '' ),
			'_drtalks_transcript'       => wp_kses( $v['transcript'] ?? '', $allowed_transcript_tags ),
			'_drtalks_duration'         => absint( $v['duration'] ?? 0 ),
			'_drtalks_synced_at'        => absint( time() ),
			'_drtalks_expert_slug'      => sanitize_title( $expert['slug'] ?? '' ),
			'_drtalks_expert_name'      => sanitize_text_field( $expert['name'] ?? '' ),
			'_drtalks_expert_photo'     => esc_url_raw( $expert['photo_url'] ?? '' ),
			'_drtalks_expert_bio'       => sanitize_textarea_field( $expert['bio'] ?? '' ),
			'_drtalks_expert_credentials' => sanitize_text_field( $expert['credentials'] ?? '' ),
			'_drtalks_expert_title'     => sanitize_text_field( $expert['professional_title'] ?? '' ),
			'_title'                    => sanitize_text_field( $v['title'] ?? '' ),
			'_short_description'        => sanitize_text_field( $v['short_description'] ?? '' ),
		];
	}
}
