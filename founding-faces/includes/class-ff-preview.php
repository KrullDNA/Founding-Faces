<?php
/**
 * Admin "view as a member" preview.
 *
 * For testing: an administrator can view the whole site as a member of The 35 or
 * The Circle, so they see exactly what that group sees — gated notes, polls,
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
		<h2><?php esc_html_e( 'Founding Faces — Preview', 'founding-faces' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ff_preview_as"><?php esc_html_e( 'View the site as', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_preview_as" id="ff_preview_as">
						<?php foreach ( self::options() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'For testing only. When set to The 35 or The Circle, you\'ll see the members\' area exactly as that group does — including being blocked from content meant for the other group. This affects only you, and you can switch it from the toolbar at any time.', 'founding-faces' ); ?></p>
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

		foreach ( $labels as $key => $label ) {
			$value = '' === $key ? 'none' : $key;
			$url   = wp_nonce_url( add_query_arg( 'ff_preview', $value ), 'ff_preview' );
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
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'ff_preview' ) ) {
			return;
		}

		$value = sanitize_key( wp_unslash( $_GET['ff_preview'] ) );
		self::set_preview( get_current_user_id(), $value );

		wp_safe_redirect( remove_query_arg( array( 'ff_preview', '_wpnonce' ) ) );
		exit;
	}
}
