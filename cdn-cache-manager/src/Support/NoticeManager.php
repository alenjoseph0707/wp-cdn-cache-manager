<?php

namespace CDNCacheManager\Support;

/**
 * Handles transient-based admin notices.
 */
final class NoticeManager {
	/**
	 * Singleton instance.
	 *
	 * @var NoticeManager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return NoticeManager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Add a notice.
	 *
	 * @param string $message Message.
	 * @param string $type    success|error|info.
	 * @return void
	 */
	public function add( $message, $type = 'success' ) {
		$key     = $this->transient_key();
		$notices = get_transient( $key );

		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		set_transient( $key, $notices, 90 );
	}

	/**
	 * Render notices and clear transient.
	 *
	 * @return void
	 */
	public function render() {
		$key     = $this->transient_key();
		$notices = get_transient( $key );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		delete_transient( $key );

		foreach ( $notices as $notice ) {
			$type    = ! empty( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'info';
			$message = ! empty( $notice['message'] ) ? $notice['message'] : '';

			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Build user-scoped transient key.
	 *
	 * @return string
	 */
	private function transient_key() {
		$user_id = get_current_user_id();
		return 'ccm_notices_' . (int) $user_id;
	}
}
