<?php
/**
 * Recommendations list screen.
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
$rwga_filter_analysis   = isset( $rwga_filter_analysis ) ? (int) $rwga_filter_analysis : 0;
$rwgc_nav_current       = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-recommendations';

$list_url = admin_url( 'admin.php?page=rwga-recommendations' );
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--recommendations">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Recommendations', 'reactwoo-geo-ai' ),
			__( 'Structured actions from UX analysis — problem, rationale, impact, and confidence.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Recommendations', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwga_rec = isset( $_GET['rwga_rec'] ) ? sanitize_key( wp_unslash( $_GET['rwga_rec'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_rec ) {
		$n = isset( $_GET['rwga_rec_count'] ) ? (int) $_GET['rwga_rec_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of cards saved */
				_n( 'Saved %d recommendation.', 'Saved %d recommendations.', $n, 'reactwoo-geo-ai' ),
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_rec ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate recommendations.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	?>

	<div class="rwgc-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter">
			<input type="hidden" name="page" value="rwga-recommendations" />
			<label for="rwga-filter-analysis"><?php esc_html_e( 'Filter by analysis run ID', 'reactwoo-geo-ai' ); ?></label>
			<input type="number" min="0" name="analysis_run" id="rwga-filter-analysis" value="<?php echo $rwga_filter_analysis > 0 ? (int) $rwga_filter_analysis : ''; ?>" style="max-width: 8rem;" />
			<?php submit_button( __( 'Filter', 'reactwoo-geo-ai' ), 'secondary', 'submit', false ); ?>
			<?php if ( $rwga_filter_analysis > 0 ) : ?>
				<a class="button-link" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<p class="description"><?php esc_html_e( 'No recommendations yet. Open an analysis and choose “Generate recommendations”, or POST to /wp-json/geo-ai/v1/recommend/ux.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Priority', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Analysis', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$aid = isset( $row['analysis_run_id'] ) ? (int) $row['analysis_run_id'] : 0;
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						?>
						<?php
						$rec_url = add_query_arg(
							array(
								'page'   => 'rwga-recommendations',
								'rec_id' => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td><strong><a href="<?php echo esc_url( $rec_url ); ?>"><?php echo isset( $row['title'] ) ? esc_html( (string) $row['title'] ) : ''; ?></a></strong></td>
							<td><?php echo isset( $row['priority_level'] ) ? esc_html( (string) $row['priority_level'] ) : '—'; ?></td>
							<td>
								<?php if ( $aid > 0 ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-analyses', 'run_id' => $aid ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $aid; ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo $pid > 0 && '' !== $pt ? esc_html( $pt ) : '—'; ?></td>
							<td><?php echo isset( $row['status'] ) ? esc_html( (string) $row['status'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = isset( $rwga_pagination['pages'] ) ? (int) $rwga_pagination['pages'] : 1;
			$current     = isset( $rwga_pagination['current'] ) ? (int) $rwga_pagination['current'] : 1;
			if ( $total_pages > 1 ) {
				$base_url = $list_url;
				if ( $rwga_filter_analysis > 0 ) {
					$base_url = add_query_arg( 'analysis_run', $rwga_filter_analysis, $list_url );
				}
				$base = esc_url_raw( add_query_arg( 'paged', '%#%', $base_url ) );
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
