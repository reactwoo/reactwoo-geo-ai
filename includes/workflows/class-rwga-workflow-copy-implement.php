<?php
/**
 * Copy implementation workflow (local bounded stub).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces reviewable copy drafts (hero, CTA, trust) without publishing.
 */
class RWGA_Workflow_Copy_Implement extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'copy_implement';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Copy implementation', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'ux_writer';
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
		$rid = isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0;
		$pid = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		if ( $rid <= 0 && $pid <= 0 ) {
			return new WP_Error(
				'rwga_need_source',
				__( 'Provide recommendation_id or page_id to generate copy drafts.', 'reactwoo-geo-ai' )
			);
		}
		if ( $rid > 0 && ! is_array( RWGA_DB_Recommendations::get( $rid ) ) ) {
			return new WP_Error( 'rwga_rec_missing', __( 'Recommendation not found.', 'reactwoo-geo-ai' ) );
		}
		if ( $pid > 0 && ! get_post( $pid ) instanceof WP_Post ) {
			return new WP_Error( 'rwga_page_missing', __( 'Page not found.', 'reactwoo-geo-ai' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		return array(
			'recommendation_id' => isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0,
			'page_id'           => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
			'geo_target'        => isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '',
		);
	}

	/**
	 * Copy page/geo from the linked recommendation when omitted.
	 *
	 * @param array<string, mixed> $in Sanitised payload.
	 * @return array<string, mixed>
	 */
	private function enrich_from_recommendation( array $in ) {
		$rid = isset( $in['recommendation_id'] ) ? (int) $in['recommendation_id'] : 0;
		if ( $rid <= 0 ) {
			return $in;
		}
		$rec = RWGA_DB_Recommendations::get( $rid );
		if ( ! is_array( $rec ) ) {
			return $in;
		}
		if ( empty( $in['page_id'] ) && ! empty( $rec['page_id'] ) ) {
			$in['page_id'] = (int) $rec['page_id'];
		}
		if ( ( ! isset( $in['geo_target'] ) || '' === $in['geo_target'] ) && ! empty( $rec['geo_target'] ) ) {
			$in['geo_target'] = strtoupper( substr( sanitize_text_field( (string) $rec['geo_target'] ), 0, 2 ) );
		}
		return $in;
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

		$in  = $this->build_request_payload( $input );
		$in  = $this->enrich_from_recommendation( $in );
		$raw = $this->produce_stub_drafts( $in );
		$norm = $this->normalise_response( $raw );

		$persisted = $this->persist( $in, $norm );
		if ( is_array( $persisted ) && empty( $persisted['success'] ) ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not save drafts.', 'reactwoo-geo-ai' );
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
			'workflow_key'    => $this->get_key(),
			'agent_key'       => $this->get_agent_key(),
			'drafts'          => isset( $response['drafts'] ) && is_array( $response['drafts'] ) ? $response['drafts'] : array(),
			'schema_version'  => self::DEFAULT_SCHEMA_VERSION,
		);
	}

	/**
	 * @param array<string, mixed> $input    Input used.
	 * @param array<string, mixed> $result   Normalised result.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result ) {
		$uid = get_current_user_id();
		$ids = array();

		$drafts = isset( $result['drafts'] ) && is_array( $result['drafts'] ) ? $result['drafts'] : array();

		foreach ( $drafts as $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			$payload = isset( $d['draft_payload'] ) && is_array( $d['draft_payload'] ) ? $d['draft_payload'] : array();

			$rid_in = isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0;
			$pid_in = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
			$geo_in = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';

			$id = RWGA_DB_Implementation_Drafts::insert(
				array(
					'recommendation_id' => $rid_in > 0 ? $rid_in : null,
					'workflow_key'      => $this->get_key(),
					'draft_type'        => isset( $d['draft_type'] ) ? sanitize_key( (string) $d['draft_type'] ) : 'copy',
					'page_id'           => $pid_in > 0 ? $pid_in : null,
					'geo_target'        => $geo_in,
					'title'             => isset( $d['title'] ) ? sanitize_text_field( (string) $d['title'] ) : '',
					'input_context'     => isset( $d['input_context'] ) ? wp_kses_post( (string) $d['input_context'] ) : '',
					'draft_payload'     => $payload,
					'status'            => 'draft',
					'created_by'        => $uid > 0 ? $uid : null,
				)
			);
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No draft rows were saved.', 'reactwoo-geo-ai' ),
			);
		}

		$page_for_mem = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo_for_mem  = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';

		RWGA_Memory_Service::append(
			'copy_drafts_generated',
			'implementation_draft',
			$ids[0],
			$page_for_mem,
			$geo_for_mem,
			array(
				'workflow_key' => $this->get_key(),
				'draft_ids'    => $ids,
				'count'        => count( $ids ),
			)
		);

		return array(
			'success'   => true,
			'draft_ids' => $ids,
			'result'    => $result,
		);
	}

	/**
	 * @param array<string, mixed> $in Sanitised payload.
	 * @return array<string, mixed>
	 */
	private function produce_stub_drafts( array $in ) {
		$rid = isset( $in['recommendation_id'] ) ? (int) $in['recommendation_id'] : 0;
		$pid = isset( $in['page_id'] ) ? (int) $in['page_id'] : 0;

		$rec    = null;
		$title  = '';
		$blurb  = '';
		$geo    = isset( $in['geo_target'] ) ? trim( (string) $in['geo_target'] ) : '';
		$ctx    = '';

		if ( $rid > 0 ) {
			$rec = RWGA_DB_Recommendations::get( $rid );
			if ( is_array( $rec ) ) {
				$title = isset( $rec['title'] ) ? (string) $rec['title'] : '';
				$prob  = isset( $rec['problem'] ) ? wp_strip_all_tags( (string) $rec['problem'] ) : '';
				$recmd = isset( $rec['recommendation'] ) ? wp_strip_all_tags( (string) $rec['recommendation'] ) : '';
				$ctx   = trim( $prob . "\n\n" . $recmd );
				if ( empty( $pid ) && ! empty( $rec['page_id'] ) ) {
					$pid = (int) $rec['page_id'];
				}
				if ( '' === $geo && ! empty( $rec['geo_target'] ) ) {
					$geo = (string) $rec['geo_target'];
				}
			}
		}

		if ( $pid > 0 && class_exists( 'RWGA_Page_Context', false ) ) {
			$pc = RWGA_Page_Context::collect( $pid );
			if ( ! empty( $pc['title'] ) && '' === $title ) {
				$title = (string) $pc['title'];
			}
			if ( '' === $blurb && ! empty( $pc['content_plain'] ) ) {
				$plain = (string) $pc['content_plain'];
				$blurb = strlen( $plain ) > 240 ? substr( $plain, 0, 240 ) . '…' : $plain;
			}
		}

		if ( '' === $ctx && '' !== $blurb ) {
			$ctx = $blurb;
		}

		$h = $title ? __( 'Turn clarity into conversion', 'reactwoo-geo-ai' ) : __( 'Lead with a clear customer outcome', 'reactwoo-geo-ai' );
		if ( $title ) {
			$h = sprintf(
				/* translators: %s: page or issue title */
				__( 'Better story for: %s', 'reactwoo-geo-ai' ),
				$title
			);
		}

		$drafts = array(
			array(
				'draft_type'    => 'hero_rewrite',
				'title'         => __( 'Hero — primary option', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'headline'    => $h,
					'subheadline' => __( 'Explain the outcome in one sentence, then how you deliver it.', 'reactwoo-geo-ai' ),
					'cta_text'    => __( 'Get started', 'reactwoo-geo-ai' ),
				),
			),
			array(
				'draft_type'    => 'cta_variant',
				'title'         => __( 'CTA — alternatives', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'primary_cta'   => __( 'Book a demo', 'reactwoo-geo-ai' ),
					'secondary_cta' => __( 'See pricing', 'reactwoo-geo-ai' ),
				),
			),
			array(
				'draft_type'    => 'trust_snippet',
				'title'         => __( 'Trust — proof line', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'text' => __( 'Trusted by teams in 12 countries — measurable results in 30 days.', 'reactwoo-geo-ai' ),
				),
			),
		);

		/**
		 * Filter stub copy drafts before persistence and normalisation.
		 *
		 * @param array<int, array<string, mixed>> $drafts Draft rows.
		 * @param array<string, mixed>            $in     Sanitised input.
		 */
		$drafts = apply_filters( 'rwga_copy_implement_stub_drafts', $drafts, $in );

		return array( 'drafts' => is_array( $drafts ) ? $drafts : array() );
	}
}
