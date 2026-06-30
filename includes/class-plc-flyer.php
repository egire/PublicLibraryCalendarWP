<?php
/**
 * Printable event flyer. Renders a standalone, print-optimized page (letter size)
 * that staff or patrons can print or "Save as PDF" to share.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PLC_Flyer {

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * Flyer URL for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return string
	 */
	public static function url( $event_id ) {
		return add_query_arg( 'plc_flyer', (int) $event_id, home_url( '/' ) );
	}

	/**
	 * Render the flyer when ?plc_flyer=ID is requested.
	 */
	public static function maybe_render() {
		if ( empty( $_GET['plc_flyer'] ) ) {
			return;
		}
		$event_id = absint( $_GET['plc_flyer'] );
		$event    = get_post( $event_id );
		if ( ! $event || PLC_Post_Type::POST_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			status_header( 404 );
			exit;
		}

		$title     = get_the_title( $event_id );
		$permalink = get_permalink( $event_id );
		$location  = get_post_meta( $event_id, '_plc_location', true );
		$start     = get_post_meta( $event_id, '_plc_start', true );
		$end       = get_post_meta( $event_id, '_plc_end', true );
		$all_day   = (int) get_post_meta( $event_id, '_plc_all_day', true );

		$start_ts = PLC_Meta_Boxes::to_timestamp( $start );
		$end_ts   = PLC_Meta_Boxes::to_timestamp( $end );

		$date_str = '';
		$time_str = '';
		if ( $start_ts ) {
			$date_str = wp_date( get_option( 'date_format' ), $start_ts );
			if ( $all_day ) {
				$time_str = __( 'All day', 'plc' );
			} else {
				$time_str = wp_date( get_option( 'time_format' ), $start_ts );
				if ( $end_ts ) {
					$same_day = wp_date( 'Y-m-d', $start_ts ) === wp_date( 'Y-m-d', $end_ts );
					$time_str .= ' – ' . ( $same_day ? wp_date( get_option( 'time_format' ), $end_ts ) : ( wp_date( get_option( 'date_format' ), $end_ts ) . ' ' . wp_date( get_option( 'time_format' ), $end_ts ) ) );
				}
			}
		}

		$description = wp_strip_all_tags( get_the_excerpt( $event_id ) );
		$qr          = PLC_Shortcodes::qr_image_url( $permalink, 320 );
		$site        = get_bloginfo( 'name' );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		self::output( compact( 'title', 'permalink', 'location', 'date_str', 'time_str', 'description', 'qr', 'site' ) );
		exit;
	}

	/**
	 * Output the flyer HTML document.
	 *
	 * @param array $f Flyer fields.
	 */
	private static function output( $f ) {
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $f['title'] ); ?> — <?php esc_html_e( 'Event Flyer', 'plc' ); ?></title>
<style>
	* { box-sizing: border-box; }
	html, body { margin: 0; padding: 0; background: #f3f4f6; color: #18181b;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
	.plc-toolbar { position: sticky; top: 0; display: flex; gap: 10px; justify-content: center;
		padding: 12px; background: #18181b; }
	.plc-toolbar button, .plc-toolbar a { font: inherit; font-weight: 600; cursor: pointer;
		border: 0; border-radius: 8px; padding: 10px 18px; text-decoration: none; }
	.plc-toolbar .pr { background: #fff; color: #18181b; }
	.plc-toolbar .cl { background: transparent; color: #fff; border: 1px solid #52525b; }
	.flyer { width: 8.5in; min-height: 11in; margin: 20px auto; background: #fff; padding: 0.9in 0.85in;
		box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
	.flyer__brand { text-transform: uppercase; letter-spacing: 0.12em; font-size: 13pt; color: #52525b;
		border-bottom: 3px solid #18181b; padding-bottom: 14px; margin-bottom: 36px; }
	.flyer__title { font-size: 38pt; line-height: 1.08; margin: 0 0 28px; color: #18181b; }
	.flyer__meta { font-size: 16pt; line-height: 1.6; margin: 0 0 28px; }
	.flyer__meta .lbl { display: inline-block; width: 1.5em; }
	.flyer__desc { font-size: 13.5pt; line-height: 1.55; color: #27272a; margin: 0 0 32px; }
	.flyer__foot { display: flex; align-items: center; gap: 28px; margin-top: 40px;
		padding-top: 24px; border-top: 1px solid #d4d4d8; }
	.flyer__qr { flex: 0 0 auto; text-align: center; }
	.flyer__qr img { width: 1.7in; height: 1.7in; display: block; }
	.flyer__qr span { display: block; font-size: 10pt; color: #52525b; margin-top: 6px; }
	.flyer__cta { font-size: 13pt; color: #27272a; }
	.flyer__cta strong { display: block; font-size: 15pt; margin-bottom: 4px; color: #18181b; }
	.flyer__url { word-break: break-all; color: #3f3f46; }

	@media print {
		@page { size: letter; margin: 0; }
		html, body { background: #fff; }
		.plc-toolbar { display: none; }
		.flyer { width: auto; min-height: auto; margin: 0; box-shadow: none; padding: 0.6in; }
	}
</style>
</head>
<body>
	<div class="plc-toolbar">
		<button class="pr" onclick="window.print()"><?php esc_html_e( '🖨 Print / Save as PDF', 'plc' ); ?></button>
		<a class="cl" href="<?php echo esc_url( $f['permalink'] ); ?>"><?php esc_html_e( 'Back to event', 'plc' ); ?></a>
	</div>

	<div class="flyer">
		<div class="flyer__brand"><?php echo esc_html( $f['site'] ); ?></div>
		<h1 class="flyer__title"><?php echo esc_html( $f['title'] ); ?></h1>

		<p class="flyer__meta">
			<?php if ( $f['date_str'] ) : ?>
				<span class="lbl" aria-hidden="true">📅</span><strong><?php echo esc_html( $f['date_str'] ); ?></strong><br>
			<?php endif; ?>
			<?php if ( $f['time_str'] ) : ?>
				<span class="lbl" aria-hidden="true">🕑</span><?php echo esc_html( $f['time_str'] ); ?><br>
			<?php endif; ?>
			<?php if ( $f['location'] ) : ?>
				<span class="lbl" aria-hidden="true">📍</span><?php echo esc_html( $f['location'] ); ?>
			<?php endif; ?>
		</p>

		<?php if ( $f['description'] ) : ?>
			<p class="flyer__desc"><?php echo esc_html( $f['description'] ); ?></p>
		<?php endif; ?>

		<div class="flyer__foot">
			<div class="flyer__qr">
				<img src="<?php echo esc_url( $f['qr'] ); ?>" alt="<?php esc_attr_e( 'Scan to view event', 'plc' ); ?>">
				<span><?php esc_html_e( 'Scan to register', 'plc' ); ?></span>
			</div>
			<div class="flyer__cta">
				<strong><?php esc_html_e( 'Register &amp; details', 'plc' ); ?></strong>
				<span class="flyer__url"><?php echo esc_html( $f['permalink'] ); ?></span>
			</div>
		</div>
	</div>
</body>
</html>
		<?php
	}
}
