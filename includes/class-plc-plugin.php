<?php
/**
 * Main plugin loader. Wires together the component classes.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PLC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PLC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return PLC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Register hooks for each component.
	 */
	private function init() {
		load_plugin_textdomain( 'plc', false, dirname( PLC_PLUGIN_BASENAME ) . '/languages' );

		PLC_Post_Type::init();
		PLC_Meta_Boxes::init();
		PLC_Ajax::init();
		PLC_Shortcodes::init();
		PLC_Admin_Registrants::init();
		PLC_Dashboard::init();
		PLC_Settings::init();
		PLC_Ics::init();
		PLC_Recurrence::init();
		PLC_Emails::init();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'public_assets' ) );

		// Run a lightweight DB upgrade check on load.
		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );
	}

	/**
	 * Enqueue admin CSS/JS only on event screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_assets( $hook ) {
		$screen = get_current_screen();
		$is_event_screen = $screen && PLC_Post_Type::POST_TYPE === $screen->post_type;
		$page            = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_plugin_page  = in_array( $page, array( 'plc-registrants', 'plc-settings' ), true );

		if ( ! $is_event_screen && ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style( 'plc-admin', PLC_PLUGIN_URL . 'assets/css/admin.css', array(), PLC_VERSION );
		wp_enqueue_script( 'plc-admin', PLC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), PLC_VERSION, true );
	}

	/**
	 * Enqueue public CSS/JS. Kept small; only loaded when shortcode runs (see Shortcodes class)
	 * but registered here so single-event pages also have them.
	 */
	public function public_assets() {
		$load_styles = (bool) PLC_Settings::get( 'load_styles' );

		// Register the stylesheet only when enabled, so the shortcode's enqueue is a
		// no-op when a theme is handling the styling itself.
		if ( $load_styles ) {
			wp_register_style( 'plc-public', PLC_PLUGIN_URL . 'assets/css/public.css', array(), PLC_VERSION );

			$accent = PLC_Settings::get( 'accent_color' );
			if ( $accent ) {
				wp_add_inline_style(
					'plc-public',
					sprintf(
						':root{--plc-accent:%1$s;--plc-accent-dark:%2$s;}',
						$accent,
						self::darken( $accent, 12 )
					)
				);
			}
		}

		wp_register_script( 'plc-public', PLC_PLUGIN_URL . 'assets/js/public.js', array(), PLC_VERSION, true );
		wp_localize_script(
			'plc-public',
			'PLC',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'plc_register' ),
				'strings' => array(
					'submitting' => __( 'Submitting…', 'plc' ),
					'error'      => __( 'Something went wrong. Please try again.', 'plc' ),
				),
			)
		);

		// Enqueue on single event pages automatically.
		if ( is_singular( PLC_Post_Type::POST_TYPE ) ) {
			if ( $load_styles ) {
				wp_enqueue_style( 'plc-public' );
			}
			wp_enqueue_script( 'plc-public' );
		}
	}

	/**
	 * Darken a hex color by a percentage, for the button hover shade.
	 *
	 * @param string $hex     Hex color (#rgb or #rrggbb).
	 * @param int    $percent Percentage to darken (0–100).
	 * @return string Hex color.
	 */
	private static function darken( $hex, $percent ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '#' . $hex;
		}
		$factor = max( 0, 1 - ( $percent / 100 ) );
		$r      = (int) round( hexdec( substr( $hex, 0, 2 ) ) * $factor );
		$g      = (int) round( hexdec( substr( $hex, 2, 2 ) ) * $factor );
		$b      = (int) round( hexdec( substr( $hex, 4, 2 ) ) * $factor );
		return sprintf( '#%02x%02x%02x', min( 255, $r ), min( 255, $g ), min( 255, $b ) );
	}

	/**
	 * Reinstall/upgrade the DB table if the stored version is behind.
	 */
	public function maybe_upgrade_db() {
		if ( get_option( 'plc_db_version' ) !== PLC_DB_VERSION ) {
			PLC_Registrations::install_table();
		}
	}
}
