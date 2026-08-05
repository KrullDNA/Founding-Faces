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
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_line' ) )
		);
		register_setting(
			self::GROUP,
			FF_Emails::OPT_35_BODY,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_copy' ) )
		);
		register_setting(
			self::GROUP,
			FF_Emails::OPT_CIRCLE_SUBJECT,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_line' ) )
		);
		register_setting(
			self::GROUP,
			FF_Emails::OPT_CIRCLE_BODY,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize_copy' ) )
		);

		// Promotion (chosen for The 35) and application-received templates.
		register_setting( self::GROUP, FF_Emails::OPT_PROMO_SUBJECT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_line' ) ) );
		register_setting( self::GROUP, FF_Emails::OPT_PROMO_BODY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_copy' ) ) );
		register_setting( self::GROUP, FF_Emails::OPT_RECEIVED_SUBJECT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_line' ) ) );
		register_setting( self::GROUP, FF_Emails::OPT_RECEIVED_BODY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_copy' ) ) );
		register_setting( self::GROUP, FF_Emails::OPT_DECLINE_SUBJECT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_line' ) ) );
		register_setting( self::GROUP, FF_Emails::OPT_DECLINE_BODY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_copy' ) ) );

		// Branded email design options.
		register_setting( self::GROUP, FF_Email_Template::OPT_LOGO, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_LOGO_WIDTH, array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_ACCENT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_BG, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_BUTTON_BG, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_BUTTON_TEXT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_HEADING_BG, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_HEADING_TEXT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_FOOTER, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_small_print' ) ) );
		register_setting( self::GROUP, FF_Email_Template::OPT_DISCLAIMER, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_small_print' ) ) );

		// New-applications behaviour: hold for review, or auto-accept into Circle.
		register_setting( self::GROUP, FF_Application::OPT_AUTO_ACCEPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ) ) );

		// The page that hosts the members portal (used by message reply emails).
		register_setting( self::GROUP, FF_Messages::OPT_PORTAL_PAGE, array( 'sanitize_callback' => 'absint' ) );

		// The login/logout menu item and the Login widget.
		register_setting( self::GROUP, FF_Menu_Items::OPT_LOGIN_PAGE, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( self::GROUP, FF_Menu_Items::OPT_LOGIN_REDIRECT, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( self::GROUP, FF_Menu_Items::OPT_LOGOUT_REDIRECT, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( self::GROUP, FF_Menu_Items::OPT_LOGIN_LABEL, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::GROUP, FF_Menu_Items::OPT_LOGOUT_LABEL, array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Which connector is active (core). The connector add-on plugins register
		// their own API-key settings against this same group via 'admin_init'.
		register_setting(
			self::GROUP,
			FF_Connectors::OPT_ACTIVE,
			array( 'sanitize_callback' => 'sanitize_key' )
		);

		// Page-access: the page a logged-in but unauthorised visitor is sent to.
		register_setting(
			self::GROUP,
			FF_Page_Access::OPT_REDIRECT,
			array( 'sanitize_callback' => 'absint' )
		);

		// Members map settings: tile source, and per-tier dot colour and size.
		// The tile URL uses a custom sanitiser because esc_url_raw() strips the
		// {z}/{x}/{y} placeholders Leaflet needs.
		register_setting( self::GROUP, FF_Map::OPT_TILE_URL, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_tile_url' ) ) );
		register_setting( self::GROUP, FF_Map::OPT_TILE_ATTRIBUTION, array( 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting( self::GROUP, FF_Map::OPT_35_COLOR, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Map::OPT_35_SIZE, array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::GROUP, FF_Map::OPT_CIRCLE_COLOR, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_color' ) ) );
		register_setting( self::GROUP, FF_Map::OPT_CIRCLE_SIZE, array( 'sanitize_callback' => 'absint' ) );
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
		$spromo  = get_option( FF_Emails::OPT_PROMO_SUBJECT, FF_Emails::default_promo_subject() );
		$bpromo  = get_option( FF_Emails::OPT_PROMO_BODY, FF_Emails::default_promo_body() );
		$srecv   = get_option( FF_Emails::OPT_RECEIVED_SUBJECT, FF_Emails::default_received_subject() );
		$brecv   = get_option( FF_Emails::OPT_RECEIVED_BODY, FF_Emails::default_received_body() );
		$sdecl   = get_option( FF_Emails::OPT_DECLINE_SUBJECT, FF_Emails::default_decline_subject() );
		$bdecl   = get_option( FF_Emails::OPT_DECLINE_BODY, FF_Emails::default_decline_body() );
		?>
		<div class="wrap ff-admin">
			<h1><?php esc_html_e( 'Founding Faces Settings', 'founding-faces' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<?php self::render_applications_section(); ?>

				<?php self::render_email_platform_section(); ?>

				<?php self::render_email_design_section(); ?>

				<h2><?php esc_html_e( 'Welcome emails', 'founding-faces' ); ?></h2>
				<p class="description">
					<strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . FF_Admin_Emails::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'See every email as a member receives it →', 'founding-faces' ); ?></a></strong>
					<?php esc_html_e( 'Preview any of these, and send yourself a test, on the Emails screen.', 'founding-faces' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'You can use these placeholders in a subject or body:', 'founding-faces' ); ?>
					<code>{name}</code> <code>{number}</code> <code>{group}</code>
					<code>{public_name}</code> <code>{site_name}</code>
					<code>{login_url}</code> <code>{set_password_link}</code>
				</p>
				<p class="description">
					<?php esc_html_e( 'The two link placeholders are written out as a linked phrase, not as a pasted address, so write the sentence around them: "Use this secure link to {set_password_link}" reads as "Use this secure link to set your password", with the words carrying the link.', 'founding-faces' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'The secure "Set your password" button is added to the welcome emails automatically, so you no longer need to include {set_password_link} in the body (though it still works if you do).', 'founding-faces' ); ?>
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
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Promotion email (chosen for The 35)', 'founding-faces' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Sent when you promote a Circle member into The 35. They already have a password, so this has a "Sign in" button instead of a set-password link. Placeholders: {name} {number} {group} {public_name} {site_name} {login_url}.', 'founding-faces' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_PROMO_SUBJECT ); ?>"><?php esc_html_e( 'Subject', 'founding-faces' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( FF_Emails::OPT_PROMO_SUBJECT ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_PROMO_SUBJECT ); ?>"
								type="text" class="large-text" value="<?php echo esc_attr( $spromo ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_PROMO_BODY ); ?>"><?php esc_html_e( 'Body', 'founding-faces' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( FF_Emails::OPT_PROMO_BODY ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_PROMO_BODY ); ?>"
								rows="10" class="large-text code"><?php echo esc_textarea( $bpromo ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Application-received email', 'founding-faces' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Sent the moment someone submits the application, confirming it arrived, used while applications are held for manual review. (With auto-accept on, applicants get the Circle welcome email instead, so this is not also sent.) Placeholders: {name} {site_name}.', 'founding-faces' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_RECEIVED_SUBJECT ); ?>"><?php esc_html_e( 'Subject', 'founding-faces' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( FF_Emails::OPT_RECEIVED_SUBJECT ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_RECEIVED_SUBJECT ); ?>"
								type="text" class="large-text" value="<?php echo esc_attr( $srecv ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_RECEIVED_BODY ); ?>"><?php esc_html_e( 'Body', 'founding-faces' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( FF_Emails::OPT_RECEIVED_BODY ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_RECEIVED_BODY ); ?>"
								rows="8" class="large-text code"><?php echo esc_textarea( $brecv ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Declined-application email', 'founding-faces' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Sent when you decline an application, so a decision is never silence, and so the "check your inbox" message on the status lookup is true for everyone. Placeholders: {name} {site_name}.', 'founding-faces' ); ?>
					<br />
					<strong><?php esc_html_e( 'To decline silently, clear the body field entirely, nothing is then sent.', 'founding-faces' ); ?></strong>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_DECLINE_SUBJECT ); ?>"><?php esc_html_e( 'Subject', 'founding-faces' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( FF_Emails::OPT_DECLINE_SUBJECT ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_DECLINE_SUBJECT ); ?>"
								type="text" class="large-text" value="<?php echo esc_attr( $sdecl ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( FF_Emails::OPT_DECLINE_BODY ); ?>"><?php esc_html_e( 'Body', 'founding-faces' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( FF_Emails::OPT_DECLINE_BODY ); ?>"
								id="<?php echo esc_attr( FF_Emails::OPT_DECLINE_BODY ); ?>"
								rows="10" class="large-text code"><?php echo esc_textarea( $bdecl ); ?></textarea>
						</td>
					</tr>
				</table>

				<?php self::render_access_section(); ?>

				<?php self::render_map_section(); ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitise a map tile URL while preserving Leaflet's {…} placeholders.
	 *
	 * esc_url_raw() strips characters like { and }, which would destroy the
	 * {z}/{x}/{y}/{s}/{r} tokens Leaflet needs. So the tokens are protected,
	 * the URL is sanitised, then the tokens are restored.
	 *
	 * @param string $url The submitted tile URL.
	 * @return string
	 */
	/**
	 * Normalise a checkbox to '1' (on) or '' (off).
	 *
	 * @param mixed $value The submitted value.
	 * @return string
	 */
	/**
	 * Normalise a colour, forgiving a missing hash.
	 *
	 * sanitize_hex_color() rejects "b8a389" outright, and a rejected colour is
	 * saved as nothing, which reads back as the shipped default. The colour
	 * looks like it was never set and there is nothing on screen to say why. A
	 * hex with the hash left off is obviously a colour, so it is treated as one.
	 *
	 * @param mixed $value The submitted colour.
	 * @return string A valid hex colour, or an empty string.
	 */
	public static function sanitize_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( '#' !== substr( $value, 0, 1 ) && preg_match( '/^[0-9a-fA-F]{3,6}$/', $value ) ) {
			$value = '#' . $value;
		}

		$hex = sanitize_hex_color( $value );

		return ( null === $hex ) ? '' : $hex;
	}

	/**
	 * A subject line or other single line of author copy.
	 *
	 * @param mixed $value The submitted text.
	 * @return string
	 */
	public static function sanitize_line( $value ) {
		return FF_Text::no_em_dash( sanitize_text_field( $value ) );
	}

	/**
	 * An email body, which may carry light HTML.
	 *
	 * @param mixed $value The submitted text.
	 * @return string
	 */
	public static function sanitize_copy( $value ) {
		return FF_Text::no_em_dash( wp_kses_post( $value ) );
	}

	/**
	 * The footer line and the small print, which are plain text.
	 *
	 * @param mixed $value The submitted text.
	 * @return string
	 */
	public static function sanitize_small_print( $value ) {
		return FF_Text::no_em_dash( sanitize_textarea_field( $value ) );
	}

	public static function sanitize_checkbox( $value ) {
		return ( '1' === $value || 1 === $value || true === $value || 'on' === $value ) ? '1' : '';
	}

	public static function sanitize_tile_url( $url ) {
		$url = trim( wp_strip_all_tags( (string) $url ) );
		if ( '' === $url ) {
			return '';
		}

		$tokens  = array( '{z}', '{x}', '{y}', '{s}', '{r}', '{ratio}' );
		$protect = array();
		foreach ( $tokens as $i => $token ) {
			$protect[ $token ] = '__FFTILE' . $i . '__';
		}

		$safe = strtr( $url, $protect );      // Hide the placeholders.
		$safe = esc_url_raw( $safe );          // Sanitise the rest.
		$safe = strtr( $safe, array_flip( $protect ) ); // Restore the placeholders.

		return $safe;
	}

	/**
	 * Render the members-access section of the settings form.
	 *
	 * Just the optional page a logged-in but unauthorised visitor is redirected
	 * to (the per-page access level itself is set on each page).
	 */
	private static function render_access_section() {
		$redirect = (int) get_option( FF_Page_Access::OPT_REDIRECT, 0 );
		?>
		<h2><?php esc_html_e( 'Members access', 'founding-faces' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Set who can view each page on the page itself, in the "Founding Faces Access" box. This is only the page a logged-in member is sent to when they open a page their group can\'t access.', 'founding-faces' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Page_Access::OPT_REDIRECT ); ?>"><?php esc_html_e( 'Restricted page redirect', 'founding-faces' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages( array(
						'name'              => FF_Page_Access::OPT_REDIRECT,
						'id'                => FF_Page_Access::OPT_REDIRECT,
						'selected'          => $redirect,
						'show_option_none'  => __( 'Home page', 'founding-faces' ),
						'option_none_value' => 0,
					) );
					?>
					<p class="description"><?php esc_html_e( 'Logged-out visitors always go to the login page and back. This only affects logged-in members in the wrong group.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Messages::OPT_PORTAL_PAGE ); ?>"><?php esc_html_e( 'Members hub / portal page', 'founding-faces' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages( array(
						'name'              => FF_Messages::OPT_PORTAL_PAGE,
						'id'                => FF_Messages::OPT_PORTAL_PAGE,
						'selected'          => (int) get_option( FF_Messages::OPT_PORTAL_PAGE, 0 ),
						'show_option_none'  => __( 'Home page', 'founding-faces' ),
						'option_none_value' => 0,
					) );
					?>
					<p class="description"><?php esc_html_e( 'Your members\' home / hub page. Members are sent here when they log in, and the "Open my portal" button in message-reply emails points here too. Stored as a page, so it resolves to the correct address on staging and live automatically.', 'founding-faces' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Log in and log out', 'founding-faces' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Used by the "Log in / Log out" menu item (set it per item in Appearance → Menus), by the Founding Faces Login widget, and by every redirect that sends a logged-out visitor away from restricted content.', 'founding-faces' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_PAGE ); ?>"><?php esc_html_e( 'Login page URL', 'founding-faces' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_PAGE ); ?>"
						name="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_PAGE ); ?>"
						value="<?php echo esc_attr( get_option( FF_Menu_Items::OPT_LOGIN_PAGE, '' ) ); ?>"
						placeholder="<?php echo esc_attr( home_url( '/login/' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'The page holding your Founding Faces Login widget. Used everywhere the plugin asks someone to log in, the menu item, the login widget, and anyone turned away from a restricted page or a gated note. Leave empty to use the standard WordPress login screen.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_REDIRECT ); ?>"><?php esc_html_e( 'After login, go to', 'founding-faces' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_REDIRECT ); ?>"
						name="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_REDIRECT ); ?>"
						value="<?php echo esc_attr( get_option( FF_Menu_Items::OPT_LOGIN_REDIRECT, '' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty to use the members hub page set above, recommended, since that follows the site between staging and live.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Menu_Items::OPT_LOGOUT_REDIRECT ); ?>"><?php esc_html_e( 'After logout, go to', 'founding-faces' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="<?php echo esc_attr( FF_Menu_Items::OPT_LOGOUT_REDIRECT ); ?>"
						name="<?php echo esc_attr( FF_Menu_Items::OPT_LOGOUT_REDIRECT ); ?>"
						value="<?php echo esc_attr( get_option( FF_Menu_Items::OPT_LOGOUT_REDIRECT, '' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave empty for the home page.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_LABEL ); ?>"><?php esc_html_e( 'Menu labels', 'founding-faces' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_LABEL ); ?>"
						name="<?php echo esc_attr( FF_Menu_Items::OPT_LOGIN_LABEL ); ?>"
						value="<?php echo esc_attr( get_option( FF_Menu_Items::OPT_LOGIN_LABEL, '' ) ); ?>"
						placeholder="<?php esc_attr_e( 'Log in', 'founding-faces' ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown when logged out.', 'founding-faces' ); ?></p>
					<input type="text" class="regular-text" style="margin-top:8px;" id="<?php echo esc_attr( FF_Menu_Items::OPT_LOGOUT_LABEL ); ?>"
						name="<?php echo esc_attr( FF_Menu_Items::OPT_LOGOUT_LABEL ); ?>"
						value="<?php echo esc_attr( get_option( FF_Menu_Items::OPT_LOGOUT_LABEL, '' ) ); ?>"
						placeholder="<?php esc_attr_e( 'Log out', 'founding-faces' ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown when logged in.', 'founding-faces' ); ?></p>
				</td>
			</tr>
		</table>

		<?php
	}

	/**
	 * Render the "New applications" section: hold for review, or auto-accept.
	 *
	 * A hidden field of the same name ships an empty value, so unticking the box
	 * reliably saves "off" (an unchecked checkbox otherwise sends nothing).
	 */
	private static function render_applications_section() {
		$auto = FF_Application::auto_accept_enabled();
		?>
		<h2><?php esc_html_e( 'New applications', 'founding-faces' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic acceptance', 'founding-faces' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( FF_Application::OPT_AUTO_ACCEPT ); ?>" value="" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( FF_Application::OPT_AUTO_ACCEPT ); ?>"
							value="1" <?php checked( $auto ); ?> />
						<?php esc_html_e( 'Automatically accept new applicants into The Circle', 'founding-faces' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Leave this OFF while you are choosing The 35, so every application waits for your review. Turn it ON once The 35 is chosen: new valid applications then become Circle members instantly and get the Circle welcome email, with no clicks from you. You can still promote a Circle member into The 35 at any time. The application form\'s spam trap protects this, bot submissions never become members.', 'founding-faces' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the branded-email design section (logo, colours, footer).
	 */
	private static function render_email_design_section() {
		$logo     = FF_Email_Template::option( FF_Email_Template::OPT_LOGO );
		$logo_w   = FF_Email_Template::option( FF_Email_Template::OPT_LOGO_WIDTH );
		$accent   = FF_Email_Template::option( FF_Email_Template::OPT_ACCENT );
		$head_bg  = FF_Email_Template::option( FF_Email_Template::OPT_HEADING_BG );
		$head_txt = FF_Email_Template::option( FF_Email_Template::OPT_HEADING_TEXT );
		$bg       = FF_Email_Template::option( FF_Email_Template::OPT_BG );
		$btn_bg   = FF_Email_Template::option( FF_Email_Template::OPT_BUTTON_BG );
		$btn_txt  = FF_Email_Template::option( FF_Email_Template::OPT_BUTTON_TEXT );
		$footer   = FF_Email_Template::option( FF_Email_Template::OPT_FOOTER );
		$small    = FF_Email_Template::option( FF_Email_Template::OPT_DISCLAIMER );
		?>
		<h2><?php esc_html_e( 'Email design', 'founding-faces' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The look applied to every programme email, welcome, promotion, password reset and application received. Set it once here.', 'founding-faces' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Email_Template::OPT_LOGO ); ?>"><?php esc_html_e( 'Logo URL', 'founding-faces' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( FF_Email_Template::OPT_LOGO ); ?>" id="<?php echo esc_attr( FF_Email_Template::OPT_LOGO ); ?>" type="url" class="large-text code" value="<?php echo esc_attr( $logo ); ?>" placeholder="https://…/logo.png" />
					<p class="description"><?php esc_html_e( 'Paste the full URL of your logo image (upload it in Media, then copy its file URL). It sits above the card, centred. Leave blank for no logo.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Email_Template::OPT_LOGO_WIDTH ); ?>"><?php esc_html_e( 'Logo width', 'founding-faces' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( FF_Email_Template::OPT_LOGO_WIDTH ); ?>" id="<?php echo esc_attr( FF_Email_Template::OPT_LOGO_WIDTH ); ?>" type="number" min="40" max="600" step="1" class="small-text" value="<?php echo esc_attr( $logo_w ); ?>" /> px
					<p class="description">
						<?php esc_html_e( 'How wide the logo is drawn. The card is 600px, so 200 to 300 is usually right. On a narrow phone it shrinks to fit rather than overflowing. Upload the file at roughly twice this width so it stays sharp on a retina screen.', 'founding-faces' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Colours', 'founding-faces' ); ?></th>
				<td>
					<p class="description" style="margin:0 0 0.8rem;">
						<?php esc_html_e( 'Where each one lands. Heading band and Heading text are the coloured strip across the top of the card and the words on it. Links is any link in the body. Page background is the area around the card, behind the logo and the small print. Button is the fill behind the call to action, and Button text the words on it. Hex codes, with or without the #.', 'founding-faces' ); ?>
					</p>
					<label style="display:inline-block; margin:0 1.2rem 0.6rem 0;"><?php esc_html_e( 'Heading band', 'founding-faces' ); ?><br />
						<input name="<?php echo esc_attr( FF_Email_Template::OPT_HEADING_BG ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $head_bg ); ?>" placeholder="#2b2d33" /></label>
					<label style="display:inline-block; margin:0 1.2rem 0.6rem 0;"><?php esc_html_e( 'Heading text', 'founding-faces' ); ?><br />
						<input name="<?php echo esc_attr( FF_Email_Template::OPT_HEADING_TEXT ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $head_txt ); ?>" placeholder="#ffffff" /></label>
					<label style="display:inline-block; margin:0 1.2rem 0.6rem 0;"><?php esc_html_e( 'Links', 'founding-faces' ); ?><br />
						<input name="<?php echo esc_attr( FF_Email_Template::OPT_ACCENT ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $accent ); ?>" placeholder="#2b2d33" /></label>
					<label style="display:inline-block; margin:0 1.2rem 0.6rem 0;"><?php esc_html_e( 'Page background', 'founding-faces' ); ?><br />
						<input name="<?php echo esc_attr( FF_Email_Template::OPT_BG ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $bg ); ?>" placeholder="#f6f7f8" /></label>
					<label style="display:inline-block; margin:0 1.2rem 0.6rem 0;"><?php esc_html_e( 'Button', 'founding-faces' ); ?><br />
						<input name="<?php echo esc_attr( FF_Email_Template::OPT_BUTTON_BG ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $btn_bg ); ?>" placeholder="#3a3d44" /></label>
					<label style="display:inline-block; margin:0 1.2rem 0.6rem 0;"><?php esc_html_e( 'Button text', 'founding-faces' ); ?><br />
						<input name="<?php echo esc_attr( FF_Email_Template::OPT_BUTTON_TEXT ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $btn_txt ); ?>" placeholder="#ffffff" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Email_Template::OPT_FOOTER ); ?>"><?php esc_html_e( 'Footer text', 'founding-faces' ); ?></label></th>
				<td>
					<textarea name="<?php echo esc_attr( FF_Email_Template::OPT_FOOTER ); ?>" id="<?php echo esc_attr( FF_Email_Template::OPT_FOOTER ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $footer ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Sits centred inside the card, under the message, e.g. "© Apotheca®". Line breaks are kept.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Email_Template::OPT_DISCLAIMER ); ?>"><?php esc_html_e( 'Small print', 'founding-faces' ); ?></label></th>
				<td>
					<textarea name="<?php echo esc_attr( FF_Email_Template::OPT_DISCLAIMER ); ?>" id="<?php echo esc_attr( FF_Email_Template::OPT_DISCLAIMER ); ?>" rows="5" class="large-text"><?php echo esc_textarea( $small ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'The disclaimer below the card, outside it, in small grey type. Use {site_name} rather than typing the name, so it follows the site between staging and live. Clear the field to leave it off entirely.', 'founding-faces' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'The Unsubscribe link sits under this, on the welcome and promotion emails. It is not offered on the password reset (that one was asked for and has to arrive) or on the application emails, where there is no account and so no list to leave. One click takes the member off the list here and on your email platform, leaves their account, number and history untouched, and emails you so you can follow it up if it is one of The 35.', 'founding-faces' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the members-map section of the settings form.
	 *
	 * The tile source is a single field so the map can be repointed to another
	 * provider later without any code change; plus per-tier dot colour and size.
	 */
	private static function render_map_section() {
		$s = FF_Map::settings();
		?>
		<h2><?php esc_html_e( 'Members map', 'founding-faces' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The map places one anonymous dot per member from their postcode. It never reads the postal address, and shows no names, labels or clickable dots.', 'founding-faces' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Map::OPT_TILE_URL ); ?>"><?php esc_html_e( 'Base map tile URL', 'founding-faces' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( FF_Map::OPT_TILE_URL ); ?>" id="<?php echo esc_attr( FF_Map::OPT_TILE_URL ); ?>" type="text" class="large-text code" value="<?php echo esc_attr( $s['tile_url'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Defaults to the pale grey Positron style (no key). Set once here; repoint to another provider (e.g. Stadia) later without touching code.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( FF_Map::OPT_TILE_ATTRIBUTION ); ?>"><?php esc_html_e( 'Tile attribution', 'founding-faces' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( FF_Map::OPT_TILE_ATTRIBUTION ); ?>" id="<?php echo esc_attr( FF_Map::OPT_TILE_ATTRIBUTION ); ?>" type="text" class="large-text" value="<?php echo esc_attr( $s['attribution'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The 35 dots', 'founding-faces' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Colour', 'founding-faces' ); ?>
						<input name="<?php echo esc_attr( FF_Map::OPT_35_COLOR ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $s['c35_color'] ); ?>" placeholder="#2b2d33" /></label>
					&nbsp;&nbsp;
					<label><?php esc_html_e( 'Size (px)', 'founding-faces' ); ?>
						<input name="<?php echo esc_attr( FF_Map::OPT_35_SIZE ); ?>" type="number" min="2" max="30" value="<?php echo esc_attr( $s['c35_size'] ); ?>" style="width:70px;" /></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'The Circle dots', 'founding-faces' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Colour', 'founding-faces' ); ?>
						<input name="<?php echo esc_attr( FF_Map::OPT_CIRCLE_COLOR ); ?>" type="text" class="ff-color" value="<?php echo esc_attr( $s['circle_color'] ); ?>" placeholder="#9aa0a6" /></label>
					&nbsp;&nbsp;
					<label><?php esc_html_e( 'Size (px)', 'founding-faces' ); ?>
						<input name="<?php echo esc_attr( FF_Map::OPT_CIRCLE_SIZE ); ?>" type="number" min="2" max="30" value="<?php echo esc_attr( $s['circle_size'] ); ?>" style="width:70px;" /></label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the email-platform section of the settings form.
	 *
	 * The core owns the "active platform" choice and shows the configured
	 * status and last sync error. Each installed connector add-on prints its own
	 * API-key fields via the 'ff_settings_connectors' hook, so the core never
	 * references a connector that may not be installed.
	 */
	private static function render_email_platform_section() {
		$active     = get_option( FF_Connectors::OPT_ACTIVE, '' );
		$available  = FF_Connectors::available();
		$connector  = FF_Connectors::get_active();
		$configured = $connector ? $connector->is_configured() : false;
		$last_error = get_option( FF_Connectors::OPT_LAST_ERROR );
		?>
		<h2><?php esc_html_e( 'Email platform', 'founding-faces' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'On approval, a consented member is synced to the active platform with their name, email, group and (for The 35) number. Nothing is ever synced without stored consent.', 'founding-faces' ); ?>
		</p>

		<?php if ( empty( $available ) ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'No connector add-on is installed yet. Install and activate the Campaign Monitor (or Klaviyo) add-on plugin to sync members to your email platform.', 'founding-faces' ); ?>
			</p></div>
		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( FF_Connectors::OPT_ACTIVE ); ?>"><?php esc_html_e( 'Active platform', 'founding-faces' ); ?></label></th>
					<td>
						<select name="<?php echo esc_attr( FF_Connectors::OPT_ACTIVE ); ?>" id="<?php echo esc_attr( FF_Connectors::OPT_ACTIVE ); ?>">
							<?php foreach ( $available as $id => $conn ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $active, $id ); ?>>
									<?php echo esc_html( $conn->get_label() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php
							if ( $configured ) {
								echo '<span style="color:#1e5631;font-weight:600;">' . esc_html__( 'Configured and ready.', 'founding-faces' ) . '</span>';
							} else {
								echo '<span style="color:#8a1f1f;font-weight:600;">' . esc_html__( 'Not configured yet, add the API key below.', 'founding-faces' ) . '</span>';
							}
							?>
						</p>
					</td>
				</tr>
			</table>

			<?php
			/**
			 * Each installed connector add-on prints its own key fields here.
			 */
			do_action( 'ff_settings_connectors' );
			?>
		<?php endif; ?>

		<?php if ( is_array( $last_error ) && ! empty( $last_error['message'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'Last sync error:', 'founding-faces' ); ?></strong>
					<?php echo esc_html( $last_error['message'] ); ?>
					<?php if ( ! empty( $last_error['email'] ) ) : ?>
						(<?php echo esc_html( $last_error['email'] ); ?>)
					<?php endif; ?>
					<?php if ( ! empty( $last_error['time'] ) ) : ?>
						at <?php echo esc_html( $last_error['time'] ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}
}
