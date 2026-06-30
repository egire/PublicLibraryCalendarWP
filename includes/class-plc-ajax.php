<?php
/**
 * AJAX + request handling for public registration and cancellation.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Ajax {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'wp_ajax_plc_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'wp_ajax_nopriv_plc_register', array( __CLASS__, 'handle_register' ) );

		// Cancellation is a GET link from the confirmation email.
		add_action( 'init', array( __CLASS__, 'maybe_handle_cancel' ) );

		// Clean up registrations when an event is permanently deleted.
		add_action( 'before_delete_post', array( __CLASS__, 'on_event_deleted' ) );
	}

	/**
	 * Handle the registration AJAX POST.
	 */
	public static function handle_register() {
		check_ajax_referer( 'plc_register', 'nonce' );

		// Honeypot: bots fill hidden fields; humans leave them blank.
		if ( ! empty( $_POST['plc_website'] ) ) {
			wp_send_json_success(
				array(
					'status'  => PLC_Registrations::STATUS_CONFIRMED,
					'message' => __( 'Thank you for registering!', 'plc' ),
				)
			);
		}

		$event_id = absint( $_POST['event_id'] ?? 0 );
		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing event.', 'plc' ) ), 400 );
		}

		$result = PLC_Registrations::register(
			$event_id,
			array(
				'name'       => wp_unslash( $_POST['name'] ?? '' ),
				'email'      => wp_unslash( $_POST['email'] ?? '' ),
				'phone'      => wp_unslash( $_POST['phone'] ?? '' ),
				'party_size' => $_POST['party_size'] ?? 1,
				'notes'      => wp_unslash( $_POST['notes'] ?? '' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$message = ( PLC_Registrations::STATUS_WAITLIST === $result['status'] )
			? __( 'This event is full — you have been added to the waitlist. We will email you if a spot opens up.', 'plc' )
			: __( 'You are registered! A confirmation email is on its way.', 'plc' );

		wp_send_json_success(
			array(
				'status'  => $result['status'],
				'message' => $message,
			)
		);
	}

	/**
	 * Handle a cancellation link click: ?plc_cancel=TOKEN.
	 */
	public static function maybe_handle_cancel() {
		if ( empty( $_GET['plc_cancel'] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['plc_cancel'] ) );
		$reg   = PLC_Registrations::get_by_token( $token );

		if ( ! $reg ) {
			self::render_notice_page( __( 'Cancellation link not recognized', 'plc' ), __( 'We could not find a registration for this link. It may have already been cancelled.', 'plc' ) );
		}

		if ( PLC_Registrations::STATUS_CANCELLED === $reg->status ) {
			self::render_notice_page( __( 'Already cancelled', 'plc' ), __( 'This registration has already been cancelled.', 'plc' ) );
		}

		PLC_Registrations::cancel( (int) $reg->id );

		$event_title = get_the_title( (int) $reg->event_id );
		self::render_notice_page(
			__( 'Registration cancelled', 'plc' ),
			sprintf(
				/* translators: %s: event title */
				__( 'Your registration for "%s" has been cancelled. Thank you for letting us know.', 'plc' ),
				$event_title
			)
		);
	}

	/**
	 * Render a minimal standalone notice page and stop.
	 *
	 * @param string $title   Heading.
	 * @param string $message Body.
	 */
	private static function render_notice_page( $title, $message ) {
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( PLC_Settings::calendar_url() ) . '">' . esc_html__( 'Browse library events', 'plc' ) . '</a></p>',
			esc_html( $title ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Remove registration rows when an event is deleted.
	 *
	 * @param int $post_id Post being deleted.
	 */
	public static function on_event_deleted( $post_id ) {
		if ( PLC_Post_Type::POST_TYPE === get_post_type( $post_id ) ) {
			PLC_Registrations::delete_for_event( $post_id );
		}
	}
}
