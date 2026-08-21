<?php
/**
 * Plugin Name:       Founding Faces
 * Plugin URI:        https://foundingfaces.com
 * Description:        Runs the entire private membership programme for Apotheca: applications, moderation into The 35 or The Circle, member creation, formulation notes, polls, an anonymous members map, and email-platform sync. Lean, single-purpose, no bundled frameworks.
 * Version:           1.1.12
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            KDNA for Apotheca
 * Author URI:        https://foundingfaces.com
 * Text Domain:       founding-faces
 * License:           GPL-2.0-or-later
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Plugin-wide constants.
 * Everything the plugin needs to know about itself lives here, so paths and
 * the version number are defined once and reused everywhere.
 * ---------------------------------------------------------------------------
 */

// The plugin version. Used for asset cache-busting and database upgrades.
define( 'FF_VERSION', '1.1.12' );

// The database schema version. Bumped only when a table structure changes,
// so the activator knows when to run dbDelta again on an existing install.
// 1.1.0 adds the ff_messages table (the private member<->admin channel);
// 1.1.1 adds its attachment columns; 1.1.2 adds the protected-file path column
// (attachments now live in a gated directory, not the public uploads folder).
define( 'FF_DB_VERSION', '1.1.2' );

// Absolute path to this plugin's folder, with a trailing slash.
define( 'FF_PATH', plugin_dir_path( __FILE__ ) );

// Public URL to this plugin's folder, with a trailing slash.
define( 'FF_URL', plugin_dir_url( __FILE__ ) );

// The main plugin file, handy for register_activation_hook and similar.
define( 'FF_FILE', __FILE__ );

/*
 * ---------------------------------------------------------------------------
 * Includes.
 * The main file stays lean: it pulls in the classes that hold the logic and
 * wires up the hooks, but contains no feature logic of its own.
 * ---------------------------------------------------------------------------
 */

// The one decision about what markup author-written copy may carry. Loaded
// first: almost everything below renders a field an administrator typed.
require_once FF_PATH . 'includes/class-ff-text.php';

// Creates the custom tables and seeds the Group taxonomy terms on activation.
require_once FF_PATH . 'includes/class-ff-activator.php';

// Registers the Products and Notes post types and the Group taxonomy.
require_once FF_PATH . 'includes/class-ff-post-types.php';

// The front-end application form, submission handling and status lookup.
require_once FF_PATH . 'includes/class-ff-application.php';

// The interaction-log spine: one small helper for recording member actions.
require_once FF_PATH . 'includes/class-ff-interactions.php';

// Content gating: the server-side role check and the Elementor condition.
require_once FF_PATH . 'includes/class-ff-gating.php';

// Page-level access control: lock whole pages to a group, with redirect.
require_once FF_PATH . 'includes/class-ff-page-access.php';

// Keep members-only content (notes and restricted pages) out of search engines.
require_once FF_PATH . 'includes/class-ff-noindex.php';

// Admin "view as a member" preview (The 35 / The Circle), for testing.
require_once FF_PATH . 'includes/class-ff-preview.php';

// Per-item visibility for WordPress nav menus, by member group.
require_once FF_PATH . 'includes/class-ff-menu-visibility.php';

// Dynamic menu items: the login/logout swap and the unread count bubble,
// plus the login form component and its Elementor widget.
require_once FF_PATH . 'includes/class-ff-menu-items.php';

// The frontend display layer: the note template, renderer and components.
require_once FF_PATH . 'includes/class-ff-display.php';

// JetEngine integration: callbacks to format note meta in Dynamic Field widgets.
require_once FF_PATH . 'includes/class-ff-jetengine.php';

// Elementor dynamic tags for the note fields (for Loop Item / card design).
require_once FF_PATH . 'includes/class-ff-dynamic-tags.php';

// Polls: the poll content type, voting, results and the Elementor widget.
require_once FF_PATH . 'includes/class-ff-polls.php';

// The personal history page: a member's own record, and only their own.
require_once FF_PATH . 'includes/class-ff-history.php';

// The private member<->admin concierge channel: feedback, questions, replies.
require_once FF_PATH . 'includes/class-ff-messages.php';

// The anonymous members map (Leaflet, bundled; postcode-only, nothing clickable).
require_once FF_PATH . 'includes/class-ff-map.php';

// Privacy: export and delete a member's data (reused by the account page and
// the Stage 12 admin tools).
require_once FF_PATH . 'includes/class-ff-privacy.php';

// The member account-settings page: email, password, name, consent, data rights.
require_once FF_PATH . 'includes/class-ff-account.php';

// Membership: approval, user creation, numbering, withdrawal, welcome email.
require_once FF_PATH . 'includes/class-ff-members.php';

// The branded HTML email shell that wraps every programme email.
require_once FF_PATH . 'includes/class-ff-email-template.php';

