<?php
/**
 * Author-written copy: the one place that decides what markup is allowed.
 *
 * Every heading, label, hint and message a widget exposes as a field is copy
 * Nick writes, and copy sometimes needs a bold word or a link. Escaping it
 * outright put "<strong>" on the page as characters; letting anything through
 * would put a script tag there. This sits between the two, and it sits in one
 * file so the answer is the same everywhere.
 *
 * Two levels, chosen by where the text lands:
 *  - inline(): headings, labels, buttons, badges — emphasis and links only,
 *    because a paragraph or a list inside an <h3> or a <label> is markup
 *    nobody means to write.
 *  - rich():   messages and intros — whatever a post allows.
 *
 * Neither allows scripts, styles, iframes or event attributes. This is copy,
 * not a place to run code.
 *
 * NOTE: this is for text an administrator typed into a widget. Anything a
 * member typed — feedback, messages, their own name — stays escaped, and is
 * not routed through here.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Text
 */
class FF_Text {

	/**
	 * The tags allowed inside a heading, label or button.
	 *
	 * @return array
	 */
	public static function inline_tags() {
		return array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'small'  => array(),
			'sup'    => array(),
			'sub'    => array(),
			'br'     => array(),
			'span'   => array( 'class' => array() ),
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
				'class'  => array(),
			),
		);
	}

	/**
	 * Copy for a heading, label, button or badge.
	 *
	 * @param string $value The author's text.
	 * @return string Safe HTML.
	 */
	public static function inline( $value ) {
		return trim( wp_kses( (string) $value, self::inline_tags() ) );
	}

	/**
	 * Copy for a message or an intro: whatever a post allows.
	 *
	 * @param string $value The author's text.
	 * @return string Safe HTML.
	 */
	public static function rich( $value ) {
		return trim( wp_kses_post( (string) $value ) );
	}

	/**
	 * Whether a field actually says anything.
	 *
	 * Judged on the words, not the markup, so an empty tag left behind while
	 * editing doesn't count as content and doesn't render an empty element.
	 *
	 * @param string $value The author's text, already filtered or not.
	 * @return bool
	 */
	public static function filled( $value ) {
		return '' !== trim( wp_strip_all_tags( (string) $value ) );
	}
}
