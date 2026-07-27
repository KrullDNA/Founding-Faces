<?php
/**
 * The member account-settings page.
 *
 * Where a member manages their own login and preferences — separate from their
 * content (the home screen) and their record (the history page). Deliberately
 * small: change email (with a confirmation step), change password (a secure
 * token reset), edit their name, toggle email consent (writing back to the
 * email platform, not just locally), and export or delete their own data.
 *
 * Number, group and privacy standing are shown read-only: they are Nick's to
 * control, never the member's to edit.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Account
 *
 * The [ff_account] page and the handlers behind its forms.
 */
class FF_Account {

	// The admin-post action name for the account forms.
	const ACTION = 'ff_account';

	// User meta used during an email-change confirmation.
	const META_PENDING_EMAIL = 'ff_pending_email';
	const META_EMAIL_HASH    = 'ff_email_token_hash';
	const META_EMAIL_EXP     = 'ff_email_token_exp';
	const META_EMAIL_RETURN  = 'ff_email_return';

	/**
	 * Wire up the shortcode, the form handler, and the email-confirm handler.
	 */
	public static function register() {
		add_shortcode( 'ff_account', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_confirm_email' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register the Account Elementor widget.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-account-widget.php';
		$widgets_manager->register( new FF_Account_Widget() );
	}

	/**
	 * A static sample of the account page for the Elementor editor.
	 *
	 * Nick (an administrator) usually isn't a member, so the real page would show
	 * the members-only notice in the editor and leave nothing to style. This
	 * mirrors the real markup and classes with placeholder values, so the whole
	 * page can be designed before there are members. It contains no live data and
	 * no working forms — it exists only for styling.
	 *
	 * @return string
	 */
	public static function sample() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );

		ob_start();
		?>
		<div class="ff-account">
			<h2 class="ff-account-title"><?php esc_html_e( 'Your account', 'founding-faces' ); ?></h2>

			<div class="ff-account-standing">
				<div><span class="ff-account-label"><?php esc_html_e( 'Founding number', 'founding-faces' ); ?></span>
					<strong>7</strong></div>
				<div><span class="ff-account-label"><?php esc_html_e( 'Group', 'founding-faces' ); ?></span>
					<strong><?php esc_html_e( 'The 35', 'founding-faces' ); ?></strong></div>
				<div><span class="ff-account-label"><?php esc_html_e( 'Standing', 'founding-faces' ); ?></span>
					<strong><?php esc_html_e( 'Active member', 'founding-faces' ); ?></strong></div>
				<p class="ff-account-standing-note"><?php esc_html_e( 'Your number, group and standing are set by Apotheca and can\'t be changed here.', 'founding-faces' ); ?></p>
			</div>

			<div class="ff-form ff-account-form">
				<p class="ff-field">
					<label><?php esc_html_e( 'Your name', 'founding-faces' ); ?></label>
					<input type="text" value="Ada Lovelace" />
				</p>
				<p class="ff-field">
					<label><?php esc_html_e( 'How you appear in the portal', 'founding-faces' ); ?></label>
					<select>
						<option><?php esc_html_e( 'Founding Face 7', 'founding-faces' ); ?></option>
						<option><?php esc_html_e( 'Ada, Founding Face 7', 'founding-faces' ); ?></option>
						<option><?php esc_html_e( 'Ada Lovelace, Founding Face 7', 'founding-faces' ); ?></option>
					</select>
					<span class="ff-hint"><?php esc_html_e( 'Choose how your name appears on your member pages. Number only is the default. This never affects the members map, which always stays anonymous.', 'founding-faces' ); ?></span>
				</p>
				<p class="ff-field">
					<label><?php esc_html_e( 'Email address', 'founding-faces' ); ?></label>
					<input type="email" value="ada@example.com" />
					<span class="ff-hint"><?php esc_html_e( 'Changing this sends a confirmation to the new address before it takes effect.', 'founding-faces' ); ?></span>
				</p>
				<p class="ff-field ff-field--checkbox">
					<label>
						<input type="checkbox" checked />
						<?php esc_html_e( 'I\'d like to receive programme emails from Apotheca.', 'founding-faces' ); ?>
					</label>
				</p>
				<p class="ff-submit">
					<button type="button"><?php esc_html_e( 'Save changes', 'founding-faces' ); ?></button>
				</p>
			</div>

