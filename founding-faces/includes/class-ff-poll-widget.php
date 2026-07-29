<?php
/**
 * The Founding Faces poll Elementor widget.
 *
 * Built for Elementor's Atomic architecture per the KDNA standard:
 * has_widget_inner_wrapper() reports correctly, the render output is a single
 * wrapper div, and no CSS depends on .elementor-widget-container. The widget is
 * the interactive element, so it carries a small set of real style controls
 * (alignment, accent colour, spacing) with Apotheca tokens as the defaults.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait FF_Poll_Style_Controls
 *
 * Shared Style-tab controls for both poll widgets: the result bars (default,
 * winning and your-choice colours, track, height, radius) and the "Poll closed"
 * capsule. Every selector is scoped to {{WRAPPER}}.
 */
trait FF_Poll_Style_Controls {

	/**
	 * Register the shared poll Style sections.
	 */
	protected function register_poll_style_controls() {

		/* ============================ RESULT BARS =========================== */
		$this->start_controls_section( 'ff_poll_bars', array(
			'label' => __( 'Result bars', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'bar_color', array(
			'label'     => __( 'Bar colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-bar-fill' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'bar_leading_color', array(
			'label'       => __( 'Winning bar colour', 'founding-faces' ),
			'description' => __( 'The option with the most votes.', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::COLOR,
			'selectors'   => array( '{{WRAPPER}} .ff-poll-result--leading .ff-poll-bar-fill' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'bar_mine_color', array(
			'label'       => __( 'Your-choice bar colour', 'founding-faces' ),
			'description' => __( 'The bar for the option the member voted for.', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::COLOR,
			'selectors'   => array( '{{WRAPPER}} .ff-poll-result.is-mine .ff-poll-bar-fill' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'bar_track_color', array(
			'label'     => __( 'Track (empty) colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-bar' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'bar_height', array(
			'label'     => __( 'Bar height', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 4, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-bar' => 'height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'bar_radius', array(
			'label'      => __( 'Bar corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 999 ) ),
			'selectors'  => array(
				'{{WRAPPER}} .ff-poll-bar'      => 'border-radius: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .ff-poll-bar-fill' => 'border-radius: {{SIZE}}{{UNIT}};',
			),
		) );
		$this->end_controls_section();

		/* ============================ POLL TEXT ============================= */
		$this->start_controls_section( 'ff_poll_text', array(
			'label' => __( 'Question & labels', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'question_typo',
			'label'    => __( 'Question', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-poll-question',
		) );
		$this->add_control( 'question_color', array(
			'label'     => __( 'Question colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-question' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'label_color', array(
			'label'     => __( 'Option label colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-result-label' => 'color: {{VALUE}};' ),
			'separator' => 'before',
		) );
		$this->add_control( 'percent_color', array(
			'label'     => __( 'Percentage colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-result-percent' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* =========================== CLOSED CAPSULE ======================== */
		$this->start_controls_section( 'ff_poll_capsule', array(
			'label' => __( '"Poll closed" capsule', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'capsule_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-poll-status' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'capsule_typo',
			'selector' => '{{WRAPPER}} .ff-poll-status-badge',
		) );
		$this->add_control( 'capsule_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-status-badge' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'capsule_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-status-badge' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'capsule_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-status-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'capsule_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-status-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'capsule_gap', array(
			'label'     => __( 'Space below capsule', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-status' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}
}

/**
 * Class FF_Poll_Widget
 *
 * Renders a chosen poll (or the current active one) through FF_Polls, passing
 * the style settings straight into the renderer.
 */
class FF_Poll_Widget extends \Elementor\Widget_Base {

	use FF_Poll_Style_Controls;

	/**
	 * The widget's machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ff_poll';
	}

	/**
	 * The widget's display title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Founding Faces Poll', 'founding-faces' );
	}

	/**
	 * The widget's icon in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-check-circle';
	}

	/**
	 * The categories this widget belongs to.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'founding-faces' );
	}

	/**
	 * Atomic architecture: don't add Elementor's extra inner wrapper div when
	 * optimised markup is active, so our render output stays a single wrapper.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Register the widget's controls: which poll, plus the style controls.
	 */
	protected function register_controls() {
		// --- Content: choose the poll ---
		$this->start_controls_section(
			'ff_poll_content',
			array(
				'label' => __( 'Poll', 'founding-faces' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'poll_id',
			array(
				'label'   => __( 'Which poll', 'founding-faces' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => FF_Polls::poll_choices(),
			)
		);

		$this->end_controls_section();

		// --- Style controls (Apotheca tokens as defaults) ---
		$this->start_controls_section(
			'ff_poll_style',
			array(
				'label' => __( 'Style', 'founding-faces' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'align',
			array(
				'label'   => __( 'Alignment', 'founding-faces' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'default' => 'left',
				'options' => array(
					'left'   => array(
						'title' => __( 'Left', 'founding-faces' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centre', 'founding-faces' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'founding-faces' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'   => __( 'Accent colour', 'founding-faces' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#3a3d44', // Apotheca deep cool grey.
			)
		);

		$this->add_control(
			'spacing',
			array(
				'label'      => __( 'Spacing', 'founding-faces' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 4, 'max' => 48 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 16 ),
			)
		);

		$this->end_controls_section();

		// The shared bar / text / capsule Style sections.
		$this->register_poll_style_controls();
	}

	/**
	 * Render the widget on the front end.
	 *
	 * Hands the chosen poll and the style settings to FF_Polls, which produces
	 * the single wrapper div and enforces the audience gate.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$poll_id = isset( $settings['poll_id'] ) ? absint( $settings['poll_id'] ) : 0;
		$spacing = isset( $settings['spacing']['size'] ) ? absint( $settings['spacing']['size'] ) : 16;

		// FF_Polls::render_poll outputs the single wrapper and its inner state.
		echo FF_Polls::render_poll( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$poll_id,
			array(
				'accent'  => isset( $settings['accent'] ) ? $settings['accent'] : '#3a3d44',
				'align'   => isset( $settings['align'] ) ? $settings['align'] : 'left',
				'spacing' => $spacing,
			)
		);
	}
}

/**
 * Class FF_Polls_Archive_Widget
 *
 * The polls page: any open poll first (votable / results), then every past poll
 * with its results and outcome, laid out in a chosen number of columns.
 */
class FF_Polls_Archive_Widget extends \Elementor\Widget_Base {

	use FF_Poll_Style_Controls;

	public function get_name() {
		return 'ff_polls_archive';
	}
	public function get_title() {
		return __( 'Founding Faces Polls Archive', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-slider-push';
	}
	public function get_categories() {
		return array( 'founding-faces' );
	}
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_pa_content', array(
			'label' => __( 'Polls archive', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'intro', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Shows any open poll first, then all past polls with their results. Tip: to style the open poll differently, place two of these widgets — one set to "Open poll only", one to "Past polls only".', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->add_control( 'show', array(
			'label'   => __( 'Show', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'both',
			'options' => array(
				'both' => __( 'Open poll, then past polls', 'founding-faces' ),
				'open' => __( 'Open poll only', 'founding-faces' ),
				'past' => __( 'Past polls only', 'founding-faces' ),
			),
		) );
		$this->add_control( 'headings', array(
			'label'        => __( 'Show "Open now" / "Past polls" headings', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'show' => 'both' ),
		) );
		$this->add_responsive_control( 'columns', array(
			'label'          => __( 'Columns', 'founding-faces' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '1',
			'tablet_default' => '1',
			'mobile_default' => '1',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'selectors'      => array(
				'{{WRAPPER}} .ff-polls-archive-grid' => 'display:grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr)); gap: 1.5rem; align-items: start;',
			),
		) );
		$this->end_controls_section();

		// The shared bar / text / capsule Style sections.
		$this->register_poll_style_controls();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$headings = ( ! isset( $s['headings'] ) || 'yes' === $s['headings'] ) ? 'yes' : 'no';
		$show     = isset( $s['show'] ) && in_array( $s['show'], array( 'both', 'open', 'past' ), true ) ? $s['show'] : 'both';
		echo FF_Polls::archive_shortcode( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'headings' => $headings,
			'show'     => $show,
		) );
	}
}
