<?php
/**
 * Automation rules — list, add, edit, run (dispatches workflows when configured).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows            = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination      = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_workflow_keys   = isset( $rwga_workflow_keys ) && is_array( $rwga_workflow_keys ) ? $rwga_workflow_keys : array();
$rwga_edit_rule       = isset( $rwga_edit_rule ) && is_array( $rwga_edit_rule ) ? $rwga_edit_rule : null;
$rwgc_nav_current     = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-automation';

$can_manage = current_user_can( RWGA_Capabilities::CAP_MANAGE_AUTOMATIONS ) || current_user_can( 'manage_options' );
$can_run    = current_user_can( RWGA_Capabilities::CAP_RUN_AI )
	&& class_exists( 'RWGA_License', false )
	&& RWGA_License::can_run_workflows();

$list_url = admin_url( 'admin.php?page=rwga-automation' );

$edit_notes               = '';
$rwga_auto_page_url       = '';
$rwga_auto_competitor     = '';
$rwga_auto_analysis_focus = 'inherit';
if ( is_array( $rwga_edit_rule ) && isset( $rwga_edit_rule['rule_config'] ) ) {
	$rc  = $rwga_edit_rule['rule_config'];
	$dec = null;
	if ( is_string( $rc ) && '' !== $rc ) {
		$dec = json_decode( $rc, true );
	} elseif ( is_array( $rc ) ) {
		$dec = $rc;
	}
	if ( is_array( $dec ) ) {
		if ( isset( $dec['notes'] ) ) {
			$edit_notes = (string) $dec['notes'];
		}
		if ( isset( $dec['page_url'] ) ) {
			$rwga_auto_page_url = (string) $dec['page_url'];
		}
		if ( isset( $dec['competitor_url'] ) ) {
			$rwga_auto_competitor = (string) $dec['competitor_url'];
		}
		if ( isset( $dec['analysis_focus'] ) ) {
			$af = sanitize_key( (string) $dec['analysis_focus'] );
			if ( in_array( $af, array( 'inherit', 'messaging', 'layout', 'both' ), true ) ) {
				$rwga_auto_analysis_focus = $af;
			}
		}
	}
}
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--automation">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Automation rules', 'reactwoo-geo-ai' ),
			__( 'Schedule hooks for bounded workflows (cron and manual runs execute the workflow when the rule supplies the required fields).', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Automation rules', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwga_auto = isset( $_GET['rwga_auto'] ) ? sanitize_key( wp_unslash( $_GET['rwga_auto'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'saved' === $rwga_auto ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule saved.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'deleted' === $rwga_auto ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule deleted.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'ran' === $rwga_auto ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule run completed.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'unlicensed' === $rwga_auto ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to run rules.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_auto || 'fail' === $rwga_auto ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not complete that action.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'runerr' === $rwga_auto && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	?>

	<?php if ( $can_manage ) : ?>
		<?php if ( is_array( $rwga_edit_rule ) ) : ?>
			<div class="rwgc-card">
				<h2><?php esc_html_e( 'Edit rule', 'reactwoo-geo-ai' ); ?></h2>
				<p><a class="button-link" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel edit', 'reactwoo-geo-ai' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_automation_rule_save" />
					<input type="hidden" name="rule_id" value="<?php echo isset( $rwga_edit_rule['id'] ) ? (int) $rwga_edit_rule['id'] : 0; ?>" />
					<?php wp_nonce_field( 'rwga_automation_rule_save' ); ?>
					<?php
					rwga_render_automation_rule_fields(
						$rwga_edit_rule,
						$edit_notes,
						$rwga_workflow_keys,
						$rwga_auto_page_url,
						$rwga_auto_competitor,
						$rwga_auto_analysis_focus
					);
					?>
					<?php submit_button( __( 'Update rule', 'reactwoo-geo-ai' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		<?php else : ?>
			<div class="rwgc-card">
				<h2><?php esc_html_e( 'Add rule', 'reactwoo-geo-ai' ); ?></h2>
				<p class="description"><?php esc_html_e( 'REST: GET/POST/PATCH/DELETE /wp-json/geo-ai/v1/automation/rules — POST …/run to execute the workflow.', 'reactwoo-geo-ai' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_automation_rule_save" />
					<input type="hidden" name="rule_id" value="0" />
					<?php wp_nonce_field( 'rwga_automation_rule_save' ); ?>
					<?php
					rwga_render_automation_rule_fields(
						array(
							'name'          => '',
							'workflow_key'  => '',
							'trigger_type'  => 'manual',
							'target_scope'  => 'site',
							'page_id'       => '',
							'geo_target'    => '',
							'status'        => 'active',
						),
						'',
						$rwga_workflow_keys,
						'',
						'',
						'inherit'
					);
					?>
					<?php submit_button( __( 'Create rule', 'reactwoo-geo-ai' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="rwgc-card">
			<p class="description"><?php esc_html_e( 'You need permission to create or edit automation rules.', 'reactwoo-geo-ai' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Rules', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_rows ) ) : ?>
			<p class="description"><?php esc_html_e( 'No rules yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Trigger', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last run (UTC)', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$edit = add_query_arg( array( 'page' => 'rwga-automation', 'rule_id' => $rid ), admin_url( 'admin.php' ) );
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td>
								<?php if ( $can_manage ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>"><?php echo isset( $row['name'] ) ? esc_html( (string) $row['name'] ) : ''; ?></a>
								<?php else : ?>
									<?php echo isset( $row['name'] ) ? esc_html( (string) $row['name'] ) : ''; ?>
								<?php endif; ?>
							</td>
							<td><code><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : ''; ?></code></td>
							<td><?php echo isset( $row['trigger_type'] ) ? esc_html( (string) $row['trigger_type'] ) : ''; ?></td>
							<td><?php echo isset( $row['status'] ) ? esc_html( (string) $row['status'] ) : ''; ?></td>
							<td><?php echo isset( $row['last_run_at'] ) && $row['last_run_at'] ? esc_html( (string) $row['last_run_at'] ) : '—'; ?></td>
							<td>
								<?php if ( $can_run && isset( $row['status'] ) && 'active' === $row['status'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<input type="hidden" name="action" value="rwga_automation_rule_run" />
										<input type="hidden" name="rule_id" value="<?php echo (int) $rid; ?>" />
										<?php wp_nonce_field( 'rwga_automation_rule_run' ); ?>
										<?php submit_button( __( 'Run', 'reactwoo-geo-ai' ), 'small', 'submit', false ); ?>
									</form>
								<?php endif; ?>
								<?php if ( $can_manage ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'reactwoo-geo-ai' ) ); ?>');">
										<input type="hidden" name="action" value="rwga_automation_rule_delete" />
										<input type="hidden" name="rule_id" value="<?php echo (int) $rid; ?>" />
										<?php wp_nonce_field( 'rwga_automation_rule_delete' ); ?>
										<?php submit_button( __( 'Delete', 'reactwoo-geo-ai' ), 'small delete', 'submit', false ); ?>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = isset( $rwga_pagination['pages'] ) ? (int) $rwga_pagination['pages'] : 1;
			$current     = isset( $rwga_pagination['current'] ) ? (int) $rwga_pagination['current'] : 1;
			if ( $total_pages > 1 ) {
				$base = esc_url_raw( add_query_arg( 'paged', '%#%', $list_url ) );
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links(
					array(
						'base'      => $base,
						'format'    => '',
						'prev_text' => __( '&laquo;', 'reactwoo-geo-ai' ),
						'next_text' => __( '&raquo;', 'reactwoo-geo-ai' ),
						'total'     => $total_pages,
						'current'   => $current,
					)
				);
				echo '</div></div>';
			}
			?>
		<?php endif; ?>
	</div>
</div>
<?php
/**
 * Shared fields for automation rule forms (included from view — not a class method).
 *
 * @param array<string, mixed> $r      Row or defaults.
 * @param string               $notes  Notes for rule_config.
 * @param array<int, string>   $wkeys  Workflow keys.
 * @param string                 $page_url        Optional page URL for ux_analysis when page ID is empty.
 * @param string                 $competitor_url Optional competitor URL for competitor_research.
 * @param string                 $analysis_focus inherit|messaging|layout|both for ux_analysis automation.
 * @return void
 */
