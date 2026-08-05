<?php
/**
 * The Founding Faces Login Elementor widget.
 *
 * A skin over WordPress's own login handler — authentication, cookies and
 * brute-force protection all stay core's job. Built for Elementor's Atomic
 * architecture, and it reuses the shared form Style tab so the login form
 * matches the application form without duplicating twenty controls.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FF_PATH . 'includes/trait-ff-form-style.php';

/**
 * Class FF_Login_Widget
 */
class FF_Login_Widget extends \Elementor\Widget_Base {

	use FF_Form_Style_Controls;

	/** @return string */
	public function get_name() {
		return 'ff_login';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Login', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-lock-user';
	}

	/** @return array */
	public function get_categories() {
		return array( 'founding-faces' );
	}

	/** @return array */
	public function get_style_depends() {
		return array( 'founding-faces' );
	}

	/**
	 * Atomic: no extra inner wrapper when optimised markup is active.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/** Register the controls. */
	protected function register_controls() {

		/* ------------------------------ Content ------------------------------ */
		$this->start_controls_section( 'ff_login_content', array(
			'label' => __( 'Login form', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'label_user', array(
			'label'   => __( 'Email label', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Email address', 'founding-faces' ),
		) );
		$this->add_control( 'label_pass', array(
			'label'   => __( 'Password label', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Password', 'founding-faces' ),
		) );
		$this->add_control( 'button', array(
			'label'   => __( 'Button text', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Log in', 'founding-faces' ),
		) );
		$this->add_control( 'show_remember', array(
			'label'        => __( 'Show "Keep me signed in"', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'show_lost', array(
			'label'        => __( 'Show "Forgotten your password?"', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'lost_text', array(
			'label'     => __( 'Lost-password link text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Forgotten your password?', 'founding-faces' ),
			'condition' => array( 'show_lost' => 'yes' ),
		) );
		$this->add_control( 'redirect', array(
			'label'         => __( 'After login, go to', 'founding-faces' ),
			'type'          => \Elementor\Controls_Manager::URL,
			'show_external' => false,
			'separator'     => 'before',
			'description'   => __( 'Leave empty to use the redirect set under Founding Faces → Settings.', 'founding-faces' ),
		) );
		$this->end_controls_section();

		/* --------------------------- Already signed in ------------------------ */
		$this->start_controls_section( 'ff_login_in', array(
			'label' => __( 'When already signed in', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'logged_in_text', array(
			'label'   => __( 'Message', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( "You're signed in.", 'founding-faces' ),
		) );
		$this->add_control( 'in_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'A signed-in member sees this message and a log-out link instead of the form, so the page is never a dead end.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'editor_view', array(
			'label'       => __( 'Show in the editor as', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'both',
			'separator'   => 'before',
			'options'     => array(
				'both' => __( 'Both states, one above the other', 'founding-faces' ),
				'out'  => __( 'A logged-out visitor (the form)', 'founding-faces' ),
				'in'   => __( 'A signed-in member (the message)', 'founding-faces' ),
			),
			'description' => __( 'Only changes the canvas. The front end always shows the right one for whoever is looking. You are signed in while you design, so without this the form could never be seen.', 'founding-faces' ),
		) );

		$this->end_controls_section();

		// The shared form Style tab: box, labels, fields, hints, button, notices.
		$this->register_form_style_controls( false );

		/* -------------------------- Links & signed-in ------------------------- */
		$this->start_controls_section( 'ff_login_links', array(
			'label' => __( 'Links & signed-in panel', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'link_color', array(
			'label'     => __( 'Link colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-login-lost a, {{WRAPPER}} .ff-login-logout' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'link_hover', array(
			'label'     => __( 'Link hover colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-login-lost a:hover, {{WRAPPER}} .ff-login-logout:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'link_typo',
			'label'    => __( 'Link text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-login-lost a, {{WRAPPER}} .ff-login-logout',
		) );
		$this->add_control( 'link_ul', array(
			'label'        => __( 'Underline links', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'underline',
			'default'      => '',
			'selectors'    => array( '{{WRAPPER}} .ff-login-lost a, {{WRAPPER}} .ff-login-logout' => 'text-decoration: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'link_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-login-lost, {{WRAPPER}} .ff-login-actions' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'link_margin', array(
			'label'      => __( 'Link margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-login-lost, {{WRAPPER}} .ff-login-actions' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'status_color', array(
			'label'     => __( 'Signed-in message colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-login-status' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'status_typo',
			'label'    => __( 'Signed-in message text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-login-status',
		) );
		$this->add_responsive_control( 'status_align', array(
			'label'     => __( 'Signed-in message alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-login-status' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'status_margin', array(
			'label'      => __( 'Signed-in message margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-login-status' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'in_box_heading', array(
			'label'     => __( 'The signed-in panel', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'in_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-login--in' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'in_border',
			'selector' => '{{WRAPPER}} .ff-login--in',
		) );
		$this->add_responsive_control( 'in_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-login--in' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'in_shadow',
			'selector' => '{{WRAPPER}} .ff-login--in',
		) );
		$this->add_responsive_control( 'in_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-login--in' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'in_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-login--in' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();

		$redirect = '';
		if ( ! empty( $s['redirect']['url'] ) ) {
			$redirect = $s['redirect']['url'];
		}

		echo FF_Menu_Items::sc_login( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'redirect'       => $redirect,
			'label_user'     => isset( $s['label_user'] ) ? $s['label_user'] : '',
			'label_pass'     => isset( $s['label_pass'] ) ? $s['label_pass'] : '',
			'button'         => isset( $s['button'] ) ? $s['button'] : '',
			'show_remember'  => ( ! isset( $s['show_remember'] ) || 'yes' === $s['show_remember'] ) ? 'yes' : 'no',
			'show_lost'      => ( ! isset( $s['show_lost'] ) || 'yes' === $s['show_lost'] ) ? 'yes' : 'no',
			'lost_text'      => isset( $s['lost_text'] ) ? $s['lost_text'] : '',
			'logged_in_text' => isset( $s['logged_in_text'] ) ? $s['logged_in_text'] : '',
			'form_class'     => 'ff-form--full',
			// Which state the canvas draws. Empty on the front end, where the
			// viewer decides it and nothing here should.
			'editor_preview' => FF_History::is_editor()
				? ( isset( $s['editor_view'] ) && '' !== $s['editor_view'] ? $s['editor_view'] : 'both' )
				: '',
		) );
	}
}
