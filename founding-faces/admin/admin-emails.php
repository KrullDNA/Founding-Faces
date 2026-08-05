<?php
/**
 * Admin "Emails" screen: preview every email, and send yourself a test.
 *
 * Editing a template on the Settings page is writing in the dark otherwise —
 * the only way to see the result was to approve a real applicant and read a
 * real inbox. This screen renders the message exactly as it is sent, through
 * the same FF_Emails::compose() the senders use, so the preview cannot drift
 * out of step with the real thing.
 *
 * Two things it deliberately will not do. It never generates a real
 * set-password token, because that would invalidate a link already sitting in
 * a member's inbox — the preview carries a sample link that lands on the
 * "this link has expired" screen. And a test is only ever sent to an address
 * typed in here, never to the member whose details are being previewed.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Admin_Emails
 */
class FF_Admin_Emails {

	const PAGE_SLUG = 'founding-faces-emails';

	/**
	 * Wire up the submenu and the test-send handler.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_ff_send_test_email', array( __CLASS__, 'handle_test_send' ) );
	}

	/**
	 * Add the Emails submenu under the Founding Faces menu.
	 */
	public static function add_menu() {
		add_submenu_page(
			FF_Admin_Applications::PAGE_SLUG,
			__( 'Founding Faces Emails', 'founding-faces' ),
			__( 'Emails', 'founding-faces' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * The sample link used in every preview and test.
	 *
	 * A real token is never minted here: creating one would overwrite the token
	 * behind a link already sent to that member, and a preview should not be
	 * able to break a member's way in. This link reaches the set-password
	 * screen and is told, correctly, that it has expired.
	 *
	 * @return string
	 */
	private static function sample_link() {
		return FF_Emails::build_setpw_link( 0, 'sample-preview-link' );
	}

	/**
	 * The placeholder values a preview is filled with.
	 *
	 * @param string $kind    A key from FF_Emails::kinds().
	 * @param int    $user_id A real member to borrow details from, or 0 for
	 *                        made-up ones.
	 * @return array
	 */
	private static function replacements( $kind, $user_id = 0 ) {
		if ( $user_id > 0 && get_userdata( $user_id ) ) {
			return FF_Emails::member_replacements( $user_id, self::sample_link() );
		}

		// Made-up but plausible details, so the layout is judged on something
		// the length of a real name and a real number.
		$admin = wp_get_current_user();
		$name  = $admin && $admin->first_name ? $admin->first_name : __( 'Alex', 'founding-faces' );
		$is_35 = ( 'welcome_circle' !== $kind );

		return array(
			'{name}'              => $name,
			'{number}'            => $is_35 ? 7 : '',
			'{group}'             => $is_35 ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' ),
			'{public_name}'       => $is_35 ? __( 'No. 7', 'founding-faces' ) : $name,
			'{site_name}'         => get_bloginfo( 'name' ),
			'{login_url}'         => wp_login_url(),
			'{set_password_link}' => self::sample_link(),
		);
	}

	/**
	 * The members available to preview against, newest first.
	 *
	 * @return array Map of user id => label.
	 */
	private static function member_choices() {
		$users = get_users( array(
			'meta_key'     => FF_Members::META_GROUP, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'number'       => 200,
			'orderby'      => 'ID',
			'order'        => 'DESC',
		) );

		$out = array();
		foreach ( $users as $user ) {
			$number = get_user_meta( $user->ID, FF_Members::META_NUMBER, true );
			$real   = get_user_meta( $user->ID, FF_Members::META_REAL_NAME, true );
			$label  = '' !== trim( (string) $real ) ? $real : $user->display_name;
			if ( '' !== $number ) {
				/* translators: 1: member name, 2: founding number. */
				$label = sprintf( __( '%1$s, No. %2$d', 'founding-faces' ), $label, (int) $number );
			} else {
				/* translators: %s: member name. */
				$label = sprintf( __( '%s, The Circle', 'founding-faces' ), $label );
			}
			$out[ $user->ID ] = $label;
		}
		return $out;
	}

	/**
	 * Send a test of the chosen email to a typed address.
	 *
	 * The address is always the one typed on the form. The member dropdown only
	 * decides whose name and number fill the placeholders — it never decides
	 * who receives the test.
	 */
	public static function handle_test_send() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'founding-faces' ) );
		}
		check_admin_referer( 'ff_send_test_email' );

		$kind    = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$user_id = isset( $_POST['member'] ) ? absint( wp_unslash( $_POST['member'] ) ) : 0;
		$to      = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';

		$back = add_query_arg(
			array(
				'page'   => self::PAGE_SLUG,
				'kind'   => $kind,
				'member' => $user_id,
			),
			admin_url( 'admin.php' )
		);

		if ( ! is_email( $to ) ) {
			wp_safe_redirect( add_query_arg( 'ff_sent', 'bad_address', $back ) );
			exit;
		}

		$message = FF_Emails::compose( $kind, self::replacements( $kind, $user_id ) );
		if ( ! $message ) {
			// The decline template's body has been emptied, so nothing is sent.
			wp_safe_redirect( add_query_arg( 'ff_sent', 'empty', $back ) );
			exit;
		}

		$sent = wp_mail(
			$to,
			$message['subject'],
			$message['html'],
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		wp_safe_redirect( add_query_arg( 'ff_sent', $sent ? 'yes' : 'no', $back ) );
		exit;
	}

