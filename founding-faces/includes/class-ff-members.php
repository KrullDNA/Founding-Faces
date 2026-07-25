<?php
/**
 * Membership: approving an application into a group, creating the WordPress
 * user, assigning and retiring Founding numbers, deactivating a member on
 * withdrawal, and firing the welcome email.
 *
 * This is where an application (the sensitive record) becomes a member (the
 * public-facing WordPress user). The two records stay deliberately separate:
 * the sensitive answers never move onto the member profile.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Members
 *
 * All approval and numbering logic. Numbers are issued from a monotonic
 * sequence that only ever counts up, so a withdrawn number is never handed to
 * anyone else — the record stays truthful.
 */
class FF_Members {

	// User meta: the member's Founding number (only for The 35).
	const META_NUMBER = 'ff_number';

	// User meta: the group slug, mirrored from the taxonomy for fast checks.
	const META_GROUP = 'ff_group_slug';

	// User meta: the real name, kept private and hidden from the public profile.
	const META_REAL_NAME = 'ff_real_name';

	// User meta: the public identity (number for The 35, first name for Circle).
	const META_PUBLIC_NAME = 'ff_public_name';

	// User meta: the id of the application this member was approved from.
	const META_APP_ID = 'ff_application_id';

	// User meta: whether this is a test account (used by the Stage 12 reset).
	const META_IS_TEST = 'ff_is_test';

	// User meta: set when a member is deactivated so their number stays reserved.
	const META_DEACTIVATED = 'ff_deactivated';

	// User meta: whether the member consents to programme emails (mirrored from
	// the application). This is the precondition for any external email sync.
	const META_CONSENT = 'ff_email_consent';

	// User meta: the member's four-digit postcode (mirrored from the
	// application). This is the ONLY location field the members map ever reads;
	// the postal address is a separate, admin-only field the map never touches.
	const META_POSTCODE = 'ff_postcode';

	// Option: the last Founding number issued. Next number is always this + 1.
	const OPT_SEQUENCE = 'ff_number_sequence';

	// Option: the list of retired numbers, kept for audit and the Stage 12 reset.
	const OPT_RETIRED = 'ff_retired_numbers';

	// The admin-post action used by every moderation button.
	const MODERATE_ACTION = 'ff_moderate';

