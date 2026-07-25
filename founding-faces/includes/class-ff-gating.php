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

	/**
	 * Wire up the Elementor visibility control and its server-side enforcement.
	 *
	 * The Elementor hooks are harmless if Elementor isn't installed — they
	 * simply never fire.
	 */
	public static function register() {
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

		$rule = $element->get_settings_for_display( 'ff_visibility' );
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
