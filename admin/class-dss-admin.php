<?php
/**
 * Admin UI — Verifence credentials, protection settings, activity log.
 */

defined( 'ABSPATH' ) || exit;

class DSS_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_dss_clear_logs',  array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_dss_allow_ip',    array( $this, 'handle_allow_ip' ) );
		add_action( 'admin_post_dss_allow_email', array( $this, 'handle_allow_email' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( DSS_PLUGIN_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_menu() {
		add_menu_page(
			__( 'Verifence Spam Shield', 'dss-spam-shield' ),
			__( 'Verifence Spam Shield', 'dss-spam-shield' ),
			'manage_options',
			'dss-spam-shield',
			array( $this, 'render_settings_page' ),
			'dashicons-shield',
			80
		);

		add_submenu_page(
			'dss-spam-shield',
			__( 'Settings', 'dss-spam-shield' ),
			__( 'Settings', 'dss-spam-shield' ),
			'manage_options',
			'dss-spam-shield',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'dss-spam-shield',
			__( 'Activity Log', 'dss-spam-shield' ),
			__( 'Activity Log', 'dss-spam-shield' ),
			'manage_options',
			'dss-spam-shield-logs',
			array( $this, 'render_logs_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public function register_settings() {
		register_setting(
			'dss_settings_group',
			'dss_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( $raw ) {
		$defaults = dss_get_default_settings();
		$out      = array();

		// Plain text / API key fields — strip tags and extra whitespace only.
		foreach ( array( 'api_key', 'shield_site_key', 'shield_secret' ) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
		}

		// Boolean toggles.
		foreach ( array(
			'protect_comments', 'comment_scan_urls', 'check_block_list',
			'protect_login', 'login_rate_limit', 'login_notify_admin',
			'protect_register', 'register_scan_email', 'register_block_disposable', 'register_block_email_rules',
			'log_events', 'trust_proxy_headers',
		) as $key ) {
			$out[ $key ] = isset( $raw[ $key ] ) ? 1 : 0;
		}

		// Integer fields with enforced bounds [min, max].
		$int_bounds = array(
			'comment_min_score'      => array( 0, 100 ),
			'comment_spam_score'     => array( 0, 100 ),
			'login_max_attempts'     => array( 1, 100 ),
			'login_lockout_duration' => array( 60, 86400 ),
			'log_retention_days'     => array( 1, 365 ),
		);
		foreach ( $int_bounds as $key => list( $min, $max ) ) {
			$val         = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : $defaults[ $key ];
			$out[ $key ] = max( $min, min( $max, $val ) );
		}

		// api_fallback: one of 'allow' | 'block'.
		$out['api_fallback'] = ( isset( $raw['api_fallback'] ) && 'block' === $raw['api_fallback'] ) ? 'block' : 'allow';

		$allowed_event_types    = array_keys( dss_get_log_event_labels() );
		$selected_event_types   = isset( $raw['log_event_types'] ) && is_array( $raw['log_event_types'] ) ? $raw['log_event_types'] : array();
		$out['log_event_types'] = array_values( array_intersect( $allowed_event_types, array_map( 'sanitize_key', $selected_event_types ) ) );

		// Textarea fields.
		$out['whitelist_ips']    = isset( $raw['whitelist_ips'] )    ? sanitize_textarea_field( $raw['whitelist_ips'] )    : '';
		$out['whitelist_emails'] = isset( $raw['whitelist_emails'] ) ? sanitize_textarea_field( $raw['whitelist_emails'] ) : '';

		return $out;
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dss-spam-shield' ) );
		}
		$settings = dss_get_settings();
		require DSS_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dss-spam-shield' ) );
		}

		global $wpdb;

		$per_page     = 50;
		$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset       = ( $current_page - 1 ) * $per_page;
		$event_filter = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$where  = array();
		$params = array();

		if ( $event_filter ) {
			$where[]  = 'event_type = %s';
			$params[] = $event_filter;
		}

		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(ip_address LIKE %s OR username LIKE %s OR email LIKE %s OR details LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log {$where_sql}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			: $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		);

		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$logs         = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dss_log {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$query_params
			)
		);

		$event_counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT event_type, COUNT(*) AS total FROM {$wpdb->prefix}dss_log GROUP BY event_type ORDER BY total DESC",
			OBJECT_K
		);

		$total_events = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$last_event   = $wpdb->get_var( "SELECT created_at FROM {$wpdb->prefix}dss_log ORDER BY created_at DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

		// Trend: last 7 days vs previous 7 days, and today's count.
		$events_today = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log WHERE DATE(created_at) = DATE(UTC_TIMESTAMP())" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$events_last7 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$events_prev7 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY) AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

		// Per-day counts for the last 14 days (for the activity chart).
		$daily_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			"SELECT DATE(created_at) AS day, COUNT(*) AS total
			 FROM {$wpdb->prefix}dss_log
			 WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY)
			 GROUP BY DATE(created_at)"
		, ARRAY_A );
		$daily_by_date = array();
		foreach ( (array) $daily_rows as $row ) {
			$daily_by_date[ $row['day'] ] = (int) $row['total'];
		}
		// Fill in zeros for days with no events so the chart is complete.
		$chart_days = array();
		for ( $i = 13; $i >= 0; $i-- ) {
			$d                  = gmdate( 'Y-m-d', strtotime( "-{$i} days", time() ) );
			$chart_days[ $d ]   = $daily_by_date[ $d ] ?? 0;
		}

		// Top 10 IPs in the last 30 days.
		$top_ips = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			"SELECT ip_address, COUNT(*) AS total
			 FROM {$wpdb->prefix}dss_log
			 WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
			   AND ip_address NOT IN ('','0.0.0.0')
			 GROUP BY ip_address
			 ORDER BY total DESC
			 LIMIT 10"
		);

		$total_pages = (int) ceil( $total / $per_page );
		require DSS_PLUGIN_DIR . 'admin/views/page-logs.php';
	}

	// -------------------------------------------------------------------------
	// Clear-logs handler
	// -------------------------------------------------------------------------

	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'dss-spam-shield' ) );
		}
		check_admin_referer( 'dss_clear_logs' );

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}dss_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'dss-spam-shield-logs', 'dss_cleared' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Allow IP / Allow Email handlers
	// -------------------------------------------------------------------------

	public function handle_allow_ip() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'dss-spam-shield' ) );
		}
		check_admin_referer( 'dss_allow_ip' );

		$ip         = isset( $_POST['ip_address'] ) ? sanitize_text_field( wp_unslash( $_POST['ip_address'] ) ) : '';
		$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';

		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$s     = dss_get_settings();
			$lines = array_filter( array_map( 'trim', explode( "\n", $s['whitelist_ips'] ) ) );
			if ( ! in_array( $ip, $lines, true ) ) {
				$lines[]          = $ip;
				$s['whitelist_ips'] = implode( "\n", $lines );
				update_option( 'dss_settings', $s );
			}
			if ( ! empty( $s['api_key'] ) ) {
				DSS_API_Client::report_false_positive( $s['api_key'], 'ip', $ip, $event_type );
			}
		}

		wp_safe_redirect(
			add_query_arg( array( 'page' => 'dss-spam-shield-logs', 'dss_allowed' => 'ip' ), admin_url( 'admin.php' ) )
		);
		exit;
	}

	public function handle_allow_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'dss-spam-shield' ) );
		}
		check_admin_referer( 'dss_allow_email' );

		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';

		if ( $email && is_email( $email ) ) {
			$s     = dss_get_settings();
			$lines = array_filter( array_map( 'trim', explode( "\n", $s['whitelist_emails'] ) ) );
			if ( ! in_array( strtolower( $email ), array_map( 'strtolower', $lines ), true ) ) {
				$lines[]             = $email;
				$s['whitelist_emails'] = implode( "\n", $lines );
				update_option( 'dss_settings', $s );
			}
			if ( ! empty( $s['api_key'] ) ) {
				DSS_API_Client::report_false_positive( $s['api_key'], 'email', $email, $event_type );
			}
		}

		wp_safe_redirect(
			add_query_arg( array( 'page' => 'dss-spam-shield-logs', 'dss_allowed' => 'email' ), admin_url( 'admin.php' ) )
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Notices + links
	// -------------------------------------------------------------------------

	public function admin_notices() {
		if ( isset( $_GET['dss_cleared'] ) && '1' === $_GET['dss_cleared'] && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Activity log cleared.', 'dss-spam-shield' ) .
				'</p></div>';
		}

		if ( isset( $_GET['dss_allowed'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$type = sanitize_text_field( wp_unslash( $_GET['dss_allowed'] ) );
			$msg  = 'ip' === $type
				? __( 'IP address added to allowlist and reported to Verifence as a false positive.', 'dss-spam-shield' )
				: __( 'Email address added to allowlist and reported to Verifence as a false positive.', 'dss-spam-shield' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		// Warn if plugin is active but no credentials are set.
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'dss-spam-shield' ) !== false ) {
			$s = dss_get_settings();
			if ( empty( $s['shield_secret'] ) ) {
				echo '<div class="notice notice-warning"><p>' .
					sprintf(
						/* translators: %s: link to Verifence Shield settings */
						esc_html__( 'Verifence Spam Shield is active but no Shield credentials are configured — forms are not protected. %s', 'dss-spam-shield' ),
						'<a href="https://app.verifence.io/shield/sites" target="_blank" rel="noopener">' . esc_html__( 'Get your keys at verifence.io →', 'dss-spam-shield' ) . '</a>'
					) .
					'</p></div>';
			}
		}
	}

	public function plugin_action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=dss-spam-shield' ) ),
				esc_html__( 'Settings', 'dss-spam-shield' )
			)
		);
		return $links;
	}

	// -------------------------------------------------------------------------
	// Dashboard widget
	// -------------------------------------------------------------------------

	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'dss_shield_stats',
			__( 'Verifence Spam Shield', 'dss-spam-shield' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		global $wpdb;

		$total        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$today        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log WHERE DATE(created_at) = DATE(UTC_TIMESTAMP())" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$last7        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$prev7        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}dss_log WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 14 DAY) AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$last_event   = $wpdb->get_var( "SELECT created_at FROM {$wpdb->prefix}dss_log ORDER BY created_at DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$event_counts = $wpdb->get_results( "SELECT event_type, COUNT(*) AS total FROM {$wpdb->prefix}dss_log GROUP BY event_type ORDER BY total DESC LIMIT 5", OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

		if ( $prev7 > 0 ) {
			$trend_pct = round( ( ( $last7 - $prev7 ) / $prev7 ) * 100 );
		} elseif ( $last7 > 0 ) {
			$trend_pct = 100;
		} else {
			$trend_pct = null;
		}

		$last_display = $last_event
			? get_date_from_gmt( $last_event, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
			: __( 'Never', 'dss-spam-shield' );

		$event_labels = dss_get_log_event_labels();
		?>
		<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #f0f0f0;">
			<div>
				<strong style="display:block;font-size:22px;line-height:1.1;"><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
				<span style="color:#666;font-size:12px;"><?php esc_html_e( 'Total events', 'dss-spam-shield' ); ?></span>
			</div>
			<div>
				<strong style="display:block;font-size:22px;line-height:1.1;"><?php echo esc_html( number_format_i18n( $today ) ); ?></strong>
				<span style="color:#666;font-size:12px;"><?php esc_html_e( 'Today', 'dss-spam-shield' ); ?></span>
			</div>
			<div>
				<strong style="display:block;font-size:22px;line-height:1.1;">
					<?php echo esc_html( number_format_i18n( $last7 ) ); ?>
					<?php if ( null !== $trend_pct ) : ?>
					<small style="font-size:12px;color:<?php echo $trend_pct > 0 ? '#b32d2e' : '#46b450'; ?>;">
						<?php echo $trend_pct > 0 ? '&#9650;' : '&#9660;'; ?><?php echo esc_html( abs( $trend_pct ) ); ?>%
					</small>
					<?php endif; ?>
				</strong>
				<span style="color:#666;font-size:12px;"><?php esc_html_e( 'Last 7 days', 'dss-spam-shield' ); ?></span>
			</div>
		</div>

		<?php if ( $event_counts ) : ?>
		<table style="width:100%;border-collapse:collapse;margin-bottom:12px;font-size:13px;">
			<?php foreach ( $event_counts as $type => $row ) : ?>
			<tr>
				<td style="padding:3px 0;color:#333;"><?php echo esc_html( $event_labels[ $type ] ?? $type ); ?></td>
				<td style="padding:3px 0;text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( (int) $row->total ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php else : ?>
		<p style="color:#666;font-size:13px;margin-bottom:12px;"><?php esc_html_e( 'No events logged yet.', 'dss-spam-shield' ); ?></p>
		<?php endif; ?>

		<p style="margin:0;color:#666;font-size:12px;">
			<?php
			/* translators: %s: date/time of last event */
			printf( esc_html__( 'Last event: %s', 'dss-spam-shield' ), '<strong>' . esc_html( $last_display ) . '</strong>' );
			?>
			&nbsp;&middot;&nbsp;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dss-spam-shield-logs' ) ); ?>">
				<?php esc_html_e( 'View full log →', 'dss-spam-shield' ); ?>
			</a>
		</p>
		<?php
	}
}
