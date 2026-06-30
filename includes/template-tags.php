<?php
/**
 * Public template tags for theme developers.
 *
 * Use these in classic PHP theme templates (page.php, single.php, custom page
 * templates, sidebars, etc.) instead of do_shortcode().
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'plc_get_calendar' ) ) {
	/**
	 * Return the events calendar HTML.
	 *
	 * Accepts the same options as the [library_calendar] shortcode:
	 *   - view     'list' (default) or 'grid'
	 *   - limit    int, number of events in list view (default 20)
	 *   - category comma-separated category slugs
	 *   - past     'yes' to include events that already started (list view)
	 *   - month    'YYYY-MM' starting month for grid view
	 *
	 * Example:
	 *   echo plc_get_calendar( array( 'view' => 'grid' ) );
	 *
	 * @param array $args Calendar options.
	 * @return string Calendar HTML (empty string if the plugin isn't ready).
	 */
	function plc_get_calendar( $args = array() ) {
		if ( ! class_exists( 'PLC_Shortcodes' ) ) {
			return '';
		}
		return PLC_Shortcodes::calendar( (array) $args );
	}
}

if ( ! function_exists( 'plc_calendar' ) ) {
	/**
	 * Echo the events calendar.
	 *
	 * Example:
	 *   plc_calendar( array( 'view' => 'grid', 'limit' => 10 ) );
	 *
	 * @param array $args Calendar options (see plc_get_calendar()).
	 * @return void
	 */
	function plc_calendar( $args = array() ) {
		// Output is assembled and escaped inside the plugin, like do_shortcode().
		echo plc_get_calendar( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
