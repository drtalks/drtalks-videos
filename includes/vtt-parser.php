<?php
/**
 * WebVTT caption parser — turns a .vtt file into timestamped transcript cues.
 *
 * PHP port of the parser used on drtalks.com
 * (frontend src/lib/utils/vtt-parser.ts) — keep the two in sync.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a WebVTT document into cues.
 *
 * @param  string $vtt Raw .vtt file contents.
 * @return array[]     List of [ 'start' => float, 'end' => float, 'text' => string ],
 *                     in file order (start-ascending for normal caption files).
 */
function drtalks_parse_vtt( string $vtt ): array {
	$normalized = str_replace( [ "\r\n", "\r" ], "\n", $vtt );
	$blocks     = explode( "\n\n", $normalized );
	$cues       = [];

	foreach ( $blocks as $block ) {
		$lines = array_values( array_filter( explode( "\n", $block ), static function ( $line ) {
			return trim( $line ) !== '';
		} ) );
		if ( empty( $lines ) ) {
			continue;
		}

		// Skip the WEBVTT header block and NOTE/STYLE/REGION blocks.
		if ( preg_match( '/^WEBVTT/', $lines[0] ) || preg_match( '/^(NOTE|STYLE|REGION)\b/', $lines[0] ) ) {
			continue;
		}

		// A cue may start with an optional identifier line before the timing line.
		$timing_index = -1;
		foreach ( $lines as $i => $line ) {
			if ( strpos( $line, '-->' ) !== false ) {
				$timing_index = $i;
				break;
			}
		}
		if ( $timing_index === -1 ) {
			continue;
		}

		if ( ! preg_match( '/([\d:.,]+)\s*-->\s*([\d:.,]+)/', $lines[ $timing_index ], $m ) ) {
			continue;
		}

		$start = drtalks_parse_vtt_timestamp( $m[1] );
		$end   = drtalks_parse_vtt_timestamp( $m[2] );
		if ( $start === null || $end === null ) {
			continue;
		}

		$text = implode( ' ', array_slice( $lines, $timing_index + 1 ) );
		// Strip cue markup (<v Speaker>, <i>, …) then decode entities the source
		// captions escaped their text with (order matters: decoding first could
		// reintroduce markup).
		$text = trim( wp_strip_all_tags( $text ) );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $text === '' ) {
			continue;
		}

		$cues[] = [
			'start' => $start,
			'end'   => $end,
			'text'  => $text,
		];
	}

	return $cues;
}

/**
 * "HH:MM:SS.mmm" or "MM:SS.mmm" → seconds (float), or null when malformed.
 */
function drtalks_parse_vtt_timestamp( string $raw ): ?float {
	if ( ! preg_match( '/^(?:(\d+):)?(\d{1,2}):(\d{2})[.,](\d{1,3})$/', trim( $raw ), $m ) ) {
		return null;
	}
	$hours   = $m[1] !== '' ? (int) $m[1] : 0;
	$minutes = (int) $m[2];
	$seconds = (int) $m[3];
	$millis  = (int) str_pad( $m[4], 3, '0' );

	return $hours * 3600 + $minutes * 60 + $seconds + $millis / 1000;
}
