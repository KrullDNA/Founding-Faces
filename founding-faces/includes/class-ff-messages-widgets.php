<?php
/**
 * Elementor widgets for the private member <-> admin channel.
 *
 *  - FF_Feedback_Widget : "Give feedback" on a note (uses the shared form Style tab).
 *  - FF_Ask_Widget      : "Ask a question" privately (uses the shared form Style tab).
 *  - FF_Messages_Widget : the member's message centre, with the "new message" flag.
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
 * Base helper: the Atomic wrapper method, shared by all three widgets.
 */
abstract class FF_Message_Widget_Base extends \Elementor\Widget_Base {
	public function get_categories() {
		return array( 'founding-faces' );
	}
	public function get_style_depends() {
		return array( 'founding-faces' );
	}
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}
}

/**
 * The "Give feedback" widget (attached to a note).
 */
class FF_Feedback_Widget extends FF_Message_Widget_Base {

	use FF_Form_Style_Controls;

	public function get_name() {
		return 'ff_feedback';
	}
	public function get_title() {
		return __( 'Founding Faces Feedback', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-review';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_fb_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'note_id', array(
			'label'       => __( 'Note ID (optional)', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'description' => __( 'Leave blank to use the note this widget is placed on (in a Single template).', 'founding-faces' ),
		) );
		$this->end_controls_section();

		$this->register_form_style_controls( true );
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$ref = ! empty( $s['note_id'] ) ? absint( $s['note_id'] ) : 0;
		echo FF_Messages::render_compose_form( 'feedback', $ref ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * The "Ask a question" widget (private message to Nick).
 */
class FF_Ask_Widget extends FF_Message_Widget_Base {

	use FF_Form_Style_Controls;

	public function get_name() {
		return 'ff_ask';
	}
	public function get_title() {
		return __( 'Founding Faces Ask a Question', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-help-o';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_ask_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'A private question box. Replies arrive in the member\'s portal and by email.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->end_controls_section();

		$this->register_form_style_controls( true );
	}

	protected function render() {
		echo FF_Messages::render_compose_form( 'question', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * The member's message centre.
 */
class FF_Messages_Widget extends FF_Message_Widget_Base {

	public function get_name() {
		return 'ff_messages';
	}
	public function get_title() {
		return __( 'Founding Faces Messages', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-mail';
	}

	protected function register_controls() {
		$this->start_controls_section( 'ff_msgs_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Place this on the portal homepage. Each member sees only their own messages, with a "New message" flag on unread replies.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->end_controls_section();

		/* Heading */
		$this->start_controls_section( 'ff_msgs_heading_style', array(
			'label' => __( 'Heading', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'msgs_heading_typo',
			'selector' => '{{WRAPPER}} .ff-messages .ff-history-heading',
		) );
		$this->add_control( 'msgs_heading_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-messages .ff-history-heading' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* Threads + badge */
		$this->start_controls_section( 'ff_msgs_thread_style', array(
			'label' => __( 'Message list', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'msgs_item_typo',
			'selector' => '{{WRAPPER}} .ff-message-thread .ff-history-item-main',
		) );
		$this->add_control( 'msgs_link_color', array(
			'label'     => __( 'Link colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message-thread a' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'badge_h', array(
			'label'     => __( '"New message" badge', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'badge_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message-badge' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'badge_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message-badge' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* Thread view bubbles */
		$this->start_controls_section( 'ff_msgs_bubble_style', array(
			'label' => __( 'Conversation bubbles', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'bubble_member_bg', array(
			'label'     => __( 'Your message background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message--member' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'bubble_admin_bg', array(
			'label'     => __( 'Reply background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message--admin' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		echo FF_Messages::sc_messages(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
