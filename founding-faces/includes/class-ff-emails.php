<?php
/**
 * Transactional welcome emails and secure member access.
 *
 * On approval a member is sent a group-specific welcome email built from an
 * editable template. The email carries a one-time set-password link, valid for
 * seven days, so the member sets their own password — we never email a
 * plain-text one. The set-password screen and a "resend my set-up link" option
 * both live on the standard WordPress login page, so they inherit its look and
 * need no page to be created.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Emails
 *
 * Owns the welcome email templates, the token mechanism, and the login-page
 * screens for setting a password and requesting a fresh link. The token
 * methods are written to be reused by the account page's password reset in a
 * later stage.
 */
class FF_Emails {

	// Option keys for the editable email templates (used by the settings page).
	const OPT_35_SUBJECT     = 'ff_email_35_subject';
	const OPT_35_BODY        = 'ff_email_35_body';
	const OPT_CIRCLE_SUBJECT = 'ff_email_circle_subject';
	const OPT_CIRCLE_BODY    = 'ff_email_circle_body';

	// User meta holding the hashed set-password token and its expiry.
	const META_TOKEN_HASH = 'ff_setpw_hash';
	const META_TOKEN_EXP  = 'ff_setpw_expires';

	// How long a set-password link stays valid.
	const TOKEN_LIFETIME = 7 * DAY_IN_SECONDS;

	// The wp-login.php action names for our two custom screens.
	const ACTION_SETPW  = 'ff_setpw';
	const ACTION_RESEND = 'ff_resend';

