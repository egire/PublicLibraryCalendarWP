<?php
/**
 * Event detail block shown above the content on a single event page.
 *
 * Override by copying to: your-theme/public-library-calendar/event-details.php
 *
 * Available variables:
 *
 * @var int    $event_id Event ID.
 * @var string $when     Formatted date/time range (unescaped).
 * @var string $location Location (unescaped).
 * @var string $ics_url  "Add to calendar" URL, or empty to hide the button.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="plc-event-detail">
	<ul class="plc-detail-list">
		<?php if ( $when ) : ?>
			<li><strong><?php esc_html_e( 'When', 'plc' ); ?>:</strong> <?php echo esc_html( $when ); ?></li>
		<?php endif; ?>
		<?php if ( $location ) : ?>
			<li><strong><?php esc_html_e( 'Where', 'plc' ); ?>:</strong> <?php echo esc_html( $location ); ?></li>
		<?php endif; ?>
	</ul>
	<?php if ( $ics_url ) : ?>
		<p class="plc-addcal">
			<a class="plc-btn plc-btn-outline" href="<?php echo esc_url( $ics_url ); ?>">
				<?php esc_html_e( '📅 Add to calendar', 'plc' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
