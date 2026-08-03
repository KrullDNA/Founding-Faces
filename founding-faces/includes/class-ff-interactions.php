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
	 * Log an interaction once for each of many reference ids.
	 *
	 * The batched form of log_once(): one query to find what's already recorded,
	 * then one insert per genuinely new id. Used when a list renders many notes
	 * at once, where calling log_once() per note would mean a query each.
	 *
	 * @param int    $member_id The member's user id.
	 * @param string $type      The interaction type.
	 * @param int[]  $ids       The reference ids.
	 */
	public static function log_once_many( $member_id, $type, $ids ) {
		$member_id = (int) $member_id;
		$ids       = array_unique( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( ! $member_id || empty( $ids ) ) {
			return;
		}

		$seen = array_flip( self::reference_ids( $member_id, $type ) );
		foreach ( $ids as $id ) {
			if ( ! isset( $seen[ $id ] ) ) {
				self::log( $member_id, $type, $id );
			}
		}
	}

	/**
	 * The reference ids a member has already logged for a given type.
	 *
	 * One query instead of a has() call per item, for the places that need to
	 * ask "which of these has this member seen?" — such as counting unread
	 * notes for the menu bubble.
	 *
	 * @param int    $member_id The member's user id.
	 * @param string $type      The interaction type (e.g. 'note_viewed').
	 * @return int[] Reference ids, as integers.
	 */
	public static function reference_ids( $member_id, $type ) {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT reference_id FROM {$wpdb->prefix}ff_interactions WHERE member_id = %d AND type = %s",
				(int) $member_id,
				$type
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Get a member's own interaction rows, newest first.
	 *
	 * Reads only the given member's rows — the caller passes the current
	 * member's id, so no one ever sees another member's history.
	 *
	 * @param int         $member_id The member's user id.
	 * @param string|null $type      Optional interaction type to filter by.
	 * @param int         $limit     Maximum rows to return.
	 * @return array
	 */
	public static function get_for_member( $member_id, $type = null, $limit = 200 ) {
		global $wpdb;

		$limit = max( 1, (int) $limit );

		if ( null === $type ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ff_interactions WHERE member_id = %d ORDER BY created_at DESC LIMIT %d",
					(int) $member_id,
					$limit
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ff_interactions WHERE member_id = %d AND type = %s ORDER BY created_at DESC LIMIT %d",
				(int) $member_id,
				$type,
				$limit
			)
		);
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
