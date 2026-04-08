<?php
/**
 * Automation runner: updates rule timestamps and memory (workflow dispatch is future work).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks a rule as executed and writes a memory event. Scheduled rules are picked up by {@see RWGA_Cron}.
 */
class RWGA_Automation_Runner {

	/**
	 * Execute one rule (stub): updates last/next timestamps.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function run( $rule_id ) {
		$rule_id = (int) $rule_id;
		if ( $rule_id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$rule = RWGA_DB_Automation_Rules::get( $rule_id );
		if ( ! is_array( $rule ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Automation rule not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		$status = isset( $rule['status'] ) ? sanitize_key( (string) $rule['status'] ) : '';
		if ( 'active' !== $status ) {
			return new WP_Error( 'rwga_rule_inactive', __( 'Rule is not active.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$last = current_time( 'mysql', true );
		$next = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

		$ok = RWGA_DB_Automation_Rules::touch_run( $rule_id, $last, $next );
		if ( ! $ok ) {
			return new WP_Error( 'rwga_persist', __( 'Could not update run timestamps.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$page_id = isset( $rule['page_id'] ) ? (int) $rule['page_id'] : 0;
		$geo     = isset( $rule['geo_target'] ) && $rule['geo_target'] ? (string) $rule['geo_target'] : '';

		RWGA_Memory_Service::append(
			'automation_ran',
			'automation_rule',
			$rule_id,
			$page_id,
			$geo,
			array(
				'workflow_key' => isset( $rule['workflow_key'] ) ? (string) $rule['workflow_key'] : '',
				'trigger_type' => isset( $rule['trigger_type'] ) ? (string) $rule['trigger_type'] : '',
				'stub'         => true,
			)
		);

		return array(
			'success'      => true,
			'rule_id'      => $rule_id,
			'last_run_at'  => $last,
			'next_run_at'  => $next,
		);
	}
}
