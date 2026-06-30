<?php
/**
 * Template loader with theme-override support.
 *
 * A theme can override any template by placing a file at:
 *   wp-content/themes/your-theme/public-library-calendar/<name>
 * Child themes take precedence over parents, which take precedence over the
 * plugin's own templates/ directory.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Templates {

	/**
	 * Sub-directory themes use to override plugin templates.
	 */
	const THEME_DIR = 'public-library-calendar';

	/**
	 * Resolve the path for a template, honoring theme overrides.
	 *
	 * @param string $name Template filename, e.g. "event-card.php".
	 * @return string Absolute path.
	 */
	public static function locate( $name ) {
		$theme = locate_template(
			array(
				trailingslashit( self::THEME_DIR ) . $name,
			)
		);

		$path = $theme ? $theme : PLC_PLUGIN_DIR . 'templates/' . $name;

		/**
		 * Filter the resolved template path.
		 *
		 * @param string $path Absolute path that will be loaded.
		 * @param string $name Template filename.
		 */
		return apply_filters( 'plc_locate_template', $path, $name );
	}

	/**
	 * Render a template to a string with the given variables in scope.
	 *
	 * @param string $name Template filename.
	 * @param array  $vars Variables made available to the template.
	 * @return string
	 */
	public static function get( $name, $vars = array() ) {
		$path = self::locate( $name );
		if ( ! file_exists( $path ) ) {
			return '';
		}

		if ( ! empty( $vars ) ) {
			// Controlled, plugin-internal variable injection for templates.
			extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		ob_start();
		include $path;
		return (string) ob_get_clean();
	}
}
