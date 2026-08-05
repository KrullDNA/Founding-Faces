<?php
/**
 * The Account Elementor widget, the member's account-settings page, styleable.
 *
 * Renders the same markup as the [ff_account] shortcode. It reuses the shared
 * form Style tab (FF_Form_Style_Controls) for the profile form's labels, fields,
 * hints, primary button and notices, and adds account-specific sections: the
 * page container, the page title, the read-only standing box, the section blocks
 * and the secondary buttons (reset / export / delete).
 *
 * In the Elementor editor it shows a static sample so the page can be styled
 * before there are members. On the front end a logged-in member sees their real
 * account; everyone else sees the members-only notice.
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
 * Class FF_Account_Widget
 */
class FF_Account_Widget extends \Elementor\Widget_Base {

	use FF_Form_Style_Controls;

	public function get_name() {
		return 'ff_account';
	}
	public function get_title() {
		return __( 'Founding Faces Account', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-lock-user';
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
	 * Register the account-specific Style sections, then the shared form Style tab.
	 */
	protected function register_controls() {

		/* ============================== CONTENT ============================== */
		$this->start_controls_section( 'ff_ac_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'editor_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Each member sees their own account here. The editor shows a sample for styling.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->end_controls_section();

		/* ============================ PAGE CONTAINER ======================= */
		$this->start_controls_section( 'ff_ac_page_style', array(
			'label' => __( 'Page', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'page_width', array(
			'label'      => __( 'Max width', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 320, 'max' => 1200 ), '%' => array( 'min' => 20, 'max' => 100 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-account' => 'max-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'page_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'page_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-account' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* =============================== TITLE ============================= */
		$this->start_controls_section( 'ff_ac_title_style', array(
			'label' => __( 'Page title', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'title_typo',
			'selector' => '{{WRAPPER}} .ff-account-title',
		) );
		$this->add_control( 'title_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-title' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'title_gap', array(
			'label'     => __( 'Space below', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-account-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* =========================== STANDING BOX ========================= */
		$this->start_controls_section( 'ff_ac_standing_style', array(
			'label' => __( 'Standing box (number & group)', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'standing_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-standing' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'standing_border',
			'selector' => '{{WRAPPER}} .ff-account-standing',
		) );
		$this->add_responsive_control( 'standing_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-account-standing' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'standing_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-account-standing' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'standing_label_color', array(
			'label'     => __( 'Label colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-label' => 'color: {{VALUE}};' ),
			'separator' => 'before',
		) );
		$this->add_control( 'standing_value_color', array(
			'label'     => __( 'Value colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-standing strong' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'standing_note_color', array(
			'label'     => __( 'Note colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-standing-note' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* =========================== SECTION BLOCKS ======================= */
		$this->start_controls_section( 'ff_ac_block_style', array(
			'label' => __( 'Section blocks (Password, Your data)', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'block_heading_typo',
			'label'    => __( 'Heading text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-account-block h3',
		) );
		$this->add_control( 'block_heading_color', array(
			'label'     => __( 'Heading colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-block h3' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'block_divider_color', array(
			'label'     => __( 'Top divider colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account-block' => 'border-top-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'block_gap', array(
			'label'     => __( 'Space above block', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-account-block' => 'margin-top: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ========================= SECONDARY BUTTONS ====================== */
		$this->start_controls_section( 'ff_ac_secbtn_style', array(
			'label' => __( 'Secondary buttons (reset / export / delete)', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'secbtn_typo',
			'selector' => '{{WRAPPER}} .ff-account .button',
		) );
		$this->add_control( 'secbtn_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account .button' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'secbtn_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account .button' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'secbtn_border',
			'selector' => '{{WRAPPER}} .ff-account .button',
		) );
		$this->add_responsive_control( 'secbtn_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-account .button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'secbtn_danger_h', array(
			'label'     => __( 'Delete button', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'secbtn_danger_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account .ff-danger' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'secbtn_danger_border', array(
			'label'     => __( 'Border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-account .ff-danger' => 'border-color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		// The shared form Style tab (labels, fields, hints, primary Save button,
		// notices) for the profile form inside the account page.
		$this->register_form_style_controls( true );
	}

	/**
	 * Render: the member's real account, a sample in the editor, or a notice.
	 */
	protected function render() {
		if ( FF_Gating::is_member() ) {
			$html = FF_Account::render();

			if ( FF_History::is_editor() && FF_History::sample_needed( $html ) ) {
				$html = FF_Account::sample();
			}

			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( FF_History::is_editor() ) {
			echo FF_Account::sample(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		echo FF_Display::members_only_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
