<?php
/**
 * Keep members-only content out of search engines.
 *
 * Gated content is already protected server-side (notes and access-restricted
 * pages redirect anyone who isn't allowed, and Googlebot always crawls
 * logged-out, so it hits the redirect). This adds the explicit, belt-and-braces
 * layer on top: every note, every product and every access-restricted page is
 * marked
 * "noindex, nofollow", as both a <meta> robots tag and an X-Robots-Tag HTTP
 * header, and restricted pages are dropped from the sitemap. So member-only
 * URLs are never listed by Google, even if one is discovered.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Noindex
 */
class FF_Noindex {

	/**
	 * Wire up the robots directives and sitemap exclusion.
	 */
	public static function register() {
		// The <meta name="robots"> tag (WordPress 5.7+ core outputs wp_robots()).
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );

		// The X-Robots-Tag HTTP header, as a second, independent signal.
		add_action( 'template_redirect', array( __CLASS__, 'send_header' ), 0 );

		// Drop access-restricted pages/posts from the core sitemap. (Notes and
		// products are already removed wholesale by
		// FF_Gating::exclude_private_from_sitemap.)
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_restricted_from_sitemap' ), 10, 2 );
	}

	/**
	 * Whether the current request is members-only content.
	 *
	 * True for a single note (members-only by nature) and for any single
	 * page/post carrying a Founding Faces access restriction.
	 *
	 * @return bool
	 */
	public static function is_restricted_request() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return false;
		}

		// Notes and products are always members-only.
		if ( class_exists( 'FF_Post_Types' ) && in_array( get_post_type( $post_id ), array( FF_Post_Types::NOTE_CPT, FF_Post_Types::PRODUCT_CPT ), true ) ) {
			return true;
		}

		// Pages/posts locked to a member tier.
		$level = get_post_meta( $post_id, FF_Page_Access::META, true );
		return ! empty( $level );
	}

	/**
	 * Add noindex/nofollow to the robots meta for restricted content.
	 *
	 * @param array $robots The wp_robots directives.
	 * @return array
	 */
	public static function filter_robots( $robots ) {
		if ( self::is_restricted_request() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			// These would contradict noindex/nofollow, so make sure they're off.
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	/**
	 * Send an X-Robots-Tag header for restricted content.
	 *
	 * A second, independent signal to search engines, and one that also covers
	 * non-HTML responses. Sent before any redirect enforcement exits.
	 *
	 * @return void
	 */
	public static function send_header() {
		if ( ! headers_sent() && self::is_restricted_request() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/**
	 * Exclude access-restricted pages/posts from the core sitemap.
	 *
	 * Restricted pages always carry the access meta (it's deleted when set back
	 * to Public), so excluding "meta exists" leaves only public URLs listed.
	 *
	 * @param array  $args      The query args for the sitemap.
	 * @param string $post_type The post type being listed.
	 * @return array
	 */
	public static function exclude_restricted_from_sitemap( $args, $post_type ) {
		if ( ! in_array( $post_type, FF_Page_Access::post_types(), true ) ) {
			return $args;
		}
		$meta_query   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => FF_Page_Access::META,
			'compare' => 'NOT EXISTS',
		);
		$args['meta_query'] = $meta_query;
		return $args;
	}
}
