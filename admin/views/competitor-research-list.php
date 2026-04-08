<?php
/**
 * Competitor research list + run form.
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
$rwga_filter_page     = isset( $rwga_filter_page ) ? (int) $rwga_filter_page : 0;
$rwgc_nav_current     = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-competitors';
$list_url             = admin_url( 'admin.php?page=rwga-competitors' );
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--competitors">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Competitor research', 'reactwoo-geo-ai' ),
			__( 'Bounded snapshots for positioning review (stub engine — no live fetch).', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Competitor research', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwga_cr = isset( $_GET['rwga_cr'] ) ? sanitize_key( wp_unslash( $_GET['rwga_cr'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_cr ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Competitor research saved.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'unlicensed' === $rwga_cr ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to run competitor research.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'badurl' === $rwga_cr ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Enter a valid competitor URL.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_cr ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Competitor research workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_cr && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	?>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'New research', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'POST /wp-json/geo-ai/v1/research/competitors with JSON competitor_url (required), optional page_id, geo_target, page_type.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_competitor_research" />
			<?php wp_nonce_field( 'rwga_competitor_research' ); ?>
			<p>
				<label for="rwga_cr_url"><?php esc_html_e( 'Competitor URL', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="url" class="regular-text" name="competitor_url" id="rwga_cr_url" required placeholder="https://example.com" />
			</p>
			<p>
				<label for="rwga_cr_page"><?php esc_html_e( 'Your page ID (optional, for context)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="number" min="0" class="small-text" name="page_id" id="rwga_cr_page" value="" />
			</p>
			<p>
				<label for="rwga_cr_geo"><?php esc_html_e( 'Geo target ISO2 (optional)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="text" maxlength="2" class="small-text" name="geo_target" id="rwga_cr_geo" value="" />
			</p>
			<p>
				<label for="rwga_cr_pt"><?php esc_html_e( 'Page type (optional)', 'reactwoo-geo-ai' ); ?></label><br />
				<input type="text" class="regular-text" name="page_type" id="rwga_cr_pt" value="page" />
			</p>
			<?php submit_button( __( 'Save research', 'reactwoo-geo-ai' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key to run competitor research.', 'reactwoo-geo-ai' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter">
			<input type="hidden" name="page" value="rwga-competitors" />
			<label for="rwga-cr-filter-page"><?php esc_html_e( 'Filter by your page ID', 'reactwoo-geo-ai' ); ?></label>
			<input type="number" min="0" name="filter_page" id="rwga-cr-filter-page" value="<?php echo $rwga_filter_page > 0 ? (int) $rwga_filter_page : ''; ?>" style="max-width: 8rem;" />
			<?php submit_button( __( 'Filter', 'reactwoo-geo-ai' ), 'secondary', 'submit', false ); ?>
			<?php if ( $rwga_filter_page > 0 ) : ?>
				<a class="button-link" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<p class="description"><?php esc_html_e( 'No competitor research rows yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Competitor URL', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Geo', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$url = isset( $row['competitor_url'] ) ? (string) $row['competitor_url'] : '';
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$det = add_query_arg(
							array(
								'page'         => 'rwga-competitors',
								'research_id'  => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td><a href="<?php echo esc_url( $det ); ?>"><?php echo esc_html( $url ); ?></a></td>
							<td><?php echo $pid > 0 ? (int) $pid : '—'; ?></td>
							<td><?php echo isset( $row['geo_target'] ) && $row['geo_target'] ? esc_html( (string) $row['geo_target'] ) : '—'; ?></td>
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
				if ( $rwga_filter_page > 0 ) {
					$base_url = add_query_arg( 'filter_page', $rwga_filter_page, $list_url );
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
