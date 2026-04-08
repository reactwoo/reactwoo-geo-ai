<?php
/**
 * Single analysis run detail.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_run     = isset( $rwga_run ) && is_array( $rwga_run ) ? $rwga_run : array();
$rwga_findings = isset( $rwga_findings ) && is_array( $rwga_findings ) ? $rwga_findings : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-analyses';

$run_id = isset( $rwga_run['id'] ) ? (int) $rwga_run['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-analyses' );

$severity_class = array(
	'high'   => 'rwga-severity--high',
	'medium' => 'rwga-severity--medium',
	'low'    => 'rwga-severity--low',
);
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--analysis-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			sprintf(
				/* translators: %d: analysis run id */
				__( 'Analysis #%d', 'reactwoo-geo-ai' ),
				$run_id
			),
			__( 'Score, summary, and findings for this run.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Analysis #%d', 'reactwoo-geo-ai' ), $run_id ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<p>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '&larr; All analyses', 'reactwoo-geo-ai' ); ?></a>
		<?php
		$pid = isset( $rwga_run['page_id'] ) ? (int) $rwga_run['page_id'] : 0;
		if ( $pid > 0 && current_user_can( 'edit_post', $pid ) ) {
			$edit = get_edit_post_link( $pid, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				echo ' <a class="button button-primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'reactwoo-geo-ai' ) . '</a>';
			}
		}
		?>
	</p>

	<div class="rwgc-card rwga-analysis-meta">
		<h2><?php esc_html_e( 'Summary', 'reactwoo-geo-ai' ); ?></h2>
		<dl class="rwga-license-dl">
			<dt><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></dt>
			<dd><code><?php echo isset( $rwga_run['workflow_key'] ) ? esc_html( (string) $rwga_run['workflow_key'] ) : '—'; ?></code></dd>
			<dt><?php esc_html_e( 'Agent', 'reactwoo-geo-ai' ); ?></dt>
			<dd><code><?php echo isset( $rwga_run['agent_key'] ) ? esc_html( (string) $rwga_run['agent_key'] ) : '—'; ?></code></dd>
			<dt><?php esc_html_e( 'Score', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['score'] ) && null !== $rwga_run['score'] ? esc_html( (string) $rwga_run['score'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Confidence', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['confidence'] ) && null !== $rwga_run['confidence'] ? esc_html( (string) $rwga_run['confidence'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Schema', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['result_schema_version'] ) ? esc_html( (string) $rwga_run['result_schema_version'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Device', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['device_type'] ) && $rwga_run['device_type'] ? esc_html( (string) $rwga_run['device_type'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Geo target', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['geo_target'] ) && $rwga_run['geo_target'] ? esc_html( (string) $rwga_run['geo_target'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['created_at'] ) ? esc_html( (string) $rwga_run['created_at'] ) : '—'; ?></dd>
		</dl>
		<?php if ( ! empty( $rwga_run['summary'] ) ) : ?>
			<h3><?php esc_html_e( 'Narrative', 'reactwoo-geo-ai' ); ?></h3>
			<p><?php echo nl2br( esc_html( (string) $rwga_run['summary'] ) ); ?></p>
		<?php endif; ?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Findings', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_findings ) ) : ?>
			<p class="description"><?php esc_html_e( 'No findings stored for this run.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<ul class="rwga-findings-list">
				<?php foreach ( $rwga_findings as $f ) : ?>
					<?php
					$sev = isset( $f['severity'] ) ? sanitize_key( (string) $f['severity'] ) : 'medium';
					$sc  = isset( $severity_class[ $sev ] ) ? $severity_class[ $sev ] : '';
					?>
					<li class="rwga-finding-card">
						<div class="rwga-finding-card__head">
							<span class="rwga-severity <?php echo esc_attr( $sc ); ?>"><?php echo esc_html( $sev ); ?></span>
							<strong><?php echo isset( $f['title'] ) ? esc_html( (string) $f['title'] ) : ''; ?></strong>
							<?php if ( ! empty( $f['category'] ) ) : ?>
								<span class="description"><?php echo esc_html( (string) $f['category'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $f['evidence'] ) ) : ?>
							<p class="rwga-finding-card__evidence"><?php echo esc_html( (string) $f['evidence'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $f['recommendation_hint'] ) ) : ?>
							<p class="rwga-finding-card__hint"><em><?php esc_html_e( 'Hint:', 'reactwoo-geo-ai' ); ?></em> <?php echo esc_html( (string) $f['recommendation_hint'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
