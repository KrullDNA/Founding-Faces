<?php
/**
 * Plugin Name:       Founding Faces — Klaviyo
 * Description:        Klaviyo connector add-on for the Founding Faces membership plugin. Syncs approved, consented members (name, email, group and number) to Klaviyo, with the group as both a tag and a profile property. Requires the Founding Faces core plugin.
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Author:            KDNA for Apotheca
 * Text Domain:       founding-faces
 * License:           GPL-2.0-or-later
 *
 * @package FoundingFaces\Klaviyo
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Klaviyo connector with the core.
 */
add_action( 'ff_register_connectors', 'ff_klaviyo_register_connector' );
function ff_klaviyo_register_connector() {
	if ( ! class_exists( 'FF_Connector' ) || ! class_exists( 'FF_Connectors' ) ) {
		return;
	}
	require_once __DIR__ . '/class-ff-klaviyo-connector.php';
	FF_Connectors::add( new FF_Klaviyo_Connector() );
}

/**
 * Register the Klaviyo settings against the core's settings group.
 */
add_action( 'admin_init', 'ff_klaviyo_register_settings' );
function ff_klaviyo_register_settings() {
	if ( ! class_exists( 'FF_Settings' ) || ! class_exists( 'FF_Klaviyo_Connector' ) ) {
		return;
	}
	register_setting( FF_Settings::GROUP, FF_Klaviyo_Connector::OPT_API_KEY, array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( FF_Settings::GROUP, FF_Klaviyo_Connector::OPT_LIST_ID, array( 'sanitize_callback' => 'sanitize_text_field' ) );
}

/**
 * Print the Klaviyo key fields into the core settings page.
 */
add_action( 'ff_settings_connectors', 'ff_klaviyo_render_settings' );
function ff_klaviyo_render_settings() {
	if ( ! class_exists( 'FF_Klaviyo_Connector' ) ) {
		return;
	}
	$key  = get_option( FF_Klaviyo_Connector::OPT_API_KEY, '' );
	$list = get_option( FF_Klaviyo_Connector::OPT_LIST_ID, '' );
	?>
	<h3><?php esc_html_e( 'Klaviyo', 'founding-faces' ); ?></h3>
	<p class="description"><?php esc_html_e( 'The group is sent as both a tag and a profile property, alongside name, email and number.', 'founding-faces' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( FF_Klaviyo_Connector::OPT_API_KEY ); ?>"><?php esc_html_e( 'Klaviyo private API key', 'founding-faces' ); ?></label></th>
			<td>
				<input name="<?php echo esc_attr( FF_Klaviyo_Connector::OPT_API_KEY ); ?>"
					id="<?php echo esc_attr( FF_Klaviyo_Connector::OPT_API_KEY ); ?>"
					type="password" class="regular-text" autocomplete="off"
					value="<?php echo esc_attr( $key ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( FF_Klaviyo_Connector::OPT_LIST_ID ); ?>"><?php esc_html_e( 'Klaviyo list ID (optional)', 'founding-faces' ); ?></label></th>
			<td>
				<input name="<?php echo esc_attr( FF_Klaviyo_Connector::OPT_LIST_ID ); ?>"
					id="<?php echo esc_attr( FF_Klaviyo_Connector::OPT_LIST_ID ); ?>"
					type="text" class="regular-text"
					value="<?php echo esc_attr( $list ); ?>" />
				<p class="description"><?php esc_html_e( 'If set, approved members are also added to this Klaviyo list.', 'founding-faces' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Show a notice if the core plugin isn't active.
 */
add_action( 'admin_notices', 'ff_klaviyo_core_notice' );
function ff_klaviyo_core_notice() {
	if ( class_exists( 'FF_Connectors' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>'
		. esc_html__( 'Founding Faces — Klaviyo needs the Founding Faces core plugin to be active.', 'founding-faces' )
		. '</p></div>';
}
