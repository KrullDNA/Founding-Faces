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

	// Option: when on, a valid application is accepted straight into The Circle
	// (member created, welcome email sent) with no manual moderation. Nick turns
	// this on once The 35 has been chosen, so Circle applications need no clicks.
	const OPT_AUTO_ACCEPT = 'ff_auto_accept_circle';

	// The name of the honeypot field. It is hidden from real people; only a bot
	// fills it, so any submission with a value here is silently dropped.
	const HONEYPOT_FIELD = 'ff_website';

	// The shortest believable time (seconds) a human takes to fill the form. A
	// submission faster than this was almost certainly scripted.
	const MIN_FILL_SECONDS = 3;

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

		// The Elementor widget wrapper for the form, so it can be styled visually.
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register the Application Form Elementor widget.
	 *
	 * The widget is a thin wrapper: it renders the very same form markup as the
	 * shortcode (one source of truth) and adds a full Style tab that targets the
	 * form's existing classes, scoped to the widget.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-application-widget.php';
		require_once FF_PATH . 'includes/class-ff-status-widget.php';
		$widgets_manager->register( new FF_Application_Widget() );
		$widgets_manager->register( new FF_Status_Widget() );
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
	 * Every phrase the application form says, before anything overrides it.
	 *
	 * This is the whole of the form's language in one place: labels, the two
	 * placeholders, the hints under the private fields, the consent line, the
	 * button and the thank-you notice. Each is a field in the widget, because
	 * the words an applicant reads are Nick's to choose, not the plugin's.
	 *
	 * @return array Key => default text.
	 */
	public static function text_defaults() {
		return array(
			'name_label'             => __( 'Full name', 'founding-faces' ),
			'name_placeholder'       => '',
			'email_label'            => __( 'Email address', 'founding-faces' ),
			'email_placeholder'      => '',
			'postcode_label'         => __( 'Postcode', 'founding-faces' ),
			'postcode_placeholder'   => __( 'e.g. 2000', 'founding-faces' ),
			'postcode_hint'          => __( 'Four digits. Used only for the anonymous members map.', 'founding-faces' ),
			'instagram_label'        => __( 'Instagram handle', 'founding-faces' ),
			'instagram_placeholder'  => __( '@yourhandle', 'founding-faces' ),
			'instagram_hint'         => __( 'Optional, and only used privately to review your application — it\'s never shown to other members or on the map.', 'founding-faces' ),
			'concerns_label'         => __( 'Your main skin concerns', 'founding-faces' ),
			'concerns_placeholder'   => '',
			'concerns_hint'          => '',
			'answers_label'          => __( 'Tell us a little about your skin and why you\'d like to join', 'founding-faces' ),
			'answers_placeholder'    => '',
			'answers_hint'           => '',
			'consent_label'          => __( 'I consent to receiving programme emails from Apotheca.', 'founding-faces' ),
			'consent_hint'           => '',
			'required_mark'          => '*',
			'button'                 => __( 'Submit application', 'founding-faces' ),
			'success'                => __( 'Thank you. Your application has been received and is now being reviewed. We\'ll be in touch by email.', 'founding-faces' ),
		);
	}

	/**
	 * Resolve wording overrides against the defaults.
	 *
	 * A key that isn't supplied keeps its default. A key supplied empty is a
	 * deliberate choice to say nothing: hints and placeholders disappear, and a
	 * label stays in the markup for screen readers but is hidden on screen —
	 * a field nobody can name is a field nobody can fill in.
	 *
	 * 'button_label' and 'success_message' are the names the shortcode has
	 * always used, so they keep working.
	 *
	 * @param array $overrides Supplied wording.
	 * @return array
	 */
	public static function text( $overrides = array() ) {
		$overrides = is_array( $overrides ) ? $overrides : array();

		// The older att names, mapped onto the keys used here.
		foreach ( array( 'button_label' => 'button', 'success_message' => 'success' ) as $old => $new ) {
			if ( ! isset( $overrides[ $new ] ) && ! empty( $overrides[ $old ] ) ) {
				$overrides[ $new ] = $overrides[ $old ];
			}
		}

		// Labels, hints and the button take inline markup; the thank-you notice
		// is a message, so it takes what a post takes. The consent line is the
		// reason this matters most — it usually needs to link a privacy policy.
		//
		// Placeholders are the exception: they end up inside an attribute, where
		// a tag can only ever arrive as visible angle brackets, so they are held
		// to plain text rather than promising markup that cannot work.
		$rich = array( 'success' );
		$text = self::text_defaults();

		foreach ( $text as $key => $default ) {
			if ( ! isset( $overrides[ $key ] ) ) {
				continue;
			}

			if ( '_placeholder' === substr( $key, -12 ) ) {
				$text[ $key ] = trim( wp_strip_all_tags( (string) $overrides[ $key ] ) );
			} elseif ( in_array( $key, $rich, true ) ) {
				$text[ $key ] = FF_Text::rich( $overrides[ $key ] );
			} else {
				$text[ $key ] = FF_Text::inline( $overrides[ $key ] );
			}
		}

		return $text;
	}

	/**
	 * One form label, hidden on screen when its wording has been cleared.
	 *
	 * @param string $for   The field id.
	 * @param string $label The label text.
	 * @param string $mark  The required marker, or '' for an optional field.
	 * @param string $key   The wording key, used to name the field when cleared.
	 * @return string
	 */
	private static function label( $for, $label, $mark = '', $key = '' ) {
		$defaults = self::text_defaults();
		$blank    = ! FF_Text::filled( $label );
		$class    = $blank ? ' class="ff-sr-only"' : '';
		$text     = $blank
			? ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : $for )
			: $label;

		$out = '<label for="' . esc_attr( $for ) . '"' . $class . '>' . FF_Text::inline( $text );

		if ( FF_Text::filled( $mark ) ) {
			$out .= ' <span class="ff-required">' . FF_Text::inline( $mark ) . '</span>';
		}

		return $out . '</label>';
	}

	/**
	 * One hint line, or nothing when its wording has been cleared.
	 *
	 * @param string $hint The hint text.
	 * @return string
	 */
	private static function hint( $hint ) {
		return FF_Text::filled( $hint ) ? '<span class="ff-hint">' . FF_Text::inline( $hint ) . '</span>' : '';
	}

	/**
	 * Render the application form shortcode.
	 *
	 * Shows a success message after a good submission, or the form (with any
	 * validation errors and the applicant's previous answers preserved) after a
	 * failed one. Uses the Post/Redirect/Get pattern so a refresh never
	 * resubmits the form.
	 *
	 * @param array $atts Optional overrides: 'button_label' for the submit
	 *                    button text, 'success_message' for the thank-you notice.
	 * @return string The form HTML.
	 */
	public static function render_form( $atts = array() ) {
		self::enqueue_assets();

		// Shortcode hands atts as '' when none are given; normalise to an array.
		$atts = is_array( $atts ) ? $atts : array();

		// Every phrase the form says, with anything the widget or shortcode
		// supplied taking precedence over the defaults.
		$t            = self::text( $atts );
		$button_label = $t['button'];
		$mark         = $t['required_mark'];

		// The Elementor widget passes a modifier class so the form fills its
		// container (the container then governs width) instead of the 560px cap.
		$form_class = 'ff-form ff-application-form';
		if ( ! empty( $atts['form_class'] ) ) {
			$form_class .= ' ' . sanitize_html_class( $atts['form_class'] );
		}

		$output = '';

		// After a submission we are redirected back with an ff_app flag.
		$state = isset( $_GET['ff_app'] ) ? sanitize_key( wp_unslash( $_GET['ff_app'] ) ) : '';

		// A good submission: thank the applicant and stop, no form shown.
		if ( 'success' === $state ) {
			return '<div class="ff-notice ff-notice--success">'
				. wpautop( $t['success'] )
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

		// In the Elementor editor the success notice never appears (it only
		// exists after a real submission, and it replaces the form), so show it
		// as a sample above the form — both are then styleable at once.
		if ( '' === $state && FF_History::is_editor() ) {
			$output .= '<div class="ff-notice ff-notice--success">' . wpautop( $t['success'] ) . '</div>';
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
		<form class="<?php echo esc_attr( $form_class ); ?>" method="post" action="<?php echo $action; ?>" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SUBMIT_ACTION ); ?>" />
			<?php wp_nonce_field( self::SUBMIT_ACTION, 'ff_application_nonce' ); ?>
			<input type="hidden" name="ff_redirect" value="<?php echo esc_url( self::current_url() ); ?>" />

			<?php // Spam trap: the timestamp catches instant (scripted) submits. ?>
			<input type="hidden" name="ff_ts" value="<?php echo esc_attr( time() ); ?>" />

			<?php // Honeypot: hidden from people, tempting to bots. Left empty by
			// real applicants; any value here means the submission is a bot. ?>
			<div class="ff-hp" aria-hidden="true">
				<label for="ff-website"><?php esc_html_e( 'Website', 'founding-faces' ); ?></label>
				<input type="text" id="ff-website" name="<?php echo esc_attr( self::HONEYPOT_FIELD ); ?>"
					tabindex="-1" autocomplete="off" value="" />
			</div>

			<p class="ff-field">
				<?php echo self::label( 'ff-name', $t['name_label'], $mark, 'name_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" id="ff-name" name="ff_name" value="<?php echo $val( 'name' ); ?>"
					placeholder="<?php echo esc_attr( $t['name_placeholder'] ); ?>" required />
			</p>

			<p class="ff-field">
				<?php echo self::label( 'ff-email', $t['email_label'], $mark, 'email_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="email" id="ff-email" name="ff_email" value="<?php echo $val( 'email' ); ?>"
					placeholder="<?php echo esc_attr( $t['email_placeholder'] ); ?>" required />
			</p>

			<p class="ff-field">
				<?php echo self::label( 'ff-postcode', $t['postcode_label'], $mark, 'postcode_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" id="ff-postcode" name="ff_postcode" value="<?php echo $val( 'postcode' ); ?>"
					inputmode="numeric" pattern="\d{4}" maxlength="4"
					placeholder="<?php echo esc_attr( $t['postcode_placeholder'] ); ?>" required />
				<?php echo self::hint( $t['postcode_hint'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

			<p class="ff-field">
				<?php echo self::label( 'ff-instagram', $t['instagram_label'], '', 'instagram_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" id="ff-instagram" name="ff_instagram" value="<?php echo $val( 'instagram' ); ?>"
					placeholder="<?php echo esc_attr( $t['instagram_placeholder'] ); ?>" />
				<?php echo self::hint( $t['instagram_hint'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

			<p class="ff-field">
				<?php echo self::label( 'ff-skin-concerns', $t['concerns_label'], '', 'concerns_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<textarea id="ff-skin-concerns" name="ff_skin_concerns" rows="4"
					placeholder="<?php echo esc_attr( $t['concerns_placeholder'] ); ?>"><?php echo $textval( 'skin_concerns' ); ?></textarea>
				<?php echo self::hint( $t['concerns_hint'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

			<p class="ff-field">
				<?php echo self::label( 'ff-answers', $t['answers_label'], '', 'answers_label' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<textarea id="ff-answers" name="ff_answers" rows="5"
					placeholder="<?php echo esc_attr( $t['answers_placeholder'] ); ?>"><?php echo $textval( 'answers' ); ?></textarea>
				<?php echo self::hint( $t['answers_hint'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

			<p class="ff-field ff-field--checkbox">
				<label>
					<input type="checkbox" name="ff_consent" value="1" required />
					<?php echo FF_Text::inline( $t['consent_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( FF_Text::filled( $mark ) ) : ?>
						<span class="ff-required"><?php echo FF_Text::inline( $mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</label>
				<?php echo self::hint( $t['consent_hint'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>

			<p class="ff-submit">
				<button type="submit"><?php echo FF_Text::inline( $button_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
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

		// Spam trap. If the honeypot was filled, or the form was submitted
		// impossibly fast, treat it as a bot: store nothing, send nothing, and
		// quietly show the success page so the bot gets no useful signal. This
		// matters most with auto-accept on, where a stored application would
		// otherwise become a Circle member automatically.
		if ( self::is_spam_submission() ) {
			wp_safe_redirect( add_query_arg( 'ff_app', 'success', $redirect ) );
			exit;
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

		$app_id = (int) $wpdb->insert_id;

		// What happens next depends on the "New applications" setting:
		//  - Auto-accept on  → create the Circle member now; the welcome email is
		//    their acknowledgement (so we don't also send the received email).
		//  - Auto-accept off → leave it pending for manual review and send the
		//    "application received" acknowledgement.
		if ( $app_id && self::auto_accept_enabled() && class_exists( 'FF_Members' ) ) {
			FF_Members::approve( $app_id, 'the-circle' );
		} else {
			FF_Emails::send_application_received( $name, $email );
		}

		// Send the applicant back to a clean success state.
		wp_safe_redirect( add_query_arg( 'ff_app', 'success', $redirect ) );
		exit;
	}

	/**
	 * Whether auto-accept into The Circle is switched on.
	 *
	 * @return bool
	 */
	public static function auto_accept_enabled() {
		return (bool) get_option( self::OPT_AUTO_ACCEPT, false );
	}

	/**
	 * Decide whether a submission is spam (honeypot filled or submitted too fast).
	 *
	 * @return bool
	 */
	private static function is_spam_submission() {
		// The honeypot must stay empty for a real person.
		$hp = isset( $_POST[ self::HONEYPOT_FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::HONEYPOT_FIELD ] ) ) : '';
		if ( '' !== $hp ) {
			return true;
		}

		// The form must have been on-screen for at least a few seconds. A missing
		// or non-numeric timestamp is treated as human (page caches can strip it),
		// so only a clearly-too-fast submission is flagged.
		$ts = isset( $_POST['ff_ts'] ) ? absint( wp_unslash( $_POST['ff_ts'] ) ) : 0;
		if ( $ts > 0 && ( time() - $ts ) < self::MIN_FILL_SECONDS ) {
			return true;
		}

		return false;
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
	 * @param array $atts Optional overrides: 'label' for the field label,
	 *                    'button_label' for the submit button text.
	 * @return string The lookup HTML.
	 */
	public static function render_status_lookup( $atts = array() ) {
		self::enqueue_assets();

		$atts         = is_array( $atts ) ? $atts : array();
		$label        = ! empty( $atts['label'] ) ? $atts['label'] : __( 'Check your application status', 'founding-faces' );
		$button_label = ! empty( $atts['button_label'] ) ? $atts['button_label'] : __( 'Check status', 'founding-faces' );
		$heading      = isset( $atts['heading'] ) ? (string) $atts['heading'] : '';
		$intro        = isset( $atts['intro'] ) ? (string) $atts['intro'] : '';
		$hide_on_found = ( isset( $atts['hide_on_found'] ) && 'yes' === $atts['hide_on_found'] );
		$again_label  = ! empty( $atts['again_label'] ) ? $atts['again_label'] : __( 'Check another email', 'founding-faces' );

		$form_class = 'ff-form ff-status-form';
		if ( ! empty( $atts['form_class'] ) ) {
			$form_class .= ' ' . sanitize_html_class( $atts['form_class'] );
		}

		$result_html = '';
		$email       = '';
		$found       = false;

		// A "send it again" request: re-send the decision email to the address
		// already on file. Handled before the lookup so the confirmation shows.
		if ( isset( $_POST['ff_resend_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['ff_resend_nonce'] ), 'ff_status_resend' ) ) {
			$email       = isset( $_POST['ff_status_email'] ) ? sanitize_email( wp_unslash( $_POST['ff_status_email'] ) ) : '';
			$result_html = self::resend_decision_email( $email );
			$found       = true;
		} elseif ( isset( $_POST['ff_status_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['ff_status_nonce'] ), 'ff_status_lookup' ) ) {
			// Process a submitted lookup. This only reads data, but we still
			// verify a nonce so the endpoint can't be driven by an off-site form.
			$email = isset( $_POST['ff_status_email'] ) ? sanitize_email( wp_unslash( $_POST['ff_status_email'] ) ) : '';

			if ( '' === $email || ! is_email( $email ) ) {
				$result_html = '<div class="ff-notice ff-notice--error">'
					. esc_html__( 'Please enter a valid email address.', 'founding-faces' )
					. '</div>';
			} else {
				$status      = self::status_for_email( $email );
				$result_html = self::status_message_for_status( $status, $email );
				// "Found" means we matched an application — a wrong email keeps
				// the form up so it can be corrected straight away.
				$found = ( null !== $status );
			}
		}

		// In the Elementor editor there is no submission to respond to, so show
		// the result notice and the "send it again" prompt as samples — they are
		// otherwise invisible and impossible to style.
		// The form is deliberately left in place alongside the sample, even with
		// "hide the form" switched on, so every element stays styleable at once.
		if ( '' === $result_html && ! empty( $atts['editor_preview'] ) ) {
			$result_html = self::sample_status_result();
		}

		$out = '<div class="ff-status">';

		if ( FF_Text::filled( $heading ) ) {
			$out .= '<h3 class="ff-status-heading">' . FF_Text::inline( $heading ) . '</h3>';
		}
		if ( FF_Text::filled( $intro ) ) {
			$out .= '<div class="ff-status-intro">' . wp_kses_post( wpautop( $intro ) ) . '</div>';
		}

		$out .= $result_html;

		// With "hide the form" on, a matched lookup replaces the form with a
		// link back — so the result stands alone, but is never a dead end.
		if ( $hide_on_found && $found ) {
			$out .= '<p class="ff-status-again"><a class="ff-status-again-link" href="'
				. esc_url( self::current_url() ) . '">' . FF_Text::inline( $again_label ) . '</a></p>';
			$out .= '</div>';
			return $out;
		}

		ob_start();
		?>
		<form class="<?php echo esc_attr( $form_class ); ?>" method="post" action="<?php echo esc_url( self::current_url() ); ?>" novalidate>
			<?php wp_nonce_field( 'ff_status_lookup', 'ff_status_nonce' ); ?>
			<p class="ff-field">
				<label for="ff-status-email"><?php echo FF_Text::inline( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
				<input type="email" id="ff-status-email" name="ff_status_email" value="<?php echo esc_attr( $email ); ?>"
					placeholder="<?php esc_attr_e( 'The email you applied with', 'founding-faces' ); ?>" required />
			</p>
			<p class="ff-submit">
				<button type="submit"><?php echo FF_Text::inline( $button_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</p>
		</form>
		<?php
		$out .= ob_get_clean();
		$out .= '</div>';

		return $out;
	}

	/**
	 * A sample status-lookup result, for the Elementor editor.
	 *
	 * The result notice, the "send it again" prompt and the button only ever
	 * appear after a real submission, so there would otherwise be nothing on
	 * screen to style them against. This renders the pending state in the exact
	 * live markup.
	 *
	 * @return string
	 */
	public static function sample_status_result() {
		return '<div class="ff-notice ff-notice--pending">'
			. esc_html__( 'Your application is being reviewed. We\'ll email you as soon as there\'s news — thank you for your patience.', 'founding-faces' )
			. '</div>'
			. self::resend_form( 'sample@example.com' );
	}

	/**
	 * The "send it again" form, shown under a decided application's message.
	 *
	 * The address is carried in a hidden field, so the email only ever goes to
	 * the address that was just looked up — which is the address on the
	 * application. Nothing about the decision is shown on screen.
	 *
	 * @param string $email The looked-up email.
	 * @return string
	 */
	private static function resend_form( $email ) {
		$out  = '<form class="ff-status-resend" method="post" action="' . esc_url( self::current_url() ) . '">';
		$out .= wp_nonce_field( 'ff_status_resend', 'ff_resend_nonce', true, false );
		$out .= '<input type="hidden" name="ff_status_email" value="' . esc_attr( $email ) . '" />';
		$out .= '<p class="ff-status-resend-hint">' . esc_html__( "Didn't receive it? Check your spam folder, or we can send it again.", 'founding-faces' ) . '</p>';
		$out .= '<button type="submit" class="ff-status-resend-button">' . esc_html__( 'Send it again', 'founding-faces' ) . '</button>';
		$out .= '</form>';
		return $out;
	}

	/**
	 * Re-send the decision email for an application, rate-limited.
	 *
	 * An approved member gets a fresh welcome email with a brand-new
	 * set-password link, which also solves an expired seven-day token. A
	 * declined applicant gets the decline email again. The reply is identical
	 * either way, so the lookup still never reveals the decision — and it is
	 * identical for an unknown address too, so this can't be used to test
	 * whether someone applied.
	 *
	 * @param string $email The email to re-send to.
	 * @return string The confirmation HTML.
	 */
	private static function resend_decision_email( $email ) {
		// The same reassuring reply in every case, so nothing is disclosed.
		$sent_notice = '<div class="ff-notice ff-notice--success">'
			. esc_html__( "If there's an application for that email address, we've just sent it again. Please allow a few minutes, and check your spam folder.", 'founding-faces' )
			. '</div>';

		if ( '' === $email || ! is_email( $email ) ) {
			return $sent_notice;
		}

		// Rate limit: one resend per address every fifteen minutes, so the
		// button can't be used to flood someone's inbox.
		$key = 'ff_resend_' . md5( strtolower( $email ) );
		if ( get_transient( $key ) ) {
			return '<div class="ff-notice">'
				. esc_html__( "We've already sent that very recently. Please give it a few minutes to arrive, and check your spam folder.", 'founding-faces' )
				. '</div>';
		}
		set_transient( $key, 1, 15 * MINUTE_IN_SECONDS );

		global $wpdb;
		$table = $wpdb->prefix . 'ff_applications';
		$app   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT 1", $email )
		);

		if ( ! $app ) {
			return $sent_notice;
		}

		if ( 'pending' === $app->status ) {
			// Nothing has been decided, so re-send the received confirmation.
			FF_Emails::send_application_received( $app->name, $app->email );
		} elseif ( 'declined' === $app->status ) {
			FF_Emails::send_decline( $app->name, $app->email );
		} elseif ( ! empty( $app->user_id ) ) {
			// Approved: a fresh welcome email with a brand-new set-password link.
			FF_Emails::send_welcome( (int) $app->user_id );
		}

		return $sent_notice;
	}

	/**
	 * The stored status for an email address, or null if there's no application.
	 *
	 * @param string $email The applicant's email address.
	 * @return string|null
	 */
	private static function status_for_email( $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ff_applications';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT 1",
				$email
			)
		);
	}

	/**
	 * The message for a looked-up status.
	 *
	 * A decided application (approved either way, or declined) is reported as
	 * "a decision has been made — check your email", so the lookup never
	 * delivers a cold rejection and never leaks the group. Because a decision
	 * email is now always sent, that instruction is true for everyone, and the
	 * "send it again" button covers the case where it never arrived.
	 *
	 * @param string|null $status The stored status, or null if not found.
	 * @param string      $email  The looked-up email.
	 * @return string The message HTML.
	 */
	private static function status_message_for_status( $status, $email ) {
		if ( null === $status ) {
			return '<div class="ff-notice">'
				. esc_html__( 'We couldn\'t find an application for that email address. Please check it and try again.', 'founding-faces' )
				. '</div>';
		}

		if ( 'pending' === $status ) {
			return '<div class="ff-notice ff-notice--pending">'
				. esc_html__( 'Your application is being reviewed. We\'ll email you as soon as there\'s news — thank you for your patience.', 'founding-faces' )
				. '</div>'
				. self::resend_form( $email );
		}

		// Anything else means the application has been decided.
		return '<div class="ff-notice ff-notice--decided">'
			. esc_html__( 'A decision has been made on your application. Please check your inbox (including spam) for an email from Apotheca.', 'founding-faces' )
			. '</div>'
			. self::resend_form( $email );
	}

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
