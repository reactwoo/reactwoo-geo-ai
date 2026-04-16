<?php
/**
 * Structured report rendering for Geo AI outputs.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts workflow payloads into readable, sanitised HTML reports.
 */
class RWGA_Report_Formatter {

	/**
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_tags() {
		return array(
			'h2'     => array(),
			'h3'     => array(),
			'h4'     => array(),
			'p'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * @param string $html Raw html.
	 * @return string
	 */
	private static function clean( $html ) {
		$html = is_string( $html ) ? $html : '';
		return wp_kses( $html, self::allowed_tags() );
	}

	/**
	 * @param array<string, mixed> $result Analysis result.
	 * @return string
	 */
	public static function format_analysis_report( array $result ) {
		$summary  = isset( $result['summary'] ) ? (string) $result['summary'] : '';
		$score    = isset( $result['score'] ) ? (string) $result['score'] : '—';
		$conf     = isset( $result['confidence'] ) ? (string) $result['confidence'] : '—';
		$findings = isset( $result['findings'] ) && is_array( $result['findings'] ) ? $result['findings'] : array();

		ob_start();
		?>
		<h2><?php esc_html_e( 'Executive Summary', 'reactwoo-geo-ai' ); ?></h2>
		<?php echo wpautop( esc_html( $summary ) ); ?>
		<h2><?php esc_html_e( 'Overall Score', 'reactwoo-geo-ai' ); ?></h2>
		<p><strong><?php echo esc_html( $score ); ?></strong> / 100 &mdash; <?php esc_html_e( 'Confidence', 'reactwoo-geo-ai' ); ?>: <?php echo esc_html( $conf ); ?></p>
		<h2><?php esc_html_e( 'Findings by Category', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $findings ) ) : ?>
			<p><?php esc_html_e( 'No findings were produced for this run.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<?php foreach ( $findings as $finding ) : ?>
				<?php
				$f = is_array( $finding ) ? $finding : array();
				$title = isset( $f['title'] ) ? (string) $f['title'] : '';
				$cat   = isset( $f['category'] ) ? (string) $f['category'] : 'general';
				$sev   = isset( $f['severity'] ) ? (string) $f['severity'] : 'medium';
				$evi   = isset( $f['evidence'] ) ? (string) $f['evidence'] : '';
				$hint  = isset( $f['recommendation_hint'] ) ? (string) $f['recommendation_hint'] : '';
				?>
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><strong><?php esc_html_e( 'Category', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( $cat ); ?> &mdash; <strong><?php esc_html_e( 'Severity', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( $sev ); ?></p>
				<?php echo wpautop( esc_html( $evi ) ); ?>
				<?php if ( '' !== trim( $hint ) ) : ?>
					<p><strong><?php esc_html_e( 'Suggested next step:', 'reactwoo-geo-ai' ); ?></strong> <?php echo esc_html( $hint ); ?></p>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php
		return self::clean( (string) ob_get_clean() );
	}

	/**
	 * @param array<string, mixed> $recommendation Recommendation row/payload.
	 * @return string
	 */
	public static function format_recommendation_report( array $recommendation ) {
		$title = isset( $recommendation['title'] ) ? (string) $recommendation['title'] : '';
		$problem = isset( $recommendation['problem'] ) ? (string) $recommendation['problem'] : '';
		$why = isset( $recommendation['why_it_matters'] ) ? (string) $recommendation['why_it_matters'] : '';
		$action = isset( $recommendation['recommendation'] ) ? (string) $recommendation['recommendation'] : '';
		$impact = isset( $recommendation['expected_impact'] ) ? (string) $recommendation['expected_impact'] : '';

		ob_start();
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<h3><?php esc_html_e( 'What we are addressing', 'reactwoo-geo-ai' ); ?></h3>
		<?php echo wpautop( esc_html( $problem ) ); ?>
		<h3><?php esc_html_e( 'Why this matters', 'reactwoo-geo-ai' ); ?></h3>
		<?php echo wpautop( esc_html( $why ) ); ?>
		<h3><?php esc_html_e( 'Recommended changes', 'reactwoo-geo-ai' ); ?></h3>
		<?php echo wpautop( esc_html( $action ) ); ?>
		<?php if ( '' !== trim( $impact ) ) : ?>
			<h3><?php esc_html_e( 'Expected impact', 'reactwoo-geo-ai' ); ?></h3>
			<p><?php echo esc_html( $impact ); ?></p>
		<?php endif; ?>
		<?php
		return self::clean( (string) ob_get_clean() );
	}

	/**
	 * @param array<string, mixed> $draft Draft row.
	 * @return string
	 */
	public static function format_draft_report( array $draft ) {
		$title = isset( $draft['title'] ) ? (string) $draft['title'] : '';
		$type  = isset( $draft['draft_type'] ) ? (string) $draft['draft_type'] : '';
		$payload = array();
		if ( isset( $draft['draft_payload'] ) ) {
			$payload = is_array( $draft['draft_payload'] ) ? $draft['draft_payload'] : json_decode( (string) $draft['draft_payload'], true );
		}
		$payload = is_array( $payload ) ? $payload : array();

		ob_start();
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><strong><?php esc_html_e( 'Draft type:', 'reactwoo-geo-ai' ); ?></strong> <?php echo esc_html( $type ); ?></p>
		<h3><?php esc_html_e( 'Generated content', 'reactwoo-geo-ai' ); ?></h3>
		<ul>
			<?php foreach ( $payload as $k => $v ) : ?>
				<li><strong><?php echo esc_html( (string) $k ); ?>:</strong> <?php echo esc_html( is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
		return self::clean( (string) ob_get_clean() );
	}
}

