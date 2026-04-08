<?php
/**
 * Single competitor research row.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_item        = isset( $rwga_item ) && is_array( $rwga_item ) ? $rwga_item : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-competitors';

$rid = isset( $rwga_item['id'] ) ? (int) $rwga_item['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-competitors' );
?>
<div class="wrap rwgc-wrap rwga-wrap rwga-wrap--competitor-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			sprintf(
				/* translators: %d: row id */
				__( 'Competitor research #%d', 'reactwoo-geo-ai' ),
				$rid
			),
			__( 'Structured snapshot for review.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Competitor research #%d', 'reactwoo-geo-ai' ), $rid ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<p><a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '&larr; All competitor research', 'reactwoo-geo-ai' ); ?></a></p>

	<div class="rwgc-card rwga-analysis-meta">
		<dl class="rwga-license-dl">
			<dt><?php esc_html_e( 'Competitor URL', 'reactwoo-geo-ai' ); ?></dt>
			<dd><a href="<?php echo esc_url( isset( $rwga_item['competitor_url'] ) ? (string) $rwga_item['competitor_url'] : '#' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo isset( $rwga_item['competitor_url'] ) ? esc_html( (string) $rwga_item['competitor_url'] ) : ''; ?></a></dd>
			<dt><?php esc_html_e( 'Your page ID', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_item['page_id'] ) && $rwga_item['page_id'] ? (int) $rwga_item['page_id'] : '—'; ?></dd>
			<dt><?php esc_html_e( 'Geo', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_item['geo_target'] ) && $rwga_item['geo_target'] ? esc_html( (string) $rwga_item['geo_target'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_item['created_at'] ) ? esc_html( (string) $rwga_item['created_at'] ) : '—'; ?></dd>
		</dl>
		<h2><?php esc_html_e( 'Summary', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_item['summary'] ) ? wp_kses_post( wpautop( (string) $rwga_item['summary'] ) ) : ''; ?></div>
		<h2><?php esc_html_e( 'Strengths', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_item['strengths'] ) ? wp_kses_post( wpautop( (string) $rwga_item['strengths'] ) ) : ''; ?></div>
		<h2><?php esc_html_e( 'Weaknesses', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_item['weaknesses'] ) ? wp_kses_post( wpautop( (string) $rwga_item['weaknesses'] ) ) : ''; ?></div>
		<h2><?php esc_html_e( 'Patterns', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_item['patterns'] ) ? wp_kses_post( wpautop( (string) $rwga_item['patterns'] ) ) : ''; ?></div>
		<h2><?php esc_html_e( 'Opportunities', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_item['opportunities'] ) ? wp_kses_post( wpautop( (string) $rwga_item['opportunities'] ) ) : ''; ?></div>
	</div>
</div>
