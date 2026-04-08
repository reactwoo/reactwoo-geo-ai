<?php
/**
 * Registered bounded workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workflow registry singleton map.
 */
class RWGA_Workflow_Registry {

	/**
	 * @var array<string, RWGA_Workflow_Interface>
	 */
	private static $workflows = array();

	/**
	 * @return void
	 */
	public static function init() {
		self::$workflows['ux_analysis'] = new RWGA_Workflow_UX_Analysis();

		/**
		 * Register additional Geo AI workflows.
		 *
		 * @param array<string, RWGA_Workflow_Interface> $workflows Workflow instances keyed by workflow key.
		 */
		$extra = apply_filters( 'rwga_register_workflows', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $key => $wf ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! $wf instanceof RWGA_Workflow_Interface ) {
					continue;
				}
				self::$workflows[ $key ] = $wf;
			}
		}
	}

	/**
	 * @param string $key Workflow key.
	 * @return RWGA_Workflow_Interface|null
	 */
	public static function get( $key ) {
		$key = sanitize_key( (string) $key );
		return isset( self::$workflows[ $key ] ) ? self::$workflows[ $key ] : null;
	}

	/**
	 * @return array<string, RWGA_Workflow_Interface>
	 */
	public static function all() {
		return self::$workflows;
	}
}
