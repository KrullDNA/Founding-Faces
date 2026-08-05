<?php
/**
 * The Founding Faces Member Bar widget.
 *
 * A header strip of member links, Messages, Notes, Polls and Log in / Log out ,
 * each with its own unread count circle. The same counts as the nav-menu bubble,
 * but as a first-class Elementor widget, so the circles can be styled directly
 * (size, colour, position, typography) instead of by hand-writing CSS against a
 * theme's menu markup.
 *
 * Drop it in an Elementor header next to (or instead of) the nav menu. In the
 * editor it always shows sample counts, so every state is visible to style.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Member_Bar_Widget
 */
class FF_Member_Bar_Widget extends \Elementor\Widget_Base {

	/** @return string */
	public function get_name() {
		return 'ff_member_bar';
	}

	/** @return string */
	public function get_title() {
		return __( 'Founding Faces Member Bar', 'founding-faces' );
	}

	/** @return string */
	public function get_icon() {
		return 'eicon-bell';
	}

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
	 * The items this bar can show, in render order.
	 *
	 * @return array key => array( default label, badge source or '' )
	 */
	private function items() {
		return array(
			'messages' => array( __( 'Messages', 'founding-faces' ), 'messages' ),
			'notes'    => array( __( 'Notes', 'founding-faces' ), 'notes' ),
			'polls'    => array( __( 'Polls', 'founding-faces' ), 'polls' ),
		);
	}