if ( ! function_exists( 'rwga_render_automation_rule_fields' ) ) {
	function rwga_render_automation_rule_fields( $r, $notes, $wkeys, $page_url = '', $competitor_url = '', $analysis_focus = 'inherit' ) {
	$name = isset( $r['name'] ) ? (string) $r['name'] : '';
	$wk   = isset( $r['workflow_key'] ) ? (string) $r['workflow_key'] : '';
	$tt   = isset( $r['trigger_type'] ) ? (string) $r['trigger_type'] : 'manual';
	$ts   = isset( $r['target_scope'] ) ? (string) $r['target_scope'] : 'site';
	$pid  = isset( $r['page_id'] ) ? (int) $r['page_id'] : 0;
	$geo  = isset( $r['geo_target'] ) && $r['geo_target'] ? (string) $r['geo_target'] : '';
	$st   = isset( $r['status'] ) ? (string) $r['status'] : 'active';
	?>
	<p>
		<label for="rwga_ar_name"><?php esc_html_e( 'Name', 'reactwoo-geo-ai' ); ?></label><br />
		<input type="text" class="regular-text" name="name" id="rwga_ar_name" required value="<?php echo esc_attr( $name ); ?>" />
	</p>
	<p>
		<label for="rwga_ar_wk"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></label><br />
		<select name="workflow_key" id="rwga_ar_wk" required>
			<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-ai' ); ?></option>
			<?php foreach ( $wkeys as $key ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $wk, $key ); ?>><?php echo esc_html( $key ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="rwga_ar_tt"><?php esc_html_e( 'Trigger', 'reactwoo-geo-ai' ); ?></label><br />
		<select name="trigger_type" id="rwga_ar_tt">
			<option value="manual" <?php selected( $tt, 'manual' ); ?>><?php esc_html_e( 'Manual', 'reactwoo-geo-ai' ); ?></option>
			<option value="schedule" <?php selected( $tt, 'schedule' ); ?>><?php esc_html_e( 'Schedule (WP-Cron)', 'reactwoo-geo-ai' ); ?></option>
		</select>
	</p>
	<p>
		<label for="rwga_ar_ts"><?php esc_html_e( 'Target scope', 'reactwoo-geo-ai' ); ?></label><br />
		<select name="target_scope" id="rwga_ar_ts">
			<option value="site" <?php selected( $ts, 'site' ); ?>><?php esc_html_e( 'Site', 'reactwoo-geo-ai' ); ?></option>
			<option value="page" <?php selected( $ts, 'page' ); ?>><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></option>
		</select>
	</p>
	<p>
		<label for="rwga_ar_pid"><?php esc_html_e( 'Page ID (if scope is page)', 'reactwoo-geo-ai' ); ?></label><br />
		<input type="number" min="0" class="small-text" name="page_id" id="rwga_ar_pid" value="<?php echo $pid > 0 ? (int) $pid : ''; ?>" />
	</p>
	<p>
		<label for="rwga_ar_geo"><?php esc_html_e( 'Geo ISO2 (optional)', 'reactwoo-geo-ai' ); ?></label><br />
		<input type="text" maxlength="2" class="small-text" name="geo_target" id="rwga_ar_geo" value="<?php echo esc_attr( $geo ); ?>" />
	</p>
	<p>
		<label for="rwga_ar_st"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></label><br />
		<select name="status" id="rwga_ar_st">
			<option value="active" <?php selected( $st, 'active' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geo-ai' ); ?></option>
			<option value="paused" <?php selected( $st, 'paused' ); ?>><?php esc_html_e( 'Paused', 'reactwoo-geo-ai' ); ?></option>
		</select>
	</p>
	<p>
		<label for="rwga_ar_purl"><?php esc_html_e( 'Automation page URL (optional)', 'reactwoo-geo-ai' ); ?></label><br />
		<input type="url" class="regular-text" name="rwga_auto_page_url" id="rwga_ar_purl" value="<?php echo esc_attr( $page_url ); ?>" placeholder="https://…" />
		<span class="description"><?php esc_html_e( 'For UX analysis when no page ID is set (site scope).', 'reactwoo-geo-ai' ); ?></span>
	</p>
	<p>
		<label for="rwga_ar_af"><?php esc_html_e( 'UX analysis focus (for UX workflow)', 'reactwoo-geo-ai' ); ?></label><br />
		<select name="rwga_auto_analysis_focus" id="rwga_ar_af">
			<option value="inherit" <?php selected( $analysis_focus, 'inherit' ); ?>><?php esc_html_e( 'Site default (Advanced)', 'reactwoo-geo-ai' ); ?></option>
			<option value="messaging" <?php selected( $analysis_focus, 'messaging' ); ?>><?php esc_html_e( 'Messaging', 'reactwoo-geo-ai' ); ?></option>
			<option value="layout" <?php selected( $analysis_focus, 'layout' ); ?>><?php esc_html_e( 'Layout', 'reactwoo-geo-ai' ); ?></option>
			<option value="both" <?php selected( $analysis_focus, 'both' ); ?>><?php esc_html_e( 'Messaging + layout', 'reactwoo-geo-ai' ); ?></option>
		</select>
		<span class="description"><?php esc_html_e( 'Messaging-only scans typically use fewer API tokens; layout and combined scans ask for more detail (see Advanced).', 'reactwoo-geo-ai' ); ?></span>
	</p>
	<p>
		<label for="rwga_ar_curl"><?php esc_html_e( 'Competitor URL (optional)', 'reactwoo-geo-ai' ); ?></label><br />
		<input type="url" class="regular-text" name="rwga_auto_competitor_url" id="rwga_ar_curl" value="<?php echo esc_attr( $competitor_url ); ?>" placeholder="https://…" />
		<span class="description"><?php esc_html_e( 'Required in rule options for scheduled competitor research.', 'reactwoo-geo-ai' ); ?></span>
	</p>
	<p>
		<label for="rwga_ar_notes"><?php esc_html_e( 'Notes (stored in rule_config)', 'reactwoo-geo-ai' ); ?></label><br />
		<textarea name="rule_notes" id="rwga_ar_notes" class="large-text" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
	</p>
	<?php
	}
}
