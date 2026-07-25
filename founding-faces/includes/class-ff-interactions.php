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

	/**
	 * Whether an interaction of a given type already exists for a member.
	 *
	 * @param int    $member_id    The member's user id.
	 * @param string $type         The interaction type.
	 * @param int    $reference_id The referenced id.
	 * @return bool
	 */
	public static function has( $member_id, $type, $reference_id ) {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ff_interactions WHERE member_id = %d AND type = %s AND reference_id = %d LIMIT 1",
				(int) $member_id,
				$type,
				(int) $reference_id
			)
		);

		return null !== $found;
	}

	/**
	 * Record an interaction only if it hasn't been recorded before.
	 *
	 * Used for "first viewed" style events, where we want one row per member and
	 * thing, not one per visit.
	 *
	 * @param int    $member_id    The member's user id.
	 * @param string $type         The interaction type.
	 * @param int    $reference_id The referenced id.
	 * @return void
	 */
	public static function log_once( $member_id, $type, $reference_id ) {
		if ( ! self::has( $member_id, $type, $reference_id ) ) {
			self::log( $member_id, $type, $reference_id );
		}
	}
}
