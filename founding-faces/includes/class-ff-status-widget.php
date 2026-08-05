<?php
/**
 * The Status Lookup Elementor widget, the "check your application" form.
 *
 * A thin wrapper around FF_Application::render_status_lookup(): same markup as
 * the [ff_status_lookup] shortcode, plus the shared form Style tab (via the
 * FF_Form_Style_Controls trait) so it can be styled to match the rest of the
 * site. Every selector is scoped to this widget.
 *
 * Atomic architecture.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FF_PATH . 'includes/trait-ff-form-style.php';

/**
 * Class FF_Status_Widget
 */
class FF_Status_Widget extends \Elementor\Widget_Base {

	use FF_Form_Style_Controls;

	public function get_name() {
		return 'ff_status_lookup';
	}
	public function get_title() {
		return __( 'Founding Faces Status Lookup', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-search';
	}
	public function get_categories() {
		return array( 'founding-faces' );
	}
	public function get_style_depends() {
		return array( 'founding-faces' );
	}
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Register the content controls, then the shared form Style tab.
	 */
	protected function register_controls() {

		/* ============================== CONTENT ============================== */
		$this->start_controls_section( 'ff_sl_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'field_label', array(
			'label'       => __( 'Field label', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Check your application status', 'founding-faces' ),
			'placeholder' => __( 'Check your application status', 'founding-faces' ),
		) );

		$this->add_control( 'button_label', array(
			'label'       => __( 'Button text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Check status', 'founding-faces' ),
			'placeholder' => __( 'Check status', 'founding-faces' ),
		) );

		$this->add_control( 'heading', array(
			'label'       => __( 'Heading', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Where is my application up to?', 'founding-faces' ),
			'separator'   => 'before',
		) );

		$this->add_control( 'intro', array(
			'label'       => __( 'Body copy', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 4,
			'default'     => '',
			'placeholder' => __( 'Enter the email address you applied with and we\'ll tell you where things stand.', 'founding-faces' ),
		) );

		$this->add_control( 'hide_on_found', array(
			'label'        => __( 'Hide the form after a successful lookup', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'separator'    => 'before',
			'description'  => __( 'The result stands on its own, with a "check another email" link back. An unrecognised email always keeps the form up, so it can be corrected.', 'founding-faces' ),
		) );

		$this->add_control( 'again_label', array(
			'label'     => __( '"Check another" link text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Check another email', 'founding-faces' ),
			'condition' => array( 'hide_on_found' => 'yes' ),
		) );

		$this->end_controls_section();

		// This form has no field hints, so skip that Style section.
		$this->register_form_style_controls( false );

		$this->register_status_style_controls();
	}

	/**
	 * Style controls for the heading, body copy, the "send it again" prompt and
	 * the "check another email" link.
	 */
	private function register_status_style_controls() {

		/* ------------------------------ Heading ------------------------------ */
		$this->start_controls_section( 'ff_sl_heading_style', array(
			'label'     => __( 'Heading', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'heading!' => '' ),
		) );
		$this->add_control( 'h_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-status-heading' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'h_typo',
			'selector' => '{{WRAPPER}} .ff-status-heading',
		) );
		$this->add_responsive_control( 'h_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => $this->ff_sl_align_options(),
			'selectors' => array( '{{WRAPPER}} .ff-status-heading' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'h_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-status-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'h_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-status-heading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ----------------------------- Body copy ----------------------------- */
		$this->start_controls_section( 'ff_sl_intro_style', array(
			'label'     => __( 'Body copy', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'intro!' => '' ),
		) );
		$this->add_control( 'i_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-status-intro' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'i_typo',
			'selector' => '{{WRAPPER}} .ff-status-intro',
		) );
		$this->add_responsive_control( 'i_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => $this->ff_sl_align_options(),
			'selectors' => array( '{{WRAPPER}} .ff-status-intro' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'i_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-status-intro' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'i_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-status-intro' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* -------------------- "Send it again" & back link -------------------- */
		$this->start_controls_section( 'ff_sl_resend_style', array(
			'label' => __( '"Send it again" & links', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'r_hint_color', array(
			'label'     => __( 'Prompt colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-status-resend-hint' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'r_hint_typo',
			'label'    => __( 'Prompt text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-status-resend-hint',
		) );
		$this->add_control( 'r_btn_color', array(
			'label'     => __( 'Button text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-status-resend-button' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'r_btn_bg', array(
			'label'     => __( 'Button background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-status-resend-button' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'r_btn_typo',
			'label'    => __( 'Button text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-status-resend-button',
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'r_btn_border',
			'selector' => '{{WRAPPER}} .ff-status-resend-button',
		) );
		$this->add_responsive_control( 'r_btn_radius', array(
			'label'      => __( 'Button corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-status-resend-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'r_btn_padding', array(
			'label'      => __( 'Button padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-status-resend-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'again_color', array(
			'label'     => __( '"Check another" link colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-status-again-link' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'again_ul', array(
			'label'        => __( 'Underline the link', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'underline',
			'default'      => '',
			'selectors'    => array( '{{WRAPPER}} .ff-status-again-link' => 'text-decoration: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * The alignment CHOOSE options, shared by the heading and body copy.
	 *
	 * @return array
	 */
	private function ff_sl_align_options() {
		return array(
			'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
			'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
			'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
		);
	}

	/**
	 * Render the lookup form with the widget's own label and button text.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$args = array( 'form_class' => 'ff-form--full' );
		if ( ! empty( $s['field_label'] ) ) {
			$args['label'] = $s['field_label'];
		}
		if ( ! empty( $s['button_label'] ) ) {
			$args['button_label'] = $s['button_label'];
		}
		if ( ! empty( $s['heading'] ) ) {
			$args['heading'] = $s['heading'];
		}
		if ( ! empty( $s['intro'] ) ) {
			$args['intro'] = $s['intro'];
		}
		if ( isset( $s['hide_on_found'] ) && 'yes' === $s['hide_on_found'] ) {
			$args['hide_on_found'] = 'yes';
		}
		if ( ! empty( $s['again_label'] ) ) {
			$args['again_label'] = $s['again_label'];
		}

		// In the editor, stand in a sample result so the notice, the "send it
		// again" prompt and the button are visible and styleable.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode()
			|| ( isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['editor_preview'] = 'yes';
		}

		echo FF_Application::render_status_lookup( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
