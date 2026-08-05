<?php
/**
 * Shared Elementor Style-tab controls for the plugin's forms.
 *
 * Both the Application Form and the Status Lookup widgets render the same
 * `.ff-form` markup (fields, hints, submit button, notices), so their Style tab
 * is identical. Rather than duplicate ~200 lines in each widget, the sections
 * live here and both widgets pull them in with one call. Every selector is
 * scoped to {{WRAPPER}}, so styling one form never touches another on the page.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait FF_Form_Style_Controls
 *
 * Used by Elementor widgets (so $this is a \Elementor\Widget_Base) that render
 * the plugin's standard form markup.
 */
trait FF_Form_Style_Controls {

	/**
	 * Register the full set of form Style sections.
	 *
	 * @param bool $with_hints Whether the form has field hints to style.
	 */
	protected function register_form_style_controls( $with_hints = true ) {

		/* ============================= FORM BOX ============================= */
		$this->start_controls_section( 'ff_form_box_style', array(
			'label' => __( 'Form box', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'form_width', array(
			'label'      => __( 'Max width', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 280, 'max' => 1200 ), '%' => array( 'min' => 20, 'max' => 100 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-form' => 'max-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'form_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'0 auto 0 0' => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-h-align-left' ),
				'0 auto'     => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-h-align-center' ),
				'0 0 0 auto' => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-h-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-form' => 'margin: {{VALUE}};' ),
		) );
		$this->add_control( 'form_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-form' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'form_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'form_border',
			'selector' => '{{WRAPPER}} .ff-form',
		) );
		$this->add_responsive_control( 'form_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'field_gap', array(
			'label'     => __( 'Space between fields', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-field' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ============================== LABELS ============================= */
		$this->start_controls_section( 'ff_form_label_style', array(
			'label' => __( 'Labels', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'label_typo',
			'selector' => '{{WRAPPER}} .ff-field label',
		) );
		$this->add_control( 'label_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-field label' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'required_color', array(
			'label'     => __( 'Required asterisk colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-required' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'label_gap', array(
			'label'     => __( 'Space below label', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-field label' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ============================== FIELDS ============================= */
		$this->start_controls_section( 'ff_form_field_style', array(
			'label' => __( 'Fields (inputs & text areas)', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		// Every typed field the plugin renders is listed. A type left off is a
		// field that keeps the browser's own border and ignores everything set
		// here, which is how a password box ends up looking unlike the email box
		// directly above it.
		$fields = implode( ', ', array(
			'{{WRAPPER}} .ff-field input[type="text"]',
			'{{WRAPPER}} .ff-field input[type="email"]',
			'{{WRAPPER}} .ff-field input[type="password"]',
			'{{WRAPPER}} .ff-field input[type="tel"]',
			'{{WRAPPER}} .ff-field input[type="url"]',
			'{{WRAPPER}} .ff-field input[type="number"]',
			'{{WRAPPER}} .ff-field input[type="date"]',
			'{{WRAPPER}} .ff-field textarea',
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'field_typo',
			'selector' => $fields,
		) );
		$this->add_control( 'field_text_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $fields => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'field_placeholder_color', array(
			'label'     => __( 'Placeholder colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .ff-field input::placeholder'    => 'color: {{VALUE}}; opacity: 1;',
				'{{WRAPPER}} .ff-field textarea::placeholder' => 'color: {{VALUE}}; opacity: 1;',
			),
		) );
		$this->add_control( 'field_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $fields => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'field_border',
			'selector' => $fields,
		) );
		$this->add_responsive_control( 'field_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( $fields => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'field_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( $fields => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'field_focus_h', array(
			'label'     => __( 'Focus (when typing)', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'field_focus_border', array(
			'label'     => __( 'Focus border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .ff-field input:focus'    => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .ff-field textarea:focus' => 'border-color: {{VALUE}};',
			),
		) );
		$this->end_controls_section();

		/* =============================== HINTS ============================= */
		if ( $with_hints ) {
			$this->start_controls_section( 'ff_form_hint_style', array(
				'label' => __( 'Field hints', 'founding-faces' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			) );
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
				'name'     => 'hint_typo',
				'selector' => '{{WRAPPER}} .ff-hint',
			) );
			$this->add_control( 'hint_color', array(
				'label'     => __( 'Colour', 'founding-faces' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .ff-hint' => 'color: {{VALUE}};' ),
			) );
			$this->end_controls_section();
		}

		/* ============================== BUTTON ============================= */
		$this->start_controls_section( 'ff_form_button_style', array(
			'label' => __( 'Submit button', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'button_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-submit' => 'text-align: {{VALUE}};' ),
			'condition' => array( 'button_full!' => 'yes' ),
		) );
		$this->add_control( 'button_full', array(
			'label'        => __( 'Full-width button', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'selectors'    => array( '{{WRAPPER}} .ff-submit button' => 'width: 100%;' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'button_typo',
			'selector' => '{{WRAPPER}} .ff-submit button',
		) );
		$this->start_controls_tabs( 'button_tabs' );
		$this->start_controls_tab( 'button_normal', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'button_text_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-submit button' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'button_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-submit button' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'button_hover', array( 'label' => __( 'Hover', 'founding-faces' ) ) );
		$this->add_control( 'button_text_color_h', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-submit button:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'button_bg_h', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-submit button:hover' => 'background-color: {{VALUE}}; opacity: 1;' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'button_border',
			'selector'  => '{{WRAPPER}} .ff-submit button',
			'separator' => 'before',
		) );
		$this->add_responsive_control( 'button_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-submit button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'button_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-submit button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'button_top_gap', array(
			'label'     => __( 'Space above button', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-submit' => 'margin-top: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ============================= NOTICES ============================= */
		$this->start_controls_section( 'ff_form_notice_style', array(
			'label' => __( 'Messages (success & error)', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'notice_typo',
			'selector' => '{{WRAPPER}} .ff-notice',
		) );
		$this->add_responsive_control( 'notice_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notice' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'notice_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notice' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'notice_success_h', array(
			'label' => __( 'Success message', 'founding-faces' ),
			'type'  => \Elementor\Controls_Manager::HEADING,
		) );
		$this->add_control( 'notice_success_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notice--success' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'notice_success_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notice--success' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'notice_error_h', array(
			'label'     => __( 'Error message', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'notice_error_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notice--error' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'notice_error_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notice--error' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}
}
