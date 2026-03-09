<?php

namespace CDNCacheManager\Core;

use CDNCacheManager\Admin\AdminBar;
use CDNCacheManager\Admin\AdminPage;
use CDNCacheManager\Admin\AjaxController;
use CDNCacheManager\Api\ImpervaClient;
use CDNCacheManager\Support\ActivityLog;
use CDNCacheManager\Support\Logger;
use CDNCacheManager\Support\NoticeManager;
use CDNCacheManager\Support\PurgeService;
use CDNCacheManager\Support\SettingsRepository;

/**
 * Main plugin bootstrap.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether plugin has been booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Initialize plugin services and hooks.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain( 'cdn-cache-manager', false, dirname( plugin_basename( __DIR__ . '/../../cdn-cache-manager.php' ) ) . '/languages' );

		$settings = SettingsRepository::instance();
		$logger   = Logger::instance();
		$notices  = NoticeManager::instance();
		$log      = ActivityLog::instance();
		$client   = new ImpervaClient( $settings, $logger );
		$purger   = new PurgeService( $settings, $client, $logger, $notices, $log );

		(new AdminPage( $settings, $purger, $log ))->register();
		(new AjaxController( $purger ))->register();
		(new AdminBar( $purger ))->register();
		(new AutoPurgeHooks( $purger, $settings ))->register();

		$notices->register();

		add_action(
			'admin_notices',
			static function () use ( $settings ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				if ( 'imperva' !== $settings->get( 'provider' ) ) {
					return;
				}

				if ( ! empty( $settings->get( 'imperva_api_id' ) ) && ! empty( $settings->get( 'imperva_api_key' ) ) && ! empty( $settings->get( 'imperva_site_id' ) ) ) {
					return;
				}

				echo '<div class=\"notice notice-warning\"><p>' . esc_html__( 'CDN Cache Manager: Imperva credentials are missing. Automatic purge is disabled until API ID, API Key, and App ID are saved.', 'cdn-cache-manager' ) . '</p></div>';
			}
		);
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( SettingsRepository::OPTION_NAME ) ) {
			update_option( SettingsRepository::OPTION_NAME, SettingsRepository::defaults() );
		}
	}
}
