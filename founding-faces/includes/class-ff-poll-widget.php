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

		/* ============================= POLL CARD ============================ */
		$this->start_controls_section( 'ff_poll_card', array(
			'label' => __( 'Poll card', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'card_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'card_border',
			'selector' => '{{WRAPPER}} .ff-poll',
		) );
		$this->add_responsive_control( 'card_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'card_shadow',
			'selector' => '{{WRAPPER}} .ff-poll',
		) );
		$this->add_responsive_control( 'card_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'card_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

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
			'description' => __( 'The option with the most votes. When the member voted for the winner, this is the colour that shows — the "Your choice" label still marks it as theirs.', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::COLOR,
			'selectors'   => array(
				'{{WRAPPER}} .ff-poll-result--leading .ff-poll-bar-fill' => 'background-color: {{VALUE}};',
				// A row that is both winning and the member's own: named with an
				// extra ancestor so it outranks the your-choice rule below,
				// whichever order Elementor prints them in.
				'{{WRAPPER}} .ff-poll .ff-poll-result--leading.is-mine .ff-poll-bar-fill' => 'background-color: {{VALUE}};',
			),
		) );
		$this->add_control( 'bar_track_color', array(
			'label'     => __( 'Track (empty) colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-bar' => 'background-color: {{VALUE}};' ),
		) );

		// The member's own choice: its bar plus its "your choice" label.
		$this->add_control( 'mine_heading', array(
			'label'       => __( 'Your choice', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::HEADING,
			'separator'   => 'before',
			'description' => __( 'Shown on the option the member voted for. If that option is also winning, the winning colour above takes priority.', 'founding-faces' ),
		) );
		$this->add_control( 'bar_mine_color', array(
			'label'     => __( 'Your-choice bar colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-result.is-mine .ff-poll-bar-fill' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'yours_color', array(
			'label'     => __( '"Your choice" label colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-yours' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'yours_typo',
			'label'    => __( '"Your choice" label text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-poll-yours',
		) );
		$this->add_control( 'yours_bg', array(
			'label'     => __( '"Your choice" background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-yours' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'yours_border',
			'selector' => '{{WRAPPER}} .ff-poll-yours',
		) );
		$this->add_responsive_control( 'yours_radius', array(
			'label'      => __( '"Your choice" corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-yours' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'yours_shadow',
			'selector' => '{{WRAPPER}} .ff-poll-yours',
		) );
		$this->add_responsive_control( 'yours_padding', array(
			'label'      => __( '"Your choice" padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-yours' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'yours_margin', array(
			'label'       => __( '"Your choice" margin', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units'  => array( 'px', 'em' ),
			'selectors'   => array( '{{WRAPPER}} .ff-poll-yours' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'description' => __( 'Overrides the gap below, which sets the left margin on its own.', 'founding-faces' ),
		) );
		$this->add_responsive_control( 'yours_valign', array(
			'label'     => __( '"Your choice" baseline nudge', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => -20, 'max' => 20 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-yours' => 'position: relative; top: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'yours_gap', array(
			'label'     => __( 'Gap before "your choice" label', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-yours' => 'margin-left: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'mine_row_heading', array(
			'label'     => __( 'The row you voted for', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'mine_row_bg', array(
			'label'     => __( 'Row background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-result.is-mine' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'mine_row_border',
			'selector' => '{{WRAPPER}} .ff-poll-result.is-mine',
		) );
		$this->add_responsive_control( 'mine_row_radius', array(
			'label'      => __( 'Row corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-result.is-mine' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'mine_row_padding', array(
			'label'      => __( 'Row padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-result.is-mine' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
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
		$this->add_responsive_control( 'question_align', array(
			'label'     => __( 'Question alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-poll-question' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'question_margin', array(
			'label'      => __( 'Question margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-question' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'question_padding', array(
			'label'      => __( 'Question padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-question' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
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
		$this->add_control( 'total_heading', array(
			'label'     => __( 'Vote count', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'total_typo',
			'label'    => __( 'Vote count text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-poll-total',
		) );
		$this->add_control( 'total_color', array(
			'label'     => __( 'Vote count colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-total' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'total_gap', array(
			'label'     => __( 'Space above vote count', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-total' => 'margin-top: {{SIZE}}{{UNIT}};' ),
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
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'capsule_border',
			'selector' => '{{WRAPPER}} .ff-poll-status-badge',
		) );
		$this->add_responsive_control( 'capsule_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-status-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'capsule_shadow',
			'selector' => '{{WRAPPER}} .ff-poll-status-badge',
		) );
		$this->add_responsive_control( 'capsule_gap', array(
			'label'     => __( 'Space below capsule', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-status' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* =========================== VOTING OPTIONS ======================== */
		$this->start_controls_section( 'ff_poll_options', array(
			'label' => __( 'Voting buttons', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'opt_typo',
			'selector' => '{{WRAPPER}} .ff-poll-option-label',
		) );
		$this->start_controls_tabs( 'opt_tabs' );
		$this->start_controls_tab( 'opt_tab_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'opt_color', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-option' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'opt_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-option' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'opt_tab_h', array( 'label' => __( 'Hover', 'founding-faces' ) ) );
		$this->add_control( 'opt_hcolor', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-option:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'opt_hbg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-option:hover' => 'background-color: {{VALUE}};' ),
		) );
		// Hover does nothing until it's set here, so the button's own border
		// and shadow are never overridden by something the editor can't see.
		$this->add_control( 'opt_border_hover', array(
			'label'     => __( 'Border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-option:hover' => 'border-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'opt_shadow_hover',
			'selector' => '{{WRAPPER}} .ff-poll-option:hover',
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'opt_border',
			'selector'  => '{{WRAPPER}} .ff-poll-option',
			'separator' => 'before',
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'opt_shadow',
			'selector' => '{{WRAPPER}} .ff-poll-option',
		) );
		$this->add_responsive_control( 'opt_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'opt_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'opt_gap', array(
			'label'     => __( 'Gap between buttons', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-poll-options' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'hint_color', array(
			'label'     => __( 'Hint text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-poll-hint' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'hint_typo',
			'label'    => __( 'Hint text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-poll-hint',
		) );
		$this->end_controls_section();

		/* ============================== OUTCOME ============================ */
		$this->start_controls_section( 'ff_poll_outcome', array(
			'label' => __( 'Outcome block', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'outcome_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-outcome' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'outcome_label_color', array(
			'label'     => __( '"Where we landed" colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-outcome-label' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'outcome_label_typo',
			'label'    => __( '"Where we landed" text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-poll-outcome-label',
		) );
		$this->add_control( 'outcome_color', array(
			'label'     => __( 'Body colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-poll-outcome p' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'outcome_typo',
			'label'    => __( 'Body text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-poll-outcome p',
		) );
		$this->add_responsive_control( 'outcome_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'separator'  => 'before',
			'selectors'  => array( '{{WRAPPER}} .ff-poll-outcome' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'outcome_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-outcome' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'outcome_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-outcome' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		// The block ships with no border of its own, so every edge here — one
		// side or four — is set in the editor.
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'outcome_border',
			'selector'  => '{{WRAPPER}} .ff-poll-outcome',
			'separator' => 'before',
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'outcome_shadow',
			'selector' => '{{WRAPPER}} .ff-poll-outcome',
		) );
		$this->add_responsive_control( 'outcome_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-poll-outcome' => 'text-align: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * Register the Content-tab fields for every fixed phrase a poll says.
	 *
	 * Each field is pre-filled with the wording the plugin would use anyway, so
	 * the panel shows what is on the page rather than an empty box. Clearing a
	 * field removes that line altogether — it is a way to say nothing, not a
	 * way to render an empty element.
	 */
	protected function register_poll_text_controls() {
		$defaults = FF_Polls::text_defaults();

		$this->start_controls_section( 'ff_poll_words', array(
			'label' => __( 'Wording', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$fields = array(
			'closed'     => array( __( '"Poll closed" capsule', 'founding-faces' ), '' ),
			'hint'       => array( __( 'Hint under the options', 'founding-faces' ), '' ),
			'yours'      => array( __( '"Your choice" label', 'founding-faces' ), '' ),
			'outcome'    => array( __( '"Where we landed" label', 'founding-faces' ), '' ),
			'total_one'  => array( __( 'Vote count — one vote', 'founding-faces' ), __( '%s is replaced by the number.', 'founding-faces' ) ),
			'total_many' => array( __( 'Vote count — several votes', 'founding-faces' ), __( '%s is replaced by the number.', 'founding-faces' ) ),
		);

		foreach ( $fields as $key => $field ) {
			$this->add_control( 'text_' . $key, array(
				'label'       => $field[0],
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $defaults[ $key ],
				'placeholder' => $defaults[ $key ],
				'label_block' => true,
				'description' => $field[1],
			) );
		}

		$this->add_control( 'text_note', array(
			'type'             => \Elementor\Controls_Manager::RAW_HTML,
			'raw'              => esc_html__( 'Clear a field to leave that line off the poll entirely.', 'founding-faces' ),
			'content_classes'  => 'elementor-descriptor',
		) );

		$this->end_controls_section();
	}

	/**
	 * Register the "no poll right now" message and its preview switch.
	 *
	 * A page with a poll widget on it is a page a member visits between polls
	 * as well as during one. Rendering nothing leaves a hole where the poll
	 * was; this is the copy that stands in its place.
	 */
	protected function register_poll_empty_controls() {
		$this->start_controls_section( 'ff_poll_empty', array(
			'label' => __( 'When there is no poll', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'empty_heading', array(
			'label'       => __( 'Heading', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Nothing to decide right now', 'founding-faces' ),
			'label_block' => true,
			'description' => __( 'Inline HTML is allowed — bold, italics, a link, a line break.', 'founding-faces' ),
		) );

		$this->add_control( 'empty_text', array(
			'label'       => __( 'Message', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 4,
			'default'     => __( 'There is no poll open at the moment. When there is a decision to make, it will appear here first.', 'founding-faces' ),
			'description' => __( 'HTML is allowed here — links, bold, italics, lists. A blank line starts a new paragraph.', 'founding-faces' ),
		) );

		$this->add_control( 'empty_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'Clear both fields to show nothing at all when there is no poll.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'preview_state', array(
			'label'       => __( 'Editor preview', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'poll',
			'separator'   => 'before',
			'options'     => array(
				'poll'  => __( 'The poll', 'founding-faces' ),
				'empty' => __( 'This message', 'founding-faces' ),
			),
			'description' => __( 'Editor only — switch to the message to style it without waiting for every poll to close.', 'founding-faces' ),
		) );

		$this->end_controls_section();

		/* ========================= NO-POLL MESSAGE ========================= */
		$this->start_controls_section( 'ff_poll_empty_style', array(
			'label' => __( 'No-poll message', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'empty_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-poll-empty' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_control( 'empty_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-empty' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'empty_border',
			'selector' => '{{WRAPPER}} .ff-poll-empty',
		) );
		$this->add_responsive_control( 'empty_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-empty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'empty_shadow',
			'selector' => '{{WRAPPER}} .ff-poll-empty',
		) );
		$this->add_responsive_control( 'empty_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-empty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'empty_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-empty' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'empty_h_head', array(
			'label'     => __( 'Heading', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'empty_h_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-empty-heading' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'empty_h_typo',
			'selector' => '{{WRAPPER}} .ff-poll-empty-heading',
		) );
		$this->add_responsive_control( 'empty_h_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-empty-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'empty_t_head', array(
			'label'     => __( 'Message text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'empty_t_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-poll-empty-text' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'empty_t_typo',
			'selector' => '{{WRAPPER}} .ff-poll-empty-text',
		) );
		$this->add_responsive_control( 'empty_t_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-poll-empty-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * The "no poll right now" markup, or nothing if both fields were cleared.
	 *
	 * Both fields take HTML. The message allows what a post allows — links,
	 * emphasis, lists — while the heading is held to inline tags, because a
	 * paragraph or a list inside a heading is not markup anyone meant to write.
	 * Neither allows scripts or styles: this is copy, not a place to run code.
	 *
	 * @param array $settings The widget settings.
	 * @return string
	 */
	protected function ffp_empty_html( $settings ) {
		$heading = isset( $settings['empty_heading'] ) ? FF_Text::inline( $settings['empty_heading'] ) : '';
		$text    = isset( $settings['empty_text'] ) ? FF_Text::rich( $settings['empty_text'] ) : '';

		$has_heading = FF_Text::filled( $heading );
		$has_text    = FF_Text::filled( $text );

		if ( ! $has_heading && ! $has_text ) {
			return '';
		}

		$out = '<div class="ff-poll-empty">';
		if ( $has_heading ) {
			$out .= '<h3 class="ff-poll-empty-heading">' . $heading . '</h3>';
		}
		if ( $has_text ) {
			// A div, not a span: wpautop produces paragraphs, and paragraphs
			// inside a span is markup no browser agrees on.
			$out .= '<div class="ff-poll-empty-text">' . wpautop( $text ) . '</div>';
		}

		return $out . '</div>';
	}

	/**
	 * Pull the wording settings out of a widget's saved settings.
	 *
	 * @param array $settings The widget settings.
	 * @return array Wording overrides, keyed as FF_Polls expects.
	 */
	protected function ffp_text( $settings ) {
		$text = array();

		foreach ( array_keys( FF_Polls::text_defaults() ) as $key ) {
			if ( isset( $settings[ 'text_' . $key ] ) ) {
				$text[ $key ] = $settings[ 'text_' . $key ];
			}
		}

		return $text;
	}

	/**
	 * Whether the widget is rendering inside the Elementor editor.
	 *
	 * @return bool
	 */
	protected function ffp_is_editor() {
		return \Elementor\Plugin::$instance->editor->is_edit_mode()
			|| ( isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
			'source',
			array(
				'label'       => __( 'Show', 'founding-faces' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'latest',
				'options'     => array(
					'latest'   => __( 'The latest poll', 'founding-faces' ),
					'all'      => __( 'All current polls', 'founding-faces' ),
					'specific' => __( 'One specific poll', 'founding-faces' ),
				),
				'description' => __( 'Open polls come first, newest first; closed ones follow while they are still inside their hide time.', 'founding-faces' ),
			)
		);

		$this->add_control(
			'poll_id',
			array(
				'label'     => __( 'Which poll', 'founding-faces' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 0,
				'options'   => FF_Polls::poll_choices(),
				'condition' => array( 'source' => 'specific' ),
			)
		);

		$this->end_controls_section();

		// Every fixed phrase the poll says, as editable fields.
		$this->register_poll_text_controls();

		// The copy shown when there is no poll to show.
		$this->register_poll_empty_controls();

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

		/* ============================== COLUMNS ============================= */
		$this->start_controls_section( 'ff_poll_cols', array(
			'label'     => __( 'Columns', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'source' => 'all' ),
		) );
		$this->add_responsive_control( 'poll_columns', array(
			'label'          => __( 'Columns', 'founding-faces' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '1',
			'tablet_default' => '1',
			'mobile_default' => '1',
			'options'        => array(
				'1' => __( '1 column', 'founding-faces' ),
				'2' => __( '2 columns', 'founding-faces' ),
				'3' => __( '3 columns', 'founding-faces' ),
				'4' => __( '4 columns', 'founding-faces' ),
			),
			'selectors'      => array(
				'{{WRAPPER}} .ff-polls-list' => 'display: grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr)); align-items: start;',
			),
			'description'    => __( 'Set each device separately with the icons above.', 'founding-faces' ),
		) );
		$this->add_responsive_control( 'poll_col_gap', array(
			'label'      => __( 'Column gap', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-polls-list' => 'column-gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'poll_row_gap', array(
			'label'      => __( 'Row gap', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-polls-list' => 'row-gap: {{SIZE}}{{UNIT}};' ),
		) );
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

		$spacing = isset( $settings['spacing']['size'] ) ? absint( $settings['spacing']['size'] ) : 16;
		$text    = $this->ffp_text( $settings );
		$source  = isset( $settings['source'] ) ? $settings['source'] : 'latest';
		$style   = array(
			'accent'  => isset( $settings['accent'] ) ? $settings['accent'] : '#3a3d44',
			'align'   => isset( $settings['align'] ) ? $settings['align'] : 'left',
			'spacing' => $spacing,
		);

		// A widget saved before the source control existed has no 'source' at
		// all. Its poll id already means what it meant then — a chosen poll, or
		// 0 for whichever poll is flagged active — so leave it to render_poll.
		if ( ! isset( $settings['source'] ) ) {
			$source = 'specific';
		}

		if ( 'all' === $source ) {
			$html = FF_Polls::render_poll_list( FF_Polls::ordered_poll_ids(), $style, $text );
		} else {
			$poll_id = ( 'specific' === $source && isset( $settings['poll_id'] ) )
				? absint( $settings['poll_id'] )
				: FF_Polls::latest_poll_id();
			$html    = FF_Polls::render_poll( $poll_id, $style, $text );
		}

		// The editor can be pointed at the no-poll message instead, so it can be
		// styled without waiting for every poll to close.
		if ( $this->ffp_is_editor() && isset( $settings['preview_state'] ) && 'empty' === $settings['preview_state'] ) {
			echo $this->ffp_empty_html( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// In the editor, show a sample poll when there's no real one to render,
		// so every part (bars, labels, capsule, outcome) can be styled up front.
		// "All" gets three, so the columns have something to lay out.
		if ( $this->ffp_is_editor() && '' === trim( (string) $html ) ) {
			if ( 'all' === $source ) {
				echo '<div class="ff-polls-list">';
				foreach ( array( 'voting', 'results', 'results' ) as $state ) {
					echo '<div class="ff-polls-list-item">' . FF_Polls::sample_poll( $state, $text ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
				return;
			}
			echo FF_Polls::sample_poll( 'results', $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// Nothing to show: the copy written for exactly this moment, or nothing
		// at all if both fields were cleared.
		if ( '' === trim( (string) $html ) ) {
			echo $this->ffp_empty_html( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
				'{{WRAPPER}} .ff-polls-archive-grid' => 'display:grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr)); align-items: start;',
			),
		) );
		$this->add_responsive_control( 'col_gap', array(
			'label'     => __( 'Gap between polls', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default'   => array( 'size' => 24, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .ff-polls-archive-grid' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ------------------------- Section headings ------------------------ */
		$this->start_controls_section( 'ff_archive_heads', array(
			'label' => __( 'Section headings', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'ah_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'ah_typo',
			'selector' => '{{WRAPPER}} .ff-polls-archive-heading',
		) );
		$this->add_responsive_control( 'ah_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'ah_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'ah_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'ah_ul', array(
			'label'        => __( 'Underline', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'display: inline-block; border-bottom-style: solid;' ),
		) );
		$this->add_control( 'ah_ul_color', array(
			'label'     => __( 'Underline colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'condition' => array( 'ah_ul' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_control( 'ah_ul_width', array(
			'label'     => __( 'Underline thickness', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'default'   => array( 'size' => 2, 'unit' => 'px' ),
			'condition' => array( 'ah_ul' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} .ff-polls-archive-heading' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		// The copy shown when there is no poll to show.
		$this->register_poll_empty_controls();

		// The shared bar / text / capsule Style sections.
		$this->register_poll_style_controls();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$headings = ( ! isset( $s['headings'] ) || 'yes' === $s['headings'] ) ? 'yes' : 'no';
		$show     = isset( $s['show'] ) && in_array( $s['show'], array( 'both', 'open', 'past' ), true ) ? $s['show'] : 'both';

		$text = $this->ffp_text( $s );

		$html = FF_Polls::archive_shortcode( array(
			'headings' => $headings,
			'show'     => $show,
		), $text, $this->ffp_empty_html( $s ) );

		// The editor can be pointed at the no-poll message instead of the polls.
		if ( $this->ffp_is_editor() && isset( $s['preview_state'] ) && 'empty' === $s['preview_state'] ) {
			echo '<div class="ff-polls-archive">' . $this->ffp_empty_html( $s ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// Editor: fall back to a sample archive so the layout can be styled even
		// before any polls exist (or when the designer isn't a member).
		if ( $this->ffp_is_editor() && '' === trim( wp_strip_all_tags( $html ) ) ) {
			echo '<div class="ff-polls-archive">';
			if ( 'yes' === $headings && 'past' !== $show ) {
				echo '<h2 class="ff-polls-archive-heading">' . esc_html__( 'Open now', 'founding-faces' ) . '</h2>';
				echo '<div class="ff-polls-archive-grid">' . FF_Polls::sample_poll( 'voting', $text ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			if ( 'open' !== $show ) {
				if ( 'yes' === $headings ) {
					echo '<h2 class="ff-polls-archive-heading">' . esc_html__( 'Past polls', 'founding-faces' ) . '</h2>';
				}
				echo '<div class="ff-polls-archive-grid">' . FF_Polls::sample_poll( 'results', $text ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
