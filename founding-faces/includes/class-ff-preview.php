<?php
/**
 * Admin "view as a member" preview.
 *
 * For testing: an administrator can view the whole site as a member of The 35 or
 * The Circle, so they see exactly what that group sees, gated notes, polls,
 * pages and menu items included, and are blocked from the other tier's content
 * just as a real member would be. It only ever affects the administrator who
 * turns it on, never a real member, and is set from the admin's own profile or
 * the admin-bar switcher.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Preview
 */
class FF_Preview {

	/**
	 * Wire up the profile field, its save, the admin-bar switcher and the
	 * quick-switch handler.
	 */
	public static function register() {
		add_action( 'show_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
		add_action( 'init', array( __CLASS__, 'maybe_switch' ) );
		add_action( 'admin_notices', array( __CLASS__, 'switch_notice' ) );
	}

	/**
	 * The preview options.
	 *
	 * @return array
	 */
	private static function options() {
		return array(
			''           => __( 'Administrator (see everything)', 'founding-faces' ),
			'the-35'     => __( 'The 35', 'founding-faces' ),
			'the-circle' => __( 'The Circle', 'founding-faces' ),
		);
	}

	/**
	 * Render the preview field on the admin's own profile screen.
	 *
	 * @param WP_User $user The user being edited.
	 */
	public static function profile_field( $user ) {
		// Only an administrator, and only on their own profile.
		if ( ! current_user_can( 'manage_options' ) || get_current_user_id() !== (int) $user->ID ) {
			return;
		}
		$current = get_user_meta( $user->ID, FF_Gating::META_PREVIEW, true );
		?>
		<h2><?php esc_html_e( 'Founding Faces Preview', 'founding-faces' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ff_preview_as"><?php esc_html_e( 'View the site as', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_preview_as" id="ff_preview_as">
						<?php foreach ( self::options() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'For testing only. When set to The 35 or The Circle, you\'ll see the members\' area exactly as that group does, including being blocked from content meant for the other group. This affects only you, and you can switch it from the toolbar at any time.', 'founding-faces' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the preview choice from the profile screen.
	 *
	 * WordPress nonce-checks the profile update itself.
	 *
	 * @param int $user_id The user being saved.
	 */
	public static function save_profile( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) || get_current_user_id() !== (int) $user_id ) {
			return;
		}
		if ( ! isset( $_POST['ff_preview_as'] ) ) {
			return;
		}
		self::set_preview( (int) $user_id, sanitize_key( wp_unslash( $_POST['ff_preview_as'] ) ) );
	}

	/**
	 * Store (or clear) a preview choice for a user.
	 *
	 * @param int    $user_id The user id.
	 * @param string $group   'the-35', 'the-circle', or anything else to clear.
	 */
	private static function set_preview( $user_id, $group ) {
		if ( ! in_array( $group, array( 'the-35', 'the-circle' ), true ) ) {
			delete_user_meta( $user_id, FF_Gating::META_PREVIEW );
			return;
		}
		update_user_meta( $user_id, FF_Gating::META_PREVIEW, $group );
	}

	/**
	 * Add the preview switcher to the admin toolbar (front end and back).
	 *
	 * @param WP_Admin_Bar $bar The admin bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = get_user_meta( get_current_user_id(), FF_Gating::META_PREVIEW, true );
		$current = in_array( $current, array( 'the-35', 'the-circle' ), true ) ? $current : '';
		$labels  = self::options();

		$title = '' !== $current
			? '👁 ' . sprintf( __( 'Viewing as %s', 'founding-faces' ), $labels[ $current ] )
			: __( 'FF: Viewing as Admin', 'founding-faces' );

		$bar->add_node( array(
			'id'    => 'ff-preview',
			'title' => esc_html( $title ),
			'href'  => '#',
			'meta'  => array( 'title' => __( 'Founding Faces preview', 'founding-faces' ) ),
		) );

		// Built from the current page with this tool's own arguments stripped,
		// so switching twice from the same screen does not stack them up.
		$base = remove_query_arg( array( 'ff_preview', '_wpnonce', 'ff_switched' ) );

		foreach ( $labels as $key => $label ) {
			$value = '' === $key ? 'none' : $key;
			$url   = wp_nonce_url( add_query_arg( 'ff_preview', $value, $base ), 'ff_preview' );
			$mark  = ( $current === $key ) ? '● ' : '○ ';
			$bar->add_node( array(
				'id'     => 'ff-preview-' . $value,
				'parent' => 'ff-preview',
				'title'  => esc_html( $mark . $label ),
				'href'   => esc_url( $url ),
			) );
		}
	}

	/**
	 * Confirm the switch, and say what is actually stored.
	 *
	 * Reads the setting back from the database rather than repeating what was
	 * asked for, so a switch that failed to save says so instead of agreeing
	 * with the click.
	 */
	public static function switch_notice() {
		if ( empty( $_GET['ff_switched'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$asked = get_transient( 'ff_preview_switched_' . get_current_user_id() );
		if ( false === $asked ) {
			return;
		}
		delete_transient( 'ff_preview_switched_' . get_current_user_id() );

		$stored  = get_user_meta( get_current_user_id(), FF_Gating::META_PREVIEW, true );
		$stored  = in_array( $stored, array( 'the-35', 'the-circle' ), true ) ? $stored : '';
		$wanted  = ( 'none' === $asked ) ? '' : $asked;
		$labels  = self::options();
		$matched = ( $stored === $wanted );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $matched ? 'success' : 'error' ),
			esc_html( $matched
				? sprintf(
					/* translators: %s is a group name. */
					__( 'Now viewing the site as: %s', 'founding-faces' ),
					isset( $labels[ $stored ] ) ? $labels[ $stored ] : __( 'Administrator', 'founding-faces' )
				)
				: sprintf(
					/* translators: 1: the group asked for, 2: the group actually stored. */
					__( 'The preview did not change. Asked for %1$s, still set to %2$s. Try the "View the site as" setting on your own profile instead.', 'founding-faces' ),
					isset( $labels[ $wanted ] ) ? $labels[ $wanted ] : $wanted,
					isset( $labels[ $stored ] ) ? $labels[ $stored ] : __( 'Administrator', 'founding-faces' )
				)
			)
		);
	}

	/**
	 * Handle a preview switch from an admin-bar link, then redirect cleanly.
	 *
	 * @return void
	 */
	public static function maybe_switch() {
		if ( ! isset( $_GET['ff_preview'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Not sanitize_key, which lowercases: a nonce is checked character for
		// character and lowercasing one is how a valid request gets refused.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ff_preview' ) ) {
			return;
		}

		$value = sanitize_key( wp_unslash( $_GET['ff_preview'] ) );
		self::set_preview( get_current_user_id(), $value );

		// Say what happened on the other side of the redirect. A switch that
		// looks like it did nothing is indistinguishable from one that did
		// nothing, and this is a tool for checking what members can see, so
		// being sure which member you are is the whole point of it.
		set_transient( 'ff_preview_switched_' . get_current_user_id(), $value, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'ff_switched', '1', remove_query_arg( array( 'ff_preview', '_wpnonce', 'ff_switched' ) ) ) );
		exit;
	}
}
