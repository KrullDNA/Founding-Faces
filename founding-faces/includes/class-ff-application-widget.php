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

		$defaults = FF_Application::text_defaults();

		// These two keep their original control names so the wording already
		// saved on a live page survives the update; FF_Application::text()
		// maps both onto the keys it uses internally.
		$this->add_control( 'button_label', array(
			'label'       => __( 'Button text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => $defaults['button'],
			'placeholder' => $defaults['button'],
			'label_block' => true,
		) );

		$this->add_control( 'success_message', array(
			'label'       => __( 'Thank-you message', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 3,
			'default'     => $defaults['success'],
			'placeholder' => $defaults['success'],
		) );

		$this->add_control( 'required_mark', array(
			'label'       => __( 'Required-field marker', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => $defaults['required_mark'],
			'description' => __( 'Clear it to drop the marker from every required field.', 'founding-faces' ),
		) );

		$this->add_control( 'editor_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'The live form appears on the front end. What you see in the editor is a preview for styling.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->end_controls_section();

		/* =============================== FIELDS ============================== */
		// One group per field: its label, its placeholder and the hint beneath
		// it. Clearing a hint or a placeholder removes it; clearing a label
		// hides it on screen but keeps the field named for screen readers.
		$this->start_controls_section( 'ff_af_words', array(
			'label' => __( 'Field wording', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$groups = array(
			'name'      => __( 'Full name', 'founding-faces' ),
			'email'     => __( 'Email address', 'founding-faces' ),
			'postcode'  => __( 'Postcode', 'founding-faces' ),
			'instagram' => __( 'Instagram handle', 'founding-faces' ),
			'concerns'  => __( 'Skin concerns', 'founding-faces' ),
			'answers'   => __( 'About your skin', 'founding-faces' ),
			'consent'   => __( 'Consent checkbox', 'founding-faces' ),
		);

		foreach ( $groups as $key => $group_label ) {
			$this->add_control( $key . '_group', array(
				'label'     => $group_label,
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			) );

			$this->add_control( $key . '_label', array(
				'label'       => __( 'Label', 'founding-faces' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $defaults[ $key . '_label' ],
				'placeholder' => $defaults[ $key . '_label' ],
				'label_block' => true,
			) );

			// The consent line is the label; it has no field of its own to fill.
			if ( 'consent' !== $key ) {
				$this->add_control( $key . '_placeholder', array(
					'label'       => __( 'Placeholder', 'founding-faces' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => $defaults[ $key . '_placeholder' ],
					'label_block' => true,
				) );
			}

			$this->add_control( $key . '_hint', array(
				'label'       => __( 'Hint below', 'founding-faces' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => $defaults[ $key . '_hint' ],
				'label_block' => true,
			) );
		}

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

		$args = array( 'form_class' => 'ff-form--full' );

		// Every wording control shares its name with the key the form expects,
		// so the whole set carries over without a mapping table.
		$keys = array_merge(
			array_keys( FF_Application::text_defaults() ),
			array( 'button_label', 'success_message' )
		);

		foreach ( $keys as $key ) {
			if ( isset( $s[ $key ] ) ) {
				$args[ $key ] = $s[ $key ];
			}
		}

		echo FF_Application::render_form( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
