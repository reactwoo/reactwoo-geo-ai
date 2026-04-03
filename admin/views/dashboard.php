<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_summary = isset( $rwga_summary ) && is_array( $rwga_summary ) ? $rwga_summary : array();
$rwga_stats   = isset( $rwga_stats ) && is_array( $rwga_stats ) ? $rwga_stats : array();
$rwga_usage   = isset( $rwga_usage ) && is_array( $rwga_usage ) ? $rwga_usage : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGA_Admin::MENU_PARENT;
$rest_capabilities_url = isset( $rest_capabilities_url ) && is_string( $rest_capabilities_url ) ? $rest_capabilities_url : '';
$rest_location_url     = isset( $rest_location_url ) && is_string( $rest_location_url ) ? $rest_location_url : '';
$rest_v1_base          = isset( $rest_v1_base ) && is_string( $rest_v1_base ) ? $rest_v1_base : '';
$variant_draft_url     = isset( $variant_draft_url ) && is_string( $variant_draft_url ) ? $variant_draft_url : '';
?>
<div class="wrap rwgc-wrap rwga-wrap">
	<h1><?php esc_html_e( 'Geo AI', 'reactwoo-geo-ai' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Connects WordPress to the ReactWoo API for AI-assisted variant drafts. Your product license and API base are on License & API — not in Geo Core Settings (those are for MaxMind only).', 'reactwoo-geo-ai' ); ?></p>
	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php settings_errors( 'rwga_dashboard' ); ?>

	<div class="rwga-hero">
		<h2><?php esc_html_e( 'Get started', 'reactwoo-geo-ai' ); ?></h2>
		<ol class="rwga-steps">
			<li><?php esc_html_e( 'Enter your API base and license key under License & API.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Enable REST in Geo Core → Settings if you use the variant-draft route.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Run the tests below to confirm reachability and usage.', 'reactwoo-geo-ai' ); ?></li>
		</ol>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="button button-primary"><?php esc_html_e( 'License & API', 'reactwoo-geo-ai' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-help' ) ); ?>" class="button"><?php esc_html_e( 'Help', 'reactwoo-geo-ai' ); ?></a>
		</p>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Connection summary', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( ! empty( $rwga_summary['core_ready'] ) ) : ?>
			<table class="widefat striped">
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
						<th scope="row"><?php esc_html_e( 'Geo Core REST', 'reactwoo-geo-ai' ); ?></th>
						<td><?php echo ! empty( $rwga_summary['rest_enabled'] ) ? esc_html__( 'Enabled', 'reactwoo-geo-ai' ) : esc_html__( 'Disabled', 'reactwoo-geo-ai' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Edit license & API', 'reactwoo-geo-ai' ); ?></a>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Geo Core settings are not available.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Assistant token usage', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Monthly usage from the ReactWoo API (refreshes when “Test authenticated assistant usage” succeeds).', 'reactwoo-geo-ai' ); ?></p>
		<?php if ( ! empty( $rwga_usage ) ) : ?>
			<table class="widefat striped">
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
			<p><?php esc_html_e( 'No cached usage yet. Run “Test authenticated assistant usage” after configuring your license on License & API.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $rwga_stats ) ) : ?>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Integration snapshot', 'reactwoo-geo-ai' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $rwga_stats as $k => $v ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) $k ); ?></th>
							<td><code><?php echo esc_html( is_scalar( $v ) || null === $v ? (string) $v : wp_json_encode( $v ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'API checks', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'AI reachability uses POST /ai/health. Authenticated usage requires a valid license.', 'reactwoo-geo-ai' ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-dashboard&rwga_action=ai_health' ), 'rwga_dash_ai_health' ) ); ?>"><?php esc_html_e( 'Test AI service reachability', 'reactwoo-geo-ai' ); ?></a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-dashboard&rwga_action=ai_usage' ), 'rwga_dash_ai_usage' ) ); ?>"><?php esc_html_e( 'Test authenticated assistant usage', 'reactwoo-geo-ai' ); ?></a>
		</p>
		<h3><?php esc_html_e( 'Geo Core REST (local smoke test)', 'reactwoo-geo-ai' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Empty POST to variant-draft — expect HTTP 400 until page_id is supplied (no outbound AI call).', 'reactwoo-geo-ai' ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-dashboard&rwga_action=rest_post_smoke' ), 'rwga_dash_rest_post_smoke' ) ); ?>"><?php esc_html_e( 'Test variant-draft REST (validation only)', 'reactwoo-geo-ai' ); ?></a>
		</p>
	</div>

	<details class="rwga-dev-details">
		<summary><?php esc_html_e( 'URLs, editor workflow, and hooks (developers)', 'reactwoo-geo-ai' ); ?></summary>
		<div class="rwgc-card" style="box-shadow:none;border:0;padding:12px 0 0;">
			<h3><?php esc_html_e( 'REST URLs', 'reactwoo-geo-ai' ); ?></h3>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Capabilities JSON', 'reactwoo-geo-ai' ); ?></th>
						<td>
							<?php if ( '' !== $rest_capabilities_url ) : ?>
								<a href="<?php echo esc_url( $rest_capabilities_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'reactwoo-geo-ai' ); ?></a>
								<code style="display:block;margin-top:6px;word-break:break-all;font-size:11px;"><?php echo esc_html( $rest_capabilities_url ); ?></code>
							<?php else : ?>
								<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'REST location (visitor)', 'reactwoo-geo-ai' ); ?></th>
						<td>
							<?php if ( '' !== $rest_location_url ) : ?>
								<a href="<?php echo esc_url( $rest_location_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'reactwoo-geo-ai' ); ?></a>
								<code style="display:block;margin-top:6px;word-break:break-all;font-size:11px;"><?php echo esc_html( $rest_location_url ); ?></code>
							<?php else : ?>
								<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'REST API v1 base', 'reactwoo-geo-ai' ); ?></th>
						<td>
							<?php if ( '' !== $rest_v1_base ) : ?>
								<code style="word-break:break-all;font-size:11px;"><?php echo esc_html( $rest_v1_base ); ?></code>
							<?php else : ?>
								<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-tools' ) ); ?>" class="button"><?php esc_html_e( 'Geo Core → Tools', 'reactwoo-geo-ai' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>" class="button"><?php esc_html_e( 'Geo Core → Usage', 'reactwoo-geo-ai' ); ?></a>
		</p>
		<h3><?php esc_html_e( 'Pages', 'reactwoo-geo-ai' ); ?></h3>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'All pages', 'reactwoo-geo-ai' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Add new page', 'reactwoo-geo-ai' ); ?></a>
		</p>
		<h3><?php esc_html_e( 'Variant draft endpoint', 'reactwoo-geo-ai' ); ?></h3>
		<p class="description"><?php esc_html_e( 'POST with authenticated user (edit_pages). Body: page_id, optional instructions, optional country_iso2.', 'reactwoo-geo-ai' ); ?></p>
		<?php if ( is_string( $variant_draft_url ) && '' !== $variant_draft_url ) : ?>
			<p><code><?php echo esc_html( $variant_draft_url ); ?></code></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Enable REST in Geo Core → Settings to expose this route.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Hooks', 'reactwoo-geo-ai' ); ?></h3>
		<p class="description"><?php esc_html_e( 'rwgc_ai_variant_draft_payload, rwgc_ai_variant_draft_response; filter rwga_stats_snapshot; rwga_usage_display_rows.', 'reactwoo-geo-ai' ); ?></p>
	</details>
</div>
