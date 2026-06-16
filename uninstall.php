<?php
/**
 * Fired when the plugin is uninstalled (not just deactivated).
 * Removes all plugin data: custom table, options, transients, and cron events.
 */

// WordPress only defines this constant when uninstall is triggered via the
// plugin list page or WP-CLI — abort if called directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the log table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dss_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

// Remove plugin options.
delete_option( 'dss_settings' );
delete_option( 'dss_db_version' );

// Remove all rate-limiting transients created by the plugin.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_dss\_rl\_%'
	    OR option_name LIKE '\_transient\_timeout\_dss\_rl\_%'"
);

// Clear the scheduled log-pruning event.
wp_clear_scheduled_hook( 'dss_prune_logs' );
