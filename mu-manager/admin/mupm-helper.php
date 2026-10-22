<?php
defined( 'MU_PLUGIN_MANAGER_PLUGIN_DIR' ) || exit;

//Return array of disabled mu-plugins
function eos_mupm_get_disabled_mu_plugins() {
  $mu_plugins = array();
  if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
    return $mu_plugins;
  }
  $dh = opendir( WPMU_PLUGIN_DIR );
  if ( ! $dh ) {
    return $mu_plugins;
  }
  while ( ( $plugin = readdir( $dh ) ) !== false ) {
    if ( 'disabled' === substr( pathinfo( $plugin, PATHINFO_EXTENSION ), 0, 8 ) ) {
        $mu_plugins[] = $plugin;
    }
  }
  closedir( $dh );
  sort( $mu_plugins );
  return $mu_plugins;
}

//It retrieves the disabled mu-plugins data
function eos_mupm_get_disabled_mu_plugins_data() {
    $wp_plugins   = array();
    $plugin_files = array();
    if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
        return $wp_plugins;
    }
    // Files in wp-content/mu-plugins directory.
    $plugins_dir = @opendir( WPMU_PLUGIN_DIR );
    if( $plugins_dir ){
      while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
        if ( 'disabled' === substr( pathinfo( $file, PATHINFO_EXTENSION ), 0, 8 ) ) {
            $plugin_files[] = $file;
        }
      }
    }
    else {
      return $wp_plugins;
    }
    closedir( $plugins_dir );
    if ( empty( $plugin_files ) ) {
        return $wp_plugins;
    }
    foreach ( $plugin_files as $plugin_file ) {
      if ( ! is_readable( WPMU_PLUGIN_DIR . "/$plugin_file" ) ) {
          continue;
      }
      $plugin_data = get_plugin_data( WPMU_PLUGIN_DIR . "/$plugin_file",false,false );
      if ( empty( $plugin_data['Name'] ) ) {
          $plugin_data['Name'] = $plugin_file;
      }
      $wp_plugins[ $plugin_file ] = $plugin_data;
    }
    if ( isset( $wp_plugins['index.php'] ) && filesize( WPMU_PLUGIN_DIR . '/index.php' ) <= 30 ) {
        // Silence is golden.
        unset( $wp_plugins['index.php'] );
    }
    uasort( $wp_plugins, '_sort_uname_callback' );
    return $wp_plugins;
}

//Activate mu-plugin
function eos_mupm_activate_mu_plugin( $mu_plugin ){
  if( current_user_can( 'activate_plugins' ) ){
    eos_mupm_rename_mu_plugin( $mu_plugin, str_replace( pathinfo( $mu_plugin, PATHINFO_EXTENSION ), 'php', $mu_plugin ) );
  }
}
//Deactivate mu-plugin
function eos_mupm_deactivate_mu_plugin( $mu_plugin ){
  if( current_user_can( 'deactivate_plugin' ) ){
    eos_mupm_rename_mu_plugin( $mu_plugin, str_replace( '.php','.disabled-' . uniqid(), $mu_plugin ) );
  }
}
//Delete mu-plugin
function eos_mupm_delete_mu_plugin( $mu_plugin){
  if( current_user_can( 'delete_plugins' ) ){
    eos_mupm_rename_mu_plugin( $mu_plugin, str_replace( pathinfo( $mu_plugin, PATHINFO_EXTENSION ), 'deleteted-' . uniqid(), $mu_plugin ) );
  }
}

