<?php
/**
 * Activity log page view.
 *
 * Variables available from the calling method:
 *   $logs          array   — log rows from DB (may be empty)
 *   $total         int     — total rows matching current filter
 *   $total_pages   int     — total pagination pages
 *   $current_page  int     — current page number
 *   $event_filter  string  — active event-type filter (may be empty)
 *   $search        string  — active search string (may be empty)
 *   $event_counts  array   — counts keyed by event type
 *   $total_events  int     — total stored events
 *   $last_event    string  — latest event UTC datetime
 */

defined( 'ABSPATH' ) || exit;

$event_labels = dss_get_log_event_labels();

$event_badges = array(
	'comment_spam'     => 'notice-error',
	'comment_blocked'  => 'notice-warning',
	'login_failed'     => 'notice-info',
	'login_blocked'    => 'notice-warning',
	'login_spam'       => 'notice-error',
	'register_spam'    => 'notice-error',
	'register_blocked' => 'notice-warning',
);

$last_event_display = $last_event
	? get_date_from_gmt( $last_event, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
	: __( 'Never', 'dss-spam-shield' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Verifence Spam Shield — Activity Log', 'dss-spam-shield' ); ?></h1>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0;">
		<div class="card" style="max-width:none;padding:14px;">
			<strong style="display:block;font-size:22px;line-height:1;"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></strong>
			<span><?php esc_html_e( 'Total events', 'dss-spam-shield' ); ?></span>
		</div>
		<div class="card" style="max-width:none;padding:14px;">
			<strong style="display:block;font-size:22px;line-height:1;"><?php echo esc_html( number_format_i18n( $events_today ) ); ?></strong>
			<span><?php esc_html_e( 'Today', 'dss-spam-shield' ); ?></span>
		</div>
		<div class="card" style="max-width:none;padding:14px;">
			<strong style="display:block;font-size:22px;line-height:1.1;">
				<?php echo esc_html( number_format_i18n( $events_last7 ) ); ?>
				<?php
				if ( $events_prev7 > 0 ) {
					$_pct   = round( ( ( $events_last7 - $events_prev7 ) / $events_prev7 ) * 100 );
					$_color = $_pct > 0 ? '#b32d2e' : '#46b450';
					$_arrow = $_pct > 0 ? '&#9650;' : '&#9660;';
					echo '<small style="font-size:12px;color:' . esc_attr( $_color ) . ';">' . wp_kses_post( $_arrow ) . esc_html( abs( $_pct ) ) . '%</small>';
				}
				?>
			</strong>
			<span><?php esc_html_e( 'Last 7 days', 'dss-spam-shield' ); ?></span>
		</div>
		<div class="card" style="max-width:none;padding:14px;">
			<strong style="display:block;font-size:22px;line-height:1;"><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
			<span><?php esc_html_e( 'Matching filters', 'dss-spam-shield' ); ?></span>
		</div>
		<div class="card" style="max-width:none;padding:14px;">
			<strong style="display:block;font-size:14px;line-height:1.4;"><?php echo esc_html( $last_event_display ); ?></strong>
			<span><?php esc_html_e( 'Latest event', 'dss-spam-shield' ); ?></span>
		</div>
	</div>

	<?php
	// 14-day activity chart + top IPs — only worth rendering when there is data.
	$chart_max = $chart_days ? max( $chart_days ) : 0;
	?>
	<?php if ( $chart_max > 0 || $top_ips ) : ?>
	<div style="display:grid;grid-template-columns:1fr auto;gap:24px;align-items:start;margin:0 0 20px;">

		<?php if ( $chart_max > 0 ) : ?>
		<div class="card" style="max-width:none;padding:14px;">
			<strong style="display:block;margin-bottom:10px;"><?php esc_html_e( 'Activity — last 14 days', 'dss-spam-shield' ); ?></strong>
			<div style="display:flex;align-items:flex-end;gap:3px;height:64px;" aria-hidden="true">
				<?php foreach ( $chart_days as $day => $count ) : ?>
				<?php $bar_h = $chart_max > 0 ? max( 2, round( ( $count / $chart_max ) * 100 ) ) : 2; ?>
				<div
					style="flex:1;background:<?php echo $count > 0 ? '#0073aa' : '#e2e4e7'; ?>;height:<?php echo esc_attr( $bar_h ); ?>%;border-radius:2px 2px 0 0;"
					title="<?php echo esc_attr( $day . ': ' . number_format_i18n( $count ) . ' ' . __( 'events', 'dss-spam-shield' ) ); ?>"
				></div>
				<?php endforeach; ?>
			</div>
			<div style="display:flex;justify-content:space-between;margin-top:4px;font-size:11px;color:#666;">
				<span><?php echo esc_html( gmdate( 'M j', strtotime( '-13 days' ) ) ); ?></span>
				<span><?php esc_html_e( 'Today', 'dss-spam-shield' ); ?></span>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $top_ips ) : ?>
		<div class="card" style="min-width:200px;padding:14px;">
			<strong style="display:block;margin-bottom:8px;"><?php esc_html_e( 'Top IPs (30 days)', 'dss-spam-shield' ); ?></strong>
			<table style="border-collapse:collapse;font-size:13px;width:100%;">
				<?php foreach ( $top_ips as $row ) : ?>
				<tr>
					<td style="padding:2px 8px 2px 0;font-family:monospace;"><?php echo esc_html( $row->ip_address ); ?></td>
					<td style="padding:2px 0;text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( (int) $row->total ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>
		<?php endif; ?>

	</div>
	<?php endif; ?>

	<form method="get" action="" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:16px 0;">
		<input type="hidden" name="page" value="dss-spam-shield-logs" />
		<label class="screen-reader-text" for="dss-event-type"><?php esc_html_e( 'Filter by event type', 'dss-spam-shield' ); ?></label>
		<label class="screen-reader-text" for="dss-log-search"><?php esc_html_e( 'Search activity log', 'dss-spam-shield' ); ?></label>
		<select id="dss-event-type" name="event_type">
			<option value=""><?php esc_html_e( '— All Events —', 'dss-spam-shield' ); ?></option>
			<?php foreach ( $event_labels as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $event_filter, $value ); ?>>
					<?php
					$count = isset( $event_counts[ $value ] ) ? (int) $event_counts[ $value ]->total : 0;
					echo esc_html( sprintf( '%s (%s)', $label, number_format_i18n( $count ) ) );
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<input
			id="dss-log-search"
			type="search"
			name="s"
			value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Search IP, email, user, details', 'dss-spam-shield' ); ?>"
			style="min-width:280px;"
		/>
		<?php submit_button( __( 'Filter', 'dss-spam-shield' ), 'secondary', '', false ); ?>
		<?php if ( $event_filter || $search ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=dss-spam-shield-logs' ) ); ?>">
				<?php esc_html_e( 'Reset', 'dss-spam-shield' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<?php if ( $logs ) : ?>

		<p>
			<?php
			printf(
				/* translators: 1: current page  2: total pages  3: total records */
				esc_html__( 'Page %1$d of %2$d (%3$d total events)', 'dss-spam-shield' ),
				$current_page,
				$total_pages,
				$total
			);
			?>
		</p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:170px;"><?php esc_html_e( 'Event', 'dss-spam-shield' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'IP Address', 'dss-spam-shield' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'Username', 'dss-spam-shield' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Email', 'dss-spam-shield' ); ?></th>
					<th><?php esc_html_e( 'Details', 'dss-spam-shield' ); ?></th>
					<th style="width:190px;"><?php esc_html_e( 'Date', 'dss-spam-shield' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Actions', 'dss-spam-shield' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
				<?php
				$label       = $event_labels[ $log->event_type ] ?? $log->event_type;
				$badge_class = $event_badges[ $log->event_type ] ?? 'notice-info';
				$local_time  = get_date_from_gmt( $log->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
				$timestamp   = strtotime( $log->created_at . ' UTC' );
				?>
				<tr>
					<td>
						<span class="notice <?php echo esc_attr( $badge_class ); ?>" style="display:inline-block;margin:0;padding:2px 8px;border-left-width:4px;background:#fff;">
							<?php echo esc_html( $label ); ?>
						</span>
					</td>
					<td><code><?php echo esc_html( $log->ip_address ?: '—' ); ?></code></td>
					<td><?php echo esc_html( $log->username ?: '—' ); ?></td>
					<td><?php echo esc_html( $log->email ?: '—' ); ?></td>
					<td style="word-break:break-word;"><?php echo esc_html( $log->details ?: '—' ); ?></td>
					<td>
						<?php echo esc_html( $local_time ); ?>
						<?php if ( $timestamp ) : ?>
							<br /><small title="<?php echo esc_attr( $log->created_at . ' UTC' ); ?>"><?php echo esc_html( human_time_diff( $timestamp, time() ) . ' ago' ); ?></small>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( ! empty( $log->ip_address ) && '0.0.0.0' !== $log->ip_address && ! dss_is_ip_whitelisted( $log->ip_address ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin:0 2px 2px 0;">
							<input type="hidden" name="action" value="dss_allow_ip" />
							<input type="hidden" name="ip_address" value="<?php echo esc_attr( $log->ip_address ); ?>" />
							<input type="hidden" name="event_type" value="<?php echo esc_attr( $log->event_type ); ?>" />
							<?php wp_nonce_field( 'dss_allow_ip' ); ?>
							<button type="submit" class="button button-small"
								onclick="return confirm('<?php echo esc_js( sprintf( __( 'Allow IP %s and report as false positive?', 'dss-spam-shield' ), $log->ip_address ) ); ?>')"
								title="<?php esc_attr_e( 'Add to IP allowlist and report false positive to Verifence', 'dss-spam-shield' ); ?>">
								<?php esc_html_e( 'Allow IP', 'dss-spam-shield' ); ?>
							</button>
						</form>
						<?php endif; ?>
						<?php if ( ! empty( $log->email ) && is_email( $log->email ) && ! dss_is_email_whitelisted( $log->email ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin:0;">
							<input type="hidden" name="action" value="dss_allow_email" />
							<input type="hidden" name="email" value="<?php echo esc_attr( $log->email ); ?>" />
							<input type="hidden" name="event_type" value="<?php echo esc_attr( $log->event_type ); ?>" />
							<?php wp_nonce_field( 'dss_allow_email' ); ?>
							<button type="submit" class="button button-small"
								onclick="return confirm('<?php echo esc_js( sprintf( __( 'Allow email %s and report as false positive?', 'dss-spam-shield' ), $log->email ) ); ?>')"
								title="<?php esc_attr_e( 'Add to email allowlist and report false positive to Verifence', 'dss-spam-shield' ); ?>">
								<?php esc_html_e( 'Allow Email', 'dss-spam-shield' ); ?>
							</button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $current_page,
							'total'   => $total_pages,
						)
					)
				);
				?>
			</div>
		</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				echo esc_html(
					( $event_filter || $search )
						? __( 'No events match the current filters.', 'dss-spam-shield' )
						: __( 'No events logged yet. Events appear here after Shield blocks or flags comments, logins, or registrations.', 'dss-spam-shield' )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<br />
	<hr />
	<h3><?php esc_html_e( 'Clear Log', 'dss-spam-shield' ); ?></h3>
	<p><?php esc_html_e( 'This will permanently delete all activity log entries.', 'dss-spam-shield' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete all log entries? This cannot be undone.', 'dss-spam-shield' ) ); ?>');">
		<input type="hidden" name="action" value="dss_clear_logs" />
		<?php wp_nonce_field( 'dss_clear_logs' ); ?>
		<?php submit_button( __( 'Clear All Logs', 'dss-spam-shield' ), 'delete', '', false ); ?>
	</form>
</div>
