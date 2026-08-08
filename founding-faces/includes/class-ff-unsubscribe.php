<?php
/**
 * The unsubscribe link at the foot of every member email.
 *
 * A member who wants out has to be able to get out from the email itself, in
 * one click, without signing in. So the link carries the member id and a hash
 * that only this site can produce. It is not a one-time token: an email sits in
 * an inbox for years, and a link that quietly stops working is worse than no
 * link at all.
 *
 * Unsubscribing switches off the stored consent flag, which is the same flag
 * the account page and the connector already read, and writes the unsubscribe
 * back to the email platform rather than only recording it here. It never
 * deactivates the account and never touches the member's number or their
 * place: leaving the mailing list is not leaving the programme.
 *
 * Nick is told, by email, every time. That is the one notification the
 * programme has, and it exists because a member of The 35 going quiet is
 * something he would want to follow up in person.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Unsubscribe
 */
class FF_Unsubscribe {

	// The wp-login.php action that handles and confirms an unsubscribe.
	const ACTION = 'ff_unsub';

	/**
	 * Wire up the unsubscribe screen.
	 *
	 * Registered on every load, not just the admin area: the whole point is
	 * that it works for someone who is not signed in.
	 */
	public static function register() {
		add_action( 'login_form_' . self::ACTION, array( __CLASS__, 'screen' ) );
	}

	/**
	 * The hash that proves a link came from us.
	 *
	 * Built from the member id and their email address with the site's own
	 * salts, so it cannot be guessed from the outside, and it changes if the
	 * address changes, which retires the old links with it.
	 *
	 * @param int $user_id The member's user id.
	 * @return string
	 */
	public static function token( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		return wp_hash( 'ff_unsubscribe|' . (int) $user_id . '|' . $user->user_email );
	}

	/**
	 * The unsubscribe URL for a member.
	 *
	 * @param int $user_id The member's user id.
	 * @return string An empty string if there is no such member.
	 */
	public static function url( $user_id ) {
		$token = self::token( $user_id );
		if ( '' === $token ) {
			return '';
		}

		return add_query_arg(
			array(
				'action' => self::ACTION,
				'uid'    => (int) $user_id,
				'token'  => $token,
			),
			wp_login_url()
		);
	}

	/**
	 * Handle the click and confirm it.
	 *
	 * One click is the whole interaction. There is no "are you sure" step,
	 * because a confirmation screen that still sends email is not an
	 * unsubscribe, and because the link is only ever in the member's own inbox.
	 *
	 * @return void
	 */
	public static function screen() {
		$user_id = isset( $_GET['uid'] ) ? absint( wp_unslash( $_GET['uid'] ) ) : 0;   // phpcs:ignore WordPress.Security.NonceVerification
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$expected = self::token( $user_id );
		$valid    = ( '' !== $expected && '' !== $token && hash_equals( $expected, $token ) );

		if ( $valid ) {
			self::apply( $user_id, 'link' );
		}

		login_header( __( 'Email preferences', 'founding-faces' ) );

		if ( $valid ) {
			echo '<p class="message">' . esc_html__( 'Done. You will not receive any more programme emails.', 'founding-faces' ) . '</p>';
			echo '<p>' . esc_html__( 'Your place, your number and everything you have taken part in are untouched. If you change your mind, you can turn emails back on from your account page at any time.', 'founding-faces' ) . '</p>';
		} else {
			echo '<p class="message">' . esc_html__( 'That link is not valid. It may have been sent to a different address, or the address on the account has since changed.', 'founding-faces' ) . '</p>';
			echo '<p>' . esc_html__( 'You can change your email preferences from your account page after signing in.', 'founding-faces' ) . '</p>';
		}

		echo '<p><a href="' . esc_url( FF_Menu_Items::login_url() ) . '">' . esc_html__( 'Sign in', 'founding-faces' ) . '</a></p>';

		login_footer();
		exit;
	}

	/**
	 * Switch a member's consent off, wherever the decision came from.
	 *
	 * There are two doors out and they have to lead to the same place. A member
	 * who clicks the link in one of our emails, and a member who clicks
	 * Campaign Monitor's own unsubscribe at the foot of a campaign, have both
	 * said the same thing, and the site has to stop emailing either of them.
	 *
	 * @param int    $user_id The member's user id.
	 * @param string $source  'link' for our own link, 'platform' when the email
	 *                        platform is telling us it already happened.
	 * @return bool Whether anything changed.
	 */
	public static function apply( $user_id, $source = 'link' ) {
		// Already off: don't log or notify twice. Mail clients pre-fetch links,
		// so this runs more often than it is clicked.
		if ( ! get_user_meta( $user_id, FF_Members::META_CONSENT, true ) ) {
			return false;
		}

		update_user_meta( $user_id, FF_Members::META_CONSENT, 0 );

		// Push it back to the platform, unless the platform is where it came
		// from. Telling Campaign Monitor about an unsubscribe Campaign Monitor
		// told us about is at best a wasted call and at worst a loop.
		if ( 'platform' !== $source ) {
			FF_Connectors::unsubscribe_member( $user_id );
		}

		FF_Interactions::log( $user_id, 'unsubscribed' );

		self::notify_admin( $user_id, $source );

		return true;
	}

	/**
	 * Tell Nick that a member has unsubscribed.
	 *
	 * The group is stated plainly, because one of The 35 leaving the list is a
	 * conversation to have and a Circle member leaving usually isn't.
	 *
	 * @param int    $user_id The member's user id.
	 * @param string $source  Where the decision was made.
	 * @return void
	 */
	private static function notify_admin( $user_id, $source = 'link' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$number = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$real   = get_user_meta( $user_id, FF_Members::META_REAL_NAME, true );
		$name   = '' !== trim( (string) $real ) ? $real : $user->display_name;
		$is_35  = ( '' !== $number );

		$who = $is_35
			/* translators: 1: member name, 2: founding number. */
			? sprintf( __( '%1$s, Founding Face %2$d, one of The 35', 'founding-faces' ), $name, (int) $number )
			/* translators: %s: member name. */
			: sprintf( __( '%s, The Circle', 'founding-faces' ), $name );

		$subject = $is_35
			/* translators: %s: founding number. */
			? sprintf( __( 'One of The 35 has unsubscribed: Founding Face %s', 'founding-faces' ), (int) $number )
			: __( 'A member has unsubscribed', 'founding-faces' );

		$lines = array(
			$who,
			$user->user_email,
			'',
			'platform' === $source
				? __( 'They unsubscribed from your email platform, and the site has been told, so it will not email them either. Their account, number and history are untouched.', 'founding-faces' )
				: __( 'They have been taken off the programme mailing list. Their account, number and history are untouched.', 'founding-faces' ),
		);

		if ( $is_35 ) {
			$lines[] = '';
			$lines[] = __( 'This is one of The 35, so it may be worth a personal note rather than leaving it there.', 'founding-faces' );
		}

		$html = FF_Email_Template::build( array(
			'heading'   => __( 'A member has unsubscribed', 'founding-faces' ),
			'body_html' => wpautop( esc_html( implode( "\n", $lines ) ) ),
			'preheader' => $who,
		) );

		wp_mail( get_option( 'admin_email' ), $subject, $html, FF_Emails::headers( true ) );
	}
}
