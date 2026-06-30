<?php
/**
 * Plugin Name:       Public Library Calendar
 * Plugin URI:        https://gistifi.com/public-library-calendar
 * Description:        Events calendar for public libraries with public event registration, capacity limits, and an automatic waitlist.
 * Version:           1.2.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Eric Gire
 * Author URI:        https://gistifi.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plc
 * Domain Path:       /languages
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'PLC_VERSION', '1.2.2' );
define( 'PLC_DB_VERSION', '1.0' );
define( 'PLC_PLUGIN_FILE', __FILE__ );
define( 'PLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PLC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once PLC_PLUGIN_DIR . 'includes/class-plc-templates.php';
require_once PLC_PLUGIN_DIR . 'includes/template-tags.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-post-type.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-meta-boxes.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-registrations.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-ajax.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-emails.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-calendar-grid.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-shortcodes.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-admin-registrants.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-dashboard.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-settings.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-ics.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-recurrence.php';
require_once PLC_PLUGIN_DIR . 'includes/class-plc-plugin.php';

/**
 * Activation: install the registrations table and register rewrite rules.
 */
function plc_activate() {
	PLC_Registrations::install_table();
	PLC_Post_Type::register(); // Register so rewrite rules exist before flushing.
	flush_rewrite_rules();
	if ( false === get_option( 'plc_settings' ) ) {
		add_option( 'plc_settings', PLC_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'plc_activate' );

/**
 * Deactivation: clear rewrite rules. Data is preserved (see uninstall.php).
 */
function plc_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'plc_deactivate' );

/**
 * Boot the plugin.
 */
function plc() {
	return PLC_Plugin::instance();
}
add_action( 'plugins_loaded', 'plc' );
