<?php
/**
 * The front-end application form and the logged-out status lookup.
 *
 * Handles rendering both forms (as shortcodes, which also work inside
 * Elementor), validating and storing a submission to ff_applications as a
 * pending record with consent and timestamp, and letting an applicant check
 * whether their application is still pending or has been decided.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Application
 *
 * All application-intake logic lives here. Input is sanitised on the way in,
 * output is escaped on the way out, and every state-changing request carries a
 * nonce.
 */
class FF_Application {

	// Shortcode that renders the application form.
	const FORM_SHORTCODE = 'ff_application_form';

	// Shortcode that renders the status-lookup form.
	const STATUS_SHORTCODE = 'ff_status_lookup';

	// The admin-post action name used when the application form is submitted.
	const SUBMIT_ACTION = 'ff_submit_application';

	/**
	 * Wire up the shortcodes and the form handler.
	 *
	 * Called once on plugin load. Registers the two shortcodes and the
	 * admin-post handlers that process a submitted application for both
	 * logged-in and logged-out visitors (the public will not be logged in).
	 */
	public static function register() {
		add_shortcode( self::FORM_SHORTCODE, array( __CLASS__, 'render_form' ) );
		add_shortcode( self::STATUS_SHORTCODE, array( __CLASS__, 'render_status_lookup' ) );

		// The form posts to admin-post.php; register the handler for both
		// visitor types so the public (logged-out) submission is accepted.
		add_action( 'admin_post_' . self::SUBMIT_ACTION, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::SUBMIT_ACTION, array( __CLASS__, 'handle_submit' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Asset loading.
	 * Styles and the small validation script load only when one of this
	 * plugin's shortcodes is actually on the page, keeping every other page
	 * untouched (governing principle 4: lean, always).
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Enqueue the form styles and the light client-side validation script.
	 *
	 * Called from inside the shortcode render methods, so the assets only ever
	 * load on a page that shows one of the forms.
	 */
	private static function enqueue_assets() {
		wp_enqueue_style(
			'founding-faces',
			FF_URL . 'assets/css/founding-faces.css',
			array(),
			FF_VERSION
		);

		wp_enqueue_script(
			'ff-application-form',
			FF_URL . 'assets/js/application-form.js',
			array(),
			FF_VERSION,
			true
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Postcode validation.
	 * The postcode is the only field the members map ever reads, so it must be
	 * a real Australian postcode, not just four digits.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Check whether a value is a real Australian postcode.
	 *
	 * Australian postcodes are always four digits, but not every four-digit
	 * number is allocated. This checks the value falls inside a range that
	 * Australia Post actually uses: 1000–9999 covers every state's leading
	 * digit, plus the two low blocks 0200–0299 (ACT) and 0800–0999 (NT).
	 * The bundled lookup table in a later stage is the final authority on
	 * placement; this is the honest gate at intake.
	 *
	 * @param string $postcode The raw postcode string.
	 * @return bool True if it is a valid Australian postcode.
	 */
	public static function is_valid_au_postcode( $postcode ) {
		// Must be exactly four digits, nothing else.
		if ( ! preg_match( '/^\d{4}$/', $postcode ) ) {
			return false;
		}

		$value = (int) $postcode;

		// The main allocated block plus the two low state blocks.
		if ( $value >= 1000 && $value <= 9999 ) {
			return true;
		}
		if ( $value >= 200 && $value <= 299 ) {
			return true;
		}
		if ( $value >= 800 && $value <= 999 ) {
			return true;
		}

		return false;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The application form.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the application form shortcode.
	 *
	 * Shows a success message after a good submission, or the form (with any
	 * validation errors and the applicant's previous answers preserved) after a
	 * failed one. Uses the Post/Redirect/Get pattern so a refresh never
	 * resubmits the form.
	 *
	 * @return string The form HTML.
	 */
	public static function render_form() {
		self::enqueue_assets();

		$output = '';

		// After a submission we are redirected back with an ff_app flag.
		$state = isset( $_GET['ff_app'] ) ? sanitize_key( wp_unslash( $_GET['ff_app'] ) ) : '';

		// A good submission: thank the applicant and stop, no form shown.
		if ( 'success' === $state ) {
			return '<div class="ff-notice ff-notice--success">'
				. esc_html__( 'Thank you. Your application has been received and is now being reviewed. We\'ll be in touch by email.', 'founding-faces' )
				. '</div>';
		}

		// A failed submission carries a token pointing at the stored errors and
		// the applicant's previous answers, so nothing they typed is lost.
		$errors = array();
		$old    = array();
		if ( 'error' === $state && isset( $_GET['ff_token'] ) ) {
			$token = sanitize_key( wp_unslash( $_GET['ff_token'] ) );
			$saved = get_transient( 'ff_app_' . $token );
			if ( is_array( $saved ) ) {
				$errors = isset( $saved['errors'] ) ? (array) $saved['errors'] : array();
				$old    = isset( $saved['old'] ) ? (array) $saved['old'] : array();
				delete_transient( 'ff_app_' . $token );
			}
		}

		// Show any validation errors at the top of the form.
		if ( ! empty( $errors ) ) {
			$output .= '<div class="ff-notice ff-notice--error"><ul>';
			foreach ( $errors as $error ) {
				$output .= '<li>' . esc_html( $error ) . '</li>';
			}
			$output .= '</ul></div>';
		}

		// A small helper to safely echo a previously entered value back in.
		$val = function ( $key ) use ( $old ) {
			return isset( $old[ $key ] ) ? esc_attr( $old[ $key ] ) : '';
		};
		$textval = function ( $key ) use ( $old ) {
			return isset( $old[ $key ] ) ? esc_textarea( $old[ $key ] ) : '';
		};

		$action = esc_url( admin_url( 'admin-post.php' ) );

		ob_start();
		?>
		<form class="ff-form ff-application-form" method="post" action="<?php echo $action; ?>" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SUBMIT_ACTION ); ?>" />
			<?php wp_nonce_field( self::SUBMIT_ACTION, 'ff_application_nonce' ); ?>
			<input type="hidden" name="ff_redirect" value="<?php echo esc_url( self::current_url() ); ?>" />

			<p class="ff-field">
				<label for="ff-name"><?php esc_html_e( 'Full name', 'founding-faces' ); ?> <span class="ff-required">*</span></label>
				<input type="text" id="ff-name" name="ff_name" value="<?php echo $val( 'name' ); ?>" required />
			</p>

			<p class="ff-field">
				<label for="ff-email"><?php esc_html_e( 'Email address', 'founding-faces' ); ?> <span class="ff-required">*</span></label>
				<input type="email" id="ff-email" name="ff_email" value="<?php echo $val( 'email' ); ?>" required />
			</p>

			<p class="ff-field">
				<label for="ff-postcode"><?php esc_html_e( 'Postcode', 'founding-faces' ); ?> <span class="ff-required">*</span></label>
				<input type="text" id="ff-postcode" name="ff_postcode" value="<?php echo $val( 'postcode' ); ?>"
					inputmode="numeric" pattern="\d{4}" maxlength="4"
					placeholder="<?php esc_attr_e( 'e.g. 2000', 'founding-faces' ); ?>" required />
				<span class="ff-hint"><?php esc_html_e( 'Four digits. Used only for the anonymous members map.', 'founding-faces' ); ?></span>
			</p>

			<p class="ff-field">
				<label for="ff-instagram"><?php esc_html_e( 'Instagram handle', 'founding-faces' ); ?></label>
				<input type="text" id="ff-instagram" name="ff_instagram" value="<?php echo $val( 'instagram' ); ?>"
					placeholder="<?php esc_attr_e( '@yourhandle', 'founding-faces' ); ?>" />
			</p>

			<p class="ff-field">
				<label for="ff-skin-concerns"><?php esc_html_e( 'Your main skin concerns', 'founding-faces' ); ?></label>
				<textarea id="ff-skin-concerns" name="ff_skin_concerns" rows="4"><?php echo $textval( 'skin_concerns' ); ?></textarea>
			</p>

			<p class="ff-field">
				<label for="ff-answers"><?php esc_html_e( 'Tell us a little about your skin and why you\'d like to join', 'founding-faces' ); ?></label>
				<textarea id="ff-answers" name="ff_answers" rows="5"><?php echo $textval( 'answers' ); ?></textarea>
			</p>

			<p class="ff-field ff-field--checkbox">
				<label>
					<input type="checkbox" name="ff_consent" value="1" required />
					<?php esc_html_e( 'I consent to receiving programme emails from Apotheca.', 'founding-faces' ); ?> <span class="ff-required">*</span>
				</label>
			</p>

			<p class="ff-submit">
				<button type="submit"><?php esc_html_e( 'Submit application', 'founding-faces' ); ?></button>
			</p>
		</form>
		<?php
		$output .= ob_get_clean();

		return $output;
	}

	/**
	 * Process a submitted application.
	 *
	 * Verifies the nonce, sanitises every field, validates the required fields
	 * and the postcode, and on success stores a pending record with the consent
	 * flag and a timestamp. Always redirects back to the form page (Post/
	 * Redirect/Get) so a browser refresh can't submit twice.
	 */
	public static function handle_submit() {
		// The page to return to after processing.
		$redirect = isset( $_POST['ff_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['ff_redirect'] ) ) : home_url();

		// Confirm the request genuinely came from our form.
		if ( ! isset( $_POST['ff_application_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ff_application_nonce'] ), self::SUBMIT_ACTION ) ) {
			self::redirect_with_errors( $redirect, array( __( 'Your session expired. Please try submitting the form again.', 'founding-faces' ) ), array() );
		}

		// Sanitise every field as it comes in.
		$name          = isset( $_POST['ff_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_name'] ) ) : '';
		$email         = isset( $_POST['ff_email'] ) ? sanitize_email( wp_unslash( $_POST['ff_email'] ) ) : '';
		$postcode      = isset( $_POST['ff_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_postcode'] ) ) : '';
		$instagram     = isset( $_POST['ff_instagram'] ) ? sanitize_text_field( wp_unslash( $_POST['ff_instagram'] ) ) : '';
		$skin_concerns = isset( $_POST['ff_skin_concerns'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ff_skin_concerns'] ) ) : '';
		$answers       = isset( $_POST['ff_answers'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ff_answers'] ) ) : '';
		$consent       = isset( $_POST['ff_consent'] ) ? 1 : 0;

		// Keep the applicant's answers so we can hand them back if validation fails.
		$old = array(
			'name'          => $name,
			'email'         => $email,
			'postcode'      => $postcode,
			'instagram'     => $instagram,
			'skin_concerns' => $skin_concerns,
			'answers'       => $answers,
		);

		// Validate.
		$errors = array();

		if ( '' === $name ) {
			$errors[] = __( 'Please enter your full name.', 'founding-faces' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'founding-faces' );
		}
		if ( '' === $postcode ) {
			$errors[] = __( 'Please enter your postcode.', 'founding-faces' );
		} elseif ( ! self::is_valid_au_postcode( $postcode ) ) {
			$errors[] = __( 'Please enter a valid four-digit Australian postcode.', 'founding-faces' );
		}
		if ( ! $consent ) {
			$errors[] = __( 'Please tick the consent box to continue.', 'founding-faces' );
		}

		// Stop and send the applicant back with their answers if anything's wrong.
		if ( ! empty( $errors ) ) {
			self::redirect_with_errors( $redirect, $errors, $old );
		}

		// Store the pending application.
		global $wpdb;
		$now = current_time( 'mysql' );

		$wpdb->insert(
			$wpdb->prefix . 'ff_applications',
			array(
				'created_at'    => $now,
				'name'          => $name,
				'email'         => $email,
				'postcode'      => $postcode,
				'instagram'     => $instagram,
				'skin_concerns' => $skin_concerns,
				'answers'       => $answers,
				'consent'       => $consent,
				'consent_at'    => $consent ? $now : null,
				'status'        => 'pending',
				'assigned_number' => null,
				'user_id'       => null,
				'is_test'       => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d' )
		);

		// Send the applicant back to a clean success state.
		wp_safe_redirect( add_query_arg( 'ff_app', 'success', $redirect ) );
		exit;
	}

	/**
	 * Redirect back to the form with validation errors and prior answers.
	 *
	 * The errors and answers are tucked into a short-lived transient keyed by a
	 * random token, so the URL stays clean and nothing is exposed in it.
	 *
	 * @param string $redirect Where to send the applicant back to.
	 * @param array  $errors   The list of human-readable error messages.
	 * @param array  $old      The applicant's previously entered values.
	 */
	private static function redirect_with_errors( $redirect, $errors, $old ) {
		$token = wp_generate_password( 12, false );
		set_transient(
			'ff_app_' . $token,
			array(
				'errors' => $errors,
				'old'    => $old,
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'ff_app'   => 'error',
					'ff_token' => $token,
				),
				$redirect
			)
		);
		exit;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The status lookup.
	 * A logged-out applicant enters their email and learns whether their
	 * application is still pending or has been decided, so Nick isn't fielding
	 * "did I get in?" messages for weeks.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the status-lookup shortcode.
	 *
	 * Shows the lookup form and, once an email has been submitted (with a valid
	 * nonce), the current state of that application. The result is deliberately
	 * coarse: pending, decided, or not found. It never reveals the group, the
	 * assigned number, or any stored answer.
	 *
	 * @return string The lookup HTML.
	 */
	public static function render_status_lookup() {
		self::enqueue_assets();

		$result_html = '';
		$email       = '';

		// Process a submitted lookup. This only reads data, but we still verify
		// a nonce so the endpoint can't be driven by an off-site form.
		if ( isset( $_POST['ff_status_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['ff_status_nonce'] ), 'ff_status_lookup' ) ) {
			$email = isset( $_POST['ff_status_email'] ) ? sanitize_email( wp_unslash( $_POST['ff_status_email'] ) ) : '';

			if ( '' === $email || ! is_email( $email ) ) {
				$result_html = '<div class="ff-notice ff-notice--error">'
					. esc_html__( 'Please enter a valid email address.', 'founding-faces' )
					. '</div>';
			} else {
				$result_html = self::status_message_for_email( $email );
			}
		}

		ob_start();
		?>
		<form class="ff-form ff-status-form" method="post" action="<?php echo esc_url( self::current_url() ); ?>" novalidate>
			<?php wp_nonce_field( 'ff_status_lookup', 'ff_status_nonce' ); ?>
			<p class="ff-field">
				<label for="ff-status-email"><?php esc_html_e( 'Check your application status', 'founding-faces' ); ?></label>
				<input type="email" id="ff-status-email" name="ff_status_email" value="<?php echo esc_attr( $email ); ?>"
					placeholder="<?php esc_attr_e( 'The email you applied with', 'founding-faces' ); ?>" required />
			</p>
			<p class="ff-submit">
				<button type="submit"><?php esc_html_e( 'Check status', 'founding-faces' ); ?></button>
			</p>
		</form>
		<?php
		$form_html = ob_get_clean();

		// Show the result above the form so it's the first thing seen.
		return $result_html . $form_html;
	}

	/**
	 * Build the status message for a given email address.
	 *
	 * Looks up the most recent application for the email and returns a friendly,
	 * deliberately vague message. A decided application (approved either way, or
	 * declined) is reported as "a decision has been made — check your email", so
	 * the lookup never delivers a cold rejection and never leaks the group.
	 *
	 * @param string $email The applicant's email address.
	 * @return string The message HTML.
	 */
	private static function status_message_for_email( $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ff_applications';

		// The most recent application for this email.
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT 1",
				$email
			)
		);

		if ( null === $status ) {
			return '<div class="ff-notice">'
				. esc_html__( 'We couldn\'t find an application for that email address. Please check it and try again.', 'founding-faces' )
				. '</div>';
		}

		if ( 'pending' === $status ) {
			return '<div class="ff-notice ff-notice--pending">'
				. esc_html__( 'Your application is being reviewed. We\'ll email you as soon as there\'s news — thank you for your patience.', 'founding-faces' )
				. '</div>';
		}

		// Anything else means the application has been decided.
		return '<div class="ff-notice ff-notice--decided">'
			. esc_html__( 'A decision has been made on your application. Please check your inbox (including spam) for an email from Apotheca.', 'founding-faces' )
			. '</div>';
	}

	/*
	 * -----------------------------------------------------------------------
	 * Small helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Work out the URL of the page currently being viewed.
	 *
	 * Used as the form's own action and as the return address after a
	 * submission, so the applicant always lands back on the page that hosts the
	 * form regardless of which page Nick placed it on.
	 *
	 * @return string The current page URL.
	 */
	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
