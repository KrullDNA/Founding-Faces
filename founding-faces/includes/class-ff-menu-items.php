<?php
/**
 * Dynamic nav-menu behaviour: the login/logout item and the unread badge.
 *
 * Two things a membership site always needs in its menu, and neither is
 * possible with a static menu item:
 *
 * 1. A single item that reads "Log in" when logged out and "Log out" when
 *    logged in, pointing at the right URL each time (with a redirect back to
 *    the hub after login, and to a chosen page after logout).
 * 2. A count bubble — like a mini-cart — showing how many unread messages (or
 *    new notes, or open polls) are waiting, so a member sees at a glance that
 *    there's something for them.
 *
 * Both are opt-in per menu item in Appearance → Menus, so an existing menu is
 * untouched until a checkbox is ticked. Everything is resolved at render time
 * against the current viewer, and page caching is bypassed for logged-in users
 * by WordPress itself, so the label and count are never stale for a member.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Menu_Items
 */
class FF_Menu_Items {

	// Per-menu-item meta keys.
	const META_AUTH   = '_ff_menu_auth';        // '' | 'loginout' | 'login' | 'logout'.
	const META_BADGE  = '_ff_menu_badge';       // '' | 'messages' | 'notes' | 'polls' | 'all'.

	// Settings (shared with the Login widget).
	const OPT_LOGIN_PAGE     = 'ff_login_page_url';
	const OPT_LOGIN_REDIRECT = 'ff_login_redirect_url';
	const OPT_LOGOUT_REDIRECT = 'ff_logout_redirect_url';
	const OPT_LOGIN_LABEL    = 'ff_login_label';
	const OPT_LOGOUT_LABEL   = 'ff_logout_label';

