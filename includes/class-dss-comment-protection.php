<?php
/**
 * Protects the comment form using the Verifence Shield widget and, optionally,
 * the Verifence URL Scan API for links found in comment bodies.
 *
 * Flow:
 *  1. Shield widget JS enqueued on singular posts/pages with open comments.
 *  2. The widget injects a hidden shield-token field; plugin adds honeypot.
 *  3. Widget runs proof-of-work + behavioural analysis in the browser.
 *  4. On submit, server calls /api/shield/siteverify — passes token, honeypot
 *     value, and comment text (for spam scoring).
 *  5. Optionally every URL in the comment is checked via /api/scan/url.
 */

defined( 'ABSPATH' ) || exit;

class DSS_Comment_Protection {

	public function __construct() {
		if ( ! dss_get_setting( 'protect_comments' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget' ) );
		add_action( 'comment_form', array( $this, 'add_fields' ) );
		add_filter( 'preprocess_comment', array( $this, 'validate' ) );
	}

	// -------------------------------------------------------------------------
	// Widget + form fields
	// -------------------------------------------------------------------------

	public function enqueue_widget() {
		if ( ! is_singular() || ! comments_open() ) {
			return;
		}
		$site_key = dss_get_setting( 'shield_site_key' );
		if ( empty( $site_key ) ) {
			return;
		}
		wp_enqueue_script( 'vss-shield', 'https://shield.verifence.io/shield/widget.js', array(), DSS_VERSION, true );
		wp_add_inline_script(
			'vss-shield',
			'window.SHIELD_CHALLENGE_URL="https://shield.verifence.io/shield/challenge";window.SHIELD_VERIFY_URL="https://shield.verifence.io/shield/verify";',
			'before'
		);
	}

	public function add_fields() {
		$site_key = dss_get_setting( 'shield_site_key' );
		?>
		<?php if ( ! empty( $site_key ) ) : ?>
		<div class="shield-widget" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-execution="execute" style="margin:6px 0 4px;"></div>
		<?php endif; ?>
		<div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
			<label for="dss_comment_hp"><?php esc_html_e( 'Leave this field empty', 'dss-spam-shield' ); ?></label>
			<input type="text" id="dss_comment_hp" name="dss_comment_hp" value="" tabindex="-1" autocomplete="off" />
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	public function validate( $comment_data ) {
		$s  = dss_get_settings();
		$ip = dss_get_client_ip();

		$comment_email = isset( $comment_data['comment_author_email'] ) ? sanitize_email( $comment_data['comment_author_email'] ) : '';
		if ( current_user_can( 'moderate_comments' ) || dss_is_ip_whitelisted( $ip ) || dss_is_email_whitelisted( $comment_email ) ) {
			return $comment_data;
		}

		if ( ! empty( $s['check_block_list'] ) && ! empty( $s['api_key'] ) ) {
			$email  = isset( $comment_data['comment_author_email'] ) ? sanitize_email( $comment_data['comment_author_email'] ) : '';
			$result = DSS_API_Client::block_list_check( $s['api_key'], $email, $ip );

			if ( is_wp_error( $result ) ) {
				dss_log_event( 'comment_blocked', $ip, '', $email, 'Block List API error: ' . $result->get_error_message() );
				if ( 'block' === $s['api_fallback'] ) {
					$this->die_spam( __( 'Spam check temporarily unavailable. Please try again shortly.', 'dss-spam-shield' ), 503 );
				}
			} elseif ( ! empty( $result['blocked'] ) ) {
				dss_log_event( 'comment_spam', $ip, '', $email, 'Matched Verifence Block List' );
				$this->die_spam( __( 'Your comment could not be submitted. Please try again.', 'dss-spam-shield' ) );
			}
		}

		if ( ! empty( $s['shield_secret'] ) ) {
			// Treat absent token identically to empty token — both mean the widget
			// didn't run (direct POST, JS blocked). This is not an API outage, so
			// never apply api_fallback here.
			$token    = dss_get_shield_token_from_request();
			$honeypot = isset( $_POST['dss_comment_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['dss_comment_hp'] ) ) : '';

			if ( empty( $token ) ) {
				$email = isset( $comment_data['comment_author_email'] ) ? sanitize_email( $comment_data['comment_author_email'] ) : '';
				dss_log_event( 'comment_spam', $ip, '', $email, dss_format_log_details( 'Missing Shield token', dss_get_request_log_context() ) );
				$this->die_spam( __( 'Bot protection could not be loaded. Please enable JavaScript and try again.', 'dss-spam-shield' ), 403 );
			} else {
				$extras = array( 'honeypot' => $honeypot );
				if ( ! empty( $comment_data['comment_content'] ) ) {
					$extras['text'] = mb_substr( $comment_data['comment_content'], 0, 1000 );
				}

				$result = DSS_API_Client::shield_verify( $s['shield_secret'], $token, $extras );

				if ( is_wp_error( $result ) ) {
					dss_log_event( 'comment_blocked', $ip, '', '', 'API error: ' . $result->get_error_message() );
					if ( 'block' === $s['api_fallback'] ) {
						$this->die_spam( __( 'Spam check temporarily unavailable. Please try again shortly.', 'dss-spam-shield' ), 503 );
					}
					// api_fallback = allow: fall through so URL scan still runs below.
				} else {
					if ( empty( $result['success'] ) ) {
						dss_log_event( 'comment_spam', $ip, '', '', 'Shield rejected: ' . ( $result['error'] ?? 'bot signal' ) );
						$this->die_spam( __( 'Your comment could not be submitted. Please try again.', 'dss-spam-shield' ) );
					}

					if ( isset( $result['score'] ) && (int) $result['score'] < (int) $s['comment_min_score'] ) {
						dss_log_event( 'comment_spam', $ip, '', '', 'Low Shield score: ' . $result['score'] );
						$this->die_spam( __( 'Your comment was flagged as suspicious.', 'dss-spam-shield' ) );
					}

					$spam_threshold = isset( $s['comment_spam_score'] ) ? (int) $s['comment_spam_score'] : 60;
					if ( $spam_threshold > 0 && isset( $result['spam_score'] ) && (int) $result['spam_score'] >= $spam_threshold ) {
						$signals = isset( $result['signals'] ) && is_array( $result['signals'] ) ? implode( ', ', array_map( 'sanitize_text_field', $result['signals'] ) ) : '';
						$detail  = 'Comment spam score: ' . (int) $result['spam_score'];
						if ( '' !== $signals ) {
							$detail .= ' (' . $signals . ')';
						}
						dss_log_event( 'comment_spam', $ip, '', '', $detail );
						$this->die_spam( __( 'Your comment was flagged as spam.', 'dss-spam-shield' ) );
					}
				}
			}
		}

		// URL scan runs independently of Shield — not skipped by Shield errors/degraded.
		if ( $s['comment_scan_urls'] && ! empty( $s['api_key'] ) ) {
			$this->check_comment_urls( $comment_data['comment_content'], $ip, $s['api_key'] );
		}

		return $comment_data;
	}

	// -------------------------------------------------------------------------
	// URL scanning
	// -------------------------------------------------------------------------

	private function check_comment_urls( $content, $ip, $api_key ) {
		preg_match_all( '/https?:\/\/[^\s<>"\']+/i', $content, $matches );
		if ( empty( $matches[0] ) ) {
			return;
		}
		foreach ( array_unique( $matches[0] ) as $url ) {
			// Strip trailing punctuation that prose wraps around URLs but is not
			// part of the URL itself (e.g. "See https://evil.com/x." or "(https://evil.com)").
			$url = rtrim( $url, '.,;:!?)>\]}\'"' );
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				continue;
			}
			$result = DSS_API_Client::scan_url( $api_key, $url );
			if ( ! is_wp_error( $result ) && isset( $result['ok'] ) && false === $result['ok'] ) {
				dss_log_event( 'comment_spam', $ip, '', '', 'Malicious URL: ' . esc_url_raw( $url ) );
				$this->die_spam( __( 'Your comment contains a link that has been flagged as a security threat.', 'dss-spam-shield' ) );
			}
		}
	}

	private function die_spam( $message, $status = 403 ) {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Comment Blocked', 'dss-spam-shield' ),
			array( 'response' => $status, 'back_link' => true )
		);
	}
}
