<?php
/**
 * The personal history page.
 *
 * A logged-in member visits their own page and sees their number, the polls
 * they voted in and how they voted, the feedback they submitted, and the notes
 * they've engaged with. It reads only their own rows from the interaction log
 * and the poll-votes table — no other member's data is ever visible here.
 *
 * This is the seed of the launch "fingerprint" moment: the same data, later
 * made presentable and optionally public with consent.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_History
 *
 * The read side of the spine, scoped hard to the current member.
 */
class FF_History {

	/**
	 * Register the personal-history shortcode and the Elementor widgets.
	 */
	public static function register() {
		add_shortcode( 'ff_history', array( __CLASS__, 'shortcode' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	/**
	 * Register the "My …" activity widgets with Elementor.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-history-widgets.php';
		require_once FF_PATH . 'includes/class-ff-welcome-widget.php';
		$widgets_manager->register( new FF_Member_Archive_Widget() );
		$widgets_manager->register( new FF_Welcome_Widget() );
	}

	/**
	 * Enqueue the frontend stylesheet.
	 */
	public static function enqueue() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );
	}

	/**
	 * Whether we're rendering inside the Elementor editor or its preview.
	 *
	 * @return bool
	 */
	public static function is_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$p    = \Elementor\Plugin::$instance;
		$edit = isset( $p->editor ) && $p->editor->is_edit_mode();
		$prev = isset( $p->preview ) && method_exists( $p->preview, 'is_preview_mode' ) && $p->preview->is_preview_mode();
		return $edit || $prev;
	}

	/**
	 * Whether a real render gave the editor nothing worth designing against.
	 *
	 * Deliberately based on the output, not on who is looking. Nick is an
	 * administrator and usually a member too, so "is the viewer a member?" is
	 * the wrong question in the editor — it makes the one person who designs
	 * these pages the one person who never sees the samples. What matters is
	 * whether real content came back: a blank render, a gate notice or an
	 * empty state all mean there is nothing on screen to style.
	 *
	 * @param string $html The real render output.
	 * @return bool
	 */
	public static function sample_needed( $html ) {
		$html = (string) $html;

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return true;
		}