	/**
	 * Wire up the moderation handler and the deactivated-login block.
	 *
	 * Called once on plugin load.
	 */
	public static function register() {
		// Handle the approve / decline / withdraw / resend buttons.
		add_action( 'admin_post_' . self::MODERATE_ACTION, array( __CLASS__, 'handle_moderation' ) );

		// Stop a deactivated member from logging in, so a retired number's owner
		// can't come back in while their number stays reserved.
		add_filter( 'authenticate', array( __CLASS__, 'block_deactivated_login' ), 30, 3 );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Numbering.
	 * A monotonic sequence guarantees "retire, never reuse": the next number is
	 * always one past the highest ever issued, so a gap left by a withdrawal is
	 * never filled.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Issue the next Founding number and advance the sequence.
	 *
	 * @return int The newly assigned number.
	 */
	public static function assign_next_number() {
		$next = (int) get_option( self::OPT_SEQUENCE, 0 ) + 1;
		update_option( self::OPT_SEQUENCE, $next );
		return $next;
	}

	/**
	 * Retire a number so it is recorded as used-and-gone.
	 *
	 * The sequence already sits past this number, so retiring it doesn't change
	 * what gets issued next; the retired list exists for the audit view and for
	 * the guarded test reset in a later stage.
	 *
	 * @param int $number The number to retire.
	 * @return void
	 */
	public static function retire_number( $number ) {
		$number  = (int) $number;
		$retired = (array) get_option( self::OPT_RETIRED, array() );
		if ( ! in_array( $number, $retired, true ) ) {
			$retired[] = $number;
			update_option( self::OPT_RETIRED, $retired );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Approval.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Approve an application into a group, creating the member.
	 *
	 * Creates the WordPress user, assigns the next number if this is The 35,
	 * stores the real name as private meta and the public identity as the
	 * display name, tags the user with the group, links the two records, logs
	 * the event to the spine, and fires the welcome email.
	 *
	 * @param int    $application_id The ff_applications row id.
	 * @param string $group_slug     Either 'the-35' or 'the-circle'.
	 * @return int|WP_Error The new user id, or a WP_Error explaining the failure.
	 */
	public static function approve( $application_id, $group_slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ff_applications';

		// Load the application.
		$app = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $application_id )
		);

		if ( ! $app ) {
			return new WP_Error( 'ff_not_found', __( 'That application could not be found.', 'founding-faces' ) );
		}

		// Don't approve the same application twice.
		if ( ! empty( $app->user_id ) ) {
			return new WP_Error( 'ff_already', __( 'That application has already been approved.', 'founding-faces' ) );
		}

		// A WordPress user can't share an email with an existing one.
		if ( email_exists( $app->email ) ) {
			return new WP_Error( 'ff_email_exists', __( 'A WordPress user already exists with that email address.', 'founding-faces' ) );
		}

		// Work out the public identity and assign a number if this is The 35.
		$is_35        = ( 'the-35' === $group_slug );
		$number       = $is_35 ? self::assign_next_number() : null;
		$first_name   = self::first_name_from( $app->name );
		$public_name  = $is_35 ? sprintf( __( 'Founding Face %d', 'founding-faces' ), $number ) : $first_name;

		// Build a unique, sensible username from the email address.
		$username = self::unique_username_from_email( $app->email );

		// Create the WordPress user. A strong random password is set now; the
		// member sets their own via the secure link in the welcome email.
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $app->email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $public_name,
				'nickname'     => $public_name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			// If we'd already advanced the sequence, retire that number so it is
			// never silently reused after this failure.
			if ( $is_35 && $number ) {
				self::retire_number( $number );
			}
			return $user_id;
		}

		// Store the member's meta. The real name lives only here, privately.
		update_user_meta( $user_id, self::META_REAL_NAME, $app->name );
		update_user_meta( $user_id, self::META_PUBLIC_NAME, $public_name );
		update_user_meta( $user_id, self::META_GROUP, $group_slug );
		update_user_meta( $user_id, self::META_APP_ID, (int) $app->id );
		update_user_meta( $user_id, self::META_IS_TEST, (int) $app->is_test );
		// Mirror the stored consent flag onto the member, so the email-sync
		// gate and the account-page toggle both read from one place.
		update_user_meta( $user_id, self::META_CONSENT, (int) $app->consent );
		// Mirror the postcode so the map can read it without ever touching the
		// sensitive application table or the postal address.
		update_user_meta( $user_id, self::META_POSTCODE, $app->postcode );
		if ( $is_35 ) {
			update_user_meta( $user_id, self::META_NUMBER, $number );
		}

		// Tag the user with the group taxonomy term (drives gating and email
		// segmentation later).
		wp_set_object_terms( $user_id, $group_slug, FF_Post_Types::GROUP_TAXONOMY, false );

		// Update the application: mark decided, link the user, record the number.
		$wpdb->update(
			$table,
			array(
				'status'          => $is_35 ? 'approved-35' : 'approved-circle',
				'user_id'         => $user_id,
				'assigned_number' => $number,
			),
			array( 'id' => (int) $app->id ),
			array( '%s', '%d', ( null === $number ) ? null : '%d' ),
			array( '%d' )
		);

		// Record the moment on the spine.
		FF_Interactions::log( $user_id, 'approved', (int) $app->id );

		// Send the group-specific welcome email.
		self::send_welcome_email( $user_id );

		// Let other parts of the plugin react to a new member — the email
		// connector uses this to sync the member to the active platform.
		do_action( 'ff_member_approved', $user_id );

		return $user_id;
	}

