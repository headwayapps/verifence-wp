<?php
/**
 * Plugin Name: Verifence
 * Description: Blocks disposable or throwaway email addresses during WordPress registration and commenting.
 * Version: 1.0.0
 * Author: verifence
 * License: GPL-2.0-or-later
 * Text Domain: verifence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Verifence_Plugin {
	const OPTION_API_KEY = 'verifence_api_key';
	const API_URL = 'https://app.docscan.dev/api/scan/email';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		add_filter( 'preprocess_comment', array( __CLASS__, 'validate_comment_email' ) );
		add_filter( 'registration_errors', array( __CLASS__, 'validate_registration_email' ), 10, 3 );
	}

	public static function add_settings_page() {
		add_options_page(
			esc_html__( 'Verifence', 'verifence' ),
			esc_html__( 'Verifence', 'verifence' ),
			'manage_options',
			'verifence',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'verifence_settings',
			self::OPTION_API_KEY,
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);

		add_settings_section(
			'verifence_main',
			esc_html__( 'API Settings', 'verifence' ),
			'__return_false',
			'verifence'
		);

		add_settings_field(
			'verifence_api_key',
			esc_html__( 'API Key', 'verifence' ),
			array( __CLASS__, 'render_api_key_field' ),
			'verifence',
			'verifence_main'
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Verifence', 'verifence' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'verifence_settings' );
				do_settings_sections( 'verifence' );
				submit_button( esc_html__( 'Save Changes', 'verifence' ) );
				?>
			</form>
		</div>
		<?php
	}

	public static function render_api_key_field() {
		$value = get_option( self::OPTION_API_KEY, '' );
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_API_KEY ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<?php
	}

	private static function get_api_key() {
		$api_key = get_option( self::OPTION_API_KEY, '' );
		return is_string( $api_key ) ? trim( $api_key ) : '';
	}

	private static function is_email_allowed( $email ) {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return true;
		}

		$request_body = wp_json_encode(
			array(
				'email' => $email,
			)
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 10,
				'headers' => array(
					'API-KEY' => $api_key,
					'Content-Type' => 'application/json',
				),
				'body' => $request_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return true;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return true;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! array_key_exists( 'ok', $data ) ) {
			return true;
		}

		if ( true !== $data['ok'] ) {
			return false;
		}

		if ( ! array_key_exists( 'disposable', $data ) ) {
			return true;
		}

		return (bool) ! $data['disposable'];
	}

	public static function validate_comment_email( $commentdata ) {
		if ( empty( $commentdata['comment_author_email'] ) ) {
			return $commentdata;
		}

		$email = sanitize_email( $commentdata['comment_author_email'] );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return $commentdata;
		}

		$allowed = self::is_email_allowed( $email );
		if ( ! $allowed ) {
			wp_die(
				esc_html__( 'Error: Please enter a valid email address.', 'verifence' ),
				esc_html__( 'Comment Submission Failure', 'verifence' ),
				array( 'back_link' => true )
			);
		}

		return $commentdata;
	}

	public static function validate_registration_email( $errors, $sanitized_user_login, $user_email ) {
		if ( empty( $user_email ) ) {
			return $errors;
		}

		$email = sanitize_email( $user_email );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return $errors;
		}

		$allowed = self::is_email_allowed( $email );
		if ( ! $allowed ) {
			$errors->add(
				'verifence_invalid_email',
				esc_html__( 'Error: Please enter a valid email address.', 'verifence' )
			);
		}

		return $errors;
	}
}

Verifence_Plugin::init();
