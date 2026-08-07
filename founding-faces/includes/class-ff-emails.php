<?php
/**
 * Transactional welcome emails and secure member access.
 *
 * On approval a member is sent a group-specific welcome email built from an
 * editable template. The email carries a one-time set-password link, valid for
 * seven days, so the member sets their own password, we never email a
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

	// Promotion email: sent when a Circle member is chosen for The 35.
	const OPT_PROMO_SUBJECT  = 'ff_email_promo_subject';
	const OPT_PROMO_BODY     = 'ff_email_promo_body';

	// Application-received email: the acknowledgement sent the moment someone
	// applies (only when applications are held for manual review).
	const OPT_RECEIVED_SUBJECT = 'ff_email_received_subject';
	const OPT_RECEIVED_BODY    = 'ff_email_received_body';

	// The email sent when an application is declined, so a decision is never
	// silence, and so the status lookup's "check your inbox" is true for
	// everyone, not just the members who were approved.
	const OPT_DECLINE_SUBJECT = 'ff_email_decline_subject';
	const OPT_DECLINE_BODY    = 'ff_email_decline_body';

	// User meta holding the hashed set-password token and its expiry.
	const META_TOKEN_HASH = 'ff_setpw_hash';
	const META_TOKEN_EXP  = 'ff_setpw_expires';

	// How long a set-password link stays valid.
	const TOKEN_LIFETIME = 7 * DAY_IN_SECONDS;

	// Who the programme's emails come from, and where a reply goes.
	const OPT_FROM_NAME   = 'ff_email_from_name';
	const OPT_FROM_EMAIL  = 'ff_email_from_email';
	const OPT_REPLY_NAME  = 'ff_email_reply_name';
	const OPT_REPLY_EMAIL = 'ff_email_reply_email';
	const OPT_BCC         = 'ff_email_bcc';

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
			? __( 'Welcome, you are Founding Face {number}', 'founding-faces' )
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
				"Hi {name},\n\nWelcome to Founding Faces. You are Founding Face {number}, one of The 35.\n\nUse the button below to set your password and step inside. The link is valid for 7 days; if it ever expires you can request a fresh one from the login page.\n\nWith thanks,\n{site_name}",
				'founding-faces'
			);
		}
		return __(
			"Hi {name},\n\nWelcome to Founding Faces and the Apotheca community.\n\nUse the button below to set your password and step inside. The link is valid for 7 days; if it ever expires you can request a fresh one from the login page.\n\nWith thanks,\n{site_name}",
			'founding-faces'
		);
	}

	/**
	 * The default subject for the promotion (chosen for The 35) email.
	 *
	 * @return string
	 */
	public static function default_promo_subject() {
		return __( 'Congratulations, you are one of The 35', 'founding-faces' );
	}

	/**
	 * The default body for the promotion email.
	 *
	 * The member already has a password (they set it as a Circle member), so this
	 * email has no set-password link, just the news and a sign-in button.
	 *
	 * @return string
	 */
	public static function default_promo_body() {
		return __(
			"Hi {name},\n\nWe have some special news. You have been chosen as one of The 35, the inner circle of Founding Faces.\n\nYou are now Founding Face {number}. Your place, your number, and everything you've taken part in are yours alone.\n\nSign in any time to see inside.\n\nWith thanks,\n{site_name}",
			'founding-faces'
		);
	}

	/**
	 * The default subject for the application-received email.
	 *
	 * @return string
	 */
	public static function default_received_subject() {
		return __( 'We\'ve received your application', 'founding-faces' );
	}

	/**
	 * The default body for the application-received email.
	 *
	 * @return string
	 */
	public static function default_received_body() {
		return __(
			"Hi {name},\n\nThank you for applying to join Founding Faces. Your application has been received and is now being reviewed.\n\nWe'll be in touch by email as soon as there's news.\n\nWith thanks,\n{site_name}",
			'founding-faces'
		);
	}

	/**
	 * The default subject for the decline email.
	 *
	 * @return string
	 */
	public static function default_decline_subject() {
		return __( 'About your Founding Faces application', 'founding-faces' );
	}

	/**
	 * The default body for the decline email.
	 *
	 * Warm and final, with no reason given and no invitation to appeal, the
	 * programme is a small, chosen group, and a kind close is better than an
	 * unanswered silence.
	 *
	 * @return string
	 */
	public static function default_decline_body() {
		return __(
			"Hi {name},\n\nThank you for applying to Founding Faces, and for the time you took over it.\n\nWe had far more applications than places, and on this occasion we haven't been able to offer you one. That isn't a reflection on you, the group is deliberately small, and the choices were genuinely hard.\n\nWe'd love to stay in touch, and you're very welcome to apply again if we open more places.\n\nWith thanks and warm wishes,\n{site_name}",
			'founding-faces'
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Composing.
	 * Every email is described once, here, and built by one method. The senders
	 * below and the preview screen both go through it, so what Nick sees on the
	 * preview screen is the message itself rather than a second rendering of it
	 * that can quietly drift out of step.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Every email the plugin sends from a template, and what is fixed about it.
	 *
	 * A kind's button URL is named rather than given: the value is taken from
	 * the replacements at composing time, so the button in a preview points
	 * wherever that preview's link points and the button in a real email points
	 * at the real one.
	 *
	 * @return array Map of kind => spec.
	 */
	public static function kinds() {
		return array(
			'welcome_35'   => array(
				'label'           => __( 'Welcome: The 35', 'founding-faces' ),
				'when'            => __( 'Sent when you approve an application into The 35, and whenever you resend the set-up link.', 'founding-faces' ),
				'subject_option'  => self::OPT_35_SUBJECT,
				'subject_default' => self::default_subject( true ),
				'body_option'     => self::OPT_35_BODY,
				'body_default'    => self::default_body( true ),
				'heading'         => __( 'Welcome to The 35', 'founding-faces' ),
				'preheader'       => __( 'Set your password to step inside.', 'founding-faces' ),
				'cta_label'       => __( 'Set your password', 'founding-faces' ),
				'cta_url'         => '{set_password_link}',
				'unsubscribe'     => true,
			),
			'welcome_circle' => array(
				'label'           => __( 'Welcome: The Circle', 'founding-faces' ),
				'when'            => __( 'Sent when you approve an application into The Circle, and whenever you resend the set-up link. Also the email an applicant gets when auto-accept is on.', 'founding-faces' ),
				'subject_option'  => self::OPT_CIRCLE_SUBJECT,
				'subject_default' => self::default_subject( false ),
				'body_option'     => self::OPT_CIRCLE_BODY,
				'body_default'    => self::default_body( false ),
				'heading'         => __( 'Welcome to Founding Faces', 'founding-faces' ),
				'preheader'       => __( 'Set your password to step inside.', 'founding-faces' ),
				'cta_label'       => __( 'Set your password', 'founding-faces' ),
				'cta_url'         => '{set_password_link}',
				'unsubscribe'     => true,
			),
			'promotion'    => array(
				'label'           => __( 'Chosen for The 35 (promotion)', 'founding-faces' ),
				'when'            => __( 'Sent when you promote a Circle member into The 35. They already have a password, so this carries a sign-in button.', 'founding-faces' ),
				'subject_option'  => self::OPT_PROMO_SUBJECT,
				'subject_default' => self::default_promo_subject(),
				'body_option'     => self::OPT_PROMO_BODY,
				'body_default'    => self::default_promo_body(),
				'heading'         => __( 'You are one of The 35', 'founding-faces' ),
				'preheader'       => __( 'You have been chosen for The 35.', 'founding-faces' ),
				'cta_label'       => __( 'Sign in', 'founding-faces' ),
				'cta_url'         => '{login_url}',
				'unsubscribe'     => true,
			),
			'received'     => array(
				'label'           => __( 'Application received', 'founding-faces' ),
				'when'            => __( 'Sent the moment an application arrives, while applications are held for manual review.', 'founding-faces' ),
				'subject_option'  => self::OPT_RECEIVED_SUBJECT,
				'subject_default' => self::default_received_subject(),
				'body_option'     => self::OPT_RECEIVED_BODY,
				'body_default'    => self::default_received_body(),
				'heading'         => __( 'Application received', 'founding-faces' ),
				'preheader'       => __( 'Thanks for applying to Founding Faces.', 'founding-faces' ),
			),
			'decline'      => array(
				'label'           => __( 'Application declined', 'founding-faces' ),
				'when'            => __( 'Sent when you decline an application. Clearing the body on the Settings page turns it off entirely.', 'founding-faces' ),
				'subject_option'  => self::OPT_DECLINE_SUBJECT,
				'subject_default' => self::default_decline_subject(),
				'body_option'     => self::OPT_DECLINE_BODY,
				'body_default'    => self::default_decline_body(),
				'heading'         => __( 'Your application', 'founding-faces' ),
				'preheader'       => __( 'An update on your Founding Faces application.', 'founding-faces' ),
				'silent_if_empty' => true,
			),
			'reset'        => array(
				'label'           => __( 'Password reset link', 'founding-faces' ),
				'when'            => __( 'Sent when a member asks for a new password from their account page. This one is not editable, it is deliberately plain.', 'founding-faces' ),
				'subject_option'  => '',
				'subject_default' => __( 'Reset your Founding Faces password', 'founding-faces' ),
				'body_option'     => '',
				'body_default'    => __( "Hi {name},\n\nUse the button below to set a new password. The link is valid for 7 days.\n\nIf you didn't ask for this, you can safely ignore this email.\n\n{site_name}", 'founding-faces' ),
				'heading'         => __( 'Reset your password', 'founding-faces' ),
				'preheader'       => __( 'Set a new Founding Faces password.', 'founding-faces' ),
				'cta_label'       => __( 'Set a new password', 'founding-faces' ),
				'cta_url'         => '{set_password_link}',
			),
		);
	}

	/**
	 * Build one email: its subject line and its complete HTML.
	 *
	 * @param string $kind         A key from kinds().
	 * @param array  $replacements Map of {placeholder} => value.
	 * @return array|false ['subject' => string, 'html' => string], or false if
	 *                     the kind is unknown or has been emptied on purpose.
	 */
	public static function compose( $kind, $replacements ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			return false;
		}
		$spec = $kinds[ $kind ];

		$body_tpl = '' !== $spec['body_option']
			? get_option( $spec['body_option'], $spec['body_default'] )
			: $spec['body_default'];

		// An emptied body is how the decline email is turned off without code.
		if ( ! empty( $spec['silent_if_empty'] ) && '' === trim( (string) $body_tpl ) ) {
			return false;
		}

		$subject_tpl = '' !== $spec['subject_option']
			? get_option( $spec['subject_option'], $spec['subject_default'] )
			: $spec['subject_default'];

		// The subject line and the heading are deliberately different sentences.
		// A subject has to earn a click from a crowded inbox; a heading is read
		// by someone who has already opened the email and only needs telling
		// where they have arrived.
		$subject = self::fill( $subject_tpl, $replacements, false );

		$args = array(
			'heading'   => $spec['heading'],
			'body_html' => self::fill( $body_tpl, $replacements, true ),
			'preheader' => $spec['preheader'],
		);

		// The button only appears when the kind has one and the link exists.
		if ( ! empty( $spec['cta_label'] ) && ! empty( $replacements[ $spec['cta_url'] ] ) ) {
			$args['cta'] = array(
				'label' => $spec['cta_label'],
				'url'   => $replacements[ $spec['cta_url'] ],
			);
		}

		// The way out, on the emails that are a mailing list rather than a
		// response to something the member just did. A password reset carries
		// no unsubscribe: it is asked for, and it has to arrive.
		if ( ! empty( $spec['unsubscribe'] ) && ! empty( $replacements['{unsubscribe_url}'] ) ) {
			$args['unsubscribe'] = $replacements['{unsubscribe_url}'];
		}

		return array(
			'subject' => $subject,
			'html'    => FF_Email_Template::build( $args ),
		);
	}

	/**
	 * Whether a member may be sent an email that isn't essential.
	 *
	 * The line is drawn at whether the email is needed to use the account. A
	 * set-password link and a password reset are asked for and have to arrive,
	 * or somebody who has unsubscribed is also locked out, which is not what
	 * they asked for. A reply to a message they sent is a reply. Everything
	 * else, the news and the announcements, waits on consent.
	 *
	 * @param int $user_id The member's user id.
	 * @return bool
	 */
	public static function may_email( $user_id ) {
		$consent = (bool) get_user_meta( $user_id, FF_Members::META_CONSENT, true );

		return (bool) apply_filters( 'ff_may_email_member', $consent, $user_id );
	}

	/**
	 * The headers every programme email is sent with.
	 *
	 * Who it comes from, where a reply goes, and that it is HTML. One place,
	 * because an email that arrives from a different name than the last one did
	 * is an email that looks like it came from somebody else.
	 *
	 * A word of warning that belongs next to the setting as much as here: the
	 * from address has to be one the site is allowed to send as. Borrowing a
	 * Gmail or an Outlook address will fail that domain's own checks and land
	 * the email in spam, whatever else is right about it.
	 *
	 * @param bool $to_admin Whether this one is going to Nick rather than a
	 *                       member, in which case the blind copy is pointless.
	 * @return array
	 */
	public static function headers( $to_admin = false ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$from_email = trim( (string) get_option( self::OPT_FROM_EMAIL, '' ) );
		$from_name  = trim( (string) get_option( self::OPT_FROM_NAME, '' ) );

		if ( is_email( $from_email ) ) {
			$headers[] = '' !== $from_name
				? 'From: ' . self::quoted( $from_name ) . ' <' . $from_email . '>'
				: 'From: ' . $from_email;
		}

		// A reply-to worth setting is one that goes somewhere a person reads.
		$reply_email = trim( (string) get_option( self::OPT_REPLY_EMAIL, '' ) );
		$reply_name  = trim( (string) get_option( self::OPT_REPLY_NAME, '' ) );

		if ( is_email( $reply_email ) ) {
			$headers[] = '' !== $reply_name
				? 'Reply-To: ' . self::quoted( $reply_name ) . ' <' . $reply_email . '>'
				: 'Reply-To: ' . $reply_email;
		}

		$bcc = trim( (string) get_option( self::OPT_BCC, '' ) );
		if ( ! $to_admin && is_email( $bcc ) ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		return apply_filters( 'ff_email_headers', $headers, $to_admin );
	}

	/**
	 * Wrap a display name so a comma in it can't split the header.
	 *
	 * @param string $name The name as typed.
	 * @return string
	 */
	private static function quoted( $name ) {
		return '"' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"';
	}

	/**
	 * Send a composed email as HTML, so its links are clickable.
	 *
	 * @param string $to      The recipient address.
	 * @param array  $message The result of compose().
	 * @return bool Whether wp_mail accepted it.
	 */
	private static function deliver( $to, $message ) {
		if ( ! $message || ! is_email( $to ) ) {
			return false;
		}
		return wp_mail( $to, $message['subject'], $message['html'], self::headers() );
	}

	/**
	 * The placeholder values for a member, for both sending and previewing.
	 *
	 * @param int    $user_id The member's user id.
	 * @param string $link    The set-password link, if the email carries one.
	 * @return array
	 */
	public static function member_replacements( $user_id, $link = '' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$number    = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$real_name = get_user_meta( $user_id, FF_Members::META_REAL_NAME, true );

		return array(
			'{name}'              => trim( (string) $real_name ) !== ''
				? preg_split( '/\s+/', trim( $real_name ) )[0]
				: $user->display_name,
			'{number}'            => '' !== $number ? (int) $number : '',
			'{group}'             => '' !== $number ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' ),
			'{public_name}'       => get_user_meta( $user_id, FF_Members::META_PUBLIC_NAME, true ),
			'{site_name}'         => get_bloginfo( 'name' ),
			'{login_url}'         => wp_login_url(),
			'{set_password_link}' => $link,
			'{unsubscribe_url}'   => FF_Unsubscribe::url( $user_id ),
		);
	}

	/**
	 * The placeholder values for an applicant, who has no account yet.
	 *
	 * @param string $name The applicant's name as given on the form.
	 * @return array
	 */
	public static function applicant_replacements( $name ) {
		return array(
			'{name}'      => trim( (string) $name ) !== ''
				? preg_split( '/\s+/', trim( $name ) )[0]
				: __( 'there', 'founding-faces' ),
			'{site_name}' => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Send the decline email to an applicant.
	 *
	 * Sent to the address on the application, so no account is needed, a
	 * declined applicant never has a WordPress user. Silent by design if the
	 * template body has been emptied on the Settings page: that is how Nick
	 * turns decline emails off without code.
	 *
	 * @param string $name  The applicant's name.
	 * @param string $email The applicant's email.
	 * @return bool
	 */
	public static function send_decline( $name, $email ) {
		// compose() returns false when the body has been emptied on purpose,
		// which is how a silent decline is arranged.
		return self::deliver( $email, self::compose( 'decline', self::applicant_replacements( $name ) ) );
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

		return self::deliver(
			$user->user_email,
			self::compose(
				$is_35 ? 'welcome_35' : 'welcome_circle',
				self::member_replacements( $user_id, $link )
			)
		);
	}

	/**
	 * Send the "you've been chosen for The 35" email to a promoted member.
	 *
	 * Fired by FF_Members::promote_to_35(). The member already has a password, so
	 * this carries a sign-in button rather than a set-password link.
	 *
	 * @param int $user_id The member's WordPress user id.
	 * @return bool
	 */
	public static function send_promotion( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// News rather than necessity, so consent decides. Someone who has
		// unsubscribed, here or at the platform, does not get told.
		if ( ! self::may_email( $user_id ) ) {
			return false;
		}

		return self::deliver(
			$user->user_email,
			self::compose( 'promotion', self::member_replacements( $user_id ) )
		);
	}

	/**
	 * Send the application-received acknowledgement to a new applicant.
	 *
	 * Sent from the application handler when applications are held for manual
	 * review. When auto-accept is on, the member gets the welcome email instead,
	 * so this is not sent (no duplicate).
	 *
	 * @param string $name  The applicant's name.
	 * @param string $email The applicant's email.
	 * @return bool
	 */
	public static function send_application_received( $name, $email ) {
		return self::deliver( $email, self::compose( 'received', self::applicant_replacements( $name ) ) );
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
		if ( ! $as_html ) {
			// A subject line can't hold a link, so a URL there stays a URL.
			return strtr( $template, $replacements );
		}

		$labels  = self::link_labels();
		$anchors = array();
		$values  = array();

		$template = self::pull_up_link_lines( $template, array_keys( $labels ) );

		foreach ( $replacements as $key => $value ) {
			// A link placeholder becomes a linked phrase, not a pasted address.
			// Nobody wants sixty characters of query string in the middle of a
			// sentence, and a wrapped URL looks broken even when it works.
			if ( isset( $labels[ $key ] ) && '' !== (string) $value ) {
				$token             = '{{ff-link-' . count( $anchors ) . '}}';
				$anchors[ $token ] = '<a href="' . esc_url( $value ) . '">' . esc_html( $labels[ $key ] ) . '</a>';
				$values[ $key ]    = $token;
				continue;
			}

			// Escape each dynamic value so a stray character can't break the HTML.
			$values[ $key ] = esc_html( $value );
		}

		// Line breaks become paragraphs, and any URL the author typed themselves
		// still becomes a link. The tokens are put back last so neither step can
		// interfere with the anchors.
		$filled = wpautop( make_clickable( strtr( $template, $values ) ) );

		return strtr( $filled, $anchors );
	}

	/**
	 * Bring a link that sits alone on the next line up onto the sentence above.
	 *
	 * The templates were written when the placeholder produced a bare URL, and
	 * a URL on its own line is the only sensible way to set one out. Now that
	 * it produces a few linked words, the same line break leaves the link
	 * stranded under the sentence that introduces it.
	 *
	 * Only a single line break is closed up. A deliberate blank line before the
	 * placeholder makes it a paragraph of its own, and that is left alone.
	 *
	 * @param string $template     The template text.
	 * @param array  $placeholders The link placeholders to look for.
	 * @return string
	 */
	private static function pull_up_link_lines( $template, $placeholders ) {
		$alternatives = array();
		foreach ( $placeholders as $placeholder ) {
			$alternatives[] = preg_quote( $placeholder, '/' );
		}
		if ( empty( $alternatives ) ) {
			return $template;
		}

		$pattern = '/([^\n])[ \t]*\n[ \t]*(' . implode( '|', $alternatives ) . ')/u';

		return preg_replace( $pattern, '$1 $2', (string) $template );
	}

	/**
	 * The words each link placeholder is written as in an email body.
	 *
	 * Filterable, so the wording can be changed without editing every template.
	 *
	 * @return array Map of placeholder => link text.
	 */
	public static function link_labels() {
		return apply_filters( 'ff_email_link_labels', array(
			'{set_password_link}' => __( 'set your password', 'founding-faces' ),
			'{login_url}'         => __( 'the login page', 'founding-faces' ),
			'{unsubscribe_url}'   => __( 'unsubscribe', 'founding-faces' ),
		) );
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

		return self::deliver(
			$user->user_email,
			self::compose( 'reset', self::member_replacements( $user_id, $link ) )
		);
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
