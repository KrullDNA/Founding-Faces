<?php
/**
 * Shared Style-tab controls for the Founding Faces display widgets.
 *
 * The notes, single-note, home and product-header widgets all render the same
 * handful of on-brand elements (a card, a heading, a meta row, body copy, a
 * link). Rather than repeat the same twenty controls in each widget, they live
 * here once as small, composable helpers, each scoped to {{WRAPPER}} so nothing
 * leaks out of the widget it belongs to.
 *
 * Every helper offers the full set a designer expects: typography, colour,
 * margin and padding, plus, on headings and links, an explicit underline
 * toggle so the built-in look can be turned on or off per widget.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait FF_Display_Style_Controls
 */
trait FF_Display_Style_Controls {

	/**
	 * A heading: typography, colour, alignment, margin, padding and an
	 * optional underline (off by default, the built-in accent underline is
	 * gone, this brings it back only when wanted).
	 *
	 * @param string $prefix   Unique control-name prefix.
	 * @param string $selector CSS selector under {{WRAPPER}} (e.g. '.ff-note-title').
	 * @param string $label    Section label.
	 */
	protected function ffds_heading_section( $prefix, $selector, $label ) {
		$sel = '{{WRAPPER}} ' . $selector;

		$this->start_controls_section( $prefix . '_sec', array(
			'label' => $label,
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( $prefix . '_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => $prefix . '_typo',
			'selector' => $sel,
		) );
		$this->add_responsive_control( $prefix . '_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => $this->ffds_align_options(),
			'selectors' => array( $sel => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( $prefix . '_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $sel => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( $prefix . '_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		// Underline (off by default). Rendered as a bottom border so its colour
		// and thickness are controllable, unlike text-decoration.
		$this->add_control( $prefix . '_ul', array(
			'label'        => __( 'Underline', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( $sel => 'display: inline-block; border-bottom-style: solid;' ),
		) );
		$this->add_control( $prefix . '_ul_color', array(
			'label'     => __( 'Underline colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '',
			'condition' => array( $prefix . '_ul' => 'yes' ),
			'selectors' => array( $sel => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_control( $prefix . '_ul_width', array(
			'label'     => __( 'Underline thickness', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'default'   => array( 'size' => 2, 'unit' => 'px' ),
			'condition' => array( $prefix . '_ul' => 'yes' ),
			'selectors' => array( $sel => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( $prefix . '_ul_gap', array(
			'label'     => __( 'Gap above underline', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'condition' => array( $prefix . '_ul' => 'yes' ),
			'selectors' => array( $sel => 'padding-bottom: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * A block of body/meta text: typography, colour, alignment, margin, padding,
	 * plus a link-underline toggle for any links inside it.
	 *
	 * @param string $prefix   Unique control-name prefix.
	 * @param string $selector CSS selector under {{WRAPPER}}.
	 * @param string $label    Section label.
	 * @param bool   $links    Whether to add the link-underline toggle.
	 */
	protected function ffds_text_section( $prefix, $selector, $label, $links = false ) {
		$sel = '{{WRAPPER}} ' . $selector;

		$this->start_controls_section( $prefix . '_sec', array(
			'label' => $label,
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( $prefix . '_color', array(
			'label'     => __( 'Text colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => $prefix . '_typo',
			'selector' => $sel,
		) );
		$this->add_responsive_control( $prefix . '_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => $this->ffds_align_options(),
			'selectors' => array( $sel => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( $prefix . '_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $sel => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( $prefix . '_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		if ( $links ) {
			$this->add_control( $prefix . '_link_color', array(
				'label'     => __( 'Link colour', 'founding-faces' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( $sel . ' a' => 'color: {{VALUE}};' ),
			) );
			$this->add_control( $prefix . '_link_ul', array(
				'label'        => __( 'Underline links', 'founding-faces' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'underline',
				'default'      => '',
				'selectors'    => array( $sel . ' a' => 'text-decoration: {{VALUE}};' ),
			) );
		}

		$this->end_controls_section();
	}

	/**
	 * The card container: background, border, radius, padding, margin.
	 *
	 * @param string $prefix   Unique control-name prefix.
	 * @param string $selector CSS selector under {{WRAPPER}}.
	 * @param string $label    Section label.
	 */
	protected function ffds_card_section( $prefix, $selector, $label ) {
		$sel = '{{WRAPPER}} ' . $selector;

		$this->start_controls_section( $prefix . '_sec', array(
			'label' => $label,
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( $prefix . '_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => $prefix . '_border',
			'selector' => $sel,
		) );
		$this->add_responsive_control( $prefix . '_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => $prefix . '_shadow',
			'selector' => $sel,
		) );
		$this->add_responsive_control( $prefix . '_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( $prefix . '_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $sel => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * A standalone link/button: colour, hover, typography, margin, underline.
	 *
	 * @param string $prefix   Unique control-name prefix.
	 * @param string $selector CSS selector under {{WRAPPER}}.
	 * @param string $label    Section label.
	 */
	protected function ffds_link_section( $prefix, $selector, $label ) {
		$sel = '{{WRAPPER}} ' . $selector;

		$this->start_controls_section( $prefix . '_sec', array(
			'label' => $label,
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( $prefix . '_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel => 'color: {{VALUE}};' ),
		) );
		$this->add_control( $prefix . '_hover', array(
			'label'     => __( 'Hover colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel . ':hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => $prefix . '_typo',
			'selector' => $sel,
		) );
		$this->add_control( $prefix . '_ul', array(
			'label'        => __( 'Underline', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'underline',
			'default'      => '',
			'selectors'    => array( $sel => 'text-decoration: {{VALUE}};' ),
		) );
		$this->add_responsive_control( $prefix . '_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $sel => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The alignment CHOOSE options, shared by headings and text blocks.
	 *
	 * @return array
	 */
	private function ffds_align_options() {
		return array(
			'left'    => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
			'center'  => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
			'right'   => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			'justify' => array( 'title' => __( 'Justify', 'founding-faces' ), 'icon' => 'eicon-text-align-justify' ),
		);
	}

	/**
	 * Whether the widget is rendering inside the Elementor editor/preview.
	 *
	 * Used to show representative dummy content so every element can be styled
	 * even before a real note or product is chosen (or when the current user
	 * wouldn't normally see gated content).
	 *
	 * @return bool
	 */
	protected function ffds_is_editor() {
		return \Elementor\Plugin::$instance->editor->is_edit_mode()
			|| ( isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether a real render gave us nothing worth designing against.
	 *
	 * Deliberately based on the output, not on who is looking. An administrator
	 * passes the members gate, so "is the viewer a member?" is the wrong
	 * question in the editor, Nick is always a member and would never see the
	 * samples. What matters is whether any real content actually came back: an
	 * empty state, a gate notice or a blank string all mean there is nothing on
	 * screen to style, so the sample takes over.
	 *
	 * @param string $html The real render output.
	 * @return bool
	 */
	/**
	 * The "Editor preview" control: real data, or always the sample.
	 *
	 * Auto only falls back to samples when the real render comes back empty,
	 * which is right most of the time, but a page with two real notes on it
	 * can never show what ten look like. This is how that is asked for.
	 */
	protected function ffds_preview_control() {
		$this->add_control( 'preview_mode', array(
			'label'       => __( 'Editor preview', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'sample',
			'separator'   => 'before',
			'options'     => array(
				'sample' => __( 'Sample content', 'founding-faces' ),
				'real'   => __( 'The real content on this site', 'founding-faces' ),
			),
			'description' => __( 'The editor shows samples so every element can be styled. The front end always shows the real thing, whichever is chosen here.', 'founding-faces' ),
		) );
	}

	/**
	 * Whether the editor should draw sample content rather than the real thing.
	 *
	 * Samples are the default, not the fallback. The editor is where the design
	 * is made, and a design has to cover the full case, every badge, a whole
	 * page of rows, the button under them, not whatever this site's records
	 * happen to hold today. Choosing "the real content" opts out.
	 *
	 * @param array $settings The widget settings.
	 * @return bool
	 */
	protected function ffds_force_sample( $settings ) {
		if ( ! $this->ffds_is_editor() ) {
			return false;
		}

		return ! isset( $settings['preview_mode'] ) || 'real' !== $settings['preview_mode'];
	}

	protected function ffds_needs_sample( $html ) {
		$html = (string) $html;

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return true;
		}

		// The empty-state and notice markers: "no notes yet", "members only",
		// "that note could not be found", and so on.
		foreach ( array( 'ff-empty-note', 'ff-members-only', 'ff-notice', 'ff-vault' ) as $marker ) {
			if ( false !== strpos( $html, $marker ) ) {
				return true;
			}
		}

		return false;
	}
}
