<?php
/**
 * Polls: the poll content type, the admin editor and who-voted view, the
 * front-end voting logic, and the aggregate results.
 *
 * A poll is a question plus two or more options, each of which can carry an
 * image. Nick chooses the audience per poll and whether it's open or closed.
 * Votes are stored against the member in ff_poll_votes and logged to the
 * interaction spine; the front end only ever shows aggregates, while an
 * admin-only view shows exactly who voted for what.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Polls
 *
 * Owns the ff_poll post type, its meta, voting and results.
 */
class FF_Polls {

	// The poll post type slug.
	const POLL_CPT = 'ff_poll';

	// Poll meta keys.
	const META_OPTIONS  = 'ff_poll_options';   // Array of ['id','label','image_id'].
	const META_AUDIENCE = 'ff_poll_audience';  // 'everyone' | 'the-35-only'.
	const META_STATUS   = 'ff_poll_status';    // 'open' | 'closed'.
	const META_ACTIVE   = 'ff_poll_active';    // 1 if this is the current active poll.
	const META_OUTCOME  = 'ff_poll_outcome';   // Nick's reasoning, shown on close.
	const META_CLOSE_AT = 'ff_poll_close_at';  // GMT Unix time to auto-close (show results), or 0.
	const META_HIDE_AT  = 'ff_poll_hide_at';   // GMT Unix time to auto-hide entirely, or 0.

	// The AJAX action name for casting a vote.
	const VOTE_ACTION = 'ff_vote';

	/**
	 * Wire up the post type, shortcode and voting endpoint.
	 */
	public static function register() {
		// Register the poll post type now. This runs on 'init' (register() is
		// called from the plugin's own init handler), so we register directly
		// rather than nesting another 'init' hook — a nested same-priority hook
		// added mid-'init' isn't reliably run, which could hide the Polls menu.
		self::register_cpt();
		add_shortcode( 'ff_poll', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'ff_polls_archive', array( __CLASS__, 'archive_shortcode' ) );

		// Voting is by logged-in members only, so only the priv handler is used.
		add_action( 'wp_ajax_' . self::VOTE_ACTION, array( __CLASS__, 'ajax_vote' ) );

		// Make the poll title field prompt for the question.
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );

