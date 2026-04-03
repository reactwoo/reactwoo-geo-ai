<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_summary = isset( $rwga_summary ) && is_array( $rwga_summary ) ? $rwga_summary : array();
$rwga_stats   = isset( $rwga_stats ) && is_array( $rwga_stats ) ? $rwga_stats : array();
$rwga_usage   = isset( $rwga_usage ) && is_array( $rwga_usage ) ? $rwga_usage : array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Geo AI', 'reactwoo-geo-ai' ); ?></h1>

	<?php settings_errors( 'rwga_dashboard' ); ?>

	<p><?php esc_html_e( 'AI-assisted variant drafts use the ReactWoo API. Store your API base URL and product license key in Geo Core — this screen summarizes status and runs the same reachability checks as Geo Core → Tools.', 'reactwoo-geo-ai' ); ?></p>

	<h2><?php esc_html_e( 'Connection (Geo Core)', 'reactwoo-geo-ai' ); ?></h2>
	<?php if ( ! empty( $rwga_summary['core_ready'] ) ) : ?>
		<table class="widefat striped" style="max-width: 640px;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'API base', 'reactwoo-geo-ai' ); ?></th>
					<td><code><?php echo esc_html( (string) $rwga_summary['api_base'] ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'License key', 'reactwoo-geo-ai' ); ?></th>
					<td><?php echo ! empty( $rwga_summary['license_configured'] ) ? esc_html__( 'Configured', 'reactwoo-geo-ai' ) : esc_html__( 'Not set', 'reactwoo-geo-ai' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Geo Core REST API', 'reactwoo-geo-ai' ); ?></th>
					<td><?php echo ! empty( $rwga_summary['rest_enabled'] ) ? esc_html__( 'Enabled', 'reactwoo-geo-ai' ) : esc_html__( 'Disabled', 'reactwoo-geo-ai' ); ?></td>
				</tr>
				<?php
				$rest_capabilities_url = isset( $rest_capabilities_url ) && is_string( $rest_capabilities_url ) ? $rest_capabilities_url : '';
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'REST capabilities (discovery)', 'reactwoo-geo-ai' ); ?></th>
					<td>
						<?php if ( '' !== $rest_capabilities_url ) : ?>
							<a href="<?php echo esc_url( $rest_capabilities_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open JSON', 'reactwoo-geo-ai' ); ?></a>
							<code style="display:block;margin-top:6px;word-break:break-all;font-size:11px;"><?php echo esc_html( $rest_capabilities_url ); ?></code>
						<?php else : ?>
							<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php $rest_location_url = isset( $rest_location_url ) && is_string( $rest_location_url ) ? $rest_location_url : ''; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'REST location (visitor)', 'reactwoo-geo-ai' ); ?></th>
					<td>
						<?php if ( '' !== $rest_location_url ) : ?>
							<a href="<?php echo esc_url( $rest_location_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open JSON', 'reactwoo-geo-ai' ); ?></a>
							<code style="display:block;margin-top:6px;word-break:break-all;font-size:11px;"><?php echo esc_html( $rest_location_url ); ?></code>
						<?php else : ?>
							<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Edit in Geo Core → Settings', 'reactwoo-geo-ai' ); ?></a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'Geo Core settings are not available.', 'reactwoo-geo-ai' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Assistant token usage', 'reactwoo-geo-ai' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Monthly token usage from the ReactWoo API (same data as “Test authenticated assistant usage”). Values refresh when that check succeeds.', 'reactwoo-geo-ai' ); ?></p>
	<?php if ( ! empty( $rwga_usage ) ) : ?>
		<table class="widefat striped" style="max-width: 640px;">
			<tbody>
				<?php foreach ( $rwga_usage as $uk => $uv ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $uk ); ?></th>
						<td><code><?php echo esc_html( (string) $uv ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No cached usage yet. Run “Test authenticated assistant usage” below after configuring your license in Geo Core.', 'reactwoo-geo-ai' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $rwga_stats ) ) : ?>
		<h2><?php esc_html_e( 'Integration snapshot', 'reactwoo-geo-ai' ); ?></h2>
		<table class="widefat striped" style="max-width: 640px;">
			<tbody>
				<?php foreach ( $rwga_stats as $k => $v ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $k ); ?></th>
						<td><code><?php echo esc_html( is_scalar( $v ) || null === $v ? (string) $v : wp_json_encode( $v ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'API checks', 'reactwoo-geo-ai' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Public health uses POST /ai/health. Usage requires a valid license and calls the authenticated assistant usage endpoint.', 'reactwoo-geo-ai' ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-dashboard&rwga_action=ai_health' ), 'rwga_dash_ai_health' ) ); ?>"><?php esc_html_e( 'Test AI service reachability', 'reactwoo-geo-ai' ); ?></a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-dashboard&rwga_action=ai_usage' ), 'rwga_dash_ai_usage' ) ); ?>"><?php esc_html_e( 'Test authenticated assistant usage', 'reactwoo-geo-ai' ); ?></a>
	</p>

	<h2><?php esc_html_e( 'Geo Core shortcuts', 'reactwoo-geo-ai' ); ?></h2>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-tools' ) ); ?>" class="button"><?php esc_html_e( 'Tools (cache, DB, AI)', 'reactwoo-geo-ai' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>" class="button"><?php esc_html_e( 'Usage guide', 'reactwoo-geo-ai' ); ?></a>
	</p>

	<h2><?php esc_html_e( 'Editor workflow (pages)', 'reactwoo-geo-ai' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Variant drafts apply to the page post type. Use your own block or app to POST to the REST route below, or open a page in the editor to integrate from there.', 'reactwoo-geo-ai' ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'All pages', 'reactwoo-geo-ai' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Add new page', 'reactwoo-geo-ai' ); ?></a>
	</p>

	<h2><?php esc_html_e( 'Variant draft endpoint (editors)', 'reactwoo-geo-ai' ); ?></h2>
	<p class="description"><?php esc_html_e( 'POST with authenticated user (edit_pages). Body: page_id, optional instructions, optional country_iso2.', 'reactwoo-geo-ai' ); ?></p>
	<?php if ( is_string( $variant_draft_url ) && '' !== $variant_draft_url ) : ?>
		<p><code><?php echo esc_html( $variant_draft_url ); ?></code></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Enable REST in Geo Core → Settings to expose this route.', 'reactwoo-geo-ai' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Hooks', 'reactwoo-geo-ai' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Extend payloads and responses from Geo Core: rwgc_ai_variant_draft_payload, rwgc_ai_variant_draft_response. Integrations: filter rwga_stats_snapshot (see RWGA_Stats::get_snapshot()). Cached usage table: rwga_usage_display_rows.', 'reactwoo-geo-ai' ); ?></p>
</div>
