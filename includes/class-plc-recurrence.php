<?php
/**
 * Recurring events. When an event is published with a repeat rule, independent
 * dated occurrences are generated once — each its own post with its own capacity
 * and registrations. Editing the original later does not alter generated occurrences.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Recurrence {

	const NONCE_ACTION = 'plc_save_recurrence';
	const NONCE_NAME   = 'plc_recurrence_nonce';
	const MAX_COUNT    = 52;

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		// Priority 20: runs after PLC_Meta_Boxes::save (10) so _plc_start is already stored.
		add_action( 'save_post_' . PLC_Post_Type::POST_TYPE, array( __CLASS__, 'maybe_generate' ), 20, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'generation_notice' ) );
	}

	/**
	 * Register the recurrence meta box in the sidebar.
	 */
	public static function add_box() {
		add_meta_box(
			'plc_event_recurrence',
			__( 'Repeat', 'plc' ),
			array( __CLASS__, 'render' ),
			PLC_Post_Type::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the recurrence controls (or a notice for occurrences).
	 *
	 * @param WP_Post $post Event.
	 */
	public static function render( $post ) {
		$parent = get_post_meta( $post->ID, '_plc_recurrence_parent', true );
		if ( $parent ) {
			printf(
				'<p>%s</p><p><a href="%s">%s</a></p>',
				esc_html__( 'This event is one occurrence in a recurring series.', 'plc' ),
				esc_url( (string) get_edit_post_link( $parent ) ),
				esc_html__( 'Edit the original event', 'plc' )
			);
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$freq      = get_post_meta( $post->ID, '_plc_recurrence_freq', true );
		$interval  = (int) get_post_meta( $post->ID, '_plc_recurrence_interval', true );
		$count     = (int) get_post_meta( $post->ID, '_plc_recurrence_count', true );
		$generated = (int) get_post_meta( $post->ID, '_plc_recurrence_generated', true );

		$freq     = $freq ? $freq : 'none';
		$interval = $interval > 0 ? $interval : 1;
		$count    = $count > 0 ? $count : 4;
		$disabled = $generated ? 'disabled' : '';
		?>
		<p>
			<label for="plc_recurrence_freq"><strong><?php esc_html_e( 'Repeats', 'plc' ); ?></strong></label><br>
			<select id="plc_recurrence_freq" name="plc_recurrence_freq" <?php echo esc_attr( $disabled ); ?>>
				<option value="none" <?php selected( $freq, 'none' ); ?>><?php esc_html_e( 'Does not repeat', 'plc' ); ?></option>
				<option value="daily" <?php selected( $freq, 'daily' ); ?>><?php esc_html_e( 'Daily', 'plc' ); ?></option>
				<option value="weekly" <?php selected( $freq, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'plc' ); ?></option>
				<option value="monthly" <?php selected( $freq, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'plc' ); ?></option>
			</select>
		</p>
		<p>
			<label for="plc_recurrence_interval"><?php esc_html_e( 'Every', 'plc' ); ?></label>
			<input type="number" id="plc_recurrence_interval" name="plc_recurrence_interval" min="1" max="12" value="<?php echo esc_attr( $interval ); ?>" style="width:60px;" <?php echo esc_attr( $disabled ); ?>>
			<span class="description"><?php esc_html_e( '(interval)', 'plc' ); ?></span>
		</p>
		<p>
			<label for="plc_recurrence_count"><?php esc_html_e( 'Number of occurrences', 'plc' ); ?></label><br>
			<input type="number" id="plc_recurrence_count" name="plc_recurrence_count" min="1" max="<?php echo esc_attr( self::MAX_COUNT ); ?>" value="<?php echo esc_attr( $count ); ?>" style="width:70px;" <?php echo esc_attr( $disabled ); ?>>
		</p>
		<?php if ( $generated ) : ?>
			<p class="description"><?php esc_html_e( 'This series has already been created. To change the schedule, delete the occurrences and create a new event.', 'plc' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Occurrences are generated once when you publish. Each is independent, with its own capacity and sign-ups.', 'plc' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * On save, persist the rule and generate occurrences if appropriate.
	 *
	 * @param int     $post_id Event ID.
	 * @param WP_Post $post    Event object.
	 */
	public static function maybe_generate( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Occurrences never spawn their own series.
		if ( get_post_meta( $post_id, '_plc_recurrence_parent', true ) ) {
			return;
		}

		$freq     = sanitize_key( wp_unslash( $_POST['plc_recurrence_freq'] ?? 'none' ) );
		$interval = min( 12, max( 1, absint( $_POST['plc_recurrence_interval'] ?? 1 ) ) );
		$count    = min( self::MAX_COUNT, max( 1, absint( $_POST['plc_recurrence_count'] ?? 1 ) ) );

		if ( ! in_array( $freq, array( 'none', 'daily', 'weekly', 'monthly' ), true ) ) {
			$freq = 'none';
		}

		update_post_meta( $post_id, '_plc_recurrence_freq', $freq );
		update_post_meta( $post_id, '_plc_recurrence_interval', $interval );
		update_post_meta( $post_id, '_plc_recurrence_count', $count );

		// Only generate once, for a published/scheduled parent with a real start.
		if ( 'none' === $freq || $count < 2 ) {
			return;
		}
		if ( get_post_meta( $post_id, '_plc_recurrence_generated', true ) ) {
			return;
		}
		if ( ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
			return;
		}
		$parent_start = get_post_meta( $post_id, '_plc_start', true );
		if ( empty( $parent_start ) ) {
			return;
		}

		$created = self::generate_series( $post_id, $post, $freq, $interval, $count );

		update_post_meta( $post_id, '_plc_recurrence_generated', 1 );
		set_transient( 'plc_recur_notice_' . get_current_user_id(), (int) $created, 60 );
	}

	/**
	 * Create the occurrence posts. Hooks are detached during inserts so the
	 * in-progress parent $_POST does not overwrite each new occurrence's meta.
	 *
	 * @param int     $parent_id Parent event ID.
	 * @param WP_Post $parent    Parent event.
	 * @param string  $freq      daily|weekly|monthly.
	 * @param int     $interval  Step size.
	 * @param int     $count     Total occurrences (including the parent).
	 * @return int Number of occurrences created.
	 */
	private static function generate_series( $parent_id, $parent, $freq, $interval, $count ) {
		$tz          = wp_timezone();
		$parent_meta = self::collect_meta( $parent_id );

		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $parent_meta['start'], $tz );
		if ( ! $start ) {
			return 0;
		}

		$start_ts    = $start->getTimestamp();
		$end_ts      = $parent_meta['end_ts'];
		$duration    = ( $end_ts && $end_ts > $start_ts ) ? ( $end_ts - $start_ts ) : 0;
		$deadline_ts = $parent_meta['deadline_ts'];
		$lead        = ( $deadline_ts && $deadline_ts <= $start_ts ) ? ( $start_ts - $deadline_ts ) : null;

		$terms = wp_get_object_terms( $parent_id, PLC_Post_Type::TAXONOMY, array( 'fields' => 'ids' ) );
		$terms = is_wp_error( $terms ) ? array() : $terms;

		$unit = array(
			'daily'   => 'days',
			'weekly'  => 'weeks',
			'monthly' => 'months',
		)[ $freq ];

		// Detach save hooks so synchronous save_post on the new posts is inert.
		self::detach_hooks();

		$created = 0;
		for ( $i = 1; $i < $count; $i++ ) {
			$occ = $start->modify( '+' . ( $interval * $i ) . ' ' . $unit );
			if ( ! $occ ) {
				continue;
			}
			$occ_ts    = $occ->getTimestamp();
			$occ_start = $occ->format( 'Y-m-d H:i:s' );
			$occ_end   = $duration ? wp_date( 'Y-m-d H:i:s', $occ_ts + $duration ) : '';
			$occ_dead  = ( null !== $lead ) ? wp_date( 'Y-m-d H:i:s', $occ_ts - $lead ) : '';

			$new_id = wp_insert_post(
				array(
					'post_type'    => PLC_Post_Type::POST_TYPE,
					'post_status'  => $parent->post_status,
					'post_title'   => $parent->post_title,
					'post_content' => $parent->post_content,
					'post_excerpt' => $parent->post_excerpt,
					'post_author'  => $parent->post_author,
					'meta_input'   => array(
						'_plc_start'                  => $occ_start,
						'_plc_end'                    => $occ_end,
						'_plc_registration_deadline'  => $occ_dead,
						'_plc_all_day'                => $parent_meta['all_day'],
						'_plc_location'               => $parent_meta['location'],
						'_plc_capacity'               => $parent_meta['capacity'],
						'_plc_registration_enabled'   => $parent_meta['reg_enabled'],
						'_plc_waitlist_enabled'       => $parent_meta['waitlist'],
						'_plc_recurrence_parent'      => $parent_id,
					),
				),
				true
			);

			if ( $new_id && ! is_wp_error( $new_id ) ) {
				if ( ! empty( $parent_meta['thumbnail_id'] ) ) {
					set_post_thumbnail( $new_id, $parent_meta['thumbnail_id'] );
				}
				if ( $terms ) {
					wp_set_object_terms( $new_id, $terms, PLC_Post_Type::TAXONOMY );
				}
				$created++;
			}
		}

		self::attach_hooks();
		return $created;
	}

	/**
	 * Gather the parent's meta needed to clone occurrences.
	 *
	 * @param int $parent_id Parent event ID.
	 * @return array
	 */
	private static function collect_meta( $parent_id ) {
		return array(
			'start'        => get_post_meta( $parent_id, '_plc_start', true ),
			'end_ts'       => PLC_Meta_Boxes::to_timestamp( get_post_meta( $parent_id, '_plc_end', true ) ),
			'deadline_ts'  => PLC_Meta_Boxes::to_timestamp( get_post_meta( $parent_id, '_plc_registration_deadline', true ) ),
			'all_day'      => (int) get_post_meta( $parent_id, '_plc_all_day', true ),
			'location'     => get_post_meta( $parent_id, '_plc_location', true ),
			'capacity'     => (int) get_post_meta( $parent_id, '_plc_capacity', true ),
			'reg_enabled'  => get_post_meta( $parent_id, '_plc_registration_enabled', true ),
			'waitlist'     => get_post_meta( $parent_id, '_plc_waitlist_enabled', true ),
			'thumbnail_id' => get_post_thumbnail_id( $parent_id ),
		);
	}

	/**
	 * Remove the save_post handlers that would otherwise fire for new occurrences.
	 */
	private static function detach_hooks() {
		remove_action( 'save_post_' . PLC_Post_Type::POST_TYPE, array( 'PLC_Meta_Boxes', 'save' ), 10 );
		remove_action( 'save_post_' . PLC_Post_Type::POST_TYPE, array( __CLASS__, 'maybe_generate' ), 20 );
	}

	/**
	 * Restore the save_post handlers after occurrence creation.
	 */
	private static function attach_hooks() {
		add_action( 'save_post_' . PLC_Post_Type::POST_TYPE, array( 'PLC_Meta_Boxes', 'save' ), 10, 2 );
		add_action( 'save_post_' . PLC_Post_Type::POST_TYPE, array( __CLASS__, 'maybe_generate' ), 20, 2 );
	}

	/**
	 * Tag events in the admin list as part of a series.
	 *
	 * @param array   $states Existing post states.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function post_states( $states, $post ) {
		if ( PLC_Post_Type::POST_TYPE !== $post->post_type ) {
			return $states;
		}
		if ( get_post_meta( $post->ID, '_plc_recurrence_parent', true ) ) {
			$states['plc_recur'] = __( 'Occurrence', 'plc' );
		} elseif ( get_post_meta( $post->ID, '_plc_recurrence_generated', true ) ) {
			$states['plc_recur'] = __( 'Recurring (original)', 'plc' );
		}
		return $states;
	}

	/**
	 * Show a one-time notice after a series is generated.
	 */
	public static function generation_notice() {
		$key   = 'plc_recur_notice_' . get_current_user_id();
		$count = get_transient( $key );
		if ( false === $count ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of additional occurrences created */
					_n( '%d additional occurrence was created for this recurring event.', '%d additional occurrences were created for this recurring event.', (int) $count, 'plc' ),
					(int) $count
				)
			)
		);
	}
}
