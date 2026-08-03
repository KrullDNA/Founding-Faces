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
	/**
	 * How many notes the unread-first ordering considers.
	 *
	 * The ordering needs the whole set to know what is unread, but this keeps
	 * that bounded. Only the current page's rows are ever rendered.
	 */
	const NOTE_ORDER_LIMIT = 500;

	public static function register() {
		add_shortcode( 'ff_history', array( __CLASS__, 'shortcode' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );

		// "Load more" on the member's own notes list. Logged-in only: a
		// logged-out visitor has no notes list to page through.
		add_action( 'wp_ajax_ff_load_notes', array( __CLASS__, 'ajax_load_notes' ) );
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
	public static function sample_notes( $heading = '', $link = true, $per_page = 0, $show = array(), $show_product = true ) {
		$heading = '' !== $heading ? $heading : __( 'Notes', 'founding-faces' );
		$rows    = array(
			array( __( 'Trial 14 — the new emulsifier', 'founding-faces' ), true, __( 'The Barrier Cream', 'founding-faces' ) ),
			array( __( 'Switching to a mild preservative system', 'founding-faces' ), true, __( 'The Cleansing Balm', 'founding-faces' ) ),
			array( __( 'Trial 12 — stability at 40°C', 'founding-faces' ), false, __( 'The Barrier Cream', 'founding-faces' ) ),
			array( __( 'Why we rejected the first serum base', 'founding-faces' ), false, __( 'The Serum', 'founding-faces' ) ),
		);

		$out  = '<section class="ff-history-section ff-notes-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';
		$out .= self::note_filter_bar( $show );
		$out .= '<div class="ff-notes-results">';
		$out .= '<ul class="ff-history-list ff-notes-read-list">';
		foreach ( $rows as $i => $row ) {
			$main = $link ? '<a href="#">' . esc_html( $row[0] ) . '</a>' : esc_html( $row[0] );
			$out .= '<li class="ff-history-item ff-note-row' . ( $row[1] ? ' is-unread' : '' ) . '">';
			$out .= '<div class="ff-history-item-body">';
			if ( $show_product ) {
				$out .= '<span class="ff-note-product">' . esc_html( $row[2] ) . '</span>';
			}
			$out .= '<span class="ff-history-item-main">' . $main;
			if ( $row[1] ) {
				$out .= ' <span class="ff-unread-badge">' . esc_html__( 'Unread', 'founding-faces' ) . '</span>';
			}
			$out .= '</span>';
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::sample_date( $i + 1 ) ) . '</span>';
			$out .= '</li>';
		}
		$out .= '</ul></div>';

		// The editor always shows the button, whatever the page size, so it can
		// be styled without first creating enough notes to trigger it.
		if ( absint( $per_page ) > 0 ) {
			$out .= '<div class="ff-notes-more"><button type="button" class="ff-notes-more-button">'
				. esc_html__( 'Load more', 'founding-faces' ) . '</button></div>';
		}

		$out .= '</section>';
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
	 * @param int    $member_id    The current member's id.
	 * @param string $heading      Optional heading override.
	 * @param bool   $link         Whether to link each note to its own page.
	 * @param int    $per_page     How many rows per batch; 0 for all of them.
	 * @param array  $show         Which filters to offer.
	 * @param bool   $show_product Whether to name the product above each title.
	 * @return string
	 */
	public static function render_notes( $member_id, $heading = '', $link = true, $per_page = 0, $show = array(), $show_product = true ) {
		$heading = '' !== $heading ? $heading : __( 'Notes', 'founding-faces' );

		$out  = '<section class="ff-history-section ff-notes-section">';
		$out .= '<h3 class="ff-history-heading">' . esc_html( $heading ) . '</h3>';
		$out .= self::note_filter_bar( $show );

		$entries = self::note_entries( $member_id );

		$per_page = absint( $per_page );
		$total    = count( $entries );
		$slice    = ( $per_page > 0 ) ? array_slice( $entries, 0, $per_page ) : $entries;

		// The list and the empty message both live inside the same wrapper, so
		// a filter change can swap the contents without touching the filter bar.
		$out .= '<div class="ff-notes-results">';
		if ( empty( $entries ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html__( 'There are no notes to read just yet.', 'founding-faces' ) . '</p>';
		} else {
			$out .= '<ul class="ff-history-list ff-notes-read-list">';
			$out .= self::note_rows( $slice, $link, $show_product );
			$out .= '</ul>';
		}
		$out .= '</div>';

		// The button is always in the markup so a filter change can reveal it;
		// it stays hidden until there is genuinely more to load.
		$has_more = ( $per_page > 0 && $total > $per_page );
		if ( $per_page > 0 || ! empty( $show ) ) {
			self::enqueue_load_more();
		}

		if ( $per_page > 0 ) {
			$out .= '<div class="ff-notes-more"' . ( $has_more ? '' : ' hidden' ) . '>';
			$out .= '<button type="button" class="ff-notes-more-button"'
				. ' data-offset="' . esc_attr( $per_page ) . '"'
				. ' data-per-page="' . esc_attr( $per_page ) . '"'
				. ' data-link="' . ( $link ? '1' : '0' ) . '"'
				. ' data-show-product="' . ( $show_product ? '1' : '0' ) . '"'
				. ' data-nonce="' . esc_attr( wp_create_nonce( 'ff_load_notes' ) ) . '">'
				. esc_html__( 'Load more', 'founding-faces' )
				. '</button>';
			$out .= '</div>';
		} elseif ( ! empty( $show ) ) {
			// No paging, but the filters still need a nonce to talk to AJAX.
			$out .= '<div class="ff-notes-more" hidden>';
			$out .= '<button type="button" class="ff-notes-more-button"'
				. ' data-offset="0" data-per-page="0"'
				. ' data-link="' . ( $link ? '1' : '0' ) . '"'
				. ' data-show-product="' . ( $show_product ? '1' : '0' ) . '"'
				. ' data-nonce="' . esc_attr( wp_create_nonce( 'ff_load_notes' ) ) . '">'
				. esc_html__( 'Load more', 'founding-faces' )
				. '</button>';
			$out .= '</div>';
		}

		return $out . '</section>';
	}

	/**
	 * Every note this member may see, unread first, then read.
	 *
	 * Unread notes lead because they are the ones a member needs to find; they
	 * keep the newest-note-first order. Read notes follow, ordered by when this
	 * member actually opened them, most recent first.
	 *
	 * @param int $member_id The current member's id.
	 * @return array[] Each entry is array( note id, date string, is unread ).
	 */
	public static function note_entries( $member_id, $filters = array() ) {
		$filters = wp_parse_args( $filters, array(
			'product' => 0,
			'stage'   => '',
			'status'  => '',      // '' | 'unread' | 'read'.
			'period'  => '',      // '' | '30' | '90' | '365'.
			'sort'    => 'unread', // 'unread' | 'newest' | 'oldest'.
		) );

		// Ids only, never whole post objects: the unread-first order has to be
		// worked out across the full set, but nothing is *rendered* here. Only
		// the rows in the requested slice ever load a title or a permalink, so
		// a member with hundreds of notes still pages cheaply.
		$args = array(
			'post_type'              => FF_Post_Types::NOTE_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => self::NOTE_ORDER_LIMIT,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		// Product and stage are indexed meta, so let the database do that work
		// rather than fetching everything and filtering in PHP.
		$meta_query = array();
		if ( ! empty( $filters['product'] ) ) {
			$meta_query[] = array(
				'key'   => FF_Post_Types::META_NOTE_PRODUCT,
				'value' => (int) $filters['product'],
			);
		}
		if ( '' !== $filters['stage'] ) {
			$meta_query[] = array(
				'key'   => FF_Post_Types::META_NOTE_STAGE,
				'value' => $filters['stage'],
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$note_ids = get_posts( $args );
		if ( empty( $note_ids ) ) {
			return array();
		}

		// One query for every note's meta, so the audience check and the date
		// lookup below read from cache instead of firing a query per note.
		update_meta_cache( 'post', $note_ids );

		// The gate runs per note, so a Circle member never sees a 35-only note.
		$note_ids = array_values( array_filter( $note_ids, array( 'FF_Gating', 'can_view_note' ) ) );
		if ( empty( $note_ids ) ) {
			return array();
		}

		$read_dates = array();
		foreach ( FF_Interactions::get_for_member( $member_id, 'note_viewed' ) as $row ) {
			$read_dates[ (int) $row->reference_id ] = $row->created_at;
		}

		// A period filter measures the note's own date, not when it was read —
		// "the last three months of work", not "what I happened to open".
		$cutoff = '';
		if ( '' !== $filters['period'] ) {
			$days   = max( 1, absint( $filters['period'] ) );
			$cutoff = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
		}

		$unread = array();
		$read   = array();
		foreach ( $note_ids as $note_id ) {
			$note_id   = (int) $note_id;
			$is_unread = ! isset( $read_dates[ $note_id ] );

			if ( 'unread' === $filters['status'] && ! $is_unread ) {
				continue;
			}
			if ( 'read' === $filters['status'] && $is_unread ) {
				continue;
			}

			$note_date = self::note_date_key( $note_id );
			if ( '' !== $cutoff && $note_date < $cutoff ) {
				continue;
			}

			// Unread rows carry the note's own date; read rows carry the date
			// this member opened it, which is the more useful fact for those.
			$entry = array( $note_id, $is_unread ? $note_date : $read_dates[ $note_id ], $is_unread, $note_date );

			if ( $is_unread ) {
				$unread[] = $entry;
			} else {
				$read[] = $entry;
			}
		}

		// Sorting by date treats the two groups as one list, ordered by the
		// note's own date; "unread first" keeps them apart.
		if ( 'newest' === $filters['sort'] || 'oldest' === $filters['sort'] ) {
			$all = array_merge( $unread, $read );
			usort( $all, function ( $a, $b ) use ( $filters ) {
				$cmp = strcmp( $b[3], $a[3] );
				return ( 'oldest' === $filters['sort'] ) ? -$cmp : $cmp;
			} );
			return $all;
		}

		// Most recently read first; unread are already newest-note-first.
		usort( $read, function ( $a, $b ) {
			return strcmp( $b[1], $a[1] );
		} );

		return array_merge( $unread, $read );
	}

	/**
	 * A note's own date, falling back to its publish date.
	 *
	 * @param int $note_id The note.
	 * @return string A YYYY-MM-DD string.
	 */
	private static function note_date_key( $note_id ) {
		$date = get_post_meta( $note_id, FF_Post_Types::META_NOTE_DATE, true );
		return $date ? substr( (string) $date, 0, 10 ) : get_post_time( 'Y-m-d', false, $note_id );
	}

	/**
	 * The filter choices offered above a member's notes list.
	 *
	 * @return array
	 */
	public static function note_filter_options() {
		return array(
			'status' => array(
				''       => __( 'All notes', 'founding-faces' ),
				'unread' => __( 'Unread only', 'founding-faces' ),
				'read'   => __( 'Already read', 'founding-faces' ),
			),
			'period' => array(
				''    => __( 'Any time', 'founding-faces' ),
				'30'  => __( 'Last 30 days', 'founding-faces' ),
				'90'  => __( 'Last 3 months', 'founding-faces' ),
				'365' => __( 'Last 12 months', 'founding-faces' ),
			),
			'sort'   => array(
				'unread' => __( 'Unread first', 'founding-faces' ),
				'newest' => __( 'Newest first', 'founding-faces' ),
				'oldest' => __( 'Oldest first', 'founding-faces' ),
			),
		);
	}

	/**
	 * The filter bar above a member's notes list.
	 *
	 * Plain selects that drive the same AJAX endpoint as "load more", so
	 * changing one refreshes the list in place without a page reload.
	 *
	 * @param array $show Which filters to render.
	 * @return string
	 */
	public static function note_filter_bar( $show ) {
		$options = self::note_filter_options();
		$fields  = array();

		if ( ! empty( $show['product'] ) ) {
			$select = '<select class="ff-note-filter" data-filter="product">';
			foreach ( FF_Display::product_choices_all() as $id => $label ) {
				$select .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</option>';
			}
			$select .= '</select>';
			$fields[] = array( __( 'Product', 'founding-faces' ), $select );
		}

		if ( ! empty( $show['stage'] ) ) {
			$select = '<select class="ff-note-filter" data-filter="stage">';
			foreach ( FF_Display::stage_choices() as $key => $label ) {
				$select .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
			}
			$select .= '</select>';
			$fields[] = array( __( 'Type', 'founding-faces' ), $select );
		}

		foreach ( array( 'status' => __( 'Show', 'founding-faces' ), 'period' => __( 'Date', 'founding-faces' ), 'sort' => __( 'Sort', 'founding-faces' ) ) as $key => $label ) {
			if ( empty( $show[ $key ] ) ) {
				continue;
			}
			$select = '<select class="ff-note-filter" data-filter="' . esc_attr( $key ) . '">';
			foreach ( $options[ $key ] as $value => $text ) {
				$select .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</option>';
			}
			$select .= '</select>';
			$fields[] = array( $label, $select );
		}

		if ( empty( $fields ) ) {
			return '';
		}

		$out = '<div class="ff-note-filters">';
		foreach ( $fields as $field ) {
			$out .= '<label class="ff-filter"><span>' . esc_html( $field[0] ) . '</span>' . $field[1] . '</label>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * The <li> rows for a set of note entries.
	 *
	 * @param array[] $entries      Entries from note_entries().
	 * @param bool    $link         Whether to link each note to its own page.
	 * @param bool    $show_product Whether to name the product above the title.
	 * @return string
	 */
	public static function note_rows( $entries, $link = true, $show_product = true ) {
		$out      = '';
		$products = array();

		// Load the products named in this slice in one query, rather than one
		// per row. Only the rows actually being rendered are looked up.
		if ( $show_product ) {
			foreach ( $entries as $entry ) {
				$product_id = (int) get_post_meta( (int) $entry[0], FF_Post_Types::META_NOTE_PRODUCT, true );
				if ( $product_id ) {
					$products[ (int) $entry[0] ] = $product_id;
				}
			}
			if ( $products ) {
				_prime_post_caches( array_values( array_unique( $products ) ), false, false );
			}
		}

		foreach ( $entries as $entry ) {
			list( $note_id, $date, $is_unread ) = $entry;

			$title = get_the_title( $note_id );
			$title = $title ? $title : __( '(untitled note)', 'founding-faces' );

			$main = esc_html( $title );
			if ( $link ) {
				$url = get_permalink( $note_id );
				if ( $url ) {
					$main = '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
				}
			}

			$out .= '<li class="ff-history-item ff-note-row' . ( $is_unread ? ' is-unread' : '' ) . '">';
			$out .= '<div class="ff-history-item-body">';

			// The product sits above the title: it says what the note is about
			// before the note says what happened. A note with no product simply
			// doesn't get the line, rather than getting an empty one.
			if ( isset( $products[ $note_id ] ) ) {
				$product = get_the_title( $products[ $note_id ] );
				if ( $product ) {
					$out .= '<span class="ff-note-product">' . esc_html( $product ) . '</span>';
				}
			}

			$out .= '<span class="ff-history-item-main">' . $main;
			if ( $is_unread ) {
				$out .= ' <span class="ff-unread-badge">' . esc_html__( 'Unread', 'founding-faces' ) . '</span>';
			}
			$out .= '</span>';
			$out .= '</div>';
			$out .= '<span class="ff-history-item-date">' . esc_html( self::format_date( $date ) ) . '</span>';
			$out .= '</li>';
		}

		return $out;
	}

	/**
	 * Enqueue the small "load more" script, once, only where it's needed.
	 */
	private static function enqueue_load_more() {
		wp_enqueue_script(
			'ff-notes-more',
			FF_URL . 'assets/js/notes-more.js',
			array(),
			FF_VERSION,
			true
		);

		wp_localize_script( 'ff-notes-more', 'ffNotesMore', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'loading' => __( 'Loading…', 'founding-faces' ),
			'error'   => __( 'Could not load more just now. Please try again.', 'founding-faces' ),
		) );
	}

	/**
	 * AJAX: return the next page of note rows for the current member.
	 *
	 * The member id always comes from the session, never the request, so this
	 * can only ever return the caller's own list — and every note still passes
	 * the group gate inside note_entries().
	 */
	public static function ajax_load_notes() {
		check_ajax_referer( 'ff_load_notes', 'nonce' );

		if ( ! FF_Gating::is_member() ) {
			wp_send_json_error();
		}

		$offset   = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
		$link     = ! empty( $_POST['link'] );
		$product  = ! empty( $_POST['show_product'] );

		$options = self::note_filter_options();
		$filters = array(
			'product' => isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0,
			'stage'   => isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : '',
			'status'  => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '',
			'period'  => isset( $_POST['period'] ) ? sanitize_key( wp_unslash( $_POST['period'] ) ) : '',
			'sort'    => isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : 'unread',
		);

		// Validate every choice against the offered options, so a hand-edited
		// request can't reach for anything the filter bar doesn't offer.
		if ( '' !== $filters['stage'] && ! array_key_exists( $filters['stage'], FF_Post_Types::note_stages() ) ) {
			$filters['stage'] = '';
		}
		foreach ( array( 'status', 'period', 'sort' ) as $key ) {
			if ( ! array_key_exists( $filters[ $key ], $options[ $key ] ) ) {
				$filters[ $key ] = ( 'sort' === $key ) ? 'unread' : '';
			}
		}

		$entries = self::note_entries( get_current_user_id(), $filters );
		$total   = count( $entries );

		// per_page 0 means "no paging": return the whole filtered list.
		$slice = ( $per_page > 0 )
			? array_slice( $entries, $offset, min( 100, $per_page ) )
			: $entries;

		$rows = self::note_rows( $slice, $link, $product );
		$next = ( $per_page > 0 ) ? $offset + count( $slice ) : $total;

		wp_send_json_success( array(
			'html'    => $rows,
			'empty'   => ( 0 === $total ) ? esc_html__( 'No notes match these filters.', 'founding-faces' ) : '',
			'offset'  => $next,
			'hasMore' => ( $per_page > 0 ) && ( $next < $total ),
		) );
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
