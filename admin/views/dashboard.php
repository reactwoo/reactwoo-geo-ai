<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_summary       = isset( $rwga_summary ) && is_array( $rwga_summary ) ? $rwga_summary : array();
$rwga_cache         = isset( $rwga_cache ) && is_array( $rwga_cache ) ? $rwga_cache : null;
$rwga_queue_preview = isset( $rwga_queue_preview ) && is_array( $rwga_queue_preview ) ? $rwga_queue_preview : array();
$rwgc_nav_current   = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGA_Admin::MENU_PARENT;

$lic_ok = ! empty( $rwga_summary['license_configured'] );
$rest_on = ! empty( $rwga_summary['rest_enabled'] );

$plan_label = __( '—', 'reactwoo-geo-ai' );
$usage_hint = __( 'Refresh usage on the License screen or in Advanced.', 'reactwoo-geo-ai' );
if ( null !== $rwga_cache ) {
	$tier = isset( $rwga_cache['license_tier'] ) ? (string) $rwga_cache['license_tier'] : '';
	if ( '' !== $tier ) {
		$plan_label = $tier;
	}
	$used  = isset( $rwga_cache['used'] ) ? (int) $rwga_cache['used'] : 0;
	$limit = isset( $rwga_cache['limit'] ) ? (int) $rwga_cache['limit'] : 0;
	if ( $limit > 0 ) {
		/* translators: 1: used tokens, 2: limit */
		$usage_hint = sprintf( __( '%1$d / %2$d tokens this period', 'reactwoo-geo-ai' ), $used, $limit );
	}
}

$drafts_month  = apply_filters( 'rwga_dashboard_drafts_month_count', null );
$drafts_month  = is_numeric( $drafts_month ) ? (string) (int) $drafts_month : '—';
$awaiting      = apply_filters( 'rwga_dashboard_awaiting_review_count', null );
$awaiting      = is_numeric( $awaiting ) ? (string) (int) $awaiting : '—';

$advanced_url = admin_url( 'admin.php?page=rwga-advanced' );
$license_url  = admin_url( 'admin.php?page=rwga-license' );
$drafts_url   = admin_url( 'admin.php?page=rwga-drafts' );
$pages_url    = admin_url( 'edit.php?post_type=page' );

