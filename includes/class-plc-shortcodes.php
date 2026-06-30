<?php
/**
 * Public display: [library_calendar] list/upcoming view, event detail block,
 * and the registration form rendered on single event pages.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Shortcodes {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_shortcode( 'library_calendar', array( __CLASS__, 'calendar' ) );
	}

	/**
	 * [library_calendar] — upcoming events list or month grid.
	 *
	 * Attributes:
	 *   view     (list|grid) display mode, default list
	 *   limit    (int)       number of events (list view), default 20
	 *   category (slug)      filter by event category
	 *   past     (yes/no)    include events that have already started (list view)
	 *   month    (YYYY-MM)   initial month (grid view)
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'     => 'list',
				'limit'    => 20,
				'category' => '',
				'past'     => 'no',
				'month'    => '',
			),
			$atts,
			'library_calendar'
		);

		wp_enqueue_style( 'plc-public' );
		wp_enqueue_script( 'plc-public' );

		if ( in_array( strtolower( $atts['view'] ), array( 'grid', 'month', 'calendar' ), true ) ) {
			return PLC_Calendar_Grid::render( $atts );
		}

		$args = array(
			'post_type'      => PLC_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['limit'],
			'meta_key'       => '_plc_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);

		if ( 'yes' !== $atts['past'] ) {
			// Floor at the start of today so events earlier today still appear.
			$args['meta_query'] = array(
				array(
					'key'     => '_plc_start',
					'value'   => wp_date( 'Y-m-d' ) . ' 00:00:00',
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			);
		}

		if ( $atts['category'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => PLC_Post_Type::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $atts['category'] ) ),
				),
			);
		}

		/**
		 * Filter the WP_Query args for the calendar list view.
		 *
		 * @param array $args WP_Query arguments.
		 * @param array $atts Shortcode attributes.
		 */
		$args = apply_filters( 'plc_calendar_query_args', $args, $atts );

		$query = new WP_Query( $args );

		ob_start();
		echo '<div class="plc-calendar">';

		if ( ! $query->have_posts() ) {
			echo '<p class="plc-empty">' . esc_html__( 'No upcoming events are scheduled right now. Please check back soon.', 'plc' ) . '</p>';
		} else {
			$today           = wp_date( 'Y-m-d' );
			$current_section = '';
			while ( $query->have_posts() ) {
				$query->the_post();
				$event_id = get_the_ID();
				$start    = get_post_meta( $event_id, '_plc_start', true );
				$ts       = PLC_Meta_Boxes::to_timestamp( $start );

				if ( $ts && wp_date( 'Y-m-d', $ts ) === $today ) {
					$section       = '__today__';
					$heading       = __( 'Today', 'plc' );
					$section_class = 'plc-month plc-section-today';
				} elseif ( $ts ) {
					$section       = wp_date( 'F Y', $ts );
					$heading       = $section;
					$section_class = 'plc-month';
				} else {
					$section       = '__tba__';
					$heading       = __( 'Date to be announced', 'plc' );
					$section_class = 'plc-month';
				}

				if ( $section !== $current_section ) {
					if ( '' !== $current_section ) {
						echo '</div>';
					}
					echo '<h3 class="' . esc_attr( $section_class ) . '">' . esc_html( $heading ) . '</h3><div class="plc-month-events">';
					$current_section = $section;
				}

				echo self::render_list_item( $event_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
		}

		echo '</div>';
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	/**
	 * Render a single event card for the calendar list.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	private static function render_list_item( $event_id ) {
		$start    = get_post_meta( $event_id, '_plc_start', true );
		$all_day  = (int) get_post_meta( $event_id, '_plc_all_day', true );
		$location = get_post_meta( $event_id, '_plc_location', true );
		$counts   = PLC_Registrations::get_counts( $event_id );
		$reg_open = PLC_Registrations::is_registration_open( $event_id );

		$start_ts = PLC_Meta_Boxes::to_timestamp( $start );
		$day  = $start_ts ? wp_date( 'j', $start_ts ) : '–';
		$dow  = $start_ts ? wp_date( 'D', $start_ts ) : '';
		$time = ( $start_ts && ! $all_day ) ? wp_date( get_option( 'time_format' ), $start_ts ) : __( 'All day', 'plc' );

		$badge = '';
		if ( $reg_open ) {
			if ( $counts['is_full'] ) {
				$badge = '1' === get_post_meta( $event_id, '_plc_waitlist_enabled', true )
					? '<span class="plc-badge plc-badge-wait">' . esc_html__( 'Waitlist', 'plc' ) . '</span>'
					: '<span class="plc-badge plc-badge-full">' . esc_html__( 'Full', 'plc' ) . '</span>';
			} elseif ( ! $counts['unlimited'] ) {
				$badge = '<span class="plc-badge plc-badge-open">' . sprintf(
					/* translators: %d: spots remaining */
					esc_html__( '%d spots left', 'plc' ),
					(int) $counts['spots_left']
				) . '</span>';
			} else {
				$badge = '<span class="plc-badge plc-badge-open">' . esc_html__( 'Registration open', 'plc' ) . '</span>';
			}
		}

		$terms = get_the_term_list( $event_id, PLC_Post_Type::TAXONOMY, '', ', ' );
		$cats  = ( $terms && ! is_wp_error( $terms ) ) ? '<span class="plc-cats">' . $terms . '</span>' : '';

		$html = PLC_Templates::get(
			'event-card.php',
			array(
				'event_id'  => $event_id,
				'permalink' => get_permalink( $event_id ),
				'title'     => get_the_title( $event_id ),
				'excerpt'   => wp_trim_words( get_the_excerpt( $event_id ), 24 ),
				'day'       => $day,
				'dow'       => $dow,
				'time'      => $time,
				'location'  => $location,
				'badge'     => $badge,
				'cats'      => $cats,
			)
		);

		/**
		 * Filter the HTML for a single event card in the calendar list.
		 *
		 * @param string $html     Card markup.
		 * @param int    $event_id Event ID.
		 */
		return apply_filters( 'plc_event_card_html', $html, $event_id );
	}

	/**
	 * Detail block shown above content on a single event page.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	public static function render_event_details( $event_id ) {
		$start    = get_post_meta( $event_id, '_plc_start', true );
		$end      = get_post_meta( $event_id, '_plc_end', true );
		$all_day  = (int) get_post_meta( $event_id, '_plc_all_day', true );
		$location = get_post_meta( $event_id, '_plc_location', true );

		$when     = '';
		$start_ts = PLC_Meta_Boxes::to_timestamp( $start );
		$end_ts   = PLC_Meta_Boxes::to_timestamp( $end );
		if ( $start_ts ) {
			if ( $all_day ) {
				$when = wp_date( get_option( 'date_format' ), $start_ts ) . ' · ' . __( 'All day', 'plc' );
			} else {
				$when = PLC_Meta_Boxes::format_datetime( $start );
				if ( $end_ts ) {
					$same_day = wp_date( 'Y-m-d', $start_ts ) === wp_date( 'Y-m-d', $end_ts );
					$when    .= ' – ' . ( $same_day ? wp_date( get_option( 'time_format' ), $end_ts ) : PLC_Meta_Boxes::format_datetime( $end ) );
				}
			}
		}

		$qr_url    = PLC_Settings::get( 'show_qr' ) ? self::qr_image_url( get_permalink( $event_id ) ) : '';
		$permalink = get_permalink( $event_id );
		$email_url = 'mailto:?subject=' . rawurlencode( get_the_title( $event_id ) )
			. '&body=' . rawurlencode( get_the_title( $event_id ) . "\n\n" . $permalink );

		$html = PLC_Templates::get(
			'event-details.php',
			array(
				'event_id'  => $event_id,
				'when'      => $when,
				'location'  => $location,
				'ics_url'   => $start_ts ? PLC_Ics::url( $event_id ) : '',
				'qr_url'    => $qr_url,
				'permalink' => $permalink,
				'flyer_url' => PLC_Flyer::url( $event_id ),
				'email_url' => $email_url,
			)
		);

		/**
		 * Filter the event detail block on single event pages.
		 *
		 * @param string $html     Detail markup.
		 * @param int    $event_id Event ID.
		 */
		return apply_filters( 'plc_event_details_html', $html, $event_id );
	}

	/**
	 * Build the QR-code image URL for a target link.
	 *
	 * Uses a free QR image service by default; loaded lazily via <img> so a slow
	 * response never blocks the page. Override with the plc_qr_image_url filter to
	 * self-host or use a different generator.
	 *
	 * @param string $target Link the QR code should encode (e.g. the event permalink).
	 * @param int    $size   Pixel size of the square image.
	 * @return string
	 */
	public static function qr_image_url( $target, $size = 200 ) {
		$url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . (int) $size . 'x' . (int) $size . '&margin=0&data=' . rawurlencode( $target );

		/**
		 * Filter the QR-code image URL.
		 *
		 * @param string $url    Image URL.
		 * @param string $target Encoded link.
		 * @param int    $size   Image size in pixels.
		 */
		return apply_filters( 'plc_qr_image_url', $url, $target, $size );
	}

	/**
	 * Registration block (status messaging + form) for a single event page.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	public static function render_registration_block( $event_id ) {
		if ( '1' !== get_post_meta( $event_id, '_plc_registration_enabled', true ) ) {
			return '';
		}

		$counts   = PLC_Registrations::get_counts( $event_id );
		$reg_open = PLC_Registrations::is_registration_open( $event_id );
		$waitlist = ( '1' === get_post_meta( $event_id, '_plc_waitlist_enabled', true ) );

		ob_start();
		echo '<div class="plc-register" id="plc-register">';
		echo '<h3>' . esc_html__( 'Register for this event', 'plc' ) . '</h3>';

		if ( ! $reg_open ) {
			echo '<p class="plc-notice plc-notice-closed">' . esc_html__( 'Registration for this event is closed.', 'plc' ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		if ( $counts['is_full'] && ! $waitlist ) {
			echo '<p class="plc-notice plc-notice-full">' . esc_html__( 'This event is full.', 'plc' ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		if ( $counts['is_full'] && $waitlist ) {
			echo '<p class="plc-notice plc-notice-wait">' . esc_html__( 'This event is full, but you can join the waitlist below. We will email you if a spot opens up.', 'plc' ) . '</p>';
		} elseif ( ! $counts['unlimited'] ) {
			echo '<p class="plc-spots">' . sprintf(
				/* translators: %d: spots remaining */
				esc_html__( '%d spots remaining.', 'plc' ),
				(int) $counts['spots_left']
			) . '</p>';
		}

		/**
		 * Fires inside the registration block, just before the form.
		 *
		 * @param int $event_id Event ID.
		 */
		do_action( 'plc_before_registration_form', $event_id );

		echo PLC_Templates::get( 'registration-form.php', array( 'event_id' => $event_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped internally.

		/**
		 * Fires inside the registration block, just after the form.
		 *
		 * @param int $event_id Event ID.
		 */
		do_action( 'plc_after_registration_form', $event_id );

		echo '</div>';

		/**
		 * Filter the entire registration block markup.
		 *
		 * @param string $html     Registration block HTML.
		 * @param int    $event_id Event ID.
		 */
		return apply_filters( 'plc_registration_block_html', (string) ob_get_clean(), $event_id );
	}
}
