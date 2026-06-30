<?php
/**
 * Settings page and the single source of truth for plugin options.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Settings {

	const OPTION    = 'plc_settings';
	const GROUP     = 'plc_settings_group';
	const PAGE_SLUG = 'plc-settings';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Default values for every option.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Email sender.
			'from_name'                => get_bloginfo( 'name' ),
			'from_email'               => get_bloginfo( 'admin_email' ),
			// Staff notifications.
			'notify_admin'             => 1,
			'admin_notify_to'          => get_bloginfo( 'admin_email' ),
			// Confirmation email text (merge tags: {event} {name} {date} {location} {party_size} {site}).
			'confirm_subject'          => __( 'You are registered: {event}', 'plc' ),
			'confirm_intro'            => __( 'Hi {name}, thank you for registering. Your spot is confirmed.', 'plc' ),
			'waitlist_subject'         => __( 'Waitlist confirmation: {event}', 'plc' ),
			'waitlist_intro'           => __( 'Hi {name}, this event is currently full, so we have added you to the waitlist. We will email you automatically if a spot opens up.', 'plc' ),
			// Defaults applied to brand-new events.
			'default_registration'     => 1,
			'default_capacity'         => 0,
			'default_waitlist'         => 0,
			// Public registration form.
			'collect_phone'            => 1,
			'require_phone'            => 0,
			'max_party_size'           => 20,
			// Where "browse events" / cancellation links point.
			'calendar_page_id'         => 0,
			// Appearance / theming.
			'load_styles'              => 1,
			'accent_color'            => '#1d4ed8',
			// Uninstall behaviour.
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * All settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * A single setting value.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Public URL events/cancellation links should point at (calendar page or home).
	 *
	 * @return string
	 */
	public static function calendar_url() {
		$id  = (int) self::get( 'calendar_page_id' );
		$url = $id ? get_permalink( $id ) : '';
		return $url ? $url : home_url( '/' );
	}

	/**
	 * Add Settings submenu under Library Calendar.
	 */
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . PLC_Post_Type::POST_TYPE,
			__( 'Calendar Settings', 'plc' ),
			__( 'Settings', 'plc' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the setting and its sanitizer.
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		$out   = array();

		$out['from_name']  = sanitize_text_field( $input['from_name'] ?? get_bloginfo( 'name' ) );
		$out['from_email'] = sanitize_email( $input['from_email'] ?? '' );
		if ( ! is_email( $out['from_email'] ) ) {
			$out['from_email'] = get_bloginfo( 'admin_email' );
		}

		$out['notify_admin']    = empty( $input['notify_admin'] ) ? 0 : 1;
		$out['admin_notify_to'] = sanitize_email( $input['admin_notify_to'] ?? '' );
		if ( ! is_email( $out['admin_notify_to'] ) ) {
			$out['admin_notify_to'] = get_bloginfo( 'admin_email' );
		}

		$out['confirm_subject']  = sanitize_text_field( $input['confirm_subject'] ?? '' );
		$out['confirm_intro']    = sanitize_textarea_field( $input['confirm_intro'] ?? '' );
		$out['waitlist_subject'] = sanitize_text_field( $input['waitlist_subject'] ?? '' );
		$out['waitlist_intro']   = sanitize_textarea_field( $input['waitlist_intro'] ?? '' );

		$out['default_registration'] = empty( $input['default_registration'] ) ? 0 : 1;
		$out['default_capacity']     = max( 0, absint( $input['default_capacity'] ?? 0 ) );
		$out['default_waitlist']     = empty( $input['default_waitlist'] ) ? 0 : 1;

		$out['collect_phone']  = empty( $input['collect_phone'] ) ? 0 : 1;
		$out['require_phone']  = empty( $input['require_phone'] ) ? 0 : 1;
		$out['max_party_size'] = min( 100, max( 1, absint( $input['max_party_size'] ?? 20 ) ) );

		$out['calendar_page_id'] = absint( $input['calendar_page_id'] ?? 0 );

		$out['load_styles']  = empty( $input['load_styles'] ) ? 0 : 1;
		$accent              = sanitize_hex_color( $input['accent_color'] ?? '' );
		$out['accent_color'] = $accent ? $accent : '#1d4ed8';

		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		// A required phone that isn't collected makes no sense — keep them consistent.
		if ( ! $out['collect_phone'] ) {
			$out['require_phone'] = 0;
		}

		return $out;
	}

	/**
	 * Field name helper.
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	private static function name( $key ) {
		return self::OPTION . '[' . $key . ']';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Library Calendar Settings', 'plc' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Confirmation emails', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="plc_from_name"><?php esc_html_e( '"From" name', 'plc' ); ?></label></th>
						<td><input type="text" id="plc_from_name" name="<?php echo esc_attr( self::name( 'from_name' ) ); ?>" value="<?php echo esc_attr( $s['from_name'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_from_email"><?php esc_html_e( '"From" address', 'plc' ); ?></label></th>
						<td>
							<input type="email" id="plc_from_email" name="<?php echo esc_attr( self::name( 'from_email' ) ); ?>" value="<?php echo esc_attr( $s['from_email'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Use an address on your own domain so messages are less likely to be marked as spam.', 'plc' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_confirm_subject"><?php esc_html_e( 'Confirmed — subject', 'plc' ); ?></label></th>
						<td><input type="text" id="plc_confirm_subject" name="<?php echo esc_attr( self::name( 'confirm_subject' ) ); ?>" value="<?php echo esc_attr( $s['confirm_subject'] ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_confirm_intro"><?php esc_html_e( 'Confirmed — message', 'plc' ); ?></label></th>
						<td><textarea id="plc_confirm_intro" name="<?php echo esc_attr( self::name( 'confirm_intro' ) ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $s['confirm_intro'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_waitlist_subject"><?php esc_html_e( 'Waitlist — subject', 'plc' ); ?></label></th>
						<td><input type="text" id="plc_waitlist_subject" name="<?php echo esc_attr( self::name( 'waitlist_subject' ) ); ?>" value="<?php echo esc_attr( $s['waitlist_subject'] ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_waitlist_intro"><?php esc_html_e( 'Waitlist — message', 'plc' ); ?></label></th>
						<td>
							<textarea id="plc_waitlist_intro" name="<?php echo esc_attr( self::name( 'waitlist_intro' ) ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $s['waitlist_intro'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Available merge tags:', 'plc' ); ?>
								<code>{event}</code> <code>{name}</code> <code>{date}</code> <code>{location}</code> <code>{party_size}</code> <code>{site}</code>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Staff notifications', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Notify staff', 'plc' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'notify_admin' ) ); ?>" value="1" <?php checked( $s['notify_admin'], 1 ); ?>>
								<?php esc_html_e( 'Email staff each time someone registers', 'plc' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_admin_notify_to"><?php esc_html_e( 'Staff notification address', 'plc' ); ?></label></th>
						<td><input type="email" id="plc_admin_notify_to" name="<?php echo esc_attr( self::name( 'admin_notify_to' ) ); ?>" value="<?php echo esc_attr( $s['admin_notify_to'] ); ?>" class="regular-text"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'New event defaults', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Registration', 'plc' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'default_registration' ) ); ?>" value="1" <?php checked( $s['default_registration'], 1 ); ?>>
								<?php esc_html_e( 'Enable registration on new events by default', 'plc' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_default_capacity"><?php esc_html_e( 'Default capacity', 'plc' ); ?></label></th>
						<td>
							<input type="number" id="plc_default_capacity" name="<?php echo esc_attr( self::name( 'default_capacity' ) ); ?>" value="<?php echo esc_attr( $s['default_capacity'] ); ?>" min="0" step="1">
							<span class="description"><?php esc_html_e( '0 = unlimited', 'plc' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Waitlist', 'plc' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'default_waitlist' ) ); ?>" value="1" <?php checked( $s['default_waitlist'], 1 ); ?>>
								<?php esc_html_e( 'Allow a waitlist on new events by default', 'plc' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Registration form', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone number', 'plc' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'collect_phone' ) ); ?>" value="1" <?php checked( $s['collect_phone'], 1 ); ?>>
								<?php esc_html_e( 'Show a phone number field', 'plc' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'require_phone' ) ); ?>" value="1" <?php checked( $s['require_phone'], 1 ); ?>>
								<?php esc_html_e( 'Make the phone number required', 'plc' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_max_party_size"><?php esc_html_e( 'Maximum party size', 'plc' ); ?></label></th>
						<td><input type="number" id="plc_max_party_size" name="<?php echo esc_attr( self::name( 'max_party_size' ) ); ?>" value="<?php echo esc_attr( $s['max_party_size'] ); ?>" min="1" max="100" step="1"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Calendar page', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="plc_calendar_page_id"><?php esc_html_e( 'Public calendar page', 'plc' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => self::name( 'calendar_page_id' ),
									'id'                => 'plc_calendar_page_id',
									'selected'          => (int) $s['calendar_page_id'],
									'show_option_none'  => __( '— None (use site home page) —', 'plc' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Where "browse events" and post-cancellation links send patrons. Choose the page that holds your [library_calendar] shortcode.', 'plc' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Appearance', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin styles', 'plc' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'load_styles' ) ); ?>" value="1" <?php checked( $s['load_styles'], 1 ); ?>>
								<?php esc_html_e( 'Load the plugin\'s front-end stylesheet', 'plc' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Turn this off if your theme provides its own styling for the calendar and forms.', 'plc' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="plc_accent_color"><?php esc_html_e( 'Accent color', 'plc' ); ?></label></th>
						<td>
							<input type="color" id="plc_accent_color" name="<?php echo esc_attr( self::name( 'accent_color' ) ); ?>" value="<?php echo esc_attr( $s['accent_color'] ); ?>">
							<p class="description"><?php esc_html_e( 'Used for buttons, badges, and highlights on the public calendar.', 'plc' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Advanced', 'plc' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'On uninstall', 'plc' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::name( 'delete_data_on_uninstall' ) ); ?>" value="1" <?php checked( $s['delete_data_on_uninstall'], 1 ); ?>>
								<?php esc_html_e( 'Delete all events and registrations when the plugin is deleted', 'plc' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default. When off, your data is preserved if you remove the plugin, so reinstalling restores everything.', 'plc' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'How to display the calendar', 'plc' ); ?></h2>
			<p><?php esc_html_e( 'Add this shortcode to any page or post:', 'plc' ); ?></p>
			<p>
				<code class="plc-shortcode">[library_calendar]</code>
				<button type="button" class="button plc-copy" data-plc-copy="[library_calendar]"><?php esc_html_e( 'Copy', 'plc' ); ?></button>
			</p>
			<p><?php esc_html_e( 'Optional attributes:', 'plc' ); ?></p>
			<ul style="list-style:disc;margin-left:1.5rem;">
				<li><code>view="grid"</code> — <?php esc_html_e( 'show a month calendar grid instead of the upcoming list', 'plc' ); ?></li>
				<li><code>limit="20"</code> — <?php esc_html_e( 'maximum number of events to show (list view)', 'plc' ); ?></li>
				<li><code>category="children,teens"</code> — <?php esc_html_e( 'show only certain event categories (by slug)', 'plc' ); ?></li>
				<li><code>past="yes"</code> — <?php esc_html_e( 'include events that have already started (list view)', 'plc' ); ?></li>
				<li><code>month="2026-07"</code> — <?php esc_html_e( 'starting month for the grid view', 'plc' ); ?></li>
			</ul>
			<p>
				<?php esc_html_e( 'Example month calendar:', 'plc' ); ?>
				<code class="plc-shortcode">[library_calendar view="grid"]</code>
				<button type="button" class="button plc-copy" data-plc-copy="[library_calendar view=&quot;grid&quot;]"><?php esc_html_e( 'Copy', 'plc' ); ?></button>
			</p>
		</div>
		<?php
	}
}