			<div class="ff-account-block">
				<h3><?php esc_html_e( 'Password', 'founding-faces' ); ?></h3>
				<button type="button" class="button"><?php esc_html_e( 'Email me a password reset link', 'founding-faces' ); ?></button>
			</div>

			<div class="ff-account-block">
				<h3><?php esc_html_e( 'Your data', 'founding-faces' ); ?></h3>
				<div class="ff-account-data-actions">
					<button type="button" class="button"><?php esc_html_e( 'Export my data (CSV)', 'founding-faces' ); ?></button>
					<button type="button" class="button ff-danger"><?php esc_html_e( 'Delete my data', 'founding-faces' ); ?></button>
				</div>
				<p class="ff-hint"><?php esc_html_e( 'Deleting removes your personal details. Your Founding number is retired, never reused.', 'founding-faces' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/*
	 * -----------------------------------------------------------------------
	 * The page.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the [ff_account] shortcode.
	 *
	 * @return string
	 */
	public static function render() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );

		if ( ! FF_Gating::is_member() ) {
			return FF_Display::members_only_notice();
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$number  = get_user_meta( $user_id, FF_Members::META_NUMBER, true );
		$group   = FF_Gating::is_the_35( $user_id ) ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' );
		$real    = get_user_meta( $user_id, FF_Members::META_REAL_NAME, true );
		$consent = (int) get_user_meta( $user_id, FF_Members::META_CONSENT, true );
		$pending = get_user_meta( $user_id, self::META_PENDING_EMAIL, true );

		$action = esc_url( admin_url( 'admin-post.php' ) );
		$return = esc_url( self::current_url() );

		ob_start();
		?>
		<div class="ff-account">
			<h2 class="ff-account-title"><?php esc_html_e( 'Your account', 'founding-faces' ); ?></h2>

			<?php echo self::notice(); // Escaped inside. ?>

			<!-- Read-only standing: Nick's to control, not the member's. -->
			<div class="ff-account-standing">
				<?php if ( $number ) : ?>
					<div><span class="ff-account-label"><?php esc_html_e( 'Founding number', 'founding-faces' ); ?></span>
						<strong><?php echo esc_html( $number ); ?></strong></div>
				<?php endif; ?>
				<div><span class="ff-account-label"><?php esc_html_e( 'Group', 'founding-faces' ); ?></span>
					<strong><?php echo esc_html( $group ); ?></strong></div>
				<div><span class="ff-account-label"><?php esc_html_e( 'Standing', 'founding-faces' ); ?></span>
					<strong><?php esc_html_e( 'Active member', 'founding-faces' ); ?></strong></div>
				<p class="ff-account-standing-note"><?php esc_html_e( 'Your number, group and standing are set by Apotheca and can\'t be changed here.', 'founding-faces' ); ?></p>
			</div>

			<!-- Profile: name, email, consent. -->
			<form class="ff-form ff-account-form" method="post" action="<?php echo $action; ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="ff_sub" value="save" />
				<input type="hidden" name="ff_return" value="<?php echo $return; ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>

				<p class="ff-field">
					<label for="ff-acct-name"><?php esc_html_e( 'Your name', 'founding-faces' ); ?></label>
					<input type="text" id="ff-acct-name" name="ff_name" value="<?php echo esc_attr( $real ); ?>" />
				</p>

				<?php
				// Display preference: The 35 only. The Circle has no public
				// display, so there is nothing for them to choose here.
				if ( FF_Gating::is_the_35( $user_id ) ) :
					$tier         = FF_Members::display_tier( $user_id );
					$number_label = sprintf(
						/* translators: %d is the member's Founding number. */
						__( 'Founding Face %d', 'founding-faces' ),
						(int) $number
					);
					$first        = $real ? preg_split( '/\s+/', trim( $real ) )[0] : '';
					?>
					<p class="ff-field">
						<label for="ff-display-tier"><?php esc_html_e( 'How you appear in the portal', 'founding-faces' ); ?></label>
						<select id="ff-display-tier" name="ff_display_tier">
							<option value="number" <?php selected( $tier, 'number' ); ?>>
								<?php echo esc_html( $number_label ); ?>
							</option>
							<option value="first_number" <?php selected( $tier, 'first_number' ); ?>>
								<?php echo esc_html( $first ? $first . ', ' . $number_label : __( 'First name and number', 'founding-faces' ) ); ?>
							</option>
							<option value="full_number" <?php selected( $tier, 'full_number' ); ?>>
								<?php echo esc_html( $real ? $real . ', ' . $number_label : __( 'Full name and number', 'founding-faces' ) ); ?>
							</option>
						</select>
						<span class="ff-hint"><?php esc_html_e( 'Choose how your name appears on your member pages. Number only is the default. This never affects the members map, which always stays anonymous.', 'founding-faces' ); ?></span>
					</p>
				<?php endif; ?>

				<p class="ff-field">
					<label for="ff-acct-email"><?php esc_html_e( 'Email address', 'founding-faces' ); ?></label>
					<input type="email" id="ff-acct-email" name="ff_email" value="<?php echo esc_attr( $user->user_email ); ?>" />
					<?php if ( $pending ) : ?>
						<span class="ff-hint"><?php echo esc_html( sprintf( __( 'A confirmation was sent to %s. The change applies once you click the link in that email.', 'founding-faces' ), $pending ) ); ?></span>
					<?php else : ?>
						<span class="ff-hint"><?php esc_html_e( 'Changing this sends a confirmation to the new address before it takes effect.', 'founding-faces' ); ?></span>
					<?php endif; ?>
				</p>

				<p class="ff-field ff-field--checkbox">
					<label>
						<input type="checkbox" name="ff_consent" value="1" <?php checked( $consent, 1 ); ?> />
						<?php esc_html_e( 'I\'d like to receive programme emails from Apotheca.', 'founding-faces' ); ?>
					</label>
					<span class="ff-hint"><?php esc_html_e( 'Turning this off unsubscribes you at our email platform too, not just here.', 'founding-faces' ); ?></span>
				</p>

				<p class="ff-submit">
					<button type="submit"><?php esc_html_e( 'Save changes', 'founding-faces' ); ?></button>
				</p>
			</form>

			<!-- Password reset (secure token, same as the welcome link). -->
			<div class="ff-account-block">
				<h3><?php esc_html_e( 'Password', 'founding-faces' ); ?></h3>
				<form method="post" action="<?php echo $action; ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="ff_sub" value="reset_password" />
					<input type="hidden" name="ff_return" value="<?php echo $return; ?>" />
					<?php wp_nonce_field( self::ACTION ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Email me a password reset link', 'founding-faces' ); ?></button>
				</form>
			</div>

			<!-- Self-service data rights. -->
			<div class="ff-account-block">
				<h3><?php esc_html_e( 'Your data', 'founding-faces' ); ?></h3>
				<div class="ff-account-data-actions">
					<form method="post" action="<?php echo $action; ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
						<input type="hidden" name="ff_sub" value="export" />
						<?php wp_nonce_field( self::ACTION ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Export my data (CSV)', 'founding-faces' ); ?></button>
					</form>

					<form method="post" action="<?php echo $action; ?>"
						onsubmit="return confirm('<?php echo esc_js( __( 'This permanently deletes your personal data and closes your account. This can\'t be undone. Continue?', 'founding-faces' ) ); ?>');">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
						<input type="hidden" name="ff_sub" value="delete" />
						<?php wp_nonce_field( self::ACTION ); ?>
						<button type="submit" class="button ff-danger"><?php esc_html_e( 'Delete my data', 'founding-faces' ); ?></button>
					</form>
				</div>
				<p class="ff-hint"><?php esc_html_e( 'Deleting removes your personal details. Your Founding number is retired, never reused.', 'founding-faces' ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/*
	 * -----------------------------------------------------------------------
	 * The form handler.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Process a submitted account form.
	 *
	 * @return void
	 */
	public static function handle() {
		$user_id = get_current_user_id();

		// Must be a logged-in member.
		if ( ! $user_id || ! FF_Gating::is_member( $user_id ) ) {
			wp_die( esc_html__( 'You need to be a logged-in member to do that.', 'founding-faces' ) );
		}

		check_admin_referer( self::ACTION );

		$sub    = isset( $_POST['ff_sub'] ) ? sanitize_key( wp_unslash( $_POST['ff_sub'] ) ) : '';
		$return = isset( $_POST['ff_return'] ) ? esc_url_raw( wp_unslash( $_POST['ff_return'] ) ) : home_url();

		switch ( $sub ) {
			case 'save':
				$code = self::handle_save( $user_id );
				break;

			case 'reset_password':
				FF_Emails::send_reset_link( $user_id );
				$code = 'reset_sent';
				break;

			case 'export':
				// Streams the CSV and exits.
				FF_Privacy::stream_export( $user_id );
				return;

			case 'delete':
				FF_Privacy::delete_member( $user_id );
				// Log the member out and send them home.
				wp_logout();
				wp_safe_redirect( add_query_arg( 'ff_acct', 'deleted', home_url( '/' ) ) );
				exit;

			default:
				$code = 'error';
		}

		wp_safe_redirect( add_query_arg( 'ff_acct', $code, $return ) );
		exit;
	}

	/**
	 * Handle the profile save: name, consent, and an email-change request.
	 *
	 * @param int $user_id The member's user id.
	 * @return string A message code for the redirect.
	 */
	private static function handle_save( $user_id ) {
		$user = get_userdata( $user_id );

		// --- Name ---
		if ( isset( $_POST['ff_name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['ff_name'] ) );
			if ( '' !== $name ) {
				update_user_meta( $user_id, FF_Members::META_REAL_NAME, $name );

				// The Circle's public identity is a first name, so keep it in step.
				if ( FF_Gating::is_the_circle( $user_id ) ) {
					$first = preg_split( '/\s+/', $name )[0];
					update_user_meta( $user_id, FF_Members::META_PUBLIC_NAME, $first );
					wp_update_user( array( 'ID' => $user_id, 'display_name' => $first, 'nickname' => $first ) );
				}
			}
		}

		// --- Display preference (The 35 only) ---
		// A member of The 35 may opt up from their number to also show their
		// first or full name. Default (and any invalid value) is number-only.
		// Recompute their stored identity afterwards so the whole portal updates,
		// including when only the name above changed.
		if ( FF_Gating::is_the_35( $user_id ) ) {
			if ( isset( $_POST['ff_display_tier'] ) ) {
				$tier = sanitize_key( wp_unslash( $_POST['ff_display_tier'] ) );
				if ( ! array_key_exists( $tier, FF_Members::display_tiers() ) ) {
					$tier = 'number';
				}
				update_user_meta( $user_id, FF_Members::META_DISPLAY_TIER, $tier );
			}
			FF_Members::sync_portal_identity( $user_id );
		}

		// --- Consent, with write-back to the email platform ---
		$new_consent = isset( $_POST['ff_consent'] ) ? 1 : 0;
		$old_consent = (int) get_user_meta( $user_id, FF_Members::META_CONSENT, true );
		if ( $new_consent !== $old_consent ) {
			update_user_meta( $user_id, FF_Members::META_CONSENT, $new_consent );
			if ( $new_consent ) {
				// Re-subscribe them on the platform.
				FF_Connectors::sync_member( $user_id );
			} else {
				// Write the unsubscribe back to Campaign Monitor, not just locally.
				FF_Connectors::unsubscribe_member( $user_id );
			}
		}

		// --- Email change (with confirmation) ---
		if ( isset( $_POST['ff_email'] ) ) {
			$new_email = sanitize_email( wp_unslash( $_POST['ff_email'] ) );
			if ( $new_email && is_email( $new_email ) && $new_email !== $user->user_email ) {
				if ( email_exists( $new_email ) ) {
					return 'email_taken';
				}
				self::start_email_change( $user_id, $new_email );
				return 'email_pending';
			}
		}

		return 'saved';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Email change confirmation.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Begin an email change: store the pending email + token and send a
	 * confirmation link to the new address.
	 *
	 * @param int    $user_id   The member's user id.
	 * @param string $new_email The requested new email.
	 * @return void
	 */
	private static function start_email_change( $user_id, $new_email ) {
		$token = wp_generate_password( 32, false );

		update_user_meta( $user_id, self::META_PENDING_EMAIL, $new_email );
		update_user_meta( $user_id, self::META_EMAIL_HASH, hash( 'sha256', $token ) );
		update_user_meta( $user_id, self::META_EMAIL_EXP, time() + DAY_IN_SECONDS );
		update_user_meta( $user_id, self::META_EMAIL_RETURN, self::current_url() );

		$link = add_query_arg(
			array(
				'ff_confirm_email' => 1,
				'uid'              => $user_id,
				'token'            => rawurlencode( $token ),
			),
			home_url( '/' )
		);

		$subject = __( 'Confirm your new email address', 'founding-faces' );
		$body    = wpautop( make_clickable( sprintf(
			/* translators: 1: secure link, 2: site name. */
			__( "Please confirm this is your new email address by clicking the link below (valid for 24 hours):\n%1\$s\n\nIf you didn't request this, you can ignore this email.\n\n%2\$s", 'founding-faces' ),
			esc_url( $link ),
			esc_html( get_bloginfo( 'name' ) )
		) ) );

		// Send to the NEW address so the change is confirmed by the owner.
		wp_mail( $new_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Handle a click on an email-change confirmation link.
	 *
	 * Validates the token, applies the new email, re-syncs the platform if
	 * consented, then redirects back to the account page.
	 *
	 * @return void
	 */
	public static function maybe_confirm_email() {
		if ( ! isset( $_GET['ff_confirm_email'] ) ) {
			return;
		}

		$uid   = isset( $_GET['uid'] ) ? absint( wp_unslash( $_GET['uid'] ) ) : 0;
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		$stored  = $uid ? get_user_meta( $uid, self::META_EMAIL_HASH, true ) : '';
		$expires = (int) get_user_meta( $uid, self::META_EMAIL_EXP, true );
		$pending = get_user_meta( $uid, self::META_PENDING_EMAIL, true );
		$return  = get_user_meta( $uid, self::META_EMAIL_RETURN, true );
		$return  = $return ? $return : home_url( '/' );

		$valid = ( $stored && $pending && time() <= $expires && hash_equals( $stored, hash( 'sha256', $token ) ) );

		if ( ! $valid ) {
			wp_safe_redirect( add_query_arg( 'ff_acct', 'email_bad', $return ) );
			exit;
		}

		// Apply the new email and clear the pending state.
		wp_update_user( array( 'ID' => $uid, 'user_email' => $pending ) );
		delete_user_meta( $uid, self::META_PENDING_EMAIL );
		delete_user_meta( $uid, self::META_EMAIL_HASH );
		delete_user_meta( $uid, self::META_EMAIL_EXP );
		delete_user_meta( $uid, self::META_EMAIL_RETURN );

		// Keep the email platform in step if they've consented.
		if ( get_user_meta( $uid, FF_Members::META_CONSENT, true ) ) {
			FF_Connectors::sync_member( $uid );
		}

		wp_safe_redirect( add_query_arg( 'ff_acct', 'email_changed', $return ) );
		exit;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Build the notice for the current ff_acct code, if any.
	 *
	 * @return string
	 */
	private static function notice() {
		if ( ! isset( $_GET['ff_acct'] ) ) {
			return '';
		}
		$code = sanitize_key( wp_unslash( $_GET['ff_acct'] ) );

		$messages = array(
			'saved'         => array( 'success', __( 'Your changes have been saved.', 'founding-faces' ) ),
			'email_pending' => array( 'success', __( 'Almost there — check your new email address for a confirmation link.', 'founding-faces' ) ),
			'email_changed' => array( 'success', __( 'Your email address has been updated.', 'founding-faces' ) ),
			'email_taken'   => array( 'error', __( 'That email address is already in use.', 'founding-faces' ) ),
			'email_bad'     => array( 'error', __( 'That confirmation link is invalid or has expired.', 'founding-faces' ) ),
			'reset_sent'    => array( 'success', __( 'We\'ve emailed you a secure link to set a new password.', 'founding-faces' ) ),
			'error'         => array( 'error', __( 'Something went wrong. Please try again.', 'founding-faces' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return '';
		}

		list( $type, $text ) = $messages[ $code ];
		return '<div class="ff-notice ff-notice--' . esc_attr( $type ) . '">' . esc_html( $text ) . '</div>';
	}

	/**
	 * The URL of the current page.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
