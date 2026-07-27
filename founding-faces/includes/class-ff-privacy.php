<?php
/**
 * Privacy: exporting and deleting a member's data.
 *
 * This is the reusable core the account page (self-service) and the admin
 * tools (Stage 12) both call. Because the plugin holds real women's names,
 * skin concerns and email addresses, these operations are deliberate and
 * complete: an export gathers everything held about one member, and a delete
 * removes the personal data while retiring — never reusing — the number, so the
 * honest record survives without keeping anyone's personal details.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Privacy
 *
 * Export and delete, kept in one place so self-service and admin behave
 * identically.
 */
class FF_Privacy {

	/**
	 * Fetch the application row linked to a member.
	 *
	 * @param int $user_id The member's user id.
	 * @return object|null
	 */
	public static function get_application( $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ff_applications WHERE user_id = %d ORDER BY id DESC LIMIT 1",
				(int) $user_id
			)
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Export.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Build a member's full record as CSV text.
	 *
	 * Gathers the profile, the application answers, the poll votes and the
	 * interaction log for one member, in readable key/value and tabular blocks.
	 *
	 * @param int $user_id The member's user id.
	 * @return string The CSV content.
	 */
	public static function export_csv( $user_id ) {
		$user = get_userdata( $user_id );
		$app  = self::get_application( $user_id );

		$number = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$group  = get_user_meta( $user_id, FF_Members::META_GROUP, true );

		$handle = fopen( 'php://temp', 'r+' );

		// Profile block.
		fputcsv( $handle, array( 'Profile' ) );
		fputcsv( $handle, array( 'Founding number', $number ? $number : '' ) );
		fputcsv( $handle, array( 'Group', 'the-35' === $group ? 'The 35' : ( 'the-circle' === $group ? 'The Circle' : '' ) ) );
		fputcsv( $handle, array( 'Public name', get_user_meta( $user_id, FF_Members::META_PUBLIC_NAME, true ) ) );
		fputcsv( $handle, array( 'Email', $user ? $user->user_email : '' ) );
		fputcsv( $handle, array( 'Account active', get_user_meta( $user_id, FF_Members::META_DEACTIVATED, true ) ? 'No' : 'Yes' ) );
		fputcsv( $handle, array() );

		// Application block.
		fputcsv( $handle, array( 'Application' ) );
		if ( $app ) {
			fputcsv( $handle, array( 'Real name', $app->name ) );
			fputcsv( $handle, array( 'Email', $app->email ) );
			fputcsv( $handle, array( 'Postcode', $app->postcode ) );
			fputcsv( $handle, array( 'Instagram', $app->instagram ) );
			fputcsv( $handle, array( 'Skin concerns', $app->skin_concerns ) );
			fputcsv( $handle, array( 'Answers', $app->answers ) );
			fputcsv( $handle, array( 'Consent to emails', $app->consent ? 'Yes' : 'No' ) );
			fputcsv( $handle, array( 'Consent recorded', $app->consent_at ) );
			fputcsv( $handle, array( 'Applied', $app->created_at ) );
			fputcsv( $handle, array( 'Status', $app->status ) );
		} else {
			fputcsv( $handle, array( 'No application record found.' ) );
		}
		fputcsv( $handle, array() );

		// Votes block.
		fputcsv( $handle, array( 'Poll votes' ) );
		fputcsv( $handle, array( 'Poll', 'Your choice', 'When' ) );
		foreach ( FF_Polls::member_votes( $user_id ) as $vote ) {
			fputcsv( $handle, array(
				get_the_title( (int) $vote->poll_id ),
				FF_Polls::option_label( (int) $vote->poll_id, (int) $vote->option_id ),
				$vote->voted_at,
			) );
		}
		fputcsv( $handle, array() );

		// Interaction log block.
		fputcsv( $handle, array( 'Activity' ) );
		fputcsv( $handle, array( 'Type', 'Reference', 'When' ) );
		foreach ( FF_Interactions::get_for_member( $user_id, null, 1000 ) as $row ) {
			fputcsv( $handle, array( $row->type, $row->reference_id, $row->created_at ) );
		}
		fputcsv( $handle, array() );

		// Private messages block: their conversations with Nick.
		if ( class_exists( 'FF_Messages' ) ) {
			fputcsv( $handle, array( 'Private messages' ) );
			fputcsv( $handle, array( 'When', 'From', 'Topic', 'Message', 'Attachment' ) );
			foreach ( FF_Messages::member_messages_for_export( $user_id ) as $m ) {
				fputcsv( $handle, array(
					$m->created_at,
					'admin' === $m->sender ? 'Apotheca' : 'Member',
					FF_Messages::thread_title( $m ),
					$m->body,
					$m->attachment_name,
				) );
			}
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	/**
	 * Stream a member's export as a CSV download and stop.
	 *
	 * @param int $user_id The member's user id.
	 * @return void
	 */
	public static function stream_export( $user_id ) {
		$csv    = self::export_csv( $user_id );
		$number = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$name   = $number ? ( 'founding-face-' . (int) $number ) : ( 'member-' . (int) $user_id );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '-data.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Delete.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Delete a member's personal data, keeping the honest record.
	 *
	 * Removes the application record and the personal user meta, unsubscribes
	 * them from the email platform, retires (never reuses) their number, and
	 * deactivates and anonymises the account. The pseudonymous votes and
	 * interaction rows stay, so poll aggregates and the record remain truthful,
	 * but nothing personal is kept.
	 *
	 * @param int $user_id The member's user id.
	 * @return true|WP_Error
	 */
	public static function delete_member( $user_id ) {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'ff_no_user', __( 'That member could not be found.', 'founding-faces' ) );
		}

		$number = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$group  = get_user_meta( $user_id, FF_Members::META_GROUP, true );

		// Unsubscribe from the email platform before we lose the email.
		FF_Connectors::unsubscribe_member( $user_id );

		// Retire the number so it stays reserved and is never reused.
		if ( $number ) {
			FF_Members::retire_number( (int) $number );
		}

		// Remove the sensitive application record entirely.
		$wpdb->delete( $wpdb->prefix . 'ff_applications', array( 'user_id' => $user_id ), array( '%d' ) );

		// Remove the member's private messages and their attachment files: their
		// own words and files are personal, so they go with the rest.
		if ( class_exists( 'FF_Messages' ) ) {
			FF_Messages::delete_member_messages( $user_id );
		}

		// Strip the personal user meta.
		delete_user_meta( $user_id, FF_Members::META_REAL_NAME );
		delete_user_meta( $user_id, FF_Members::META_POSTCODE );
		update_user_meta( $user_id, FF_Members::META_CONSENT, 0 );

		// Deactivate and anonymise the account, keeping the number/group so the
		// honest record survives.
		update_user_meta( $user_id, FF_Members::META_DEACTIVATED, 1 );

		$public = $number
			? sprintf( __( 'Founding Face %d', 'founding-faces' ), (int) $number )
			: __( 'Former member', 'founding-faces' );
		update_user_meta( $user_id, FF_Members::META_PUBLIC_NAME, $public );

		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$host  = $host ? $host : 'example.com';
		$email = 'ff-removed-' . (int) $user_id . '@' . $host;

		wp_update_user( array(
			'ID'           => $user_id,
			'user_email'   => $email,
			'display_name' => $public,
			'nickname'     => $public,
			'first_name'   => '',
			'last_name'    => '',
		) );

		return true;
	}
}
