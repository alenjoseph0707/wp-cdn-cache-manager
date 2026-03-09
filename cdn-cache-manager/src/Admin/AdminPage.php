<?php

namespace CDNCacheManager\Admin;

use CDNCacheManager\Support\ActivityLog;
use CDNCacheManager\Support\PurgeService;
use CDNCacheManager\Support\SettingsRepository;

/**
 * Admin settings and manual purge UI.
 */
final class AdminPage {
	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Purge service.
	 *
	 * @var PurgeService
	 */
	private $purger;

	/**
	 * Activity log.
	 *
	 * @var ActivityLog
	 */
	private $log;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings.
	 * @param PurgeService       $purger   Purger.
	 * @param ActivityLog        $log      Activity log.
	 */
	public function __construct( SettingsRepository $settings, PurgeService $purger, ActivityLog $log ) {
		$this->settings = $settings;
		$this->purger   = $purger;
		$this->log      = $log;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this->settings, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add options page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'CDN Cache Manager', 'cdn-cache-manager' ),
			__( 'CDN Cache Manager', 'cdn-cache-manager' ),
			'manage_options',
			'cdn-cache-manager',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue admin scripts/styles.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_cdn-cache-manager' !== $hook_suffix ) {
			return;
		}

		$settings = $this->settings->get_all();

		wp_enqueue_style(
			'cdn-cache-manager-admin',
			plugins_url( '../../assets/css/admin.css', __FILE__ ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'cdn-cache-manager-admin',
			plugins_url( '../../assets/js/admin.js', __FILE__ ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'cdn-cache-manager-admin',
			'CDNCacheManagerAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'cdn_cache_manager_ajax_nonce' ),
				'confirmPurgeAll' => __( 'Are you sure you want to purge all CDN cache?', 'cdn-cache-manager' ),
				'successLabel'    => __( 'Success:', 'cdn-cache-manager' ),
				'errorLabel'      => __( 'Error:', 'cdn-cache-manager' ),
				'provider'        => isset( $settings['provider'] ) ? $settings['provider'] : 'imperva',
				'siteHost'        => wp_parse_url( get_site_url(), PHP_URL_HOST ),
				'purgingLabel'    => __( 'Purging all cache...', 'cdn-cache-manager' ),
				'purgedLabel'     => __( 'All site cache purged successfully.', 'cdn-cache-manager' ),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->settings->get_all();
		$log      = $this->log->all();
		?>
		<div class="wrap ccm-wrap">
			<h1><?php echo esc_html__( 'CDN Cache Manager', 'cdn-cache-manager' ); ?></h1>
			<?php settings_errors( SettingsRepository::OPTION_NAME, false ); ?>

			<div class="ccm-layout">
				<div class="ccm-main">
					<form method="post" action="options.php" class="ccm-settings-form">
						<?php settings_fields( 'cdn_cache_manager_group' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="ccm_provider"><?php echo esc_html__( 'CDN Provider', 'cdn-cache-manager' ); ?></label></th>
								<td>
									<select id="ccm_provider" name="cdn_cache_manager_settings[provider]">
										<option value="imperva" <?php selected( $settings['provider'], 'imperva' ); ?>><?php echo esc_html__( 'Imperva', 'cdn-cache-manager' ); ?></option>
										<option value="cloudflare" <?php selected( $settings['provider'], 'cloudflare' ); ?>><?php echo esc_html__( 'Cloudflare (coming soon)', 'cdn-cache-manager' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Automatic Cache Purging', 'cdn-cache-manager' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="cdn_cache_manager_settings[auto_purge_enabled]" value="1" <?php checked( ! empty( $settings['auto_purge_enabled'] ) ); ?> />
										<?php echo esc_html__( 'Enable automatic cache purging on content updates', 'cdn-cache-manager' ); ?>
									</label>
								</td>
							</tr>
							<tr class="ccm-provider-row" data-provider="imperva">
								<th scope="row"><label for="ccm_imperva_api_id"><?php echo esc_html__( 'API ID', 'cdn-cache-manager' ); ?></label></th>
								<td><input id="ccm_imperva_api_id" type="text" class="regular-text" name="cdn_cache_manager_settings[imperva_api_id]" value="<?php echo esc_attr( $settings['imperva_api_id'] ); ?>" /></td>
							</tr>
							<tr class="ccm-provider-row" data-provider="imperva">
								<th scope="row"><label for="ccm_imperva_api_key"><?php echo esc_html__( 'API Key', 'cdn-cache-manager' ); ?></label></th>
								<td><input id="ccm_imperva_api_key" type="password" class="regular-text" name="cdn_cache_manager_settings[imperva_api_key]" value="<?php echo esc_attr( $settings['imperva_api_key'] ); ?>" /></td>
							</tr>
							<tr class="ccm-provider-row" data-provider="imperva">
								<th scope="row"><label for="ccm_imperva_site_id"><?php echo esc_html__( 'App ID', 'cdn-cache-manager' ); ?></label></th>
								<td><input id="ccm_imperva_site_id" type="text" class="regular-text" name="cdn_cache_manager_settings[imperva_site_id]" value="<?php echo esc_attr( $settings['imperva_site_id'] ); ?>" /></td>
							</tr>
							<tr class="ccm-provider-row" data-provider="cloudflare">
								<th scope="row"><label for="ccm_cloudflare_zone_id"><?php echo esc_html__( 'Cloudflare Zone ID', 'cdn-cache-manager' ); ?></label></th>
								<td><input id="ccm_cloudflare_zone_id" type="text" class="regular-text" name="cdn_cache_manager_settings[cloudflare_zone_id]" value="<?php echo esc_attr( $settings['cloudflare_zone_id'] ); ?>" disabled /></td>
							</tr>
							<tr class="ccm-provider-row" data-provider="cloudflare">
								<th scope="row"><label for="ccm_cloudflare_api_token"><?php echo esc_html__( 'Cloudflare API Token', 'cdn-cache-manager' ); ?></label></th>
								<td><input id="ccm_cloudflare_api_token" type="password" class="regular-text" name="cdn_cache_manager_settings[cloudflare_api_token]" value="<?php echo esc_attr( $settings['cloudflare_api_token'] ); ?>" disabled /></td>
							</tr>
						</table>
						<?php submit_button( __( 'Save Settings', 'cdn-cache-manager' ) ); ?>
					</form>
				</div>

				<div class="ccm-side">
					<div class="ccm-card">
						<h2><?php echo esc_html__( 'Manual Cache Management', 'cdn-cache-manager' ); ?></h2>
						<h3><?php echo esc_html__( 'Purge Entire Site Cache', 'cdn-cache-manager' ); ?></h3>
						<p><?php echo esc_html__( 'This will clear all cached pages. Use this after theme updates or major site-wide changes.', 'cdn-cache-manager' ); ?></p>
						<button type="button" class="button button-primary button-hero ccm-purge-all-button" id="ccm-purge-all-button">
							<?php echo esc_html__( 'Purge All Cache', 'cdn-cache-manager' ); ?>
						</button>
						<div id="ccm-purge-all-status" class="ccm-inline-status" style="display:none;"></div>
						<hr />

						<h3><?php echo esc_html__( 'Purge Specific URL', 'cdn-cache-manager' ); ?></h3>
						<p><?php echo esc_html__( 'Enter a specific URL to purge its cache.', 'cdn-cache-manager' ); ?></p>
						<input type="url" id="ccm-specific-url" class="regular-text code" placeholder="https://example.com/sample-page/" />
						<p id="ccm-url-error" class="ccm-inline-error" style="display:none;">
							<span class="ccm-url-error-text"></span>
							<button type="button" class="ccm-url-error-close" aria-label="<?php echo esc_attr__( 'Dismiss URL error', 'cdn-cache-manager' ); ?>">&times;</button>
						</p>
						<p>
							<button type="button" class="button ccm-secondary-button" id="ccm-purge-url-button"><?php echo esc_html__( 'Purge URL', 'cdn-cache-manager' ); ?></button>
						</p>

						<div id="ccm-ajax-feedback" class="notice" style="display:none;"><p></p></div>
					</div>
				</div>
			</div>

			<div class="ccm-activity-section">
				<h2><?php echo esc_html__( 'Recent Activity', 'cdn-cache-manager' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'cdn-cache-manager' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'cdn-cache-manager' ); ?></th>
							<th><?php echo esc_html__( 'Target', 'cdn-cache-manager' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'cdn-cache-manager' ); ?></th>
							<th><?php echo esc_html__( 'User', 'cdn-cache-manager' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'cdn-cache-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $log ) ) : ?>
							<tr><td colspan="6"><?php echo esc_html__( 'No purge actions yet.', 'cdn-cache-manager' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( array_slice( $log, 0, 20 ) as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $entry['time'] ) ? $entry['time'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $entry['type'] ) ? $entry['type'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $entry['target'] ) ? $entry['target'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $entry['status'] ) ? $entry['status'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $entry['user'] ) ? $entry['user'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
