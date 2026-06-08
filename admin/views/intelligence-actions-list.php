<?php
/**
 * Intelligence workflow actions awaiting approval.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows              = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination        = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_filter_status     = isset( $rwga_filter_status ) ? (string) $rwga_filter_status : 'pending';
$rwga_filter_workflow   = isset( $rwga_filter_workflow ) ? (string) $rwga_filter_workflow : '';
$rwgc_nav_current       = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-intelligence-actions';

$list_url = admin_url( 'admin.php?page=rwga-intelligence-actions' );
$can_run  = current_user_can( RWGA_Capabilities::CAP_RUN_AI )
	&& class_exists( 'RWGA_License', false )
	&& RWGA_License::can_run_workflows();
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--intelligence-actions">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Intelligence actions', 'reactwoo-geo-ai' ),
			__( 'Review AI-suggested site changes. Nothing is applied until you approve an action here.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Intelligence actions', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$flash = isset( $_GET['rwga_act'] ) ? sanitize_key( wp_unslash( $_GET['rwga_act'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'applied' === $flash ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Intelligence action applied.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'dismissed' === $flash ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Intelligence action dismissed.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $flash ) {
		$err = isset( $_GET['rwga_err'] ) ? sanitize_text_field( wp_unslash( $_GET['rwga_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ? $err : __( 'Action failed.', 'reactwoo-geo-ai' ) ) . '</p></div>';
	}
	?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-filters" style="margin:1em 0;">
		<input type="hidden" name="page" value="rwga-intelligence-actions" />
		<label>
			<?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?>
			<select name="status">
				<?php
				foreach ( array( 'pending', 'applied', 'dismissed', '' ) as $st ) {
					$label = '' === $st ? __( 'All', 'reactwoo-geo-ai' ) : ucfirst( $st );
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $st ),
						selected( $rwga_filter_status, $st, false ),
						esc_html( $label )
					);
				}
				?>
			</select>
		</label>
		<label style="margin-left:1em;">
			<?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?>
			<input type="text" name="workflow_key" value="<?php echo esc_attr( $rwga_filter_workflow ); ?>" placeholder="<?php esc_attr_e( 'e.g. site_audit', 'reactwoo-geo-ai' ); ?>" />
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'reactwoo-geo-ai' ); ?></button>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Action', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Created', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Operations', 'reactwoo-geo-ai' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rwga_rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No intelligence actions found. Run a site intelligence workflow to generate suggestions.', 'reactwoo-geo-ai' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rwga_rows as $row ) : ?>
					<?php
					$id     = isset( $row['id'] ) ? (int) $row['id'] : 0;
					$label  = isset( $row['label'] ) ? (string) $row['label'] : '';
					$wk     = isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : '';
					$type   = isset( $row['action_type'] ) ? (string) $row['action_type'] : '';
					$status = isset( $row['status'] ) ? (string) $row['status'] : '';
					$created = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
					?>
					<tr>
						<td><?php echo esc_html( (string) $id ); ?></td>
						<td><code><?php echo esc_html( $wk ); ?></code></td>
						<td>
							<strong><?php echo esc_html( $label ); ?></strong>
							<?php if ( '' !== $type ) : ?>
								<br /><span class="description"><code><?php echo esc_html( $type ); ?></code></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $created ); ?></td>
						<td>
							<?php if ( 'pending' === $status && $can_run ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="rwga_intelligence_action_apply" />
									<input type="hidden" name="action_id" value="<?php echo esc_attr( (string) $id ); ?>" />
									<?php wp_nonce_field( 'rwga_intelligence_action_apply' ); ?>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve & apply', 'reactwoo-geo-ai' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:4px;">
									<input type="hidden" name="action" value="rwga_intelligence_action_dismiss" />
									<input type="hidden" name="action_id" value="<?php echo esc_attr( (string) $id ); ?>" />
									<?php wp_nonce_field( 'rwga_intelligence_action_dismiss' ); ?>
									<button type="submit" class="button"><?php esc_html_e( 'Dismiss', 'reactwoo-geo-ai' ); ?></button>
								</form>
							<?php elseif ( 'pending' === $status ) : ?>
								<span class="description"><?php esc_html_e( 'License required to apply.', 'reactwoo-geo-ai' ); ?></span>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $rwga_pagination['pages'] ) && (int) $rwga_pagination['pages'] > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $list_url ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => (int) $rwga_pagination['pages'],
							'current'   => (int) $rwga_pagination['current'],
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