//Download an mu-plugin as a zip archive.
function eos_mupm_download_mu_plugin( $mu_plugin ){
	if( ! current_user_can( 'activate_plugins' ) ){
		wp_die( esc_html__( 'Sorry, you are not allowed to download mu-plugins.', 'mu-manager' ), '', array( 'response' => 403 ) );
	}

	$mu_plugin = basename( str_replace( '\\', '/', $mu_plugin ) );
	$source = eos_mupm_resolve_mu_plugin_file( $mu_plugin );
	if( ! $source ){
		wp_die( esc_html__( 'Mu-plugin not found.', 'mu-manager' ), '', array( 'response' => 404 ) );
	}

	$download_name = pathinfo( $mu_plugin, PATHINFO_FILENAME );
	if( '' === $download_name || '.' === $download_name || '..' === $download_name ){
		wp_die( esc_html__( 'Invalid mu-plugin.', 'mu-manager' ), '', array( 'response' => 400 ) );
	}

	$staging = trailingslashit( get_temp_dir() ) . 'mupm-' . wp_generate_password( 12, false );
	if( ! wp_mkdir_p( $staging ) ){
		wp_die( esc_html__( 'Could not create the download archive.', 'mu-manager' ), '', array( 'response' => 500 ) );
	}

	$copied = copy( $source, $staging . '/' . $download_name . '.php' );
	if( ! $copied ){
		eos_mupm_rrmdir( $staging );
		wp_die( esc_html__( 'Could not create the download archive.', 'mu-manager' ), '', array( 'response' => 500 ) );
	}

	$companion = trailingslashit( dirname( $source ) ) . $download_name;
	if( is_dir( $companion ) && eos_mupm_path_is_inside_mu_plugins( $companion ) && ! is_link( $companion ) ){
		eos_mupm_copy_dir( $companion, $staging . '/' . $download_name );
	}

	$zip_path = wp_tempnam( 'mupm-zip' );
	if( ! $zip_path ){
		eos_mupm_rrmdir( $staging );
		wp_die( esc_html__( 'Could not create the download archive.', 'mu-manager' ), '', array( 'response' => 500 ) );
	}
	if( file_exists( $zip_path ) ){
		unlink( $zip_path );
	}
	$zip_path .= '.zip';

	if( ! eos_mupm_zip_directory( $staging, $zip_path ) ){
		eos_mupm_rrmdir( $staging );
		if( file_exists( $zip_path ) ){
			unlink( $zip_path );
		}
		wp_die( esc_html__( 'Could not create the download archive.', 'mu-manager' ), '', array( 'response' => 500 ) );
	}

	eos_mupm_rrmdir( $staging );
	eos_mupm_send_download( $zip_path, sanitize_file_name( $download_name . '.zip' ), 'application/zip', true );
}

//Resolve an mu-plugin filename to a readable file inside mu-plugins.
function eos_mupm_resolve_mu_plugin_file( $mu_plugin ){
	if( '' === $mu_plugin ){
		return false;
	}
	$extension = pathinfo( $mu_plugin, PATHINFO_EXTENSION );
	$is_php = ( 'php' === $extension );
	$is_disabled = ( strlen( $extension ) >= 8 && 'disabled' === substr( $extension, 0, 8 ) );
	if( ! $is_php && ! $is_disabled ){
		return false;
	}
	$path = WPMU_PLUGIN_DIR . '/' . $mu_plugin;
	if( ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ){
		return false;
	}
	if( ! eos_mupm_path_is_inside_mu_plugins( $path ) ){
		return false;
	}
	$real = realpath( $path );
	$root = realpath( WPMU_PLUGIN_DIR );
	if( ! $real || ! $root || wp_normalize_path( dirname( $real ) ) !== wp_normalize_path( $root ) ){
		return false;
	}
	return $real;
}

//Whether a path stays inside the mu-plugins directory.
function eos_mupm_path_is_inside_mu_plugins( $path ){
	$root = realpath( WPMU_PLUGIN_DIR );
	$real = realpath( $path );
	if( ! $root || ! $real ){
		return false;
	}
	$root = wp_normalize_path( $root );
	$real = wp_normalize_path( $real );
	return $real === $root || 0 === strpos( $real, $root . '/' );
}

//Copy a directory tree, skipping symbolic links.
function eos_mupm_copy_dir( $source, $dest ){
	if( ! eos_mupm_path_is_inside_mu_plugins( $source ) || is_link( $source ) ){
		return false;
	}
	if( ! wp_mkdir_p( $dest ) ){
		return false;
	}
	$items = scandir( $source );
	if( ! is_array( $items ) ){
		return false;
	}
	foreach( $items as $item ){
		if( '.' === $item || '..' === $item ){
			continue;
		}
		$from = $source . '/' . $item;
		$to = $dest . '/' . $item;
		if( is_link( $from ) ){
			continue;
		}
		if( is_dir( $from ) ){
			eos_mupm_copy_dir( $from, $to );
		} elseif( is_file( $from ) && is_readable( $from ) ){
			copy( $from, $to );
		}
	}
	return true;
}

//Create a zip archive from a directory.
function eos_mupm_zip_directory( $source_dir, $zip_path ){
	$source_dir = untrailingslashit( $source_dir );
	if( class_exists( 'ZipArchive' ) ){
		$zip = new ZipArchive();
		$flags = ZipArchive::CREATE;
		if( true === $zip->open( $zip_path, $flags ) ){
			$added = eos_mupm_zip_add_tree( $zip, $source_dir, '' );
			$closed = $zip->close();
			return $added && $closed && file_exists( $zip_path ) && filesize( $zip_path ) > 0;
		}
	}
	if( ! class_exists( 'PclZip' ) ){
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
	}
	if( file_exists( $zip_path ) ){
		unlink( $zip_path );
	}
	$archive = new PclZip( $zip_path );
	$result = $archive->create( $source_dir, PCLZIP_OPT_REMOVE_PATH, $source_dir );
	return is_array( $result ) && ! empty( $result ) && file_exists( $zip_path );
}

