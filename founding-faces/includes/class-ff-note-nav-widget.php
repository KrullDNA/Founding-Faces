<?php
/**
 * The Elementor widget for stepping between the versions of one product.
 *
 * The notes list is one stream across every product, but a member reading
 * version 12 of a serum wants version 11 of that serum, not whatever was
 * published in between for something else. This widget links to the note
 * before and after within the same product, and skips over any version the
 * reader is not allowed to see rather than landing them on a locked page.
 *
 * Nothing here decides how it looks. Every colour, border and space is a
 * control.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FF_PATH . 'includes/class-ff-display-widgets.php';

/**
 * Class FF_Note_Nav_Widget
 */
class FF_Note_Nav_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_note_nav';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Note Navigation', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-post-navigation';
	}

	/** @return array */
	public function get_keywords() {
		return array( 'note', 'navigation', 'previous', 'next', 'version', 'founding faces' );
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->content_section();
		$this->row_style_section();
		$this->link_style_section();
		$this->text_style_section();
		$this->icon_style_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Content.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * What the two links say, and what happens at the ends.
	 */
	private function content_section() {
		$this->start_controls_section( 'ff_nav_content', array(
			'label' => __( 'Navigation', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'nav_note', array(
			'label'       => __( 'Note', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 0,
			'options'     => array( 0 => __( 'The note on this page (automatic)', 'founding-faces' ) ) + array_slice( FF_Display::note_choices(), 1, null, true ),
			'description' => __( 'Left automatic, this steps through whichever note is being read, which is what a Single Note template needs.', 'founding-faces' ),
		) );

		$this->add_control( 'show', array(
			'label'   => __( 'Show', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'both',
			'options' => array(
				'both' => __( 'Both directions', 'founding-faces' ),
				'prev' => __( 'The previous version only', 'founding-faces' ),
				'next' => __( 'The next version only', 'founding-faces' ),
			),
		) );

		$this->add_control( 'prev_text', array(
			'label'       => __( 'Back label', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Previous version', 'founding-faces' ),
			'label_block' => true,
		) );

		$this->add_control( 'prev_icon', array(
			'label'   => __( 'Back icon', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::ICONS,
			'default' => array(
				'value'   => 'eicon-chevron-left',
				'library' => 'eicons',
			),
		) );

		$this->add_control( 'next_text', array(
			'label'       => __( 'Forward label', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Next version', 'founding-faces' ),
			'label_block' => true,
			'separator'   => 'before',
		) );

		$this->add_control( 'next_icon', array(
			'label'   => __( 'Forward icon', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::ICONS,
			'default' => array(
				'value'   => 'eicon-chevron-right',
				'library' => 'eicons',
			),
		) );

		$this->add_control( 'detail', array(
			'label'       => __( 'Second line', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'version',
			'separator'   => 'before',
			'options'     => array(
				'version' => __( 'The version it goes to', 'founding-faces' ),
				'title'   => __( 'The note title', 'founding-faces' ),
				'none'    => __( 'Nothing, just the label', 'founding-faces' ),
			),
			'description' => __( 'Saying where a link goes is worth the line. "Previous version" alone leaves the reader guessing how far back they are about to step.', 'founding-faces' ),
		) );

		$this->add_control( 'missing', array(
			'label'       => __( 'At the first or last version', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'hide',
			'options'     => array(
				'hide' => __( 'Take the link away', 'founding-faces' ),
				'keep' => __( 'Leave it in place, not clickable', 'founding-faces' ),
			),
			'description' => __( 'Kept in place, the row holds its shape on the oldest and newest notes instead of shifting. It is drawn as plain text, so nothing is clickable that goes nowhere.', 'founding-faces' ),
		) );

		$this->ffds_preview_control();

		$this->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Style.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The row the two links sit in.
	 */
	private function row_style_section() {
		$this->start_controls_section( 'ff_nav_row', array(
			'label' => __( 'The row', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'row_align', array(
			'label'   => __( 'Arrangement', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'space-between',
			'options' => array(
				'space-between' => __( 'One at each end', 'founding-faces' ),
				'flex-start'    => __( 'Together, left', 'founding-faces' ),
				'center'        => __( 'Together, centred', 'founding-faces' ),
				'flex-end'      => __( 'Together, right', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-note-nav' => 'justify-content: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'row_gap', array(
			'label'      => __( 'Space between them', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-nav' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'row_stack', array(
			'label'     => __( 'Stack them', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'row',
			'options'   => array(
				'row'    => __( 'Side by side', 'founding-faces' ),
				'column' => __( 'One above the other', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-note-nav' => 'flex-direction: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'row_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-nav' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'row_border_top', array(
			'label'      => __( 'Rule above', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 10 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-nav' => 'border-top-style: solid; border-top-width: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'row_border_color', array(
			'label'     => __( 'Rule colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-nav' => 'border-top-color: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'row_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-nav' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The links themselves, as buttons or as bare text.
	 */
	private function link_style_section() {
		$this->start_controls_section( 'ff_nav_link', array(
			'label' => __( 'The links', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$sel = '{{WRAPPER}} .ff-note-nav-link';

		$this->add_responsive_control( 'link_width', array(
			'label'      => __( 'Minimum width', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 400 ), '%' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array( $sel => 'min-width: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'link_inner_align', array(
			'label'   => __( 'Contents sit', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'flex-start',
			'options' => array(
				'flex-start'    => __( 'Left', 'founding-faces' ),
				'center'        => __( 'Centred', 'founding-faces' ),
				'flex-end'      => __( 'Right', 'founding-faces' ),
				'space-between' => __( 'Pushed apart', 'founding-faces' ),
			),
			'selectors' => array( $sel => 'justify-content: {{VALUE}};' ),
		) );

		$this->start_controls_tabs( 'link_tabs' );

		$this->start_controls_tab( 'link_normal', array( 'label' => __( 'Normal', 'founding-faces' ) ) );

		$this->add_control( 'link_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel => 'background-color: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'link_border',
			'selector' => $sel,
		) );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'link_shadow',
			'selector' => $sel,
		) );

		$this->end_controls_tab();

		$this->start_controls_tab( 'link_hover', array( 'label' => __( 'Hover', 'founding-faces' ) ) );

		$this->add_control( 'link_bg_hover', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel . ':hover' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'link_border_hover', array(
			'label'     => __( 'Border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel . ':hover' => 'border-color: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'link_shadow_hover',
			'selector' => $sel . ':hover',
		) );

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control( 'link_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'separator'  => 'before',
			'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'link_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'spent_heading', array(
			'label'     => __( 'At the ends', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_control( 'spent_opacity', array(
			'label'       => __( 'Fade', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'range'       => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
			'default'     => array( 'size' => 0.4 ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-nav-link.is-spent' => 'opacity: {{SIZE}};' ),
			'description' => __( 'Only seen when the link is set to stay in place at the first or last version.', 'founding-faces' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The label and the line under it.
	 */
	private function text_style_section() {
		$this->start_controls_section( 'ff_nav_text', array(
			'label' => __( 'The wording', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'label_typo',
			'label'    => __( 'Label', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-note-nav-label',
		) );

		$this->start_controls_tabs( 'label_tabs' );

		$this->start_controls_tab( 'label_normal', array( 'label' => __( 'Normal', 'founding-faces' ) ) );

		$this->add_control( 'label_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-nav-label' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_tab();

		$this->start_controls_tab( 'label_hover_tab', array( 'label' => __( 'Hover', 'founding-faces' ) ) );

		$this->add_control( 'label_color_hover', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-nav-link:hover .ff-note-nav-label' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control( 'detail_heading', array(
			'label'     => __( 'The second line', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'detail_typo',
			'selector' => '{{WRAPPER}} .ff-note-nav-detail',
		) );

		$this->add_control( 'detail_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-nav-detail' => 'color: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'detail_gap', array(
			'label'      => __( 'Space above it', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-nav-detail' => 'margin-top: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'text_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-note-nav-text' => 'text-align: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The arrows.
	 */
	private function icon_style_section() {
		$this->start_controls_section( 'ff_nav_icon', array(
			'label' => __( 'The icons', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$sel = '{{WRAPPER}} .ff-note-nav-icon';

		$this->add_responsive_control( 'icon_size', array(
			'label'      => __( 'Size', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 6, 'max' => 60 ) ),
			'selectors'  => array(
				$sel . ' svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				$sel . ' i'   => 'font-size: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'icon_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				$sel           => 'color: {{VALUE}};',
				$sel . ' svg'  => 'fill: {{VALUE}};',
			),
		) );

		$this->add_control( 'icon_color_hover', array(
			'label'     => __( 'Colour on hover', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .ff-note-nav-link:hover .ff-note-nav-icon'      => 'color: {{VALUE}};',
				'{{WRAPPER}} .ff-note-nav-link:hover .ff-note-nav-icon svg'  => 'fill: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'icon_gap', array(
			'label'      => __( 'Space beside the wording', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-nav-link' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Render.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Turn the chosen icons into markup once, for both renders.
	 *
	 * @param array $s The widget settings.
	 * @return array
	 */
	private function args( $s ) {
		$icons = array();

		foreach ( array( 'prev', 'next' ) as $dir ) {
			$icons[ $dir ] = '';

			if ( ! empty( $s[ $dir . '_icon' ]['value'] ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $s[ $dir . '_icon' ], array( 'aria-hidden' => 'true' ) );
				$icons[ $dir ] = ob_get_clean();
			}
		}

		return array(
			'show'      => isset( $s['show'] ) ? $s['show'] : 'both',
			'prev_text' => isset( $s['prev_text'] ) ? $s['prev_text'] : '',
			'next_text' => isset( $s['next_text'] ) ? $s['next_text'] : '',
			'prev_icon' => $icons['prev'],
			'next_icon' => $icons['next'],
			'detail'    => isset( $s['detail'] ) ? $s['detail'] : 'version',
			'missing'   => isset( $s['missing'] ) ? $s['missing'] : 'hide',
		);
	}

	/** Render. */
	protected function render() {
		$s    = $this->get_settings_for_display();
		$args = $this->args( $s );

		$html = '';

		if ( FF_Gating::can_view_members_area() ) {
			$html = FF_Display::note_nav_html(
				FF_Display::note_context_id( isset( $s['nav_note'] ) ? $s['nav_note'] : 0 ),
				$args
			);
		}

		// The editor gets both ends live whatever note the preview happens to
		// be sitting on, because a design has to cover the full row.
		if ( $this->ffds_force_sample( $s ) || ( $this->ffds_is_editor() && $this->ffds_needs_sample( $html ) ) ) {
			$html = FF_Display::sample_note_nav( $args );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped in FF_Display.
	}
}
