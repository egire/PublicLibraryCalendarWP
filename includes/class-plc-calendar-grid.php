<?php
/**
 * Month-grid rendering for the [library_calendar view="grid"] display mode.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Calendar_Grid {

	/**
	 * Render a clickable month grid with prev/next navigation.
	 *
	 * @param array $atts Shortcode attributes (month, category).
	 * @return string
	 */
	public static function render( $atts ) {
		$tz    = wp_timezone();
		$first = self::resolve_month( $atts, $tz );

		$year          = (int) $first->format( 'Y' );
		$month_num     = (int) $first->format( 'n' );
		$days_in_month = (int) $first->format( 't' );
		$first_dow     = (int) $first->format( 'w' ); // 0 (Sun) – 6 (Sat).
		$start_of_week = (int) get_option( 'start_of_week' );
		$lead          = ( $first_dow - $start_of_week + 7 ) % 7;

		$month_start   = $first->format( 'Y-m-d H:i:s' );
		$month_end     = $first->modify( 'last day of this month' )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
		$events_by_day = self::events_by_day_range( $month_start, $month_end, $atts );

		// Number of trailing (next-month) cells the grid will show.
		$trailing = ( 7 - ( ( $lead + $days_in_month ) % 7 ) ) % 7;

		// Coming events that land on those visible next-month days, so they can be
		// previewed inside the grayed-out trailing cells.
		$next_events_by_day = array();
		if ( $trailing > 0 ) {
			$next_first         = $first->modify( '+1 month' )->setTime( 0, 0, 0 );
			$next_start         = $next_first->format( 'Y-m-d H:i:s' );
			$next_end           = $next_first->modify( '+' . ( $trailing - 1 ) . ' days' )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
			$next_events_by_day = self::events_by_day_range( $next_start, $next_end, $atts );
		}

		ob_start();
		echo '<div class="plc-grid-wrap">';
		self::render_nav( $first, $tz );

		echo '<table class="plc-grid" role="grid">';
		echo '<thead><tr>';
		foreach ( self::weekday_headers( $start_of_week ) as $abbr ) {
			echo '<th scope="col">' . esc_html( $abbr ) . '</th>';
		}
		echo '</tr></thead><tbody><tr>';

		// Leading cells: the tail end of the previous month, shadowed.
		$prev_days = (int) $first->modify( '-1 month' )->format( 't' );
		for ( $i = 0; $i < $lead; $i++ ) {
			$d = $prev_days - $lead + 1 + $i;
			echo '<td class="plc-grid-day plc-grid-out"><span class="plc-grid-num">' . esc_html( $d ) . '</span></td>';
		}

		$today = wp_date( 'Y-m-d' );
		$col   = $lead;

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$cell_date = sprintf( '%04d-%02d-%02d', $year, $month_num, $day );
			$is_today  = ( $cell_date === $today );

			echo '<td class="plc-grid-day' . ( $is_today ? ' plc-grid-today' : '' ) . '">';
			echo '<span class="plc-grid-num">' . esc_html( $day ) . '</span>';

			if ( ! empty( $events_by_day[ $day ] ) ) {
				echo '<ul class="plc-grid-events">';
				foreach ( $events_by_day[ $day ] as $event ) {
					echo self::render_cell_event( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</ul>';
			}

			echo '</td>';

			$col++;
			if ( 0 === $col % 7 && $day < $days_in_month ) {
				echo '</tr><tr>';
			}
		}

		// Trailing cells: the start of the next month, shadowed, previewing any
		// coming events that fall on those days.
		for ( $i = 1; $i <= $trailing; $i++ ) {
			echo '<td class="plc-grid-day plc-grid-out"><span class="plc-grid-num">' . esc_html( $i ) . '</span>';
			if ( ! empty( $next_events_by_day[ $i ] ) ) {
				echo '<ul class="plc-grid-events">';
				foreach ( $next_events_by_day[ $i ] as $event ) {
					echo self::render_cell_event( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</ul>';
			}
			echo '</td>';
		}

		echo '</tr></tbody></table>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Determine the first day of the month to display.
	 *
	 * @param array        $atts Shortcode atts.
	 * @param DateTimeZone $tz   Site timezone.
	 * @return DateTimeImmutable
	 */
	private static function resolve_month( $atts, $tz ) {
		$param = '';
		if ( ! empty( $_GET['plc_month'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$param = sanitize_text_field( wp_unslash( $_GET['plc_month'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $atts['month'] ) ) {
			$param = $atts['month'];
		}

		if ( $param && preg_match( '/^\d{4}-\d{2}$/', $param ) ) {
			$dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $param . '-01 00:00:00', $tz );
			if ( $dt ) {
				return $dt;
			}
		}

		$now = new DateTimeImmutable( 'now', $tz );
		return $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
	}

	/**
	 * Build a day-of-month => events[] map for events whose start falls within a
	 * given datetime range.
	 *
	 * @param string $range_start Range start (MySQL datetime, site local).
	 * @param string $range_end   Range end (MySQL datetime, site local).
	 * @param array  $atts        Shortcode atts.
	 * @return array
	 */
	private static function events_by_day_range( $range_start, $range_end, $atts ) {
		$month_start = $range_start;
		$month_end   = $range_end;

		$args = array(
			'post_type'      => PLC_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_key'       => '_plc_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_plc_start',
					'value'   => array( $month_start, $month_end ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => PLC_Post_Type::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $atts['category'] ) ),
				),
			);
		}

		$events = get_posts( $args );
		$map    = array();
		foreach ( $events as $event ) {
			$ts = PLC_Meta_Boxes::to_timestamp( get_post_meta( $event->ID, '_plc_start', true ) );
			if ( ! $ts ) {
				continue;
			}
			$day = (int) wp_date( 'j', $ts );
			$map[ $day ][] = $event;
		}
		return $map;
	}

	/**
	 * Render a single event link inside a day cell.
	 *
	 * @param WP_Post $event Event.
	 * @return string
	 */
	private static function render_cell_event( $event ) {
		$start_ts = PLC_Meta_Boxes::to_timestamp( get_post_meta( $event->ID, '_plc_start', true ) );
		$all_day  = (int) get_post_meta( $event->ID, '_plc_all_day', true );
		$time     = ( $start_ts && ! $all_day ) ? wp_date( get_option( 'time_format' ), $start_ts ) : '';

		$counts   = PLC_Registrations::get_counts( $event->ID );
		$reg_on   = PLC_Registrations::is_registration_open( $event->ID );
		$cls      = 'plc-grid-event';
		if ( $reg_on && $counts['is_full'] ) {
			$cls .= ( '1' === get_post_meta( $event->ID, '_plc_waitlist_enabled', true ) ) ? ' plc-grid-event-wait' : ' plc-grid-event-full';
		}

		return sprintf(
			'<li><a class="%1$s" href="%2$s">%3$s<span class="plc-grid-event-title">%4$s</span></a></li>',
			esc_attr( $cls ),
			esc_url( get_permalink( $event->ID ) ),
			$time ? '<span class="plc-grid-event-time">' . esc_html( $time ) . '</span> ' : '',
			esc_html( get_the_title( $event->ID ) )
		);
	}

	/**
	 * Render the month heading + prev/next navigation.
	 *
	 * @param DateTimeImmutable $first First day of month.
	 * @param DateTimeZone      $tz    Site timezone.
	 */
	private static function render_nav( $first, $tz ) {
		$prev = $first->modify( '-1 month' )->format( 'Y-m' );
		$next = $first->modify( '+1 month' )->format( 'Y-m' );
		$label = wp_date( 'F Y', $first->getTimestamp() );

		printf(
			'<div class="plc-grid-nav">
				<a class="plc-btn plc-btn-outline plc-grid-prev" href="%1$s" aria-label="%2$s">&laquo; %3$s</a>
				<h3 class="plc-grid-title">%4$s</h3>
				<a class="plc-btn plc-btn-outline plc-grid-next" href="%5$s" aria-label="%6$s">%7$s &raquo;</a>
			</div>',
			esc_url( add_query_arg( 'plc_month', $prev ) ),
			esc_attr__( 'Previous month', 'plc' ),
			esc_html__( 'Prev', 'plc' ),
			esc_html( $label ),
			esc_url( add_query_arg( 'plc_month', $next ) ),
			esc_attr__( 'Next month', 'plc' ),
			esc_html__( 'Next', 'plc' )
		);
	}

	/**
	 * Abbreviated weekday headers ordered by the site's start-of-week.
	 *
	 * @param int $start_of_week 0 (Sun) – 6 (Sat).
	 * @return array
	 */
	private static function weekday_headers( $start_of_week ) {
		global $wp_locale;
		$labels = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$dow      = ( $start_of_week + $i ) % 7;
			$labels[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $dow ) );
		}
		return $labels;
	}
}
