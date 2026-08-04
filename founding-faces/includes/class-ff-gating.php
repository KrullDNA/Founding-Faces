<?php
/**
 * Content gating: the server-side role check and the Elementor visibility
 * condition that sits on top of it.
 *
 * The rule that matters: 35-only content is never sent to an unauthorised
 * browser. Both the note gate and the Elementor condition decide on the server,
 * before any markup is produced, so gated content can't be revealed by viewing
 * the page source.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Gating
 *
 * The single source of truth for "is this person allowed to see this?". Used by
 * the note renderer in a later stage and by the Elementor visibility control
 * built here.
 */
class FF_Gating {

	// User meta on an admin: which group they are previewing the site as
	// ('the-35' | 'the-circle' | ''). Only ever read for administrators.
	const META_PREVIEW = 'ff_preview_as';

	/**
	 * The group an administrator is previewing the site as, or '' if none.
	 *
	 * Only administrators can preview; for everyone else this is always ''.
	 *
	 * @return string 'the-35', 'the-circle', or ''.
	 */
	public static function preview_group() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		$g = get_user_meta( get_current_user_id(), self::META_PREVIEW, true );
		return in_array( $g, array( 'the-35', 'the-circle' ), true ) ? $g : '';
	}

	/**
	 * Whether the current viewer is an administrator who should see everything.
	 *
	 * True for a normal admin; FALSE while an admin is previewing as a member,
	 * so the preview experiences the real gate instead of the admin bypass.
	 *
	 * @return bool
	 */
	public static function admin_bypass() {
		return current_user_can( 'manage_options' ) && '' === self::preview_group();
	}

	/**
	 * Wire up the Elementor visibility control and its server-side enforcement.
	 *
	 * The Elementor hooks are harmless if Elementor isn't installed — they
	 * simply never fire.
	 */
	public static function register() {
		// Gate note queries at the source, so ANY loop tool that queries notes
		// (JetEngine Listing Grid, Elementor Pro Loop, a native WP_Query, our
		// own components) automatically hides 35-only notes from anyone who
		// isn't in The 35. This is what keeps the vault safe in a JetEngine grid.
		add_action( 'pre_get_posts', array( __CLASS__, 'gate_note_queries' ) );

		// Gate the single-note URL: a note now has its own page (so Elementor
		// can style it), but the wrong viewer is redirected before it renders.
		add_action( 'template_redirect', array( __CLASS__, 'gate_single_note' ) );

		// The same for a product's own page.
		add_action( 'template_redirect', array( __CLASS__, 'gate_single_product' ) );

		// Keep both out of the WordPress sitemap, so single URLs aren't listed.
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'exclude_private_from_sitemap' ) );

		// Add the "Show to" control to the Advanced tab of every element.
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'add_visibility_control' ), 10, 3 );

		// Enforce it server-side: return false to skip rendering entirely, so
		// gated markup is never produced or sent.
		add_filter( 'elementor/frontend/widget/should_render', array( __CLASS__, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/section/should_render', array( __CLASS__, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/container/should_render', array( __CLASS__, 'should_render' ), 10, 2 );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Membership checks.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Get the group slug of a user, or empty string if they aren't a member.
	 *
	 * @param int|null $user_id A user id, or null for the current user.
	 * @return string 'the-35', 'the-circle', or ''.
	 */
	public static function group_of( $user_id = null ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		// When resolving the CURRENT viewer and that viewer is a previewing admin,
		// report the previewed group, so the whole site treats them as it would a
		// real member of that group. (This must run whether the caller passed null
		// or the current user's own id — is_member() resolves the id first.)
		if ( (int) $user_id === get_current_user_id() ) {
			$preview = self::preview_group();
			if ( '' !== $preview ) {
				return $preview;
			}
		}
		return (string) get_user_meta( $user_id, FF_Members::META_GROUP, true );
	}

	/**
	 * Whether a user is a member of the programme (in either group).
	 *
	 * A deactivated member counts as not a member for viewing purposes.
	 *
	 * @param int|null $user_id A user id, or null for the current user.
	 * @return bool
	 */
	public static function is_member( $user_id = null ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( get_user_meta( $user_id, FF_Members::META_DEACTIVATED, true ) ) {
			return false;
		}
		return '' !== self::group_of( $user_id );
	}

	/**
	 * Whether a user is one of The 35.
	 *
	 * @param int|null $user_id A user id, or null for the current user.
	 * @return bool
	 */
	public static function is_the_35( $user_id = null ) {
		return self::is_member( $user_id ) && 'the-35' === self::group_of( $user_id );
	}

	/**
	 * Whether a user is one of The Circle.
	 *
	 * @param int|null $user_id A user id, or null for the current user.
	 * @return bool
	 */
	public static function is_the_circle( $user_id = null ) {
		return self::is_member( $user_id ) && 'the-circle' === self::group_of( $user_id );
	}

	/**
	 * Whether the current viewer may see the members area at all.
	 *
	 * Members can; so can administrators, so Nick can preview and build the
	 * pages (and, per the governing principle, always see everything).
	 *
	 * @return bool
	 */
	public static function can_view_members_area() {
		return self::is_member() || current_user_can( 'manage_options' );
	}

	/**
	 * Exclude 35-only notes from front-end note queries for anyone not in The 35.
	 *
	 * Runs on every query. It leaves admin screens and members of The 35 (and
	 * administrators) untouched — they see everything — and for everyone else it
	 * appends a meta condition that drops notes flagged the-35-only. Because it
	 * works at the query level, a JetEngine Listing Grid or any other loop over
	 * ff_note stays gated with no extra work.
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return void
	 */
	public static function gate_note_queries( $query ) {
		// Never touch real admin-screen queries (but DO gate front-end AJAX,
		// e.g. a JetEngine "load more", where is_admin() is also true).
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Only note queries.
		$post_type = $query->get( 'post_type' );
		$is_note   = ( FF_Post_Types::NOTE_CPT === $post_type )
			|| ( is_array( $post_type ) && in_array( FF_Post_Types::NOTE_CPT, $post_type, true ) );
		if ( ! $is_note ) {
			return;
		}

		// Administrators and The 35 see every note (a previewing admin does not
		// bypass, so they experience the real gate).
		if ( self::admin_bypass() || self::is_the_35() ) {
			return;
		}

		// Everyone else: exclude notes flagged the-35-only.
		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => FF_Post_Types::META_NOTE_AUDIENCE,
				'value'   => 'the-35-only',
				'compare' => '!=',
			),
			array(
				'key'     => FF_Post_Types::META_NOTE_AUDIENCE,
				'compare' => 'NOT EXISTS',
			),
		);
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Redirect the wrong viewer away from a single note's page.
	 *
	 * The note has a public URL now (for Elementor Single templates), but the
	 * audience gate is enforced here before anything renders: a member who can't
	 * view the note is sent away, and a logged-out visitor is sent to log in.
	 *
	 * @return void
	 */
	public static function gate_single_note() {
		if ( is_admin() || ! is_singular( FF_Post_Types::NOTE_CPT ) ) {
			return;
		}

		$note_id = get_queried_object_id();
		if ( self::can_view_note( $note_id ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::current_url() ) );
			exit;
		}

		$redirect_id = (int) get_option( FF_Page_Access::OPT_REDIRECT, 0 );
		$target      = $redirect_id ? get_permalink( $redirect_id ) : home_url( '/' );
		if ( ! $target ) {
			$target = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'ff_denied', '1', $target ) );
		exit;
	}

	/**
	 * Send anyone who isn't a member away from a product's own page.
	 *
	 * A product has no audience flag — there is no vault version of a product —
	 * so the only question is whether the viewer is in the members area at all.
	 *
	 * @return void
	 */
	public static function gate_single_product() {
		if ( is_admin() || ! is_singular( FF_Post_Types::PRODUCT_CPT ) ) {
			return;
		}
		if ( self::can_view_members_area() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::current_url() ) );
			exit;
		}

		$redirect_id = (int) get_option( FF_Page_Access::OPT_REDIRECT, 0 );
		$target      = $redirect_id ? get_permalink( $redirect_id ) : home_url( '/' );
		if ( ! $target ) {
			$target = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'ff_denied', '1', $target ) );
		exit;
	}

	/**
	 * Remove the members-only post types from the WordPress sitemap.
	 *
	 * @param array $post_types The sitemap post types, keyed by name.
	 * @return array
	 */
	public static function exclude_private_from_sitemap( $post_types ) {
		unset( $post_types[ FF_Post_Types::NOTE_CPT ], $post_types[ FF_Post_Types::PRODUCT_CPT ] );
		return $post_types;
	}

	/**
	 * The URL of the current request, for a post-login redirect.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp ? $wp->request : '' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The note gate.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Whether a user may view a given note.
	 *
	 * Notes are members-only. An "everyone" note is visible to any member; a
	 * "the-35-only" note is visible only to The 35. The renderer calls this
	 * before producing any of the note's content.
	 *
	 * @param int      $note_id The ff_note id.
	 * @param int|null $user_id A user id, or null for the current user.
	 * @return bool
	 */
	public static function can_view_note( $note_id, $user_id = null ) {
		// Administrators always see everything (they build the pages and, per
		// the governing principle, can always see the full record) — unless the
		// admin is previewing the site as a member, in which case they are gated
		// exactly as that member would be.
		$is_current = ( null === $user_id );
		$check_user = $user_id ? $user_id : get_current_user_id();
		if ( user_can( $check_user, 'manage_options' ) && ! ( $is_current && '' !== self::preview_group() ) ) {
			return true;
		}

		// Only members see notes at all.
		if ( ! self::is_member( $user_id ) ) {
			return false;
		}

		$audience = get_post_meta( $note_id, FF_Post_Types::META_NOTE_AUDIENCE, true );
		if ( 'the-35-only' === $audience ) {
			return self::is_the_35( $user_id );
		}

		// Everything else is visible to all members.
		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The Elementor visibility condition.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The choices offered by the "Show to" control.
	 *
	 * @return array
	 */
	public static function visibility_options() {
		return array(
			''           => __( 'Everyone (no restriction)', 'founding-faces' ),
			'logged_out' => __( 'Logged-out visitors only', 'founding-faces' ),
			'members'    => __( 'All members', 'founding-faces' ),
			'the_35'     => __( 'The 35 only', 'founding-faces' ),
			'the_circle' => __( 'The Circle only', 'founding-faces' ),
		);
	}

	/**
	 * Add the "Show to" select to the Advanced tab of every Elementor element.
	 *
	 * Hooked to run once per element, just after the built-in responsive
	 * section, so it appears in a tidy "Founding Faces" section in Advanced.
	 *
	 * @param object $element    The Elementor element.
	 * @param string $section_id The id of the section that just ended.
	 * @param array  $args       Section args (unused).
	 */
	public static function add_visibility_control( $element, $section_id, $args ) {
		// Only add it once per element: after the responsive section, which is
		// present on widgets, sections and containers.
		if ( '_section_responsive' !== $section_id ) {
			return;
		}

		$element->start_controls_section(
			'ff_visibility_section',
			array(
				'label' => __( 'Founding Faces Visibility', 'founding-faces' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			'ff_visibility',
			array(
				'label'       => __( 'Show to', 'founding-faces' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => self::visibility_options(),
				'description' => __( 'Restrict who this element renders for. Enforced on the server, so hidden content is never sent to the browser.', 'founding-faces' ),
			)
		);

		$element->end_controls_section();
	}

	/**
	 * Decide whether an Elementor element should render, given its "Show to".
	 *
	 * Returning false stops Elementor rendering the element and its children, so
	 * nothing gated ends up in the page source.
	 *
	 * @param bool   $should_render Whether Elementor would otherwise render it.
	 * @param object $element       The Elementor element.
	 * @return bool
	 */
	public static function should_render( $should_render, $element ) {
		// Respect an earlier decision not to render.
		if ( ! $should_render ) {
			return false;
		}

		// IMPORTANT: read the RAW setting here, never get_settings_for_display().
		// should_render() fires before each element sets up its own render, and
		// get_settings_for_display() would prematurely finalise and cache the
		// element's display settings — which makes every container lose its
		// applied width/flex values and collapse to content width on the front
		// end (a page-wide layout bug). get_settings() reads the stored value
		// without any of that side effect.
		$settings = $element->get_settings();
		$rule     = isset( $settings['ff_visibility'] ) ? $settings['ff_visibility'] : '';
		if ( empty( $rule ) ) {
			return $should_render;
		}

		return self::audience_allows( $rule );
	}

	/**
	 * Whether the current visitor satisfies a visibility rule.
	 *
	 * @param string $rule One of the visibility_options keys.
	 * @return bool
	 */
	public static function audience_allows( $rule ) {
		switch ( $rule ) {
			case 'logged_out':
				return ! is_user_logged_in();
			case 'members':
				return self::is_member();
			case 'the_35':
				return self::is_the_35();
			case 'the_circle':
				return self::is_the_circle();
			default:
				return true;
		}
	}
}
