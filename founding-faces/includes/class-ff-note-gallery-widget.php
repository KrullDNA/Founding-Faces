<?php
/**
 * The Elementor widget for a note's image gallery, shown as a slider.
 *
 * The images are already on the note, this is the widget that puts them on a
 * page, most usefully in a Single Note template, where it follows whichever
 * note is being read without anybody choosing one.
 *
 * One image is not a slider: no arrows, no dots, nothing to interact with. From
 * two images upwards it loops, and the arrows can be given any icon and styled
 * completely, because nothing about how they look is decided here.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FF_PATH . 'includes/class-ff-display-widgets.php';

/**
 * Class FF_Note_Gallery_Widget
 */
class FF_Note_Gallery_Widget extends FF_Display_Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_note_gallery';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Note Gallery', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-slider-push';
	}

	/** @return array */
	public function get_script_depends() {
		return array( 'ff-note-slider' );
	}

	/** @return array */
	public function get_keywords() {
		return array( 'gallery', 'slider', 'carousel', 'images', 'note', 'founding faces' );
	}

	/**
	 * The registered image sizes, for the size dropdown.
	 *
	 * @return array
	 */
	private function size_choices() {
		$sizes   = array();
		$default = array(
			'thumbnail' => __( 'Thumbnail', 'founding-faces' ),
			'medium'    => __( 'Medium', 'founding-faces' ),
			'large'     => __( 'Large', 'founding-faces' ),
			'full'      => __( 'Full', 'founding-faces' ),
		);

		foreach ( get_intermediate_image_sizes() as $size ) {
			$sizes[ $size ] = isset( $default[ $size ] ) ? $default[ $size ] : ucwords( str_replace( array( '_', '-' ), ' ', $size ) );
		}
		$sizes['full'] = $default['full'];

		return $sizes;
	}

	/** Register the controls. */
	protected function register_controls() {
		$this->gallery_content_section();
		$this->slider_content_section();

		$this->gallery_style_section();
		$this->image_style_section();
		$this->caption_style_section();
		$this->arrow_style_section();
		$this->dot_style_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Content.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Which note, which images, and what a click on one does.
	 */
	private function gallery_content_section() {
		$this->start_controls_section( 'ff_gal_content', array(
			'label' => __( 'Gallery', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'note_id', array(
			'label'       => __( 'Note', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 0,
			'options'     => array( 0 => __( 'The note on this page (automatic)', 'founding-faces' ) ) + array_slice( FF_Display::note_choices(), 1, null, true ),
			'description' => __( 'Left automatic, this shows the gallery of whichever note is being read, which is what a Single Note template needs.', 'founding-faces' ),
		) );

		$this->add_control( 'image_size', array(
			'label'   => __( 'Image size', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'large',
			'options' => $this->size_choices(),
		) );

		$this->add_control( 'link', array(
			'label'   => __( 'Clicking an image', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'lightbox',
			'options' => array(
				'none'     => __( 'Does nothing', 'founding-faces' ),
				'lightbox' => __( 'Opens the lightbox', 'founding-faces' ),
				'file'     => __( 'Opens the full image in a new tab', 'founding-faces' ),
			),
		) );

		$this->add_control( 'caption', array(
			'label'       => __( 'Show captions', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SWITCHER,
			'description' => __( 'The caption written against the image in the media library.', 'founding-faces' ),
		) );

		$this->ffds_preview_control();

		$this->end_controls_section();
	}

	/**
	 * How the slider behaves.
	 */
	private function slider_content_section() {
		$this->start_controls_section( 'ff_gal_slider', array(
			'label' => __( 'Slider', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_responsive_control( 'slides', array(
			'label'          => __( 'Images on show', 'founding-faces' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '1',
			'tablet_default' => '',
			'mobile_default' => '',
			'options'        => array(
				''  => __( 'Inherit', 'founding-faces' ),
				'1' => '1',
				'2' => '2',
				'3' => '3',
				'4' => '4',
				'5' => '5',
			),
			'description'    => __( 'How many images sit side by side. The arrows disappear on any screen where they all already fit.', 'founding-faces' ),
			'selectors'      => array( '{{WRAPPER}} .ff-note-slider' => '--ff-slides: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'slide_gap', array(
			'label'      => __( 'Gap between images', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', '%' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 16 ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-slider' => '--ff-gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'dots', array(
			'label'       => __( 'Show dots', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SWITCHER,
			'description' => __( 'A dot per image under the slider. Styled in the Style tab.', 'founding-faces' ),
		) );

		$this->add_control( 'autoplay', array(
			'label'       => __( 'Move on its own', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SWITCHER,
			'separator'   => 'before',
			'description' => __( 'Pauses while the pointer is over it, and never runs for a member who has asked their device to reduce motion.', 'founding-faces' ),
		) );

		$this->add_control( 'autoplay_delay', array(
			'label'      => __( 'Seconds on each image', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 's' ),
			'range'      => array( 's' => array( 'min' => 1, 'max' => 20, 'step' => 0.5 ) ),
			'default'    => array( 'unit' => 's', 'size' => 5 ),
			'condition'  => array( 'autoplay' => 'yes' ),
		) );

		$this->add_control( 'speed', array(
			'label'      => __( 'Slide speed', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'ms' ),
			'range'      => array( 'ms' => array( 'min' => 0, 'max' => 2000, 'step' => 50 ) ),
			'default'    => array( 'unit' => 'ms', 'size' => 400 ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-slider' => '--ff-speed: {{SIZE}}ms;' ),
		) );

		$this->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Style.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The slider as a whole.
	 */
	private function gallery_style_section() {
		$this->start_controls_section( 'ff_gal_box', array(
			'label' => __( 'Slider box', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'box_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-note-slider' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'box_border',
			'selector' => '{{WRAPPER}} .ff-note-slider',
		) );
		$this->add_responsive_control( 'box_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-slider' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'box_shadow',
			'selector' => '{{WRAPPER}} .ff-note-slider',
		) );
		$this->add_responsive_control( 'box_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-slider' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'box_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-note-slider' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The images themselves.
	 */
	private function image_style_section() {
		$this->start_controls_section( 'ff_gal_img', array(
			'label' => __( 'Images', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		// All three go out as custom properties on the slider rather than as
		// declarations on the image, for two reasons. The stylesheet can then
		// hold the sensible default for each one, so cropping still happens
		// when only a height has been set. And neither of the two that follow
		// needs a condition on the height any more: a condition is evaluated
		// again when the front end's CSS is written, and one that answers
		// differently there than it did in the editor produces exactly this,
		// a crop that looks right while designing and is missing on the page.
		$this->add_responsive_control( 'img_height', array(
			'label'       => __( 'Image height', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px', 'vh' ),
			'range'       => array( 'px' => array( 'min' => 80, 'max' => 900 ) ),
			'description' => __( 'Left empty, each image keeps its own proportions and the two settings below do nothing.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-slider' => '--ff-img-h: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'img_fit', array(
			'label'     => __( 'How it fills that height', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'cover',
			'options'   => array(
				'cover'   => __( 'Crop to fill', 'founding-faces' ),
				'contain' => __( 'Fit inside', 'founding-faces' ),
				'fill'    => __( 'Stretch', 'founding-faces' ),
				'none'    => __( 'Original size', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-note-slider' => '--ff-img-fit: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'img_position', array(
			'label'       => __( 'Crop position', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'center center',
			'options'     => array(
				'center center' => __( 'Centre', 'founding-faces' ),
				'center top'    => __( 'Top', 'founding-faces' ),
				'center bottom' => __( 'Bottom', 'founding-faces' ),
				'left center'   => __( 'Left', 'founding-faces' ),
				'right center'  => __( 'Right', 'founding-faces' ),
			),
			'description' => __( 'Which part of the picture survives the crop. Only has anything to do when a height is set above.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-note-slider' => '--ff-img-pos: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'img_border',
			'selector' => '{{WRAPPER}} .ff-slide-img',
		) );
		$this->add_responsive_control( 'img_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			// The rounding goes on the image and on the link around it. A linked
			// image paints its own box over the corners otherwise, so the radius
			// is set and then covered up, which reads as a control doing
			// nothing.
			'selectors'  => array(
				'{{WRAPPER}} .ff-slide-img'  => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				'{{WRAPPER}} .ff-slide-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
			),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'img_shadow',
			'selector' => '{{WRAPPER}} .ff-slide-img',
		) );

		$this->end_controls_section();
	}

	/**
	 * The caption under each image.
	 */
	private function caption_style_section() {
		$this->start_controls_section( 'ff_gal_cap', array(
			'label'     => __( 'Captions', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'caption' => 'yes' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'cap_typo',
			'selector' => '{{WRAPPER}} .ff-slide-caption',
		) );
		$this->add_control( 'cap_color', array(
			'label'     => __( 'Text', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slide-caption' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'cap_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slide-caption' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'cap_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-slide-caption' => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'cap_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-slide-caption' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'cap_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-slide-caption' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The arrows: icon, size, colour, box, position, the lot.
	 */
	private function arrow_style_section() {
		$this->start_controls_section( 'ff_gal_arrows', array(
			'label' => __( 'Arrows', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'prev_icon', array(
			'label'       => __( 'Previous icon', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::ICONS,
			'skin'        => 'inline',
			'description' => __( 'Left empty, a plain chevron is used.', 'founding-faces' ),
		) );
		$this->add_control( 'next_icon', array(
			'label' => __( 'Next icon', 'founding-faces' ),
			'type'  => \Elementor\Controls_Manager::ICONS,
			'skin'  => 'inline',
		) );

		$this->add_responsive_control( 'arrow_size', array(
			'label'      => __( 'Icon size', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 120 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 24 ),
			'separator'  => 'before',
			'selectors'  => array( '{{WRAPPER}} .ff-slider-arrow' => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'arrow_box', array(
			'label'       => __( 'Button size', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px', 'em' ),
			'range'       => array( 'px' => array( 'min' => 16, 'max' => 160 ) ),
			'description' => __( 'Left empty, the button is only as big as its icon and its padding.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-slider-arrow' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'arrow_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-slider-arrow' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->start_controls_tabs( 'arrow_tabs' );

		$this->start_controls_tab( 'arrow_tab_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'arrow_color', array(
			'label'     => __( 'Icon colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slider-arrow' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'arrow_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slider-arrow' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'arrow_border',
			'selector' => '{{WRAPPER}} .ff-slider-arrow',
		) );
		$this->add_responsive_control( 'arrow_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-slider-arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'arrow_shadow',
			'selector' => '{{WRAPPER}} .ff-slider-arrow',
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'arrow_tab_h', array( 'label' => __( 'Hover', 'founding-faces' ) ) );
		$this->add_control( 'arrow_hcolor', array(
			'label'     => __( 'Icon colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slider-arrow:hover, {{WRAPPER}} .ff-slider-arrow:focus-visible' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'arrow_hbg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slider-arrow:hover, {{WRAPPER}} .ff-slider-arrow:focus-visible' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'arrow_hborder', array(
			'label'     => __( 'Border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slider-arrow:hover, {{WRAPPER}} .ff-slider-arrow:focus-visible' => 'border-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'arrow_hshadow',
			'selector' => '{{WRAPPER}} .ff-slider-arrow:hover, {{WRAPPER}} .ff-slider-arrow:focus-visible',
		) );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control( 'arrow_offset', array(
			'label'       => __( 'Distance from the edge', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px', '%' ),
			'range'       => array( 'px' => array( 'min' => -160, 'max' => 160 ) ),
			'default'     => array( 'unit' => 'px', 'size' => 8 ),
			'separator'   => 'before',
			'description' => __( 'A negative number moves them out past the images instead of sitting over them.', 'founding-faces' ),
			'selectors'   => array(
				'{{WRAPPER}} .ff-slider-prev' => 'left: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .ff-slider-next' => 'right: {{SIZE}}{{UNIT}};',
			),
		) );
		$this->add_responsive_control( 'arrow_top', array(
			'label'      => __( 'Vertical position', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( '%' ),
			'range'      => array( '%' => array( 'min' => 0, 'max' => 100 ) ),
			'default'    => array( 'unit' => '%', 'size' => 50 ),
			'selectors'  => array( '{{WRAPPER}} .ff-slider-arrow' => 'top: {{SIZE}}%;' ),
		) );
		$this->add_responsive_control( 'arrow_show', array(
			'label'     => __( 'Show the arrows', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'flex',
			'options'   => array(
				'flex' => __( 'Yes', 'founding-faces' ),
				'none' => __( 'No', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-note-slider:not(.ff-slider--static) .ff-slider-arrow' => 'display: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The dots.
	 */
	private function dot_style_section() {
		$this->start_controls_section( 'ff_gal_dots', array(
			'label'     => __( 'Dots', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'dots' => 'yes' ),
		) );

		$this->add_responsive_control( 'dot_size', array(
			'label'      => __( 'Size', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 2, 'max' => 40 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 8 ),
			'selectors'  => array( '{{WRAPPER}} .ff-slider-dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'dot_gap', array(
			'label'      => __( 'Gap', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 8 ),
			'selectors'  => array( '{{WRAPPER}} .ff-slider-dots' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'dot_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'default'    => array( 'top' => 50, 'right' => 50, 'bottom' => 50, 'left' => 50, 'unit' => '%', 'isLinked' => true ),
			'selectors'  => array( '{{WRAPPER}} .ff-slider-dot' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->start_controls_tabs( 'dot_tabs' );

		$this->start_controls_tab( 'dot_tab_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'dot_bg', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#D8D3CB',
			'selectors' => array( '{{WRAPPER}} .ff-slider-dot' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'dot_border',
			'selector' => '{{WRAPPER}} .ff-slider-dot',
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'dot_tab_c', array( 'label' => __( 'Current', 'founding-faces' ) ) );
		$this->add_control( 'dot_cbg', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#4A4034',
			'selectors' => array( '{{WRAPPER}} .ff-slider-dot.is-current' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'dot_cborder', array(
			'label'     => __( 'Border colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-slider-dot.is-current' => 'border-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control( 'dots_margin', array(
			'label'      => __( 'Margin', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'separator'  => 'before',
			'selectors'  => array( '{{WRAPPER}} .ff-slider-dots' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'dots_align', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'default'   => 'center',
			'options'   => array(
				'flex-start' => array( 'title' => __( 'Left', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => __( 'Right', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-slider-dots' => 'justify-content: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/*
	 * -----------------------------------------------------------------------
	 * Render.
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Turn the settings into the arguments the renderer takes.
	 *
	 * @param array $s The widget settings.
	 * @return array
	 */
	private function slider_args( $s ) {
		$autoplay = 0;
		if ( isset( $s['autoplay'] ) && 'yes' === $s['autoplay'] ) {
			$seconds  = isset( $s['autoplay_delay']['size'] ) && '' !== $s['autoplay_delay']['size'] ? (float) $s['autoplay_delay']['size'] : 5;
			$autoplay = (int) round( $seconds * 1000 );
		}

		return array(
			'size'     => isset( $s['image_size'] ) && $s['image_size'] ? $s['image_size'] : 'large',
			'link'     => isset( $s['link'] ) ? $s['link'] : 'lightbox',
			'caption'  => isset( $s['caption'] ) && 'yes' === $s['caption'],
			'dots'     => isset( $s['dots'] ) && 'yes' === $s['dots'],
			'autoplay' => $autoplay,
			'prev'     => $this->icon_html( isset( $s['prev_icon'] ) ? $s['prev_icon'] : array() ),
			'next'     => $this->icon_html( isset( $s['next_icon'] ) ? $s['next_icon'] : array() ),
		);
	}

	/**
	 * Render a chosen icon, or nothing so the default chevron is used.
	 *
	 * @param array $icon The ICONS control value.
	 * @return string
	 */
	private function icon_html( $icon ) {
		if ( empty( $icon['value'] ) ) {
			return '';
		}

		ob_start();
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/** Render. */
	protected function render() {
		$s    = $this->get_settings_for_display();
		$args = $this->slider_args( $s );

		if ( $this->ffds_force_sample( $s ) ) {
			echo FF_Display::sample_gallery_html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$html = FF_Display::note_gallery_html(
			FF_Display::note_context_id( isset( $s['note_id'] ) ? $s['note_id'] : 0 ),
			$args
		);

		// In the editor an empty gallery leaves nothing to design against, so
		// the samples stand in even when real content was asked for.
		if ( '' === $html && $this->ffds_is_editor() ) {
			$html = FF_Display::sample_gallery_html( $args );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
