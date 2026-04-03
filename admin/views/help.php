<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-help';
?>
<div class="wrap rwgc-wrap rwga-wrap">
	<h1><?php esc_html_e( 'Geo AI — help', 'reactwoo-geo-ai' ); ?></h1>
	<p class="description"><?php esc_html_e( 'How Geo AI fits next to Geo Core and where credentials live.', 'reactwoo-geo-ai' ); ?></p>
	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card rwgc-card--highlight">
		<h2><?php esc_html_e( 'MaxMind vs ReactWoo API', 'reactwoo-geo-ai' ); ?></h2>
		<ul class="rwgc-docs-list">
			<li>
				<strong><?php esc_html_e( 'Geo Core → Settings', 'reactwoo-geo-ai' ); ?></strong>
				— <?php esc_html_e( 'MaxMind / GeoLite2 key powers IP lookups only.', 'reactwoo-geo-ai' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Geo AI → License', 'reactwoo-geo-ai' ); ?></strong>
				— <?php esc_html_e( 'ReactWoo product license for AI-assisted drafts and usage. Not stored in Geo Core.', 'reactwoo-geo-ai' ); ?>
			</li>
		</ul>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Typical setup', 'reactwoo-geo-ai' ); ?></h2>
			<ol class="rwgc-steps">
				<li><?php esc_html_e( 'Confirm Geo Core can resolve visitor country (Tools / dashboard).', 'reactwoo-geo-ai' ); ?></li>
				<li><?php esc_html_e( 'Enter API base and license on the License & API screen.', 'reactwoo-geo-ai' ); ?></li>
				<li><?php esc_html_e( 'On Overview, run the connection tests and use Geo Core Tools if something fails.', 'reactwoo-geo-ai' ); ?></li>
			</ol>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="button button-primary"><?php esc_html_e( 'License', 'reactwoo-geo-ai' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-dashboard' ) ); ?>" class="button"><?php esc_html_e( 'Overview', 'reactwoo-geo-ai' ); ?></a>
			</p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Who is this for?', 'reactwoo-geo-ai' ); ?></h2>
			<p><?php esc_html_e( 'Teams using the ReactWoo API to draft or suggest geo-aware page variants. Editors still work in WordPress; integrations call Geo Core REST routes that require REST to be enabled in Geo Core.', 'reactwoo-geo-ai' ); ?></p>
		</div>
	</div>
</div>
