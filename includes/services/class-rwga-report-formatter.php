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
	public static function format_analysis_report( array $run, array $findings = array(), array $recommendations = array() ) {
		$summary = isset( $run['summary'] ) ? (string) $run['summary'] : '';
		$score   = isset( $run['score'] ) ? (string) $run['score'] : '—';
		$conf    = isset( $run['confidence'] ) ? (string) $run['confidence'] : '—';
		if ( empty( $findings ) && isset( $run['findings'] ) && is_array( $run['findings'] ) ) {
			$findings = $run['findings'];
		}

		$html  = self::render_section( __( 'Executive Summary', 'reactwoo-geo-ai' ), $summary );
		$html .= '<h2>' . esc_html__( 'Overall Score', 'reactwoo-geo-ai' ) . '</h2>';
		$html .= '<p><strong>' . esc_html( $score ) . '</strong> / 100 &mdash; ' . esc_html__( 'Confidence', 'reactwoo-geo-ai' ) . ': ' . esc_html( $conf ) . '</p>';
		$html .= '<h2>' . esc_html__( 'Findings by Category', 'reactwoo-geo-ai' ) . '</h2>';

		if ( empty( $findings ) ) {
			$html .= '<p>' . esc_html__( 'No findings were produced for this run.', 'reactwoo-geo-ai' ) . '</p>';
		} else {
			foreach ( $findings as $finding ) {
				$f     = is_array( $finding ) ? $finding : array();
				$title = isset( $f['title'] ) ? (string) $f['title'] : '';
				$cat   = isset( $f['category'] ) ? (string) $f['category'] : 'general';
				$sev   = isset( $f['severity'] ) ? (string) $f['severity'] : 'medium';
				$evi   = isset( $f['evidence'] ) ? (string) $f['evidence'] : '';
				$hint  = isset( $f['recommendation_hint'] ) ? (string) $f['recommendation_hint'] : '';
				$html .= '<h3>' . esc_html( $title ) . '</h3>';
				$html .= '<p><strong>' . esc_html__( 'Category', 'reactwoo-geo-ai' ) . ':</strong> ' . esc_html( $cat ) . ' &mdash; <strong>' . esc_html__( 'Severity', 'reactwoo-geo-ai' ) . ':</strong> ' . esc_html( $sev ) . '</p>';
				$html .= self::render_paragraphs( $evi );
				if ( '' !== trim( $hint ) ) {
					$html .= '<p><strong>' . esc_html__( 'Suggested next step:', 'reactwoo-geo-ai' ) . '</strong> ' . esc_html( $hint ) . '</p>';
				}
			}
		}
		if ( ! empty( $recommendations ) ) {
			$html .= self::format_recommendation_report( $recommendations, array( 'show_title' => true ) );
		}
		return self::clean( $html );
	}

	/**
	 * @param array<string, mixed> $recommendation Recommendation row/payload.
	 * @return string
	 */
	public static function format_recommendation_report( array $recommendations, array $context = array() ) {
		$list = array();
		if ( isset( $recommendations['title'] ) || isset( $recommendations['recommendation'] ) ) {
			$list[] = $recommendations;
		} else {
			$list = $recommendations;
		}
		$grouped = array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cat = isset( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : 'general';
			if ( ! isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ] = array();
			}
			$grouped[ $cat ][] = $row;
		}
		$html = '';
		if ( ! empty( $context['show_title'] ) ) {
			$html .= '<h2>' . esc_html__( 'Recommendation report', 'reactwoo-geo-ai' ) . '</h2>';
		}
		$html .= '<h3>' . esc_html__( 'What we are addressing', 'reactwoo-geo-ai' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'The following recommendations continue directly from your latest analysis.', 'reactwoo-geo-ai' ) . '</p>';
		$html .= '<h3>' . esc_html__( 'Why this matters', 'reactwoo-geo-ai' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'These changes improve clarity, trust, conversion flow, and page performance.', 'reactwoo-geo-ai' ) . '</p>';
		$html .= '<h2>' . esc_html__( 'Recommended changes', 'reactwoo-geo-ai' ) . '</h2>';

		foreach ( $grouped as $cat => $rows ) {
			$html  .= '<h3>' . esc_html( ucwords( str_replace( '_', ' ', (string) $cat ) ) ) . '</h3>';
			$items = array();
			foreach ( $rows as $r ) {
				$title  = isset( $r['title'] ) ? (string) $r['title'] : '';
				$action = isset( $r['recommendation'] ) ? (string) $r['recommendation'] : '';
				$items[] = trim( $title . ': ' . preg_replace( '/\s+/', ' ', $action ) );
			}
			$html .= self::render_bullets( $items );
		}

		return self::clean( $html );
	}

	/**
	 * @param array<string, mixed> $draft Draft row.
	 * @return string
	 */
	public static function format_implementation_report( array $drafts, array $context = array() ) {
		$list = isset( $drafts['id'] ) ? array( $drafts ) : $drafts;
		$html = '<h2>' . esc_html__( 'Implementation draft', 'reactwoo-geo-ai' ) . '</h2>';
		foreach ( $list as $draft ) {
			if ( ! is_array( $draft ) ) {
				continue;
			}
			$title   = isset( $draft['title'] ) ? (string) $draft['title'] : __( 'Section', 'reactwoo-geo-ai' );
			$problem = isset( $context['issue'] ) ? (string) $context['issue'] : __( 'Issue inferred from recommendation context', 'reactwoo-geo-ai' );
			$html   .= '<h3>' . esc_html( $title ) . '</h3>';
			$html   .= '<p><strong>' . esc_html__( 'Issue:', 'reactwoo-geo-ai' ) . '</strong> ' . esc_html( $problem ) . '</p>';
			$payload = isset( $draft['draft_payload'] ) ? $draft['draft_payload'] : array();
			if ( ! is_array( $payload ) ) {
				$payload = json_decode( (string) $payload, true );
			}
			$payload = is_array( $payload ) ? $payload : array();
			foreach ( $payload as $key => $value ) {
				$html .= '<h4>' . esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ) . '</h4>';
				$html .= self::render_paragraphs( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			}
		}
		return self::clean( $html );
	}

	/**
	 * Backward-compatible single draft formatter.
	 *
	 * @param array<string, mixed> $draft Draft row.
	 * @return string
	 */
	public static function format_draft_report( array $draft ) {
		return self::format_implementation_report( array( $draft ) );
	}

	/**
	 * @param string               $heading Heading.
	 * @param string               $body Body.
	 * @param array<int, string>   $items List items.
	 * @return string
	 */
	public static function render_section( $heading, $body = '', array $items = array() ) {
		$html = '<h2>' . esc_html( (string) $heading ) . '</h2>';
		$html .= self::render_paragraphs( (string) $body );
		if ( ! empty( $items ) ) {
			$html .= self::render_bullets( $items );
		}
		return $html;
	}

	/**
	 * @param array<int, string> $items Bullets.
	 * @return string
	 */
	public static function render_bullets( array $items ) {
		if ( empty( $items ) ) {
			return '';
		}
		$html = '<ul>';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( (string) $item ) . '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function render_paragraphs( $text ) {
		return wpautop( esc_html( (string) $text ) );
	}
}