	/**
	 * Wire up the welcome-email sender and the login-page screens.
	 *
	 * Registered on every load (the login page is not the admin area), so the
	 * set-password and resend screens work for logged-out members.
	 */
	public static function register() {
		// Take over welcome-email delivery from the interim sender in Stage 3.
		add_action( 'ff_send_welcome_email', array( __CLASS__, 'send_welcome' ) );

		// Our two custom login-page screens.
		add_action( 'login_form_' . self::ACTION_SETPW, array( __CLASS__, 'screen_set_password' ) );
		add_action( 'login_form_' . self::ACTION_RESEND, array( __CLASS__, 'screen_resend_link' ) );

		// A gentle prompt above the normal login form pointing first-timers to
		// the resend screen, so an expired link is never a dead end.
		add_filter( 'login_message', array( __CLASS__, 'login_prompt' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Template defaults.
	 * These ship as sensible starting points; Nick edits them in Settings.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The default subject line for a group.
	 *
	 * @param bool $is_35 Whether this is the The 35 template.
	 * @return string
	 */
	public static function default_subject( $is_35 ) {
		return $is_35
			? __( 'Welcome — you are Founding Face {number}', 'founding-faces' )
			: __( 'Welcome to the Apotheca community', 'founding-faces' );
	}

	/**
	 * The default body for a group.
	 *
	 * @param bool $is_35 Whether this is the The 35 template.
	 * @return string
	 */
	public static function default_body( $is_35 ) {
		if ( $is_35 ) {
			return __(
				"Hi {name},\n\nWelcome to Founding Faces. You are Founding Face {number} — one of The 35.\n\nTo set your password and step inside, use this secure link (valid for 7 days):\n{set_password_link}\n\nIf your link ever expires, you can request a fresh one from the login page:\n{login_url}\n\nWith thanks,\n{site_name}",
				'founding-faces'
			);
		}
		return __(
			"Hi {name},\n\nWelcome to Founding Faces and the Apotheca community.\n\nTo set your password and step inside, use this secure link (valid for 7 days):\n{set_password_link}\n\nIf your link ever expires, you can request a fresh one from the login page:\n{login_url}\n\nWith thanks,\n{site_name}",
			'founding-faces'
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Sending the welcome email.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Send the group-specific welcome email to a member.
	 *
	 * Generates a fresh set-password token, builds the secure link, fills the
	 * chosen template's placeholders, and sends it as HTML. Called on approval
	 * and whenever a link is resent.
	 *
	 * @param int $user_id The member's WordPress user id.
	 * @return bool Whether wp_mail accepted the message.
	 */
	public static function send_welcome( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$number = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$is_35  = ! empty( $number );

		// A new one-time token and its secure link.
		$token = self::create_setpw_token( $user_id );
		$link  = self::build_setpw_link( $user_id, $token );

		// Pull the (possibly edited) template for this group.
		$subject_tpl = get_option(
			$is_35 ? self::OPT_35_SUBJECT : self::OPT_CIRCLE_SUBJECT,
			self::default_subject( $is_35 )
		);
		$body_tpl = get_option(
			$is_35 ? self::OPT_35_BODY : self::OPT_CIRCLE_BODY,
			self::default_body( $is_35 )
		);

		// The values every placeholder can be filled with.
		$real_name  = get_user_meta( $user_id, FF_Members::META_REAL_NAME, true );
		$first_name = trim( (string) $real_name ) !== '' ? preg_split( '/\s+/', trim( $real_name ) )[0] : $user->display_name;
		$group      = $is_35 ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' );

		$replacements = array(
			'{name}'              => $first_name,
			'{number}'            => $is_35 ? (int) $number : '',
			'{group}'             => $group,
			'{public_name}'       => get_user_meta( $user_id, FF_Members::META_PUBLIC_NAME, true ),
			'{site_name}'         => get_bloginfo( 'name' ),
			'{login_url}'         => wp_login_url(),
			'{set_password_link}' => $link,
		);

		// Fill subject (plain text) and body (escaped, then made into HTML).
		$subject = self::fill( $subject_tpl, $replacements, false );
		$body    = self::fill( $body_tpl, $replacements, true );

		// Send as HTML so links are clickable.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $user->user_email, $subject, $body, $headers );
	}

	/**
	 * Fill a template's placeholders with their values.
	 *
	 * For the HTML body, dynamic values are escaped, then the whole is turned
	 * into paragraphs with clickable links. The subject is returned as plain
	 * text.
	 *
	 * @param string $template     The template text with {placeholders}.
	 * @param array  $replacements Map of placeholder => value.
	 * @param bool   $as_html      Whether to render the result as HTML.
	 * @return string
	 */
	private static function fill( $template, $replacements, $as_html ) {
		if ( $as_html ) {
			// Escape each dynamic value so a stray character can't break the HTML.
			foreach ( $replacements as $key => $value ) {
				$replacements[ $key ] = ( '{set_password_link}' === $key || '{login_url}' === $key )
					? esc_url( $value )
					: esc_html( $value );
			}
			$filled = strtr( $template, $replacements );
			// Turn line breaks into paragraphs and bare URLs into links.
			return wpautop( make_clickable( $filled ) );
		}

		return strtr( $template, $replacements );
	}

	/**
	 * Send a member a secure password-reset link.
	 *
	 * Uses the same one-time token mechanism as the welcome link, so a member
	 * who asks to change their password from the account page gets a secure
	 * set-password screen rather than a plain-text password.
	 *
	 * @param int $user_id The member's user id.
	 * @return bool
	 */
	public static function send_reset_link( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$token = self::create_setpw_token( $user_id );
		$link  = self::build_setpw_link( $user_id, $token );

		$subject = __( 'Reset your Founding Faces password', 'founding-faces' );
		$body    = wpautop( make_clickable( sprintf(
			/* translators: 1: member first name or display name, 2: secure link, 3: site name. */
			__( "Hi %1\$s,\n\nUse this secure link to set a new password (valid for 7 days):\n%2\$s\n\nIf you didn't ask for this, you can safely ignore this email.\n\n%3\$s", 'founding-faces' ),
			esc_html( $user->display_name ),
			esc_url( $link ),
			esc_html( get_bloginfo( 'name' ) )
		) ) );

		return wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The token mechanism.
	 * A random token is generated, its hash and a 7-day expiry stored against
	 * the user, and the plain token placed only in the emailed link. The token
	 * is single-use: setting a password consumes it.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Create a set-password token for a user and store its hash and expiry.
	 *
	 * @param int $user_id The member's user id.
	 * @return string The plain token (goes into the emailed link only).
	 */
	public static function create_setpw_token( $user_id ) {
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, self::META_TOKEN_HASH, hash( 'sha256', $token ) );
		update_user_meta( $user_id, self::META_TOKEN_EXP, time() + self::TOKEN_LIFETIME );
		return $token;
	}

	/**
	 * Check whether a token is valid and unexpired for a user.
	 *
	 * Uses a timing-safe comparison so the check can't be gamed.
	 *
	 * @param int    $user_id The member's user id.
	 * @param string $token   The token from the link.
	 * @return bool True if the token matches and hasn't expired.
	 */
	public static function validate_setpw_token( $user_id, $token ) {
		$stored  = get_user_meta( $user_id, self::META_TOKEN_HASH, true );
		$expires = (int) get_user_meta( $user_id, self::META_TOKEN_EXP, true );

		if ( ! $stored || '' === $token ) {
			return false;
		}
		if ( time() > $expires ) {
			return false;
		}
		return hash_equals( $stored, hash( 'sha256', $token ) );
	}

	/**
	 * Consume (delete) a user's set-password token so it can't be reused.
	 *
	 * @param int $user_id The member's user id.
	 * @return void
	 */
	public static function consume_setpw_token( $user_id ) {
		delete_user_meta( $user_id, self::META_TOKEN_HASH );
		delete_user_meta( $user_id, self::META_TOKEN_EXP );
	}

	/**
	 * Build the secure set-password link for a user and token.
	 *
	 * @param int    $user_id The member's user id.
	 * @param string $token   The plain token.
	 * @return string The full URL.
	 */
	public static function build_setpw_link( $user_id, $token ) {
		return add_query_arg(
			array(
				'action' => self::ACTION_SETPW,
				'uid'    => (int) $user_id,
				'token'  => rawurlencode( $token ),
			),
			wp_login_url()
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Login-page screens.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The set-password screen (wp-login.php?action=ff_setpw).
	 *
	 * On GET with a valid token, shows a two-field password form. On POST,
	 * validates and saves the new password, consumes the token, and shows a
	 * success screen with a link to log in. An invalid or expired token shows a
	 * clear message pointing at the resend screen.
	 *
	 * @return void
	 */
	public static function screen_set_password() {
		$uid   = isset( $_REQUEST['uid'] ) ? absint( wp_unslash( $_REQUEST['uid'] ) ) : 0;
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		$user  = $uid ? get_userdata( $uid ) : false;
		$valid = ( $user && self::validate_setpw_token( $uid, $token ) );

		$error = '';
		$done  = false;

		// Process a submitted new password.
		if ( $valid && 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			check_admin_referer( 'ff_setpw_' . $uid );

			// Passwords are not sanitised, only unslashed, so every character
			// the member chose is preserved exactly.
			$pass1 = isset( $_POST['ff_pass1'] ) ? wp_unslash( $_POST['ff_pass1'] ) : '';
			$pass2 = isset( $_POST['ff_pass2'] ) ? wp_unslash( $_POST['ff_pass2'] ) : '';

			if ( strlen( $pass1 ) < 8 ) {
				$error = __( 'Please choose a password of at least 8 characters.', 'founding-faces' );
			} elseif ( $pass1 !== $pass2 ) {
				$error = __( 'The two passwords don\'t match.', 'founding-faces' );
			} else {
				// Set the password and burn the token.
				wp_set_password( $pass1, $uid );
				self::consume_setpw_token( $uid );
				$done = true;
			}
		}

		// Render using WordPress's own login chrome.
		login_header( __( 'Set your password', 'founding-faces' ) );

		if ( $done ) {
			echo '<p class="message">' . esc_html__( 'Your password is set. You can now log in.', 'founding-faces' ) . '</p>';
			echo '<p><a class="button button-large" href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Go to login', 'founding-faces' ) . '</a></p>';
		} elseif ( ! $valid ) {
			echo '<p class="message">' . esc_html__( 'This link is invalid or has expired.', 'founding-faces' ) . '</p>';
			$resend = add_query_arg( 'action', self::ACTION_RESEND, wp_login_url() );
			echo '<p><a href="' . esc_url( $resend ) . '">' . esc_html__( 'Request a fresh set-up link', 'founding-faces' ) . '</a></p>';
		} else {
			if ( $error ) {
				echo '<div id="login_error">' . esc_html( $error ) . '</div>';
			}
			$post_url = self::build_setpw_link( $uid, $token );
			?>
			<form name="ff_setpw_form" id="ff_setpw_form" action="<?php echo esc_url( $post_url ); ?>" method="post">
				<p>
					<label for="ff_pass1"><?php esc_html_e( 'New password', 'founding-faces' ); ?><br />
						<input type="password" name="ff_pass1" id="ff_pass1" class="input" size="20" autocomplete="new-password" required /></label>
				</p>
				<p>
					<label for="ff_pass2"><?php esc_html_e( 'Confirm new password', 'founding-faces' ); ?><br />
						<input type="password" name="ff_pass2" id="ff_pass2" class="input" size="20" autocomplete="new-password" required /></label>
				</p>
				<?php wp_nonce_field( 'ff_setpw_' . $uid ); ?>
				<p class="submit">
					<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Set password', 'founding-faces' ); ?>" />
				</p>
			</form>
			<?php
		}

		login_footer();
		exit;
	}

	/**
	 * The resend-link screen (wp-login.php?action=ff_resend).
	 *
	 * Asks for an email address and, if it belongs to an active member, sends a
	 * fresh welcome email with a new set-password link. The confirmation message
	 * is always the same regardless of whether the email was found, so the form
	 * never reveals who is or isn't a member.
	 *
	 * @return void
	 */
	public static function screen_resend_link() {
		$sent = false;

		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			check_admin_referer( 'ff_resend' );

			$email = isset( $_POST['ff_email'] ) ? sanitize_email( wp_unslash( $_POST['ff_email'] ) ) : '';
			if ( $email && is_email( $email ) ) {
				$user = get_user_by( 'email', $email );
				// Only re-send to an actual, still-active member.
				if ( $user
					&& get_user_meta( $user->ID, FF_Members::META_GROUP, true )
					&& ! get_user_meta( $user->ID, FF_Members::META_DEACTIVATED, true ) ) {
					self::send_welcome( $user->ID );
				}
			}
			// Always report success, so the screen can't be used to probe emails.
			$sent = true;
		}

		login_header( __( 'Get your set-up link', 'founding-faces' ) );

		if ( $sent ) {
			echo '<p class="message">' . esc_html__( 'If that email is in our records, a fresh set-up link is on its way. Please check your inbox (including spam).', 'founding-faces' ) . '</p>';
			echo '<p><a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Back to login', 'founding-faces' ) . '</a></p>';
		} else {
			$post_url = add_query_arg( 'action', self::ACTION_RESEND, wp_login_url() );
			?>
			<p><?php esc_html_e( 'Enter the email you applied with and we\'ll send you a fresh link to set your password.', 'founding-faces' ); ?></p>
			<form name="ff_resend_form" id="ff_resend_form" action="<?php echo esc_url( $post_url ); ?>" method="post">
				<p>
					<label for="ff_email"><?php esc_html_e( 'Email address', 'founding-faces' ); ?><br />
						<input type="email" name="ff_email" id="ff_email" class="input" size="20" autocomplete="email" required /></label>
				</p>
				<?php wp_nonce_field( 'ff_resend' ); ?>
				<p class="submit">
					<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Send my link', 'founding-faces' ); ?>" />
				</p>
			</form>
			<?php
		}

		login_footer();
		exit;
	}

	/**
	 * Add a prompt above the standard login form pointing to the resend screen.
	 *
	 * Only shown on the normal login action, so it doesn't clutter the other
	 * screens.
	 *
	 * @param string $message The existing login message HTML.
	 * @return string
	 */
	public static function login_prompt( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( ! in_array( $action, array( 'login', '' ), true ) ) {
			return $message;
		}

		$url      = add_query_arg( 'action', self::ACTION_RESEND, wp_login_url() );
		$message .= '<p class="message">' . sprintf(
			/* translators: %s is the URL of the set-up link screen. */
			wp_kses(
				__( 'First time here, or your link expired? <a href="%s">Get your set-up link</a>.', 'founding-faces' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( $url )
		) . '</p>';

		return $message;
	}
}
