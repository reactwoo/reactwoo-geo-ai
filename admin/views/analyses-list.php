<?php
/**
 * Analyses list screen.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows       = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-analyses';

$list_url = admin_url( 'admin.php?page=rwga-analyses' );
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--analyses">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Analyses', 'reactwoo-geo-ai' ),
			__( 'Stored UX and workflow runs for this site.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Analyses', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['rwga_sample'] ) && 'ok' === sanitize_key( wp_unslash( $_GET['rwga_sample'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analysis run completed.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<p class="description"><?php esc_html_e( 'No analyses yet. Run a sample from Overview or call the REST API.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Score', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Geo', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						$detail_url = add_query_arg(
							array(
								'page'   => 'rwga-analyses',
								'run_id' => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td>
								<?php if ( $pid > 0 && '' !== $pt ) : ?>
									<strong><?php echo esc_html( $pt ); ?></strong>
									<?php if ( current_user_can( 'edit_post', $pid ) ) : ?>
										<br /><a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geo-ai' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo isset( $row['score'] ) && null !== $row['score'] ? esc_html( (string) $row['score'] ) : '—'; ?></td>
							<td><code><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : '—'; ?></code></td>
							<td><?php echo isset( $row['geo_target'] ) && $row['geo_target'] ? esc_html( (string) $row['geo_target'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'reactwoo-geo-ai' ); ?></a></td>
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
