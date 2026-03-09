<?php

namespace CDNCacheManager\Api;

use CDNCacheManager\Support\Logger;
use CDNCacheManager\Support\SettingsRepository;
use WP_Error;

/**
 * Imperva API client.
 */
final class ImpervaClient {
	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Optional runtime settings override.
	 *
	 * @var array<string,mixed>
	 */
	private $override_settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository    $settings          Settings.
	 * @param Logger                $logger            Logger.
	 * @param array<string,mixed>   $override_settings Runtime settings override.
	 */
	public function __construct( SettingsRepository $settings, Logger $logger, $override_settings = array() ) {
		$this->settings          = $settings;
		$this->logger            = $logger;
		$this->override_settings = is_array( $override_settings ) ? $override_settings : array();
	}

	/**
	 * Validate credential presence.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ! empty( $this->setting( 'imperva_api_id' ) )
			&& ! empty( $this->setting( 'imperva_api_key' ) )
			&& ! empty( $this->setting( 'imperva_site_id' ) );
	}

	/**
	 * Purge full site cache.
	 *
	 * @return true|WP_Error
	 */
	public function purge_all() {
		$site_id = (string) $this->setting( 'imperva_site_id' );

		$modern_endpoint = $this->modern_base_url() . '/sites/' . rawurlencode( $site_id ) . '/cache/purge';
		$modern_result   = $this->request( 'POST', $modern_endpoint, array( 'purge_all' => true ), 'json' );
		if ( ! is_wp_error( $modern_result ) ) {
			return true;
		}

		$legacy_payload = array(
			'site_id'   => $site_id,
			'purge_all' => 'true',
		);

		$legacy_result = $this->request_legacy( 'POST', '/sites/purge-cache', $legacy_payload );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$legacy_result = $this->request_legacy( 'POST', '/sites/cache/purge', $legacy_payload );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$this->logger->log( '[CDN Cache Manager / Imperva] All purge endpoints failed. Modern error: ' . $modern_result->get_error_message() . ' | Legacy error: ' . $legacy_result->get_error_message() );

		return $legacy_result;
	}

	/**
	 * Purge a single URL.
	 *
	 * @param string $url URL.
	 * @return true|WP_Error
	 */
	public function purge_url( $url ) {
		$site_id = (string) $this->setting( 'imperva_site_id' );
		$url     = esc_url_raw( $url );

		$modern_endpoint = $this->modern_base_url() . '/sites/' . rawurlencode( $site_id ) . '/cache/purge';
		$modern_result   = $this->request(
			'POST',
			$modern_endpoint,
			array(
				'purge_all' => false,
				'files'     => array( $url ),
			),
			'json'
		);
		if ( ! is_wp_error( $modern_result ) ) {
			return true;
		}

		$purge_pattern = $this->build_purge_pattern( $url );
		$legacy_body   = array(
			'site_id'       => $site_id,
			'purge_pattern' => $purge_pattern,
		);

		$legacy_result = $this->request_legacy( 'POST', '/sites/purge-cache', $legacy_body );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$legacy_result = $this->request_legacy( 'POST', '/sites/cache/purge', $legacy_body );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$legacy_body['purge_pattern'] = $url;
		$legacy_result                = $this->request_legacy( 'POST', '/sites/purge-cache', $legacy_body );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$legacy_result = $this->request_legacy( 'POST', '/sites/cache/purge', $legacy_body );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$this->logger->log( '[CDN Cache Manager / Imperva] URL purge failed for ' . $url . '. Modern error: ' . $modern_result->get_error_message() . ' | Legacy error: ' . $legacy_result->get_error_message() );

		return $legacy_result;
	}

	/**
	 * Validate API credentials against site endpoint.
	 *
	 * @param bool $allow_purge_fallback Whether purge call fallback is allowed.
	 * @return true|WP_Error
	 */
	public function validate_credentials( $allow_purge_fallback = true ) {
		$site_id = (string) $this->setting( 'imperva_site_id' );

		$modern_endpoint = $this->modern_base_url() . '/sites/' . rawurlencode( $site_id );
		$modern_result   = $this->request( 'GET', $modern_endpoint, array(), 'json' );
		if ( ! is_wp_error( $modern_result ) ) {
			return true;
		}

		$legacy_result = $this->request_legacy(
			'GET',
			'/sites/status',
			array(
				'site_id' => $site_id,
			)
		);
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		$legacy_result = $this->request_legacy( 'GET', '/sites/list', array() );
		if ( ! is_wp_error( $legacy_result ) ) {
			return true;
		}

		if ( $allow_purge_fallback ) {
			// Final fallback: if purge works for homepage URL, credentials and App ID are valid.
			$purge_validation = $this->purge_url( home_url( '/' ) );
			if ( ! is_wp_error( $purge_validation ) ) {
				return true;
			}

			return $purge_validation;
		}

		return $legacy_result;
	}

	/**
	 * Modern Imperva base URL.
	 *
	 * @return string
	 */
	private function modern_base_url() {
		$default = 'https://api.imperva.com/cdn/v1';
		return esc_url_raw( apply_filters( 'cdn_cache_manager_imperva_base_url', $default ) );
	}

