<?php
/**
 * WordPress REST API for bounded Geo AI workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers geo-ai/v1 routes.
 */
class RWGA_REST {

	const NS = 'geo-ai/v1';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/analyse/ux',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_analyse_ux' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/agents',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_agents' ),
				'permission_callback' => array( __CLASS__, 'permission_read_agents' ),
			)
		);

		register_rest_route(
			self::NS,
			'/analyses',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_analyses_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/analyses/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_analysis_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/recommend/ux',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_recommend_ux' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/recommendations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_recommendations_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'              => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'          => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'analysis_run_id'   => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/recommendations/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_recommendation_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/implement/copy',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_implement_copy' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/implement/seo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_implement_seo' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/implementation-drafts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_implementation_drafts_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'               => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'           => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'recommendation_id'  => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'workflow_key'       => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/implementation-drafts/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_implementation_draft_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/research/competitors',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_research_competitors' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/competitor-research',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_competitor_research_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page_id'  => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/competitor-research/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_competitor_research_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_automation_rules_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_automation_rules_create' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_automations' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_automation_rule_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'handle_automation_rule_patch' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_automations' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'handle_automation_rule_delete' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_automations' ),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_automation_rule_run' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_run_ai() {
		if ( ! RWGA_Capabilities::current_user_can_run_ai() ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		if ( ! RWGA_License::can_run_workflows() ) {
			return new WP_Error( 'rwga_unlicensed', __( 'Geo AI license not configured.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_read_agents() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_view_reports() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_manage_automations() {
		if ( ! RWGA_Capabilities::current_user_can_manage_automations() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission to manage automations.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * POST /geo-ai/v1/analyse/ux
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_analyse_ux( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'ux_analysis' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/agents
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_agents() {
		return rest_ensure_response( array( 'agents' => RWGA_Agent_Registry::all() ) );
	}

	/**
	 * GET /geo-ai/v1/analyses
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_analyses_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$total    = RWGA_DB_Analysis_Runs::count_rows();
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$rows     = RWGA_DB_Analysis_Runs::list_paged( $per_page, $page );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'runs'     => $rows,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/analyses/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_analysis_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid analysis id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$run = RWGA_DB_Analysis_Runs::get( $id );
		if ( ! is_array( $run ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Analysis not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		$findings = RWGA_DB_Analysis_Findings::list_for_run( $id );

		return rest_ensure_response(
			array(
				'run'      => $run,
				'findings' => $findings,
			)
		);
	}

	/**
	 * POST /geo-ai/v1/recommend/ux
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_recommend_ux( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'ux_recommend' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/recommendations
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_recommendations_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$filter   = max( 0, (int) $request->get_param( 'analysis_run_id' ) );

		$total = RWGA_DB_Recommendations::count_rows( $filter );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Recommendations::list_paged( $per_page, $page, $filter );

		return rest_ensure_response(
			array(
				'total'             => $total,
				'pages'             => $pages,
				'page'              => $page,
				'per_page'          => $per_page,
				'analysis_run_id'   => $filter,
				'recommendations'   => $rows,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/recommendations/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_recommendation_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid recommendation id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Recommendations::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Recommendation not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'recommendation' => $row ) );
	}

	/**
	 * POST /geo-ai/v1/implement/copy
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_implement_copy( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'copy_implement' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * POST /geo-ai/v1/implement/seo
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_implement_seo( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'seo_implement' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/implementation-drafts
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_implementation_drafts_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$filter   = max( 0, (int) $request->get_param( 'recommendation_id' ) );
		$wk       = sanitize_key( (string) $request->get_param( 'workflow_key' ) );

		$total = RWGA_DB_Implementation_Drafts::count_rows( $filter, $wk );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Implementation_Drafts::list_paged( $per_page, $page, $filter, $wk );

		return rest_ensure_response(
			array(
				'total'             => $total,
				'pages'             => $pages,
				'page'              => $page,
				'per_page'          => $per_page,
				'recommendation_id' => $filter,
				'workflow_key'      => $wk,
				'drafts'            => array_map( array( __CLASS__, 'decode_implementation_draft_row' ), $rows ),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/implementation-drafts/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_implementation_draft_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid draft id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Implementation_Drafts::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Implementation draft not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'draft' => self::decode_implementation_draft_row( $row ),
			)
		);
	}

	/**
	 * JSON-decode payload fields for API consumers.
	 *
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_implementation_draft_row( array $row ) {
		foreach ( array( 'draft_payload', 'diff_payload' ) as $k ) {
			if ( ! isset( $row[ $k ] ) || ! is_string( $row[ $k ] ) || '' === $row[ $k ] ) {
				continue;
			}
			$dec = json_decode( $row[ $k ], true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) {
				$row[ $k ] = $dec;
			}
		}
		return $row;
	}

	/**
	 * POST /geo-ai/v1/research/competitors
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_research_competitors( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'competitor_research' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/competitor-research
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_competitor_research_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$filter   = max( 0, (int) $request->get_param( 'page_id' ) );

		$total = RWGA_DB_Competitor_Research::count_rows( $filter );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Competitor_Research::list_paged( $per_page, $page, $filter );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'page_id'  => $filter,
				'items'    => $rows,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/competitor-research/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_competitor_research_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Competitor_Research::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Competitor research not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'item' => $row ) );
	}

	/**
	 * GET /geo-ai/v1/automation/rules
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_automation_rules_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$total    = RWGA_DB_Automation_Rules::count_rows();
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$rows     = RWGA_DB_Automation_Rules::list_paged( $per_page, $page );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'rules'    => array_map( array( __CLASS__, 'decode_automation_rule_row' ), $rows ),
			)
		);
	}

	/**
	 * POST /geo-ai/v1/automation/rules
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rules_create( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'rwga_bad_input', __( 'name is required.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$wk = isset( $input['workflow_key'] ) ? sanitize_key( (string) $input['workflow_key'] ) : '';
		if ( '' === $wk ) {
			return new WP_Error( 'rwga_bad_input', __( 'workflow_key is required.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$id = RWGA_DB_Automation_Rules::insert(
			array(
				'name'          => $name,
				'workflow_key'  => $wk,
				'trigger_type'  => isset( $input['trigger_type'] ) ? sanitize_key( (string) $input['trigger_type'] ) : 'manual',
				'target_scope'  => isset( $input['target_scope'] ) ? sanitize_key( (string) $input['target_scope'] ) : 'site',
				'page_id'       => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
				'geo_target'    => isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
				'rule_config'   => isset( $input['rule_config'] ) && is_array( $input['rule_config'] ) ? $input['rule_config'] : array(),
				'status'        => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'active',
				'created_by'    => get_current_user_id(),
			)
		);

		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_persist', __( 'Could not create rule.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$row = RWGA_DB_Automation_Rules::get( $id );
		return rest_ensure_response(
			array(
				'rule' => is_array( $row ) ? self::decode_automation_rule_row( $row ) : null,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/automation/rules/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Automation_Rules::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Rule not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'rule' => self::decode_automation_rule_row( $row ) ) );
	}

	/**
	 * PATCH /geo-ai/v1/automation/rules/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_patch( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$ok = RWGA_DB_Automation_Rules::update_rule(
			$id,
			array(
				'name'          => isset( $input['name'] ) ? (string) $input['name'] : '',
				'workflow_key'  => isset( $input['workflow_key'] ) ? (string) $input['workflow_key'] : '',
				'trigger_type'  => isset( $input['trigger_type'] ) ? (string) $input['trigger_type'] : 'manual',
				'target_scope'  => isset( $input['target_scope'] ) ? (string) $input['target_scope'] : 'site',
				'page_id'       => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
				'geo_target'    => isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
				'rule_config'   => isset( $input['rule_config'] ) && is_array( $input['rule_config'] ) ? $input['rule_config'] : array(),
				'status'        => isset( $input['status'] ) ? (string) $input['status'] : 'active',
			)
		);

		if ( ! $ok ) {
			return new WP_Error( 'rwga_persist', __( 'Could not update rule.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$row = RWGA_DB_Automation_Rules::get( $id );
		return rest_ensure_response( array( 'rule' => is_array( $row ) ? self::decode_automation_rule_row( $row ) : null ) );
	}

	/**
	 * DELETE /geo-ai/v1/automation/rules/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_delete( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$ok = RWGA_DB_Automation_Rules::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'rwga_not_found', __( 'Rule not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * POST /geo-ai/v1/automation/rules/(?P<id>\\d+)/run
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_run( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$out = RWGA_Automation_Runner::run( $id );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_automation_rule_row( array $row ) {
		if ( isset( $row['rule_config'] ) && is_string( $row['rule_config'] ) && '' !== $row['rule_config'] ) {
			$dec = json_decode( $row['rule_config'], true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) {
				$row['rule_config'] = $dec;
			}
		}
		return $row;
	}
}
