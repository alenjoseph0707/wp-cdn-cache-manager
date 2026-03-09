<?php

namespace CDNCacheManager\Support;

use CDNCacheManager\Api\ImpervaClient;

/**
 * Settings persistence and sanitization.
 */
final class SettingsRepository {
	/**
	 * Settings option key.
	 */
	public const OPTION_NAME = 'cdn_cache_manager_settings';

	/**
	 * Singleton instance.
	 *
	 * @var SettingsRepository|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return SettingsRepository
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'provider'               => 'imperva',
			'auto_purge_enabled'     => 1,
			'imperva_api_id'         => '',
			'imperva_api_key'        => '',
			'imperva_site_id'        => '',
			'cloudflare_zone_id'     => '',
			'cloudflare_api_token'   => '',
		);
	}

	/**
	 * Get settings with defaults merged.
	 *
	 * @return array<string,mixed>
	 */
	public function get_all() {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$settings = $this->get_all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Whether auto purge is enabled.
	 *
	 * @return bool
	 */
	public function is_auto_purge_enabled() {
		return ! empty( $this->get( 'auto_purge_enabled' ) );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'cdn_cache_manager_group',
			self::OPTION_NAME,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		$output = array(
			'provider'               => isset( $input['provider'] ) ? sanitize_text_field( $input['provider'] ) : 'imperva',
			'auto_purge_enabled'     => ! empty( $input['auto_purge_enabled'] ) ? 1 : 0,
			'imperva_api_id'         => isset( $input['imperva_api_id'] ) ? sanitize_text_field( $input['imperva_api_id'] ) : '',
			'imperva_api_key'        => isset( $input['imperva_api_key'] ) ? sanitize_text_field( $input['imperva_api_key'] ) : '',
			'imperva_site_id'        => isset( $input['imperva_site_id'] ) ? sanitize_text_field( $input['imperva_site_id'] ) : '',
			'cloudflare_zone_id'     => isset( $input['cloudflare_zone_id'] ) ? sanitize_text_field( $input['cloudflare_zone_id'] ) : '',
			'cloudflare_api_token'   => isset( $input['cloudflare_api_token'] ) ? sanitize_text_field( $input['cloudflare_api_token'] ) : '',
		);

		if ( ! in_array( $output['provider'], array( 'imperva', 'cloudflare' ), true ) ) {
			$output['provider'] = 'imperva';
		}

		if ( 'imperva' === $output['provider'] ) {
			$has_any_imperva_value = ! empty( $output['imperva_api_id'] ) || ! empty( $output['imperva_api_key'] ) || ! empty( $output['imperva_site_id'] );

			if ( $has_any_imperva_value ) {
					if ( empty( $output['imperva_api_id'] ) || empty( $output['imperva_api_key'] ) || empty( $output['imperva_site_id'] ) ) {
						$this->add_unique_settings_error(
							'ccm_missing_credentials',
							__( 'Imperva settings not saved: API ID, API Key, and App ID are all required.', 'cdn-cache-manager' )
						);
						$this->prevent_success_notice();

						$previous = $this->get_all();
						$previous['provider']           = $output['provider'];
						$previous['auto_purge_enabled'] = $output['auto_purge_enabled'];
						return $previous;
					}

				$validator = new ImpervaClient( $this, Logger::instance(), $output );
				$result    = $validator->validate_credentials();

					if ( is_wp_error( $result ) ) {
						$this->add_unique_settings_error(
							'ccm_invalid_credentials',
							sprintf(
								/* translators: %s: error message */
								__( 'Imperva settings not saved: %s', 'cdn-cache-manager' ),
								$result->get_error_message()
							)
						);
						$this->prevent_success_notice();

						$previous = $this->get_all();
						$previous['provider']           = $output['provider'];
						$previous['auto_purge_enabled'] = $output['auto_purge_enabled'];
						return $previous;
					}
			}
		}

		return $output;
	}

	/**
	 * Add settings error once per code to avoid duplicate notices.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return void
	 */
	private function add_unique_settings_error( $code, $message ) {
		$existing = get_settings_errors( self::OPTION_NAME );
		if ( is_array( $existing ) ) {
			foreach ( $existing as $error ) {
				if ( isset( $error['code'] ) && $code === $error['code'] ) {
					return;
				}
			}
		}

		add_settings_error( self::OPTION_NAME, $code, $message, 'error' );
	}

	/**
	 * Hide default "Settings saved." notice for failed validation saves.
	 *
	 * @return void
	 */
	private function prevent_success_notice() {
		add_filter(
			'redirect_post_location',
			static function ( $location ) {
				return remove_query_arg( 'settings-updated', $location );
			},
			99
		);
	}
}