	/** Register the controls. */
	protected function register_controls() {

		/* ------------------------------ Content ------------------------------ */
		$this->start_controls_section( 'ff_bar_content', array(
			'label' => __( 'Items', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		foreach ( $this->items() as $key => $spec ) {
			$this->add_control( 'show_' . $key, array(
				'label'        => sprintf( /* translators: %s is an item name. */ __( 'Show %s', 'founding-faces' ), $spec[0] ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'messages' === $key ? 'yes' : '',
				'return_value' => 'yes',
				'separator'    => 'before',
			) );
			$this->add_control( 'label_' . $key, array(
				'label'     => __( 'Label', 'founding-faces' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => $spec[0],
				'condition' => array( 'show_' . $key => 'yes' ),
			) );
			$this->add_control( 'url_' . $key, array(
				'label'         => __( 'Links to', 'founding-faces' ),
				'type'          => \Elementor\Controls_Manager::URL,
				'show_external' => false,
				'condition'     => array( 'show_' . $key => 'yes' ),
			) );
			$this->add_control( 'hide_' . $key, array(
				'label'        => __( 'Hide when nothing is unread', 'founding-faces' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'condition'    => array( 'show_' . $key => 'yes' ),
			) );
		}

		$this->add_control( 'show_auth', array(
			'label'        => __( 'Show Log in / Log out', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => '',
			'return_value' => 'yes',
			'separator'    => 'before',
		) );

		$this->add_control( 'members_only', array(
			'label'        => __( 'Hide the whole bar from logged-out visitors', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'separator'    => 'before',
			'description'  => __( 'The Log in link is still shown if it is switched on above.', 'founding-faces' ),
		) );

		$this->end_controls_section();

		/* ------------------------------ Layout ------------------------------- */
		$this->start_controls_section( 'ff_bar_layout', array(
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
			'selectors' => array( '{{WRAPPER}} .ff-member-bar' => 'flex-direction: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'justify', array(
			'label'     => __( 'Alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'default'   => 'flex-start',
			'options'   => array(
				'flex-start' => array( 'title' => __( 'Start', 'founding-faces' ), 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => __( 'Centre', 'founding-faces' ), 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => __( 'End', 'founding-faces' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .ff-member-bar' => 'justify-content: {{VALUE}}; align-items: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'gap', array(
			'label'     => __( 'Gap between items', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default'   => array( 'size' => 20, 'unit' => 'px' ),
			'selectors' => array( '{{WRAPPER}} .ff-member-bar' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ------------------------------- Links ------------------------------- */
		$this->start_controls_section( 'ff_bar_links', array(
			'label' => __( 'Link text', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'link_typo',
			'selector' => '{{WRAPPER}} .ff-member-bar-item',
		) );
		$this->start_controls_tabs( 'link_tabs' );
		$this->start_controls_tab( 'link_n', array( 'label' => __( 'Normal', 'founding-faces' ) ) );
		$this->add_control( 'link_color', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-member-bar-item' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'link_h', array( 'label' => __( 'Hover', 'founding-faces' ) ) );
		$this->add_control( 'link_hover', array(
			'label'     => __( 'Colour', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .ff-member-bar-item:hover' => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control( 'link_ul', array(
			'label'        => __( 'Underline', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'underline',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-member-bar-item' => 'text-decoration: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'item_padding', array(
			'label'      => __( 'Item padding', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( '{{WRAPPER}} .ff-member-bar-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'hide_labels', array(
			'label'        => __( 'Hide the labels (bubble only)', 'founding-faces' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'none',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => array( '{{WRAPPER}} .ff-member-bar-label' => 'display: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ------------------------------ The circle --------------------------- */
		$this->start_controls_section( 'ff_bar_badge', array(
			'label' => __( 'Count circle', 'founding-faces' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
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
			'range'       => array( 'px' => array( 'min' => 12, 'max' => 48 ) ),
			'description' => __( 'The circle grows past this if the number is long.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-menu-badge' => 'min-width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'badge_radius', array(
			'label'      => __( 'Corner radius', 'founding-faces' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors'  => array( '{{WRAPPER}} .ff-menu-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'badge_padding', array(
			'label'       => __( 'Inner padding', 'founding-faces' ),
			'type'        => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units'  => array( 'px', 'em' ),
			'description' => __( 'Breathing room around the number, which stays centred whatever you set. For a circle sized purely by its padding, clear the circle size above.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-menu-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'badge_border',
			'selector' => '{{WRAPPER}} .ff-menu-badge',
		) );

		$this->add_control( 'badge_position', array(
			'label'     => __( 'Position', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'inline',
			'separator' => 'before',
			'options'   => array(
				'inline' => __( 'Beside the label', 'founding-faces' ),
				'corner' => __( 'Top-right corner (mini-cart style)', 'founding-faces' ),
			),
		) );
		$this->add_responsive_control( 'badge_gap', array(
			'label'     => __( 'Gap from the label', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
			'condition' => array( 'badge_position' => 'inline' ),
			'selectors' => array( '{{WRAPPER}} .ff-menu-badge' => 'margin-left: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'badge_valign', array(
			'label'     => __( 'Vertical alignment', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'middle',
			'condition' => array( 'badge_position' => 'inline' ),
			'options'   => array(
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
			'condition'   => array( 'badge_position' => 'inline' ),
			'description' => __( 'Lifts the bubble without moving the line it sits on.', 'founding-faces' ),
			'selectors'   => array( '{{WRAPPER}} .ff-menu-badge' => 'top: calc(0px - {{SIZE}}{{UNIT}});' ),
		) );
		$this->add_responsive_control( 'badge_offset_x', array(
			'label'     => __( 'Nudge right', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => -30, 'max' => 30 ) ),
			'condition' => array( 'badge_position' => 'corner' ),
			'selectors' => array( '{{WRAPPER}} .ff-member-bar--corner .ff-menu-badge' => 'right: calc(0px - {{SIZE}}{{UNIT}});' ),
		) );
		$this->add_responsive_control( 'badge_offset_y', array(
			'label'     => __( 'Nudge up', 'founding-faces' ),
			'type'      => \Elementor\Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'min' => -30, 'max' => 30 ) ),
			'condition' => array( 'badge_position' => 'corner' ),
			'selectors' => array( '{{WRAPPER}} .ff-member-bar--corner .ff-menu-badge' => 'top: calc(0px - {{SIZE}}{{UNIT}});' ),
		) );
		$this->end_controls_section();
	}

	/** Render. */
	protected function render() {
		$s        = $this->get_settings_for_display();
		$preview  = FF_Menu_Items::is_design_preview();
		$logged   = is_user_logged_in();
		$corner   = ( isset( $s['badge_position'] ) && 'corner' === $s['badge_position'] );

		// Members-only bar: logged-out visitors see nothing but the Log in link.
		$members_only = ( ! isset( $s['members_only'] ) || 'yes' === $s['members_only'] );

		$out = '';

		if ( $logged || $preview || ! $members_only ) {
			foreach ( $this->items() as $key => $spec ) {
				if ( ! isset( $s[ 'show_' . $key ] ) || 'yes' !== $s[ 'show_' . $key ] ) {
					continue;
				}

				$count = ( $logged || $preview ) ? FF_Menu_Items::badge_count( $spec[1] ) : 0;
				if ( $preview && $count < 1 ) {
					$count = FF_Menu_Items::PREVIEW_COUNT;
				}

				// "Hide when nothing is unread" never hides while designing.
				if ( $count < 1 && isset( $s[ 'hide_' . $key ] ) && 'yes' === $s[ 'hide_' . $key ] && ! $preview ) {
					continue;
				}

				$label = isset( $s[ 'label_' . $key ] ) && '' !== $s[ 'label_' . $key ] ? $s[ 'label_' . $key ] : $spec[0];
				$url   = ! empty( $s[ 'url_' . $key ]['url'] ) ? $s[ 'url_' . $key ]['url'] : FF_Messages::portal_url();

				$out .= '<a class="ff-member-bar-item ff-member-bar-item--' . esc_attr( $key ) . '" href="' . esc_url( $url ? $url : home_url( '/' ) ) . '">';
				$out .= '<span class="ff-member-bar-label">' . FF_Text::inline( $label ) . '</span>';
				$out .= $this->badge_html( $count );
				$out .= '</a>';
			}
		}

		// The log in / log out link, resolved the same way as the menu item.
		if ( isset( $s['show_auth'] ) && 'yes' === $s['show_auth'] ) {
			$out .= '<a class="ff-member-bar-item ff-member-bar-item--auth" href="'
				. esc_url( $logged ? FF_Menu_Items::logout_url() : FF_Menu_Items::login_url() ) . '">';
			$out .= '<span class="ff-member-bar-label">'
				. esc_html( $logged ? FF_Menu_Items::logout_label() : FF_Menu_Items::login_label() )
				. '</span></a>';
		}

		if ( '' === $out ) {
			return;
		}

		printf(
			'<nav class="ff-member-bar%s" aria-label="%s">%s</nav>',
			$corner ? ' ff-member-bar--corner' : '',
			esc_attr__( 'Member links', 'founding-faces' ),
			$out // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * The count circle, or nothing at zero.
	 *
	 * @param int $count The unread count.
	 * @return string
	 */
	private function badge_html( $count ) {
		if ( $count < 1 ) {
			return '';
		}

		return '<span class="ff-menu-badge" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>'
			. '<span class="screen-reader-text">' . esc_html( sprintf(
				/* translators: %s is a number of unread items. */
				_n( '%s unread', '%s unread', $count, 'founding-faces' ),
				number_format_i18n( $count )
			) ) . '</span>';
	}
}
