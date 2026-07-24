<?php
/**
 * Promptor Uninstall Script
 *
 * This file is executed when the plugin is deleted via the WordPress admin interface.
 * It cleans up all database tables, options, and metadata created by Promptor.
 *
 * @package Promptor
 * @since 1.1.0
 */

// Exit if accessed directly or not during uninstall process
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop custom database tables
$promptor_tables = array(
	$wpdb->prefix . 'promptor_queries',
	$wpdb->prefix . 'promptor_submissions',
	$wpdb->prefix . 'promptor_query_products',
);

foreach ( $promptor_tables as $promptor_table_name ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$promptor_table_name}`" );
}

// 2. Delete all Promptor options
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE 'promptor_%'" );

// 3. Delete all Promptor transients
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient_promptor_%' OR `option_name` LIKE '_transient_timeout_promptor_%'" );

// 4. Delete post metadata (indexed content markers)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM `{$wpdb->postmeta}` WHERE `meta_key` LIKE 'promptor_%'" );

// 5. Clear any remaining cache
wp_cache_flush();

// Optional: Log uninstall for debugging (only in development)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( 'Promptor: Plugin uninstalled and all data removed.' );
}
