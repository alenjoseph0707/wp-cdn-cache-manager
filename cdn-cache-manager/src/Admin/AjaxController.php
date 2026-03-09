<?php

namespace CDNCacheManager\Admin;

use CDNCacheManager\Support\PurgeService;

/**
 * AJAX handlers for manual purge tools.
 */
final class AjaxController {
	/**
	 * Purge service.
	 *
	 * @var PurgeService
	 */
	private $purger;

	/**
	 * Constructor.
	 *
	 * @param PurgeService  $purger  Purger.
	 */
	public function __construct( PurgeService $purger ) {
		$this->purger  = $purger;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_cdn_cache_manager_purge_all', array( $this, 'purge_all' ) );
		add_action( 'wp_ajax_cdn_cache_manager_purge_url', array( $this, 'purge_url' ) );
	}

	/**
	 * Purge all cache handler.
	 *
	 * @return void
	 */
	public function purge_all() {
		$this->authorize();

		$result = $this->purger->purge_all( 'manual:ajax', true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Cache purged successfully for all content.', 'cdn-cache-manager' ),
			)
		);
	}

	/**
	 * Purge URL handler.
	 *
	 * @return void
	 */
	public function purge_url() {
		$this->authorize();

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		$result = $this->purger->purge_url( $url, 'manual:ajax', true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Cache purged successfully for URL.', 'cdn-cache-manager' ),
			)
		);
	}

	/**
	 * Shared authorization and nonce check.
	 *
	 * @return void
	 */
	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cdn-cache-manager' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cdn_cache_manager_ajax_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'cdn-cache-manager' ) ), 403 );
		}
	}
}
