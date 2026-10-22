<?php
defined( 'ABSPATH' ) || exit;
defined( 'MU_PLUGIN_MANAGER_PLUGIN_DIR' ) || exit;

require_once MU_PLUGIN_MANAGER_PLUGIN_DIR . '/admin/mupm-helper.php';

// The plugin file editor only reads and writes files inside WP_PLUGIN_DIR.
// These bridges are symlinks so plugin-editor.php edits the real mu-plugin.

add_action( 'admin_init', 'eos_mupm_maybe_sync_editor_bridges', 1 );
add_action( 'load-plugin-editor.php', 'eos_mupm_guard_editor_bridge' );
add_action( 'shutdown', 'eos_mupm_delete_bridges_after_save', 999 );
add_filter( 'all_plugins', 'eos_mupm_hide_editor_bridges', 100 );
add_filter( 'plugins_list', 'eos_mupm_hide_editor_bridges_from_list', 100 );
add_filter( 'option_active_plugins', 'eos_mupm_include_bridge_in_active_plugins' );
add_filter( 'pre_update_option_active_plugins', 'eos_mupm_strip_bridge_plugins' );
add_filter( 'pre_update_site_option_active_sitewide_plugins', 'eos_mupm_strip_bridge_plugins' );

function eos_mupm_delete_bridges_after_save() {
	if ( ! eos_mupm_is_finished_mu_plugin_save() ) {
		return;
	}
	eos_mupm_delete_all_editor_bridges();
}

// True only after the editor has finished writing, including the fatal-error check.
function eos_mupm_is_finished_mu_plugin_save() {
	if ( isset( $_REQUEST['wp_scrape_key'] ) || '' === eos_mupm_requested_bridge_plugin() ) {
		return false;
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return isset( $_REQUEST['action'] ) && 'edit-theme-plugin-file' === $_REQUEST['action'];
	}
	global $pagenow;
	if ( ! isset( $pagenow ) || 'plugin-editor.php' !== $pagenow ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		return true;
	}
	return isset( $_GET['a'] ) && '1' === (string) $_GET['a'];
}

function eos_mupm_requested_bridge_plugin() {
	if ( empty( $_REQUEST['plugin'] ) || ! is_string( $_REQUEST['plugin'] ) ) {
		return '';
	}
	$plugin = wp_unslash( $_REQUEST['plugin'] );
	if ( ! is_string( $plugin ) || 0 !== strpos( $plugin, 'mupm-edit-' ) || false !== strpos( $plugin, '..' ) || false !== strpos( $plugin, '\\' ) ) {
		return '';
	}
	return $plugin;
}

function eos_mupm_maybe_sync_editor_bridges() {
	if ( ! eos_mupm_is_plugin_editor_request() ) {
		return;
	}
	eos_mupm_sync_editor_bridges();
}

function eos_mupm_is_plugin_editor_request() {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return isset( $_REQUEST['action'] ) && 'edit-theme-plugin-file' === $_REQUEST['action'];
	}
	global $pagenow;
	return isset( $pagenow ) && 'plugin-editor.php' === $pagenow;
}

function eos_mupm_editor_plugin_file( $mu_plugin ) {
	$source = eos_mupm_editor_source_from_file( $mu_plugin );
	if ( ! $source ) {
		return '';
	}
	return $source['folder'] . '/' . $source['php'];
}

function eos_mupm_editor_source_from_file( $mu_plugin ) {
	$mu_plugin = basename( str_replace( '\\', '/', (string) $mu_plugin ) );
	if ( '' === $mu_plugin || 'index.php' === $mu_plugin || 'mu-manager.php' === $mu_plugin ) {
		return null;
	}
	$extension = pathinfo( $mu_plugin, PATHINFO_EXTENSION );
	$is_php = ( 'php' === $extension );
	$is_disabled = ( strlen( $extension ) >= 8 && 'disabled' === substr( $extension, 0, 8 ) );
	if ( ! $is_php && ! $is_disabled ) {
		return null;
	}
	$name = pathinfo( $mu_plugin, PATHINFO_FILENAME );
	if ( '' === $name || '.' === $name || '..' === $name || false !== strpos( $name, '/' ) || false !== strpos( $name, '\\' ) ) {
		return null;
	}
	$safe = sanitize_file_name( $name );
	if ( '' === $safe || '.' === $safe || '..' === $safe ) {
		return null;
	}
	return array(
		'file'   => $mu_plugin,
		'name'   => $name,
		'php'    => $name . '.php',
		'folder' => 'mupm-edit-' . $safe,
		'active' => $is_php,
	);
}

