<?php
/**
 * Admin settings page — Verifence credentials and protection options.
 *
 * Available: $settings (array — merged plugin settings)
 */

defined( 'ABSPATH' ) || exit;

$log_event_labels = dss_get_log_event_labels();
$enabled_log_types = (array) ( $settings['log_event_types'] ?? array_keys( $log_event_labels ) );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Verifence Spam Shield', 'dss-spam-shield' ); ?></h1>
	<p>
		<?php
		printf(
			/* translators: %s: link to Verifence dashboard */
			esc_html__( 'Keys are created in your %s under Shield → Sites.', 'dss-spam-shield' ),
			'<a href="https://app.verifence.io" target="_blank" rel="noopener">Verifence dashboard</a>'
		);
		?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'dss_settings_group' ); ?>

		<!-- ================================================================ -->
		<!-- Verifence Credentials                                            -->
		<!-- ================================================================ -->
		<h2 class="title"><?php esc_html_e( 'Verifence Credentials', 'dss-spam-shield' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'Platform API Key', 'dss-spam-shield' ); ?></th>
				<td>
					<input type="password" name="dss_settings[api_key]"
						value="<?php echo esc_attr( $settings['api_key'] ); ?>"
						class="regular-text" autocomplete="off" />
					<p class="description">
						<?php esc_html_e( 'Required for email validation and URL scanning. Found in Settings → API Keys.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Shield Site Key', 'dss-spam-shield' ); ?></th>
				<td>
					<input type="text" name="dss_settings[shield_site_key]"
						value="<?php echo esc_attr( $settings['shield_site_key'] ); ?>"
						class="regular-text" placeholder="sk_…" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Public site key — loaded by the widget on all forms (comments, login, registration).', 'dss-spam-shield' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Shield Secret Key', 'dss-spam-shield' ); ?></th>
				<td>
					<input type="password" name="dss_settings[shield_secret]"
						value="<?php echo esc_attr( $settings['shield_secret'] ); ?>"
						class="regular-text" placeholder="sec_…" autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Private secret key — used server-side to verify tokens on all forms. Never expose this publicly.', 'dss-spam-shield' ); ?></p>
				</td>
			</tr>

		</table>

		<hr />

		<!-- ================================================================ -->
		<!-- Comment Protection                                               -->
		<!-- ================================================================ -->
		<h2 class="title"><?php esc_html_e( 'Comment Protection', 'dss-spam-shield' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'Enable', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[protect_comments]" value="1"
							<?php checked( $settings['protect_comments'], 1 ); ?> />
						<?php esc_html_e( 'Protect comment forms with Shield', 'dss-spam-shield' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Minimum Shield Score', 'dss-spam-shield' ); ?></th>
				<td>
					<input type="number" name="dss_settings[comment_min_score]"
						value="<?php echo absint( $settings['comment_min_score'] ); ?>"
						min="0" max="100" style="width:70px;" />
					<p class="description">
						<?php esc_html_e( 'Block comments where the Shield risk score is below this value (0–100; higher = more likely human). Default: 50.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Maximum Spam Score', 'dss-spam-shield' ); ?></th>
				<td>
					<input type="number" name="dss_settings[comment_spam_score]"
						value="<?php echo absint( $settings['comment_spam_score'] ); ?>"
						min="0" max="100" style="width:70px;" />
					<p class="description">
						<?php esc_html_e( 'Block comments where Verifence text analysis returns this spam score or higher (0 disables this check). Default: 60.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'URL Threat Scan', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[comment_scan_urls]" value="1"
							<?php checked( $settings['comment_scan_urls'], 1 ); ?> />
						<?php esc_html_e( 'Scan every URL in the comment body against the Verifence threat database', 'dss-spam-shield' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Uses the URL Scan API — requires Platform API Key. Each URL costs 5 credits.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Block List', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[check_block_list]" value="1"
							<?php checked( $settings['check_block_list'], 1 ); ?> />
						<?php esc_html_e( 'Block comments and registrations matching your Verifence Block List', 'dss-spam-shield' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Checks submitted email addresses and visitor IPs. Requires Platform API Key.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<hr />

		<!-- ================================================================ -->
		<!-- Login Protection                                                 -->
		<!-- ================================================================ -->
		<h2 class="title"><?php esc_html_e( 'Login Protection', 'dss-spam-shield' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'Enable', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[protect_login]" value="1"
							<?php checked( $settings['protect_login'], 1 ); ?> />
						<?php esc_html_e( 'Protect login form with Shield', 'dss-spam-shield' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Brute-Force Lockout', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[login_rate_limit]" value="1"
							<?php checked( $settings['login_rate_limit'], 1 ); ?> />
						<?php esc_html_e( 'Lock out IP after too many failures (always active as fallback)', 'dss-spam-shield' ); ?>
					</label>
					<br />
					<?php esc_html_e( 'Lock after', 'dss-spam-shield' ); ?>
					<input type="number" name="dss_settings[login_max_attempts]"
						value="<?php echo absint( $settings['login_max_attempts'] ); ?>"
						min="1" max="100" style="width:60px;" />
					<?php esc_html_e( 'failed attempts for', 'dss-spam-shield' ); ?>
					<input type="number" name="dss_settings[login_lockout_duration]"
						value="<?php echo absint( $settings['login_lockout_duration'] ); ?>"
						min="60" max="86400" style="width:80px;" />
					<?php esc_html_e( 'seconds', 'dss-spam-shield' ); ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Admin Notification', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[login_notify_admin]" value="1"
							<?php checked( $settings['login_notify_admin'], 1 ); ?> />
						<?php esc_html_e( 'Email admin when an IP is locked out', 'dss-spam-shield' ); ?>
					</label>
				</td>
			</tr>

		</table>

		<hr />

		<!-- ================================================================ -->
		<!-- Registration Protection                                          -->
		<!-- ================================================================ -->
		<h2 class="title"><?php esc_html_e( 'Registration Protection', 'dss-spam-shield' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'Enable', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[protect_register]" value="1"
							<?php checked( $settings['protect_register'], 1 ); ?> />
						<?php esc_html_e( 'Protect registration form with Shield', 'dss-spam-shield' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email Validation', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[register_scan_email]" value="1"
							<?php checked( $settings['register_scan_email'], 1 ); ?> />
						<?php esc_html_e( 'Check registration email via Verifence Email Scan', 'dss-spam-shield' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Blocks disposable addresses, IP/country-blocked registrations, and shows typo corrections. Requires Platform API Key. Costs 1 credit per registration.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email Blocks', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[register_block_disposable]" value="1"
							<?php checked( $settings['register_block_disposable'], 1 ); ?> />
						<?php esc_html_e( 'Block disposable or temporary email addresses', 'dss-spam-shield' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="dss_settings[register_block_email_rules]" value="1"
							<?php checked( $settings['register_block_email_rules'], 1 ); ?> />
						<?php esc_html_e( 'Block matches from Verifence Email Rules (email, IP, and country)', 'dss-spam-shield' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'These options use Email Validation above. Disable a checkbox to keep scanning but stop blocking that specific result.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<hr />

		<!-- ================================================================ -->
		<!-- General                                                          -->
		<!-- ================================================================ -->
		<h2 class="title"><?php esc_html_e( 'General', 'dss-spam-shield' ); ?></h2>
		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'API Fallback', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="radio" name="dss_settings[api_fallback]" value="allow"
							<?php checked( $settings['api_fallback'], 'allow' ); ?> />
						<?php esc_html_e( 'Allow — let the submission through if the Verifence API is unreachable', 'dss-spam-shield' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="dss_settings[api_fallback]" value="block"
							<?php checked( $settings['api_fallback'], 'block' ); ?> />
						<?php esc_html_e( 'Block — reject all submissions if the API is unreachable', 'dss-spam-shield' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Recommended: Allow (prevents site outage during API downtime). Verifence uptime is tracked at status.verifence.io.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Activity Logging', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[log_events]" value="1"
							<?php checked( $settings['log_events'], 1 ); ?> />
						<?php esc_html_e( 'Log blocked and suspicious events', 'dss-spam-shield' ); ?>
					</label>
					<br />
					<?php esc_html_e( 'Retain logs for', 'dss-spam-shield' ); ?>
					<input type="number" name="dss_settings[log_retention_days]"
						value="<?php echo absint( $settings['log_retention_days'] ); ?>"
						min="1" max="365" style="width:60px;" />
					<?php esc_html_e( 'days', 'dss-spam-shield' ); ?>
					<fieldset style="margin-top:12px;">
						<legend class="screen-reader-text"><?php esc_html_e( 'Events to log', 'dss-spam-shield' ); ?></legend>
						<strong><?php esc_html_e( 'Events to log', 'dss-spam-shield' ); ?></strong>
						<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:6px 16px;margin-top:8px;">
							<?php foreach ( $log_event_labels as $event_key => $event_label ) : ?>
								<label>
									<input
										type="checkbox"
										name="dss_settings[log_event_types][]"
										value="<?php echo esc_attr( $event_key ); ?>"
										<?php checked( in_array( $event_key, $enabled_log_types, true ) ); ?>
									/>
									<?php echo esc_html( $event_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description">
							<?php esc_html_e( 'Disabling an event type stops future entries for that event. Existing log entries are not removed.', 'dss-spam-shield' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'IP Allowlist', 'dss-spam-shield' ); ?></th>
				<td>
					<textarea name="dss_settings[whitelist_ips]" rows="4" class="large-text"><?php echo esc_textarea( $settings['whitelist_ips'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One IP per line. These IPs bypass all checks — useful for your own address during testing. You can also add IPs directly from the Activity Log.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email Allowlist', 'dss-spam-shield' ); ?></th>
				<td>
					<textarea name="dss_settings[whitelist_emails]" rows="4" class="large-text"><?php echo esc_textarea( $settings['whitelist_emails'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One email address per line. These addresses bypass all checks. You can also add emails directly from the Activity Log using the Allow Email button.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Reverse Proxy / Cloudflare', 'dss-spam-shield' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dss_settings[trust_proxy_headers]" value="1"
							<?php checked( $settings['trust_proxy_headers'], 1 ); ?> />
						<?php esc_html_e( 'Trust X-Forwarded-For and CF-Connecting-IP headers', 'dss-spam-shield' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable only if your site is behind Cloudflare, a load balancer, or a reverse proxy. On a direct-connect server this setting lets visitors spoof their IP address.', 'dss-spam-shield' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button(); ?>
	</form>
</div>
