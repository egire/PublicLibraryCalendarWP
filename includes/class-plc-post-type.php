<?php
/**
 * Registers the Event custom post type and its category taxonomy,
 * and renders public event detail + registration form on single pages.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Post_Type {

	const POST_TYPE = 'plc_event';
	const TAXONOMY  = 'plc_event_category';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_event_details' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_admin_by_start' ) );
		// Use the classic editor for events so the date/detail meta boxes save reliably.
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'use_classic_editor' ), 10, 2 );
		// Clarify the theme's published date on single event pages so it isn't
		// mistaken for the event date.
		add_filter( 'get_the_date', array( __CLASS__, 'label_posting_date' ), 10, 3 );
	}

	/**
	 * Prefix the WordPress published date with a clear "Posted on" label on
	 * single event pages, so visitors don't read it as the event's own date.
	 * The event date itself is shown, labeled "When", in the event detail block.
	 *
	 * @param string      $the_date The formatted date string.
	 * @param string      $format   The requested date format.
	 * @param int|WP_Post $post     Post object or ID.
	 * @return string
	 */
	public static function label_posting_date( $the_date, $format, $post ) {
		if ( is_admin() || is_feed() || '' === $the_date ) {
			return $the_date;
		}
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type || ! is_singular( self::POST_TYPE ) ) {
			return $the_date;
		}

		/**
		 * Filter the label prefixed to the posting date on single event pages.
		 * Return an empty string to leave the date unchanged.
		 *
		 * @param string $label The prefix label.
		 */
		$label = apply_filters( 'plc_posting_date_label', __( 'Posted on ', 'plc' ) );

		return $label ? $label . $the_date : $the_date;
	}

	/**
	 * Force the classic editor for events. The block editor saves classic meta
	 * boxes through a separate request that some setups block, which can drop the
	 * date fields; the classic editor posts them in the main save every time.
	 *
	 * @param bool   $use_block Whether to use the block editor.
	 * @param string $post_type Post type being edited.
	 * @return bool
	 */
	public static function use_classic_editor( $use_block, $post_type ) {
		return ( self::POST_TYPE === $post_type ) ? false : $use_block;
	}

	/**
	 * Register CPT + taxonomy. Also called on activation.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Library Events', 'plc' ),
			'singular_name'      => __( 'Event', 'plc' ),
			'menu_name'          => __( 'Library Calendar', 'plc' ),
			'add_new'            => __( 'Add Event', 'plc' ),
			'add_new_item'       => __( 'Add New Event', 'plc' ),
			'edit_item'          => __( 'Edit Event', 'plc' ),
			'new_item'           => __( 'New Event', 'plc' ),
			'view_item'          => __( 'View Event', 'plc' ),
			'search_items'       => __( 'Search Events', 'plc' ),
			'not_found'          => __( 'No events found.', 'plc' ),
			'not_found_in_trash' => __( 'No events found in Trash.', 'plc' ),
			'all_items'          => __( 'All Events', 'plc' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
				'menu_position' => 25,
				'rewrite'      => array( 'slug' => 'events' ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Event Categories', 'plc' ),
					'singular_name' => __( 'Event Category', 'plc' ),
					'menu_name'     => __( 'Categories', 'plc' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'event-category' ),
			)
		);
	}

	/**
	 * Append formatted event details and the registration form to single event content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function append_event_details( $content ) {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$event_id = get_the_ID();
		$details  = PLC_Shortcodes::render_event_details( $event_id );
		$form     = PLC_Shortcodes::render_registration_block( $event_id );

		/**
		 * Filter the full single-event output (details + content + registration).
		 *
		 * @param string $html     Combined markup.
		 * @param int    $event_id Event ID.
		 */
		return apply_filters( 'plc_single_event_html', $details . $content . $form, $event_id );
	}

	/**
	 * Admin list-table columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['plc_start']    = __( 'Starts', 'plc' );
				$new['plc_capacity'] = __( 'Capacity', 'plc' );
				$new['plc_signups']  = __( 'Registered', 'plc' );
			}
		}
		return $new;
	}

	/**
	 * Render custom admin columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Event ID.
	 */
	public static function render_admin_column( $column, $post_id ) {
		switch ( $column ) {
			case 'plc_start':
				$start = get_post_meta( $post_id, '_plc_start', true );
				echo $start ? esc_html( PLC_Meta_Boxes::format_datetime( $start ) ) : '—';
				break;
			case 'plc_capacity':
				$cap = (int) get_post_meta( $post_id, '_plc_capacity', true );
				echo $cap > 0 ? esc_html( $cap ) : esc_html__( 'Unlimited', 'plc' );
				break;
			case 'plc_signups':
				$counts = PLC_Registrations::get_counts( $post_id );
				printf(
					'<a href="%s">%d %s</a>',
					esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=plc-registrants&event_id=' . $post_id ) ),
					(int) $counts['confirmed_seats'],
					esc_html__( 'confirmed', 'plc' )
				);
				if ( $counts['waitlist_seats'] > 0 ) {
					printf( ' <span class="plc-wl">(%d %s)</span>', (int) $counts['waitlist_seats'], esc_html__( 'waitlisted', 'plc' ) );
				}
				break;
		}
	}

	/**
	 * Make the Starts column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['plc_start'] = 'plc_start';
		return $columns;
	}

	/**
	 * Default admin ordering by event start date.
	 *
	 * @param WP_Query $query Current query.
	 */
	public static function order_admin_by_start( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( '' === $query->get( 'orderby' ) || 'plc_start' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_plc_start' );
			$query->set( 'orderby', 'meta_value' );
			if ( '' === $query->get( 'order' ) ) {
				$query->set( 'order', 'ASC' );
			}
		}
	}
}