	/**
	 * Wire up the menu-item fields, the save handler and the render filters.
	 */
	public static function register() {
		add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'render_fields' ), 20, 4 );
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save_fields' ), 20, 2 );

		// Resolve the dynamic items at render time.
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'resolve_items' ), 20, 2 );
		add_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'append_badge' ), 10, 4 );

		// The Login widget and its shortcode.
		add_shortcode( 'ff_login', array( __CLASS__, 'sc_login' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );

		// Send a failed login back to the page the form was on, not to
		// wp-login.php, so a custom login page stays the login page.
		add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ) );
	}

	/**
	 * Return a failed login to the originating page with ?login=failed.
	 *
	 * Only acts when the attempt came from one of our own forms (the referer is
	 * a page on this site, not wp-login.php), so the standard WordPress login
	 * screen keeps its normal error behaviour.
	 *
	 * @param string $username The attempted username (unused).
	 */
	public static function login_failed( $username ) {
		$referer = wp_get_referer();
		if ( ! $referer || false !== strpos( $referer, 'wp-login.php' ) || false !== strpos( $referer, 'wp-admin' ) ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'login', 'failed', remove_query_arg( 'login', $referer ) ) );
		exit;
	}

	/**
	 * Register the Login Elementor widget.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-login-widget.php';
		$widgets_manager->register( new FF_Login_Widget() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings helpers.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The URL of the login page.
	 *
	 * Falls back to the WordPress login screen if no page has been set, so the
	 * link is never dead.
	 *
	 * @param string $redirect_to Where to send the member after logging in.
	 * @return string
	 */
	public static function login_url( $redirect_to = '' ) {
		$redirect_to = '' !== $redirect_to ? $redirect_to : self::login_redirect_url();
		$page        = trim( (string) get_option( self::OPT_LOGIN_PAGE, '' ) );

		if ( '' === $page ) {
			return wp_login_url( $redirect_to );
		}

		// A custom login page: pass the destination along as redirect_to, which
		// both the WordPress login form and our own widget understand.
		return '' !== $redirect_to
			? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $page )
			: $page;
	}

	/**
	 * Where a member lands after logging in.
	 *
	 * The explicit setting wins; otherwise the hub page (already used by the
	 * login_redirect filter); otherwise the site's home page.
	 *
	 * @return string
	 */
	public static function login_redirect_url() {
		$url = trim( (string) get_option( self::OPT_LOGIN_REDIRECT, '' ) );
		if ( '' !== $url ) {
			return $url;
		}

		$portal = FF_Messages::portal_url();
		return '' !== $portal ? $portal : home_url( '/' );
	}

	/**
	 * The logout URL, with its own redirect destination.
	 *
	 * @return string
	 */
	public static function logout_url() {
		$url = trim( (string) get_option( self::OPT_LOGOUT_REDIRECT, '' ) );
		return wp_logout_url( '' !== $url ? $url : home_url( '/' ) );
	}

	/**
	 * The "Log in" label.
	 *
	 * @return string
	 */
	public static function login_label() {
		$label = trim( (string) get_option( self::OPT_LOGIN_LABEL, '' ) );
		return '' !== $label ? $label : __( 'Log in', 'founding-faces' );
	}

	/**
	 * The "Log out" label.
	 *
	 * @return string
	 */
	public static function logout_label() {
		$label = trim( (string) get_option( self::OPT_LOGOUT_LABEL, '' ) );
		return '' !== $label ? $label : __( 'Log out', 'founding-faces' );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The menu-item editor fields.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Render the login/logout and badge fields in the menu-item editor.
	 *
	 * @param int    $item_id The menu item id.
	 * @param object $item    The menu item.
	 * @param int    $depth   Depth (unused).
	 * @param object $args    Args (unused).
	 */
	public static function render_fields( $item_id, $item, $depth, $args ) {
		$auth  = get_post_meta( $item_id, self::META_AUTH, true );
		$badge = get_post_meta( $item_id, self::META_BADGE, true );
		?>
		<p class="field-ff-auth description description-wide">
			<label for="ff-menu-auth-<?php echo esc_attr( $item_id ); ?>">
				<?php esc_html_e( 'Founding Faces login/logout', 'founding-faces' ); ?><br />
				<select name="ff_menu_auth[<?php echo esc_attr( $item_id ); ?>]"
					id="ff-menu-auth-<?php echo esc_attr( $item_id ); ?>" class="widefat">
					<?php foreach ( self::auth_options() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $auth, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="description">
					<?php esc_html_e( 'The label and link are set automatically. Set the login page and redirects under Founding Faces → Settings.', 'founding-faces' ); ?>
				</span>
			</label>
		</p>

		<p class="field-ff-badge description description-wide">
			<label for="ff-menu-badge-<?php echo esc_attr( $item_id ); ?>">
				<?php esc_html_e( 'Show an unread count bubble', 'founding-faces' ); ?><br />
				<select name="ff_menu_badge[<?php echo esc_attr( $item_id ); ?>]"
					id="ff-menu-badge-<?php echo esc_attr( $item_id ); ?>" class="widefat">
					<?php foreach ( self::badge_options() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $badge, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="description">
					<?php esc_html_e( 'The bubble is hidden entirely when the count is zero, and for logged-out visitors.', 'founding-faces' ); ?>
				</span>
			</label>
		</p>
		<?php
	}

	/**
	 * The login/logout behaviour choices.
	 *
	 * @return array
	 */
	public static function auth_options() {
		return array(
			''         => __( 'Normal menu item', 'founding-faces' ),
			'loginout' => __( 'Log in / Log out (swaps automatically)', 'founding-faces' ),
			'login'    => __( 'Log in only (hidden once logged in)', 'founding-faces' ),
			'logout'   => __( 'Log out only (hidden when logged out)', 'founding-faces' ),
		);
	}

	/**
	 * The badge source choices.
	 *
	 * @return array
	 */
	public static function badge_options() {
		return array(
			''         => __( 'No bubble', 'founding-faces' ),
			'messages' => __( 'Unread private messages', 'founding-faces' ),
			'notes'    => __( 'New notes since last visit', 'founding-faces' ),
			'polls'    => __( 'Open polls not yet voted in', 'founding-faces' ),
			'all'      => __( 'Everything unread (messages + notes + polls)', 'founding-faces' ),
		);
	}

	/**
	 * Save both fields for a menu item.
	 *
	 * Runs on the core nav-menu save, which WordPress has already nonce-checked.
	 *
	 * @param int $menu_id         The menu id (unused).
	 * @param int $menu_item_db_id The menu item id.
	 */
	public static function save_fields( $menu_id, $menu_item_db_id ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$fields = array(
			self::META_AUTH  => array( 'ff_menu_auth', self::auth_options() ),
			self::META_BADGE => array( 'ff_menu_badge', self::badge_options() ),
		);

		foreach ( $fields as $meta_key => $spec ) {
			list( $post_key, $allowed ) = $spec;
			if ( ! isset( $_POST[ $post_key ][ $menu_item_db_id ] ) ) {
				continue;
			}

			$value = sanitize_key( wp_unslash( $_POST[ $post_key ][ $menu_item_db_id ] ) );
			if ( ! array_key_exists( $value, $allowed ) ) {
				$value = '';
			}

			if ( '' === $value ) {
				delete_post_meta( $menu_item_db_id, $meta_key );
			} else {
				update_post_meta( $menu_item_db_id, $meta_key, $value );
			}
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Front-end rendering.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Swap the label and URL of any login/logout item, and drop the ones that
	 * don't apply to the current viewer.
	 *
	 * Runs after FF_Menu_Visibility's filter, so a hidden item is already gone.
	 *
	 * @param array  $items The menu item objects.
	 * @param object $args  The menu args (unused).
	 * @return array
	 */
	public static function resolve_items( $items, $args ) {
		$logged_in = is_user_logged_in();
		$kept      = array();

		foreach ( $items as $item ) {
			$auth = get_post_meta( $item->ID, self::META_AUTH, true );

			if ( '' === $auth ) {
				$kept[] = $item;
				continue;
			}

			// A single-purpose item disappears when it doesn't apply.
			if ( ( 'login' === $auth && $logged_in ) || ( 'logout' === $auth && ! $logged_in ) ) {
				continue;
			}

			if ( $logged_in ) {
				$item->title = self::logout_label();
				$item->url   = self::logout_url();
			} else {
				// Send them back to where they were, if that's a members page,
				// otherwise to the configured landing page.
				$item->title = self::login_label();
				$item->url   = self::login_url();
			}

			// The resolved URL is generated per request; never let it be treated
			// as the "current page" and pick up an active class.
			$item->classes[] = 'ff-menu-auth';
			$item->classes[] = $logged_in ? 'ff-menu-auth--out' : 'ff-menu-auth--in';

			$kept[] = $item;
		}

		return $kept;
	}

	/**
	 * Append the count bubble to a menu item's link markup.
	 *
	 * Uses walker_nav_menu_start_el so the bubble lands inside the <a>, exactly
	 * like a mini-cart count, and inherits the link's colour by default.
	 *
	 * @param string $item_output The item's HTML.
	 * @param object $item        The menu item.
	 * @param int    $depth       Depth (unused).
	 * @param object $args        The menu args (unused).
	 * @return string
	 */
	public static function append_badge( $item_output, $item, $depth, $args ) {
		if ( ! is_user_logged_in() ) {
			return $item_output;
		}

		$source = get_post_meta( $item->ID, self::META_BADGE, true );
		if ( '' === $source ) {
			return $item_output;
		}

		$count = self::badge_count( $source );
		if ( $count < 1 ) {
			return $item_output;
		}

		$bubble = '<span class="ff-menu-badge" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>'
			. '<span class="screen-reader-text">' . esc_html( sprintf(
				/* translators: %s is a number of unread items. */
				_n( '%s unread', '%s unread', $count, 'founding-faces' ),
				number_format_i18n( $count )
			) ) . '</span>';

		// Place the bubble just inside the closing </a> where one exists, so it
		// stays part of the link; otherwise append it to the output.
		$pos = strripos( $item_output, '</a>' );
		if ( false === $pos ) {
			return $item_output . $bubble;
		}

		return substr( $item_output, 0, $pos ) . $bubble . substr( $item_output, $pos );
	}

	/**
	 * The unread count for a badge source, for the current member.
	 *
	 * Always scoped to the viewer: a member only ever counts their own unread
	 * messages, and only notes and polls their group is allowed to see.
	 *
	 * @param string $source One of the badge_options() keys.
	 * @return int
	 */
	public static function badge_count( $source ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return 0;
		}

		switch ( $source ) {
			case 'messages':
				return self::count_messages( $user_id );

			case 'notes':
				return self::count_new_notes( $user_id );

			case 'polls':
				return self::count_open_polls( $user_id );

			case 'all':
				return self::count_messages( $user_id )
					+ self::count_new_notes( $user_id )
					+ self::count_open_polls( $user_id );
		}

		return 0;
	}

	/**
	 * Unread admin replies for this member.
	 *
	 * @param int $user_id The member.
	 * @return int
	 */
	private static function count_messages( $user_id ) {
		return (int) FF_Messages::unread_for_member( $user_id );
	}

	/**
	 * Notes published since the member last logged in, that they may see.
	 *
	 * A member with no recorded login yet sees no badge rather than a count of
	 * every note ever published — the bubble is for what's new to them.
	 *
	 * @param int $user_id The member.
	 * @return int
	 */
	private static function count_new_notes( $user_id ) {
		$since = (int) get_user_meta( $user_id, FF_Members::META_LAST_LOGIN, true );
		if ( ! $since ) {
			return 0;
		}

		$notes = get_posts( array(
			'post_type'      => FF_Post_Types::NOTE_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'date_query'     => array(
				array( 'after' => gmdate( 'Y-m-d H:i:s', $since ), 'column' => 'post_date_gmt', 'inclusive' => false ),
			),
		) );

		$count = 0;
		foreach ( $notes as $note_id ) {
			if ( FF_Gating::can_view_note( $note_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Open polls the member may see and hasn't voted in yet.
	 *
	 * @param int $user_id The member.
	 * @return int
	 */
	private static function count_open_polls( $user_id ) {
		$count = 0;
		foreach ( FF_Polls::viewable_poll_ids() as $poll_id ) {
			if ( 'open' !== FF_Polls::poll_state( $poll_id ) ) {
				continue;
			}
			if ( ! FF_Polls::member_vote( $poll_id, $user_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The login component.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The login form: [ff_login].
	 *
	 * A member-facing login form that posts to WordPress's own handler, so
	 * authentication, cookies and brute-force protection all behave exactly as
	 * core does — this is a skin, not a replacement. A member who is already
	 * logged in sees a short "you're signed in" panel with a log-out link
	 * instead, so the page is never a dead end.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function sc_login( $atts = array() ) {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );

		$atts = shortcode_atts(
			array(
				'redirect'       => '',
				'label_user'     => __( 'Email address', 'founding-faces' ),
				'label_pass'     => __( 'Password', 'founding-faces' ),
				'button'         => __( 'Log in', 'founding-faces' ),
				'show_remember'  => 'yes',
				'show_lost'      => 'yes',
				'lost_text'      => __( 'Forgotten your password?', 'founding-faces' ),
				'logged_in_text' => '',
				'form_class'     => '',
			),
			$atts,
			'ff_login'
		);

		// An empty string from a widget control means "use the default", not
		// "render nothing", so fill the blanks back in.
		$defaults = array(
			'label_user' => __( 'Email address', 'founding-faces' ),
			'label_pass' => __( 'Password', 'founding-faces' ),
			'button'     => __( 'Log in', 'founding-faces' ),
			'lost_text'  => __( 'Forgotten your password?', 'founding-faces' ),
		);
		foreach ( $defaults as $key => $fallback ) {
			if ( '' === trim( (string) $atts[ $key ] ) ) {
				$atts[ $key ] = $fallback;
			}
		}

		$redirect = '' !== trim( (string) $atts['redirect'] ) ? $atts['redirect'] : self::login_redirect_url();
		$classes  = 'ff-login ff-form' . ( '' !== $atts['form_class'] ? ' ' . $atts['form_class'] : '' );

		// Already signed in: show who they are and a way out, not a login form.
		if ( is_user_logged_in() ) {
			$text = '' !== trim( (string) $atts['logged_in_text'] )
				? $atts['logged_in_text']
				: __( "You're signed in.", 'founding-faces' );

			$out  = '<div class="' . esc_attr( $classes ) . ' ff-login--in">';
			$out .= '<p class="ff-login-status">' . esc_html( $text ) . '</p>';
			$out .= '<p class="ff-login-actions"><a class="ff-login-logout" href="' . esc_url( self::logout_url() ) . '">'
				. esc_html( self::logout_label() ) . '</a></p>';
			$out .= '</div>';
			return $out;
		}

		// A failed attempt comes back with ?login=failed; say so plainly.
		$failed = ( isset( $_GET['login'] ) && 'failed' === sanitize_key( wp_unslash( $_GET['login'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$out = '<div class="' . esc_attr( $classes ) . '">';
		if ( $failed ) {
			$out .= '<div class="ff-notice ff-notice--error">'
				. esc_html__( 'That email address and password did not match. Please try again.', 'founding-faces' )
				. '</div>';
		}

		$out .= '<form class="ff-login-form" method="post" action="' . esc_url( wp_login_url() ) . '">';
		$out .= '<div class="ff-field"><label for="ff-login-user">' . esc_html( $atts['label_user'] ) . '</label>';
		$out .= '<input type="text" name="log" id="ff-login-user" autocomplete="username" required /></div>';
		$out .= '<div class="ff-field"><label for="ff-login-pass">' . esc_html( $atts['label_pass'] ) . '</label>';
		$out .= '<input type="password" name="pwd" id="ff-login-pass" autocomplete="current-password" required /></div>';

		if ( 'no' !== $atts['show_remember'] ) {
			$out .= '<div class="ff-field ff-field--checkbox"><label for="ff-login-remember">';
			$out .= '<input type="checkbox" name="rememberme" id="ff-login-remember" value="forever" /> ';
			$out .= esc_html__( 'Keep me signed in', 'founding-faces' ) . '</label></div>';
		}

		$out .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect ) . '" />';
		$out .= '<div class="ff-submit"><button type="submit">' . esc_html( $atts['button'] ) . '</button></div>';
		$out .= '</form>';

		if ( 'no' !== $atts['show_lost'] ) {
			$out .= '<p class="ff-login-lost"><a href="' . esc_url( wp_lostpassword_url( $redirect ) ) . '">'
				. esc_html( $atts['lost_text'] ) . '</a></p>';
		}

		$out .= '</div>';
		return $out;
	}
}
