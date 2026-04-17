<?php
/**
 * Grouped recommendation report (journey mode).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_report_html = isset( $rwga_report_html ) ? (string) $rwga_report_html : '';
$rwga_analysis_run_id = isset( $rwga_analysis_run_id ) ? (int) $rwga_analysis_run_id : 0;
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-recommendations';
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--recommendation-report">
	<h1><?php esc_html_e( 'Recommendation report', 'reactwoo-geo-ai' ); ?></h1>
	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<div class="rwgc-actions rwgc-actions--stack-mobile" style="margin-bottom:16px;">
		<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-analyses&run_id=' . (int) $rwga_analysis_run_id ) ); ?>"><?php esc_html_e( 'View analysis', 'reactwoo-geo-ai' ); ?></a>
		<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-recommendations' ) ); ?>"><?php esc_html_e( 'Open recommendation library', 'reactwoo-geo-ai' ); ?></a>
	</div>

	<div class="rwgc-card rwga-report-content">
		<?php if ( '' !== $rwga_report_html ) : ?>
			<div class="rwga-report-html"><?php echo wp_kses_post( $rwga_report_html ); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'No grouped recommendation report was generated yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card rwgc-card--highlight">
		<h2><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Continue the journey by generating section-aware implementation drafts.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_bulk_implement_analysis" />
			<input type="hidden" name="analysis_run_id" value="<?php echo (int) $rwga_analysis_run_id; ?>" />
			<?php wp_nonce_field( 'rwga_bulk_implement_analysis' ); ?>
			<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></button>
		</form>
	</div>
	<?php endif; ?>
</div>

