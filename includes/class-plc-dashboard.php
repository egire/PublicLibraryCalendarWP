<?php
/**
 * WordPress Dashboard widget: quick-add an event and see what's coming up.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Dashboard {

	const NONCE_ACTION = 'plc_quick_add_event';
	const NONCE_NAME   = 'plc_quick_add_nonce';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
		add_action( 'admin_post_plc_quick_add_event', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Register the dashboard widget for users who can create events.
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'plc_quick_add_event',
			__( 'Library Calendar — Add Event', 'plc' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the quick-add form and a short list of upcoming events.
	 */
	public static function render() {
		$can_publish = current_user_can( 'publish_posts' );
		?>
		<style>
			.plc-qa-field { margin: 0 0 10px; }
			.plc-qa-field label { display: block; font-weight: 600; margin-bottom: 3px; }
			.plc-qa-field input[type="text"] { width: 100%; }
			.plc-qa-row { display: flex; gap: 10px; flex-wrap: wrap; }
			.plc-qa-row .plc-qa-field { flex: 1 1 140px; margin-bottom: 10px; }
			.plc-qa-actions { display: flex; align-items: center; gap: 12px; margin-top: 6px; }
			.plc-qa-upcoming { margin-top: 16px; border-top: 1px solid #dcdcde; padding-top: 12px; }
			.plc-qa-upcoming h4 { margin: 0 0 8px; }
			.plc-qa-upcoming ul { margin: 0; }
			.plc-qa-upcoming li { display: flex; justify-content: space-between; gap: 10px; padding: 3px 0; }
			.plc-qa-upcoming .plc-qa-when { color: #646970; white-space: nowrap; }
		</style>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="action" value="plc_quick_add_event">

			<div class="plc-qa-field">
				<label for="plc_qa_title"><?php esc_html_e( 'Event title', 'plc' ); ?></label>
				<input type="text" id="plc_qa_title" name="plc_title" required>
			</div>

			<div class="plc-qa-row">
				<div class="plc-qa-field">
					<label for="plc_qa_date"><?php esc_html_e( 'Date', 'plc' ); ?></label>
					<input type="date" id="plc_qa_date" name="plc_date" required>
				</div>
				<div class="plc-qa-field">
					<label for="plc_qa_time"><?php esc_html_e( 'Start time', 'plc' ); ?></label>
					<select id="plc_qa_time" name="plc_time">
						<?php echo PLC_Meta_Boxes::time_options( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</select>
				</div>
			</div>

			<div class="plc-qa-row">
				<div class="plc-qa-field">
					<label for="plc_qa_location"><?php esc_html_e( 'Location', 'plc' ); ?></label>
					<input type="text" id="plc_qa_location" name="plc_location">
				</div>
				<div class="plc-qa-field">
					<label for="plc_qa_capacity"><?php esc_html_e( 'Capacity', 'plc' ); ?></label>
					<input type="number" id="plc_qa_capacity" name="plc_capacity" min="0" step="1" placeholder="<?php esc_attr_e( '0 = unlimited', 'plc' ); ?>">
				</div>
			</div>

			<div class="plc-qa-actions">
				<?php if ( $can_publish ) : ?>
					<select name="plc_status">
						<option value="publish"><?php esc_html_e( 'Publish now', 'plc' ); ?></option>
						<option value="draft"><?php esc_html_e( 'Save as draft', 'plc' ); ?></option>
					</select>
				<?php else : ?>
					<input type="hidden" name="plc_status" value="draft">
					<span class="description"><?php esc_html_e( 'Saved as a draft for review.', 'plc' ); ?></span>
				<?php endif; ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Event', 'plc' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PLC_Post_Type::POST_TYPE ) ); ?>"><?php esc_html_e( 'Open full editor →', 'plc' ); ?></a>
			</div>
		</form>

		<?php self::render_upcoming(); ?>
		<?php
	}

	/**
	 * Render the next few upcoming events with edit links.
	 */
	private static function render_upcoming() {
		$events = get_posts(
			array(
				'post_type'      => PLC_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'future', 'draft' ),
				'posts_per_page' => 5,
				'meta_key'       => '_plc_start',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_plc_start',
						'value'   => current_time( 'mysql' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		echo '<div class="plc-qa-upcoming">';
		echo '<h4>' . esc_html__( 'Upcoming events', 'plc' ) . '</h4>';

		if ( empty( $events ) ) {
			echo '<p class="description">' . esc_html__( 'No upcoming events yet.', 'plc' ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( $events as $event ) {
				$when  = PLC_Meta_Boxes::format_datetime( get_post_meta( $event->ID, '_plc_start', true ) );
				$label = get_the_title( $event ) ? get_the_title( $event ) : __( '(no title)', 'plc' );
				printf(
					'<li><a href="%1$s">%2$s</a><span class="plc-qa-when">%3$s</span></li>',
					esc_url( (string) get_edit_post_link( $event->ID ) ),
					esc_html( $label ),
					esc_html( $when )
				);
			}
			echo '</ul>';
		}

		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'edit.php?post_type=' . PLC_Post_Type::POST_TYPE ) ),
			esc_html__( 'Manage all events', 'plc' )
		);
		echo '</div>';
	}

	/**
	 * Handle the quick-add submission.
	 */
	public static function handle() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to add events.', 'plc' ) );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$title = sanitize_text_field( wp_unslash( $_POST['plc_title'] ?? '' ) );
		$date  = sanitize_text_field( wp_unslash( $_POST['plc_date'] ?? '' ) );
		$time  = sanitize_text_field( wp_unslash( $_POST['plc_time'] ?? '' ) );

		if ( '' === $title || '' === $date ) {
			self::redirect( 'error' );
		}

		if ( '' === $time ) {
			$time = '00:00';
		}
		$ts    = strtotime( $date . ' ' . $time );
		$start = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
		if ( '' === $start ) {
			self::redirect( 'error' );
		}

		$status = ( 'publish' === ( $_POST['plc_status'] ?? '' ) && current_user_can( 'publish_posts' ) ) ? 'publish' : 'draft';

		$capacity = isset( $_POST['plc_capacity'] ) && '' !== $_POST['plc_capacity']
			? max( 0, absint( $_POST['plc_capacity'] ) )
			: (int) PLC_Settings::get( 'default_capacity' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => PLC_Post_Type::POST_TYPE,
				'post_status' => $status,
				'post_title'  => $title,
				'meta_input'  => array(
					'_plc_start'                 => $start,
					'_plc_location'              => sanitize_text_field( wp_unslash( $_POST['plc_location'] ?? '' ) ),
					'_plc_capacity'              => $capacity,
					'_plc_registration_enabled'  => PLC_Settings::get( 'default_registration' ) ? '1' : '0',
					'_plc_waitlist_enabled'      => PLC_Settings::get( 'default_waitlist' ) ? '1' : '0',
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::redirect( 'error' );
		}

		self::redirect( 'publish' === $status ? 'published' : 'drafted', $post_id );
	}

	/**
	 * Redirect back to the dashboard with a status flag.
	 *
	 * @param string $result  added|published|drafted|error.
	 * @param int    $post_id Created event ID, if any.
	 */
	private static function redirect( $result, $post_id = 0 ) {
		$args = array( 'plc_added' => $result );
		if ( $post_id ) {
			$args['plc_event'] = (int) $post_id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'index.php' ) ) );
		exit;
	}

	/**
	 * Show a success/error notice on the dashboard after a quick-add.
	 */
	public static function notice() {
		if ( empty( $_GET['plc_added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}

		$result   = sanitize_key( wp_unslash( $_GET['plc_added'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event_id = isset( $_GET['plc_event'] ) ? absint( $_GET['plc_event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit     = $event_id ? get_edit_post_link( $event_id ) : '';

		if ( 'error' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not add the event. Please enter at least a title and a date.', 'plc' ) . '</p></div>';
			return;
		}

		$message = ( 'published' === $result )
			? __( 'Event published.', 'plc' )
			: __( 'Event saved as a draft.', 'plc' );

		$link = $edit ? ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit it', 'plc' ) . '</a>' : '';

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . wp_kses_post( $link ) . '</p></div>';
	}
}
