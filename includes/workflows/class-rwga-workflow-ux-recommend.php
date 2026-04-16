<?php
/**
 * UX recommendations workflow (local bounded stub).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns analysis findings into structured recommendation cards (stub engine).
 */
class RWGA_Workflow_UX_Recommend extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'ux_recommend';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'UX recommendations', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'ux_strategist';
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return true|\WP_Error
	 */
	public function validate_input( array $input ) {
		$g = $this->gate_capabilities();
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		$rid = isset( $input['analysis_run_id'] ) ? (int) $input['analysis_run_id'] : 0;
		if ( $rid <= 0 ) {
			return new WP_Error(
				'rwga_need_analysis',
				__( 'Provide analysis_run_id for recommendations.', 'reactwoo-geo-ai' )
			);
		}
		$run = RWGA_DB_Analysis_Runs::get( $rid );
		if ( ! is_array( $run ) ) {
			return new WP_Error( 'rwga_analysis_missing', __( 'Analysis run not found.', 'reactwoo-geo-ai' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		return array(
			'analysis_run_id' => isset( $input['analysis_run_id'] ) ? (int) $input['analysis_run_id'] : 0,
			'business_goal'   => isset( $input['business_goal'] ) ? sanitize_text_field( (string) $input['business_goal'] ) : '',
			'geo_target'      => isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '',
		);
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( array $input ) {
		$v = $this->validate_input( $input );
		if ( is_wp_error( $v ) ) {
			return $v;
		}

		$analysis_run_id = (int) $input['analysis_run_id'];
		$run               = RWGA_DB_Analysis_Runs::get( $analysis_run_id );
		if ( ! is_array( $run ) ) {
			return new WP_Error( 'rwga_analysis_missing', __( 'Analysis run not found.', 'reactwoo-geo-ai' ) );
		}

		$findings = RWGA_DB_Analysis_Findings::list_for_run( $analysis_run_id );
		$goal     = isset( $input['business_goal'] ) ? sanitize_text_field( (string) $input['business_goal'] ) : '';
		$mode     = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'local';

		$remote_payload = array(
			'analysis_run_id'  => $analysis_run_id,
			'business_goal'    => $goal,
			'geo_target'       => isset( $input['geo_target'] ) ? sanitize_text_field( (string) $input['geo_target'] ) : ( isset( $run['geo_target'] ) ? (string) $run['geo_target'] : '' ),
			'analysis_summary' => isset( $run['summary'] ) ? (string) $run['summary'] : '',
			'findings'         => is_array( $findings ) ? $findings : array(),
		);
		$remote = class_exists( 'RWGA_Engine', false ) && RWGA_Engine::should_try_remote()
			? RWGA_Remote_Client::dispatch( $this->get_key(), $remote_payload )
			: null;
		$use_api = ! is_wp_error( $remote ) && is_array( $remote ) && ! empty( $remote['engine_response'] );

		if ( $use_api ) {
			$norm = $this->normalise_response( $remote['engine_response'] );
		} else {
			if ( is_wp_error( $remote ) && 'remote' === $mode ) {
				return $remote;
			}
			$raw  = $this->produce_stub_recommendations( $run, $findings, $goal );
			$norm = $this->normalise_response( $raw );
		}

		$in = array(
			'analysis_run_id' => $analysis_run_id,
			'page_id'         => isset( $run['page_id'] ) ? (int) $run['page_id'] : 0,
			'geo_target'      => isset( $input['geo_target'] ) ? sanitize_text_field( (string) $input['geo_target'] ) : ( isset( $run['geo_target'] ) ? (string) $run['geo_target'] : '' ),
		);

		$persisted = $this->persist( $in, $norm );
		if ( is_array( $persisted ) && empty( $persisted['success'] ) ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not save recommendations.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_persist', $msg );
		}
		return $persisted;
	}

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response ) {
		return array(
			'workflow_key'   => $this->get_key(),
			'agent_key'      => $this->get_agent_key(),
			'recommendations'=> isset( $response['recommendations'] ) && is_array( $response['recommendations'] ) ? $response['recommendations'] : array(),
			'schema_version' => self::DEFAULT_SCHEMA_VERSION,
		);
	}

	/**
	 * @param array<string, mixed> $input    Context.
	 * @param array<string, mixed> $result   Normalised result.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result ) {
		$uid             = get_current_user_id();
		$analysis_run_id = isset( $input['analysis_run_id'] ) ? (int) $input['analysis_run_id'] : 0;
		$page_id         = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo             = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';

		$recs = isset( $result['recommendations'] ) && is_array( $result['recommendations'] ) ? $result['recommendations'] : array();
		$ids  = array();

		foreach ( $recs as $rec ) {
			if ( ! is_array( $rec ) ) {
				continue;
			}
			$id = RWGA_DB_Recommendations::insert(
				array(
					'analysis_run_id' => $analysis_run_id,
					'workflow_key'    => $this->get_key(),
					'agent_key'       => $this->get_agent_key(),
					'page_id'         => $page_id > 0 ? $page_id : null,
					'geo_target'      => $geo,
					'priority_level'  => isset( $rec['priority_level'] ) ? sanitize_key( (string) $rec['priority_level'] ) : 'medium',
					'category'        => isset( $rec['category'] ) ? sanitize_key( (string) $rec['category'] ) : 'general',
					'title'           => isset( $rec['title'] ) ? sanitize_text_field( (string) $rec['title'] ) : '',
					'problem'         => isset( $rec['problem'] ) ? wp_kses_post( (string) $rec['problem'] ) : '',
					'why_it_matters'  => isset( $rec['why_it_matters'] ) ? wp_kses_post( (string) $rec['why_it_matters'] ) : '',
					'recommendation'  => isset( $rec['recommendation'] ) ? wp_kses_post( (string) $rec['recommendation'] ) : '',
					'expected_impact' => isset( $rec['expected_impact'] ) ? sanitize_text_field( (string) $rec['expected_impact'] ) : null,
					'confidence'      => isset( $rec['confidence'] ) ? (float) $rec['confidence'] : null,
					'status'          => 'open',
					'created_by'      => $uid > 0 ? $uid : null,
				)
			);
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No recommendation rows were saved.', 'reactwoo-geo-ai' ),
			);
		}

		RWGA_Memory_Service::append(
			'recommendations_generated',
			'analysis_run',
			$analysis_run_id,
			$page_id,
			$geo,
			array(
				'workflow_key'        => $this->get_key(),
				'recommendation_ids'  => $ids,
				'count'               => count( $ids ),
			)
		);

		return array(
			'success'            => true,
			'recommendation_ids' => $ids,
			'result'             => $result,
		);
	}

	/**
	 * @param array<string, mixed>           $run      Analysis run row.
	 * @param array<int, array<string, mixed>> $findings Findings.
	 * @param string                         $goal     Business goal hint.
	 * @return array<string, mixed>
	 */
	private function produce_stub_recommendations( array $run, array $findings, $goal ) {
		$out = array();
		$goal = trim( (string) $goal );

		if ( ! empty( $findings ) ) {
			$findings = array_slice( $findings, 0, 12 );
			foreach ( $findings as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$sev = isset( $f['severity'] ) ? sanitize_key( (string) $f['severity'] ) : 'medium';
				$pri = $sev;
				if ( ! in_array( $pri, array( 'high', 'medium', 'low' ), true ) ) {
					$pri = 'medium';
				}
				$title = isset( $f['title'] ) ? (string) $f['title'] : '';
				$cat   = isset( $f['category'] ) ? sanitize_key( (string) $f['category'] ) : 'general';

				$problem = isset( $f['evidence'] ) && '' !== trim( (string) $f['evidence'] )
					? (string) $f['evidence']
					: $title;

				$why = __( 'Friction here reduces clarity and conversion; fixing it improves the primary user path.', 'reactwoo-geo-ai' );
				if ( '' !== $goal ) {
					$why .= ' ' . sprintf(
						/* translators: %s: business goal */
						__( 'Aligned with goal: %s.', 'reactwoo-geo-ai' ),
						$goal
					);
				}

				$rec_text_raw = isset( $f['recommendation_hint'] ) && '' !== trim( (string) $f['recommendation_hint'] )
					? (string) $f['recommendation_hint']
					: __( 'Test a clearer headline, stronger CTA hierarchy, and specific proof near the decision point.', 'reactwoo-geo-ai' );
				$rec_text = $this->format_structured_recommendation( $problem, $why, $rec_text_raw, $cat );

				$impact = isset( $f['impact_estimate'] ) ? sanitize_text_field( (string) $f['impact_estimate'] ) : 'medium';
				$conf   = isset( $f['confidence'] ) ? (float) $f['confidence'] : 0.7;

				$title_out = $title
					? __( 'Prioritise:', 'reactwoo-geo-ai' ) . ' ' . $title
					: __( 'Improve conversion focus', 'reactwoo-geo-ai' );

				$out[] = array(
					'priority_level'  => $pri,
					'category'        => $cat,
					'title'           => $title_out,
					'problem'         => $problem,
					'why_it_matters'  => $why,
					'recommendation'  => $rec_text,
					'expected_impact' => $impact,
					'confidence'      => $conf,
				);
			}
		} else {
			$summary = isset( $run['summary'] ) ? (string) $run['summary'] : '';
			$problem = __( 'No granular findings were stored; the page still needs a sharper narrative and CTA.', 'reactwoo-geo-ai' );
			$why     = __( 'Visitors decide quickly; ambiguity loses leads.', 'reactwoo-geo-ai' );
			$base_recommendation = $summary
				? __( 'Use the analysis summary as a starting point: tighten the hero, add proof, and single primary action.', 'reactwoo-geo-ai' ) . ' ' . $summary
				: __( 'Tighten the hero, add proof, and use a single primary action.', 'reactwoo-geo-ai' );
			$out[]   = array(
				'priority_level'  => 'medium',
				'category'        => 'general',
				'title'           => __( 'Establish a clearer primary story', 'reactwoo-geo-ai' ),
				'problem'         => $problem,
				'why_it_matters'  => $why,
				'recommendation'  => $this->format_structured_recommendation( $problem, $why, $base_recommendation, 'general' ),
				'expected_impact' => 'medium',
				'confidence'      => 0.65,
			);
		}

		return array( 'recommendations' => $out );
	}

	/**
	 * Keep recommendation text actionable and consistently structured.
	 *
	 * @param string $problem Problem statement.
	 * @param string $why Why it matters statement.
	 * @param string $raw Raw recommendation hint.
	 * @param string $category Finding category.
	 * @return string
	 */
	private function format_structured_recommendation( $problem, $why, $raw, $category ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			$raw = __( 'Clarify the value proposition, reduce CTA conflict, and add specific proof near the conversion moment.', 'reactwoo-geo-ai' );
		}
		$primary_cta   = __( 'Get started', 'reactwoo-geo-ai' );
		$secondary_cta = __( 'See pricing', 'reactwoo-geo-ai' );
		if ( false !== stripos( $raw, 'demo' ) || false !== stripos( (string) $problem, 'demo' ) ) {
			$primary_cta = __( 'Book a demo', 'reactwoo-geo-ai' );
		}
		$proof = __( 'Add one testimonial with role/company and one measurable result stat near the primary CTA.', 'reactwoo-geo-ai' );
		$category = sanitize_key( (string) $category );
		if ( 'trust' === $category ) {
			$proof = __( 'Show logos, a short testimonial quote, and one numeric outcome claim with source context.', 'reactwoo-geo-ai' );
		} elseif ( 'layout' === $category ) {
			$proof = __( 'Place proof directly after the hero and again before pricing/checkout to reduce decision friction.', 'reactwoo-geo-ai' );
		}

		return sprintf(
			/* translators: 1: problem, 2: why, 3: implementation, 4: CTA pair, 5: proof guidance */
			__( 'Problem to fix: %1$s | Why it matters: %2$s | Implementation: %3$s | Copy example: "Get [specific outcome] in [timeframe] without [main objection]." | CTA options: Primary "%4$s", Secondary "%5$s" | Proof to add: %6$s', 'reactwoo-geo-ai' ),
			sanitize_text_field( (string) $problem ),
			sanitize_text_field( (string) $why ),
			sanitize_text_field( $raw ),
			$primary_cta,
			$secondary_cta,
			$proof
		);
	}
}
