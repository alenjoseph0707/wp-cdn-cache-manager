<?php
/**
 * Plugin Name: CDN Cache Manager
 * Description: Automatic and manual Imperva CDN cache management for WordPress.
 * Version: 1.0.0
 * Author: Alen Joseph
 * Text Domain: cdn-cache-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/autoload.php';

register_activation_hook( __FILE__, array( '\\CDNCacheManager\\Core\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\CDNCacheManager\Core\Plugin::instance()->boot();
	}
);
