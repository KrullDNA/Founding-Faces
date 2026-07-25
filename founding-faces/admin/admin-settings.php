<?php
/**
 * The plugin settings screen.
 *
 * Stage 4 introduces this page with the editable welcome-email templates for
 * The 35 and The Circle. Later stages add the email-platform API keys and the
 * numbering controls to the same page.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Settings
 *
 * Registers the settings, their sanitisation, and the Settings screen under
 * the Founding Faces menu.
 */
class FF_Settings {

	// The settings group name and page slug.
	const GROUP     = 'ff_settings';
	const PAGE_SLUG = 'founding-faces-settings';

	/**
	 * Wire up the settings menu and the registered settings.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add the Settings submenu under the Founding Faces menu.
	 */
	public static function add_menu() {
		add_submenu_page(
			FF_Admin_Applications::PAGE_SLUG,
			__( 'Founding Faces Settings', 'founding-faces' ),
			__( 'Settings', 'founding-faces' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the four email-template settings with their sanitisers.
	 *
	 * Subjects are treated as plain text; bodies allow safe HTML so Nick can add
	 * light formatting if he wants.
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			FF_Emails::OPT_35_SUBJECT,
			array( 'sanitize_callback' => 'sanitize_text_field' )
		);
		register_setting(
			self::GROUP,
			FF_Emails::OPT_35_BODY,
			array( 'sanitize_callback' => 'wp_kses_post' )
		);
		register_setting(
			self::GROUP,
			FF_Emails::OPT_CIRCLE_SUBJECT,
			array( 'sanitize_callback' => 'sanitize_text_field' )
		);
		register_setting(
			self::GROUP,
			FF_Emails::OPT_CIRCLE_BODY,
			array( 'sanitize_callback' => 'wp_kses_post' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * A standard Settings-API form: it saves to options.php, which handles the
	 * nonce and capability checks for us.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Current values, falling back to the shipped defaults.
		$s35    = get_option( FF_Emails::OPT_35_SUBJECT, FF_Emails::default_subject( true ) );
		$b35    = get_option( FF_Emails::OPT_35_BODY, FF_Emails::default_body( true ) );
		$scircle = get_option( FF_Emails::OPT_CIRCLE_SUBJECT, FF_Emails::default_subject( false ) );
		$bcircle = get_option( FF_Emails::OPT_CIRCLE_BODY, FF_Emails::default_body( false ) );
		?>
		<div class="wrap ff-admin">
			<h1><?php esc_html_e( 'Founding Faces — Settings', 'founding-faces' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Welcome emails', 'founding-faces' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'You can use these placeholders in a subject or body:', 'founding-faces' ); ?>
					<code>{name}</code> <code>{number}</code> <code>{group}</code>
					<code>{public_name}</code> <code>{site_name}</code>
					<code>{login_url}</code> <code>{set_password_link}</code>
				</p>

				<h3><?php esc_html_e( 'The 35', 'founding-faces' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_35_SUBJECT ); ?>"><?php esc_html_e( 'Subject', 'founding-faces' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( FF_Emails::OPT_35_SUBJECT ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_35_SUBJECT ); ?>"
								type="text" class="large-text" value="<?php echo esc_attr( $s35 ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_35_BODY ); ?>"><?php esc_html_e( 'Body', 'founding-faces' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( FF_Emails::OPT_35_BODY ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_35_BODY ); ?>"
								rows="10" class="large-text code"><?php echo esc_textarea( $b35 ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Make sure this includes {set_password_link} so the member can set their password.', 'founding-faces' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'The Circle', 'founding-faces' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_CIRCLE_SUBJECT ); ?>"><?php esc_html_e( 'Subject', 'founding-faces' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( FF_Emails::OPT_CIRCLE_SUBJECT ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_CIRCLE_SUBJECT ); ?>"
								type="text" class="large-text" value="<?php echo esc_attr( $scircle ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_CIRCLE_BODY ); ?>"><?php esc_html_e( 'Body', 'founding-faces' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( FF_Emails::OPT_CIRCLE_BODY ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_CIRCLE_BODY ); ?>"
								rows="10" class="large-text code"><?php echo esc_textarea( $bcircle ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Make sure this includes {set_password_link} so the member can set their password.', 'founding-faces' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