// Welcome emails, the set-password token, and the login-page access screens.
// Loaded always: the login page and its screens are not the admin area.
require_once FF_PATH . 'includes/class-ff-emails.php';

// The unsubscribe link at the foot of every member email, and the screen that
// honours it. Loaded always: it has to work for someone who is not signed in.
require_once FF_PATH . 'includes/class-ff-unsubscribe.php';

// The email-platform connector interface and manager. The connectors
// themselves (Campaign Monitor, Klaviyo) are separate add-on plugins that
// register through the 'ff_register_connectors' hook, so the core never
// depends on them being installed.
require_once FF_PATH . 'includes/class-ff-connector.php';

// Admin-only screens: the moderation queue and the settings page.
if ( is_admin() ) {
	require_once FF_PATH . 'admin/admin-applications.php';
	require_once FF_PATH . 'admin/admin-settings.php';
	require_once FF_PATH . 'admin/admin-emails.php';
	require_once FF_PATH . 'admin/admin-privacy.php';
	require_once FF_PATH . 'admin/admin-messages.php';
}

/*
 * ---------------------------------------------------------------------------
 * Activation.
 * Runs once, the moment Nick clicks "Activate". Builds the data layer so the
 * rest of the plugin has tables and content types to work with.
 * ---------------------------------------------------------------------------
 */

// Register the activation routine that builds the database and content types.
register_activation_hook( FF_FILE, array( 'FF_Activator', 'activate' ) );

// Stop asking the email platform anything once the plugin is switched off.
register_deactivation_hook( FF_FILE, function () {
	$next = wp_next_scheduled( FF_Connectors::CRON_HOOK );
	if ( $next ) {
		wp_unschedule_event( $next, FF_Connectors::CRON_HOOK );
	}
} );

/*
 * ---------------------------------------------------------------------------
 * Boot the plugin.
 * Register the post types and taxonomy on every load. These are cheap to
 * register and WordPress needs them present on each request, not just on
 * activation, for the admin screens and queries to work.
 * ---------------------------------------------------------------------------
 */

/**
 * Start the plugin's runtime pieces.
 *
 * Called on the WordPress "init" hook, which is the correct, standard place
 * to register post types and taxonomies.
 */
function ff_init() {
	FF_Post_Types::register();
	FF_Application::register();
	FF_Members::register();
	FF_Emails::register();
	FF_Unsubscribe::register();
	FF_Connectors::register();
	FF_Gating::register();
	FF_Page_Access::register();
	FF_Noindex::register();
	FF_Preview::register();
	FF_Menu_Visibility::register();
	FF_Menu_Items::register();
	FF_Display::register();
	FF_JetEngine::register();
	FF_Dynamic_Tags::register();
	FF_Polls::register();
	FF_History::register();
	FF_Messages::register();
	FF_Map::register();
	FF_Account::register();

	// The admin screens, only in the admin area.
	if ( is_admin() ) {
		FF_Post_Types::register_admin();
		FF_Polls::register_admin();
		FF_Admin_Applications::register();
		FF_Settings::register();
		FF_Admin_Emails::register();
		FF_Admin_Privacy::register();
		FF_Admin_Messages::register();
	}

	// Notes gained a single URL (rewrite slug) in 1.0.9 and products in 1.0.79,
	// so flush the rewrite rules once on existing installs, otherwise the
	// /note/… and /formulation/… URLs would 404 until permalinks are re-saved
	// by hand.
	if ( '3' !== get_option( 'ff_rewrite_version' ) ) {
		flush_rewrite_rules( false );
		update_option( 'ff_rewrite_version', '3' );
	}
}
add_action( 'init', 'ff_init' );

/**
 * Run any pending database upgrades after plugins have loaded.
 *
 * If the stored schema version is older than the code's schema version, the
 * tables are (re)built. This lets a simple plugin update migrate the database
 * without Nick having to deactivate and reactivate the plugin.
 */
