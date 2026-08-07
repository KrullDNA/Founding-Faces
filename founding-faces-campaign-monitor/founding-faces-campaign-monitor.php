<?php
/**
 * Plugin Name:       Founding Faces, Campaign Monitor
 * Description:        Campaign Monitor connector add-on for the Founding Faces membership plugin. Syncs approved, consented members (name, email, group and number) to a Campaign Monitor list. Requires the Founding Faces core plugin.
 * Version:           1.1.3
 * Requires PHP:      7.4
 * Author:            KDNA for Apotheca
 * Text Domain:       founding-faces
 * License:           GPL-2.0-or-later
 *
 * @package FoundingFaces\CampaignMonitor
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Campaign Monitor connector with the core.
 *
 * Hooks 'ff_register_connectors', which the core fires once its connector
 * interface is loaded, so the class (which extends FF_Connector) is only
 * defined when that parent exists. If the core plugin isn't active, this add-on
 * quietly does nothing.
 */
add_action( 'ff_register_connectors', 'ff_cm_register_connector' );
function ff_cm_register_connector() {
	if ( ! class_exists( 'FF_Connector' ) || ! class_exists( 'FF_Connectors' ) ) {
		return;
	}
	require_once __DIR__ . '/class-ff-cm-connector.php';
	FF_Connectors::add( new FF_CM_Connector() );
}

/**
 * Register the Campaign Monitor settings against the core's settings group.
 */
add_action( 'admin_init', 'ff_cm_register_settings' );
function ff_cm_register_settings() {
	if ( ! class_exists( 'FF_Settings' ) || ! class_exists( 'FF_CM_Connector' ) ) {
		return;
	}
	register_setting( FF_Settings::GROUP, FF_CM_Connector::OPT_API_KEY, array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( FF_Settings::GROUP, FF_CM_Connector::OPT_LIST_ID, array( 'sanitize_callback' => 'sanitize_text_field' ) );
}

/**
 * Print the Campaign Monitor key fields into the core settings page.
 */
add_action( 'ff_settings_connectors', 'ff_cm_render_settings' );
function ff_cm_render_settings() {
	if ( ! class_exists( 'FF_CM_Connector' ) ) {
		return;
	}
	$api_key = get_option( FF_CM_Connector::OPT_API_KEY, '' );
	$list_id = get_option( FF_CM_Connector::OPT_LIST_ID, '' );
	?>
	<h3><?php esc_html_e( 'Campaign Monitor', 'founding-faces' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( FF_CM_Connector::OPT_API_KEY ); ?>"><?php esc_html_e( 'Campaign Monitor API key', 'founding-faces' ); ?></label></th>
			<td>
				<input name="<?php echo esc_attr( FF_CM_Connector::OPT_API_KEY ); ?>"
					id="<?php echo esc_attr( FF_CM_Connector::OPT_API_KEY ); ?>"
					type="password" class="regular-text" autocomplete="off"
					value="<?php echo esc_attr( $api_key ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( FF_CM_Connector::OPT_LIST_ID ); ?>"><?php esc_html_e( 'Campaign Monitor list ID', 'founding-faces' ); ?></label></th>
			<td>
				<input name="<?php echo esc_attr( FF_CM_Connector::OPT_LIST_ID ); ?>"
					id="<?php echo esc_attr( FF_CM_Connector::OPT_LIST_ID ); ?>"
					type="text" class="regular-text"
					value="<?php echo esc_attr( $list_id ); ?>" />
				<p class="description"><?php esc_html_e( 'Every custom field this plugin uses is created on this list automatically: Group, Number, Status, DisplayPreference, ApplicationDate, Postcode, Tags, and the engagement counts.', 'founding-faces' ); ?></p>
			</td>
		</tr>
	</table>

	<?php
	$trimmed = get_option( FF_CM_Connector::OPT_TAGS_TRIMMED, array() );
	if ( is_array( $trimmed ) && ! empty( $trimmed['count'] ) ) :
		?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'A member has more tags than Campaign Monitor can hold.', 'founding-faces' ); ?></strong>
				<?php
				printf(
					/* translators: 1: how many tags were dropped, 2: a date and time. */
					esc_html__( 'Campaign Monitor keeps all of a member\'s tags in one 250-character field, and %1$d had to be left out on the sync at %2$s. The oldest poll tags go first and anything typed by hand is kept.', 'founding-faces' ),
					(int) $trimmed['count'],
					esc_html( isset( $trimmed['time'] ) ? $trimmed['time'] : '' )
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'Nothing is lost in WordPress, which holds the full list. If you are segmenting on how much someone takes part rather than on one particular poll, use the PollsVoted, LastVoted, FeedbackCount and NotesRead fields instead: they count without ever filling up. Shorter tags on your polls buy back a lot of room too.', 'founding-faces' ); ?>
			</p>
		</div>
		<?php
	endif;
}

/**
 * Show a notice if the core plugin isn't active, so it's obvious why the
 * add-on does nothing.
 */
add_action( 'admin_notices', 'ff_cm_core_notice' );
function ff_cm_core_notice() {
	if ( class_exists( 'FF_Connectors' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>'
		. esc_html__( 'Founding Faces, Campaign Monitor needs the Founding Faces core plugin to be active.', 'founding-faces' )
		. '</p></div>';
}
