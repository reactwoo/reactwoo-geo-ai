<?php
/**
 * Detect geo action type from a single clause.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Action_Type_Detector {

	/**
	 * @param string $clause Normalised clause.
	 * @return array{type:string,visibility:string,mode:string,confidence:float}
	 */
	public static function detect( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$visibility = 'show';
		$mode       = 'update';

		if ( preg_match( '/\b(?:test|preview|simulate|check what)\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_TEST,
				'visibility' => 'show',
				'mode'       => 'test',
				'confidence' => 0.86,
			);
		}
		if ( preg_match( '/\b(?:diagnose|debug|troubleshoot|why (?:is|does))\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::DIAGNOSE,
				'visibility' => 'show',
				'mode'       => 'diagnose',
				'confidence' => 0.84,
			);
		}
		if ( preg_match( '/\b(?:hide|exclude|block|do not show|don\'t show)\b/i', $clause ) ) {
			$visibility = 'hide';
			$mode       = 'create';
			if ( preg_match( '/\bpopup\b/i', $clause ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => $visibility,
					'mode'       => $mode,
					'confidence' => 0.88,
				);
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::HIDE,
				'visibility' => $visibility,
				'mode'       => $mode,
				'confidence' => 0.82,
			);
		}
		if ( preg_match( '/\b(?:update|keep|leave)\s+(?:the\s+)?original\b|\bupdate\s+(?:the\s+)?(?:homepage|shop)\b/i', $clause ) ) {
			if ( preg_match( '/\b(?:only|just)\s+(?:show|display)\b|\bonly\s+show\b/i', $clause ) ) {
				$visibility = 'only_show';
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.9,
			);
		}
		if ( preg_match( '/\b(?:create|make|build|duplicate|copy|clone)\s+(?:an?\s+)?(?:additional\s+)?(?:\d+|one|two|three|four|five)?\s*(?:new\s+)?(?:variants?|variations?|versions?)\b/i', $clause )
			|| preg_match( '/\b(?:one|another)\s+(?:variant|variation|version)\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
				'visibility' => 'show',
				'mode'       => 'create',
				'confidence' => 0.88,
			);
		}
		if ( preg_match( '/\b(?:show|display|target|visible)\b/i', $clause ) ) {
			if ( preg_match( '/\b(?:only|just)\b/i', $clause ) ) {
				$visibility = 'only_show';
			}
			if ( preg_match( '/\bpopup\b/i', $clause ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => $visibility,
					'mode'       => 'create',
					'confidence' => 0.85,
				);
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.84,
			);
		}
		return array(
			'type'       => RWGA_Geo_Action_Types::SHOW,
			'visibility' => $visibility,
			'mode'       => $mode,
			'confidence' => 0.5,
		);
	}
}