	/**
	 * The notice shown after a test send.
	 *
	 * @param string $to The address it went to, for the message.
	 */
	private static function send_notice( $to ) {
		$result = isset( $_GET['ff_sent'] ) ? sanitize_key( wp_unslash( $_GET['ff_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'yes' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Test sent. If it does not arrive, the problem is with the site\'s mail delivery rather than the template, check your spam folder first, then whatever sends mail for this site.', 'founding-faces' )
				. '</p></div>';
		} elseif ( 'no' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'WordPress refused to send that. Nothing is wrong with the template, the site cannot send mail at the moment.', 'founding-faces' )
				. '</p></div>';
		} elseif ( 'bad_address' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'That is not a valid email address.', 'founding-faces' )
				. '</p></div>';
		} elseif ( 'empty' === $result ) {
			echo '<div class="notice notice-warning is-dismissible"><p>'
				. esc_html__( 'Nothing was sent: this email\'s body has been cleared on the Settings page, which switches it off. That is exactly what a real applicant would get, nothing.', 'founding-faces' )
				. '</p></div>';
		}
	}

	/**
	 * Render the Emails screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$kinds = FF_Emails::kinds();

		// phpcs:disable WordPress.Security.NonceVerification — read-only view.
		$kind    = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$user_id = isset( $_GET['member'] ) ? absint( wp_unslash( $_GET['member'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! isset( $kinds[ $kind ] ) ) {
			$kind = 'welcome_35';
		}

		$spec    = $kinds[ $kind ];
		$members = self::member_choices();
		$to      = wp_get_current_user()->user_email;
		$message = FF_Emails::compose( $kind, self::replacements( $kind, $user_id ) );
		?>
		<div class="wrap ff-admin">
			<h1><?php esc_html_e( 'Founding Faces Emails', 'founding-faces' ); ?></h1>
			<p class="description" style="max-width:46em;">
				<?php esc_html_e( 'Every email exactly as a member receives it. This is the real message, built the same way the real one is, so what you see here is what lands in an inbox. The wording lives on the Settings page; the colours, logo and footer live in Email design on that same page.', 'founding-faces' ); ?>
			</p>

			<?php self::send_notice( $to ); ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ff-email-kind"><?php esc_html_e( 'Which email', 'founding-faces' ); ?></label></th>
						<td>
							<select name="kind" id="ff-email-kind">
								<?php foreach ( $kinds as $key => $one ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $kind ); ?>>
										<?php echo esc_html( $one['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html( $spec['when'] ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ff-email-member"><?php esc_html_e( 'Fill the details with', 'founding-faces' ); ?></label></th>
						<td>
							<select name="member" id="ff-email-member">
								<option value="0"><?php esc_html_e( 'Made-up details (no member involved)', 'founding-faces' ); ?></option>
								<?php foreach ( $members as $id => $label ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( (int) $id, $user_id ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choosing a member only borrows their name, number and group to fill the placeholders. Nothing is sent to them and nothing about their account changes.', 'founding-faces' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Show this email', 'founding-faces' ), 'secondary', '', false ); ?>
			</form>

			<hr />

			<?php if ( ! $message ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'This email is switched off: its body has been cleared on the Settings page, so nothing is sent at all. Put some wording back to turn it on.', 'founding-faces' ); ?>
				</p></div>
			<?php else : ?>

				<h2><?php esc_html_e( 'The subject line', 'founding-faces' ); ?></h2>
				<p style="font-size:15px;background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:10px 12px;max-width:46em;">
					<strong><?php echo esc_html( $message['subject'] ); ?></strong>
				</p>

				<h2><?php esc_html_e( 'The email', 'founding-faces' ); ?></h2>
				<iframe
					title="<?php esc_attr_e( 'Email preview', 'founding-faces' ); ?>"
					srcdoc="<?php echo esc_attr( $message['html'] ); ?>"
					style="width:100%;max-width:760px;height:820px;border:1px solid #dcdcde;border-radius:4px;background:#fff;"
				></iframe>

				<?php if ( ! empty( $spec['cta_url'] ) && '{set_password_link}' === $spec['cta_url'] ) : ?>
					<p class="description" style="max-width:46em;">
						<?php esc_html_e( 'The button in this preview is a sample link, not a working one, a real one is only ever made when a real email is sent, so previewing can never break a link already sitting in a member\'s inbox. Following it will correctly say the link has expired.', 'founding-faces' ); ?>
					</p>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Send yourself a test', 'founding-faces' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ff_send_test_email' ); ?>
					<input type="hidden" name="action" value="ff_send_test_email" />
					<input type="hidden" name="kind" value="<?php echo esc_attr( $kind ); ?>" />
					<input type="hidden" name="member" value="<?php echo esc_attr( $user_id ); ?>" />
					<p>
						<label for="ff-email-to" class="screen-reader-text"><?php esc_html_e( 'Send to', 'founding-faces' ); ?></label>
						<input type="email" name="to" id="ff-email-to" class="regular-text"
							value="<?php echo esc_attr( $to ); ?>" required />
						<?php submit_button( __( 'Send this test', 'founding-faces' ), 'primary', '', false ); ?>
					</p>
					<p class="description" style="max-width:46em;">
						<?php esc_html_e( 'Goes only to the address above. Worth doing at least once for the welcome emails: a preview shows the design, but only a real inbox shows how your mail host, and Gmail, treat it.', 'founding-faces' ); ?>
					</p>
				</form>

			<?php endif; ?>
		</div>
		<?php
	}
}
