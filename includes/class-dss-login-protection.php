<?php
/**
 * Protects the login form using the Verifence Shield widget.
 *
 * The Shield widget JS is loaded on wp-login.php via dss_enqueue_login_shield()
 * in the main plugin file (shared with registration). This class adds the
 * form fields and handles server-side verification.
 *
 * Local rate-limiting is kept as a defence-in-depth layer for brute-force
 * attacks regardless of whether Shield credentials are configured.
 */

defined( 'ABSPATH' ) || exit;

class DSS_Login_Protection {

	public function __construct() {
		if ( ! dss_get_setting( 'protect_login' ) ) {
			return;
		}
		add_action( 'login_form', array( $this, 'add_fields' ) );
		add_filter( 'authenticate', array( $this, 'check_before_auth' ), 1, 3 );
		add_action( 'wp_login_failed', array( $this, 'on_failed' ) );
		add_action( 'wp_login', array( $this, 'on_success' ), 10, 1 );
	}

	// -------------------------------------------------------------------------
	// Form fields
	// -------------------------------------------------------------------------

	public function add_fields() {
		$site_key = dss_get_setting( 'shield_site_key' );
		?>
		<?php if ( ! empty( $site_key ) ) : ?>
		<div class="shield-widget" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-execution="execute" style="margin:6px 0 4px;"></div>
		<?php endif; ?>
		<div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
			<label for="dss_login_hp"><?php esc_html_e( 'Leave this field empty', 'dss-spam-shield' ); ?></label>
			<input type="text" id="dss_login_hp" name="dss_login_hp" value="" tabindex="-1" autocomplete="off" />
		</div>
		<?php
		wp_nonce_field( 'dss_login', 'dss_login_nonce' );
	}

	// -------------------------------------------------------------------------
	// Pre-authentication check
	// -------------------------------------------------------------------------

	/**
	 * Runs at priority 1 on the `authenticate` filter — before WP checks credentials.
	 * Returns WP_Error to short-circuit the entire auth chain.
	 */
	public function check_before_auth( $user, $username, $password ) {
		// Skip programmatic / API logins that don't come from the form.
		if ( empty( $username ) ) {
			return $user;
		}

		$s  = dss_get_settings();
		$ip = dss_get_client_ip();

		if ( dss_is_ip_whitelisted( $ip ) ) {
			return $user;
		}

		// Rate-limit and Shield checks apply to web-form logins only.
		// REST API, XML-RPC, and Application Password requests don't set wp-submit
		// and are excluded to avoid locking out programmatic clients.
		$is_form = isset( $_POST['wp-submit'] ) ||
		           ! ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
		               ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) );

		if ( $s['login_rate_limit'] && $is_form && DSS_Rate_Limiter::is_over_limit( 'login', $ip, (int) $s['login_max_attempts'] ) ) {
			$minutes = (int) ceil( (int) $s['login_lockout_duration'] / MINUTE_IN_SECONDS );
			dss_log_event( 'login_blocked', $ip, $username, '', 'IP rate-locked' );
			return new WP_Error(
				'dss_locked',
				sprintf(
					/* translators: %d: lock duration in minutes */
					esc_html__( 'Too many failed login attempts. Please try again in %d minutes.', 'dss-spam-shield' ),
					$minutes
				)
			);
		}

		// Shield + nonce — only for form submissions (wp-submit is present).
		if ( isset( $_POST['wp-submit'] ) ) {
			// Nonce verified for every form submission — not gated on any other field.
			$nonce = isset( $_POST['dss_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dss_login_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'dss_login' ) ) {
				dss_log_event( 'login_spam', $ip, $username, '', dss_format_log_details( 'Nonce invalid', dss_get_request_log_context() ) );
				return new WP_Error( 'dss_nonce', esc_html__( 'Security check failed. Please refresh the page and try again.', 'dss-spam-shield' ) );
			}

			if ( ! empty( $s['shield_secret'] ) ) {
				$token    = dss_get_shield_token_from_request();
				$honeypot = isset( $_POST['dss_login_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['dss_login_hp'] ) ) : '';

				if ( empty( $token ) ) {
					if ( 'block' === $s['api_fallback'] ) {
						return new WP_Error( 'dss_no_token', esc_html__( 'Bot protection could not be loaded. Please enable JavaScript and try again.', 'dss-spam-shield' ) );
					}
				} else {
					$result = DSS_API_Client::shield_verify(
						$s['shield_secret'],
						$token,
						array( 'honeypot' => $honeypot )
					);

					if ( is_wp_error( $result ) ) {
						dss_log_event( 'login_blocked', $ip, $username, '', 'API error: ' . $result->get_error_message() );
						if ( 'block' === $s['api_fallback'] ) {
							return new WP_Error( 'dss_api', esc_html__( 'Security check temporarily unavailable. Please try again shortly.', 'dss-spam-shield' ) );
						}
					} elseif ( empty( $result['success'] ) ) {
						dss_log_event( 'login_spam', $ip, $username, '', 'Shield rejected' );
						return new WP_Error( 'dss_shield', esc_html__( 'Login blocked. Please refresh the page and try again.', 'dss-spam-shield' ) );
					}
				}
			}
		}

		return $user;
	}

	// -------------------------------------------------------------------------
	// Track failures / successes
	// -------------------------------------------------------------------------

	public function on_failed( $username ) {
		$s  = dss_get_settings();
		$ip = dss_get_client_ip();

		if ( ! $s['login_rate_limit'] || dss_is_ip_whitelisted( $ip ) ) {
			return;
		}

		// Don't count REST / XML-RPC failures against the web-form lockout counter.
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
		     ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return;
		}

		$count = DSS_Rate_Limiter::increment( 'login', $ip, (int) $s['login_lockout_duration'] );
		dss_log_event( 'login_failed', $ip, $username, '', 'Attempt ' . $count . ' of ' . $s['login_max_attempts'] );

		if ( $s['login_notify_admin'] && $count === (int) $s['login_max_attempts'] ) {
			$this->notify_admin( $ip, $username, $count );
		}
	}

	public function on_success( $user_login ) {
		DSS_Rate_Limiter::reset( 'login', dss_get_client_ip() );
	}

	private function notify_admin( $ip, $username, $count ) {
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Login lockout triggered', 'dss-spam-shield' ),
			get_bloginfo( 'name' )
		);
		$body = sprintf(
			/* translators: 1: IP  2: attempt count  3: username */
			__( "Verifence Spam Shield locked out IP %1\$s after %2\$d failed login attempts.\n\nLast username attempted: %3\$s", 'dss-spam-shield' ),
			$ip,
			$count,
			$username
		);
		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}
}