?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--overview">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			__( 'Create and review geo-aware content drafts using your ReactWoo plan — without exposing raw API wiring in day-to-day screens.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI', 'reactwoo-geo-ai' ); ?></h1>
		<p class="description"><?php esc_html_e( 'AI-assisted geo content drafts for WordPress.', 'reactwoo-geo-ai' ); ?></p>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php settings_errors( 'rwga_geo_ai' ); ?>

	<?php RWGA_Admin::render_suite_satellite_quick_links(); ?>

	<div class="rwga-hero">
		<h2><?php esc_html_e( 'What to do next', 'reactwoo-geo-ai' ); ?></h2>
		<ol class="rwga-steps">
			<li><?php esc_html_e( 'Add your product license under License.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Turn on REST in Geo Core if you use automated variant drafts.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Open your pages and use the block editor tools to generate drafts, then track them in Drafts / Queue.', 'reactwoo-geo-ai' ); ?></li>
		</ol>
	</div>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_stat_grid_open();
		RWGC_Admin_UI::render_stat_card(
			__( 'License', 'reactwoo-geo-ai' ),
			$lic_ok ? __( 'Active', 'reactwoo-geo-ai' ) : __( 'Not set', 'reactwoo-geo-ai' ),
			array(
				'hint' => $lic_ok ? __( 'Key stored for this site', 'reactwoo-geo-ai' ) : __( 'Enter your key under License', 'reactwoo-geo-ai' ),
				'tone' => $lic_ok ? 'success' : 'warning',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Plan / usage', 'reactwoo-geo-ai' ),
			$plan_label,
			array(
				'hint' => $usage_hint,
				'tone' => 'default',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Drafts this month', 'reactwoo-geo-ai' ),
			$drafts_month,
			array(
				'hint' => __( 'Tracked when your site records draft events', 'reactwoo-geo-ai' ),
				'tone' => 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Awaiting review', 'reactwoo-geo-ai' ),
			$awaiting,
			array(
				'hint' => __( 'Open Drafts / Queue for the full list', 'reactwoo-geo-ai' ),
				'tone' => 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_grid_close();
		?>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Quick actions', 'reactwoo-geo-ai' ); ?></h2>
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_quick_actions(
				array(
					array(
						'url'     => $pages_url,
						'label'   => __( 'Open Pages', 'reactwoo-geo-ai' ),
						'primary' => true,
					),
					array(
						'url'   => $drafts_url,
						'label' => __( 'Open draft queue', 'reactwoo-geo-ai' ),
					),
					array(
						'url'   => $license_url,
						'label' => __( 'License & connection', 'reactwoo-geo-ai' ),
					),
					array(
						'url'   => $advanced_url,
						'label' => __( 'Advanced tools', 'reactwoo-geo-ai' ),
					),
				)
			);
		} else {
			echo '<p><a class="button button-primary" href="' . esc_url( $pages_url ) . '">' . esc_html__( 'Open Pages', 'reactwoo-geo-ai' ) . '</a></p>';
		}
		?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Setup checklist', 'reactwoo-geo-ai' ); ?></h2>
		<ul class="rwgc-suite-checklist">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_checklist_row(
					$lic_ok,
					__( 'License key saved', 'reactwoo-geo-ai' ),
					$license_url,
					__( 'Add license', 'reactwoo-geo-ai' )
				);
				RWGC_Admin_UI::render_checklist_row(
					null !== $rwga_cache,
					__( 'Usage snapshot available (refresh after connecting)', 'reactwoo-geo-ai' ),
					$advanced_url,
					__( 'Refresh usage', 'reactwoo-geo-ai' )
				);
				RWGC_Admin_UI::render_checklist_row(
					$rest_on,
					__( 'Geo Core REST enabled for variant drafts', 'reactwoo-geo-ai' ),
					admin_url( 'admin.php?page=rwgc-settings' ),
					__( 'Geo Core settings', 'reactwoo-geo-ai' )
				);
			} else {
				echo '<li>' . esc_html( $lic_ok ? __( 'License: OK', 'reactwoo-geo-ai' ) : __( 'License: needed', 'reactwoo-geo-ai' ) ) . '</li>';
			}
			?>
		</ul>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Queue preview', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Recent draft jobs (extend via rwga_draft_queue_rows).', 'reactwoo-geo-ai' ); ?></p>
		<?php if ( ! empty( $rwga_queue_preview ) ) : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Context', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_queue_preview as $row ) : ?>
						<tr>
							<td><?php echo isset( $row['source_label'] ) ? esc_html( (string) $row['source_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['context_label'] ) ? esc_html( (string) $row['context_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['status_label'] ) ? esc_html( (string) $row['status_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_gmt'] ) ? esc_html( (string) $row['created_gmt'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><a class="button" href="<?php echo esc_url( $drafts_url ); ?>"><?php esc_html_e( 'View full queue', 'reactwoo-geo-ai' ); ?></a></p>
		<?php else : ?>
			<p class="rwga-empty-hint"><?php esc_html_e( 'No queued drafts yet. When integrations record jobs, they will appear here and on Drafts / Queue.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<details class="rwga-dev-details">
		<summary><?php esc_html_e( 'Status details', 'reactwoo-geo-ai' ); ?></summary>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Geo Core REST', 'reactwoo-geo-ai' ); ?></th>
					<td><?php echo $rest_on ? esc_html__( 'Enabled', 'reactwoo-geo-ai' ) : esc_html__( 'Disabled', 'reactwoo-geo-ai' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection', 'reactwoo-geo-ai' ); ?></th>
					<td><?php echo $lic_ok ? esc_html__( 'License key on file', 'reactwoo-geo-ai' ) : esc_html__( 'No license key', 'reactwoo-geo-ai' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p><a class="button" href="<?php echo esc_url( $advanced_url ); ?>"><?php esc_html_e( 'Open Advanced (diagnostics)', 'reactwoo-geo-ai' ); ?></a></p>
	</details>
</div>
