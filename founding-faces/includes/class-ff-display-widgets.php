<?php
/**
 * Elementor widgets for the frontend display components.
 *
 * Thin wrappers around the FF_Display shortcode methods, so a note appears on a
 * page by dragging a widget and choosing a product (or note) from a dropdown ,
 * the same designed-once template renders it automatically. The shortcodes stay
 * as a fallback; these widgets don't replace them.
 *
 * All widgets are built for Elementor's Atomic architecture: correct
 * has_widget_inner_wrapper(), a single wrapper div, no reliance on
 * .elementor-widget-container. Each carries a full Style tab through the
 * FF_Display_Style_Controls trait, and shows representative dummy content in
 * the editor so every element can be styled before real content exists.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FF_PATH . 'includes/trait-ff-display-style.php';

/**
 * Shared Atomic helpers for the display widgets.
 */
abstract class FF_Display_Widget_Base extends \Elementor\Widget_Base {

	use FF_Display_Style_Controls;

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

	/**
	 * The Style sections shared by every widget that renders note cards.
	 */
	protected function register_note_card_style() {
		$this->ffds_card_section( 'card', '.ff-note', __( 'Note card', 'founding-faces' ) );
		$this->ffds_heading_section( 'ntitle', '.ff-note-title', __( 'Note title', 'founding-faces' ) );
		$this->ffds_text_section( 'nmeta', '.ff-note-meta', __( 'Note meta row', 'founding-faces' ) );
		$this->ffds_badge_section();
		$this->ffds_text_section( 'nbody', '.ff-note-body', __( 'Note body', 'founding-faces' ), true );
		$this->ffds_link_section( 'nmore', '.ff-note-more-link', __( '"Read the full note" link', 'founding-faces' ) );
		$this->ffds_gallery_section();
		$this->ffds_text_section( 'empty', '.ff-empty-note', __( 'Empty message', 'founding-faces' ) );
	}

