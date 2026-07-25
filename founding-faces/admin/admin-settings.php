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

		// Which connector is active (core). The connector add-on plugins register
		// their own API-key settings against this same group via 'admin_init'.
		register_setting(
			self::GROUP,
			FF_Connectors::OPT_ACTIVE,
			array( 'sanitize_callback' => 'sanitize_key' )
		);

		// Members map settings: tile source, and per-tier dot colour and size.
		register_setting( self::GROUP, FF_Map::OPT_TILE_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( self::GROUP, FF_Map::OPT_TILE_ATTRIBUTION, array( 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting( self::GROUP, FF_Map::OPT_35_COLOR, array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( self::GROUP, FF_Map::OPT_35_SIZE, array( 'sanitize_callback' => 'absint' ) );
		register_setting( self::GROUP, FF_Map::OPT_CIRCLE_COLOR, array( 'sanitize_callback' => 'sanitize_hex_color' ) );
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
		?>
		<div class="wrap ff-admin">
			<h1><?php esc_html_e( 'Founding Faces — Settings', 'founding-faces' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<?php self::render_email_platform_section(); ?>

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

				<?php self::render_map_section(); ?>

				<?php submit_button(); ?>
			</form>
		</div>
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
								echo '<span style="color:#8a1f1f;font-weight:600;">' . esc_html__( 'Not configured yet — add the API key below.', 'founding-faces' ) . '</span>';
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
						— <?php echo esc_html( $last_error['time'] ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}
}
