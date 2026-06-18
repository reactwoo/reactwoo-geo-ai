<?php
/**
 * Send approved/rejected interpretation feedback to ReactWoo API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Learning_Event_Service {

	const OPTION_ENABLED     = 'geo_ai_learning_events_enabled';
	const OPTION_LOCAL_LOG   = 'geo_ai_learning_events_log';
	const MAX_LOCAL_EVENTS   = 100;
	const LEARNING_PATH      = '/api/v1/intelligence/geocore/learning-event';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwga_intelligence_interpretation_feedback', array( __CLASS__, 'on_feedback' ), 10, 2 );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		$default = true;
		return (bool) apply_filters( 'rwga_learning_events_enabled', (bool) get_option( self::OPTION_ENABLED, $default ) );
	}

	/**
	 * @param array<string,mixed> $interpretation Interpretation result.
	 * @param array<string,mixed> $feedback       Feedback payload.
	 * @return void
	 */
	public static function on_feedback( array $interpretation, array $feedback ) {
		self::record(
			array_merge(
				$interpretation,
				$feedback
			)
		);
	}

	/**
	 * @param array<string,mixed> $payload Event payload.
	 * @return array{success:bool,message:string}
	 */
	public static function record( array $payload ) {
		$event = self::build_event( $payload );
		self::append_local_log( $event );

		if ( ! self::is_enabled() ) {
			return array(
				'success' => true,
				'message' => __( 'Learning event stored locally (sync disabled).', 'reactwoo-geo-ai' ),
			);
		}

		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return array(
				'success' => false,
				'message' => __( 'Platform client unavailable.', 'reactwoo-geo-ai' ),
			);
		}

		$res = RWGA_Platform_Client::request( 'POST', self::LEARNING_PATH, $event, true );
		if ( is_wp_error( $res ) ) {
			return array(
				'success' => false,
				'message' => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP status */
					__( 'Learning event sync failed (HTTP %d).', 'reactwoo-geo-ai' ),
					$code
				),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Learning event sent.', 'reactwoo-geo-ai' ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_local_log() {
		$log = get_option( self::OPTION_LOCAL_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	private static function build_event( array $payload ) {
		$site_hash = hash( 'sha256', (string) home_url( '/' ) );
		$license   = class_exists( 'RWGA_Platform_Client', false ) ? RWGA_Platform_Client::get_license_key() : '';
		$license_hash = '' !== $license ? hash( 'sha256', $license ) : hash( 'sha256', 'no-license' );

		return array(
			'site_hash'            => $site_hash,
			'license_hash'         => $license_hash,
			'plugin_version'       => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'satellite_version'    => RWGA_VERSION,
			'raw_phrase'           => (string) ( $payload['raw_phrase'] ?? $payload['normalised_phrase'] ?? '' ),
			'normalised_phrase'    => (string) ( $payload['normalised_phrase'] ?? '' ),
			'context_type'         => (string) ( $payload['context_type'] ?? '' ),
			'resolved_target_type' => (string) ( $payload['resolved_target_type'] ?? ( $payload['resolved_target']['type'] ?? '' ) ),
			'intent_key'           => (string) ( $payload['intent'] ?? $payload['intent_key'] ?? '' ),
			'action_key'           => (string) ( $payload['matched_action'] ?? $payload['action_key'] ?? '' ),
			'params'               => isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array(),
			'confidence'           => (float) ( $payload['confidence'] ?? 0 ),
			'outcome'              => (string) ( $payload['outcome'] ?? 'accepted' ),
			'correction'           => isset( $payload['correction'] ) && is_array( $payload['correction'] ) ? $payload['correction'] : null,
			'approved_by_user'     => ! empty( $payload['approved_by_user'] ),
		);
	}

	/**
	 * @param array<string,mixed> $event Event row.
	 * @return void
	 */
	private static function append_local_log( array $event ) {
		$log   = self::get_local_log();
		$log[] = array_merge( $event, array( 'recorded_at' => gmdate( 'c' ) ) );
		if ( count( $log ) > self::MAX_LOCAL_EVENTS ) {
			$log = array_slice( $log, -self::MAX_LOCAL_EVENTS );
		}
		update_option( self::OPTION_LOCAL_LOG, $log, false );
	}
}
