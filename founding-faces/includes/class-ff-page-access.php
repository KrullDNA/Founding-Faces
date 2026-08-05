<?php
/**
 * Page-level access control.
 *
 * Adds a "Founding Faces Access" choice to every Page and Post so a whole page
 * can be set Public, All members, The 35 only, or The Circle only. Unlike the
 * per-element Elementor "Show to" control (which hides content within a page),
 * this locks the whole URL: an unauthorised visitor is redirected, logged-out
 * people to the login page (and back once they sign in), logged-in members who
 * aren't in the right tier to a page you choose (or the home page).
 *
 * Enforced on the server, before the page renders. Admins, and anyone who can
 * edit the page, are never locked out.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Page_Access
 */
class FF_Page_Access {

	// Post meta holding the access level for a page/post.
	const META = '_ff_page_access';

	// Option holding an optional "restricted" landing page for logged-in but
	// unauthorised visitors.
	const OPT_REDIRECT = 'ff_restricted_redirect';

	/**
	 * The access levels a page can be set to.
	 *
	 * @return array
	 */
	public static function levels() {
		return array(
			''            => __( 'Public (no restriction)', 'founding-faces' ),
			'members'     => __( 'All members (The 35 & The Circle)', 'founding-faces' ),
			'the-35'      => __( 'The 35 only', 'founding-faces' ),
			'the-circle'  => __( 'The Circle only', 'founding-faces' ),
		);
	}

	/**
	 * The post types the access box appears on.
	 *
	 * @return array
	 */
	public static function post_types() {
		return apply_filters( 'ff_access_post_types', array( 'page', 'post' ) );
	}

	/**
	 * Wire up the metabox, its save handler, and the front-end enforcement.
	 */
	public static function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'enforce' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Admin: the access box.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Add the "Founding Faces Access" metabox to the chosen post types.
	 */
	public static function add_metabox() {
		foreach ( self::post_types() as $type ) {
			add_meta_box(
				'ff_page_access',
				__( 'Founding Faces Access', 'founding-faces' ),
				array( __CLASS__, 'render_metabox' ),
				$type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the access dropdown.
	 *
	 * @param WP_Post $post The post being edited.
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( 'ff_save_access', 'ff_access_nonce' );
		$current = get_post_meta( $post->ID, self::META, true );
		?>
		<p>
			<label for="ff_page_access_level"><strong><?php esc_html_e( 'Who can view this page?', 'founding-faces' ); ?></strong></label>
		</p>
		<select name="ff_page_access_level" id="ff_page_access_level" style="width:100%;">
			<?php foreach ( self::levels() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Restricted pages redirect anyone who isn\'t allowed. Logged-out visitors are sent to log in; logged-in members in the wrong group are sent to the restricted page set in Founding Faces → Settings (or the home page).', 'founding-faces' ); ?>
		</p>
		<?php
	}

	/**
	 * Save the access level.
	 *
	 * @param int     $post_id The post id.
	 * @param WP_Post $post    The post object.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['ff_access_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ff_access_nonce'] ), 'ff_save_access' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$level = isset( $_POST['ff_page_access_level'] ) ? sanitize_key( wp_unslash( $_POST['ff_page_access_level'] ) ) : '';
		if ( ! array_key_exists( $level, self::levels() ) ) {
			$level = '';
		}

		if ( '' === $level ) {
			delete_post_meta( $post_id, self::META );
		} else {
			update_post_meta( $post_id, self::META, $level );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Front end: enforcement.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Redirect a visitor away from a restricted page they can't view.
	 *
	 * Runs before the page renders. Admins and anyone who can edit the page are
	 * always allowed (so the page can be built and previewed).
	 */
	public static function enforce() {
		// Only guard front-end single pages/posts.
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$level = get_post_meta( $post_id, self::META, true );
		if ( '' === $level || null === $level ) {
			return; // Public.
		}

		if ( self::is_allowed( $level, $post_id ) ) {
			return;
		}

		// Not allowed: redirect.
		if ( ! is_user_logged_in() ) {
			// Send them to log in, then back to this page. FF_Menu_Items decides
			// which login screen that is, the one set in Settings, or the
			// WordPress one if none has been set. A member who has never seen
			// wp-login.php should not meet it for the first time here.
			wp_safe_redirect( FF_Menu_Items::login_redirect_target( self::current_url() ) );
			exit;
		}

		// Logged in but in the wrong group (or not a member): send them to the
		// configured restricted page, or the home page.
		$redirect_id = (int) get_option( self::OPT_REDIRECT, 0 );
		$target      = $redirect_id ? get_permalink( $redirect_id ) : home_url( '/' );

		// Avoid redirecting the restricted page to itself.
		if ( $redirect_id === $post_id || ! $target ) {
			$target = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'ff_denied', '1', $target ) );
		exit;
	}

	/**
	 * Whether the current visitor may view a page at a given access level.
	 *
	 * @param string $level   The access level.
	 * @param int    $post_id The page id (for the edit-capability bypass).
	 * @return bool
	 */
	public static function is_allowed( $level, $post_id = 0 ) {
		// Admins and page editors are never locked out, unless an admin is
		// previewing the site as a member, in which case they get the real gate.
		if ( '' === FF_Gating::preview_group()
			&& ( current_user_can( 'manage_options' ) || ( $post_id && current_user_can( 'edit_post', $post_id ) ) ) ) {
			return true;
		}

		switch ( $level ) {
			case 'members':
				return FF_Gating::is_member();
			case 'the-35':
				return FF_Gating::is_the_35();
			case 'the-circle':
				return FF_Gating::is_the_circle();
			default:
				return true;
		}
	}

	/**
	 * The URL of the current page.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
