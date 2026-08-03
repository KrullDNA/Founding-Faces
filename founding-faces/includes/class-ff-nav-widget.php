<?php
/**
 * The Founding Faces Nav Menu widget.
 *
 * Renders a WordPress menu — the same one from Appearance → Menus, with all of
 * its per-item Founding Faces settings intact (group visibility, the login /
 * logout swap, the unread count bubble) — but as a first-class Elementor
 * widget, so the links *and* the count circle can be styled in the editor
 * instead of through a global settings page.
 *
 * The menu items are produced by wp_nav_menu(), so FF_Menu_Visibility and
 * FF_Menu_Items keep working exactly as they do in a theme menu; this widget
 * only owns the markup wrapper and the styling.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Nav_Widget
 */
class FF_Nav_Widget extends \Elementor\Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_nav';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Nav Menu', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-nav-menu';
	}

	/** @return array */
	public function get_categories() {
		return array( 'founding-faces' );
	}

	/** @return array */
	public function get_style_depends() {
		return array( 'founding-faces' );
	}

	/** @return array */
	public function get_keywords() {
		return array( 'nav', 'menu', 'header', 'badge', 'count', 'founding faces' );
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
	 * The site's menus as id => name, for the dropdown.
	 *
	 * @return array
	 */
	private function menu_choices() {
		$menus   = wp_get_nav_menus();
		$choices = array();

		foreach ( $menus as $menu ) {
			$choices[ $menu->term_id ] = $menu->name;
		}

		if ( empty( $choices ) ) {
			$choices[0] = __( '— No menus yet —', 'founding-faces' );
		}

		return $choices;
	}

	/** Register the controls. */
	protected function register_controls() {

		/* ------------------------------ Content ------------------------------ */
		$this->start_controls_section( 'ff_nav_content', array(
			'label' => __( 'Menu', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$choices = $this->menu_choices();
		$this->add_control( 'menu', array(
			'label'       => __( 'Menu', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => $choices,
			'default'     => (string) key( $choices ),
			'description' => sprintf(
				/* translators: %s is the Menus screen URL. */
				__( 'Edit the items — including the login/logout swap and the count bubble — in <a href="%s" target="_blank">Appearance → Menus</a>.', 'founding-faces' ),
				esc_url( admin_url( 'nav-menus.php' ) )
			),
		) );

		$this->add_control( 'submenus', array(
			'label'        => __( 'Show sub-menus on hover', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->end_controls_section();

		/* ------------------------------- Layout ------------------------------ */
		$this->start_controls_section( 'ff_nav_layout', array(
			'label' => __( 'Layout', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_responsive_control( 'direction', array(
			'label'     => __( 'Direction', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'default'   => 'row',
			'options'   => array(
				'row'    => array( 'title' => __( 'Horizontal', 'founding-faces' ), 'icon' => 'eicon-ellipsis-h' ),
				'column' => array( 'title' => __( 'Vertical', 'founding-faces' ), 'icon' => 'eicon-ellipsis-v' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu' => 'flex-direction: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'justify', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'default'   => 'center',
			'options'   => array(
				'flex-start' => array( 'title' => __( 'Start', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => __( 'End', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu' => 'justify-content: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'gap', array(
			'label'     => __( 'Gap between items', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'default'   => array( 'size' => 32, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ------------------------------- Links ------------------------------- */
		$this->start_controls_section( 'ff_nav_links', array(
			'label' => __( 'Menu links', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'link_typo',
			'selector' => '{{WRAPPER}} .ff-nav-menu a',
		) );

		$this->start_controls_tabs( 'link_tabs' );

		$this->start_controls_tab( 'link_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'link_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu a' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'link_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu a' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'link_h', array( 'label' => __( 'Hover', 'founding-faces' ) ) );
		$this->add_control( 'link_hover', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu a:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'link_hover_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu a:hover' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'link_a', array( 'label' => __( 'Active', 'founding-faces' ) ) );
		$this->add_control( 'link_active', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .ff-nav-menu .current-menu-item > a, {{WRAPPER}} .ff-nav-menu .current_page_item > a' => 'color: {{VALUE}};',
			),
		) );
		$this->add_control( 'link_active_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .ff-nav-menu .current-menu-item > a, {{WRAPPER}} .ff-nav-menu .current_page_item > a' => 'background-color: {{VALUE}};',
			),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_control( 'link_ul', array(
			'label'        => __( 'Underline', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'underline',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-nav-menu a' => 'text-decoration: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'link_padding', array(
			'label'      => __( 'Padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-nav-menu a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'link_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-nav-menu a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ------------------------------ The circle --------------------------- */
		$this->start_controls_section( 'ff_nav_badge', array(
			'label' => __( 'Count circle', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'badge_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => __( 'Set which items show a bubble, and what it counts, in Appearance → Menus. A sample count shows here in the editor so it can always be styled.', 'founding-faces' ),
			'content_classes' => 'elementor-descriptor',
		) );
		$this->add_control( 'badge_bg', array(
			'label'     => __( 'Background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-menu-badge' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_control( 'badge_color', array(
			'label'     => __( 'Number colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-menu-badge' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'badge_typo',
			'label'    => __( 'Number text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-menu-badge',
		) );
		$this->add_responsive_control( 'badge_size', array(
			'label'       => __( 'Circle size', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'range'       => array( 'px' => array( 'min' => 10, 'max' => 60 ) ),
			'description' => __( 'Leave empty to let the padding decide the size. The circle grows past this if the number is long.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-menu-badge' => 'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'badge_padding', array(
			'label'      => __( 'Inner padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-menu-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'badge_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-menu-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'badge_border',
			'selector' => '{{WRAPPER}} .ff-menu-badge',
		) );
		$this->add_responsive_control( 'badge_gap', array(
			'label'     => __( 'Gap from the label', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-menu-badge' => 'margin-left: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'badge_valign', array(
			'label'   => __( 'Vertical alignment', 'founding-faces' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'middle',
			'options' => array(
				'middle'      => __( 'Middle', 'founding-faces' ),
				'top'         => __( 'Top', 'founding-faces' ),
				'super'       => __( 'Superscript', 'founding-faces' ),
				'text-top'    => __( 'Top of the text', 'founding-faces' ),
				'baseline'    => __( 'Baseline', 'founding-faces' ),
				'text-bottom' => __( 'Bottom of the text', 'founding-faces' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-menu-badge' => 'vertical-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'badge_shift', array(
			'label'       => __( 'Raise above the text', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'range'       => array( 'px' => array( 'min' => -30, 'max' => 30 ) ),
			'description' => __( 'Lifts the bubble without moving the menu line it sits on.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-menu-badge' => 'top: calc(0px - {{SIZE}}{{UNIT}});' ),
		) );
		$this->end_controls_section();

		/* ------------------------------ Sub-menus ---------------------------- */
		$this->start_controls_section( 'ff_nav_sub', array(
			'label'     => __( 'Sub-menus', 'founding-faces' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => array( 'submenus' => 'yes' ),
		) );
		$this->add_control( 'sub_bg', array(
			'label'     => __( 'Panel background', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu .sub-menu' => 'background-color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'sub_border',
			'selector' => '{{WRAPPER}} .ff-nav-menu .sub-menu',
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'sub_shadow',
			'selector' => '{{WRAPPER}} .ff-nav-menu .sub-menu',
		) );
		$this->add_responsive_control( 'sub_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-nav-menu .sub-menu' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'sub_padding', array(
			'label'      => __( 'Panel padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .ff-nav-menu .sub-menu' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'sub_color', array(
			'label'     => __( 'Link colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu .sub-menu a' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'sub_hover', array(
			'label'     => __( 'Link hover colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu .sub-menu a:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'sub_typo',
			'label'    => __( 'Link text', 'founding-faces' ),
			'selector' => '{{WRAPPER}} .ff-nav-menu .sub-menu a',
		) );
		$this->add_responsive_control( 'sub_width', array(
			'label'     => __( 'Panel width', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 120, 'max' => 500 ) ),
			'default'   => array( 'size' => 220, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .ff-nav-menu .sub-menu' => 'min-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s       = $this->get_settings_for_display();
		$menu_id = isset( $s['menu'] ) ? absint( $s['menu'] ) : 0;

		if ( ! $menu_id ) {
			if ( FF_Menu_Items::is_design_preview() ) {
				echo '<p class="ff-empty-note">' . esc_html__( 'Choose a menu to display.', 'founding-faces' ) . '</p>';
			}
			return;
		}

		$classes = 'ff-nav-menu';
		if ( ! isset( $s['submenus'] ) || 'yes' === $s['submenus'] ) {
			$classes .= ' ff-nav-menu--dropdowns';
		}

		// wp_nav_menu produces the items, so every per-item Founding Faces
		// setting (group visibility, the login/logout swap, the count bubble)
		// keeps working exactly as it does in a theme menu.
		wp_nav_menu( array(
			'menu'        => $menu_id,
			'menu_class'  => $classes,
			'container'   => 'nav',
			'container_class' => 'ff-nav',
			'depth'       => ( ! isset( $s['submenus'] ) || 'yes' === $s['submenus'] ) ? 0 : 1,
			'fallback_cb' => '__return_empty_string',
		) );
	}
}