		// Register the Elementor widget (harmless if Elementor isn't installed).
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
	}

	/**
	 * Register the Founding Faces widget category in Elementor.
	 *
	 * @param object $elements_manager Elementor's elements manager.
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category( 'founding-faces', array(
			'title' => __( 'Founding Faces', 'founding-faces' ),
			'icon'  => 'fa fa-plug',
		) );
	}

	/**
	 * Register the poll widget with Elementor.
	 *
	 * @param object $widgets_manager Elementor's widgets manager.
	 */
	public static function register_widget( $widgets_manager ) {
		require_once FF_PATH . 'includes/class-ff-poll-widget.php';
		$widgets_manager->register( new FF_Poll_Widget() );
		$widgets_manager->register( new FF_Polls_Archive_Widget() );
	}

	/**
	 * Get all polls as id => question, for the widget's poll picker.
	 *
	 * @return array
	 */
	public static function poll_choices() {
		$choices = array( 0 => __( 'Current active poll', 'founding-faces' ) );
		$polls   = get_posts( array(
			'post_type'      => self::POLL_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		foreach ( $polls as $poll ) {
			$choices[ $poll->ID ] = $poll->post_title ? $poll->post_title : sprintf( __( 'Poll #%d', 'founding-faces' ), $poll->ID );
		}
		return $choices;
	}

	/**
	 * Wire up the admin editor and results metaboxes.
	 *
	 * Called in the admin area only.
	 */
	public static function register_admin() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metaboxes' ) );
		add_action( 'save_post_' . self::POLL_CPT, array( __CLASS__, 'save_poll' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	/**
	 * Register the poll post type.
	 *
	 * Non-public and out of REST, like notes and products: polls reach a page
	 * only through the widget or shortcode, and the audience gate is enforced
	 * there.
	 */
	public static function register_cpt() {
		$labels = array(
			'name'          => __( 'Polls', 'founding-faces' ),
			'singular_name' => __( 'Poll', 'founding-faces' ),
			'add_new_item'  => __( 'Add New Poll', 'founding-faces' ),
			'edit_item'     => __( 'Edit Poll', 'founding-faces' ),
			'new_item'      => __( 'New Poll', 'founding-faces' ),
			'all_items'     => __( 'Polls', 'founding-faces' ),
			'menu_name'     => __( 'Polls', 'founding-faces' ),
		);

		register_post_type( self::POLL_CPT, array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-chart-bar',
			'menu_position'       => 28,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => array( 'title' ),
			'show_in_rest'        => false,
		) );
	}

	/**
	 * Use the poll title field to ask for the question.
	 *
	 * @param string  $text The default placeholder.
	 * @param WP_Post $post The post being edited.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( $post && self::POLL_CPT === $post->post_type ) {
			return __( 'Poll question', 'founding-faces' );
		}
		return $text;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Audience gate.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The audiences a poll can target.
	 *
	 * @return array
	 */
	public static function audiences() {
		return array(
			'everyone'    => __( 'Everyone (all members)', 'founding-faces' ),
			'the-35-only' => __( 'The 35 only', 'founding-faces' ),
		);
	}

	/**
	 * Whether a user may see and vote on a poll.
	 *
	 * @param int      $poll_id The poll id.
	 * @param int|null $user_id A user id, or null for the current user.
	 * @return bool
	 */
	public static function can_view_poll( $poll_id, $user_id = null ) {
		if ( ! FF_Gating::is_member( $user_id ) ) {
			return false;
		}
		$audience = get_post_meta( $poll_id, self::META_AUDIENCE, true );
		if ( 'the-35-only' === $audience ) {
			return FF_Gating::is_the_35( $user_id );
		}
		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Voting and results (data).
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Whether a member has already voted on a poll.
	 *
	 * @param int $poll_id   The poll id.
	 * @param int $member_id The member id.
	 * @return bool
	 */
	public static function has_voted( $poll_id, $member_id ) {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ff_poll_votes WHERE poll_id = %d AND member_id = %d LIMIT 1",
				$poll_id,
				$member_id
			)
		);
		return null !== $found;
	}

	/**
	 * The option id a member voted for, or 0 if they haven't voted.
	 *
	 * @param int $poll_id   The poll id.
	 * @param int $member_id The member id.
	 * @return int
	 */
	public static function member_vote( $poll_id, $member_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_id FROM {$wpdb->prefix}ff_poll_votes WHERE poll_id = %d AND member_id = %d LIMIT 1",
				$poll_id,
				$member_id
			)
		);
	}

	/**
	 * The aggregate tally for a poll: option id => vote count.
	 *
	 * @param int $poll_id The poll id.
	 * @return array
	 */
	public static function tally( $poll_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, COUNT(*) AS votes FROM {$wpdb->prefix}ff_poll_votes WHERE poll_id = %d GROUP BY option_id",
				$poll_id
			)
		);
		$tally = array();
		foreach ( $rows as $row ) {
			$tally[ (int) $row->option_id ] = (int) $row->votes;
		}
		return $tally;
	}

	/**
	 * Record a member's vote and log it to the spine.
	 *
	 * Guards against a second vote via the pre-check and the table's unique key.
	 *
	 * @param int $poll_id   The poll id.
	 * @param int $member_id The member id.
	 * @param int $option_id The chosen option id.
	 * @return true|WP_Error
	 */
	public static function record_vote( $poll_id, $member_id, $option_id ) {
		global $wpdb;

		if ( self::has_voted( $poll_id, $member_id ) ) {
			return new WP_Error( 'ff_already_voted', __( 'You have already voted in this poll.', 'founding-faces' ) );
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'ff_poll_votes',
			array(
				'member_id' => $member_id,
				'poll_id'   => $poll_id,
				'option_id' => $option_id,
				'voted_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'ff_vote_failed', __( 'Your vote could not be recorded. Please try again.', 'founding-faces' ) );
		}

		// The spine: a vote cast, attributed to the member and the poll.
		FF_Interactions::log( $member_id, 'vote_cast', $poll_id );

		return true;
	}

	/**
	 * A member's own poll votes, newest first.
	 *
	 * Reads only this member's rows from ff_poll_votes.
	 *
	 * @param int $member_id The member's user id.
	 * @return array Rows of poll_id, option_id, voted_at.
	 */
	public static function member_votes( $member_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT poll_id, option_id, voted_at FROM {$wpdb->prefix}ff_poll_votes WHERE member_id = %d ORDER BY voted_at DESC",
				(int) $member_id
			)
		);
	}

	/**
	 * The label of a specific option within a poll.
	 *
	 * @param int $poll_id   The poll id.
	 * @param int $option_id The option id.
	 * @return string The label, or an empty string if not found.
	 */
	public static function option_label( $poll_id, $option_id ) {
		foreach ( self::get_options( $poll_id ) as $opt ) {
			if ( (int) $opt['id'] === (int) $option_id ) {
				return $opt['label'];
			}
		}
		return '';
	}

	/**
	 * The id of the current active poll, or 0 if none.
	 *
	 * @return int
	 */
	public static function get_active_poll_id() {
		$ids = get_posts( array(
			'post_type'      => self::POLL_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_key'       => self::META_ACTIVE, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	/**
	 * The effective state of a poll: 'open', 'closed' (results shown), or 'hidden'.
	 *
	 * Combines the manual Status with the optional scheduled close/hide times:
	 *  - Past the hide time  -> hidden (gone from the site).
	 *  - Manually closed with NO close/hide times set -> hidden immediately.
	 *  - Past the close time, or manually closed with a time set -> closed
	 *    (results and reasoning shown, until the hide time passes).
	 *  - Otherwise -> open (accepting votes).
	 *
	 * @param int $poll_id The poll id.
	 * @return string 'open' | 'closed' | 'hidden'.
	 */
	public static function poll_state( $poll_id ) {
		$now      = time();
		$close_at = (int) get_post_meta( $poll_id, self::META_CLOSE_AT, true );
		$hide_at  = (int) get_post_meta( $poll_id, self::META_HIDE_AT, true );
		$manual   = ( 'closed' === get_post_meta( $poll_id, self::META_STATUS, true ) );

		if ( $hide_at > 0 && $now >= $hide_at ) {
			return 'hidden';
		}

		$closed = ( $close_at > 0 && $now >= $close_at );

		if ( $manual ) {
			// Manually closed with neither date set disappears straight away.
			if ( 0 === $close_at && 0 === $hide_at ) {
				return 'hidden';
			}
			$closed = true;
		}

		return $closed ? 'closed' : 'open';
	}

	/**
	 * Whether a poll is showing results (its effective state is 'closed').
	 *
	 * @param int $poll_id The poll id.
	 * @return bool
	 */
	public static function is_closed( $poll_id ) {
		return 'closed' === self::poll_state( $poll_id );
	}

	/**
	 * Convert a datetime-local admin value (site time) to a GMT Unix timestamp.
	 *
	 * @param string $value 'Y-m-d\TH:i' or 'Y-m-d H:i' in the site's timezone.
	 * @return int A GMT Unix timestamp, or 0 if empty/invalid.
	 */
	public static function local_to_gmt_ts( $value ) {
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		if ( '' === $value ) {
			return 0;
		}
		$gmt = get_gmt_from_date( $value, 'Y-m-d H:i:s' );
		$ts  = $gmt ? strtotime( $gmt . ' +0000' ) : false;
		return $ts ? (int) $ts : 0;
	}

	/**
	 * Convert a stored GMT timestamp to a datetime-local field value (site time).
	 *
	 * @param int $ts A GMT Unix timestamp, or 0.
	 * @return string 'Y-m-d\TH:i', or '' if 0.
	 */
	public static function gmt_ts_to_local( $ts ) {
		$ts = (int) $ts;
		if ( ! $ts ) {
			return '';
		}
		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ), 'Y-m-d\TH:i' );
	}

	/**
	 * The ids of every poll the current member may see, newest first.
	 *
	 * @return int[]
	 */
	public static function viewable_poll_ids() {
		$ids = get_posts( array(
			'post_type'      => self::POLL_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );
		$ids = array_filter( array_map( 'intval', $ids ), array( __CLASS__, 'can_view_poll' ) );
		// Drop polls past their hide time (or manually closed with no schedule).
		$ids = array_filter( $ids, function ( $id ) {
			return 'hidden' !== self::poll_state( $id );
		} );
		return array_values( $ids );
	}

	/**
	 * The [ff_polls_archive] shortcode: open poll(s) then past polls with results.
	 *
	 * For a dedicated polls page: any open poll shows first (votable, or results
	 * if the member has voted), followed by every past poll with its results and
	 * outcome. All gated — a poll the member can't see never appears.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function archive_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'headings' => 'yes',
				'show'     => 'both', // 'both' | 'open' | 'past'.
			),
			$atts,
			'ff_polls_archive'
		);

		if ( ! FF_Gating::can_view_members_area() ) {
			return FF_Display::members_only_notice();
		}

		self::enqueue_front();

		$show   = in_array( $atts['show'], array( 'both', 'open', 'past' ), true ) ? $atts['show'] : 'both';
		$ids    = self::viewable_poll_ids();
		$open   = array();
		$closed = array();
		foreach ( $ids as $id ) {
			if ( self::is_closed( $id ) ) {
				$closed[] = $id;
			} else {
				$open[] = $id;
			}
		}

		// Honour the "show" choice, so two widgets (one open, one past) can each
		// be styled separately on the same page.
		if ( 'past' === $show ) {
			$open = array();
		} elseif ( 'open' === $show ) {
			$closed = array();
		}

		// Headings are only useful when both sections can appear together.
		$headings = ( 'yes' === $atts['headings'] ) && ( 'both' === $show );
		$out      = '<div class="ff-polls-archive ff-polls-archive--' . esc_attr( $show ) . '">';

		if ( empty( $open ) && empty( $closed ) ) {
			$out .= '<p class="ff-empty-note">' . esc_html(
				'open' === $show
					? __( 'No open poll right now.', 'founding-faces' )
					: __( 'No polls yet — check back soon.', 'founding-faces' )
			) . '</p>';
			return $out . '</div>';
		}

		if ( ! empty( $open ) ) {
			if ( $headings ) {
				$out .= '<h2 class="ff-polls-archive-heading">' . esc_html__( 'Open now', 'founding-faces' ) . '</h2>';
			}
			$out .= '<div class="ff-polls-archive-grid">';
			foreach ( $open as $id ) {
				$out .= '<div class="ff-polls-archive-item">' . self::render_poll( $id ) . '</div>';
			}
			$out .= '</div>';
		}

		if ( ! empty( $closed ) ) {
			if ( $headings ) {
				$out .= '<h2 class="ff-polls-archive-heading">' . esc_html__( 'Past polls', 'founding-faces' ) . '</h2>';
			}
			$out .= '<div class="ff-polls-archive-grid">';
			foreach ( $closed as $id ) {
				$out .= '<div class="ff-polls-archive-item">' . self::render_poll( $id ) . '</div>';
			}
			$out .= '</div>';
		}

		$out .= '</div>';
		return $out;
	}

	/*
	 * -----------------------------------------------------------------------
	 * The AJAX vote handler.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Handle a vote submitted over AJAX.
	 *
	 * Validates the nonce, the member, the poll's open state and audience, and
	 * the chosen option, records the vote, then returns the aggregate results
	 * HTML to reveal in place.
	 *
	 * @return void
	 */
	public static function ajax_vote() {
		check_ajax_referer( self::VOTE_ACTION, 'nonce' );

		$member_id = get_current_user_id();
		if ( ! $member_id || ! FF_Gating::is_member( $member_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Only members can vote.', 'founding-faces' ) ) );
		}

		$poll_id   = isset( $_POST['poll_id'] ) ? absint( wp_unslash( $_POST['poll_id'] ) ) : 0;
		$option_id = isset( $_POST['option_id'] ) ? absint( wp_unslash( $_POST['option_id'] ) ) : 0;

		$poll = $poll_id ? get_post( $poll_id ) : null;
		if ( ! $poll || self::POLL_CPT !== $poll->post_type ) {
			wp_send_json_error( array( 'message' => __( 'That poll could not be found.', 'founding-faces' ) ) );
		}

		// Audience gate.
		if ( ! self::can_view_poll( $poll_id, $member_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This poll isn\'t available to you.', 'founding-faces' ) ) );
		}

		// Must be open (not closed by hand or by its scheduled close time).
		if ( 'open' !== self::poll_state( $poll_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This poll is closed.', 'founding-faces' ) ) );
		}

		// The option must belong to this poll.
		$valid_ids = wp_list_pluck( self::get_options( $poll_id ), 'id' );
		if ( ! in_array( $option_id, array_map( 'intval', $valid_ids ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid option.', 'founding-faces' ) ) );
		}

		$result = self::record_vote( $poll_id, $member_id, $option_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Reveal the aggregate results, marking the member's own choice.
		wp_send_json_success( array(
			'html' => self::render_results( $poll_id, $option_id, false ),
		) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Front-end rendering.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The [ff_poll] shortcode: render a poll by id, or the active one.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'ff_poll' );
		return self::render_poll( absint( $atts['id'] ) );
	}

	/**
	 * Render a poll for the current member, in whatever state applies.
	 *
	 * A 35-only poll is invisible to anyone who isn't in The 35 (nothing is
	 * rendered, so its very existence stays private). Style options set inline
	 * CSS variables on the wrapper, defaulting to Apotheca tokens.
	 *
	 * @param int   $poll_id The poll id, or 0 for the active poll.
	 * @param array $style   Optional style overrides: accent, align, spacing.
	 * @return string
	 */
	public static function render_poll( $poll_id = 0, $style = array() ) {
		self::enqueue_front();

		if ( ! $poll_id ) {
			$poll_id = self::get_active_poll_id();
		}

		$poll = $poll_id ? get_post( $poll_id ) : null;
		if ( ! $poll || self::POLL_CPT !== $poll->post_type || 'publish' !== $poll->post_status ) {
			return '';
		}

		// Non-members and members outside the audience see nothing at all.
		if ( ! self::can_view_poll( $poll_id ) ) {
			return '';
		}

		// Past its hide time (or manually closed with no schedule): gone entirely.
		if ( 'hidden' === self::poll_state( $poll_id ) ) {
			return '';
		}

		$style = wp_parse_args( $style, array(
			'accent'  => '#3a3d44',
			'align'   => 'left',
			'spacing' => 16,
		) );

		$align = in_array( $style['align'], array( 'left', 'center', 'right' ), true ) ? $style['align'] : 'left';

		$wrapper_style = sprintf(
			'--ff-poll-accent:%1$s;--ff-poll-gap:%2$dpx;text-align:%3$s;',
			esc_attr( $style['accent'] ),
			absint( $style['spacing'] ),
			esc_attr( $align )
		);

		$inner = self::render_inner( $poll_id );

		return '<div class="ff-poll" data-poll="' . esc_attr( $poll_id ) . '" style="' . esc_attr( $wrapper_style ) . '">'
			. $inner
			. '</div>';
	}

	/**
	 * Render the inner state of a poll: vote form, results, or closed state.
	 *
	 * @param int $poll_id The poll id.
	 * @return string
	 */
	private static function render_inner( $poll_id ) {
		$state     = self::poll_state( $poll_id );
		$member_id = get_current_user_id();

		// Closed: show results and Nick's reasoning to everyone in the audience.
		if ( 'closed' === $state ) {
			return self::render_results( $poll_id, self::member_vote( $poll_id, $member_id ), true );
		}

		// Open and already voted: show results, marking their choice.
		if ( self::has_voted( $poll_id, $member_id ) ) {
			return self::render_results( $poll_id, self::member_vote( $poll_id, $member_id ), false );
		}

		// Open and not voted: show the voting form.
		return self::render_form( $poll_id );
	}

	/**
	 * Render the voting form (question and clickable options).
	 *
	 * @param int $poll_id The poll id.
	 * @return string
	 */
	private static function render_form( $poll_id ) {
		$options = self::get_options( $poll_id );

		$out  = '<div class="ff-poll-inner">';
		$out .= '<h3 class="ff-poll-question">' . esc_html( get_the_title( $poll_id ) ) . '</h3>';
		$out .= '<div class="ff-poll-error" role="alert"></div>';
		$out .= '<div class="ff-poll-options">';

		foreach ( $options as $opt ) {
			$out .= '<button type="button" class="ff-poll-option" data-option="' . esc_attr( $opt['id'] ) . '">';
			if ( ! empty( $opt['image_id'] ) ) {
				$img = wp_get_attachment_image( $opt['image_id'], 'medium', false, array( 'class' => 'ff-poll-option-img', 'loading' => 'lazy' ) );
				if ( $img ) {
					$out .= $img;
				}
			}
			$out .= '<span class="ff-poll-option-label">' . esc_html( $opt['label'] ) . '</span>';
			$out .= '</button>';
		}

		$out .= '</div>';
		$out .= '<p class="ff-poll-hint">' . esc_html__( 'Results are revealed once you vote.', 'founding-faces' ) . '</p>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Render the aggregate results (and, when closed, Nick's reasoning).
	 *
	 * Aggregate only: counts and percentages, never who voted. The member's own
	 * choice is highlighted if known.
	 *
	 * @param int  $poll_id     The poll id.
	 * @param int  $member_vote The option id the member chose, or 0.
	 * @param bool $closed      Whether the poll is closed.
	 * @return string
	 */
	public static function render_results( $poll_id, $member_vote = 0, $closed = false ) {
		$options = self::get_options( $poll_id );
		$tally   = self::tally( $poll_id );
		$total   = array_sum( $tally );
		$max     = ! empty( $tally ) ? max( $tally ) : 0;

		$out = '<div class="ff-poll-inner ff-poll-results">';

		// Closed capsule, above the question. Stylable via the widget controls.
		if ( $closed ) {
			$out .= '<div class="ff-poll-status"><span class="ff-poll-status-badge">'
				. esc_html__( 'Poll closed', 'founding-faces' ) . '</span></div>';
		}

		$out .= '<h3 class="ff-poll-question">' . esc_html( get_the_title( $poll_id ) ) . '</h3>';

		foreach ( $options as $opt ) {
			$votes   = isset( $tally[ (int) $opt['id'] ] ) ? (int) $tally[ (int) $opt['id'] ] : 0;
			$percent = $total > 0 ? round( ( $votes / $total ) * 100 ) : 0;
			$mine    = ( (int) $member_vote === (int) $opt['id'] );
			$leading = ( $max > 0 && $votes === $max ); // The winning option(s).

			$classes = 'ff-poll-result';
			if ( $mine ) {
				$classes .= ' is-mine';
			}
			if ( $leading ) {
				$classes .= ' ff-poll-result--leading';
			}
			$out .= '<div class="' . esc_attr( $classes ) . '">';
			$out .= '<div class="ff-poll-result-head">';
			$out .= '<span class="ff-poll-result-label">' . esc_html( $opt['label'] );
			if ( $mine ) {
				$out .= ' <span class="ff-poll-yours">' . esc_html__( 'your choice', 'founding-faces' ) . '</span>';
			}
			$out .= '</span>';
			$out .= '<span class="ff-poll-result-percent">' . esc_html( $percent ) . '%</span>';
			$out .= '</div>';
			$out .= '<div class="ff-poll-bar"><span class="ff-poll-bar-fill" style="width:' . esc_attr( $percent ) . '%;"></span></div>';
			$out .= '</div>';
		}

		$out .= '<p class="ff-poll-total">' . sprintf(
			/* translators: %s is the number of votes. */
			esc_html( _n( '%s vote', '%s votes', $total, 'founding-faces' ) ),
			esc_html( number_format_i18n( $total ) )
		) . '</p>';

		// On close, show the outcome and Nick's reasoning (the "closed" status is
		// now shown as the capsule above the question).
		if ( $closed ) {
			$outcome = get_post_meta( $poll_id, self::META_OUTCOME, true );
			if ( trim( (string) $outcome ) !== '' ) {
				$out .= '<div class="ff-poll-outcome">';
				$out .= '<span class="ff-poll-outcome-label">' . esc_html__( 'Where we landed', 'founding-faces' ) . '</span>';
				$out .= wpautop( wp_kses_post( $outcome ) );
				$out .= '</div>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Get a poll's options as a clean list of ['id','label','image_id'].
	 *
	 * @param int $poll_id The poll id.
	 * @return array
	 */
	public static function get_options( $poll_id ) {
		$options = get_post_meta( $poll_id, self::META_OPTIONS, true );
		return is_array( $options ) ? $options : array();
	}

	/**
	 * Enqueue the front-end poll script and styles (once).
	 */
	private static function enqueue_front() {
		wp_enqueue_style( 'founding-faces', FF_URL . 'assets/css/founding-faces.css', array(), FF_VERSION );

		wp_enqueue_script( 'ff-polls', FF_URL . 'assets/js/polls.js', array(), FF_VERSION, true );

		// Pass the AJAX url and a nonce to the script, once.
		static $localized = false;
		if ( ! $localized ) {
			wp_localize_script( 'ff-polls', 'ffPolls', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::VOTE_ACTION,
				'nonce'   => wp_create_nonce( self::VOTE_ACTION ),
			) );
			$localized = true;
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Admin: the poll editor and the who-voted view.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Add the poll editor and results metaboxes.
	 */
	public static function add_metaboxes() {
		add_meta_box( 'ff_poll_editor', __( 'Poll setup', 'founding-faces' ), array( __CLASS__, 'render_editor' ), self::POLL_CPT, 'normal', 'high' );
		add_meta_box( 'ff_poll_results', __( 'Results — who voted for what (admin only)', 'founding-faces' ), array( __CLASS__, 'render_admin_results' ), self::POLL_CPT, 'normal', 'default' );
	}

	/**
	 * Load the poll admin script (repeater + media) on the poll edit screen.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_admin( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || self::POLL_CPT !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'ff-admin', FF_URL . 'admin/admin-style.css', array(), FF_VERSION );
		wp_enqueue_script( 'ff-polls-admin', FF_URL . 'assets/js/polls-admin.js', array( 'jquery' ), FF_VERSION, true );
	}

	/**
	 * Render the poll setup metabox: options, audience, status, outcome.
	 *
	 * @param WP_Post $post The poll being edited.
	 */
	public static function render_editor( $post ) {
		wp_nonce_field( 'ff_save_poll', 'ff_poll_nonce' );

		$options  = self::get_options( $post->ID );
		$audience = get_post_meta( $post->ID, self::META_AUDIENCE, true ) ? get_post_meta( $post->ID, self::META_AUDIENCE, true ) : 'everyone';
		$status   = get_post_meta( $post->ID, self::META_STATUS, true ) ? get_post_meta( $post->ID, self::META_STATUS, true ) : 'open';
		$active   = (int) get_post_meta( $post->ID, self::META_ACTIVE, true );
		$outcome  = get_post_meta( $post->ID, self::META_OUTCOME, true );
		$close_at = self::gmt_ts_to_local( get_post_meta( $post->ID, self::META_CLOSE_AT, true ) );
		$hide_at  = self::gmt_ts_to_local( get_post_meta( $post->ID, self::META_HIDE_AT, true ) );

		// Ensure at least two empty rows to start with.
		if ( count( $options ) < 2 ) {
			$options = array_pad( $options, 2, array( 'id' => 0, 'label' => '', 'image_id' => 0 ) );
		}
		?>
		<p class="description"><?php esc_html_e( 'The poll question is the title above. Add two or more options; each can carry an image.', 'founding-faces' ); ?></p>

		<div id="ff-poll-options" class="ff-poll-editor">
			<?php foreach ( $options as $opt ) : ?>
				<?php self::render_option_row( $opt ); ?>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button" id="ff-poll-add-option"><?php esc_html_e( 'Add option', 'founding-faces' ); ?></button></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ff_poll_audience"><?php esc_html_e( 'Audience', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_poll_audience" id="ff_poll_audience">
						<?php foreach ( self::audiences() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $audience, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_poll_status"><?php esc_html_e( 'Status', 'founding-faces' ); ?></label></th>
				<td>
					<select name="ff_poll_status" id="ff_poll_status">
						<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Open (accepting votes)', 'founding-faces' ); ?></option>
						<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed (show results & reasoning)', 'founding-faces' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'With NO close/hide times set below, choosing "Closed" makes the poll disappear straight away. Set times below to close and hide it on a schedule instead.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_poll_close_at"><?php esc_html_e( 'Auto-close at', 'founding-faces' ); ?></label></th>
				<td>
					<input type="datetime-local" name="ff_poll_close_at" id="ff_poll_close_at" value="<?php echo esc_attr( $close_at ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. At this time the poll stops taking votes and shows the final results and your reasoning. Leave blank to close it by hand with the Status above.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_poll_hide_at"><?php esc_html_e( 'Auto-hide at', 'founding-faces' ); ?></label></th>
				<td>
					<input type="datetime-local" name="ff_poll_hide_at" id="ff_poll_hide_at" value="<?php echo esc_attr( $hide_at ); ?>" />
					<p class="description"><?php esc_html_e( 'Optional. At this time the poll disappears from the site entirely. Set it a little after the close time so members can see the final votes for a while first.', 'founding-faces' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Active poll', 'founding-faces' ); ?></th>
				<td>
					<label><input type="checkbox" name="ff_poll_active" value="1" <?php checked( $active, 1 ); ?> />
						<?php esc_html_e( 'Make this the current active poll (a widget set to "current active" shows this one).', 'founding-faces' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ff_poll_outcome"><?php esc_html_e( 'Outcome / reasoning (shown when closed)', 'founding-faces' ); ?></label></th>
				<td>
					<textarea name="ff_poll_outcome" id="ff_poll_outcome" rows="4" class="large-text"><?php echo esc_textarea( $outcome ); ?></textarea>
					<p class="description"><?php esc_html_e( 'e.g. "You leaned 60/40 to the darker grey, but it failed small-print contrast, so here\'s where we landed."', 'founding-faces' ); ?></p>
				</td>
			</tr>
		</table>

		<?php
		// A hidden template row the admin script clones for new options.
		echo '<script type="text/html" id="ff-poll-row-template">';
		self::render_option_row( array( 'id' => 0, 'label' => '', 'image_id' => 0 ) );
		echo '</script>';
	}

	/**
	 * Render one option row in the poll editor.
	 *
	 * @param array $opt An option: id, label, image_id.
	 */
	private static function render_option_row( $opt ) {
		$id       = isset( $opt['id'] ) ? (int) $opt['id'] : 0;
		$label    = isset( $opt['label'] ) ? $opt['label'] : '';
		$image_id = isset( $opt['image_id'] ) ? (int) $opt['image_id'] : 0;
		$thumb    = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
		?>
		<div class="ff-poll-row">
			<input type="hidden" name="ff_poll_opt_id[]" value="<?php echo esc_attr( $id ); ?>" />
			<span class="ff-poll-row-image"><?php echo $thumb; // Escaped by WP. ?></span>
			<input type="hidden" name="ff_poll_opt_image[]" class="ff-poll-opt-image" value="<?php echo esc_attr( $image_id ); ?>" />
			<input type="text" name="ff_poll_opt_label[]" class="regular-text ff-poll-opt-label" placeholder="<?php esc_attr_e( 'Option label', 'founding-faces' ); ?>" value="<?php echo esc_attr( $label ); ?>" />
			<button type="button" class="button ff-poll-pick-image"><?php esc_html_e( 'Image', 'founding-faces' ); ?></button>
			<button type="button" class="button-link ff-poll-remove"><?php esc_html_e( 'Remove', 'founding-faces' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Save the poll setup.
	 *
	 * @param int     $post_id The poll id.
	 * @param WP_Post $post    The poll object.
	 */
	public static function save_poll( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['ff_poll_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ff_poll_nonce'] ), 'ff_save_poll' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Build the options, assigning a stable id to any new row.
		$labels = isset( $_POST['ff_poll_opt_label'] ) ? (array) wp_unslash( $_POST['ff_poll_opt_label'] ) : array();
		$images = isset( $_POST['ff_poll_opt_image'] ) ? (array) wp_unslash( $_POST['ff_poll_opt_image'] ) : array();
		$ids    = isset( $_POST['ff_poll_opt_id'] ) ? (array) wp_unslash( $_POST['ff_poll_opt_id'] ) : array();

		$existing = array_map( 'intval', $ids );
		$next_id  = $existing ? ( max( $existing ) + 1 ) : 1;

		$options = array();
		foreach ( $labels as $i => $label ) {
			$label = sanitize_text_field( $label );
			if ( '' === $label ) {
				continue; // Skip blank rows.
			}
			$oid = isset( $ids[ $i ] ) ? (int) $ids[ $i ] : 0;
			if ( $oid <= 0 ) {
				$oid = $next_id;
				$next_id++;
			}
			$options[] = array(
				'id'       => $oid,
				'label'    => $label,
				'image_id' => isset( $images[ $i ] ) ? absint( $images[ $i ] ) : 0,
			);
		}
		update_post_meta( $post_id, self::META_OPTIONS, $options );

		// Audience.
		$audience = isset( $_POST['ff_poll_audience'] ) ? sanitize_key( wp_unslash( $_POST['ff_poll_audience'] ) ) : 'everyone';
		if ( ! array_key_exists( $audience, self::audiences() ) ) {
			$audience = 'everyone';
		}
		update_post_meta( $post_id, self::META_AUDIENCE, $audience );

		// Status.
		$status = ( isset( $_POST['ff_poll_status'] ) && 'closed' === $_POST['ff_poll_status'] ) ? 'closed' : 'open';
		update_post_meta( $post_id, self::META_STATUS, $status );

		// Scheduled close/hide times (stored as GMT timestamps; 0 = unset).
		$close_at = isset( $_POST['ff_poll_close_at'] ) ? self::local_to_gmt_ts( sanitize_text_field( wp_unslash( $_POST['ff_poll_close_at'] ) ) ) : 0;
		$hide_at  = isset( $_POST['ff_poll_hide_at'] ) ? self::local_to_gmt_ts( sanitize_text_field( wp_unslash( $_POST['ff_poll_hide_at'] ) ) ) : 0;
		update_post_meta( $post_id, self::META_CLOSE_AT, $close_at );
		update_post_meta( $post_id, self::META_HIDE_AT, $hide_at );

		// Outcome.
		update_post_meta( $post_id, self::META_OUTCOME, isset( $_POST['ff_poll_outcome'] ) ? wp_kses_post( wp_unslash( $_POST['ff_poll_outcome'] ) ) : '' );

		// Active flag — only one poll may be active at a time.
		$active = isset( $_POST['ff_poll_active'] ) ? 1 : 0;
		update_post_meta( $post_id, self::META_ACTIVE, $active );
		if ( $active ) {
			self::clear_other_active_polls( $post_id );
		}
	}

	/**
	 * Clear the active flag on every poll except the given one.
	 *
	 * @param int $keep_id The poll to keep active.
	 * @return void
	 */
	private static function clear_other_active_polls( $keep_id ) {
		$others = get_posts( array(
			'post_type'      => self::POLL_CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'exclude'        => array( $keep_id ),
			'meta_key'       => self::META_ACTIVE, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( $others as $other_id ) {
			update_post_meta( $other_id, self::META_ACTIVE, 0 );
		}
	}

	/**
	 * Render the admin-only "who voted for what" view.
	 *
	 * Shows each option with its count and the members who chose it (real name
	 * and number), plus a total. This is never exposed on the frontend.
	 *
	 * @param WP_Post $post The poll being edited.
	 */
	public static function render_admin_results( $post ) {
		global $wpdb;

		$options = self::get_options( $post->ID );
		if ( empty( $options ) ) {
			echo '<p>' . esc_html__( 'Add some options and save first.', 'founding-faces' ) . '</p>';
			return;
		}

		// All votes for this poll with their member ids.
		$votes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT member_id, option_id, voted_at FROM {$wpdb->prefix}ff_poll_votes WHERE poll_id = %d ORDER BY voted_at ASC",
				$post->ID
			)
		);

		// Group voters by option.
		$by_option = array();
		foreach ( $votes as $vote ) {
			$by_option[ (int) $vote->option_id ][] = $vote;
		}

		echo '<p><strong>' . esc_html( sprintf( /* translators: %d is a vote count. */ _n( '%d vote in total.', '%d votes in total.', count( $votes ), 'founding-faces' ), count( $votes ) ) ) . '</strong></p>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Option', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Votes', 'founding-faces' ) . '</th>';
		echo '<th>' . esc_html__( 'Who voted', 'founding-faces' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $options as $opt ) {
			$rows  = isset( $by_option[ (int) $opt['id'] ] ) ? $by_option[ (int) $opt['id'] ] : array();
			$names = array();
			foreach ( $rows as $row ) {
				$number = get_user_meta( $row->member_id, FF_Members::META_NUMBER, true );
				$real   = get_user_meta( $row->member_id, FF_Members::META_REAL_NAME, true );
				$label  = $real ? $real : ( '#' . (int) $row->member_id );
				if ( $number ) {
					$label .= ' (#' . (int) $number . ')';
				}
				$names[] = $label;
			}

			echo '<tr>';
			echo '<td>' . esc_html( $opt['label'] ) . '</td>';
			echo '<td>' . esc_html( count( $rows ) ) . '</td>';
			echo '<td>' . ( $names ? esc_html( implode( ', ', $names ) ) : '<span style="color:#a0a5aa;">' . esc_html__( 'No votes yet', 'founding-faces' ) . '</span>' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
