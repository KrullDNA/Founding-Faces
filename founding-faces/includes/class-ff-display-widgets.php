<?php
/**
 * Elementor widgets for the frontend display components.
 *
 * Thin wrappers around the FF_Display shortcode methods, so a note appears on a
 * page by dragging a widget and choosing a product (or note) from a dropdown —
 * the same designed-once template renders it automatically. The shortcodes stay
 * as a fallback; these widgets don't replace them.
 *
 * All widgets are built for Elementor's Atomic architecture: correct
 * has_widget_inner_wrapper(), a single wrapper div, no reliance on
 * .elementor-widget-container.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Atomic helpers for the display widgets.
 */
abstract class FF_Display_Widget_Base extends \Elementor\Widget_Base {

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
}

/**
 * Notes by product — the workhorse. All notes for a product, newest first,
 * optionally filtered by stage.
 */
class FF_Notes_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_notes';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Notes', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->start_controls_section( 'ff_notes_content', array(
			'label' => __( 'Notes', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'product', array(
			'label'   => __( 'Product', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 0,
			'options' => FF_Display::product_choices(),
		) );
		$this->add_control( 'stage', array(
			'label'   => __( 'Only show stage', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '',
			'options' => FF_Display::stage_choices(),
		) );
		$this->add_control( 'filters', array(
			'label'        => __( 'Show stage filter chips', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'limit', array(
			'label'   => __( 'Maximum notes', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 50,
			'min'     => 1,
			'max'     => 200,
		) );

		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();
		echo FF_Display::sc_notes( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'product' => isset( $s['product'] ) ? absint( $s['product'] ) : 0,
			'stage'   => isset( $s['stage'] ) ? $s['stage'] : '',
			'filters' => ( isset( $s['filters'] ) && 'yes' === $s['filters'] ) ? 'yes' : 'no',
			'limit'   => isset( $s['limit'] ) ? absint( $s['limit'] ) : 50,
		) );
	}
}

/**
 * A single note by id.
 */
class FF_Note_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_note';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Single Note', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-document-file';
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->start_controls_section( 'ff_note_content', array(
			'label' => __( 'Note', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'note_id', array(
			'label'   => __( 'Note', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 0,
			'options' => FF_Display::note_choices(),
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();
		echo FF_Display::sc_note( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'id' => isset( $s['note_id'] ) ? absint( $s['note_id'] ) : 0,
		) );
	}
}

/**
 * A product header (name, current stage, where it's up to).
 */
class FF_Product_Header_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_product_header';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Product Header', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-header';
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->start_controls_section( 'ff_ph_content', array(
			'label' => __( 'Product', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'product', array(
			'label'   => __( 'Product', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 0,
			'options' => FF_Display::product_choices(),
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();
		echo FF_Display::sc_product_header( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'product' => isset( $s['product'] ) ? absint( $s['product'] ) : 0,
		) );
	}
}

/**
 * The hybrid members home: a latest-notes feed above the products list.
 */
class FF_Home_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_home';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Home', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-posts-grid';
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->start_controls_section( 'ff_home_content', array(
			'label' => __( 'Home', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'latest', array(
			'label'   => __( 'Notes in the latest feed', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 8,
			'min'     => 1,
			'max'     => 50,
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();
		echo FF_Display::sc_home( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'latest' => isset( $s['latest'] ) ? absint( $s['latest'] ) : 8,
		) );
	}
}
