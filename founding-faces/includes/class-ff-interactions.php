<?php
/**
 * The interaction-log spine.
 *
 * A single, tiny helper for writing a row to ff_interactions. Every meaningful
 * member action in the plugin is recorded through this one method, so the
 * personal-history page and the later launch showcase can be built from the
 * same honest record with no retrofit (governing principle 3).
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Interactions
 *
 * The write side of the spine. Reading it back for the history page comes in a
 * later stage; for now everything that happens gets logged through here.
 */
class FF_Interactions {

	/**
	 * Record one interaction against a member.
	 *
	 * @param int      $member_id    The WordPress user ID of the member.
	 * @param string   $type         A short label for what happened,
	 *                               e.g. 'approved', 'vote_cast', 'note_viewed'.
	 * @param int|null $reference_id Optional ID of the thing it relates to
	 *                               (a poll, a note, an application), or null.
	 * @return void
	 */
	public static function log( $member_id, $type, $reference_id = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ff_interactions',
			array(
				'member_id'    => (int) $member_id,
				'type'         => $type,
				'reference_id' => ( null === $reference_id ) ? null : (int) $reference_id,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', ( null === $reference_id ) ? null : '%d', '%s' )
		);
	}
}
