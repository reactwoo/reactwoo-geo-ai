<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$option_key = RWGA_Settings::OPTION_KEY;
$settings   = RWGA_Settings::get_settings();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-license';
?>
<div class="wrap rwgc-wrap rwga-wrap">
	<h1><?php esc_html_e( 'Geo AI — ReactWoo API', 'reactwoo-geo-ai' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Enter your ReactWoo product license and API base here. Geo Core (WordPress.org) does not store commercial credentials; this satellite plugin follows the same JWT login pattern as other ReactWoo commercial plugins.', 'reactwoo-geo-ai' ); ?>
	</p>
	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card" style="max-width: 720px;">
	<form method="post" action="options.php">
		<?php settings_fields( 'rwga_license_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwga_reactwoo_api_base"><?php esc_html_e( 'API base URL', 'reactwoo-geo-ai' ); ?></label></th>
				<td>
					<input type="url" id="rwga_reactwoo_api_base" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_api_base]" value="<?php echo esc_attr( isset( $settings['reactwoo_api_base'] ) ? $settings['reactwoo_api_base'] : 'https://api.reactwoo.com' ); ?>" class="regular-text code" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwga_reactwoo_license_key"><?php esc_html_e( 'ReactWoo product license key', 'reactwoo-geo-ai' ); ?></label></th>
				<td>
					<input type="password" id="rwga_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="<?php echo esc_attr( isset( $settings['reactwoo_license_key'] ) ? $settings['reactwoo_license_key'] : '' ); ?>" class="regular-text" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Leave blank to keep the current saved key.', 'reactwoo-geo-ai' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	</div>
</div>
