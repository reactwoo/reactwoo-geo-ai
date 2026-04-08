<?php
/**
 * Remote workflow dispatch via Geo Core platform JWT.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST workflow payload to api.reactwoo.com (or filtered base).
 */
class RWGA_Remote_Client {

	/**
	 * Default API path (appended to {@see RWGC_Platform_Client::get_api_base()}).
	 */
	const DEFAULT_PATH = '/api/v5/geo-ai/workflow';

	/**
	 * Execute a workflow on the remote engine.
	 *
	 * On success returns array with keys: remote_run_id (string, may be empty), engine_response (array).
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $payload      Sanitised workflow input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function dispatch( $workflow_key, array $payload ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		if ( '' === $workflow_key ) {
			return new WP_Error( 'rwga_bad_workflow', __( 'Invalid workflow key.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'RWGC_Platform_Client', false ) ) {
			return new WP_Error(
				'rwga_no_platform',
				__( 'ReactWoo Geo Core platform client is required for remote workflows.', 'reactwoo-geo-ai' ),
				array( 'status' => 500 )
			);
		}

		$path = apply_filters( 'rwga_remote_workflow_path', self::DEFAULT_PATH, $workflow_key, $payload );
		$path = is_string( $path ) ? trim( $path ) : self::DEFAULT_PATH;
		if ( '' === $path || '/' !== $path[0] ) {
			return new WP_Error( 'rwga_bad_path', __( 'Invalid remote workflow path.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$site_uuid = function_exists( 'rwga_get_site_uuid' ) ? (string) rwga_get_site_uuid() : '';

		$body = array(
			'workflow_key' => $workflow_key,
			'payload'      => $payload,
			'site'         => array(
				'uuid' => $site_uuid,
				'url'  => home_url( '/' ),
			),
		);

		/**
		 * Filter JSON body for POST /api/v5/geo-ai/workflow (or filtered path).
		 *
		 * @param array<string, mixed> $body         Request body.
		 * @param string               $workflow_key Workflow key.
		 * @param array<string, mixed> $payload      Original payload.
		 */
		$body = apply_filters( 'rwga_remote_workflow_body', $body, $workflow_key, $payload );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$result = RWGC_Platform_Client::request( 'POST', $path, $body, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;

		if ( $code < 200 || $code >= 300 || null === $data ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : __( 'Remote workflow request failed.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_remote_failed', $msg, array( 'status' => $code ) );
		}

		$remote_run_id = '';
		if ( isset( $data['remote_run_id'] ) ) {
			$remote_run_id = (string) $data['remote_run_id'];
		} elseif ( isset( $data['run_id'] ) ) {
			$remote_run_id = (string) $data['run_id'];
		}

		$engine_response = null;
		if ( isset( $data['result'] ) && is_array( $data['result'] ) ) {
			$engine_response = $data['result'];
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$engine_response = $data['data'];
		} else {
			$engine_response = $data;
		}

		$parsed = array(
			'remote_run_id'   => $remote_run_id,
			'engine_response' => is_array( $engine_response ) ? $engine_response : array(),
		);

		/**
		 * Normalised remote workflow response before the workflow consumes it.
		 *
		 * @param array<string, mixed> $parsed       Keys: remote_run_id, engine_response.
		 * @param array<string, mixed> $data         Raw API JSON.
		 * @param string               $workflow_key Workflow key.
		 */
		$parsed = apply_filters( 'rwga_remote_workflow_response', $parsed, $data, $workflow_key );
		if ( ! is_array( $parsed ) || empty( $parsed['engine_response'] ) || ! is_array( $parsed['engine_response'] ) ) {
			return new WP_Error( 'rwga_remote_shape', __( 'Remote response did not include a usable result.', 'reactwoo-geo-ai' ), array( 'status' => 502 ) );
		}

		return $parsed;
	}
}
