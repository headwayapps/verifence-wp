<?php
/**
 * Protects the registration form using the Verifence Shield widget and the
 * Verifence Email Scan API.
 *
 * The Shield widget JS is loaded on wp-login.php via dss_enqueue_login_shield()
 * in the main plugin file (shared with login). This class adds the form fields
 * and handles server-side verification.
 *
 * Email scan checks:
 *  - disposable / throwaway addresses
 *  - IP or country blocked by your Verifence email rules
 *  - typo detection with a "did you mean?" suggestion
 *
 * Shield also runs your Verifence email rules on the submitted address when the
 * email field is passed to siteverify — that runs before the explicit scan call
 * and catches rule-based blocks without consuming scan credits.
 */

defined( 'ABSPATH' ) || exit;

class DSS_Registration_Protection {

	public function __construct() {
		if ( ! dss_get_setting( 'protect_register' ) ) {
			return;
		}
		add_action( 'register_form', array( $this, 'add_fields' ) );
		add_filter( 'registration_errors', array( $this, 'validate' ), 10, 3 );
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
			<label for="dss_register_hp"><?php esc_html_e( 'Leave this field empty', 'dss-spam-shield' ); ?></label>
			<input type="text" id="dss_register_hp" name="dss_register_hp" value="" tabindex="-1" autocomplete="off" />
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * @param WP_Error $errors    Existing registration errors.
	 * @param string   $username  Sanitised username.
	 * @param string   $user_email Submitted email address.
	 * @return WP_Error
	 */
	public function validate( $errors, $username, $user_email ) {
		$s  = dss_get_settings();
		$ip = dss_get_client_ip();

		if ( dss_is_ip_whitelisted( $ip ) || dss_is_email_whitelisted( $user_email ) ) {
			return $errors;
		}

		if ( ! empty( $s['check_block_list'] ) && ! empty( $s['api_key'] ) && ! $errors->has_errors() ) {
			$result = DSS_API_Client::block_list_check( $s['api_key'], $user_email, $ip );

			if ( is_wp_error( $result ) ) {
				dss_log_event( 'register_blocked', $ip, $username, $user_email, 'Block List API error: ' . $result->get_error_message() );
				if ( 'block' === $s['api_fallback'] ) {
					$errors->add(
						'dss_block_list_api',
						'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
						esc_html__( 'Security check temporarily unavailable. Please try again shortly.', 'dss-spam-shield' )
					);
					return $errors;
				}
			} elseif ( ! empty( $result['blocked'] ) ) {
				dss_log_event( 'register_spam', $ip, $username, $user_email, 'Matched Verifence Block List' );
				$errors->add(
					'dss_block_list',
					'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
					esc_html__( 'Registration blocked. Please try again.', 'dss-spam-shield' )
				);
				return $errors;
			}
		}

		// Shield verification.
		if ( ! empty( $s['shield_secret'] ) ) {
			// Treat absent token identically to empty — direct POST without the widget
			// running. Apply api_fallback rather than silently passing through.
			$token    = dss_get_shield_token_from_request();
			$honeypot = isset( $_POST['dss_register_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['dss_register_hp'] ) ) : '';

			if ( empty( $token ) ) {
				if ( 'block' === $s['api_fallback'] ) {
					$errors->add(
						'dss_no_token',
						'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
						esc_html__( 'Bot protection could not be loaded. Please enable JavaScript and try again.', 'dss-spam-shield' )
					);
					return $errors;
				}
			} else {
				// Pass the email only when email-rule blocking is enabled.
				$extras = array( 'honeypot' => $honeypot );
				if ( ! empty( $s['register_block_email_rules'] ) ) {
					$extras['email'] = $user_email;
				}
				$result = DSS_API_Client::shield_verify(
					$s['shield_secret'],
					$token,
					$extras
				);

				if ( is_wp_error( $result ) ) {
					dss_log_event( 'register_blocked', $ip, $username, $user_email, 'API error: ' . $result->get_error_message() );
					if ( 'block' === $s['api_fallback'] ) {
						$errors->add(
							'dss_api',
							'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
							esc_html__( 'Security check temporarily unavailable. Please try again shortly.', 'dss-spam-shield' )
						);
						return $errors;
					}
				} elseif ( empty( $result['success'] ) ) {
					dss_log_event( 'register_spam', $ip, $username, $user_email, 'Shield rejected' );
					$errors->add(
						'dss_shield',
						'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
						esc_html__( 'Registration blocked. Please try again.', 'dss-spam-shield' )
					);
					return $errors;
				}
			}
		}

		// Deep email scan via /api/scan/email — only when no prior errors.
		if ( $s['register_scan_email'] && ! empty( $s['api_key'] ) && ! $errors->has_errors() ) {
			$result = DSS_API_Client::scan_email( $s['api_key'], $user_email, $ip );

			if ( ! is_wp_error( $result ) && isset( $result['ok'] ) ) {
				if ( ! empty( $s['register_block_disposable'] ) && ! empty( $result['disposable'] ) ) {
					dss_log_event( 'register_spam', $ip, $username, $user_email, 'Disposable email' );
					$errors->add(
						'dss_disposable',
						'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
						esc_html__( 'Disposable or temporary email addresses are not allowed.', 'dss-spam-shield' )
					);
				} elseif ( ! empty( $result['email_blocked'] ) || ! empty( $result['ip_blocked'] ) || ! empty( $result['country_blocked'] ) ) {
					dss_log_event( 'register_spam', $ip, $username, $user_email, 'Blocked by email rules' );
					$errors->add(
						'dss_geo_blocked',
						'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
						esc_html__( 'Registration is not available for this email or location.', 'dss-spam-shield' )
					);
				} elseif ( ! empty( $result['did_you_mean'] ) ) {
					$errors->add(
						'dss_typo',
						'<strong>' . esc_html__( 'Error:', 'dss-spam-shield' ) . '</strong> ' .
						sprintf(
							/* translators: %s: suggested email correction */
							esc_html__( 'Did you mean %s?', 'dss-spam-shield' ),
							esc_html( $result['did_you_mean'] )
						)
					);
				}
			}
		}

		return $errors;
	}
}
