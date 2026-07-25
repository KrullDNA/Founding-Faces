<?php
/**
 * Elementor dynamic tags for the note fields.
 *
 * Registers a "Founding Faces" group of dynamic tags so a note's structured
 * fields can be pulled into any Elementor widget (and, crucially, into an
 * Elementor Pro Loop Item template) by clicking the dynamic (database) icon and
 * picking the field — no meta keys to type, no JetEngine required.
 *
 * The tags read the current post in the loop, so dropping a Heading widget in a
 * Loop Item and setting its dynamic source to "Founding Faces — Note Stage"
 * shows that note's stage, formatted.
 *
 * Adds nothing if Elementor isn't installed.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Dynamic_Tags
 */
class FF_Dynamic_Tags {

	/**
	 * Hook the dynamic-tags registration (supporting old and new Elementor).
	 */
	public static function register() {
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_tags' ) );
		// Older Elementor used a different action name.
		add_action( 'elementor/dynamic_tags/register_tags', array( __CLASS__, 'register_tags' ) );
	}

	/**
	 * Register the group and the tags.
	 *
	 * @param object $dynamic_tags The dynamic-tags manager passed by Elementor.
	 * @return void
	 */
	public static function register_tags( $dynamic_tags ) {
		// Only do this once even if both actions fire.
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		// The tag classes extend Elementor base classes, which only exist once
		// Elementor is loaded — which it is, since this runs on its hook.
		require_once FF_PATH . 'includes/class-ff-dynamic-tag-classes.php';

		if ( method_exists( $dynamic_tags, 'register_group' ) ) {
			$dynamic_tags->register_group( 'founding-faces', array(
				'title' => __( 'Founding Faces', 'founding-faces' ),
			) );
		}

		$tags = array(
			'FF_Tag_Note_Stage',
			'FF_Tag_Note_Trial',
			'FF_Tag_Note_Date',
			'FF_Tag_Note_Audience',
			'FF_Tag_Note_Product',
			'FF_Tag_Note_Gallery',
			'FF_Tag_Note_Image',
			'FF_Tag_My_Number',
			'FF_Tag_My_Group',
		);

		foreach ( $tags as $tag ) {
			if ( method_exists( $dynamic_tags, 'register' ) ) {
				$dynamic_tags->register( new $tag() );
			} elseif ( method_exists( $dynamic_tags, 'register_tag' ) ) {
				$dynamic_tags->register_tag( $tag );
			}
		}
	}
}
