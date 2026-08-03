<?php
/**
 * Plugin Name:       Founding Faces
 * Plugin URI:        https://foundingfaces.com
 * Description:        Runs the entire private membership programme for Apotheca: applications, moderation into The 35 or The Circle, member creation, formulation notes, polls, an anonymous members map, and email-platform sync. Lean, single-purpose, no bundled frameworks.
 * Version:           1.0.58
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
define( 'FF_VERSION', '1.0.58' );

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

// The email-platform connector interface and manager. The connectors
// themselves (Campaign Monitor, Klaviyo) are separate add-on plugins that
// register through the 'ff_register_connectors' hook, so the core never
// depends on them being installed.
require_once FF_PATH . 'includes/class-ff-connector.php';

// Admin-only screens: the moderation queue and the settings page.
if ( is_admin() ) {
	require_once FF_PATH . 'admin/admin-applications.php';
	require_once FF_PATH . 'admin/admin-settings.php';
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
		FF_Admin_Privacy::register();
		FF_Admin_Messages::register();
	}

	// Notes gained a single URL (rewrite slug) in 1.0.9, so flush the rewrite
	// rules once on existing installs — otherwise the /note/… URL would 404
	// until permalinks are re-saved by hand.
	if ( '2' !== get_option( 'ff_rewrite_version' ) ) {
		flush_rewrite_rules( false );
		update_option( 'ff_rewrite_version', '2' );
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
