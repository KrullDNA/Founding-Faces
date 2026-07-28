<?php
/**
 * The personalised Welcome text widget.
 *
 * Renders a greeting built from editable before/middle/after text wrapped around
 * the member's own first name and Founding number, e.g. "Hi Sarah, Founding
 * Member 4, welcome back." Each of the name and number can be toggled on or off,
 * and the whole thing — plus the name and number individually — has full style
 * controls. Because a member only ever sees their own greeting, it shows the
 * real first name (the privacy tiers govern how a member appears to OTHERS, which
 * doesn't apply here).
 *
 * Atomic architecture.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Welcome_Widget
 */
class FF_Welcome_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'ff_welcome';
	}
	public function get_title() {
		return __( 'Founding Faces Welcome', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-user-circle-o';
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
	 * The HTML tags the greeting may render as.
	 *
	 * @return array
	 */
	private function tags() {
		return array(
			'h1'   => 'H1',
			'h2'   => 'H2',
			'h3'   => 'H3',
			'h4'   => 'H4',
			'h5'   => 'H5',
			'h6'   => 'H6',
			'p'    => 'p',
			'div'  => 'div',
			'span' => 'span',
		);
	}

	/**
	 * Register the content and style controls.
	 */
	protected function register_controls() {

		/* ============================== CONTENT ============================== */
		$this->start_controls_section( 'ff_w_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'before_text', array(
			'label'       => __( 'Before text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Hi ', 'founding-faces' ),
			'description' => __( 'Comes before the first name.', 'founding-faces' ),
		) );

		$this->add_control( 'show_name', array(
			'label'        => __( 'Show first name', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'founding-faces' ),
			'label_off'    => __( 'Hide', 'founding-faces' ),
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'mid_text', array(
			'label'       => __( 'Middle text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( ', Founding Member ', 'founding-faces' ),
			'description' => __( 'Sits between the name and the number (only shown when the number shows).', 'founding-faces' ),
		) );

		$this->add_control( 'show_number', array(
			'label'        => __( 'Show Founding number', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'founding-faces' ),
			'label_off'    => __( 'Hide', 'founding-faces' ),
			'default'      => 'yes',
			'return_value' => 'yes',
			'description'  => __( 'The Circle has no number, so this shows only for The 35.', 'founding-faces' ),
		) );

		$this->add_control( 'after_text', array(
			'label'       => __( 'After text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( ', welcome back.', 'founding-faces' ),
			'description' => __( 'Comes after the name and number.', 'founding-faces' ),
		) );

		$this->add_control( 'html_tag', array(
			'label'   => __( 'HTML tag', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'h2',
			'options' => $this->tags(),
		) );

		$this->end_controls_section();

		/* ============================= TEXT STYLE =========================== */
		$this->start_controls_section( 'ff_w_text_style', array(
			'label' => __( 'Text', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'    => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center'  => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'   => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-welcome' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'text_typo',
			'selector' => '{{WRAPPER}} .ff-welcome',
		) );
		$this->add_control( 'text_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-welcome' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'text_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-welcome' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'text_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-welcome' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ============================= NAME STYLE =========================== */
		$this->start_controls_section( 'ff_w_name_style', array(
			'label'     => __( 'First name', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_name' => 'yes' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'name_typo',
			'selector' => '{{WRAPPER}} .ff-welcome-name',
		) );
		$this->add_control( 'name_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-welcome-name' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ============================ NUMBER STYLE ========================== */
		$this->start_controls_section( 'ff_w_number_style', array(
			'label'     => __( 'Founding number', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_number' => 'yes' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'number_typo',
			'selector' => '{{WRAPPER}} .ff-welcome-number',
		) );
		$this->add_control( 'number_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-welcome-number' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * The member's first name and Founding number, or sample values in the editor.
	 *
	 * @return array {first, number} — number is '' for The Circle / sample Circle.
	 */
	private function identity() {
		if ( FF_Gating::is_member() ) {
			$uid   = get_current_user_id();
			$real  = trim( (string) get_user_meta( $uid, FF_Members::META_REAL_NAME, true ) );
			$first = '' !== $real ? preg_split( '/\s+/', $real )[0] : '';
			if ( '' === $first ) {
				$u     = get_userdata( $uid );
				$first = $u ? preg_split( '/\s+/', trim( $u->display_name ) )[0] : '';
			}
			$number = get_user_meta( $uid, FF_Members::META_NUMBER, true );
			return array( 'first' => $first, 'number' => $number ? (int) $number : '' );
		}
		// Sample for the editor / admin preview.
		return array( 'first' => __( 'Sarah', 'founding-faces' ), 'number' => 4 );
	}

	/**
	 * Render the greeting.
	 */
	protected function render() {
		FF_History::enqueue();

		// Only the member (real), or the editor/admin (sample), see anything.
		if ( ! FF_Gating::is_member() && ! FF_History::is_editor() && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s      = $this->get_settings_for_display();
		$ident  = $this->identity();
		$tags   = $this->tags();
		$tag    = isset( $s['html_tag'], $tags[ $s['html_tag'] ] ) ? $s['html_tag'] : 'h2';

		$show_name   = ! isset( $s['show_name'] ) || 'yes' === $s['show_name'];
		$show_number = ( ! isset( $s['show_number'] ) || 'yes' === $s['show_number'] ) && '' !== $ident['number'];

		$html  = esc_html( isset( $s['before_text'] ) ? $s['before_text'] : '' );
		if ( $show_name && '' !== $ident['first'] ) {
			$html .= '<span class="ff-welcome-name">' . esc_html( $ident['first'] ) . '</span>';
		}
		if ( $show_number ) {
			$html .= esc_html( isset( $s['mid_text'] ) ? $s['mid_text'] : '' );
			$html .= '<span class="ff-welcome-number">' . esc_html( $ident['number'] ) . '</span>';
		}
		$html .= esc_html( isset( $s['after_text'] ) ? $s['after_text'] : '' );

		printf( '<%1$s class="ff-welcome">%2$s</%1$s>', esc_attr( $tag ), $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
	}
}
