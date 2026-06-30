<?php
/**
 * Event detail meta box: date/time, location, capacity, registration settings.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Meta_Boxes {

	const NONCE_ACTION = 'plc_save_event_meta';
	const NONCE_NAME   = 'plc_event_meta_nonce';

	/**
	 * Meta keys this box manages.
	 *
	 * @var array
	 */
	private static $fields = array(
		'_plc_start',
		'_plc_end',
		'_plc_all_day',
		'_plc_location',
		'_plc_capacity',
		'_plc_registration_enabled',
		'_plc_waitlist_enabled',
		'_plc_registration_deadline',
	);

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post_' . PLC_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta box.
	 */
	public static function add_box() {
		add_meta_box(
			'plc_event_details',
			__( 'Event Details', 'plc' ),
			array( __CLASS__, 'render' ),
			PLC_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box form fields.
	 *
	 * @param WP_Post $post Current event.
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$start     = get_post_meta( $post->ID, '_plc_start', true );
		$end       = get_post_meta( $post->ID, '_plc_end', true );
		$all_day   = (int) get_post_meta( $post->ID, '_plc_all_day', true );
		$location  = get_post_meta( $post->ID, '_plc_location', true );
		$capacity  = get_post_meta( $post->ID, '_plc_capacity', true );
		$reg_on    = get_post_meta( $post->ID, '_plc_registration_enabled', true );
		$wl_on     = get_post_meta( $post->ID, '_plc_waitlist_enabled', true );
		$deadline  = get_post_meta( $post->ID, '_plc_registration_deadline', true );

		// Pre-fill brand-new events from the configured defaults.
		if ( 'auto-draft' === $post->post_status ) {
			if ( '' === $reg_on ) {
				$reg_on = PLC_Settings::get( 'default_registration' ) ? '1' : '0';
			}
			if ( '' === $wl_on ) {
				$wl_on = PLC_Settings::get( 'default_waitlist' ) ? '1' : '0';
			}
			if ( '' === $capacity ) {
				$capacity = (string) (int) PLC_Settings::get( 'default_capacity' );
			}
		}
		?>
		<div class="plc-metabox">
			<p class="plc-field">
				<label for="plc_start_date"><strong><?php esc_html_e( 'Start date &amp; time', 'plc' ); ?></strong></label><br>
				<input type="date" id="plc_start_date" name="plc_start_date" value="<?php echo esc_attr( self::to_date_value( $start ) ); ?>">
				<select id="plc_start_time" name="plc_start_time" class="plc-time-select" aria-label="<?php esc_attr_e( 'Start time', 'plc' ); ?>">
					<?php echo self::time_options( self::to_time_value( $start ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</p>
			<p class="plc-field">
				<label for="plc_end_date"><strong><?php esc_html_e( 'End date &amp; time', 'plc' ); ?></strong></label><br>
				<input type="date" id="plc_end_date" name="plc_end_date" value="<?php echo esc_attr( self::to_date_value( $end ) ); ?>">
				<select id="plc_end_time" name="plc_end_time" class="plc-time-select" aria-label="<?php esc_attr_e( 'End time', 'plc' ); ?>">
					<?php echo self::time_options( self::to_time_value( $end ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</p>
			<p class="plc-field">
				<label>
					<input type="checkbox" name="plc_all_day" value="1" <?php checked( $all_day, 1 ); ?>>
					<?php esc_html_e( 'All-day event', 'plc' ); ?>
				</label>
			</p>
			<p class="plc-field">
				<label for="plc_location"><strong><?php esc_html_e( 'Location', 'plc' ); ?></strong></label><br>
				<input type="text" id="plc_location" name="plc_location" class="widefat" value="<?php echo esc_attr( $location ); ?>" placeholder="<?php esc_attr_e( 'e.g. Main Branch — Community Room', 'plc' ); ?>">
			</p>

			<hr>

			<p class="plc-field">
				<label>
					<input type="checkbox" name="plc_registration_enabled" value="1" <?php checked( $reg_on, '1' ); ?>>
					<strong><?php esc_html_e( 'Enable public registration for this event', 'plc' ); ?></strong>
				</label>
			</p>
			<p class="plc-field">
				<label for="plc_capacity"><strong><?php esc_html_e( 'Capacity (total seats)', 'plc' ); ?></strong></label><br>
				<input type="number" id="plc_capacity" name="plc_capacity" min="0" step="1" value="<?php echo esc_attr( $capacity ); ?>">
				<span class="description"><?php esc_html_e( '0 = unlimited', 'plc' ); ?></span>
			</p>
			<p class="plc-field">
				<label>
					<input type="checkbox" name="plc_waitlist_enabled" value="1" <?php checked( $wl_on, '1' ); ?>>
					<?php esc_html_e( 'Allow a waitlist when the event is full', 'plc' ); ?>
				</label>
			</p>
			<p class="plc-field">
				<label for="plc_deadline_date"><strong><?php esc_html_e( 'Registration closes', 'plc' ); ?></strong></label><br>
				<input type="date" id="plc_deadline_date" name="plc_deadline_date" value="<?php echo esc_attr( self::to_date_value( $deadline ) ); ?>">
				<select id="plc_deadline_time" name="plc_deadline_time" class="plc-time-select" aria-label="<?php esc_attr_e( 'Registration close time', 'plc' ); ?>">
					<?php echo self::time_options( self::to_time_value( $deadline ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
				<br><span class="description"><?php esc_html_e( 'Optional. Defaults to event start time.', 'plc' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Save meta box fields.
	 *
	 * @param int     $post_id Event ID.
	 * @param WP_Post $post    Event object.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Datetimes: combine the separate date + time inputs into Y-m-d H:i:s.
		update_post_meta( $post_id, '_plc_start', self::combine_datetime( $_POST['plc_start_date'] ?? '', $_POST['plc_start_time'] ?? '' ) );
		update_post_meta( $post_id, '_plc_end', self::combine_datetime( $_POST['plc_end_date'] ?? '', $_POST['plc_end_time'] ?? '' ) );
		update_post_meta( $post_id, '_plc_registration_deadline', self::combine_datetime( $_POST['plc_deadline_date'] ?? '', $_POST['plc_deadline_time'] ?? '' ) );

		update_post_meta( $post_id, '_plc_all_day', isset( $_POST['plc_all_day'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_plc_location', sanitize_text_field( wp_unslash( $_POST['plc_location'] ?? '' ) ) );
		update_post_meta( $post_id, '_plc_capacity', max( 0, absint( $_POST['plc_capacity'] ?? 0 ) ) );
		update_post_meta( $post_id, '_plc_registration_enabled', isset( $_POST['plc_registration_enabled'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_plc_waitlist_enabled', isset( $_POST['plc_waitlist_enabled'] ) ? '1' : '0' );
	}

	/**
	 * Combine separate date and time inputs into a MySQL datetime string.
	 * A missing time defaults to midnight; a missing date yields an empty value.
	 *
	 * @param string $date Raw date input (Y-m-d).
	 * @param string $time Raw time input (H:i).
	 * @return string Y-m-d H:i:s or empty string.
	 */
	private static function combine_datetime( $date, $time ) {
		$date = sanitize_text_field( wp_unslash( $date ) );
		if ( '' === $date ) {
			return '';
		}
		$time = sanitize_text_field( wp_unslash( $time ) );
		if ( '' === $time ) {
			$time = '00:00';
		}
		$ts = strtotime( $date . ' ' . $time );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}

	/**
	 * Date portion (Y-m-d) of a stored datetime, for a date input.
	 *
	 * @param string $stored MySQL datetime.
	 * @return string
	 */
	private static function to_date_value( $stored ) {
		if ( empty( $stored ) ) {
			return '';
		}
		$ts = strtotime( $stored );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	/**
	 * Time portion (H:i) of a stored datetime, for a time input.
	 *
	 * @param string $stored MySQL datetime.
	 * @return string
	 */
	private static function to_time_value( $stored ) {
		if ( empty( $stored ) ) {
			return '';
		}
		$ts = strtotime( $stored );
		return $ts ? gmdate( 'H:i', $ts ) : '';
	}

	/**
	 * Build <option> tags for a time-of-day dropdown, in 15-minute increments.
	 * A previously-saved time that doesn't fall on a 15-minute boundary is kept
	 * as its own selected option so editing never loses it.
	 *
	 * @param string $selected Currently selected time as H:i (24-hour), or ''.
	 * @return string HTML <option> list.
	 */
	public static function time_options( $selected ) {
		$selected = (string) $selected;
		$step     = (int) apply_filters( 'plc_time_picker_step', 15 );
		$step     = ( $step > 0 && $step <= 60 ) ? $step : 15;

		$options = '<option value="">' . esc_html__( '—', 'plc' ) . '</option>';

		$found = false;
		for ( $m = 0; $m < 24 * 60; $m += $step ) {
			$value    = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
			$is_sel   = ( $value === $selected );
			$found    = $found || $is_sel;
			$options .= '<option value="' . esc_attr( $value ) . '"' . selected( $is_sel, true, false ) . '>'
				. esc_html( self::time_label( intdiv( $m, 60 ), $m % 60 ) ) . '</option>';
		}

		// Preserve an off-grid saved time (e.g. 6:50) by injecting it.
		if ( '' !== $selected && ! $found && preg_match( '/^(\d{1,2}):(\d{2})$/', $selected, $mm ) ) {
			$options = '<option value="' . esc_attr( $selected ) . '" selected>'
				. esc_html( self::time_label( (int) $mm[1], (int) $mm[2] ) ) . '</option>' . $options;
		}

		return $options;
	}

	/**
	 * Format a 24-hour time as a 12-hour label, e.g. "6:30 PM".
	 *
	 * @param int $hour   0–23.
	 * @param int $minute 0–59.
	 * @return string
	 */
	private static function time_label( $hour, $minute ) {
		$ampm = $hour < 12 ? __( 'AM', 'plc' ) : __( 'PM', 'plc' );
		$h12  = $hour % 12;
		if ( 0 === $h12 ) {
			$h12 = 12;
		}
		return sprintf( '%d:%02d %s', $h12, $minute, $ampm );
	}

	/**
	 * Convert a stored wall-clock datetime (the library's local time) to a real
	 * UTC timestamp, so it can be formatted consistently regardless of server tz.
	 *
	 * @param string $stored MySQL datetime in the site's local time.
	 * @return int Unix timestamp, or 0 if empty/invalid.
	 */
	public static function to_timestamp( $stored ) {
		if ( empty( $stored ) ) {
			return 0;
		}
		$dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $stored, wp_timezone() );
		if ( $dt instanceof DateTimeImmutable ) {
			return $dt->getTimestamp();
		}
		$ts = strtotime( $stored );
		return $ts ? $ts : 0;
	}

	/**
	 * Human-friendly datetime for display, using the site's timezone & format.
	 *
	 * @param string $stored MySQL datetime in the site's local time.
	 * @return string
	 */
	public static function format_datetime( $stored ) {
		$ts = self::to_timestamp( $stored );
		if ( ! $ts ) {
			return '';
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return wp_date( $format, $ts );
	}
}