	/**
	 * Decline an application.
	 *
	 * No user is created; the record is simply marked declined. The sensitive
	 * answers stay in ff_applications for Nick's reference until deleted.
	 *
	 * @param int $application_id The ff_applications row id.
	 * @return true|WP_Error
	 */
	public static function decline( $application_id ) {
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->prefix . 'ff_applications',
			array( 'status' => 'declined' ),
			array( 'id' => (int) $application_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'ff_decline_failed', __( 'The application could not be updated.', 'founding-faces' ) );
		}
		return true;
	}

	/**
	 * Withdraw a member: deactivate the account and retire their number.
	 *
	 * The WordPress user is not deleted, so a retired number stays reserved and
	 * the honest record survives. Deletion of personal data is a separate,
	 * deliberate action in the privacy stage.
	 *
	 * @param int $application_id The ff_applications row id.
	 * @return true|WP_Error
	 */
	public static function withdraw( $application_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ff_applications';
		$app   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $application_id )
		);

		if ( ! $app || empty( $app->user_id ) ) {
			return new WP_Error( 'ff_no_member', __( 'That application has no member to withdraw.', 'founding-faces' ) );
		}

		// Retire the number if they had one.
		if ( ! empty( $app->assigned_number ) ) {
			self::retire_number( (int) $app->assigned_number );
		}

		// Deactivate rather than delete: flag the account so login is blocked.
		update_user_meta( (int) $app->user_id, self::META_DEACTIVATED, 1 );

		// Mark the application withdrawn.
		$wpdb->update(
			$table,
			array( 'status' => 'withdrawn' ),
			array( 'id' => (int) $app->id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Stop a deactivated member from logging in.
	 *
	 * Runs on WordPress's authentication filter. If the user carries the
	 * deactivated flag, login is refused with a clear message.
	 *
	 * @param null|WP_User|WP_Error $user     The user or error so far.
	 * @param string                $username The submitted username.
	 * @param string                $password The submitted password.
	 * @return null|WP_User|WP_Error
	 */
	public static function block_deactivated_login( $user, $username, $password ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, self::META_DEACTIVATED, true ) ) {
			return new WP_Error( 'ff_deactivated', __( 'This account is no longer active.', 'founding-faces' ) );
		}
		return $user;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Welcome email.
	 * Interim implementation: a plain group-specific email so the resend button
	 * is testable now. Stage 4 replaces the body of this method with editable
	 * templates and a secure one-time set-password link.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Send the group-specific welcome email to a member.
	 *
	 * @param int $user_id The member's WordPress user id.
	 * @return bool Whether the email was handed to wp_mail successfully.
	 */
	public static function send_welcome_email( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$number = get_user_meta( $user_id, self::META_NUMBER, true );
		$blog   = get_bloginfo( 'name' );

		if ( $number ) {
			/* translators: %d is the member's Founding number. */
			$subject = __( 'Welcome — you are one of The 35', 'founding-faces' );
			$body    = sprintf(
				/* translators: 1: member number, 2: site name. */
				__( "Welcome to Founding Faces.\n\nYou are Founding Face %1\$d.\n\nWe'll send your secure sign-in link shortly so you can set your password and see inside %2\$s.", 'founding-faces' ),
				(int) $number,
				$blog
			);
		} else {
			$subject = __( 'Welcome to the Apotheca community', 'founding-faces' );
			$body    = sprintf(
				/* translators: %s is the site name. */
				__( "Welcome to Founding Faces.\n\nYou're now part of the Apotheca community. We'll send your secure sign-in link shortly so you can set your password and see inside %s.", 'founding-faces' ),
				$blog
			);
		}

		// Let a later stage take over delivery entirely if it wants to.
		if ( has_action( 'ff_send_welcome_email' ) ) {
			do_action( 'ff_send_welcome_email', $user_id );
			return true;
		}

		return wp_mail( $user->user_email, $subject, $body );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Moderation request handler.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Process a moderation button press from the admin queue.
	 *
	 * Checks the current user is allowed, verifies the nonce, runs the chosen
	 * action, then redirects back to the queue with a short status message.
	 *
	 * @return void
	 */
	public static function handle_moderation() {
		// Only administrators moderate applications.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'founding-faces' ) );
		}

		$app_id = isset( $_POST['ff_app_id'] ) ? absint( wp_unslash( $_POST['ff_app_id'] ) ) : 0;
		$sub    = isset( $_POST['ff_sub'] ) ? sanitize_key( wp_unslash( $_POST['ff_sub'] ) ) : '';

		// Verify the nonce tied to this specific application and action.
		check_admin_referer( self::MODERATE_ACTION . '_' . $app_id );

		$msg = 'error';

		switch ( $sub ) {
			case 'approve_35':
				$result = self::approve( $app_id, 'the-35' );
				$msg    = is_wp_error( $result ) ? $result->get_error_code() : 'approved_35';
				break;

			case 'approve_circle':
				$result = self::approve( $app_id, 'the-circle' );
				$msg    = is_wp_error( $result ) ? $result->get_error_code() : 'approved_circle';
				break;

			case 'decline':
				$result = self::decline( $app_id );
				$msg    = is_wp_error( $result ) ? $result->get_error_code() : 'declined';
				break;

			case 'withdraw':
				$result = self::withdraw( $app_id );
				$msg    = is_wp_error( $result ) ? $result->get_error_code() : 'withdrawn';
				break;

			case 'resend':
				$result = self::resend_welcome( $app_id );
				$msg    = is_wp_error( $result ) ? $result->get_error_code() : 'resent';
				break;
		}

		// Back to the queue with a message code.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'founding-faces',
					'ff_msg' => $msg,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Resend the welcome email to the member behind an application.
	 *
	 * @param int $application_id The ff_applications row id.
	 * @return true|WP_Error
	 */
	public static function resend_welcome( $application_id ) {
		global $wpdb;

		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}ff_applications WHERE id = %d",
				$application_id
			)
		);

		if ( ! $user_id ) {
			return new WP_Error( 'ff_no_member', __( 'That application has no member to email.', 'founding-faces' ) );
		}

		self::send_welcome_email( (int) $user_id );
		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Small helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Pull a first name out of a full name, for The Circle's public identity.
	 *
	 * @param string $full_name The applicant's full name.
	 * @return string The first word, or the whole string if there's no space.
	 */
	private static function first_name_from( $full_name ) {
		$full_name = trim( (string) $full_name );
		if ( '' === $full_name ) {
			return __( 'Member', 'founding-faces' );
		}
		$parts = preg_split( '/\s+/', $full_name );
		return $parts[0];
	}

	/**
	 * Build a unique WordPress username from an email address.
	 *
	 * Uses the part before the @, cleaned to a safe username, then appends a
	 * number if that login is already taken.
	 *
	 * @param string $email The applicant's email.
	 * @return string A username that isn't yet in use.
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'member';
		}

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$suffix++;
			$username = $base . $suffix;
		}
		return $username;
	}
}
