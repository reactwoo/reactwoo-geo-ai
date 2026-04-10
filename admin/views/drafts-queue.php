<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_queue_rows = isset( $rwga_queue_rows ) && is_array( $rwga_queue_rows ) ? $rwga_queue_rows : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-drafts';
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--drafts">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Drafts / Queue', 'reactwoo-geo-ai' ),
			__( 'Review generated drafts: preview, approve, discard, or regenerate when your workflow supplies queue data.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Drafts / Queue', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_queue_rows ) ) : ?>
			<p class="rwga-empty-hint"><?php esc_html_e( 'No draft jobs in the queue yet. When the integration stores drafts in WordPress (or extends rwga_draft_queue_rows), they will appear here with actions.', 'reactwoo-geo-ai' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Browse pages', 'reactwoo-geo-ai' ); ?></a></p>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Target context', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_queue_rows as $row ) : ?>
						<tr>
							<td><?php echo isset( $row['source_label'] ) ? esc_html( (string) $row['source_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['context_label'] ) ? esc_html( (string) $row['context_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['type_label'] ) ? esc_html( (string) $row['type_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['status_label'] ) ? esc_html( (string) $row['status_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_gmt'] ) ? esc_html( (string) $row['created_gmt'] ) : '—'; ?></td>
							<td>
								<?php
								if ( ! empty( $row['actions_html'] ) ) {
									echo wp_kses_post( (string) $row['actions_html'] );
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