	/**
	 * The parts of a note card, and how much of the body to show.
	 *
	 * Every part is on by default: a card asked for with no opinion is the whole
	 * card. Turning one off leaves nothing behind, no empty header holding
	 * space where a title used to be.
	 */
	protected function ffds_card_content_controls() {
		$this->add_control( 'parts_heading', array(
			'label'     => __( 'Show on each note', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		foreach ( self::ffds_card_parts() as $key => $label ) {
			$this->add_control( 'card_' . $key, array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			) );
		}

		$this->add_control( 'meta_sep', array(
			'label'       => __( 'Between the chips', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '|',
			'separator'   => 'before',
			'placeholder' => __( 'Leave blank for none', 'founding-faces' ),
			'description' => __( 'Goes between the version and the date, which have no edge of their own. Never beside the stage badge or the vault chip, they have a shape already. Styled under Badges & chips.', 'founding-faces' ),
		) );

		$this->add_control( 'body_unit', array(
			'label'     => __( 'Body length', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => '',
			'separator' => 'before',
			'options'   => array(
				''           => __( 'The whole note', 'founding-faces' ),
				'words'      => __( 'Shorten to a number of words', 'founding-faces' ),
				'characters' => __( 'Shorten to a number of characters', 'founding-faces' ),
			),
			'condition' => array( 'card_body' => 'yes' ),
		) );
		$this->add_control( 'body_trim', array(
			'label'       => __( 'How many', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 40,
			'min'         => 1,
			'max'         => 2000,
			'condition'   => array( 'card_body' => 'yes', 'body_unit!' => '' ),
			'description' => __( 'A shortened body is plain text, formatting is dropped rather than cut in half.', 'founding-faces' ),
		) );
		$this->add_control( 'body_more', array(
			'label'        => __( 'Add a link to the full note', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'card_body' => 'yes', 'body_unit!' => '' ),
		) );
		$this->add_control( 'body_more_text', array(
			'label'       => __( 'Link wording', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => __( 'Read the full note', 'founding-faces' ),
			'condition'   => array( 'card_body' => 'yes', 'body_unit!' => '', 'body_more' => 'yes' ),
		) );
	}

	/**
	 * The parts of a note card that can be turned off, and their labels.
	 *
	 * @return array
	 */
	protected static function ffds_card_parts() {
		return array(
			'title'   => __( 'Title', 'founding-faces' ),
			'stage'   => __( 'Stage badge', 'founding-faces' ),
			'version' => __( 'Version number', 'founding-faces' ),
			'date'    => __( 'Date', 'founding-faces' ),
			'ph'      => __( 'Final pH', 'founding-faces' ),
			'natural' => __( 'Natural origin', 'founding-faces' ),
			'vault'   => __( 'The 35 vault chip', 'founding-faces' ),
			'body'    => __( 'Body copy', 'founding-faces' ),
			'gallery' => __( 'Images', 'founding-faces' ),
		);
	}

	/**
	 * Turn those controls into the attributes the shortcodes take.
	 *
	 * @param array $s The widget settings.
	 * @return array
	 */
	protected function ffds_card_atts( $s ) {
		$hidden = array();
		foreach ( array_keys( self::ffds_card_parts() ) as $key ) {
			if ( isset( $s[ 'card_' . $key ] ) && 'yes' !== $s[ 'card_' . $key ] ) {
				$hidden[] = $key;
			}
		}

		$unit = isset( $s['body_unit'] ) ? $s['body_unit'] : '';

		return array(
			'hide'           => implode( ',', $hidden ),
			'sep'            => isset( $s['meta_sep'] ) ? $s['meta_sep'] : '|',
			'body_trim'      => ( '' !== $unit && isset( $s['body_trim'] ) ) ? absint( $s['body_trim'] ) : 0,
			'body_unit'      => ( 'characters' === $unit ) ? 'characters' : 'words',
			'body_more'      => ( isset( $s['body_more'] ) && 'yes' === $s['body_more'] ) ? 'yes' : 'no',
			'body_more_text' => isset( $s['body_more_text'] ) ? $s['body_more_text'] : '',
		);
	}

	/**
	 * The card options in the shape sample_note_card() takes.
	 *
	 * @param array $s The widget settings.
	 * @return array
	 */
	protected function ffds_card_sample_args( $s ) {
		return FF_Display::card_args_from_atts( $this->ffds_card_atts( $s ) );
	}

	/**
	 * The meta row as a whole: how far apart the chips sit, and what goes
	 * between them.
	 *
	 * Everything about an individual chip lives in its own section below. A
	 * shared typography control was the wrong shape: the stage badge is a pill,
	 * the version and date are quiet text, and the vault chip is a small caps
	 * marker, three different jobs that only looked like one.
	 */
	protected function ffds_badge_section() {
		$this->start_controls_section( 'ff_badges_sec', array(
			'label' => __( 'Badges & chips', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'meta_layout', array(
			'label'       => __( 'Meta row layout', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'column',
			'options'     => array(
				'row'    => __( 'All on one line', 'founding-faces' ),
				'column' => __( 'Badges above, version and date below', 'founding-faces' ),
			),
			'description' => __( 'Two lines keeps the pills together and the plain text together, rather than letting each chip wrap wherever it runs out of room.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-meta' => '--ff-meta-dir: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'badge_gap', array(
			'label'     => __( 'Gap between chips', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-note-meta-group' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'meta_line_gap', array(
			'label'       => __( 'Gap between the two lines', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px', 'em', 'rem' ),
			'range'       => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-meta' => 'gap: {{SIZE}}{{UNIT}};' ),
			'condition'   => array( 'meta_layout' => 'column' ),
		) );
		$this->add_responsive_control( 'meta_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array(
				'{{WRAPPER}} .ff-note-meta'       => 'justify-content: {{VALUE}};',
				'{{WRAPPER}} .ff-note-meta-group' => 'justify-content: {{VALUE}};',
			),
		) );
		$this->add_responsive_control( 'meta_margin', array(
			'label'      => __( 'Row margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-meta' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'sep_heading', array(
			'label'     => __( 'The mark between them', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );
		$this->add_control( 'sep_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-sep' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'sep_typo',
			'selector' => '{{WRAPPER}} .ff-note-sep',
		) );
		$this->add_responsive_control( 'sep_space', array(
			'label'       => __( 'Space either side', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px', 'em' ),
			'range'       => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'description' => __( 'On top of the gap above, so the mark can sit tight to one chip or breathe between both.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-sep' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'sep_offset', array(
			'label'      => __( 'Nudge up or down', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => -10, 'max' => 10 ) ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-sep' => 'transform: translateY({{SIZE}}px);' ),
		) );

		$this->end_controls_section();

		// One section per chip, each with the same full set. The selectors are
		// scoped through .ff-note-meta so a chip's own settings always outrank
		// the stylesheet's starting point for it.
		$this->ffds_chip_section( 'badge', '.ff-badge', __( 'Stage badge', 'founding-faces' ) );
		$this->ffds_chip_section( 'ver', '.ff-note-trial', __( 'Version number', 'founding-faces' ) );
		$this->ffds_chip_section( 'date', '.ff-note-date', __( 'Date', 'founding-faces' ) );
		$this->ffds_chip_section( 'vault', '.ff-note-vault', __( 'The 35 vault chip', 'founding-faces' ) );
		$this->ffds_chip_section( 'ph', '.ff-note-ph', __( 'Final pH', 'founding-faces' ) );
		$this->ffds_chip_section( 'nat', '.ff-note-natural', __( 'Natural origin', 'founding-faces' ) );
		$this->ffds_change_section();
	}

	/**
	 * The figure beside a measurement saying how far it moved.
	 *
	 * Up and down are coloured separately, because which direction is the good
	 * news depends entirely on the figure: natural origin climbing is progress,
	 * pH climbing may be the opposite.
	 */
	protected function ffds_change_section() {
		$this->start_controls_section( 'ff_change_style', array(
			'label' => __( 'Change since last version', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$sel = '{{WRAPPER}} .ff-note-delta';

		$this->add_control( 'change_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'The small figure beside a pH or a natural origin, showing the move from the last version that had one. It appears only when there is something to report: no earlier figure, or no change, and nothing is drawn.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'change_typo',
			'selector' => $sel,
		) );

		$this->add_control( 'change_up_color', array(
			'label'     => __( 'When it has gone up', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-delta.is-up' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'change_down_color', array(
			'label'     => __( 'When it has gone down', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-delta.is-down' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'change_up_bg', array(
			'label'     => __( 'Background when up', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-delta.is-up' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'change_down_bg', array(
			'label'     => __( 'Background when down', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-delta.is-down' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'change_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'change_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'change_gap', array(
			'label'      => __( 'Space before it', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'selectors'  => array( $sel => 'margin-left: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'change_nudge', array(
			'label'      => __( 'Nudge up or down', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => -10, 'max' => 10 ) ),
			'selectors'  => array( $sel => 'display: inline-block; transform: translateY({{SIZE}}px);' ),
		) );

		$this->add_control( 'measure_label_heading', array(
			'label'     => __( 'The word before the figure', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'measure_label_typo',
			'selector' => '{{WRAPPER}} .ff-measure-label',
		) );

		$this->add_control( 'measure_label_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-measure-label' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The full set of controls for one chip in the meta row.
	 *
	 * @param string $prefix   Control id prefix.
	 * @param string $selector The chip's class, relative to the meta row.
	 * @param string $label    The section heading.
	 */
	protected function ffds_chip_section( $prefix, $selector, $label ) {
		// Both meta rows: the same widget base styles a note's chips and the
		// stage badge on a product header, and the badge is the same badge.
		$sel = '{{WRAPPER}} .ff-note-meta ' . $selector . ', {{WRAPPER}} .ff-product-meta ' . $selector;

		$this->start_controls_section( 'ff_chip_' . $prefix, array(
			'label' => $label,
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => $prefix . '_typo',
			'selector' => $sel,
		) );
		$this->add_control( $prefix . '_color', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $sel => 'color: {{VALUE}};' ),
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
		$this->add_responsive_control( $prefix . '_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => $prefix . '_shadow',
			'selector' => $sel,
		) );
		$this->add_responsive_control( $prefix . '_offset', array(
			'label'      => __( 'Nudge up or down', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => -12, 'max' => 12 ) ),
			'selectors'  => array( $sel => 'transform: translateY({{SIZE}}px);' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The note image gallery.
	 */
	protected function ffds_gallery_section() {
		$this->start_controls_section( 'ff_gallery_sec', array(
			'label' => __( 'Note gallery', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'gal_min', array(
			'label'       => __( 'Minimum image width', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'range'       => array( 'px' => array( 'min' => 60, 'max' => 400 ) ),
			'description' => __( 'The gallery fits as many images per row as this allows.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-gallery' => 'grid-template-columns: repeat(auto-fill, minmax({{SIZE}}{{UNIT}}, 1fr));' ),
		) );
		$this->add_responsive_control( 'gal_gap', array(
			'label'     => __( 'Gap', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-note-gallery' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gal_height', array(
			'label'     => __( 'Image height', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 60, 'max' => 500 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-gallery-img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;' ),
		) );
		$this->add_responsive_control( 'gal_radius', array(
			'label'      => __( 'Image corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-gallery-img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gal_margin', array(
			'label'      => __( 'Gallery margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-gallery' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * The stage filter chips (used by the notes list).
	 */
	protected function ffds_chips_section() {
		$this->start_controls_section( 'ff_chips_sec', array(
			'label' => __( 'Filter chips', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'chip_typo',
			'selector' => '{{WRAPPER}} .ff-chip',
		) );
		$this->start_controls_tabs( 'chip_tabs' );

		$this->start_controls_tab( 'chip_tab_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'chip_color', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-chip' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'chip_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-chip' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'chip_tab_a', array( 'label' => __( 'Active', 'founding-faces' ) ) );
		$this->add_control( 'chip_acolor', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-chip.is-active' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'chip_abg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-chip.is-active' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control( 'chip_ul', array(
			'label'        => __( 'Underline chips', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'underline',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-chip' => 'text-decoration: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'chip_border',
			'selector' => '{{WRAPPER}} .ff-chip',
		) );
		$this->add_responsive_control( 'chip_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-chip' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'chip_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-chip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'chip_gap', array(
			'label'     => __( 'Gap between chips', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors' => array( '{{WRAPPER}} .ff-stage-filters' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'chips_margin', array(
			'label'      => __( 'Filter bar margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-stage-filters' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * A JetEngine layout picker for a section.
	 *
	 * @param string $key    Control name for the listing id.
	 * @param string $label  Control label.
	 * @param string $colkey Control name for the columns (or '' to skip).
	 */
	protected function ffds_layout_controls( $key, $label, $colkey = '' ) {
		$this->add_control( $key . '_layout', array(
			'label'   => $label,
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'default',
			'options' => array(
				'default' => __( 'Default layout', 'founding-faces' ),
				'jet'     => __( 'JetEngine listing template', 'founding-faces' ),
			),
		) );
		$this->add_control( $key, array(
			'label'       => __( 'Listing template', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 0,
			'options'     => FF_JetEngine::listing_choices(),
			'condition'   => array( $key . '_layout' => 'jet' ),
			'description' => __( 'Falls back to the default layout if the listing is missing.', 'founding-faces' ),
		) );
		if ( '' !== $colkey ) {
			$this->add_control( $colkey, array(
				'label'     => __( 'Listing columns', 'founding-faces' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 1,
				'min'       => 1,
				'max'       => 6,
				'condition' => array( $key . '_layout' => 'jet' ),
			) );
		}
	}

	/**
	 * Resolve a layout picker to a listing id (0 = use the default layout).
	 *
	 * @param array  $s   The settings array.
	 * @param string $key The listing control name.
	 * @return int
	 */
	protected function ffds_listing_id( $s, $key ) {
		$mode = isset( $s[ $key . '_layout' ] ) ? $s[ $key . '_layout' ] : 'default';
		if ( 'jet' !== $mode ) {
			return 0;
		}
		return isset( $s[ $key ] ) ? absint( $s[ $key ] ) : 0;
	}
}

/**
 * Notes by product, the workhorse. All notes for a product, newest first,
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
			'label'       => __( 'Product', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'auto',
			'options'     => array(
				'auto' => __( 'The product on this page (automatic)', 'founding-faces' ),
				0      => __( 'Every product', 'founding-faces' ),
			) + FF_Display::product_choices( false ),
			'description' => __( 'Automatic follows whichever product is being viewed, so one Single Product template serves them all. On a note\'s page it means that note\'s product.', 'founding-faces' ),
		) );
		$this->add_control( 'stage', array(
			'label'   => __( 'Only show stage', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '',
			'options' => FF_Display::stage_choices(),
		) );
		$this->ffds_layout_controls( 'listing', __( 'Card layout', 'founding-faces' ) );
		$this->add_control( 'filters', array(
			'label'        => __( 'Show stage filter chips', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'limit', array(
			'label'       => __( 'Maximum notes', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 50,
			'min'         => 1,
			'max'         => 200,
			'description' => __( 'e.g. 5 for a "latest notes" block on the hub page.', 'founding-faces' ),
		) );

		$this->add_control( 'show_view_all', array(
			'label'        => __( 'Show a "View all" link', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'separator'    => 'before',
		) );
		$this->add_control( 'view_all_text', array(
			'label'     => __( 'Link text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'View all notes', 'founding-faces' ),
			'condition' => array( 'show_view_all' => 'yes' ),
		) );
		$this->add_control( 'view_all_url', array(
			'label'         => __( 'Notes page', 'founding-faces' ),
			'type'          => \Elementor\Controls_Manager::URL,
			'placeholder'   => __( 'https://…/notes', 'founding-faces' ),
			'show_external' => false,
			'condition'     => array( 'show_view_all' => 'yes' ),
		) );

		$this->ffds_card_content_controls();

		$this->add_responsive_control( 'columns', array(
			'label'          => __( 'Columns', 'founding-faces' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '1',
			'tablet_default' => '1',
			'mobile_default' => '1',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'separator'      => 'before',
			'selectors'      => array(
				'{{WRAPPER}} .ff-notes-cards' => 'display:grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
			),
		) );
		$this->add_responsive_control( 'equal_height', array(
			'label'     => __( 'Card heights', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'stretch',
			'options'   => array(
				'stretch' => __( 'Equal across the row', 'founding-faces' ),
				'start'   => __( 'As tall as their content', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-notes-cards' => 'align-items: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'col_gap', array(
			'label'     => __( 'Gap between cards', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default'   => array( 'size' => 20, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .ff-notes-cards' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->ffds_preview_control();

		$this->end_controls_section();

		$this->register_note_card_style();
		$this->ffds_chips_section();
		$this->ffds_link_section( 'viewall', '.ff-notes-viewall-link', __( '"View all" link', 'founding-faces' ) );
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();

		$view_all_url = '';
		if ( isset( $s['show_view_all'] ) && 'yes' === $s['show_view_all'] && ! empty( $s['view_all_url']['url'] ) ) {
			$view_all_url = $s['view_all_url']['url'];
		}

		$html = FF_Display::sc_notes( array(
			'product'       => isset( $s['product'] ) ? $s['product'] : 'auto',
			'listing'       => $this->ffds_listing_id( $s, 'listing' ),
			'stage'         => isset( $s['stage'] ) ? $s['stage'] : '',
			'filters'       => ( isset( $s['filters'] ) && 'yes' === $s['filters'] ) ? 'yes' : 'no',
			'limit'         => isset( $s['limit'] ) ? absint( $s['limit'] ) : 50,
			'view_all_text' => isset( $s['view_all_text'] ) ? $s['view_all_text'] : '',
			'view_all_url'  => $view_all_url,
		) + $this->ffds_card_atts( $s ) );

		// In the editor, stand in sample cards when the real render had nothing
		// to show, so every element can be styled up front.
		if ( $this->ffds_force_sample( $s ) || ( $this->ffds_is_editor() && $this->ffds_needs_sample( $html ) ) ) {
			echo '<div class="ff-notes-list">';
			if ( ! isset( $s['filters'] ) || 'yes' === $s['filters'] ) {
				echo FF_Display::sample_filter_chips(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo FF_Display::sample_note_cards( min( 3, max( 1, absint( $s['limit'] ) ) ), $this->ffds_card_sample_args( $s ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( isset( $s['show_view_all'] ) && 'yes' === $s['show_view_all'] ) {
				echo '<p class="ff-notes-viewall"><a class="ff-notes-viewall-link" href="#">' . esc_html( isset( $s['view_all_text'] ) && '' !== $s['view_all_text'] ? $s['view_all_text'] : __( 'View all notes', 'founding-faces' ) ) . '</a></p>';
			}
			echo '</div>';
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * The filterable notes archive (for the dedicated notes page).
 */
class FF_Notes_Archive_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_notes_archive';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Notes Archive', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-filter';
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->start_controls_section( 'ff_archive_content', array(
			'label' => __( 'Notes archive', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'intro', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Every note the member may see, with filters for product, type and date. Put this on your dedicated notes page.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->add_control( 'show_product', array(
			'label'        => __( 'Product filter', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'show_stage', array(
			'label'        => __( 'Type (stage) filter', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'show_sort', array(
			'label'        => __( 'Date sort', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'limit', array(
			'label'   => __( 'Maximum notes', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 30,
			'min'     => 1,
			'max'     => 200,
		) );
		$this->ffds_card_content_controls();

		$this->add_responsive_control( 'columns', array(
			'label'          => __( 'Columns', 'founding-faces' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '1',
			'tablet_default' => '1',
			'mobile_default' => '1',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'selectors'      => array(
				'{{WRAPPER}} .ff-notes-cards' => 'display:grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
			),
		) );
		$this->add_responsive_control( 'equal_height', array(
			'label'     => __( 'Card heights', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'stretch',
			'options'   => array(
				'stretch' => __( 'Equal across the row', 'founding-faces' ),
				'start'   => __( 'As tall as their content', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-notes-cards' => 'align-items: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'col_gap', array(
			'label'     => __( 'Gap between cards', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default'   => array( 'size' => 20, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .ff-notes-cards' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->ffds_preview_control();

		$this->end_controls_section();

		$this->register_note_card_style();
		$this->ffds_filterbar_section();
	}

	/**
	 * The archive's select-based filter bar.
	 */
	private function ffds_filterbar_section() {
		$this->start_controls_section( 'ff_fb_sec', array(
			'label' => __( 'Filter bar', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'fb_label_color', array(
			'label'     => __( 'Label colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-filter span' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'fb_label_typo',
			'label'    => __( 'Label text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-filter span',
		) );
		$this->add_control( 'fb_select_bg', array(
			'label'     => __( 'Select background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-filter select' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'fb_select_color', array(
			'label'     => __( 'Select text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-filter select' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'fb_select_border',
			'selector' => '{{WRAPPER}} .ff-filter select',
		) );
		$this->add_responsive_control( 'fb_select_radius', array(
			'label'      => __( 'Select corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-filter select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'fb_btn_bg', array(
			'label'     => __( 'Apply button background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-filter-apply' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'fb_btn_color', array(
			'label'     => __( 'Apply button text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-filter-apply' => 'color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'fb_btn_padding', array(
			'label'      => __( 'Apply button padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-filter-apply' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'fb_btn_radius', array(
			'label'      => __( 'Apply button radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-filter-apply' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'fb_gap', array(
			'label'     => __( 'Gap between filters', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-notes-filters' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'fb_margin', array(
			'label'      => __( 'Filter bar margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notes-filters' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'fb_padding', array(
			'label'      => __( 'Filter bar padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-notes-filters' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s     = $this->get_settings_for_display();
		$flags = array(
			'show_product' => ( ! isset( $s['show_product'] ) || 'yes' === $s['show_product'] ) ? 'yes' : 'no',
			'show_stage'   => ( ! isset( $s['show_stage'] ) || 'yes' === $s['show_stage'] ) ? 'yes' : 'no',
			'show_sort'    => ( ! isset( $s['show_sort'] ) || 'yes' === $s['show_sort'] ) ? 'yes' : 'no',
		);

		$html = FF_Display::sc_notes_archive( array_merge( $flags, $this->ffds_card_atts( $s ), array(
			'limit' => isset( $s['limit'] ) ? absint( $s['limit'] ) : 30,
		) ) );

		if ( $this->ffds_force_sample( $s ) || ( $this->ffds_is_editor() && $this->ffds_needs_sample( $html ) ) ) {
			echo '<div class="ff-notes-archive ff-notes-list">';
			echo FF_Display::sample_filter_bar( $flags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo FF_Display::sample_note_cards( 3, $this->ffds_card_sample_args( $s ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		$this->ffds_layout_controls( 'listing', __( 'Layout', 'founding-faces' ) );
		$this->ffds_card_content_controls();
		$this->ffds_preview_control();

		$this->end_controls_section();

		$this->register_note_card_style();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();

		$html = FF_Display::sc_note( array(
			'id'      => isset( $s['note_id'] ) ? absint( $s['note_id'] ) : 0,
			'listing' => $this->ffds_listing_id( $s, 'listing' ),
		) + $this->ffds_card_atts( $s ) );

		if ( $this->ffds_force_sample( $s ) || ( $this->ffds_is_editor() && $this->ffds_needs_sample( $html ) ) ) {
			echo '<div class="ff-notes-single">' . FF_Display::sample_note_card( '', 'stability_testing', '4', false, $this->ffds_card_sample_args( $s ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			'label'       => __( 'Product', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'auto',
			'options'     => array( 'auto' => __( 'The product on this page (automatic)', 'founding-faces' ) ) + FF_Display::product_choices(),
			'description' => __( 'Automatic follows whichever product is being viewed, what a Single Product template needs.', 'founding-faces' ),
		) );
		$this->ffds_preview_control();

		$this->end_controls_section();

		$this->ffds_card_section( 'phwrap', '.ff-product-header', __( 'Header block', 'founding-faces' ) );
		$this->ffds_heading_section( 'pname', '.ff-product-name', __( 'Product name', 'founding-faces' ) );
		$this->ffds_text_section( 'pstatus', '.ff-product-status', __( 'Status line', 'founding-faces' ) );
		$this->ffds_badge_section();
		$this->ffds_text_section( 'pintro', '.ff-product-intro', __( 'Introduction', 'founding-faces' ), true );
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();

		$html = FF_Display::sc_product_header( array(
			'product' => isset( $s['product'] ) ? $s['product'] : 0,
		) );

		if ( $this->ffds_force_sample( $s ) || ( $this->ffds_is_editor() && $this->ffds_needs_sample( $html ) ) ) {
			echo FF_Display::sample_product_header(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

		/* ---------------------------- Latest notes --------------------------- */
		$this->start_controls_section( 'ff_home_latest', array(
			'label' => __( 'Latest notes', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'show_latest', array(
			'label'        => __( 'Show this section', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'latest_heading', array(
			'label'       => __( 'Heading', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Latest notes', 'founding-faces' ),
			'condition'   => array( 'show_latest' => 'yes' ),
		) );
		$this->add_control( 'latest', array(
			'label'     => __( 'Notes in the feed', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 8,
			'min'       => 1,
			'max'       => 50,
			'condition' => array( 'show_latest' => 'yes' ),
		) );
		$this->ffds_card_content_controls();
		$this->ffds_layout_controls( 'latest_listing', __( 'Layout', 'founding-faces' ), 'latest_columns' );
		$this->add_responsive_control( 'latest_grid', array(
			'label'     => __( 'Columns (default layout)', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => '1',
			'options'   => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'condition' => array( 'latest_listing_layout' => 'default' ),
			'selectors' => array(
				'{{WRAPPER}} .ff-home-latest .ff-notes-cards' => 'display:grid; grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
			),
		) );
		$this->add_responsive_control( 'equal_height', array(
			'label'     => __( 'Card heights', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'stretch',
			'options'   => array(
				'stretch' => __( 'Equal across the row', 'founding-faces' ),
				'start'   => __( 'As tall as their content', 'founding-faces' ),
			),
			'condition' => array( 'latest_listing_layout' => 'default' ),
			'selectors' => array( '{{WRAPPER}} .ff-home-latest .ff-notes-cards' => 'align-items: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ------------------------------ Products ----------------------------- */
		$this->start_controls_section( 'ff_home_products', array(
			'label' => __( 'Products', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );
		$this->add_control( 'show_products', array(
			'label'        => __( 'Show this section', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );
		$this->add_control( 'products_heading', array(
			'label'     => __( 'Heading', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Products', 'founding-faces' ),
			'condition' => array( 'show_products' => 'yes' ),
		) );
		$this->ffds_layout_controls( 'products_listing', __( 'Layout', 'founding-faces' ), 'products_columns' );
		$this->ffds_preview_control();

		$this->end_controls_section();

		/* ------------------------------- Style ------------------------------- */
		$this->ffds_heading_section( 'hlatest', '.ff-home-heading--latest', __( 'Latest heading', 'founding-faces' ) );
		$this->ffds_heading_section( 'hprod', '.ff-home-heading--products', __( 'Products heading', 'founding-faces' ) );
		$this->register_note_card_style();
		$this->ffds_products_section();

		$this->start_controls_section( 'ff_home_space', array(
			'label' => __( 'Section spacing', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'latest_margin', array(
			'label'      => __( 'Latest section margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-home-latest' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'products_margin', array(
			'label'      => __( 'Products section margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-home-products' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/**
	 * The default-layout products list.
	 */
	private function ffds_products_section() {
		$this->start_controls_section( 'ff_prodlist_sec', array(
			'label' => __( 'Products list', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'pl_color', array(
			'label'     => __( 'Product name colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-product-item-name' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'pl_typo',
			'label'    => __( 'Product name text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-product-item-name',
		) );
		$this->add_control( 'pl_divider', array(
			'label'        => __( 'Divider lines', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-product-item' => 'border-bottom-style: solid;' ),
		) );
		$this->add_control( 'pl_divider_color', array(
			'label'     => __( 'Divider colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'condition' => array( 'pl_divider' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} .ff-product-item' => 'border-bottom-color: {{VALUE}};' ),
		) );
		$this->add_control( 'pl_divider_width', array(
			'label'     => __( 'Divider thickness', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 1, 'max' => 10 ) ),
			'default'   => array( 'size' => 1, 'unit' => 'px' ),
			'condition' => array( 'pl_divider' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} .ff-product-item' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'pl_padding', array(
			'label'      => __( 'Row padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'separator'  => 'before',
			'selectors'  => array( '{{WRAPPER}} .ff-product-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'pl_margin', array(
			'label'      => __( 'List margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-products' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s = $this->get_settings_for_display();

		$show_latest   = ( ! isset( $s['show_latest'] ) || 'yes' === $s['show_latest'] ) ? 'yes' : 'no';
		$show_products = ( ! isset( $s['show_products'] ) || 'yes' === $s['show_products'] ) ? 'yes' : 'no';

		$html = FF_Display::sc_home( array(
			'latest'           => isset( $s['latest'] ) ? absint( $s['latest'] ) : 8,
			'latest_heading'   => isset( $s['latest_heading'] ) ? $s['latest_heading'] : '',
			'products_heading' => isset( $s['products_heading'] ) ? $s['products_heading'] : '',
			'show_latest'      => $show_latest,
			'show_products'    => $show_products,
			'latest_listing'   => $this->ffds_listing_id( $s, 'latest_listing' ),
			'products_listing' => $this->ffds_listing_id( $s, 'products_listing' ),
			'latest_columns'   => isset( $s['latest_columns'] ) ? absint( $s['latest_columns'] ) : 1,
			'products_columns' => isset( $s['products_columns'] ) ? absint( $s['products_columns'] ) : 1,
		) + $this->ffds_card_atts( $s ) );

		if ( $this->ffds_force_sample( $s ) || ( $this->ffds_is_editor() && $this->ffds_needs_sample( $html ) ) ) {
			echo '<div class="ff-home">';
			if ( 'yes' === $show_latest ) {
				echo '<section class="ff-home-latest"><h2 class="ff-home-heading ff-home-heading--latest">'
					. esc_html( isset( $s['latest_heading'] ) && '' !== $s['latest_heading'] ? $s['latest_heading'] : __( 'Latest notes', 'founding-faces' ) )
					. '</h2>';
				echo FF_Display::sample_note_cards( 2, $this->ffds_card_sample_args( $s ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</section>';
			}
			if ( 'yes' === $show_products ) {
				echo '<section class="ff-home-products"><h2 class="ff-home-heading ff-home-heading--products">'
					. esc_html( isset( $s['products_heading'] ) && '' !== $s['products_heading'] ? $s['products_heading'] : __( 'Products', 'founding-faces' ) )
					. '</h2>';
				echo FF_Display::sample_products_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</section>';
			}
			echo '</div>';
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
