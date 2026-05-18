<?php
/**
 * Server-side render for drtalks/video block.
 *
 * @var array $attributes
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug = sanitize_title( $attributes['videoSlug'] ?? '' );
if ( ! $slug ) {
	echo '<p>' . esc_html__( 'Select a DrTalks video.', 'drtalks-videos' ) . '</p>';
	return;
}

$options = [
	'show_title'       => (bool) ( $attributes['showTitle']       ?? true ),
	'show_description' => (bool) ( $attributes['showDescription'] ?? true ),
	'show_transcript'  => (bool) ( $attributes['showTranscript']  ?? true ),
	'show_author'      => (bool) ( $attributes['showAuthor']      ?? true ),
];

echo drtalks_render_block_video( $slug, $options );
