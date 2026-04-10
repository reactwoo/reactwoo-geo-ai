<?php
/**
 * Single implementation draft detail.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_draft       = isset( $rwga_draft ) && is_array( $rwga_draft ) ? $rwga_draft : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-implementation-drafts';

$draft_id = isset( $rwga_draft['id'] ) ? (int) $rwga_draft['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-implementation-drafts' );

$payload_raw  = isset( $rwga_draft['draft_payload'] ) ? (string) $rwga_draft['draft_payload'] : '';
$payload_dec  = json_decode( $payload_raw, true );
$payload_text = is_array( $payload_dec )
	? wp_json_encode( $payload_dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
	: $payload_raw;

$ctx_raw = isset( $rwga_draft['input_context'] ) ? (string) $rwga_draft['input_context'] : '';
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--implementation-draft-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			sprintf(
				/* translators: %d: draft id */
				__( 'Implementation draft #%d', 'reactwoo-geo-ai' ),
				$draft_id
			),
			__( 'Structured payload for review and handoff to the editor.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Implementation draft #%d', 'reactwoo-geo-ai' ), $draft_id ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<p>
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '&larr; All drafts', 'reactwoo-geo-ai' ); ?></a>
		<?php
		$pid = isset( $rwga_draft['page_id'] ) ? (int) $rwga_draft['page_id'] : 0;
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
			<dd><code><?php echo isset( $rwga_draft['workflow_key'] ) ? esc_html( (string) $rwga_draft['workflow_key'] ) : '—'; ?></code></dd>
			<dt><?php esc_html_e( 'Draft type', 'reactwoo-geo-ai' ); ?></dt>
			<dd><code><?php echo isset( $rwga_draft['draft_type'] ) ? esc_html( (string) $rwga_draft['draft_type'] ) : '—'; ?></code></dd>
			<dt><?php esc_html_e( 'Geo target', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['geo_target'] ) && $rwga_draft['geo_target'] ? esc_html( (string) $rwga_draft['geo_target'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['status'] ) ? esc_html( (string) $rwga_draft['status'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['created_at'] ) ? esc_html( (string) $rwga_draft['created_at'] ) : '—'; ?></dd>
			<?php
			$rec_id = isset( $rwga_draft['recommendation_id'] ) ? (int) $rwga_draft['recommendation_id'] : 0;
			if ( $rec_id > 0 ) :
				?>
			<dt><?php esc_html_e( 'Recommendation', 'reactwoo-geo-ai' ); ?></dt>
			<dd>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'rec_id' => $rec_id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $rec_id; ?></a>
			</dd>
			<?php endif; ?>
		</dl>
	</div>

	<?php if ( '' !== $ctx_raw ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Input context', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo wp_kses_post( wpautop( $ctx_raw ) ); ?></div>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Draft payload', 'reactwoo-geo-ai' ); ?></h2>
		<pre class="rwga-code-block"><?php echo esc_html( (string) $payload_text ); ?></pre>
	</div>
</div>
