<?php
/**
 * The Founding Faces Elementor dynamic-tag classes.
 *
 * Loaded only when Elementor registers dynamic tags, so the Elementor base
 * classes these extend are guaranteed to exist. Each tag reads the current post
 * (the note being looped) and returns a formatted value.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Note, Stage (formatted label, e.g. "In development").
 */
class FF_Tag_Note_Stage extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-note-stage';
	}
	public function get_title() {
		return __( 'Note: Stage', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		$value = get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_STAGE, true );
		echo esc_html( FF_JetEngine::stage_label( $value ) );
	}
}

/**
 * Note, Version number.
 */
class FF_Tag_Note_Trial extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-note-trial';
	}
	public function get_title() {
		return __( 'Note: Version number', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		echo esc_html( (string) get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_TRIAL, true ) );
	}
}

/**
 * Note, Date (formatted to the site's date format).
 */
class FF_Tag_Note_Date extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-note-date';
	}
	public function get_title() {
		return __( 'Note: Date', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		$date = get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_DATE, true );
		if ( $date ) {
			echo esc_html( mysql2date( get_option( 'date_format' ), $date ) );
		} else {
			echo esc_html( get_the_date( '', get_the_ID() ) );
		}
	}
}

/**
 * Note, Audience (formatted label).
 */
class FF_Tag_Note_Audience extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-note-audience';
	}
	public function get_title() {
		return __( 'Note: Audience', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		$value = get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_AUDIENCE, true );
		echo esc_html( FF_JetEngine::audience_label( $value ) );
	}
}

/**
 * Note, Product name (the linked product's title).
 */
class FF_Tag_Note_Product extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-note-product';
	}
	public function get_title() {
		return __( 'Note: Product name', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		$product_id = (int) get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_PRODUCT, true );
		echo esc_html( FF_JetEngine::product_title( $product_id ) );
	}
}

/**
 * Member, My Founding number (the signed-in member's own number).
 */
class FF_Tag_My_Number extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-my-number';
	}
	public function get_title() {
		return __( 'Member: My number', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		if ( ! FF_Gating::is_member() ) {
			return;
		}
		echo esc_html( (string) get_user_meta( get_current_user_id(), FF_Members::META_NUMBER, true ) );
	}
}

/**
 * Member, My group (the signed-in member's own group label).
 */
class FF_Tag_My_Group extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'ff-my-group';
	}
	public function get_title() {
		return __( 'Member: My group', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}
	public function render() {
		if ( ! FF_Gating::is_member() ) {
			return;
		}
		echo esc_html( FF_Gating::is_the_35() ? __( 'The 35', 'founding-faces' ) : __( 'The Circle', 'founding-faces' ) );
	}
}

/**
 * Note, Image gallery (a gallery data tag for Elementor's Gallery / Carousel).
 */
class FF_Tag_Note_Gallery extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'ff-note-gallery';
	}
	public function get_title() {
		return __( 'Note: Image gallery', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::GALLERY_CATEGORY );
	}

	/**
	 * Return the gallery as Elementor expects: a list of {id, url}.
	 *
	 * @param array $options Unused.
	 * @return array
	 */
	public function get_value( array $options = array() ) {
		$csv = get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_GALLERY, true );
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $csv ) ) );

		$gallery = array();
		foreach ( $ids as $id ) {
			$gallery[] = array(
				'id'  => $id,
				'url' => wp_get_attachment_url( $id ),
			);
		}
		return $gallery;
	}
}

/**
 * Note, First image (a single-image data tag for the Image widget).
 *
 * The gallery tag only appears on widgets that take a set of images. This gives
 * a single image (the first in the note's gallery) for a plain Image widget.
 */
class FF_Tag_Note_Image extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'ff-note-image';
	}
	public function get_title() {
		return __( 'Note: First image', 'founding-faces' );
	}
	public function get_group() {
		return 'founding-faces';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY );
	}

	/**
	 * Return the first gallery image as Elementor expects: {id, url}.
	 *
	 * @param array $options Unused.
	 * @return array
	 */
	public function get_value( array $options = array() ) {
		$csv = get_post_meta( get_the_ID(), FF_Post_Types::META_NOTE_GALLERY, true );
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $csv ) ) );
		$id  = ! empty( $ids ) ? reset( $ids ) : 0;

		return array(
			'id'  => $id,
			'url' => $id ? wp_get_attachment_url( $id ) : '',
		);
	}
}
