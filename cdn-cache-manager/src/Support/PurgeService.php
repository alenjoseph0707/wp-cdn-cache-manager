<?php

namespace CDNCacheManager\Support;

use CDNCacheManager\Api\ImpervaClient;
use WP_Error;

/**
 * Central purge orchestration.
 */
final class PurgeService {
	/**
	 * Settings.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Imperva API client.
	 *
	 * @var ImpervaClient
	 */
	private $imperva;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Notices.
	 *
	 * @var NoticeManager
	 */
	private $notices;

	/**
	 * Activity log.
	 *
	 * @var ActivityLog
	 */
	private $activity_log;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings.
	 * @param ImpervaClient      $imperva  API client.
	 * @param Logger             $logger   Logger.
	 * @param NoticeManager      $notices  Notices.
	 * @param ActivityLog        $log      Activity log.
	 */
	public function __construct( SettingsRepository $settings, ImpervaClient $imperva, Logger $logger, NoticeManager $notices, ActivityLog $log ) {
		$this->settings     = $settings;
		$this->imperva      = $imperva;
		$this->logger       = $logger;
		$this->notices      = $notices;
		$this->activity_log = $log;
	}

	/**
	 * Purge all cache.
	 *
	 * @param string $source Source.
	 * @param bool   $notify Add admin notice.
	 * @return true|WP_Error
	 */
	public function purge_all( $source = 'manual', $notify = true ) {
		if ( $this->is_manual_source( $source ) ) {
			$preflight = $this->validate_manual_credentials();
			if ( is_wp_error( $preflight ) ) {
				$this->activity_log->add( 'purge_all', 'all', 'error', $preflight->get_error_message() );
				if ( $notify ) {
					$this->notices->add( '✗ CDN Cache Manager: Cache NOT purged: ' . $preflight->get_error_message(), 'error' );
				}
				return $preflight;
			}
		}

		$result = $this->execute_provider_purge_all();

		if ( is_wp_error( $result ) ) {
			$this->activity_log->add( 'purge_all', 'all', 'error', $result->get_error_message() );
			if ( $notify ) {
				$this->notices->add( '✗ CDN Cache Manager: Cache NOT purged: ' . $result->get_error_message(), 'error' );
			}
			return $result;
		}

		$this->activity_log->add( 'purge_all', 'all', 'success', 'Cache purged via ' . sanitize_text_field( $source ) );
		if ( $notify ) {
			$this->notices->add( '✔ CDN Cache Manager: Cache purged for: all cache', 'success' );
		}

		return true;
	}

	/**
	 * Purge specific URL.
	 *
	 * @param string $url    URL.
	 * @param string $source Source.
	 * @param bool   $notify Add admin notice.
	 * @return true|WP_Error
	 */
	public function purge_url( $url, $source = 'manual', $notify = true ) {
		$normalized_url = $this->normalize_url( $url );
		if ( is_wp_error( $normalized_url ) ) {
			$error = $normalized_url;
			$this->activity_log->add( 'purge_url', 'unknown', 'error', $error->get_error_message() );
			if ( $notify ) {
				$this->notices->add( '✗ CDN Cache Manager: Cache NOT purged: ' . $error->get_error_message(), 'error' );
			}

			return $error;
		}

		$url = $normalized_url;

		if ( $this->is_manual_source( $source ) ) {
			$preflight = $this->validate_manual_credentials();
			if ( is_wp_error( $preflight ) ) {
				$this->activity_log->add( 'purge_url', $url, 'error', $preflight->get_error_message() );
				if ( $notify ) {
					$this->notices->add( '✗ CDN Cache Manager: Cache NOT purged: ' . $preflight->get_error_message(), 'error' );
				}
				return $preflight;
			}
		}

		$result = $this->execute_provider_purge_url( $url );
		if ( is_wp_error( $result ) ) {
			$this->activity_log->add( 'purge_url', $url, 'error', $result->get_error_message() );
			if ( $notify ) {
				$this->notices->add( '✗ CDN Cache Manager: Cache NOT purged: ' . $result->get_error_message(), 'error' );
			}
			return $result;
		}

		$this->activity_log->add( 'purge_url', $url, 'success', 'Cache purged via ' . sanitize_text_field( $source ) );
		if ( $notify ) {
			$this->notices->add( '✔ CDN Cache Manager: Cache purged for: ' . $url, 'success' );
		}

		return true;
	}

	/**
	 * Execute provider-specific full purge.
	 *
	 * @return true|WP_Error
	 */
	private function execute_provider_purge_all() {
		if ( 'imperva' !== $this->settings->get( 'provider' ) ) {
			return new WP_Error( 'provider_not_supported', __( 'Selected CDN provider is not supported yet.', 'cdn-cache-manager' ) );
		}

		return $this->imperva->purge_all();
	}

	/**
	 * Execute provider-specific URL purge.
	 *
	 * @param string $url URL.
	 * @return true|WP_Error
	 */
	private function execute_provider_purge_url( $url ) {
		if ( 'imperva' !== $this->settings->get( 'provider' ) ) {
			return new WP_Error( 'provider_not_supported', __( 'Selected CDN provider is not supported yet.', 'cdn-cache-manager' ) );
		}

		return $this->imperva->purge_url( $url );
	}

	/**
	 * Check if source is a manual action.
	 *
	 * @param string $source Source label.
	 * @return bool
	 */
	private function is_manual_source( $source ) {
		return 0 === strpos( (string) $source, 'manual' );
	}

	/**
	 * Validate manual action credentials before purge.
	 *
	 * @return true|WP_Error
	 */
	private function validate_manual_credentials() {
		if ( ! $this->imperva->has_credentials() ) {
			return new WP_Error( 'missing_credentials', __( 'Imperva credentials are missing. Save valid API ID, API Key, and App ID first.', 'cdn-cache-manager' ) );
		}

		return true;
	}

	/**
	 * Normalize and validate URL for purge requests.
	 *
	 * @param string $url Raw URL.
	 * @return string|WP_Error
	 */
	private function normalize_url( $url ) {
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'Please enter a valid URL.', 'cdn-cache-manager' ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid URL format.', 'cdn-cache-manager' ) );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'invalid_url_scheme', __( 'Only http:// or https:// URLs are allowed.', 'cdn-cache-manager' ) );
		}

		$input_host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$site_host  = wp_parse_url( get_site_url(), PHP_URL_HOST );
		$site_host  = is_string( $site_host ) ? strtolower( $site_host ) : '';

		if ( '' === $input_host || '' === $site_host || $input_host !== $site_host ) {
			return new WP_Error( 'invalid_url_host', __( 'URL hostname must match this site domain.', 'cdn-cache-manager' ) );
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( '' === $path ) {
			$path = '/';
		}

		$normalized = $scheme . '://' . $input_host . $path;
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$normalized .= '?' . $parts['query'];
		}

		return esc_url_raw( $normalized );
	}
}
