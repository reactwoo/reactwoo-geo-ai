<?php
/**
 * Implementation drafts (copy, etc.) list.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows                    = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination              = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_filter_recommendation   = isset( $rwga_filter_recommendation ) ? (int) $rwga_filter_recommendation : 0;
$rwga_filter_workflow         = isset( $rwga_filter_workflow ) ? (string) $rwga_filter_workflow : '';
$rwgc_nav_current             = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-implementation-drafts';

$list_url = admin_url( 'admin.php?page=rwga-implementation-drafts' );
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--implementation-drafts">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Implementation drafts', 'reactwoo-geo-ai' ),
			__( 'Reviewable copy and SEO drafts from bounded workflows (nothing is published automatically).', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Implementation drafts', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwga_copy = isset( $_GET['rwga_copy'] ) ? sanitize_key( wp_unslash( $_GET['rwga_copy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_copy ) {
		$n = isset( $_GET['rwga_draft_count'] ) ? (int) $_GET['rwga_draft_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of draft rows saved */
				_n( 'Saved %d implementation draft.', 'Saved %d implementation drafts.', $n, 'reactwoo-geo-ai' ),
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_copy ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate implementation drafts.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_copy ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Provide a recommendation ID and/or page ID.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_copy ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Copy implementation workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_copy && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	$rwga_seo = isset( $_GET['rwga_seo'] ) ? sanitize_key( wp_unslash( $_GET['rwga_seo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_seo ) {
		$n = isset( $_GET['rwga_draft_count'] ) ? (int) $_GET['rwga_draft_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of SEO draft rows saved */
				_n( 'Saved %d SEO draft.', 'Saved %d SEO drafts.', $n, 'reactwoo-geo-ai' ),
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_seo ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate SEO drafts.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_seo ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Provide a recommendation ID and/or page ID.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_seo ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'SEO implementation workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_seo && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	?>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Generate copy drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use a stored recommendation for context, or a page alone. You can also POST to /wp-json/geo-ai/v1/implement/copy.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_copy_implement" />
			<?php wp_nonce_field( 'rwga_copy_implement' ); ?>
			<p>
				<label for="rwga_copy_rec_id"><?php esc_html_e( 'Recommendation ID (optional)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="number" min="0" class="small-text" name="recommendation_id" id="rwga_copy_rec_id" value="<?php echo $rwga_filter_recommendation > 0 ? (int) $rwga_filter_recommendation : ''; ?>" />
			</p>
			<p>
				<label for="rwga_copy_page_id"><?php esc_html_e( 'Page ID (optional if recommendation has a page)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="number" min="0" class="small-text" name="page_id" id="rwga_copy_page_id" value="" />
			</p>
			<p>
				<label for="rwga_copy_geo"><?php esc_html_e( 'Geo target ISO2 (optional)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="text" maxlength="2" class="small-text" name="geo_target" id="rwga_copy_geo" value="" />
			</p>
			<?php submit_button( __( 'Generate copy drafts', 'reactwoo-geo-ai' ), 'primary', 'submit', false ); ?>
		</form>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Generate SEO drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Meta title/description, heading outline, and on-page checklist (bounded local engine). POST /wp-json/geo-ai/v1/implement/seo', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_seo_implement" />
			<?php wp_nonce_field( 'rwga_seo_implement' ); ?>
			<p>
				<label for="rwga_seo_rec_id"><?php esc_html_e( 'Recommendation ID (optional)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="number" min="0" class="small-text" name="recommendation_id" id="rwga_seo_rec_id" value="<?php echo $rwga_filter_recommendation > 0 ? (int) $rwga_filter_recommendation : ''; ?>" />
			</p>
			<p>
				<label for="rwga_seo_page_id"><?php esc_html_e( 'Page ID (optional if recommendation has a page)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="number" min="0" class="small-text" name="page_id" id="rwga_seo_page_id" value="" />
			</p>
			<p>
				<label for="rwga_seo_geo"><?php esc_html_e( 'Geo target ISO2 (optional)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="text" maxlength="2" class="small-text" name="geo_target" id="rwga_seo_geo" value="" />
			</p>
			<?php submit_button( __( 'Generate SEO drafts', 'reactwoo-geo-ai' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key to generate implementation drafts.', 'reactwoo-geo-ai' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter">
			<input type="hidden" name="page" value="rwga-implementation-drafts" />
			<p>
				<label for="rwga-filter-rec"><?php esc_html_e( 'Recommendation ID', 'reactwoo-geo-ai' ); ?></label>
				<input type="number" min="0" name="recommendation_id" id="rwga-filter-rec" value="<?php echo $rwga_filter_recommendation > 0 ? (int) $rwga_filter_recommendation : ''; ?>" style="max-width: 8rem;" />
			</p>
			<p>
				<label for="rwga-filter-wf"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></label>
				<select name="workflow_key" id="rwga-filter-wf">
					<option value="" <?php selected( '', $rwga_filter_workflow ); ?>><?php esc_html_e( 'All', 'reactwoo-geo-ai' ); ?></option>
					<option value="copy_implement" <?php selected( 'copy_implement', $rwga_filter_workflow ); ?>>copy_implement</option>
					<option value="seo_implement" <?php selected( 'seo_implement', $rwga_filter_workflow ); ?>>seo_implement</option>
				</select>
			</p>
			<?php submit_button( __( 'Filter', 'reactwoo-geo-ai' ), 'secondary', 'submit', false ); ?>
			<?php if ( $rwga_filter_recommendation > 0 || '' !== $rwga_filter_workflow ) : ?>
				<a class="button-link" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<p class="description"><?php esc_html_e( 'No implementation drafts yet. Generate from a recommendation or use the REST API.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Recommendation', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$did = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$rid = isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : 0;
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						$detail_url = add_query_arg(
							array(
								'page'     => 'rwga-implementation-drafts',
								'draft_id' => $did,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $did; ?></td>
							<td><code><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : '—'; ?></code></td>
							<td>
								<strong><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo isset( $row['title'] ) ? esc_html( (string) $row['title'] ) : ''; ?></a></strong>
							</td>
							<td><code><?php echo isset( $row['draft_type'] ) ? esc_html( (string) $row['draft_type'] ) : '—'; ?></code></td>
							<td>
								<?php if ( $rid > 0 ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'rec_id' => $rid ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $rid; ?></a>
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
				if ( $rwga_filter_recommendation > 0 ) {
					$base_url = add_query_arg( 'recommendation_id', $rwga_filter_recommendation, $base_url );
				}
				if ( '' !== $rwga_filter_workflow ) {
					$base_url = add_query_arg( 'workflow_key', $rwga_filter_workflow, $base_url );
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
