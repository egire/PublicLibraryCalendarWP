<?php
/**
 * Transactional email: registration confirmation, waitlist promotion, admin notice.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Emails {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'maybe_html_content_type' ) );
	}

	/**
	 * Flag set while we send our own HTML mail so we don't affect other plugins.
	 *
	 * @var bool
	 */
	private static $sending_html = false;

	/**
	 * @param string $type Content type.
	 * @return string
	 */
	public static function maybe_html_content_type( $type ) {
		return self::$sending_html ? 'text/html' : $type;
	}

	/**
	 * Build the From: header from settings.
	 *
	 * @return array
	 */
	private static function headers() {
		$name  = PLC_Settings::get( 'from_name' );
		$email = PLC_Settings::get( 'from_email' );
		return array( sprintf( 'From: %s <%s>', $name, $email ) );
	}

	/**
	 * Send an HTML email, restoring content type afterwards.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    HTML body (inner content; wrapped by template).
	 * @return bool
	 */
	private static function send( $to, $subject, $body ) {
		self::$sending_html = true;
		$sent = wp_mail( $to, $subject, self::wrap( $subject, $body ), self::headers() );
		self::$sending_html = false;
		return $sent;
	}

	/**
	 * Wrap inner HTML in a simple responsive shell.
	 *
	 * @param string $title Title shown in the header band.
	 * @param string $body  Inner HTML.
	 * @return string
	 */
	private static function wrap( $title, $body ) {
		$site = esc_html( get_bloginfo( 'name' ) );
		ob_start();
		?>
		<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;">
			<div style="background:#1d4ed8;color:#fff;padding:18px 24px;border-radius:8px 8px 0 0;">
				<strong style="font-size:18px;"><?php echo $site; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
			</div>
			<div style="border:1px solid #e5e7eb;border-top:0;padding:24px;border-radius:0 0 8px 8px;">
				<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<p style="color:#9ca3af;font-size:12px;text-align:center;margin-top:16px;">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Compose the shared event summary block.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	private static function event_summary( $event_id ) {
		$start    = get_post_meta( $event_id, '_plc_start', true );
		$location = get_post_meta( $event_id, '_plc_location', true );
		$when     = $start ? PLC_Meta_Boxes::format_datetime( $start ) : '';

		$rows = '<p style="margin:4px 0;"><strong>' . esc_html__( 'Event:', 'plc' ) . '</strong> ' . esc_html( get_the_title( $event_id ) ) . '</p>';
		if ( $when ) {
			$rows .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'When:', 'plc' ) . '</strong> ' . esc_html( $when ) . '</p>';
		}
		if ( $location ) {
			$rows .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Where:', 'plc' ) . '</strong> ' . esc_html( $location ) . '</p>';
		}
		return $rows;
	}

	/**
	 * Cancellation link for a registration.
	 *
	 * @param object $reg Registration row.
	 * @return string
	 */
	private static function cancel_link( $reg ) {
		return add_query_arg( 'plc_cancel', rawurlencode( $reg->token ), home_url( '/' ) );
	}

	/**
	 * Replace {merge} tags in an admin-supplied template with event/registrant data.
	 *
	 * @param string $template Template text.
	 * @param object $reg      Registration row.
	 * @return string
	 */
	private static function merge( $template, $reg ) {
		$start = get_post_meta( $reg->event_id, '_plc_start', true );
		return strtr(
			(string) $template,
			array(
				'{event}'      => get_the_title( $reg->event_id ),
				'{name}'       => $reg->name,
				'{date}'       => $start ? PLC_Meta_Boxes::format_datetime( $start ) : '',
				'{location}'   => get_post_meta( $reg->event_id, '_plc_location', true ),
				'{party_size}' => (int) $reg->party_size,
				'{site}'       => get_bloginfo( 'name' ),
			)
		);
	}

	/**
	 * Send the registration confirmation (confirmed or waitlist).
	 *
	 * @param int $registration_id Registration ID.
	 * @return bool
	 */
	public static function send_confirmation( $registration_id ) {
		$reg = PLC_Registrations::get( $registration_id );
		if ( ! $reg ) {
			return false;
		}

		$is_waitlist = ( PLC_Registrations::STATUS_WAITLIST === $reg->status );
		$cancel_url  = self::cancel_link( $reg );

		if ( $is_waitlist ) {
			$subject    = self::merge( PLC_Settings::get( 'waitlist_subject' ), $reg );
			$intro_text = self::merge( PLC_Settings::get( 'waitlist_intro' ), $reg );
		} else {
			$subject    = self::merge( PLC_Settings::get( 'confirm_subject' ), $reg );
			$intro_text = self::merge( PLC_Settings::get( 'confirm_intro' ), $reg );
		}
		$intro = '<p>' . nl2br( esc_html( $intro_text ) ) . '</p>';

		$body  = $intro;
		$body .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 16px;margin:16px 0;">' . self::event_summary( $reg->event_id );
		$body .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Party size:', 'plc' ) . '</strong> ' . (int) $reg->party_size . '</p></div>';

		if ( PLC_Registrations::STATUS_CONFIRMED === $reg->status ) {
			$body .= '<p><a href="' . esc_url( PLC_Ics::url( $reg->event_id ) ) . '" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;">' . esc_html__( 'Add to your calendar', 'plc' ) . '</a></p>';
		}

		$body .= '<p>' . esc_html__( 'Need to cancel? Use the link below so we can offer your spot to someone else.', 'plc' ) . '</p>';
		$body .= '<p><a href="' . esc_url( $cancel_url ) . '" style="display:inline-block;background:#dc2626;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;">' . esc_html__( 'Cancel my registration', 'plc' ) . '</a></p>';
		$body .= '<p style="font-size:13px;"><a href="' . esc_url( PLC_Settings::calendar_url() ) . '">' . esc_html__( 'Browse more library events', 'plc' ) . '</a></p>';

		return self::send( $reg->email, $subject, $body );
	}

	/**
	 * Notify a waitlisted registrant that they have been moved to confirmed.
	 *
	 * @param int $registration_id Registration ID.
	 * @return bool
	 */
	public static function send_promotion( $registration_id ) {
		$reg = PLC_Registrations::get( $registration_id );
		if ( ! $reg ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: event title */
			__( 'Good news — a spot opened up: %s', 'plc' ),
			get_the_title( $reg->event_id )
		);

		$body  = '<p>' . sprintf(
			/* translators: %s: registrant name */
			esc_html__( 'Hi %s, a spot has opened up and your registration is now confirmed!', 'plc' ),
			esc_html( $reg->name )
		) . '</p>';
		$body .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 16px;margin:16px 0;">' . self::event_summary( $reg->event_id ) . '</div>';
		$body .= '<p>' . esc_html__( 'If you can no longer attend, please cancel so we can offer the spot to the next person:', 'plc' ) . '</p>';
		$body .= '<p><a href="' . esc_url( self::cancel_link( $reg ) ) . '" style="display:inline-block;background:#dc2626;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;">' . esc_html__( 'Cancel my registration', 'plc' ) . '</a></p>';

		return self::send( $reg->email, $subject, $body );
	}

	/**
	 * Notify library staff of a new registration, if enabled.
	 *
	 * @param int $registration_id Registration ID.
	 * @return bool
	 */
	public static function notify_admin_new( $registration_id ) {
		if ( ! PLC_Settings::get( 'notify_admin' ) ) {
			return false;
		}
		$reg = PLC_Registrations::get( $registration_id );
		if ( ! $reg ) {
			return false;
		}

		$to = PLC_Settings::get( 'admin_notify_to' );

		$subject = sprintf(
			/* translators: 1: status, 2: event title */
			__( 'New %1$s registration: %2$s', 'plc' ),
			$reg->status,
			get_the_title( $reg->event_id )
		);

		$body  = '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px 16px;">' . self::event_summary( $reg->event_id );
		$body .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Name:', 'plc' ) . '</strong> ' . esc_html( $reg->name ) . '</p>';
		$body .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Email:', 'plc' ) . '</strong> ' . esc_html( $reg->email ) . '</p>';
		if ( $reg->phone ) {
			$body .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Phone:', 'plc' ) . '</strong> ' . esc_html( $reg->phone ) . '</p>';
		}
		$body .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Party size:', 'plc' ) . '</strong> ' . (int) $reg->party_size . '</p>';
		$body .= '<p style="margin:4px 0;"><strong>' . esc_html__( 'Status:', 'plc' ) . '</strong> ' . esc_html( ucfirst( $reg->status ) ) . '</p></div>';
		$body .= '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . PLC_Post_Type::POST_TYPE . '&page=plc-registrants&event_id=' . $reg->event_id ) ) . '">' . esc_html__( 'View all registrants', 'plc' ) . '</a></p>';

		return self::send( $to, $subject, $body );
	}
}
