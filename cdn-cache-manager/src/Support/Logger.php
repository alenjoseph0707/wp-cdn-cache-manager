<?php

namespace CDNCacheManager\Support;

/**
 * Debug logger wrapper.
 */
final class Logger {
	/**
	 * Singleton instance.
	 *
	 * @var Logger|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Logger
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Log a message when WP_DEBUG is enabled.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( (string) $message );
		}
	}
}
