<?php
/**
 * Thin HTTP client for the Verifence platform API.
 *
 * Uses wp_remote_post() — the WordPress-standard HTTP layer — so proxy
 * settings, SSL certificates, and timeouts are all handled by WordPress core.
 *
 * Endpoints used:
 *  POST /api/shield/siteverify  — bot/spam token verification (no API-KEY needed)
 *  POST /api/scan/email         — disposable / rule-based email check
 *  POST /api/scan/url           — URL threat check
 */

defined( 'ABSPATH' ) || exit;

class DSS_API_Client {

	const BASE_URL = 'https://app.verifence.io';
	const TIMEOUT  = 5; // seconds — keep short; a slow API call should not stall a form submission

	// -------------------------------------------------------------------------
	// Shield
	// -------------------------------------------------------------------------

	/**
	 * Verify a Shield token submitted with a form.
	 *
	 * Does NOT require an API-KEY header — authenticates with the site's secret.
	 *
	 * @param string $secret   Shield site secret key (sec_…)
	 * @param string $token    Value of the hidden shield-token field
	 * @param array  $extras   Optional extra fields: email, text, honeypot
	 * @return array|WP_Error  Decoded response body, or WP_Error on network/parse failure
	 */
	public static function shield_verify( $secret, $token, array $extras = [] ) {
		$body = array_merge(
			array( 'secret' => $secret, 'token' => $token ),
			$extras
		);
		return self::post( '/api/shield/siteverify', $body );
	}

	// -------------------------------------------------------------------------
	// Email scan
	// -------------------------------------------------------------------------

	/**
	 * Validate an email address against the Verifence email-scan endpoint.
	 *
	 * Requires a platform API key.
	 *
	 * @param string $api_key  Platform API key
	 * @param string $email
	 * @param string $ip       Optional — enables IP/country rule checks
	 * @return array|WP_Error
	 */
	public static function scan_email( $api_key, $email, $ip = '' ) {
		$body = array( 'email' => $email );
		if ( ! empty( $ip ) ) {
			$body['ip'] = $ip;
		}
		return self::post( '/api/scan/email', $body, $api_key );
	}

	// -------------------------------------------------------------------------
	// URL scan
	// -------------------------------------------------------------------------

	/**
	 * Check a URL against the Verifence threat database and Google Web Risk.
	 *
	 * Requires a platform API key.
	 *
	 * @param string $api_key
	 * @param string $url
	 * @return array|WP_Error
	 */
	public static function scan_url( $api_key, $url ) {
		return self::post( '/api/scan/url', array( 'url' => $url ), $api_key );
	}

	// -------------------------------------------------------------------------
	// Block List
	// -------------------------------------------------------------------------

	/**
	 * Check an email/IP pair against the tenant's Verifence Block List.
	 *
	 * Requires a platform API key.
	 *
	 * @param string $api_key
	 * @param string $email
	 * @param string $ip
	 * @return array|WP_Error
	 */
	public static function block_list_check( $api_key, $email = '', $ip = '' ) {
		$body = array();
		if ( ! empty( $email ) ) {
			$body['email'] = $email;
		}
		if ( ! empty( $ip ) ) {
			$body['ip'] = $ip;
		}
		return self::post( '/api/block-list/check', $body, $api_key );
	}

	// -------------------------------------------------------------------------
	// False Positive Feedback
	// -------------------------------------------------------------------------

	/**
	 * Report a false positive block to the Verifence platform.
	 *
	 * Fire-and-forget: the plugin should not fail the user flow if this call
	 * errors. Call it after the local allowlist has already been updated.
	 *
	 * @param string $api_key
	 * @param string $type       ip | email | email_domain | ip_cidr
	 * @param string $value      The value that was incorrectly blocked
	 * @param string $event_type Optional — the plugin event type for context
	 * @return array|WP_Error
	 */
	public static function report_false_positive( $api_key, $type, $value, $event_type = '' ) {
		$body = array( 'type' => $type, 'value' => $value );
		if ( ! empty( $event_type ) ) {
			$body['event_type'] = $event_type;
		}
		return self::post( '/api/block-list/false-positive', $body, $api_key );
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * @param string      $path     API path (e.g. '/api/shield/siteverify')
	 * @param array       $body     Request payload — will be JSON-encoded
	 * @param string|null $api_key  If provided, sent as API-KEY header
	 * @return array|WP_Error
	 */
	private static function post( $path, array $body, $api_key = null ) {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $api_key ) ) {
			$headers['API-KEY'] = $api_key;
		}

		$response = wp_remote_post(
			self::BASE_URL . $path,
			array(
				'body'    => wp_json_encode( $body ),
				'headers' => $headers,
				'timeout' => self::TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code || ( $code >= 500 && $code < 600 ) ) {
			return new WP_Error(
				'dss_api_http',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Verifence API returned HTTP %d.', 'dss-spam-shield' ), $code ),
				$code
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'dss_api_parse',
				__( 'Verifence API returned an unexpected response.', 'dss-spam-shield' ),
				wp_remote_retrieve_response_code( $response )
			);
		}

		return $data;
	}
}
