<?php
/**
 * The Member Archive Elementor widget — a member's own activity, fully styleable.
 *
 * One widget with a Section selector (Full record / Header / Votes / Notes /
 * Feedback): drop it as many times as you like, each set to one section, to
 * build any layout — with a complete Style tab (typography, colours, spacing,
 * backgrounds and borders for every part). It reads ONLY the signed-in member's
 * own data (id from the session, never the request), and shows on-brand sample
 * data in the Elementor editor so it can be styled before there are members.
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
 * Class FF_Member_Archive_Widget
 */
class FF_Member_Archive_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'ff_member_archive';
	}
	public function get_title() {
		return __( 'Founding Faces Member Archive', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-person';
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
	 * Register the content and (extensive) style controls.
	 */
	protected function register_controls() {

		/* ============================== CONTENT ============================== */
		$this->start_controls_section( 'ff_ma_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'section', array(
			'label'   => __( 'Section', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'all',
			'options' => array(
				'all'      => __( 'Full record (all sections)', 'founding-faces' ),
				'header'   => __( 'Number & group header', 'founding-faces' ),
				'votes'    => __( 'Votes', 'founding-faces' ),
				'notes'    => __( 'Notes you\'ve read', 'founding-faces' ),
				'feedback' => __( 'Feedback', 'founding-faces' ),
			),
		) );

		$this->add_control( 'heading', array(
			'label'       => __( 'Section heading', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Leave blank for the default', 'founding-faces' ),
			'condition'   => array( 'section!' => array( 'all', 'header' ) ),
		) );

		$this->add_control( 'link_notes', array(
			'label'        => __( 'Link notes to their page', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'section' => array( 'all', 'notes' ) ),
		) );

		$this->add_control( 'header_subheading', array(
			'label'       => __( 'Header subheading', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 2,
			'default'     => FF_History::default_subheading(),
			'placeholder' => __( 'Leave blank to hide the subheading', 'founding-faces' ),
			'condition'   => array( 'section' => array( 'all', 'header' ) ),
		) );

		$this->end_controls_section();

		/* ============================ HEADER STYLE ========================== */
		$this->start_controls_section( 'ff_ma_header_style', array(
			'label'     => __( 'Header (number & group)', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'header' ) ),
		) );

		$this->add_responsive_control( 'header_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'number_typo',
			'label'    => __( 'Number text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-history-number',
		) );
		$this->add_control( 'number_color', array(
			'label'     => __( 'Number colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-number' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'group_typo',
			'label'    => __( 'Group text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-history-group',
		) );
		$this->add_control( 'group_color', array(
			'label'     => __( 'Group colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-group' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'group_bg', array(
			'label'     => __( 'Group background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-group' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'intro_typo',
			'label'    => __( 'Subheading text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-history-intro',
		) );
		$this->add_control( 'intro_color', array(
			'label'     => __( 'Subheading colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-intro' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'header_padding', array(
			'label'      => __( 'Header padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'header_bg', array(
			'label'     => __( 'Header background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'background-color: {{VALUE}};' ),
		) );

		// A line under the header — off by default; set a width to show it.
		$this->add_control( 'header_divider_h', array(
			'label'     => __( 'Line under header', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'header_divider_width', array(
			'label'       => __( 'Line thickness', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'range'       => array( 'px' => array( 'min' => 0, 'max' => 10 ) ),
			'default'     => array( 'size' => 0 ),
			'description' => __( 'Set above 0 to show a line under the header.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-history-header' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid;' ),
		) );
		$this->add_control( 'header_divider_color', array(
			'label'     => __( 'Line colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#d5d8dd',
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'header_divider_gap', array(
			'label'     => __( 'Space above the line', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'padding-bottom: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* =========================== HEADING STYLE ========================== */
		$this->start_controls_section( 'ff_ma_heading_style', array(
			'label'     => __( 'Section headings', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'votes', 'notes', 'feedback' ) ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'heading_typo',
			'selector' => '{{WRAPPER}} .ff-history-heading',
		) );
		$this->add_control( 'heading_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-heading' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'heading_underline', array(
			'label'     => __( 'Underline colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-heading' => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'heading_spacing', array(
			'label'      => __( 'Spacing below', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* =========================== SECTION BOX =========================== */
		$this->start_controls_section( 'ff_ma_section_style', array(
			'label'     => __( 'Section box', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'votes', 'notes', 'feedback' ) ),
		) );
		$this->add_control( 'section_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-section' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'section_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-section' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'section_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-section' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'section_border',
			'selector' => '{{WRAPPER}} .ff-history-section',
		) );
		$this->add_responsive_control( 'section_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-section' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ============================== ITEMS ============================== */
		$this->start_controls_section( 'ff_ma_item_style', array(
			'label'     => __( 'Items', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'votes', 'notes', 'feedback' ) ),
		) );
		$this->add_control( 'item_bg', array(
			'label'     => __( 'Item background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'item_padding', array(
			'label'      => __( 'Item padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'item_gap', array(
			'label'     => __( 'Space between items', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-history-item' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'item_divider', array(
			'label'     => __( 'Divider colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item' => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_control( 'item_divider_width', array(
			'label'     => __( 'Divider width', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-history-item' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid;' ),
		) );
		$this->add_responsive_control( 'item_radius', array(
			'label'      => __( 'Item corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ============================ ITEM TEXT ============================ */
		$this->start_controls_section( 'ff_ma_text_style', array(
			'label'     => __( 'Item text', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'votes', 'notes', 'feedback' ) ),
		) );

		$this->add_control( 'main_h', array(
			'label' => __( 'Main text (title)', 'founding-faces' ),
			'type'  => \Elementor\Controls_Manager::HEADING,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'main_typo',
			'selector' => '{{WRAPPER}} .ff-history-item-main',
		) );
		$this->add_control( 'main_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item-main' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'detail_h', array(
			'label'     => __( 'Sub text (e.g. "You chose…")', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'      => 'detail_typo',
			'selector'  => '{{WRAPPER}} .ff-history-item-detail',
			'condition' => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_control( 'detail_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item-detail' => 'color: {{VALUE}};' ),
			'condition' => array( 'section' => array( 'all', 'votes' ) ),
		) );

		$this->add_control( 'date_h', array(
			'label'     => __( 'Date', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'date_typo',
			'selector' => '{{WRAPPER}} .ff-history-item-date',
		) );
		$this->add_control( 'date_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item-date' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'fbtext_h', array(
			'label'     => __( 'Feedback text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'section' => array( 'all', 'feedback' ) ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'      => 'fbtext_typo',
			'selector'  => '{{WRAPPER}} .ff-history-feedback-text',
			'condition' => array( 'section' => array( 'all', 'feedback' ) ),
		) );
		$this->add_control( 'fbtext_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-feedback-text' => 'color: {{VALUE}};' ),
			'condition' => array( 'section' => array( 'all', 'feedback' ) ),
		) );

		$this->add_control( 'link_h', array(
			'label'     => __( 'Note & feedback links', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'section' => array( 'all', 'notes', 'feedback' ) ),
		) );
		$this->add_control( 'link_color', array(
			'label'     => __( 'Link colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item-main a' => 'color: {{VALUE}};' ),
			'condition' => array( 'section' => array( 'all', 'notes', 'feedback' ) ),
		) );
		$this->add_control( 'link_hover', array(
			'label'     => __( 'Link hover colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-history-item-main a:hover' => 'color: {{VALUE}};' ),
			'condition' => array( 'section' => array( 'all', 'notes', 'feedback' ) ),
		) );

		$this->end_controls_section();
	}

	/**
	 * Build the HTML for the chosen section, real or sample.
	 *
	 * @param array    $s      The widget settings.
	 * @param int|null $mid    The member id, or null for the sample.
	 * @param bool     $sample Whether to render sample content.
	 * @return string
	 */
	private function build( $s, $mid, $sample ) {
		$section    = isset( $s['section'] ) ? $s['section'] : 'all';
		$heading    = isset( $s['heading'] ) ? $s['heading'] : '';
		$link       = ! isset( $s['link_notes'] ) || 'yes' === $s['link_notes'];
		$subheading = isset( $s['header_subheading'] ) ? $s['header_subheading'] : null;

		$out = '<div class="ff-history">';

		if ( 'all' === $section || 'header' === $section ) {
			$out .= $sample ? FF_History::sample_header( $subheading ) : FF_History::render_header( $mid, $subheading );
		}
		if ( 'all' === $section || 'votes' === $section ) {
			$h    = ( 'votes' === $section ) ? $heading : '';
			$out .= $sample ? FF_History::sample_votes( $h ) : FF_History::render_votes( $mid, $h );
		}
		if ( 'all' === $section || 'notes' === $section ) {
			$h    = ( 'notes' === $section ) ? $heading : '';
			$out .= $sample ? FF_History::sample_notes( $h, $link ) : FF_History::render_notes( $mid, $h, $link );
		}
		if ( 'all' === $section || 'feedback' === $section ) {
			$h    = ( 'feedback' === $section ) ? $heading : '';
			$out .= $sample ? FF_History::sample_feedback( $h ) : FF_History::render_feedback( $mid, $h );
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Render: the member's real data, sample data in the editor, or a prompt.
	 */
	protected function render() {
		FF_History::enqueue();
		$s = $this->get_settings_for_display();

		if ( FF_Gating::is_member() ) {
			echo $this->build( $s, get_current_user_id(), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( FF_History::is_editor() ) {
			echo $this->build( $s, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo '<div class="ff-notice">' . esc_html__( 'Each member sees their own activity here.', 'founding-faces' ) . '</div>';
			return;
		}
		echo FF_Display::members_only_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
