<?php
/**
 * Registration data layer: custom table, capacity logic, and waitlist handling.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Registrations {

	const STATUS_CONFIRMED = 'confirmed';
	const STATUS_WAITLIST  = 'waitlist';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'plc_registrations';
	}

	/**
	 * Create or upgrade the registrations table.
	 */
	public static function install_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(200) NOT NULL,
			email VARCHAR(200) NOT NULL,
			phone VARCHAR(50) NOT NULL DEFAULT '',
			party_size SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			token VARCHAR(64) NOT NULL DEFAULT '',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY email (email),
			KEY token (token),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'plc_db_version', PLC_DB_VERSION );
	}

	/**
	 * Aggregate seat counts for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return array{capacity:int,confirmed_seats:int,waitlist_seats:int,confirmed_rows:int,waitlist_rows:int,spots_left:int,is_full:bool,unlimited:bool}
	 */
	public static function get_counts( $event_id ) {
		global $wpdb;
		$table = self::table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$confirmed_seats = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(party_size),0) FROM {$table} WHERE event_id = %d AND status = %s", $event_id, self::STATUS_CONFIRMED ) );
		$waitlist_seats  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(party_size),0) FROM {$table} WHERE event_id = %d AND status = %s", $event_id, self::STATUS_WAITLIST ) );
		$confirmed_rows  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = %s", $event_id, self::STATUS_CONFIRMED ) );
		$waitlist_rows   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = %s", $event_id, self::STATUS_WAITLIST ) );
		// phpcs:enable

		$capacity  = (int) get_post_meta( $event_id, '_plc_capacity', true );
		$unlimited = ( $capacity <= 0 );
		$spots     = $unlimited ? PHP_INT_MAX : max( 0, $capacity - $confirmed_seats );

		return array(
			'capacity'        => $capacity,
			'confirmed_seats' => $confirmed_seats,
			'waitlist_seats'  => $waitlist_seats,
			'confirmed_rows'  => $confirmed_rows,
			'waitlist_rows'   => $waitlist_rows,
			'spots_left'      => $unlimited ? -1 : $spots,
			'is_full'         => ( ! $unlimited && $spots <= 0 ),
			'unlimited'       => $unlimited,
		);
	}

	/**
	 * Whether registration is currently open for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return bool
	 */
	public static function is_registration_open( $event_id ) {
		if ( '1' !== get_post_meta( $event_id, '_plc_registration_enabled', true ) ) {
			return false;
		}

		$deadline = get_post_meta( $event_id, '_plc_registration_deadline', true );
		if ( empty( $deadline ) ) {
			$deadline = get_post_meta( $event_id, '_plc_start', true );
		}
		if ( ! empty( $deadline ) ) {
			$deadline_ts = PLC_Meta_Boxes::to_timestamp( $deadline );
			if ( $deadline_ts && $deadline_ts < time() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Register a party for an event. Decides confirmed vs waitlist by capacity.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $data     name, email, phone, party_size, notes.
	 * @return array|WP_Error On success: array with id, status, token.
	 */
	public static function register( $event_id, $data ) {
		global $wpdb;

		$event = get_post( $event_id );
		if ( ! $event || PLC_Post_Type::POST_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return new WP_Error( 'invalid_event', __( 'This event is not available for registration.', 'plc' ) );
		}
		if ( ! self::is_registration_open( $event_id ) ) {
			return new WP_Error( 'registration_closed', __( 'Registration for this event is closed.', 'plc' ) );
		}

		$name       = sanitize_text_field( $data['name'] ?? '' );
		$email      = sanitize_email( $data['email'] ?? '' );
		$phone      = sanitize_text_field( $data['phone'] ?? '' );
		$party_size = max( 1, absint( $data['party_size'] ?? 1 ) );
		$notes      = sanitize_textarea_field( $data['notes'] ?? '' );

		if ( '' === $name ) {
			return new WP_Error( 'missing_name', __( 'Please enter your name.', 'plc' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'plc' ) );
		}
		if ( PLC_Settings::get( 'collect_phone' ) && PLC_Settings::get( 'require_phone' ) && '' === $phone ) {
			return new WP_Error( 'missing_phone', __( 'Please enter a phone number.', 'plc' ) );
		}
		$max_party = (int) PLC_Settings::get( 'max_party_size' );
		if ( $party_size > $max_party ) {
			return new WP_Error(
				'party_too_large',
				sprintf(
					/* translators: %d: maximum party size */
					__( 'Please contact the library to register a group larger than %d.', 'plc' ),
					$max_party
				)
			);
		}

		// Prevent duplicate active registrations for the same email.
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_id = %d AND email = %s AND status IN (%s, %s)", $event_id, $email, self::STATUS_CONFIRMED, self::STATUS_WAITLIST ) );
		if ( $existing ) {
			return new WP_Error( 'already_registered', __( 'This email is already registered for this event.', 'plc' ) );
		}

		// Decide status by capacity.
		$counts = self::get_counts( $event_id );
		if ( $counts['unlimited'] || $party_size <= $counts['spots_left'] ) {
			$status = self::STATUS_CONFIRMED;
		} elseif ( '1' === get_post_meta( $event_id, '_plc_waitlist_enabled', true ) ) {
			$status = self::STATUS_WAITLIST;
		} else {
			return new WP_Error( 'event_full', __( 'Sorry, this event is full.', 'plc' ) );
		}

		$token  = wp_generate_password( 32, false );
		$result = $wpdb->insert(
			$table,
			array(
				'event_id'   => $event_id,
				'name'       => $name,
				'email'      => $email,
				'phone'      => $phone,
				'party_size' => $party_size,
				'status'     => $status,
				'token'      => $token,
				'notes'      => $notes,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not save your registration. Please try again.', 'plc' ) );
		}

		$registration_id = (int) $wpdb->insert_id;

		PLC_Emails::send_confirmation( $registration_id );
		PLC_Emails::notify_admin_new( $registration_id );

		return array(
			'id'     => $registration_id,
			'status' => $status,
			'token'  => $token,
		);
	}

	/**
	 * Fetch a registration row by ID.
	 *
	 * @param int $id Registration ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Fetch a registration by its cancellation token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );
	}

	/**
	 * All registrations for an event, optionally filtered by status.
	 *
	 * @param int         $event_id Event ID.
	 * @param string|null $status   Optional status filter.
	 * @return array
	 */
	public static function get_for_event( $event_id, $status = null ) {
		global $wpdb;
		$table = self::table();
		if ( $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d AND status = %s ORDER BY created_at ASC", $event_id, $status ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d ORDER BY FIELD(status,'confirmed','waitlist','cancelled'), created_at ASC", $event_id ) );
	}

	/**
	 * All registrations across every event, ordered for a combined export.
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY event_id ASC, FIELD(status,'confirmed','waitlist','cancelled'), created_at ASC" );
	}

	/**
	 * Cancel a registration and promote waitlisted parties if room opens up.
	 *
	 * @param int $id Registration ID.
	 * @return bool|WP_Error
	 */
	public static function cancel( $id ) {
		global $wpdb;
		$reg = self::get( $id );
		if ( ! $reg ) {
			return new WP_Error( 'not_found', __( 'Registration not found.', 'plc' ) );
		}
		if ( self::STATUS_CANCELLED === $reg->status ) {
			return true;
		}

		$was_confirmed = ( self::STATUS_CONFIRMED === $reg->status );

		$wpdb->update(
			self::table(),
			array( 'status' => self::STATUS_CANCELLED ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $was_confirmed ) {
			self::promote_waitlist( (int) $reg->event_id );
		}

		return true;
	}

	/**
	 * Promote waitlisted parties (oldest first) into freed capacity.
	 *
	 * @param int $event_id Event ID.
	 * @return int Number of registrations promoted.
	 */
	public static function promote_waitlist( $event_id ) {
		$counts = self::get_counts( $event_id );
		if ( $counts['unlimited'] || $counts['spots_left'] <= 0 ) {
			return 0;
		}

		$spots    = $counts['spots_left'];
		$waitlist = self::get_for_event( $event_id, self::STATUS_WAITLIST );
		$promoted = 0;

		global $wpdb;
		foreach ( $waitlist as $reg ) {
			if ( (int) $reg->party_size > $spots ) {
				continue; // Skip parties that don't fit; keep order fair for smaller ones.
			}
			$wpdb->update(
				self::table(),
				array( 'status' => self::STATUS_CONFIRMED ),
				array( 'id' => $reg->id ),
				array( '%s' ),
				array( '%d' )
			);
			$spots -= (int) $reg->party_size;
			$promoted++;
			PLC_Emails::send_promotion( (int) $reg->id );

			if ( $spots <= 0 ) {
				break;
			}
		}

		return $promoted;
	}

	/**
	 * Delete all registrations for an event (used when an event is permanently deleted).
	 *
	 * @param int $event_id Event ID.
	 */
	public static function delete_for_event( $event_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'event_id' => $event_id ), array( '%d' ) );
	}
}
