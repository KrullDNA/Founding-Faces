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
				'notes'    => __( 'Notes (unread first)', 'founding-faces' ),
				'feedback' => __( 'Feedback', 'founding-faces' ),
				'messages' => __( 'Private messages (conversations)', 'founding-faces' ),
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

		$this->add_control( 'show_note_product', array(
			'label'        => __( 'Show the product above each note', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'section' => array( 'all', 'notes' ) ),
			'description'  => __( 'Notes with no product attached simply skip the line.', 'founding-faces' ),
		) );

		$this->add_control( 'notes_per_page', array(
			'label'       => __( 'Notes per page', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 10,
			'min'         => 0,
			'max'         => 100,
			'condition'   => array( 'section' => array( 'all', 'notes' ) ),
			'description' => __( 'A "Load more" button fetches the next batch of this size without reloading the page. Set 0 to show every note at once.', 'founding-faces' ),
		) );

		$this->add_control( 'filters_heading', array(
			'label'     => __( 'Filters', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'section' => array( 'all', 'notes' ) ),
		) );
		foreach ( array(
			'filter_status'  => array( __( 'Read / unread filter', 'founding-faces' ), 'yes' ),
			'filter_product' => array( __( 'Product filter', 'founding-faces' ), 'yes' ),
			'filter_stage'   => array( __( 'Type (stage) filter', 'founding-faces' ), '' ),
			'filter_period'  => array( __( 'Date filter', 'founding-faces' ), 'yes' ),
			'filter_sort'    => array( __( 'Sort', 'founding-faces' ), 'yes' ),
		) as $key => $spec ) {
			$this->add_control( $key, array(
				'label'        => $spec[0],
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => $spec[1],
				'return_value' => 'yes',
				'condition'    => array( 'section' => array( 'all', 'notes' ) ),
			) );
		}

		$this->add_control( 'header_subheading', array(
			'label'       => __( 'Header subheading', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 2,
			'default'     => FF_History::default_subheading(),
			'placeholder' => __( 'Leave blank to hide the subheading', 'founding-faces' ),
			'condition'   => array( 'section' => array( 'all', 'header' ) ),
		) );

		$this->add_control( 'preview_mode', array(
			'label'       => __( 'Editor preview', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'sample',
			'separator'   => 'before',
			'options'     => array(
				'sample' => __( 'Sample content', 'founding-faces' ),
				'real'   => __( 'Your own record', 'founding-faces' ),
			),
			'description' => __( 'The editor shows samples so a full page of notes, the filters and the "Load more" button can all be styled. The front end always shows each member their own record.', 'founding-faces' ),
		) );

		$this->add_control( 'show_line', array(
			'label'        => __( 'Line under header', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'founding-faces' ),
			'label_off'    => __( 'Remove', 'founding-faces' ),
			'default'      => '',
			'return_value' => 'yes',
			'condition'    => array( 'section' => array( 'all', 'header' ) ),
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
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'group_border',
			'selector' => '{{WRAPPER}} .ff-history-group',
		) );
		$this->add_responsive_control( 'group_radius', array(
			'label'      => __( 'Group corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-group' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'group_padding', array(
			'label'      => __( 'Group padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-group' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'group_margin', array(
			'label'      => __( 'Group margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
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

		// A line under the header — only shown when the Content toggle is on.
		$this->add_control( 'header_divider_h', array(
			'label'     => __( 'Line under header', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
			'condition' => array( 'show_line' => 'yes' ),
		) );
		$this->add_control( 'header_divider_width', array(
			'label'     => __( 'Line thickness', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'default'   => array( 'size' => 1 ),
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid;' ),
			'condition' => array( 'show_line' => 'yes' ),
		) );
		$this->add_control( 'header_divider_color', array(
			'label'     => __( 'Line colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#d5d8dd',
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'border-bottom-color: {{VALUE}};' ),
			'condition' => array( 'show_line' => 'yes' ),
		) );
		$this->add_responsive_control( 'header_divider_gap', array(
			'label'     => __( 'Space above the line', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'default'   => array( 'size' => 12 ),
			'selectors' => array( '{{WRAPPER}} .ff-history-header' => 'padding-bottom: {{SIZE}}{{UNIT}};' ),
			'condition' => array( 'show_line' => 'yes' ),
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
		$this->add_responsive_control( 'heading_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-history-heading' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'heading_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'heading_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-history-heading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		// Underline: off by default, since the built-in accent rule is gone.
		$this->add_control( 'heading_ul', array(
			'label'        => __( 'Underline', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-history-heading' => 'display: inline-block; border-bottom-style: solid;' ),
		) );
		$this->add_control( 'heading_underline', array(
			'label'     => __( 'Underline colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'condition' => array( 'heading_ul' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} .ff-history-heading' => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_control( 'heading_ul_width', array(
			'label'     => __( 'Underline thickness', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'default'   => array( 'size' => 2, 'unit' => 'px' ),
			'condition' => array( 'heading_ul' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} .ff-history-heading' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'heading_spacing', array(
			'label'      => __( 'Spacing below', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'separator'  => 'before',
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
		/* =========================== FILTER BAR ============================ */
		$this->start_controls_section( 'ff_ma_filters_style', array(
			'label'     => __( 'Filter bar', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'notes' ) ),
		) );
		$this->add_control( 'nf_label_color', array(
			'label'     => __( 'Label colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-filters .ff-filter span' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'nf_label_typo',
			'label'    => __( 'Label text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-note-filters .ff-filter span',
		) );
		$this->add_control( 'nf_select_bg', array(
			'label'     => __( 'Select background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-note-filters select' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'nf_select_color', array(
			'label'     => __( 'Select text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-filters select' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'nf_select_typo',
			'label'    => __( 'Select text style', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-note-filters select',
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'nf_select_border',
			'selector' => '{{WRAPPER}} .ff-note-filters select',
		) );
		$this->add_responsive_control( 'nf_select_radius', array(
			'label'      => __( 'Select corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-filters select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'nf_select_padding', array(
			'label'      => __( 'Select padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-filters select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'nf_gap', array(
			'label'     => __( 'Gap between filters', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-note-filters' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'nf_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-filters' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ========================= LOAD MORE BUTTON ======================== */
		$this->start_controls_section( 'ff_ma_more_style', array(
			'label'     => __( '"Load more" button', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'notes' ) ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'more_typo',
			'selector' => '{{WRAPPER}} .ff-notes-more-button',
		) );
		$this->start_controls_tabs( 'more_tabs' );
		$this->start_controls_tab( 'more_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'more_color', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notes-more-button' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'more_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notes-more-button' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'more_h', array( 'label' => __( 'Hover', 'founding-faces' ) ) );
		$this->add_control( 'more_hcolor', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notes-more-button:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'more_hbg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-notes-more-button:hover' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'more_border',
			'selector'  => '{{WRAPPER}} .ff-notes-more-button',
			'separator' => 'before',
		) );
		$this->add_responsive_control( 'more_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notes-more-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'more_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notes-more-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'more_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-notes-more' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'more_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notes-more' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ========================= PRODUCT LABEL =========================== */
		$this->start_controls_section( 'ff_ma_product_style', array(
			'label'     => __( 'Product label', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array(
				'section'           => array( 'all', 'notes' ),
				'show_note_product' => 'yes',
			),
		) );
		$this->add_control( 'np_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-product' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'np_typo',
			'label'    => __( 'Typography', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-note-product',
		) );
		$this->add_control( 'np_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-note-product' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'np_border',
			'selector' => '{{WRAPPER}} .ff-note-product',
		) );
		$this->add_responsive_control( 'np_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-product' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'np_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-product' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'np_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-product' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ========================== UNREAD BADGE =========================== */
		$this->start_controls_section( 'ff_ma_unread_style', array(
			'label'     => __( '"Unread" badge', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'notes' ) ),
		) );
		$this->add_control( 'unread_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-unread-badge' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'unread_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-unread-badge' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'unread_typo',
			'label'    => __( 'Badge text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-unread-badge',
		) );
		$this->add_responsive_control( 'unread_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-unread-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'unread_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-unread-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'unread_gap', array(
			'label'     => __( 'Gap from the title', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-unread-badge' => 'margin-left: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'unread_row_h', array(
			'label'     => __( 'Unread row', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'unread_row_bg', array(
			'label'     => __( 'Row background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-row.is-unread' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'unread_row_weight', array(
			'label'     => __( 'Bold the unread titles', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => '700',
			'default'   => '700',
			'selectors' => array( '{{WRAPPER}} .ff-note-row.is-unread .ff-history-item-main' => 'font-weight: {{VALUE}};' ),
		) );
		$this->end_controls_section();

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

		// The same box treatment the Unread badge and the poll's closed capsule
		// get, so "You chose: …" can be made to stand out rather than read as
		// another line of body copy.
		$this->add_control( 'choice_bg', array(
			'label'     => __( 'Box background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-vote-choice' => 'background-color: {{VALUE}};' ),
			'condition' => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'choice_border',
			'selector'  => '{{WRAPPER}} .ff-vote-choice',
			'condition' => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_responsive_control( 'choice_radius', array(
			'label'      => __( 'Box corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-vote-choice' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'condition'  => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'      => 'choice_shadow',
			'selector'  => '{{WRAPPER}} .ff-vote-choice',
			'condition' => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_responsive_control( 'choice_padding', array(
			'label'      => __( 'Box padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-vote-choice' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'condition'  => array( 'section' => array( 'all', 'votes' ) ),
		) );
		$this->add_responsive_control( 'choice_margin', array(
			'label'      => __( 'Box margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-vote-choice' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			'condition'  => array( 'section' => array( 'all', 'votes' ) ),
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

		/* ========================= PRIVATE MESSAGES ======================= */
		$this->start_controls_section( 'ff_ma_messages_style', array(
			'label'     => __( 'Private messages', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'section' => array( 'all', 'messages' ) ),
		) );
		$this->add_control( 'msg_link_color', array(
			'label'     => __( 'Conversation link colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message-thread a' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'msg_badge_h', array(
			'label' => __( '"New message" badge', 'founding-faces' ),
			'type'  => \Elementor\Controls_Manager::HEADING,
		) );
		$this->add_control( 'msg_badge_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message-badge' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'msg_badge_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message-badge' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'msg_bubble_h', array(
			'label'     => __( 'Conversation bubbles', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'msg_bubble_member_bg', array(
			'label'     => __( 'Your message background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message--member' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'msg_bubble_admin_bg', array(
			'label'     => __( 'Reply background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-message--admin' => 'background-color: {{VALUE}};' ),
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
			$per   = isset( $s['notes_per_page'] ) ? absint( $s['notes_per_page'] ) : 10;
			$show  = array(
				'product' => ( isset( $s['filter_product'] ) && 'yes' === $s['filter_product'] ),
				'stage'   => ( isset( $s['filter_stage'] ) && 'yes' === $s['filter_stage'] ),
				'status'  => ( isset( $s['filter_status'] ) && 'yes' === $s['filter_status'] ),
				'period'  => ( isset( $s['filter_period'] ) && 'yes' === $s['filter_period'] ),
				'sort'    => ( isset( $s['filter_sort'] ) && 'yes' === $s['filter_sort'] ),
			);
			$show  = array_filter( $show );
			$prod  = ! isset( $s['show_note_product'] ) || 'yes' === $s['show_note_product'];
			$out  .= $sample
				? FF_History::sample_notes( $h, $link, $per, $show, $prod )
				: FF_History::render_notes( $mid, $h, $link, $per, $show, $prod );
		}
		if ( 'all' === $section || 'feedback' === $section ) {
			$h    = ( 'feedback' === $section ) ? $heading : '';
			$out .= $sample ? FF_History::sample_feedback( $h ) : FF_History::render_feedback( $mid, $h );
		}
		if ( 'all' === $section || 'messages' === $section ) {
			// The message centre self-detects the member; in the editor it
			// follows the same sample-first rule as the sections above.
			$out .= FF_Messages::sc_messages( ! $sample );
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

		$editing = FF_History::is_editor();
		$real    = isset( $s['preview_mode'] ) && 'real' === $s['preview_mode'];

		// Samples are what the editor shows. Nick's own record holds whatever
		// it happens to hold — two notes, no votes — and a design made against
		// that is a design with no page of notes, no badge and no button in it.
		if ( $editing && ! $real ) {
			echo $this->build( $s, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		if ( FF_Gating::is_member() ) {
			$html = $this->build( $s, get_current_user_id(), false );

			// In the editor, a member with no history yet (Nick's own account,
			// usually) leaves nothing to style — so fall back to the sample.
			if ( $editing && FF_History::sample_needed( $html ) ) {
				$html = $this->build( $s, null, true );
			}

			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( $editing ) {
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
