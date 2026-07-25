<?php
/**
 * Plugin Name:       Founding Faces
 * Plugin URI:        https://foundingfaces.com
 * Description:        Runs the entire private membership programme for Apotheca: applications, moderation into The 35 or The Circle, member creation, formulation notes, polls, an anonymous members map, and email-platform sync. Lean, single-purpose, no bundled frameworks.
 * Version:           1.0.0
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
define( 'FF_VERSION', '1.0.0' );

// The database schema version. Bumped only when a table structure changes,
// so the activator knows when to run dbDelta again on an existing install.
define( 'FF_DB_VERSION', '1.0.0' );

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

// Membership: approval, user creation, numbering, withdrawal, welcome email.
require_once FF_PATH . 'includes/class-ff-members.php';

// The admin moderation queue (loaded only in the admin area).
if ( is_admin() ) {
	require_once FF_PATH . 'admin/admin-applications.php';
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

	// The moderation queue's admin screens, only in the admin area.
	if ( is_admin() ) {
		FF_Admin_Applications::register();
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
