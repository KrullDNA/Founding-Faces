<?php
/**
 * Activation routines: build the custom database tables and seed the
 * Group taxonomy terms. This is the plugin's data layer foundation.
 *
 * @package FoundingFaces
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FF_Activator
 *
 * Everything that must happen once, when the plugin is switched on: the three
 * custom tables that hold applications, poll votes and the interaction log,
 * and the two Group terms every member is sorted into.
 */
class FF_Activator {

	/**
	 * Master activation routine.
	 *
	 * Runs on plugin activation. Builds the tables, makes sure the post types
	 * and taxonomy are registered so their terms can be created, seeds the two
	 * Group terms, records the schema version, then flushes rewrite rules so
	 * WordPress recognises the new content types straight away.
	 */
	public static function activate() {
		// Build the three custom tables.
		self::create_tables();

		// Register the post types and taxonomy now, during activation, so we
		// can safely insert the Group terms below.
		FF_Post_Types::register();

		// Make sure the two membership groups exist as taxonomy terms.
		self::seed_group_terms();

		// Remember which schema version this install is on, for future upgrades.
		update_option( 'ff_db_version', FF_DB_VERSION );

		// Refresh permalinks so the new post types resolve correctly.
		flush_rewrite_rules();
	}

	/**
	 * Create (or update) the three custom tables.
	 *
	 * Uses dbDelta, WordPress's own schema tool, which creates the tables if
	 * they are missing and quietly adds any new columns on a later upgrade
	 * without destroying existing data. Every table carries the site's table
	 * prefix and the site's own character set and collation.
	 */
	public static function create_tables() {
		global $wpdb;

		// dbDelta lives in this admin file, which isn't always loaded.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * ff_applications — the sensitive tier.
		 * Holds the real name, email, postcode, skin concerns, Instagram handle,
		 * consent flag and timestamp, the free-text research answers, the
		 * application's status, and the Founding number once assigned. This data
		 * is admin-only and is never rendered on the public frontend.
		 */
		$applications = $wpdb->prefix . 'ff_applications';
		$sql_applications = "CREATE TABLE {$applications} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			name VARCHAR(191) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			postcode VARCHAR(4) NOT NULL DEFAULT '',
			instagram VARCHAR(191) NOT NULL DEFAULT '',
			skin_concerns TEXT NULL,
			answers LONGTEXT NULL,
			consent TINYINT(1) NOT NULL DEFAULT 0,
			consent_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			assigned_number INT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NULL,
			is_test TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY status (status),
			KEY user_id (user_id)
		) {$charset_collate};";
		dbDelta( $sql_applications );

		/*
		 * ff_poll_votes — one row per vote.
		 * Attributed in the database: which member, which poll, which option and
		 * when. Admin can see exactly who voted for what; the frontend only ever
		 * shows aggregates. The unique key stops a member voting twice on the
		 * same poll.
		 */
		$poll_votes = $wpdb->prefix . 'ff_poll_votes';
		$sql_poll_votes = "CREATE TABLE {$poll_votes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			poll_id BIGINT UNSIGNED NOT NULL,
			option_id BIGINT UNSIGNED NOT NULL,
			voted_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY member_poll (member_id, poll_id),
			KEY poll_id (poll_id),
			KEY member_id (member_id)
		) {$charset_collate};";
		dbDelta( $sql_poll_votes );

		/*
		 * ff_interactions — the spine.
		 * Every meaningful action a member takes is written here as member ID,
		 * interaction type, a reference ID (e.g. the note or poll it relates to)
		 * and a timestamp. This powers the personal-history page now and the
		 * launch showcase later, with no retrofit needed.
		 */
		$interactions = $wpdb->prefix . 'ff_interactions';
		$sql_interactions = "CREATE TABLE {$interactions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(50) NOT NULL,
			reference_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY member_id (member_id),
			KEY type (type)
		) {$charset_collate};";
		dbDelta( $sql_interactions );

		/*
		 * ff_messages — the private member<->admin concierge channel.
		 * A member's feedback on a note, or a question to Nick, starts a thread;
		 * Nick's reply (and any further member reply) are rows in the same thread.
		 * Strictly private and admin-only: never member-to-member, never public.
		 * member_read / admin_read drive the "new message" indicators on each side.
		 */
		$messages = $wpdb->prefix . 'ff_messages';
		$sql_messages = "CREATE TABLE {$messages} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT UNSIGNED NOT NULL,
			thread_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sender VARCHAR(10) NOT NULL DEFAULT 'member',
			context VARCHAR(20) NOT NULL DEFAULT 'question',
			reference_id BIGINT UNSIGNED NULL,
			subject VARCHAR(200) NOT NULL DEFAULT '',
			body TEXT NULL,
			attachment_url VARCHAR(255) NULL,
			attachment_name VARCHAR(191) NULL,
			member_read TINYINT(1) NOT NULL DEFAULT 1,
			admin_read TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY member_id (member_id),
			KEY thread_id (thread_id)
		) {$charset_collate};";
		dbDelta( $sql_messages );
	}

	/**
	 * Seed the two Group taxonomy terms.
	 *
	 * Every member belongs to exactly one of these two groups, so both must
	 * exist from the start. wp_insert_term is a no-op if the term already
	 * exists, so this is safe to run on every activation.
	 */
	public static function seed_group_terms() {
		$groups = array(
			'The 35'     => 'the-35',
			'The Circle' => 'the-circle',
		);

		foreach ( $groups as $name => $slug ) {
			// Only create the term if it isn't already there.
			if ( ! term_exists( $slug, FF_Post_Types::GROUP_TAXONOMY ) ) {
				wp_insert_term(
					$name,
					FF_Post_Types::GROUP_TAXONOMY,
					array( 'slug' => $slug )
				);
			}
		}
	}
}
