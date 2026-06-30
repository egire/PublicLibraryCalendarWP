<?php
/**
 * Admin screen to view, manage, and export event registrants.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Admin_Registrants {

	const CAP = 'edit_others_posts'; // Editors and admins.

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_plc_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_plc_export_all_csv', array( __CLASS__, 'export_all_csv' ) );
		add_action( 'admin_post_plc_cancel_registration', array( __CLASS__, 'admin_cancel' ) );
		add_action( 'admin_post_plc_add_registrant', array( __CLASS__, 'admin_add' ) );
	}

	/**
	 * Add the Registrants submenu under the Library Calendar menu.
	 */
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . PLC_Post_Type::POST_TYPE,
			__( 'Registrants', 'plc' ),
			__( 'Registrants', 'plc' ),
			self::CAP,
			'plc-registrants',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the registrants admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view registrants.', 'plc' ) );
		}

		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap plc-registrants-page">';
		echo '<h1>' . esc_html__( 'Event Registrants', 'plc' ) . '</h1>';

		self::render_admin_notice();

		echo '<p><a class="button" href="' . esc_url( self::export_all_url() ) . '">' . esc_html__( 'Export ALL registrants (every event)', 'plc' ) . '</a></p>';

		self::render_event_picker( $event_id );

		if ( $event_id ) {
			self::render_event_registrants( $event_id );
		} else {
			echo '<p>' . esc_html__( 'Choose an event above to view its registrants.', 'plc' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Show a success/error notice based on the plc_msg query arg.
	 */
	private static function render_admin_notice() {
		if ( empty( $_GET['plc_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$msg = sanitize_key( wp_unslash( $_GET['plc_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$map = array(
			'cancelled' => array( 'success', __( 'Registration cancelled. Any eligible waitlisted guests were promoted automatically.', 'plc' ) ),
			'added'     => array( 'success', __( 'Registrant added.', 'plc' ) ),
			'add_error' => array( 'error', __( 'Could not add registrant. They may already be registered, or the event may be full or closed.', 'plc' ) ),
		);

		if ( isset( $map[ $msg ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $map[ $msg ][0] ),
				esc_html( $map[ $msg ][1] )
			);
		}
	}

	/**
	 * Dropdown to pick an event.
	 *
	 * @param int $selected Selected event ID.
	 */
	private static function render_event_picker( $selected ) {
		$events = get_posts(
			array(
				'post_type'      => PLC_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'future' ),
				'posts_per_page' => -1,
				'meta_key'       => '_plc_start',
				'orderby'        => 'meta_value',
				'order'          => 'DESC',
			)
		);
		?>
		<form method="get" class="plc-event-picker">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( PLC_Post_Type::POST_TYPE ); ?>">
			<input type="hidden" name="page" value="plc-registrants">
			<label for="plc-event-select"><strong><?php esc_html_e( 'Event:', 'plc' ); ?></strong></label>
			<select name="event_id" id="plc-event-select" onchange="this.form.submit()">
				<option value="0"><?php esc_html_e( '— Select an event —', 'plc' ); ?></option>
				<?php foreach ( $events as $event ) :
					$start = get_post_meta( $event->ID, '_plc_start', true );
					$label = get_the_title( $event ) . ( $start ? ' (' . PLC_Meta_Boxes::format_datetime( $start ) . ')' : '' );
					?>
					<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $selected, $event->ID ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'View', 'plc' ); ?></button></noscript>
		</form>
		<?php
	}

	/**
	 * Render the registrant table + summary + add form for one event.
	 *
	 * @param int $event_id Event ID.
	 */
	private static function render_event_registrants( $event_id ) {
		$event = get_post( $event_id );
		if ( ! $event || PLC_Post_Type::POST_TYPE !== $event->post_type ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Event not found.', 'plc' ) . '</p></div>';
			return;
		}

		$counts        = PLC_Registrations::get_counts( $event_id );
		$registrations = PLC_Registrations::get_for_event( $event_id );

		$cap_label = $counts['unlimited'] ? __( 'Unlimited', 'plc' ) : (string) $counts['capacity'];
		?>
		<div class="plc-summary-cards">
			<div class="plc-card"><span class="plc-card-num"><?php echo esc_html( $counts['confirmed_seats'] ); ?></span><span class="plc-card-label"><?php esc_html_e( 'Confirmed seats', 'plc' ); ?></span></div>
			<div class="plc-card"><span class="plc-card-num"><?php echo esc_html( $counts['waitlist_seats'] ); ?></span><span class="plc-card-label"><?php esc_html_e( 'Waitlisted', 'plc' ); ?></span></div>
			<div class="plc-card"><span class="plc-card-num"><?php echo esc_html( $cap_label ); ?></span><span class="plc-card-label"><?php esc_html_e( 'Capacity', 'plc' ); ?></span></div>
			<div class="plc-card"><span class="plc-card-num"><?php echo $counts['unlimited'] ? '∞' : esc_html( max( 0, $counts['spots_left'] ) ); ?></span><span class="plc-card-label"><?php esc_html_e( 'Spots left', 'plc' ); ?></span></div>
		</div>

		<p>
			<a class="button button-primary" href="<?php echo esc_url( self::export_url( $event_id ) ); ?>"><?php esc_html_e( 'Export CSV', 'plc' ); ?></a>
			<a class="button" href="<?php echo esc_url( get_edit_post_link( $event_id ) ); ?>"><?php esc_html_e( 'Edit event', 'plc' ); ?></a>
		</p>

		<?php if ( empty( $registrations ) ) : ?>
			<p><?php esc_html_e( 'No registrations yet.', 'plc' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'plc' ); ?></th>
						<th><?php esc_html_e( 'Email', 'plc' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'plc' ); ?></th>
						<th><?php esc_html_e( 'Party', 'plc' ); ?></th>
						<th><?php esc_html_e( 'Status', 'plc' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'plc' ); ?></th>
						<th><?php esc_html_e( 'Action', 'plc' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $registrations as $reg ) : ?>
					<tr class="plc-status-<?php echo esc_attr( $reg->status ); ?>">
						<td><?php echo esc_html( $reg->name ); ?></td>
						<td><a href="mailto:<?php echo esc_attr( $reg->email ); ?>"><?php echo esc_html( $reg->email ); ?></a></td>
						<td><?php echo esc_html( $reg->phone ); ?></td>
						<td><?php echo (int) $reg->party_size; ?></td>
						<td><span class="plc-pill plc-pill-<?php echo esc_attr( $reg->status ); ?>"><?php echo esc_html( ucfirst( $reg->status ) ); ?></span></td>
						<td><?php echo esc_html( PLC_Meta_Boxes::format_datetime( $reg->created_at ) ); ?></td>
						<td>
							<?php if ( PLC_Registrations::STATUS_CANCELLED !== $reg->status ) : ?>
								<a class="plc-cancel-link" href="<?php echo esc_url( self::cancel_url( $reg->id, $event_id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this registration? Waitlisted guests may be promoted automatically.', 'plc' ) ); ?>');"><?php esc_html_e( 'Cancel', 'plc' ); ?></a>
							<?php else : ?>
								<span class="plc-muted">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Add a registrant manually', 'plc' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="plc-add-form">
			<?php wp_nonce_field( 'plc_add_registrant_' . $event_id ); ?>
			<input type="hidden" name="action" value="plc_add_registrant">
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
			<input type="text" name="name" placeholder="<?php esc_attr_e( 'Name', 'plc' ); ?>" required>
			<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email', 'plc' ); ?>" required>
			<input type="text" name="phone" placeholder="<?php esc_attr_e( 'Phone (optional)', 'plc' ); ?>">
			<input type="number" name="party_size" value="1" min="1" max="<?php echo esc_attr( (int) PLC_Settings::get( 'max_party_size' ) ); ?>" style="width:70px;">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'plc' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Build a nonced export URL.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	private static function export_url( $event_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=plc_export_csv&event_id=' . $event_id ),
			'plc_export_' . $event_id
		);
	}

	/**
	 * Build a nonced URL for the combined export.
	 *
	 * @return string
	 */
	private static function export_all_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=plc_export_all_csv' ),
			'plc_export_all'
		);
	}

	/**
	 * Build a nonced cancel URL.
	 *
	 * @param int $reg_id   Registration ID.
	 * @param int $event_id Event ID.
	 * @return string
	 */
	private static function cancel_url( $reg_id, $event_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=plc_cancel_registration&reg_id=' . $reg_id . '&event_id=' . $event_id ),
			'plc_cancel_' . $reg_id
		);
	}

	/**
	 * Export a single event's registrants as CSV.
	 */
	public static function export_csv() {
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'plc' ) );
		}
		check_admin_referer( 'plc_export_' . $event_id );

		$registrations = PLC_Registrations::get_for_event( $event_id );
		$slug          = sanitize_title( get_the_title( $event_id ) );
		$filename      = 'registrants-' . ( $slug ? $slug : $event_id ) . '-' . gmdate( 'Ymd' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );
		fputs( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
		fputcsv( $out, array( 'Name', 'Email', 'Phone', 'Party Size', 'Status', 'Registered' ) );

		foreach ( $registrations as $reg ) {
			fputcsv(
				$out,
				array(
					$reg->name,
					$reg->email,
					$reg->phone,
					$reg->party_size,
					ucfirst( $reg->status ),
					PLC_Meta_Boxes::format_datetime( $reg->created_at ),
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Export every registration across all events as one CSV.
	 */
	public static function export_all_csv() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'plc' ) );
		}
		check_admin_referer( 'plc_export_all' );

		$rows     = PLC_Registrations::get_all();
		$filename = 'all-registrants-' . gmdate( 'Ymd' ) . '.csv';

		// Cache event title + date lookups across rows.
		$event_cache = array();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );
		fputs( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Event', 'Event Date', 'Name', 'Email', 'Phone', 'Party Size', 'Status', 'Registered' ) );

		foreach ( $rows as $reg ) {
			if ( ! isset( $event_cache[ $reg->event_id ] ) ) {
				$start = get_post_meta( $reg->event_id, '_plc_start', true );
				$event_cache[ $reg->event_id ] = array(
					'title' => get_the_title( $reg->event_id ),
					'date'  => $start ? PLC_Meta_Boxes::format_datetime( $start ) : '',
				);
			}
			fputcsv(
				$out,
				array(
					$event_cache[ $reg->event_id ]['title'],
					$event_cache[ $reg->event_id ]['date'],
					$reg->name,
					$reg->email,
					$reg->phone,
					$reg->party_size,
					ucfirst( $reg->status ),
					PLC_Meta_Boxes::format_datetime( $reg->created_at ),
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Cancel a registration from the admin table.
	 */
	public static function admin_cancel() {
		$reg_id   = isset( $_GET['reg_id'] ) ? absint( $_GET['reg_id'] ) : 0;
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'plc' ) );
		}
		check_admin_referer( 'plc_cancel_' . $reg_id );

		PLC_Registrations::cancel( $reg_id );

		wp_safe_redirect( add_query_arg( 'plc_msg', 'cancelled', self::page_url( $event_id ) ) );
		exit;
	}

	/**
	 * Add a registrant manually from the admin.
	 */
	public static function admin_add() {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'plc' ) );
		}
		check_admin_referer( 'plc_add_registrant_' . $event_id );

		$result = PLC_Registrations::register(
			$event_id,
			array(
				'name'       => wp_unslash( $_POST['name'] ?? '' ),
				'email'      => wp_unslash( $_POST['email'] ?? '' ),
				'phone'      => wp_unslash( $_POST['phone'] ?? '' ),
				'party_size' => $_POST['party_size'] ?? 1,
			)
		);

		$msg = is_wp_error( $result ) ? 'add_error' : 'added';
		wp_safe_redirect( add_query_arg( 'plc_msg', $msg, self::page_url( $event_id ) ) );
		exit;
	}

	/**
	 * URL for the registrants page for a given event.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	private static function page_url( $event_id ) {
		return admin_url( 'edit.php?post_type=' . PLC_Post_Type::POST_TYPE . '&page=plc-registrants&event_id=' . $event_id );
	}
}
