<?php

namespace CDNCacheManager\Admin;

use CDNCacheManager\Support\PurgeService;

/**
 * Adds admin bar purge shortcut.
 */
final class AdminBar {
	/**
	 * Purge service.
	 *
	 * @var PurgeService
	 */
	private $purger;

	/**
	 * Constructor.
	 *
	 * @param PurgeService $purger Purger.
	 */
	public function __construct( PurgeService $purger ) {
		$this->purger = $purger;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 100 );
		add_action( 'admin_post_cdn_cache_manager_adminbar_purge', array( $this, 'handle_adminbar_purge' ) );
	}

	/**
	 * Add toolbar node.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 * @return void
	 */
	public function add_node( $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$href = wp_nonce_url(
			admin_url( 'admin-post.php?action=cdn_cache_manager_adminbar_purge' ),
			'cdn_cache_manager_adminbar_purge'
		);

		$admin_bar->add_node(
			array(
				'id'    => 'cdn-cache-manager-purge',
				'title' => __( 'Purge Imperva Cache', 'cdn-cache-manager' ),
				'href'  => $href,
			)
		);
	}

	/**
	 * Handle toolbar purge request.
	 *
	 * @return void
	 */
	public function handle_adminbar_purge() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cdn-cache-manager' ) );
		}

		check_admin_referer( 'cdn_cache_manager_adminbar_purge' );

		$this->purger->purge_all( 'manual:admin_bar', true );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
