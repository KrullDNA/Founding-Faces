<?php
/**
 * JetEngine integration.
 *
 * The note fields are stored as ordinary post meta, so a JetEngine Dynamic
 * Field widget can already read any of them with source "Post Meta" and the
 * meta key. The trouble is that some values are stored as keys (the stage is
 * "in_development", the product is an ID), which don't read nicely on their own.
 *
 * This registers a set of JetEngine "callbacks" that appear in the Dynamic
 * Field widget's Callback dropdown and turn those raw values into friendly
 * output — a stage label or badge, the audience label, the product's name, or
 * the image gallery. Everything is self-contained (inline styles) so it looks
 * right inside a JetEngine listing without any of the plugin's other assets.
 *
 * Adds nothing if JetEngine isn't installed.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_JetEngine
 */
class FF_JetEngine {

	/**
	 * Register the callbacks with JetEngine.
	 */
	public static function register() {
		add_filter( 'jet-engine/listings/dynamic-field/custom-callbacks', array( __CLASS__, 'register_callbacks' ) );
	}

	/**
	 * Add the Founding Faces callbacks to JetEngine's Callback dropdown.
	 *
	 * The array key is the callable; the value is the label shown in the panel.
	 *
	 * @param array $callbacks Existing callbacks.
	 * @return array
	 */
	public static function register_callbacks( $callbacks ) {
		$callbacks['FF_JetEngine::stage_label']    = __( 'Founding Faces — Stage label', 'founding-faces' );
		$callbacks['FF_JetEngine::stage_badge']    = __( 'Founding Faces — Stage badge', 'founding-faces' );
		$callbacks['FF_JetEngine::audience_label'] = __( 'Founding Faces — Audience label', 'founding-faces' );
		$callbacks['FF_JetEngine::product_title']  = __( 'Founding Faces — Product name', 'founding-faces' );
		$callbacks['FF_JetEngine::gallery']        = __( 'Founding Faces — Image gallery', 'founding-faces' );
		return $callbacks;
	}

	/**
	 * Turn a stage key into its human label.
	 *
	 * @param string $value The stored stage key (e.g. "in_development").
	 * @return string
	 */
	public static function stage_label( $value ) {
		$stages = FF_Post_Types::note_stages();
		return isset( $stages[ $value ] ) ? $stages[ $value ] : (string) $value;
	}

	/**
	 * Turn a stage key into a styled badge (self-contained inline styles).
	 *
	 * @param string $value The stored stage key.
	 * @return string
	 */
	public static function stage_badge( $value ) {
		$label  = self::stage_label( $value );
		$colors = array(
			'in_development'    => array( '#eceef0', '#3a3d44' ),
			'stability_testing' => array( '#fff3d6', '#8a6d1f' ),
			'passed'            => array( '#e6f4ea', '#1e5631' ),
			'failed'            => array( '#fbeaea', '#8a1f1f' ),
		);
		$pair = isset( $colors[ $value ] ) ? $colors[ $value ] : array( '#eceef0', '#3a3d44' );

		return '<span style="display:inline-block;padding:3px 11px;border-radius:11px;font-size:0.75rem;font-weight:600;background:'
			. esc_attr( $pair[0] ) . ';color:' . esc_attr( $pair[1] ) . ';">'
			. esc_html( $label ) . '</span>';
	}

	/**
	 * Turn an audience key into its label.
	 *
	 * @param string $value The stored audience key.
	 * @return string
	 */
	public static function audience_label( $value ) {
		$audiences = FF_Post_Types::note_audiences();
		return isset( $audiences[ $value ] ) ? $audiences[ $value ] : (string) $value;
	}

	/**
	 * Turn a product id into the product's name.
	 *
	 * @param int|string $value The product post id.
	 * @return string
	 */
	public static function product_title( $value ) {
		$id = absint( $value );
		return $id ? get_the_title( $id ) : '';
	}

	/**
	 * Turn a gallery of attachment ids into a simple image grid.
	 *
	 * @param string $value Comma-separated attachment ids.
	 * @return string
	 */
	public static function gallery( $value ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
		if ( empty( $ids ) ) {
			return '';
		}

		$out = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;">';
		foreach ( $ids as $id ) {
			$img = wp_get_attachment_image( $id, 'medium', false, array( 'style' => 'width:100%;height:120px;object-fit:cover;border-radius:6px;display:block;', 'loading' => 'lazy' ) );
			if ( $img ) {
				$full = wp_get_attachment_image_url( $id, 'full' );
				$out .= '<a href="' . esc_url( $full ) . '" target="_blank" rel="noopener">' . $img . '</a>';
			}
		}
		$out .= '</div>';
		return $out;
	}
}
