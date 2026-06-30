<?php
/**
 * Single event card for the calendar list view.
 *
 * Override by copying to: your-theme/public-library-calendar/event-card.php
 *
 * Available variables:
 *
 * @var int    $event_id  Event ID.
 * @var string $permalink Event URL.
 * @var string $title     Event title (unescaped).
 * @var string $excerpt   Trimmed excerpt (unescaped).
 * @var string $day       Day-of-month number.
 * @var string $dow       Abbreviated weekday.
 * @var string $time      Formatted time or "All day".
 * @var string $location  Location (unescaped).
 * @var string $badge     Pre-built badge HTML (already escaped).
 * @var string $cats      Pre-built category links HTML (already escaped).
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="plc-event-card">
	<div class="plc-date-chip" aria-hidden="true">
		<span class="plc-dow"><?php echo esc_html( $dow ); ?></span>
		<span class="plc-day"><?php echo esc_html( $day ); ?></span>
	</div>
	<div class="plc-event-body">
		<h4 class="plc-event-title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h4>
		<p class="plc-event-meta">
			<span class="plc-time">🕑 <?php echo esc_html( $time ); ?></span>
			<?php if ( $location ) : ?>
				<span class="plc-loc">📍 <?php echo esc_html( $location ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( $cats ) : ?>
			<p class="plc-event-cats"><?php echo wp_kses_post( $cats ); ?></p>
		<?php endif; ?>
		<?php if ( $excerpt ) : ?>
			<p class="plc-event-excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>
		<p class="plc-event-actions">
			<a class="plc-btn plc-btn-outline" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Details &amp; sign up', 'plc' ); ?></a>
			<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>
	</div>
</article>
