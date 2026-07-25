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
 * Class FF_Poll_Widget
 *
 * Renders a chosen poll (or the current active one) through FF_Polls, passing
 * the style settings straight into the renderer.
 */
class FF_Poll_Widget extends \Elementor\Widget_Base {

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
			'poll_id',
			array(
				'label'   => __( 'Which poll', 'founding-faces' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => FF_Polls::poll_choices(),
			)
		);

		$this->end_controls_section();

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
	}

	/**
	 * Render the widget on the front end.
	 *
	 * Hands the chosen poll and the style settings to FF_Polls, which produces
	 * the single wrapper div and enforces the audience gate.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$poll_id = isset( $settings['poll_id'] ) ? absint( $settings['poll_id'] ) : 0;
		$spacing = isset( $settings['spacing']['size'] ) ? absint( $settings['spacing']['size'] ) : 16;

		// FF_Polls::render_poll outputs the single wrapper and its inner state.
		echo FF_Polls::render_poll( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$poll_id,
			array(
				'accent'  => isset( $settings['accent'] ) ? $settings['accent'] : '#3a3d44',
				'align'   => isset( $settings['align'] ) ? $settings['align'] : 'left',
				'spacing' => $spacing,
			)
		);
	}
}