//Add files from a directory to an open ZipArchive.
function eos_mupm_zip_add_tree( $zip, $dir, $base ){
	$items = scandir( $dir );
	if( ! is_array( $items ) ){
		return false;
	}
	foreach( $items as $item ){
		if( '.' === $item || '..' === $item ){
			continue;
		}
		$path = $dir . '/' . $item;
		if( is_link( $path ) ){
			continue;
		}
		$local = '' === $base ? $item : $base . '/' . $item;
		if( is_dir( $path ) ){
			if( ! $zip->addEmptyDir( $local ) || ! eos_mupm_zip_add_tree( $zip, $path, $local ) ){
				return false;
			}
		} elseif( is_file( $path ) ){
			if( ! $zip->addFile( $path, $local ) ){
				return false;
			}
		}
	}
	return true;
}

//Stream a file download and stop the request.
function eos_mupm_send_download( $path, $filename, $content_type, $delete_after = false ){
	if( ! is_file( $path ) || ! is_readable( $path ) ){
		wp_die( esc_html__( 'Could not create the download archive.', 'mu-manager' ), '', array( 'response' => 500 ) );
	}
	while( ob_get_level() ){
		ob_end_clean();
	}
	nocache_headers();
	header( 'Content-Description: File Transfer' );
	header( 'Content-Type: ' . $content_type );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Transfer-Encoding: binary' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path );
	if( $delete_after ){
		unlink( $path );
	}
	exit;
}

//Remove a temporary directory created for an mu-plugin download.
function eos_mupm_rrmdir( $dir ){
	if( ! is_dir( $dir ) ){
		return;
	}
	$temp_root = realpath( get_temp_dir() );
	$real = realpath( $dir );
	if( ! $temp_root || ! $real || false === strpos( wp_normalize_path( $real ), 'mupm-' ) ){
		return;
	}
	if( 0 !== strpos( wp_normalize_path( $real ), wp_normalize_path( $temp_root ) . '/' ) ){
		return;
	}
	$items = scandir( $real );
	if( ! is_array( $items ) ){
		return;
	}
	foreach( $items as $item ){
		if( '.' === $item || '..' === $item ){
			continue;
		}
		$path = $real . '/' . $item;
		if( is_dir( $path ) && ! is_link( $path ) ){
			eos_mupm_rrmdir( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $real );
}

//Rename file
function eos_mupm_rename_mu_plugin( $old,$new ){
	$writeAccess = false;
	$access_type = get_filesystem_method();
	if( $access_type === 'direct' ){
		/* you can safely run request_filesystem_credentials() without any issues and don't need to worry about passing in a URL */
		$creds = request_filesystem_credentials( admin_url(), '', false, false, array() );
		/* initialize the API */
		if ( ! WP_Filesystem( $creds ) ) {
			/* any problems and we exit */
			return false;
		}
    if( file_exists( WPMU_PLUGIN_DIR. '/' . $old ) ){
      if( ! file_exists( WPMU_PLUGIN_DIR . '/index.php' ) ) {
        file_put_contents( WPMU_PLUGIN_DIR . '/index.php', '<?php' . PHP_EOL . '// Silent is golden.' );
      }
      if( ! file_exists( WPMU_PLUGIN_DIR . '/mu-manager.php' ) ) {
        file_put_contents( WPMU_PLUGIN_DIR . '/mu-manager.php', '<?php' . PHP_EOL . '// Empty MU Plugin installed by MU Manager to prevent WP from hidding the MU Plugins page.' );
      }
		  rename( WPMU_PLUGIN_DIR . '/' . $old, WPMU_PLUGIN_DIR . '/' . $new );
    }
	}
}

add_filter( 'plugins_list', function( $plugins ) {
  if( isset( $plugins['mustuse'] ) && 1 === count( $plugins['mustuse'] ) && in_array( 'mu-manager.php', array_keys( $plugins['mustuse'] ) ) ) {
    $plugins['mustuse'] = array(
      'mu-manager.php' => array(
          'Name' => 'MU Manager',
          'PluginURI' => '',
          'Version' => '',
          'Description' => esc_html__( 'Empty MU Plugin installed by MU Manager to prevent WP from hidding this page. Do not deactivate this mu-plugin.', 'mu-manager' ),
          'Author' => '',
          'AuthorURI' => '',
          'TextDomain' => '',
          'DomainPath' => '',
          'Network' => '',
          'RequiresWP' => '',
          'RequiresPHP' => '',
          'UpdateURI' => '',
          'RequiresPlugins' => '',
          'Title' => '',
          'AuthorName' => ''
      )
    );
  }
  return $plugins;
} );