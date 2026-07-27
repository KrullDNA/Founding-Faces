<?php
/**
 * The Status Lookup Elementor widget — the "check your application" form.
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

		$this->end_controls_section();

		// This form has no field hints, so skip that Style section.
		$this->register_form_style_controls( false );
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

		echo FF_Application::render_status_lookup( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