function eos_mupm_editor_sources() {
	static $sources = null;
	if ( null !== $sources ) {
		return $sources;
	}
	$sources = array();
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		return $sources;
	}
	$handle = opendir( WPMU_PLUGIN_DIR );
	if ( ! $handle ) {
		return $sources;
	}
	while ( ( $file = readdir( $handle ) ) !== false ) {
		$source = eos_mupm_editor_source_from_file( $file );
		if ( ! $source ) {
			continue;
		}
		$path = WPMU_PLUGIN_DIR . '/' . $file;
		if ( ! is_file( $path ) || ! is_readable( $path ) || ! eos_mupm_path_is_inside_mu_plugins( $path ) ) {
			continue;
		}
		$real = realpath( $path );
		$root = realpath( WPMU_PLUGIN_DIR );
		if ( ! $real || ! $root || wp_normalize_path( dirname( $real ) ) !== wp_normalize_path( $root ) ) {
			continue;
		}
		$source['path'] = $real;
		$folder = $source['folder'];
		if ( isset( $sources[ $folder ] ) && ( $sources[ $folder ]['active'] || ! $source['active'] ) ) {
			continue;
		}
		$sources[ $folder ] = $source;
	}
	closedir( $handle );
	return $sources;
}

function eos_mupm_sync_editor_bridges() {
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_plugins' ) ) {
		return false;
	}
	if ( ! is_dir( WP_PLUGIN_DIR ) || ! is_writable( WP_PLUGIN_DIR ) ) {
		return false;
	}
	$sources = eos_mupm_editor_sources();
	$expected = array();
	foreach ( $sources as $folder => $source ) {
		$dir = WP_PLUGIN_DIR . '/' . $folder;
		if ( file_exists( $dir ) && ( is_link( $dir ) || ! is_dir( $dir ) || ! file_exists( $dir . '/.mupm-bridge' ) ) ) {
			continue;
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			continue;
		}
		file_put_contents( $dir . '/.mupm-bridge', $source['file'] );
		if ( ! eos_mupm_ensure_file_link( $source['path'], $dir . '/' . $source['php'] ) ) {
			continue;
		}
		$companion = WPMU_PLUGIN_DIR . '/' . $source['name'];
		$companion_link = $dir . '/' . $source['name'];
		if ( is_dir( $companion ) && ! is_link( $companion ) && eos_mupm_path_is_inside_mu_plugins( $companion ) ) {
			$companion_real = realpath( $companion );
			if ( $companion_real ) {
				eos_mupm_ensure_symlink( $companion_real, $companion_link );
			}
		} elseif ( is_link( $companion_link ) ) {
			unlink( $companion_link );
		}
		$expected[] = $folder;
	}
	eos_mupm_delete_stale_bridges( $expected );
	clearstatcache();
	eos_mupm_cache_editor_plugins();
	return true;
}

function eos_mupm_ensure_file_link( $target, $link ) {
	if ( eos_mupm_link_matches( $target, $link ) ) {
		return true;
	}
	if ( is_link( $link ) || ( file_exists( $link ) && eos_mupm_same_inode( $target, $link ) ) ) {
		unlink( $link );
	} elseif ( file_exists( $link ) ) {
		return false;
	}
	if ( function_exists( 'symlink' ) && @symlink( $target, $link ) ) {
		return true;
	}
	if ( is_file( $target ) && function_exists( 'link' ) && @link( $target, $link ) ) {
		return true;
	}
	return is_file( $link );
}

function eos_mupm_ensure_symlink( $target, $link ) {
	if ( is_link( $link ) ) {
		if ( readlink( $link ) === $target ) {
			return true;
		}
		unlink( $link );
	} elseif ( file_exists( $link ) ) {
		return false;
	}
	return function_exists( 'symlink' ) && @symlink( $target, $link );
}

function eos_mupm_link_matches( $target, $link ) {
	if ( is_link( $link ) ) {
		return readlink( $link ) === $target;
	}
	return eos_mupm_same_inode( $target, $link );
}

function eos_mupm_same_inode( $target, $link ) {
	if ( ! file_exists( $link ) || ! file_exists( $target ) || is_link( $link ) ) {
		return false;
	}
	$target_stat = stat( $target );
	$link_stat = stat( $link );
	return is_array( $target_stat ) && is_array( $link_stat ) && $target_stat['ino'] === $link_stat['ino'] && $target_stat['dev'] === $link_stat['dev'];
}

function eos_mupm_delete_stale_bridges( $expected ) {
	$handle = opendir( WP_PLUGIN_DIR );
	if ( ! $handle ) {
		return;
	}
	while ( ( $file = readdir( $handle ) ) !== false ) {
		if ( 0 !== strpos( $file, 'mupm-edit-' ) || in_array( $file, $expected, true ) ) {
			continue;
		}
		eos_mupm_delete_bridge_dir( WP_PLUGIN_DIR . '/' . $file );
	}
	closedir( $handle );
}

function eos_mupm_delete_all_editor_bridges() {
	if ( ! is_dir( WP_PLUGIN_DIR ) ) {
		return;
	}
	eos_mupm_delete_stale_bridges( array() );
}

