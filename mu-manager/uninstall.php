<?php
/**
 * Uninstall Mu Manager.
 *
 * Removes editor bridges that may remain under wp-content/plugins/mupm-edit-*.
 *
 * @package Mu_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'WP_PLUGIN_DIR' ) || ! is_dir( WP_PLUGIN_DIR ) ) {
	return;
}

$plugins_dir = WP_PLUGIN_DIR;
$handle      = opendir( $plugins_dir );
if ( ! $handle ) {
	return;
}

while ( false !== ( $file = readdir( $handle ) ) ) {
	if ( 0 !== strpos( $file, 'mupm-edit-' ) ) {
		continue;
	}
	$dir = $plugins_dir . '/' . $file;
	if ( is_link( $dir ) || ! is_dir( $dir ) ) {
		continue;
	}
	$root_real = realpath( $plugins_dir );
	$dir_real  = realpath( $dir );
	if ( ! $root_real || ! $dir_real ) {
		continue;
	}
	$root_real = str_replace( '\\', '/', $root_real );
	$dir_real  = str_replace( '\\', '/', $dir_real );
	if ( 0 !== strpos( basename( $dir_real ), 'mupm-edit-' ) || 0 !== strpos( $dir_real, $root_real . '/' ) ) {
		continue;
	}
	if ( ! is_file( $dir_real . '/.mupm-bridge' ) ) {
		continue;
	}
	eos_mupm_uninstall_delete_bridge_contents( $dir_real );
}

closedir( $handle );

/**
 * Recursively remove a bridge directory during uninstall.
 *
 * @param string $dir Absolute path to the bridge directory.
 */
function eos_mupm_uninstall_delete_bridge_contents( $dir ) {
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_link( $path ) ) {
			unlink( $path );
			continue;
		}
		if ( is_dir( $path ) ) {
			eos_mupm_uninstall_delete_bridge_contents( $path );
			rmdir( $path );
			continue;
		}
		unlink( $path );
	}
	rmdir( $dir );
}
