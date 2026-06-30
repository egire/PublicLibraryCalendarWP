<?php
/**
 * Public registration form. Included from PLC_Shortcodes::render_registration_block().
 *
 * Expects $event_id in scope.
 *
 * @package PublicLibraryCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var int $event_id */

$plc_collect_phone = (int) PLC_Settings::get( 'collect_phone' );
$plc_require_phone = (int) PLC_Settings::get( 'require_phone' );
$plc_max_party     = (int) PLC_Settings::get( 'max_party_size' );
?>
<form class="plc-register-form" method="post" novalidate>
	<div class="plc-form-row">
		<label for="plc-name-<?php echo esc_attr( $event_id ); ?>"><?php esc_html_e( 'Full name', 'plc' ); ?> <span class="plc-req">*</span></label>
		<input type="text" id="plc-name-<?php echo esc_attr( $event_id ); ?>" name="name" required autocomplete="name">
	</div>

	<div class="plc-form-row">
		<label for="plc-email-<?php echo esc_attr( $event_id ); ?>"><?php esc_html_e( 'Email address', 'plc' ); ?> <span class="plc-req">*</span></label>
		<input type="email" id="plc-email-<?php echo esc_attr( $event_id ); ?>" name="email" required autocomplete="email">
		<small class="plc-help"><?php esc_html_e( 'We will send your confirmation and any updates here.', 'plc' ); ?></small>
	</div>

	<div class="plc-form-grid">
		<?php if ( $plc_collect_phone ) : ?>
			<div class="plc-form-row">
				<label for="plc-phone-<?php echo esc_attr( $event_id ); ?>">
					<?php
					echo $plc_require_phone
						? esc_html__( 'Phone', 'plc' ) . ' <span class="plc-req">*</span>'
						: esc_html__( 'Phone (optional)', 'plc' );
					?>
				</label>
				<input type="tel" id="plc-phone-<?php echo esc_attr( $event_id ); ?>" name="phone" autocomplete="tel" <?php echo $plc_require_phone ? 'required' : ''; ?>>
			</div>
		<?php endif; ?>
		<div class="plc-form-row">
			<label for="plc-party-<?php echo esc_attr( $event_id ); ?>"><?php esc_html_e( 'Number attending', 'plc' ); ?></label>
			<input type="number" id="plc-party-<?php echo esc_attr( $event_id ); ?>" name="party_size" value="1" min="1" max="<?php echo esc_attr( $plc_max_party ); ?>">
		</div>
	</div>

	<div class="plc-form-row">
		<label for="plc-notes-<?php echo esc_attr( $event_id ); ?>"><?php esc_html_e( 'Notes / accessibility needs (optional)', 'plc' ); ?></label>
		<textarea id="plc-notes-<?php echo esc_attr( $event_id ); ?>" name="notes" rows="2"></textarea>
	</div>

	<?php // Honeypot field — hidden from humans, tempting to bots. ?>
	<div class="plc-hp" aria-hidden="true">
		<label for="plc-website-<?php echo esc_attr( $event_id ); ?>"><?php esc_html_e( 'Website', 'plc' ); ?></label>
		<input type="text" id="plc-website-<?php echo esc_attr( $event_id ); ?>" name="plc_website" tabindex="-1" autocomplete="off">
	</div>

	<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">

	<div class="plc-form-actions">
		<button type="submit" class="plc-btn plc-btn-primary"><?php esc_html_e( 'Register', 'plc' ); ?></button>
		<span class="plc-form-message" role="status" aria-live="polite"></span>
	</div>
</form>
