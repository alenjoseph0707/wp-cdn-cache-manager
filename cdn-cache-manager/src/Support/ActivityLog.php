<?php

namespace CDNCacheManager\Support;

/**
 * Stores recent purge activity.
 */
final class ActivityLog {
	/**
	 * Option key for activity log.
	 */
	public const OPTION_NAME = 'cdn_cache_manager_activity_log';

	/**
	 * Max entries to keep.
	 */
	private const MAX_ENTRIES = 50;

	/**
	 * Singleton instance.
	 *
	 * @var ActivityLog|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ActivityLog
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add log record.
	 *
	 * @param string $type    Type.
	 * @param string $target  Target.
	 * @param string $status  Status.
	 * @param string $message Message.
	 * @return void
	 */
	public function add( $type, $target, $status, $message ) {
		$records = $this->all();
		$user    = wp_get_current_user();

		array_unshift(
			$records,
			array(
				'time'    => current_time( 'mysql' ),
				'type'    => sanitize_text_field( $type ),
				'target'  => sanitize_text_field( $target ),
				'status'  => sanitize_text_field( $status ),
				'message' => sanitize_text_field( $message ),
				'user'    => $user instanceof \WP_User ? $user->user_login : '',
			)
		);

		$records = array_slice( $records, 0, self::MAX_ENTRIES );
		update_option( self::OPTION_NAME, $records, false );
	}

	/**
	 * Get all records.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function all() {
		$records = get_option( self::OPTION_NAME, array() );
		return is_array( $records ) ? $records : array();
	}
}