		foreach ( array( 'ff-empty-note', 'ff-members-only', 'ff-empty', 'ff-notice' ) as $marker ) {
			if ( false !== strpos( $html, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Sample renderers.
	 * Used only in the Elementor editor so the widgets can be styled on-brand
	 * before there are any real members. Same markup and classes as the real
	 * sections, so styling carries straight over.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * A sample date a number of weeks in the past, formatted for display.
	 *
	 * @param int $weeks_ago How many weeks back.
	 * @return string
	 */
	private static function sample_date( $weeks_ago ) {
		$ts = current_time( 'timestamp' ) - ( $weeks_ago * WEEK_IN_SECONDS );
		return date_i18n( get_option( 'date_format' ), $ts );
	}

	/**
	 * Sample header markup.
	 *
	 * @param string|null $subheading The subheading text (null = default, '' = hide).
	 * @return string
	 */
	public static function sample_header( $subheading = null ) {
		$sub  = ( null === $subheading ) ? self::default_subheading() : $subheading;
		$out  = '<header class="ff-history-header">';
		$out .= '<div class="ff-history-number">' . sprintf( esc_html__( 'Founding Face %d', 'founding-faces' ), 7 ) . '</div>';
		$out .= '<div class="ff-history-group">' . esc_html__( 'The 35', 'founding-faces' ) . '</div>';
		if ( '' !== trim( (string) $sub ) ) {
			$out .= '<p class="ff-history-intro">' . esc_html( $sub ) . '</p>';
		}
		$out .= '</header>';
		return $out;
	}

	/**
	 * Sample votes markup.
	 *
	 * @param string $heading Optional heading override.
	 * @return string
	 */
	public static function sample_votes( $heading = '' ) {
		$heading = '' !== $heading ? $heading : __( 'Your votes', 'founding-faces' );
		$rows    = array(
			array( __( 'The Serum — accent colour', 'founding-faces' ), __( 'Darker grey', 'founding-faces' ) ),
			array( __( 'The Cleanser — texture', 'founding-faces' ), __( 'Gel-cream', 'founding-faces' ) ),
			array( __( 'The Mist — fragrance', 'founding-faces' ), __( 'Unscented', 'founding-faces' ) ),
		);

		$out  = '<section class="ff-history-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';
		$out .= '<ul class="ff-history-list">';
		foreach ( $rows as $i => $row ) {
			$out .= '<li class="ff-history-item">';
			$out .= '<div class="ff-history-item-body">';
			$out .= '<span class="ff-history-item-main">' . esc_html( $row[0] ) . '</span>';
			$out .= '<span class="ff-history-item-detail">' . sprintf( esc_html__( 'You chose: %s', 'founding-faces' ), esc_html( $row[1] ) ) . '</span>';
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::sample_date( $i + 1 ) ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul></section>';
		return $out;
	}

	/**
	 * Sample notes-read markup.
	 *
	 * @param string $heading Optional heading override.
	 * @param bool   $link    Whether to render the titles as links (to '#').
	 * @return string
	 */
	public static function sample_notes( $heading = '', $link = true ) {
		$heading = '' !== $heading ? $heading : __( 'Notes you\'ve read', 'founding-faces' );
		$titles  = array(
			__( 'Trial 12 — stability at 40°C', 'founding-faces' ),
			__( 'Switching to a mild preservative system', 'founding-faces' ),
			__( 'Why we rejected the first serum base', 'founding-faces' ),
		);

		$out  = '<section class="ff-history-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';
		$out .= '<ul class="ff-history-list">';
		foreach ( $titles as $i => $title ) {
			$main = $link ? '<a href="#">' . esc_html( $title ) . '</a>' : esc_html( $title );
			$out .= '<li class="ff-history-item">';
			$out .= '<div class="ff-history-item-body">';
			$out .= '<span class="ff-history-item-main">' . $main . '</span>';
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::sample_date( $i + 1 ) ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul></section>';
		return $out;
	}

	/**
	 * Sample feedback markup.
	 *
	 * @param string $heading Optional heading override.
	 * @return string
	 */
	public static function sample_feedback( $heading = '' ) {
		$heading = '' !== $heading ? $heading : __( 'Feedback you\'ve shared', 'founding-faces' );
		$items   = array(
			array(
				__( 'The Serum', 'founding-faces' ),
				__( 'I found the texture a touch tacky on application, but it settled after a minute and the finish was lovely. The scent is subtle, which I really appreciate. For daytime I\'d personally prefer something a little lighter, but overall I\'m genuinely excited about where this one is heading.', 'founding-faces' ),
				1,
			),
			array(
				__( 'The Cleanser', 'founding-faces' ),
				__( 'Gentle and never stripping — my skin felt calm and comfortable afterwards, even on the days it\'s a bit reactive. If anything, the pump dispenses a little more than I need per push.', 'founding-faces' ),
				3,
			),
		);

		$out  = '<section class="ff-history-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';
		$out .= '<ul class="ff-history-list">';
		foreach ( $items as $item ) {
			$out .= '<li class="ff-history-item ff-history-item--feedback">';
			$out .= '<div class="ff-history-feedback-head">';
			$out .= '<span class="ff-history-item-main"><a href="#">' . esc_html( $item[0] ) . '</a></span>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::sample_date( $item[2] ) ) . '</span>';
			$out .= '</div>';
			$out .= '<div class="ff-history-feedback-text"><p>' . esc_html( $item[1] ) . '</p></div>';
			$out .= '</li>';
		}
		$out .= '</ul></section>';
		return $out;
	}

	/**
	 * The current member's id for a widget, or 0 with a placeholder shown.
	 *
	 * Returns the logged-in member's id. For a non-member who can nonetheless
	 * see the members area (an admin building the page), returns 0 so the widget
	 * can show a neutral placeholder instead of real data. For everyone else it
	 * returns 0 and the caller shows the login notice.
	 *
	 * @return int
	 */
	public static function current_member_id() {
		return FF_Gating::is_member() ? get_current_user_id() : 0;
	}

	/**
	 * Render the [ff_history] shortcode.
	 *
	 * Always reads the CURRENT member's id from the session — never an id from
	 * the request — so a member can only ever see their own record.
	 *
	 * @return string
	 */
	public static function shortcode() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );

		if ( ! FF_Gating::is_member() ) {
			return FF_Display::members_only_notice();
		}

		$member_id = get_current_user_id();

		$out  = '<div class="ff-history">';
		$out .= self::render_header( $member_id );
		$out .= self::render_votes( $member_id );
		$out .= self::render_notes( $member_id );
		$out .= self::render_feedback( $member_id );
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render the header: the member's number and group.
	 *
	 * @param int $member_id The current member's id.
	 * @return string
	 */
	public static function render_header( $member_id, $subheading = null ) {
		$number = get_user_meta( $member_id, FF_Members::META_NUMBER, true );
		$group  = FF_Gating::is_the_35( $member_id ) ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' );
		$sub    = ( null === $subheading ) ? self::default_subheading() : $subheading;

		$out  = '<header class="ff-history-header">';
		if ( $number ) {
			// The 35's identity honours their portal display preference (number
			// only by default; optionally first name or full name plus number).
			$out .= '<div class="ff-history-number">' . esc_html( FF_Members::portal_display_name( $member_id ) ) . '</div>';
		}
		$out .= '<div class="ff-history-group">' . esc_html( $group ) . '</div>';
		if ( '' !== trim( (string) $sub ) ) {
			$out .= '<p class="ff-history-intro">' . esc_html( $sub ) . '</p>';
		}
		$out .= '</header>';

		return $out;
	}

	/**
	 * The default header subheading sentence.
	 *
	 * @return string
	 */
	public static function default_subheading() {
		return __( 'Your history — everything you\'ve taken part in, and yours alone.', 'founding-faces' );
	}

	/**
	 * Render the polls the member voted in and how they voted.
	 *
	 * @param int    $member_id The current member's id.
	 * @param string $heading   Optional heading override.
	 * @return string
	 */
	public static function render_votes( $member_id, $heading = '' ) {
		$votes   = FF_Polls::member_votes( $member_id );
		$heading = '' !== $heading ? $heading : __( 'Your votes', 'founding-faces' );

		$out  = '<section class="ff-history-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';

		if ( empty( $votes ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'You haven\'t voted in a poll yet.', 'founding-faces' ) . '</p>';
			return $out . '</section>';
		}

		$out .= '<ul class="ff-history-list">';
		foreach ( $votes as $vote ) {
			$question = get_the_title( (int) $vote->poll_id );
			$choice   = FF_Polls::option_label( (int) $vote->poll_id, (int) $vote->option_id );

			$out .= '<li class="ff-history-item">';
			$out .= '<div class="ff-history-item-body">';
			$out .= '<span class="ff-history-item-main">' . esc_html( $question ? $question : __( '(poll removed)', 'founding-faces' ) ) . '</span>';
			if ( '' !== $choice ) {
				$out .= '<span class="ff-history-item-detail">' . sprintf(
					/* translators: %s is the option the member chose. */
					esc_html__( 'You chose: %s', 'founding-faces' ),
					esc_html( $choice )
				) . '</span>';
			}
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::format_date( $vote->voted_at ) ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul>';

		return $out . '</section>';
	}

	/**
	 * Render the notes the member has engaged with.
	 *
	 * @param int    $member_id The current member's id.
	 * @param string $heading   Optional heading override.
	 * @param bool   $link      Whether to link each note to its own page.
	 * @return string
	 */
	public static function render_notes( $member_id, $heading = '', $link = true ) {
		$rows    = FF_Interactions::get_for_member( $member_id, 'note_viewed' );
		$heading = '' !== $heading ? $heading : __( 'Notes you\'ve read', 'founding-faces' );

		$out  = '<section class="ff-history-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'You haven\'t opened any notes yet.', 'founding-faces' ) . '</p>';
			return $out . '</section>';
		}

		$out .= '<ul class="ff-history-list">';
		foreach ( $rows as $row ) {
			$ref   = (int) $row->reference_id;
			$title = get_the_title( $ref );
			$title = $title ? $title : __( '(note removed)', 'founding-faces' );

			// Link the title to the note's own page when asked and possible.
			$main = esc_html( $title );
			if ( $link ) {
				$url = get_permalink( $ref );
				if ( $url ) {
					$main = '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
				}
			}

			$out .= '<li class="ff-history-item">';
			$out .= '<div class="ff-history-item-body">';
			$out .= '<span class="ff-history-item-main">' . $main . '</span>';
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::format_date( $row->created_at ) ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul>';

		return $out . '</section>';
	}

	/**
	 * Render the feedback the member has submitted.
	 *
	 * Reads feedback rows from the interaction spine. Feedback capture is a
	 * later addition; this section shows entries whenever they exist and stays
	 * quietly empty until then.
	 *
	 * @param int    $member_id The current member's id.
	 * @param string $heading   Optional heading override.
	 * @return string
	 */
	public static function render_feedback( $member_id, $heading = '' ) {
		// Read the feedback the member has submitted through the private channel,
		// so it shows in one consistent place.
		$rows    = class_exists( 'FF_Messages' ) ? FF_Messages::feedback_threads_for_member( $member_id ) : array();
		$heading = '' !== $heading ? $heading : __( 'Feedback you\'ve shared', 'founding-faces' );

		$out  = '<section class="ff-history-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'You haven\'t shared any feedback yet.', 'founding-faces' ) . '</p>';
			return $out . '</section>';
		}

		$out .= '<ul class="ff-history-list">';
		foreach ( $rows as $row ) {
			$ref   = (int) $row->reference_id;
			$title = $ref ? get_the_title( $ref ) : '';
			$title = $title ? $title : __( 'Feedback', 'founding-faces' );
			$url   = $ref ? get_permalink( $ref ) : '';
			$head  = $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
			$text  = (string) $row->body;

			$out .= '<li class="ff-history-item ff-history-item--feedback">';
			$out .= '<div class="ff-history-feedback-head">';
			$out .= '<span class="ff-history-item-main">' . $head . '</span>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::format_date( $row->created_at ) ) . '</span>';
			$out .= '</div>';
			if ( '' !== trim( $text ) ) {
				$out .= '<div class="ff-history-feedback-text">' . wpautop( esc_html( $text ) ) . '</div>';
			}
			$out .= '</li>';
		}
		$out .= '</ul>';

		return $out . '</section>';
	}

	/**
	 * Format a stored datetime to the site's date format.
	 *
	 * @param string $datetime A stored MySQL datetime.
	 * @return string
	 */
	private static function format_date( $datetime ) {
		return mysql2date( get_option( 'date_format' ), $datetime );
	}
}
