<?php
/**
 * Plugin Name:       Verifence Spam Shield
 * Plugin URI:        https://verifence.io
 * Description:       Connects WordPress comments, login, and registration to the Verifence platform — Shield bot protection, email validation, and URL threat scanning. Requires a Verifence account.
 * Version:           2.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Headway Apps
 * Author URI:        https://headwayapps.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dss-spam-shield
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'DSS_VERSION', '2.0.0' );
define( 'DSS_DB_VERSION', '1.0' );
define( 'DSS_PLUGIN_FILE', __FILE__ );
define( 'DSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, 'dss_activate' );
register_deactivation_hook( __FILE__, 'dss_deactivate' );

function dss_activate() {
	dss_create_tables();
	dss_set_default_options();
	if ( ! wp_next_scheduled( 'dss_prune_logs' ) ) {
		wp_schedule_event( time(), 'daily', 'dss_prune_logs' );
	}
	flush_rewrite_rules();
}

function dss_deactivate() {
	wp_clear_scheduled_hook( 'dss_prune_logs' );
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

function dss_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'dss_log';

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(50) NOT NULL DEFAULT '',
		ip_address varchar(45) NOT NULL DEFAULT '',
		username varchar(200) NOT NULL DEFAULT '',
		email varchar(200) NOT NULL DEFAULT '',
		details text NOT NULL DEFAULT '',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY ip_address (ip_address),
		KEY created_at (created_at)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'dss_db_version', DSS_DB_VERSION );
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

function dss_get_default_settings() {
	return array(
		// Verifence credentials.
		'api_key'         => '',    // Platform API key — for email + URL scans
		'shield_site_key' => '',    // sk_… loaded by the widget on all forms
		'shield_secret'   => '',    // sec_… used server-side for siteverify on all forms
		// Comment protection.
		'protect_comments'         => 1,
		'comment_min_score'        => 50,    // block if Shield score < this (0–100)
		'comment_spam_score'       => 60,    // block if comment text spam score >= this (0–100)
		'comment_scan_urls'        => 0,     // scan every URL found in the comment body
		'check_block_list'         => 1,     // block comments/registrations matching Verifence Block List
		// Login protection.
		'protect_login'            => 1,
		'login_rate_limit'         => 1,     // local fallback brute-force counter
		'login_max_attempts'       => 5,
		'login_lockout_duration'   => 900,
		'login_notify_admin'       => 0,
		// Registration protection.
		'protect_register'         => 1,
		'register_scan_email'      => 1,     // call /api/scan/email on the submitted address
		'register_block_disposable'=> 1,
		'register_block_email_rules'=> 1,    // block user-defined email/IP/country rules from Email Scan
		// General.
		'api_fallback'             => 'allow', // 'allow' | 'block' when the API is unreachable
		'log_events'               => 1,
		'log_event_types'          => array_keys( dss_get_log_event_labels() ),
		'log_retention_days'       => 30,
		'whitelist_ips'            => '',
		'whitelist_emails'         => '',
		'trust_proxy_headers'      => 0,
	);
}

function dss_get_log_event_labels() {
	return array(
		'comment_spam'     => __( 'Comment spam', 'dss-spam-shield' ),
		'comment_blocked'  => __( 'Comment blocked by API fallback', 'dss-spam-shield' ),
		'login_failed'     => __( 'Failed login attempts', 'dss-spam-shield' ),
		'login_blocked'    => __( 'Login lockouts / API fallback blocks', 'dss-spam-shield' ),
		'login_spam'       => __( 'Login Shield or nonce failures', 'dss-spam-shield' ),
		'register_spam'    => __( 'Registration spam / blocked registrations', 'dss-spam-shield' ),
		'register_blocked' => __( 'Registration API fallback blocks', 'dss-spam-shield' ),
	);
}

function dss_set_default_options() {
	if ( ! get_option( 'dss_settings' ) ) {
		add_option( 'dss_settings', dss_get_default_settings() );
	}
}

function dss_get_settings() {
	return wp_parse_args( (array) get_option( 'dss_settings', array() ), dss_get_default_settings() );
}

function dss_get_setting( $key ) {
	$s = dss_get_settings();
	return $s[ $key ] ?? null;
}

function dss_get_shield_token_from_request() {
	if ( isset( $_POST['shield-token'] ) ) {
		return sanitize_text_field( wp_unslash( $_POST['shield-token'] ) );
	}

	// Backward compatibility with earlier plugin builds and custom forms.
	if ( isset( $_POST['shield_token'] ) ) {
		return sanitize_text_field( wp_unslash( $_POST['shield_token'] ) );
	}

	return '';
}

function dss_get_request_log_context() {
	$parts = array();

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$parts[] = 'uri=' . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
		$parts[] = 'ref=' . esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
	}

	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		if ( strlen( $ua ) > 120 ) {
			$ua = substr( $ua, 0, 117 ) . '...';
		}
		$parts[] = 'ua=' . $ua;
	}

	return implode( ' | ', $parts );
}

function dss_format_log_details( $message, $context = '' ) {
	$message = sanitize_text_field( $message );
	$context = sanitize_text_field( $context );

	if ( '' === $context ) {
		return $message;
	}

	return $message . ' | ' . $context;
}

// ---------------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------------

function dss_log_event( $event_type, $ip, $username = '', $email = '', $details = '' ) {
	if ( ! dss_get_setting( 'log_events' ) ) {
		return;
	}

	$enabled_types = (array) dss_get_setting( 'log_event_types' );
	if ( ! in_array( $event_type, $enabled_types, true ) ) {
		return;
	}

	global $wpdb;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->prefix . 'dss_log',
		array(
			'event_type' => sanitize_text_field( $event_type ),
			'ip_address' => sanitize_text_field( $ip ),
			'username'   => sanitize_text_field( $username ),
			'email'      => sanitize_email( $email ),
			'details'    => sanitize_text_field( $details ),
			'created_at' => current_time( 'mysql', true ), // UTC — matches UTC_TIMESTAMP() in prune query
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

// ---------------------------------------------------------------------------
// IP utilities
// ---------------------------------------------------------------------------

function dss_get_client_ip() {
	// Only trust forwarded headers when the admin has explicitly opted in.
	// Blindly trusting them on a direct-connect server lets any client forge their IP.
	if ( dss_get_setting( 'trust_proxy_headers' ) ) {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					// Take the rightmost value — appended by the last trusted proxy,
					// not by the client. Leftmost is client-supplied and spoofable.
					$parts = array_map( 'trim', explode( ',', $ip ) );
					$ip    = end( $parts );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}
	}

	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return ( $remote && filter_var( $remote, FILTER_VALIDATE_IP ) ) ? $remote : '0.0.0.0';
}

function dss_is_ip_whitelisted( $ip ) {
	$raw = dss_get_setting( 'whitelist_ips' );
	if ( empty( $raw ) ) {
		return false;
	}
	$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	return in_array( $ip, $list, true );
}

function dss_is_email_whitelisted( $email ) {
	$raw = dss_get_setting( 'whitelist_emails' );
	if ( empty( $raw ) || empty( $email ) ) {
		return false;
	}
	$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	return in_array( strtolower( $email ), array_map( 'strtolower', $list ), true );
}

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------

require_once DSS_PLUGIN_DIR . 'includes/class-dss-rate-limiter.php';
require_once DSS_PLUGIN_DIR . 'includes/class-dss-api-client.php';
require_once DSS_PLUGIN_DIR . 'includes/class-dss-comment-protection.php';
require_once DSS_PLUGIN_DIR . 'includes/class-dss-login-protection.php';
require_once DSS_PLUGIN_DIR . 'includes/class-dss-registration-protection.php';

if ( is_admin() ) {
	require_once DSS_PLUGIN_DIR . 'admin/class-dss-admin.php';
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

add_action( 'plugins_loaded', 'dss_init' );

function dss_init() {
	load_plugin_textdomain( 'dss-spam-shield', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Ensure the DB table exists even when the plugin was updated by dropping files
	// (bypassing the activation hook). Safe to call on every request — dbDelta is idempotent.
	if ( get_option( 'dss_db_version' ) !== DSS_DB_VERSION ) {
		dss_create_tables();
	}

	new DSS_Comment_Protection();
	new DSS_Login_Protection();
	new DSS_Registration_Protection();

	if ( is_admin() ) {
		new DSS_Admin();
	}

	// Load the Shield widget on the WP login / registration page.
	add_action( 'login_enqueue_scripts', 'dss_enqueue_login_shield' );
}

/**
 * Enqueue the Verifence Shield widget on wp-login.php.
 * Picks the login or registration site key based on the current page action.
 */
function dss_enqueue_login_shield() {
	$s      = dss_get_settings();
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$site_key = $s['shield_site_key'];

	if ( empty( $site_key ) ) {
		return;
	}

	wp_enqueue_script( 'vss-shield', 'https://shield.verifence.io/shield/widget.js', array(), DSS_VERSION, true );
	// Set the endpoint BEFORE widget.js runs so it doesn't fall back to the
	// relative 'shield' path (which 404s on any non-Verifence origin).
	wp_add_inline_script(
		'vss-shield',
		'window.SHIELD_CHALLENGE_URL="https://shield.verifence.io/shield/challenge";window.SHIELD_VERIFY_URL="https://shield.verifence.io/shield/verify";',
		'before'
	);
}

// ---------------------------------------------------------------------------
// Log pruning cron
// ---------------------------------------------------------------------------

add_action( 'dss_prune_logs', 'dss_do_prune_logs' );

function dss_do_prune_logs() {
	global $wpdb;
	$days = absint( dss_get_setting( 'log_retention_days' ) );
	if ( $days < 1 ) {
		return;
	}
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}dss_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
			$days
		)
	);
}
