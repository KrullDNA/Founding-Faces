<?php
/**
 * The Founding Faces members-map Elementor widget.
 *
 * A thin wrapper around FF_Map::render_map(): it gathers the panel controls
 * into the same options array the shortcode uses, then renders through the one
 * shared renderer. It does not replace the shortcode, which stays as a fallback.
 *
 * Built for Elementor's Atomic architecture per the KDNA standard:
 * has_widget_inner_wrapper() reports correctly, the render output is a single
 * wrapper div, and no CSS depends on .elementor-widget-container.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Map_Widget
 */
class FF_Map_Widget extends \Elementor\Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_members_map';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Map', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-google-maps';
	}

	/** @return array */
	public function get_categories() {
		return array( 'founding-faces' );
	}

	/**
	 * The assets the widget needs, so Elementor loads Leaflet and the map
	 * script, including inside the editor, and only where the widget is used.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'leaflet', 'ff-map' );
	}

	/** @return array */
	public function get_style_depends() {
		return array( 'leaflet', 'founding-faces' );
	}

	/**
	 * Atomic architecture: no extra inner wrapper when optimised markup is on.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * Register the widget's controls.
	 */
	protected function register_controls() {
		$s = FF_Map::settings();

		/* -------------------------------------------------- Map behaviour -- */
		$this->start_controls_section( 'ff_map_behaviour', array(
			'label' => __( 'Map behaviour', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'center_lat', array(
			'label'   => __( 'Centre latitude', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => FF_Map::AU_CENTER_LAT,
			'step'    => 0.0001,
		) );
		$this->add_control( 'center_lng', array(
			'label'   => __( 'Centre longitude', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => FF_Map::AU_CENTER_LNG,
			'step'    => 0.0001,
		) );
		$this->add_control( 'zoom', array(
			'label'   => __( 'Default zoom', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 1, 'max' => 18 ) ),
			'default' => array( 'size' => 4 ),
		) );
		$this->add_control( 'min_zoom', array(
			'label'   => __( 'Minimum zoom', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 3,
			'min'     => 1,
			'max'     => 18,
		) );
		$this->add_control( 'max_zoom', array(
			'label'   => __( 'Maximum zoom', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 12,
			'min'     => 1,
			'max'     => 18,
		) );
		$this->add_control( 'scroll_zoom', array(
			'label'        => __( 'Scroll-wheel zoom', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '', // Off by default.
			'return_value' => 'yes',
		) );
		$this->add_control( 'dragging', array(
			'label'        => __( 'Pan / drag', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'lock_bounds', array(
			'label'        => __( 'Lock panning to Australia', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'condition'    => array( 'dragging' => 'yes' ),
		) );
		$this->add_control( 'zoom_control', array(
			'label'        => __( 'Show zoom buttons', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_responsive_control( 'height', array(
			'label'   => __( 'Map height', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 200, 'max' => 1000 ) ),
			'default' => array( 'size' => 520, 'unit' => 'px' ),
		) );

		$this->end_controls_section();

		/* -------------------------------------------------------- The dots -- */
		$this->start_controls_section( 'ff_map_dots', array(
			'label' => __( 'The dots', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'color_35', array(
			'label'   => __( 'The 35 colour', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => $s['c35_color'],
		) );
		$this->add_control( 'size_35', array(
			'label'   => __( 'The 35 size', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 2, 'max' => 30 ) ),
			'default' => array( 'size' => $s['c35_size'] ),
		) );
		$this->add_control( 'color_circle', array(
			'label'   => __( 'The Circle colour', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => $s['circle_color'],
		) );
		$this->add_control( 'size_circle', array(
			'label'   => __( 'The Circle size', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 2, 'max' => 30 ) ),
			'default' => array( 'size' => $s['circle_size'] ),
		) );
		$this->add_control( 'dot_opacity', array(
			'label'   => __( 'Dot opacity', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SLIDER,
			'range'   => array( 'px' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ) ),
			'default' => array( 'size' => 0.45 ),
		) );
		$this->add_control( 'stroke', array(
			'label'        => __( 'Dot border', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
		) );
		$this->add_control( 'stroke_color', array(
			'label'     => __( 'Border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'condition' => array( 'stroke' => 'yes' ),
		) );
		$this->add_control( 'stroke_width', array(
			'label'     => __( 'Border width', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
			'default'   => array( 'size' => 1 ),
			'condition' => array( 'stroke' => 'yes' ),
		) );

		$this->end_controls_section();

		/* ------------------------------------------------ Frame & styling -- */
		$this->start_controls_section( 'ff_map_legend_style', array(
			'label'     => __( 'Key', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'legend_show' => 'yes' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'lg_typo',
			'label'    => __( 'Typography', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-map-legend',
		) );
		$this->add_control( 'lg_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-map-legend' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'lg_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-map-legend' => 'background: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'lg_border',
			'selector' => '{{WRAPPER}} .ff-map-legend',
		) );
		$this->add_responsive_control( 'lg_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-legend' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'lg_shadow',
			'selector' => '{{WRAPPER}} .ff-map-legend',
		) );
		$this->add_responsive_control( 'lg_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-legend' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'lg_offset', array(
			'label'      => __( 'Distance from the map edge', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-legend' => 'margin: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'lg_gap', array(
			'label'      => __( 'Space between the lines', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-legend-item' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'lg_dot_gap', array(
			'label'      => __( 'Space between a dot and its label', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-legend-item' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'ff_map_count_style', array(
			'label'     => __( 'Member count', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'count_show' => 'yes' ),
		) );

		// Both homes, named together. The count is one line of copy wherever
		// it sits, so one set of controls governs it rather than the controls
		// changing under you when the position does.
		$mc = '{{WRAPPER}} .ff-map-count, {{WRAPPER}} .ff-map-legend-count';
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'mc_typo',
			'label'    => __( 'Typography', 'founding-faces' ),
			'selector' => $mc,
		) );
		$this->add_control( 'mc_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $mc => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'mc_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( $mc => 'text-align: {{VALUE}};' ),
		) );
		$this->add_control( 'mc_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( $mc => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'mc_border',
			'selector' => $mc,
		) );
		$this->add_responsive_control( 'mc_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( $mc => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'mc_shadow',
			'selector' => $mc,
		) );
		$this->add_responsive_control( 'mc_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $mc => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'mc_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $mc => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'mcn_heading', array(
			'label'       => __( 'The number itself', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::HEADING,
			'separator'   => 'before',
			'description' => __( 'Styles whatever {count}, {the35} and {circle} put on the page, apart from the words around them.', 'founding-faces' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'mcn_typo',
			'label'    => __( 'Typography', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-map-count-number',
		) );
		$this->add_control( 'mcn_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-map-count-number' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'mcn_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-map-count-number' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'mcn_border',
			'selector' => '{{WRAPPER}} .ff-map-count-number',
		) );
		$this->add_responsive_control( 'mcn_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-count-number' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'mcn_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-map-count-number' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'mc_rule', array(
			'label'        => __( 'Line above', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
			'description'  => __( 'A rule between the keys and the total, for when the two want separating.', 'founding-faces' ),
			'selectors'    => array( $mc => 'border-top-style: solid;' ),
		) );
		$this->add_control( 'mc_rule_color', array(
			'label'     => __( 'Line colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'condition' => array( 'mc_rule' => 'yes' ),
			'selectors' => array( $mc => 'border-top-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'mc_rule_width', array(
			'label'     => __( 'Line thickness', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'default'   => array( 'size' => 1, 'unit' => 'px' ),
			'condition' => array( 'mc_rule' => 'yes' ),
			'selectors' => array( $mc => 'border-top-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'mc_rule_gap', array(
			'label'     => __( 'Space above the line', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'condition' => array( 'mc_rule' => 'yes' ),
			'selectors' => array( $mc => 'margin-top: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'ff_map_frame', array(
			'label' => __( 'Frame & styling', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'tile_url', array(
			'label'       => __( 'Base tile source', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Uses the plugin setting (Positron) when blank', 'founding-faces' ),
			'description' => __( 'Leave blank to use the plugin-level tile setting.', 'founding-faces' ),
		) );

		$this->add_control( 'container_bg', array(
			'label'     => __( 'Container background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-members-map' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'container_border',
				'selector' => '{{WRAPPER}} .ff-members-map',
			)
		);

		$this->add_responsive_control( 'container_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array(
				'{{WRAPPER}} .ff-members-map' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			),
		) );

		$this->add_control( 'count_heading', array(
			'label'     => __( 'Member count', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'count_show', array(
			'label'        => __( 'Show the number of members', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
		) );
		$this->add_control( 'count_text', array(
			'label'       => __( 'Wording', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( '{count} founding faces across Australia', 'founding-faces' ),
			'condition'   => array( 'count_show' => 'yes' ),
			'description' => __( '{count} is everyone on the map, {the35} is The 35 and {circle} is the rest. Counted from the dots themselves, so the number and the map can never disagree.', 'founding-faces' ),
		) );
		$this->add_control( 'count_position', array(
			'label'       => __( 'Where it sits', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'legend',
			'options'     => array(
				'legend' => __( 'In the key, under The Circle', 'founding-faces' ),
				'above'  => __( 'Above the map', 'founding-faces' ),
				'below'  => __( 'Below the map', 'founding-faces' ),
			),
			'condition'   => array( 'count_show' => 'yes' ),
			'description' => __( 'In the key puts it under The Circle, styled with that box rather than on its own. With the key switched off it goes above the map instead, since showing the number is what you asked for.', 'founding-faces' ),
		) );

		$this->add_control( 'legend_heading', array(
			'label'     => __( 'Legend', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'legend_show', array(
			'label'        => __( 'Show legend', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
		) );
		$this->add_control( 'legend_position', array(
			'label'     => __( 'Legend position', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'bottomright',
			'options'   => array(
				'topleft'     => __( 'Top left', 'founding-faces' ),
				'topright'    => __( 'Top right', 'founding-faces' ),
				'bottomleft'  => __( 'Bottom left', 'founding-faces' ),
				'bottomright' => __( 'Bottom right', 'founding-faces' ),
			),
			'condition' => array( 'legend_show' => 'yes' ),
		) );
		$this->add_control( 'legend_35', array(
			'label'     => __( 'The 35 label', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'The 35', 'founding-faces' ),
			'condition' => array( 'legend_show' => 'yes' ),
		) );
		$this->add_control( 'legend_circle', array(
			'label'     => __( 'The Circle label', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'The Circle', 'founding-faces' ),
			'condition' => array( 'legend_show' => 'yes' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * Render the widget by gathering the controls and calling the shared
	 * renderer.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		// A small helper for reading a slider control's numeric size.
		$slider = function ( $value, $fallback ) {
			return isset( $value['size'] ) && '' !== $value['size'] ? $value['size'] : $fallback;
		};

		$args = array(
			'center'       => array( (float) $s['center_lat'], (float) $s['center_lng'] ),
			'zoom'         => (int) $slider( $s['zoom'], 4 ),
			'min_zoom'     => (int) $s['min_zoom'],
			'max_zoom'     => (int) $s['max_zoom'],
			'scroll_zoom'  => ( 'yes' === $s['scroll_zoom'] ),
			'dragging'     => ( 'yes' === $s['dragging'] ),
			'lock_bounds'  => ( 'yes' === $s['lock_bounds'] ),
			'zoom_control' => ( 'yes' === $s['zoom_control'] ),
			'height'       => (int) $slider( $s['height'], 520 ),
			'opacity'      => (float) $slider( $s['dot_opacity'], 0.45 ),
			'tiers'        => array(
				'35'     => array( 'color' => $s['color_35'], 'size' => (int) $slider( $s['size_35'], 8 ) ),
				'circle' => array( 'color' => $s['color_circle'], 'size' => (int) $slider( $s['size_circle'], 6 ) ),
			),
			'stroke'       => array(
				'on'    => ( 'yes' === $s['stroke'] ),
				'color' => $s['stroke_color'],
				'width' => (int) $slider( $s['stroke_width'], 1 ),
			),
			'count'        => array(
				'on'       => ( isset( $s['count_show'] ) && 'yes' === $s['count_show'] ),
				'position' => isset( $s['count_position'] ) && in_array( $s['count_position'], array( 'legend', 'above', 'below' ), true )
					? $s['count_position']
					: 'legend',
				'text'     => isset( $s['count_text'] ) ? $s['count_text'] : '',
			),
			'legend'       => array(
				'on'           => ( 'yes' === $s['legend_show'] ),
				'position'     => $s['legend_position'],
				'label_35'     => $s['legend_35'],
				'label_circle' => $s['legend_circle'],
			),
		);

		// Blank tile source falls back to the plugin-level setting.
		if ( isset( $s['tile_url'] ) && '' !== trim( $s['tile_url'] ) ) {
			$args['tile_url'] = trim( $s['tile_url'] );
		}

		echo FF_Map::render_map( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
