<?php
/**
 * Single recommendation detail.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rec         = isset( $rwga_rec ) && is_array( $rwga_rec ) ? $rwga_rec : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-recommendations';

$rec_id = isset( $rwga_rec['id'] ) ? (int) $rwga_rec['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-recommendations' );

$analysis_run_id = isset( $rwga_rec['analysis_run_id'] ) ? (int) $rwga_rec['analysis_run_id'] : 0;
$page_id         = isset( $rwga_rec['page_id'] ) ? (int) $rwga_rec['page_id'] : 0;
$geo             = isset( $rwga_rec['geo_target'] ) ? (string) $rwga_rec['geo_target'] : '';
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--recommendation-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			sprintf(
				/* translators: %d: recommendation id */
				__( 'Recommendation #%d', 'reactwoo-geo-ai' ),
				$rec_id
			),
			__( 'Structured UX action card. Generate copy drafts from this context.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Recommendation #%d', 'reactwoo-geo-ai' ), $rec_id ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<p>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '&larr; All recommendations', 'reactwoo-geo-ai' ); ?></a>
		<?php if ( $analysis_run_id > 0 ) : ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-analyses', 'run_id' => $analysis_run_id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View analysis', 'reactwoo-geo-ai' ); ?></a>
		<?php endif; ?>
		<?php if ( $page_id > 0 && current_user_can( 'edit_post', $page_id ) ) : ?>
			<?php
			$edit = get_edit_post_link( $page_id, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				echo ' <a class="button button-primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'reactwoo-geo-ai' ) . '</a>';
			}
			?>
		<?php endif; ?>
	</p>

	<div class="rwgc-card rwga-analysis-meta">
		<h2><?php esc_html_e( 'Summary', 'reactwoo-geo-ai' ); ?></h2>
		<dl class="rwga-license-dl">
			<dt><?php esc_html_e( 'Priority', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['priority_level'] ) ? esc_html( (string) $rwga_rec['priority_level'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Category', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['category'] ) ? esc_html( (string) $rwga_rec['category'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Geo target', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo '' !== $geo ? esc_html( $geo ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['status'] ) ? esc_html( (string) $rwga_rec['status'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['created_at'] ) ? esc_html( (string) $rwga_rec['created_at'] ) : '—'; ?></dd>
		</dl>
		<h3><?php esc_html_e( 'Title', 'reactwoo-geo-ai' ); ?></h3>
		<p><strong><?php echo isset( $rwga_rec['title'] ) ? esc_html( (string) $rwga_rec['title'] ) : ''; ?></strong></p>
		<h3><?php esc_html_e( 'Problem', 'reactwoo-geo-ai' ); ?></h3>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_rec['problem'] ) ? wp_kses_post( wpautop( (string) $rwga_rec['problem'] ) ) : ''; ?></div>
		<h3><?php esc_html_e( 'Recommendation', 'reactwoo-geo-ai' ); ?></h3>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_rec['recommendation'] ) ? wp_kses_post( wpautop( (string) $rwga_rec['recommendation'] ) ) : ''; ?></div>
	</div>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Copy implementation drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Generate hero, CTA, and trust-line drafts (bounded local engine). Nothing is published automatically.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_copy_implement" />
			<input type="hidden" name="recommendation_id" value="<?php echo (int) $rec_id; ?>" />
			<?php if ( $page_id > 0 ) : ?>
				<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
			<?php endif; ?>
			<?php if ( '' !== $geo ) : ?>
				<input type="hidden" name="geo_target" value="<?php echo esc_attr( $geo ); ?>" />
			<?php endif; ?>
			<?php wp_nonce_field( 'rwga_copy_implement' ); ?>
			<?php submit_button( __( 'Generate copy drafts', 'reactwoo-geo-ai' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key to generate implementation drafts.', 'reactwoo-geo-ai' ); ?></p>
	</div>
	<?php endif; ?>
</div>
