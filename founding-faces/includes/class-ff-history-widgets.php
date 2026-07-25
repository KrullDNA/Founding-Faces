<?php
/**
 * Elementor widgets for a member's own activity (the personal-history page).
 *
 * These let a page be built from parts — a combined "My Activity" widget, plus
 * separate My Votes / My Notes / My Feedback widgets — so the look and feel can
 * be designed in Elementor. Every widget reads ONLY the signed-in member's own
 * data (their id comes from the session, never the request), so no member can
 * ever see another's activity.
 *
 * Atomic architecture throughout.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared base for the activity widgets.
 */
abstract class FF_Activity_Widget_Base extends \Elementor\Widget_Base {

	/** @return array */
	public function get_categories() {
		return array( 'founding-faces' );
	}

	/** @return array */
	public function get_style_depends() {
		return array( 'founding-faces' );
	}

	/** @return bool */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Render either the member's real data, on-brand sample data (in the
	 * Elementor editor, so it can be styled), or the members-only prompt.
	 *
	 * @param callable $real   Receives the member id and returns HTML.
	 * @param callable $sample Returns sample HTML for the editor.
	 * @return void
	 */
	protected function render_variants( $real, $sample ) {
		FF_History::enqueue();

		// A real, logged-in member: show their own data.
		if ( FF_Gating::is_member() ) {
			echo $real( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// In the Elementor editor / preview: show sample data so it can be
		// styled on-brand before there are any real members.
		if ( FF_History::is_editor() ) {
			echo $sample(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// An admin viewing the live page (not editing): a neutral note.
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="ff-notice">' . esc_html__( 'Each member sees their own activity here.', 'founding-faces' ) . '</div>';
			return;
		}

		// Everyone else: the members-only prompt.
		echo FF_Display::members_only_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * My Activity — the whole record, with section toggles and editable headings.
 */
class FF_My_Activity_Widget extends FF_Activity_Widget_Base {

	public function get_name() {
		return 'ff_my_activity';
	}
	public function get_title() {
		return __( 'Founding Faces My Activity', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-person';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_ma', array(
			'label' => __( 'My Activity', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'show_header', array(
			'label'        => __( 'Show number & group header', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'show_votes', array(
			'label'        => __( 'Show votes', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'votes_heading', array(
			'label'     => __( 'Votes heading', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Your votes', 'founding-faces' ),
			'condition' => array( 'show_votes' => 'yes' ),
		) );
		$this->add_control( 'show_notes', array(
			'label'        => __( 'Show notes read', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'notes_heading', array(
			'label'     => __( 'Notes heading', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Notes you\'ve read', 'founding-faces' ),
			'condition' => array( 'show_notes' => 'yes' ),
		) );
		$this->add_control( 'show_feedback', array(
			'label'        => __( 'Show feedback', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'feedback_heading', array(
			'label'     => __( 'Feedback heading', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Feedback you\'ve shared', 'founding-faces' ),
			'condition' => array( 'show_feedback' => 'yes' ),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$real = function ( $member_id ) use ( $s ) {
			$out = '<div class="ff-history">';
			if ( 'yes' === $s['show_header'] ) {
				$out .= FF_History::render_header( $member_id );
			}
			if ( 'yes' === $s['show_votes'] ) {
				$out .= FF_History::render_votes( $member_id, isset( $s['votes_heading'] ) ? $s['votes_heading'] : '' );
			}
			if ( 'yes' === $s['show_notes'] ) {
				$out .= FF_History::render_notes( $member_id, isset( $s['notes_heading'] ) ? $s['notes_heading'] : '' );
			}
			if ( 'yes' === $s['show_feedback'] ) {
				$out .= FF_History::render_feedback( $member_id, isset( $s['feedback_heading'] ) ? $s['feedback_heading'] : '' );
			}
			$out .= '</div>';
			return $out;
		};

		$sample = function () use ( $s ) {
			$out = '<div class="ff-history">';
			if ( 'yes' === $s['show_header'] ) {
				$out .= FF_History::sample_header();
			}
			if ( 'yes' === $s['show_votes'] ) {
				$out .= FF_History::sample_votes( isset( $s['votes_heading'] ) ? $s['votes_heading'] : '' );
			}
			if ( 'yes' === $s['show_notes'] ) {
				$out .= FF_History::sample_notes( isset( $s['notes_heading'] ) ? $s['notes_heading'] : '' );
			}
			if ( 'yes' === $s['show_feedback'] ) {
				$out .= FF_History::sample_feedback( isset( $s['feedback_heading'] ) ? $s['feedback_heading'] : '' );
			}
			$out .= '</div>';
			return $out;
		};

		$this->render_variants( $real, $sample );
	}
}

/**
 * My Votes — just the member's own poll votes.
 */
class FF_My_Votes_Widget extends FF_Activity_Widget_Base {

	public function get_name() {
		return 'ff_my_votes';
	}
	public function get_title() {
		return __( 'Founding Faces My Votes', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-check-circle';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_mv', array(
			'label' => __( 'My Votes', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Your votes', 'founding-faces' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$this->render_variants(
			function ( $member_id ) use ( $s ) {
				return '<div class="ff-history">' . FF_History::render_votes( $member_id, isset( $s['heading'] ) ? $s['heading'] : '' ) . '</div>';
			},
			function () use ( $s ) {
				return '<div class="ff-history">' . FF_History::sample_votes( isset( $s['heading'] ) ? $s['heading'] : '' ) . '</div>';
			}
		);
	}
}

/**
 * My Notes — the notes the member has read.
 */
class FF_My_Notes_Widget extends FF_Activity_Widget_Base {

	public function get_name() {
		return 'ff_my_notes';
	}
	public function get_title() {
		return __( 'Founding Faces My Notes Read', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-document-file';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_mn', array(
			'label' => __( 'My Notes Read', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Notes you\'ve read', 'founding-faces' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$this->render_variants(
			function ( $member_id ) use ( $s ) {
				return '<div class="ff-history">' . FF_History::render_notes( $member_id, isset( $s['heading'] ) ? $s['heading'] : '' ) . '</div>';
			},
			function () use ( $s ) {
				return '<div class="ff-history">' . FF_History::sample_notes( isset( $s['heading'] ) ? $s['heading'] : '' ) . '</div>';
			}
		);
	}
}

/**
 * My Feedback — the feedback the member has shared.
 */
class FF_My_Feedback_Widget extends FF_Activity_Widget_Base {

	public function get_name() {
		return 'ff_my_feedback';
	}
	public function get_title() {
		return __( 'Founding Faces My Feedback', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-comment';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_mf', array(
			'label' => __( 'My Feedback', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Feedback you\'ve shared', 'founding-faces' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$this->render_variants(
			function ( $member_id ) use ( $s ) {
				return '<div class="ff-history">' . FF_History::render_feedback( $member_id, isset( $s['heading'] ) ? $s['heading'] : '' ) . '</div>';
			},
			function () use ( $s ) {
				return '<div class="ff-history">' . FF_History::sample_feedback( isset( $s['heading'] ) ? $s['heading'] : '' ) . '</div>';
			}
		);
	}
}
