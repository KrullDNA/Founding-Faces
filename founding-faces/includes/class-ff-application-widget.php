<?php
/**
 * The Application Form Elementor widget — the intake form, fully styleable.
 *
 * A thin wrapper around FF_Application::render_form(): it renders the exact
 * same form markup as the [ff_application_form] shortcode (one source of truth)
 * and adds a complete Style tab (shared with the Status Lookup widget through
 * the FF_Form_Style_Controls trait) — form box, labels, fields, hints, the
 * submit button and the success/error notices — every control scoped to this
 * widget so it never leaks into another form on the page.
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
 * Class FF_Application_Widget
 */
class FF_Application_Widget extends \Elementor\Widget_Base {

	use FF_Form_Style_Controls;

	public function get_name() {
		return 'ff_application_form';
	}
	public function get_title() {
		return __( 'Founding Faces Application Form', 'founding-faces' );
	}
	public function get_icon() {
		return 'eicon-form-horizontal';
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
		$this->start_controls_section( 'ff_af_content', array(
			'label' => __( 'Content', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'button_label', array(
			'label'       => __( 'Button text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Submit application', 'founding-faces' ),
			'placeholder' => __( 'Submit application', 'founding-faces' ),
		) );

		$this->add_control( 'success_message', array(
			'label'       => __( 'Thank-you message', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 3,
			'default'     => '',
			'placeholder' => __( 'Shown after a successful submission. Leave blank for the default.', 'founding-faces' ),
		) );

		$this->add_control( 'editor_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'The live form appears on the front end. What you see in the editor is a preview for styling.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->end_controls_section();

		// The full, shared form Style tab (form box, labels, fields, hints,
		// button, notices).
		$this->register_form_style_controls( true );
	}

	/**
	 * Render the form: the same markup as the shortcode, with the widget's own
	 * button label and thank-you message applied.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$args = array();
		if ( ! empty( $s['button_label'] ) ) {
			$args['button_label'] = $s['button_label'];
		}
		if ( ! empty( $s['success_message'] ) ) {
			$args['success_message'] = $s['success_message'];
		}

		echo FF_Application::render_form( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
