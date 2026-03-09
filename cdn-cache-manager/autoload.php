<?php
/**
 * PSR-4 style autoloader for CDN Cache Manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		$prefix   = 'CDNCacheManager\\';
		$base_dir = __DIR__ . '/src/';

		$length = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class, $length ) ) {
			return;
		}

		$relative_class = substr( $class, $length );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