function eos_mupm_delete_bridge_dir( $dir ) {
	if ( is_link( $dir ) || ! is_dir( $dir ) ) {
		return;
	}
	$root_real = realpath( WP_PLUGIN_DIR );
	$dir_real = realpath( $dir );
	if ( ! $root_real || ! $dir_real ) {
		return;
	}
	$root_real = wp_normalize_path( $root_real );
	$dir_real = wp_normalize_path( $dir_real );
	if ( 0 !== strpos( basename( $dir_real ), 'mupm-edit-' ) || 0 !== strpos( $dir_real, $root_real . '/' ) ) {
		return;
	}
	if ( ! is_file( $dir_real . '/.mupm-bridge' ) ) {
		return;
	}
	eos_mupm_delete_bridge_contents( $dir_real );
}

function eos_mupm_delete_bridge_contents( $dir ) {
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
			eos_mupm_delete_bridge_contents( $path );
			rmdir( $path );
			continue;
		}
		unlink( $path );
	}
	rmdir( $dir );
}

function eos_mupm_cache_editor_plugins() {
	wp_cache_delete( 'plugins', 'plugins' );
	$plugins = get_plugins();
	$changed = false;
	foreach ( eos_mupm_editor_sources() as $folder => $source ) {
		$relative = $folder . '/' . $source['php'];
		if ( isset( $plugins[ $relative ] ) || ! is_readable( WP_PLUGIN_DIR . '/' . $relative ) ) {
			continue;
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $relative, false, false );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		if ( empty( $data['Name'] ) ) {
			$data['Name'] = $source['name'];
		}
		$plugins[ $relative ] = $data;
		$changed = true;
	}
	if ( ! $changed ) {
		return;
	}
	$cache = wp_cache_get( 'plugins', 'plugins' );
	if ( ! is_array( $cache ) ) {
		$cache = array();
	}
	$cache[''] = $plugins;
	wp_cache_set( 'plugins', $cache, 'plugins' );
}

function eos_mupm_bridge_is_active_mu( $plugin ) {
	foreach ( eos_mupm_editor_sources() as $folder => $source ) {
		if ( $source['active'] && $folder . '/' . $source['php'] === $plugin ) {
			return true;
		}
	}
	return false;
}

function eos_mupm_include_bridge_in_active_plugins( $plugins ) {
	if ( ! did_action( 'plugins_loaded' ) || ! eos_mupm_is_plugin_editor_request() ) {
		return $plugins;
	}
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_plugins' ) ) {
		return $plugins;
	}
	if ( empty( $_REQUEST['plugin'] ) || ! is_string( $_REQUEST['plugin'] ) ) {
		return $plugins;
	}
	$plugin = wp_unslash( $_REQUEST['plugin'] );
	if ( ! is_string( $plugin ) || false !== strpos( $plugin, '..' ) || false !== strpos( $plugin, '\\' ) ) {
		return $plugins;
	}
	if ( ! eos_mupm_bridge_is_active_mu( $plugin ) ) {
		return $plugins;
	}
	if ( ! is_array( $plugins ) ) {
		$plugins = array();
	}
	if ( ! in_array( $plugin, $plugins, true ) ) {
		$plugins[] = $plugin;
	}
	return $plugins;
}

function eos_mupm_strip_bridge_plugins( $plugins ) {
	if ( ! is_array( $plugins ) || array() === $plugins ) {
		return $plugins;
	}
	$keys = array_keys( $plugins );
	$is_list = $keys === range( 0, count( $plugins ) - 1 );
	foreach ( $plugins as $key => $plugin ) {
		$file = $is_list ? $plugin : $key;
		if ( is_string( $file ) && 0 === strpos( $file, 'mupm-edit-' ) ) {
			unset( $plugins[ $key ] );
		}
	}
	if ( $is_list ) {
		$plugins = array_values( $plugins );
	}
	return $plugins;
}

function eos_mupm_hide_editor_bridges( $plugins ) {
	if ( ! is_array( $plugins ) ) {
		return $plugins;
	}
	foreach ( $plugins as $file => $data ) {
		if ( is_string( $file ) && 0 === strpos( $file, 'mupm-edit-' ) ) {
			unset( $plugins[ $file ] );
		}
	}
	return $plugins;
}

function eos_mupm_hide_editor_bridges_from_list( $plugins ) {
	if ( ! is_array( $plugins ) ) {
		return $plugins;
	}
	foreach ( $plugins as $status => $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}
		foreach ( $group as $file => $data ) {
			if ( is_string( $file ) && 0 === strpos( $file, 'mupm-edit-' ) ) {
				unset( $plugins[ $status ][ $file ] );
			}
		}
	}
	return $plugins;
}

function eos_mupm_guard_editor_bridge() {
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_plugins' ) ) {
		return;
	}
	if ( empty( $_REQUEST['plugin'] ) || ! is_string( $_REQUEST['plugin'] ) ) {
		return;
	}
	$plugin = wp_unslash( $_REQUEST['plugin'] );
	if ( ! is_string( $plugin ) || 0 !== strpos( $plugin, 'mupm-edit-' ) || false !== strpos( $plugin, '..' ) ) {
		return;
	}
	if ( ! is_file( WP_PLUGIN_DIR . '/' . $plugin ) ) {
		wp_die( esc_html__( 'The mu-plugin editor link could not be created. The plugins directory needs to be writable.', 'mu-manager' ) );
	}
}
