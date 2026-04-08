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
}