function ff_maybe_upgrade_db() {
	if ( get_option( 'ff_db_version' ) !== FF_DB_VERSION ) {
		FF_Activator::create_tables();
		update_option( 'ff_db_version', FF_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'ff_maybe_upgrade_db' );

/**
 * Clear Elementor's generated CSS once after the plugin is updated.
 *
 * Elementor turns a widget's control values into a CSS file and keeps it until
 * something tells it not to. The selectors those values are written against
 * live in this plugin's PHP, so a release that widens a selector, the day the
 * field rules learned about password inputs, say, leaves every page still
 * serving CSS built from the old ones. The control is set correctly and the
 * page ignores it, which reads as a bug in the control.
 *
 * Rather than ask for a manual "Regenerate CSS" after each update, the version
 * is remembered and the cache cleared once when it changes. Elementor rebuilds
 * each file the next time a page is viewed.
 */
function ff_maybe_clear_elementor_css() {
	if ( get_option( 'ff_css_version' ) === FF_VERSION ) {
		return;
	}

	if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->files_manager ) ) {
			$elementor->files_manager->clear_cache();
		}
	}

	// Rebuilding the CSS is only half of it. The pages that link to it are
	// themselves cached, stylesheet version and all, so a fresh file sits on
	// disk while every visitor is served the address of the old one. LiteSpeed
	// listens for this; nothing happens if it isn't installed.
	do_action( 'litespeed_purge_all' );

	// Anything else that caches the front end can hang off this.
	do_action( 'ff_version_changed', FF_VERSION, get_option( 'ff_css_version' ) );

	update_option( 'ff_css_version', FF_VERSION );
}
add_action( 'admin_init', 'ff_maybe_clear_elementor_css' );

/**
 * Take the em dashes out of copy that was saved before the rule was enforced.
 *
 * Fixing the shipped defaults did nothing for the templates that had already
 * been edited: those live in the options table, and an install that had been
 * written in kept every dash. This runs once and rewrites them, the same way
 * the save filter now rewrites anything typed from here on.
 */
function ff_sweep_em_dashes() {
	if ( '1' === get_option( 'ff_em_dash_swept' ) ) {
		return;
	}

	$keys = array(
		FF_Emails::OPT_35_SUBJECT,
		FF_Emails::OPT_35_BODY,
		FF_Emails::OPT_CIRCLE_SUBJECT,
		FF_Emails::OPT_CIRCLE_BODY,
		FF_Emails::OPT_PROMO_SUBJECT,
		FF_Emails::OPT_PROMO_BODY,
		FF_Emails::OPT_RECEIVED_SUBJECT,
		FF_Emails::OPT_RECEIVED_BODY,
		FF_Emails::OPT_DECLINE_SUBJECT,
		FF_Emails::OPT_DECLINE_BODY,
		FF_Email_Template::OPT_FOOTER,
		FF_Email_Template::OPT_DISCLAIMER,
	);

	foreach ( $keys as $key ) {
		$value = get_option( $key );
		if ( ! is_string( $value ) || '' === $value ) {
			continue;
		}

		$clean = FF_Text::no_em_dash( $value );
		if ( $clean !== $value ) {
			update_option( $key, $clean );
		}
	}

	update_option( 'ff_em_dash_swept', '1' );
}
add_action( 'admin_init', 'ff_sweep_em_dashes' );

/**
 * Rename poll tags from the id to the poll's own words.
 *
 * Anyone tagged before 1.1.9 carries poll-14, which says nothing useful in a
 * segment builder. The poll is still there, so the tag can be rewritten from
 * it rather than left as a number nobody can read.
 */
function ff_rename_poll_tags() {
	if ( '1' === get_option( 'ff_poll_tags_renamed' ) ) {
		return;
	}

	$users = get_users( array(
		'meta_key'     => FF_Members::META_TAGS, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_compare' => 'EXISTS',
		'number'       => 500,
	) );

	foreach ( $users as $user ) {
		$tags    = FF_Members::tags( $user->ID );
		$changed = false;

		foreach ( $tags as $i => $tag ) {
			if ( ! preg_match( '/^poll-(\d+)$/', $tag, $m ) ) {
				continue;
			}

			$renamed = FF_Polls::poll_tag( (int) $m[1] );
			if ( $renamed !== $tag ) {
				$tags[ $i ] = $renamed;
				$changed    = true;
			}
		}

		if ( $changed ) {
			FF_Members::set_tags( $user->ID, $tags );
			FF_Connectors::sync_member( $user->ID );
		}
	}

	update_option( 'ff_poll_tags_renamed', '1' );
}
add_action( 'admin_init', 'ff_rename_poll_tags' );

/**
 * Take the feedback tag off anyone who already carries it.
 *
 * Feedback used to add a gave-feedback tag, which meant the email platform was
 * told who writes to Nick privately. That is the wrong thing to share, so the
 * tag is no longer added and the ones already out there are withdrawn.
 */
function ff_remove_feedback_tags() {
	if ( '1' === get_option( 'ff_feedback_tags_removed' ) ) {
		return;
	}

	$users = get_users( array(
		'meta_key'     => FF_Members::META_TAGS, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_compare' => 'EXISTS',
		'number'       => 500,
	) );

	foreach ( $users as $user ) {
		// remove_tag() re-syncs on its own, which is what pushes the removal
		// out to the platform rather than only forgetting it here.
		FF_Members::remove_tag( $user->ID, 'gave-feedback' );
	}

	update_option( 'ff_feedback_tags_removed', '1' );
}
add_action( 'admin_init', 'ff_remove_feedback_tags' );
