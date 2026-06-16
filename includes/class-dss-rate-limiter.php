<?php
/**
 * IP-based rate limiter using WordPress transients.
 */

defined( 'ABSPATH' ) || exit;

class DSS_Rate_Limiter {

	private static function key( $action, $ip ) {
		return 'dss_rl_' . md5( $action . $ip );
	}

	/**
	 * Return current attempt count without modifying it.
	 */
	public static function get_count( $action, $ip ) {
		$data = get_transient( self::key( $action, $ip ) );
		return $data ? (int) $data['count'] : 0;
	}

	/**
	 * Return true if the IP has already reached or exceeded $max attempts.
	 * Does NOT increment the counter.
	 */
	public static function is_over_limit( $action, $ip, $max ) {
		return self::get_count( $action, $ip ) >= (int) $max;
	}

	/**
	 * Increment the attempt counter and return the new count.
	 * The transient expires after $window seconds from the first attempt.
	 */
	public static function increment( $action, $ip, $window ) {
		$key  = self::key( $action, $ip );
		$data = get_transient( $key );

		if ( false === $data || ( time() - (int) $data['first'] ) > (int) $window ) {
			$data = array( 'count' => 0, 'first' => time() );
		}

		$data['count']++;
		set_transient( $key, $data, (int) $window );

		return $data['count'];
	}

	/**
	 * Reset the counter (e.g., on successful login).
	 */
	public static function reset( $action, $ip ) {
		delete_transient( self::key( $action, $ip ) );
	}
}