	/**
	 * Legacy Imperva/Incapsula API base URLs.
	 *
	 * @return array<int,string>
	 */
	private function legacy_base_urls() {
		$defaults = array(
			'https://my.imperva.com/api/prov/v1',
			'https://my.incapsula.com/api/prov/v1',
		);

		$urls = apply_filters( 'cdn_cache_manager_imperva_legacy_base_urls', $defaults );

		if ( ! is_array( $urls ) ) {
			return $defaults;
		}

		return array_values( array_filter( array_map( 'esc_url_raw', $urls ) ) );
	}

	/**
	 * Execute request against legacy endpoints with fallback domains.
	 *
	 * @param string              $method   HTTP method.
	 * @param string              $endpoint Endpoint.
	 * @param array<string,mixed> $payload  Payload.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_legacy( $method, $endpoint, $payload ) {
		$last_error = new WP_Error( 'legacy_request_failed', __( 'Imperva API request failed.', 'cdn-cache-manager' ) );

		foreach ( $this->legacy_base_urls() as $base_url ) {
			$url    = untrailingslashit( $base_url ) . $endpoint;
			$result = $this->request( $method, $url, $payload, 'legacy' );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			$last_error = $result;
		}

		return $last_error;
	}

	/**
	 * Generate a purge pattern from a full URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function build_purge_pattern( $url ) {
		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		$path = is_string( $path ) && '' !== $path ? $path : '/';
		if ( is_string( $query ) && '' !== $query ) {
			$path .= '?' . $query;
		}

		return $path;
	}

	/**
	 * Issue API request.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $url    URL.
	 * @param array<string,mixed> $body   Body/query.
	 * @param string              $format Request format: json|legacy.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request( $method, $url, $body, $format = 'json' ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( 'missing_credentials', __( 'Imperva credentials are missing. Please save API ID, API Key, and App ID.', 'cdn-cache-manager' ) );
		}

		$api_id  = (string) $this->setting( 'imperva_api_id' );
		$api_key = (string) $this->setting( 'imperva_api_key' );

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'x-API-Id'  => $api_id,
				'x-API-Key' => $api_key,
				'Accept'    => 'application/json',
			),
		);

		if ( 'legacy' === $format ) {
			if ( 'GET' === strtoupper( $method ) ) {
				$body['api_id']  = $api_id;
				$body['api_key'] = $api_key;
				$url             = add_query_arg( $body, $url );
			} else {
				$body['api_id']               = $api_id;
				$body['api_key']              = $api_key;
				$args['body']                 = $body;
				$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			}
		} elseif ( 'GET' !== strtoupper( $method ) ) {
			$args['body']                    = wp_json_encode( $body );
			$args['headers']['Content-Type'] = 'application/json';
		}

		$this->logger->log( '[CDN Cache Manager / Imperva] ' . strtoupper( $method ) . ' ' . $url );

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$this->logger->log( '[CDN Cache Manager / Imperva] Error: ' . $response->get_error_message() );
			return new WP_Error( 'api_request_failed', $response->get_error_message() );
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$parsed = json_decode( $raw, true );

		$this->logger->log( '[CDN Cache Manager / Imperva] Response code: ' . $code );
		$this->logger->log( '[CDN Cache Manager / Imperva] Response body: ' . $raw );

		if ( 404 === $code ) {
			return new WP_Error( 'site_not_found', __( 'Site not found — check App ID', 'cdn-cache-manager' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'api_error', $this->extract_error_message( $parsed, $raw ) );
		}

		if ( is_array( $parsed ) && isset( $parsed['res'] ) && 0 !== (int) $parsed['res'] ) {
			return new WP_Error( 'api_error', $this->extract_error_message( $parsed, $raw ) );
		}

		return array(
			'code' => $code,
			'body' => $raw,
		);
	}

	/**
	 * Build user-friendly message from response body.
	 *
	 * @param mixed  $parsed Parsed JSON.
	 * @param string $raw    Raw body.
	 * @return string
	 */
	private function extract_error_message( $parsed, $raw ) {
		if ( is_array( $parsed ) ) {
			if ( ! empty( $parsed['res_message'] ) ) {
				return sanitize_text_field( (string) $parsed['res_message'] );
			}

			if ( ! empty( $parsed['message'] ) ) {
				return sanitize_text_field( (string) $parsed['message'] );
			}

			if ( ! empty( $parsed['error_description'] ) ) {
				return sanitize_text_field( (string) $parsed['error_description'] );
			}
		}

		if ( '' !== $raw ) {
			return sanitize_text_field( $raw );
		}

		return __( 'Imperva API request failed.', 'cdn-cache-manager' );
	}

	/**
	 * Read setting from override payload or persisted settings.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	private function setting( $key ) {
		if ( array_key_exists( $key, $this->override_settings ) ) {
			return $this->override_settings[ $key ];
		}

		return $this->settings->get( $key );
	}
}
