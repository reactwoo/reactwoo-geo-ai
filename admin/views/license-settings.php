<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$option_key = RWGA_Settings::OPTION_KEY;
$settings   = RWGA_Settings::get_settings();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-license';

$summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
$cache   = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_cache() : null;
$import_sources = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::get_manual_import_sources() : array();

$lic_ok = ! empty( $summary['license_configured'] );
$last_refresh = ( null !== $cache && ! empty( $cache['refreshed_at_gmt'] ) ) ? (string) $cache['refreshed_at_gmt'] : __( 'Never', 'reactwoo-geo-ai' );

$refresh_url = wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=ai_usage' ), 'rwga_dash_ai_usage' );
$connect_hint = __( 'Connect your ReactWoo plan here. Usage and tokens are tied to this key on this site.', 'reactwoo-geo-ai' );

?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--license">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Settings', 'reactwoo-geo-ai' ),
			__( 'License, usage, and connection. Advanced API overrides stay under Advanced.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI — Settings', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php settings_errors( 'rwga_geo_ai' ); ?>
	<?php RWGA_Admin::render_usage_refresh_notices(); ?>

	<?php if ( ! empty( $_GET['rwga_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwga_imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key imported into Geo AI.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwga_import_err'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['rwga_import_err'] ) ) ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-grid" style="align-items: flex-start;">
		<div class="rwgc-card" style="max-width: 560px;">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_section_header(
					__( 'License & connection', 'reactwoo-geo-ai' ),
					$connect_hint
				);
			} else {
				echo '<h2>' . esc_html__( 'Product license', 'reactwoo-geo-ai' ) . '</h2>';
			}
			?>

			<p style="margin: 12px 0;">
				<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
					<?php
					RWGC_Admin_UI::render_badge(
						$lic_ok ? __( 'Connected', 'reactwoo-geo-ai' ) : __( 'Not configured', 'reactwoo-geo-ai' ),
						$lic_ok ? 'success' : 'warning'
					);
					?>
				<?php else : ?>
					<strong><?php echo $lic_ok ? esc_html__( 'Key on file', 'reactwoo-geo-ai' ) : esc_html__( 'Not configured', 'reactwoo-geo-ai' ); ?></strong>
				<?php endif; ?>
			</p>

			<dl class="rwga-license-dl">
				<dt><?php esc_html_e( 'Last usage refresh', 'reactwoo-geo-ai' ); ?></dt>
				<dd><?php echo esc_html( $last_refresh ); ?></dd>
				<?php if ( null !== $cache && class_exists( 'RWGA_Usage', false ) ) : ?>
					<?php
					$plan_line = RWGA_Usage::format_plan_label( $cache );
					if ( '' !== $plan_line ) :
						?>
					<dt><?php esc_html_e( 'Plan', 'reactwoo-geo-ai' ); ?></dt>
					<dd><?php echo esc_html( $plan_line ); ?></dd>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( null !== $cache && isset( $cache['used'], $cache['limit'] ) ) : ?>
					<dt><?php esc_html_e( 'Usage (this period)', 'reactwoo-geo-ai' ); ?></dt>
					<dd><?php echo esc_html( (string) (int) $cache['used'] . ' / ' . (int) $cache['limit'] ); ?></dd>
				<?php endif; ?>
			</dl>
			<p class="description"><?php esc_html_e( 'After upgrading your ReactWoo plan, save the license here and refresh usage so limits stay accurate.', 'reactwoo-geo-ai' ); ?></p>

			<form method="post" action="options.php" class="rwga-license-form">
				<?php settings_fields( 'rwga_license_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwga_form_scope]" value="license" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwga_reactwoo_license_key"><?php esc_html_e( 'License key', 'reactwoo-geo-ai' ); ?></label></th>
						<td>
							<input type="password" id="rwga_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'reactwoo-geo-ai' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved key.', 'reactwoo-geo-ai' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="rwgc-actions">
					<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Save license', 'reactwoo-geo-ai' ); ?></button>
				</p>
			</form>
		</div>

		<div class="rwgc-card" style="max-width: 560px;">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_section_header(
					__( 'Import & usage', 'reactwoo-geo-ai' ),
					__( 'Optionally copy a key from another ReactWoo plugin on this site, refresh plan limits from the API, or disconnect.', 'reactwoo-geo-ai' )
				);
			} else {
				echo '<h2>' . esc_html__( 'Import & usage', 'reactwoo-geo-ai' ) . '</h2>';
			}
			?>
			<?php if ( ! empty( $import_sources ) ) : ?>
				<p class="rwgc-actions rwga-license-actions">
					<?php foreach ( $import_sources as $source => $label ) : ?>
						<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=import_license&source=' . rawurlencode( $source ) ), 'rwga_import_license' ) ); ?>"><?php echo esc_html( sprintf( __( 'Import from %s', 'reactwoo-geo-ai' ), $label ) ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<p class="rwgc-actions rwga-license-actions">
				<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Refresh usage', 'reactwoo-geo-ai' ); ?></a>
				<?php if ( $lic_ok ) : ?>
					<a class="rwgc-btn rwgc-btn--danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=clear_license' ), 'rwga_clear_license' ) ); ?>" onclick="return window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-ai' ) ); ?>);"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-ai' ); ?></a>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( 'Subscription and billing are managed in your ReactWoo account.', 'reactwoo-geo-ai' ); ?></p>
		</div>
	</div>
</div>
