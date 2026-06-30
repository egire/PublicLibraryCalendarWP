<?php
/**
 * iCalendar (.ics) export for individual events — the "Add to calendar" button.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Ics {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_output' ) );
	}

	/**
	 * Download URL for an event's .ics file.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	public static function url( $event_id ) {
		return add_query_arg( 'plc_ics', $event_id, home_url( '/' ) );
	}

	/**
	 * Stream the .ics file when ?plc_ics=ID is requested.
	 */
	public static function maybe_output() {
		if ( empty( $_GET['plc_ics'] ) ) {
			return;
		}
		$event_id = absint( $_GET['plc_ics'] );
		$event    = get_post( $event_id );

		if ( ! $event || PLC_Post_Type::POST_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			status_header( 404 );
			exit;
		}

		$ics = self::generate( $event_id );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="event-' . $event_id . '.ics"' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text calendar payload.
		exit;
	}

	/**
	 * Build the VCALENDAR/VEVENT payload for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	public static function generate( $event_id ) {
		$start_ts = PLC_Meta_Boxes::to_timestamp( get_post_meta( $event_id, '_plc_start', true ) );
		$end_ts   = PLC_Meta_Boxes::to_timestamp( get_post_meta( $event_id, '_plc_end', true ) );
		if ( ! $end_ts ) {
			$end_ts = $start_ts ? $start_ts + HOUR_IN_SECONDS : 0;
		}
		$all_day  = (int) get_post_meta( $event_id, '_plc_all_day', true );
		$location = get_post_meta( $event_id, '_plc_location', true );

		$summary     = get_the_title( $event_id );
		$permalink   = get_permalink( $event_id );
		$description = wp_strip_all_tags( get_the_excerpt( $event_id ) );
		if ( $permalink ) {
			$description = trim( $description . "\n\n" . $permalink );
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$uid  = 'plc-' . $event_id . '@' . ( $host ? $host : 'library.local' );

		$lines   = array();
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Public Library Calendar//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $uid;
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );

		if ( $all_day && $start_ts ) {
			// All-day events use local DATE values, with DTEND as the day after.
			$lines[] = 'DTSTART;VALUE=DATE:' . wp_date( 'Ymd', $start_ts );
			$end_day = $end_ts ? $end_ts : $start_ts;
			$lines[] = 'DTEND;VALUE=DATE:' . wp_date( 'Ymd', $end_day + DAY_IN_SECONDS );
		} elseif ( $start_ts ) {
			$lines[] = 'DTSTART:' . gmdate( 'Ymd\THis\Z', $start_ts );
			$lines[] = 'DTEND:' . gmdate( 'Ymd\THis\Z', $end_ts );
		}

		$lines[] = 'SUMMARY:' . self::escape( $summary );
		if ( $location ) {
			$lines[] = 'LOCATION:' . self::escape( $location );
		}
		if ( $description ) {
			$lines[] = 'DESCRIPTION:' . self::escape( $description );
		}
		if ( $permalink ) {
			$lines[] = 'URL:' . self::escape( $permalink );
		}
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		// Fold long lines per RFC 5545 and join with CRLF.
		$folded = array_map( array( __CLASS__, 'fold' ), $lines );
		return implode( "\r\n", $folded ) . "\r\n";
	}

	/**
	 * Escape a text value for iCalendar (RFC 5545 §3.3.11).
	 *
	 * @param string $text Raw value.
	 * @return string
	 */
	private static function escape( $text ) {
		$text = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $text );
		$text = str_replace( array( "\r\n", "\n", "\r" ), '\\n', $text );
		return $text;
	}

	/**
	 * Fold a content line to 75 octets with CRLF + space continuation.
	 *
	 * @param string $line Content line.
	 * @return string
	 */
	private static function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out   = '';
		$chunk = '';
		$len   = strlen( $line );
		for ( $i = 0; $i < $len; $i++ ) {
			$chunk .= $line[ $i ];
			if ( 75 === strlen( $chunk ) ) {
				$out  .= $chunk . "\r\n ";
				$chunk = '';
			}
		}
		return $out . $chunk;
	}
}
