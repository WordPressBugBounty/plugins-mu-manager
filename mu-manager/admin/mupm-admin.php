<?php
defined( 'MU_PLUGIN_MANAGER_PLUGIN_DIR' ) || exit;

require_once MU_PLUGIN_MANAGER_PLUGIN_DIR.'/admin/mupm-helper.php';

add_action( 'admin_init',function(){
  if( ! isset( $_GET['mupm-action'] ) || ! isset( $_GET['mu-plugin'] ) || ! isset( $_GET['_wpnonce'] ) ){
    return;
  }
  if ( is_multisite() && ! is_network_admin() ) {
    return;
  }
  $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
  $action = sanitize_text_field( wp_unslash( $_GET['mupm-action'] ) );
  $mu_plugin = eos_mupm_normalize_mu_plugin_basename( isset( $_GET['mu-plugin'] ) ? $_GET['mu-plugin'] : '' );
  if ( '' === $mu_plugin || ! eos_mupm_is_allowed_mu_plugin_name( $mu_plugin ) ) {
    return;
  }
  switch( $action ){
    case 'eos-mu-deactivate':
      if( wp_verify_nonce( $nonce,'eos-deactivate-mu-plugin_'.$mu_plugin ) ){
        eos_mupm_deactivate_mu_plugin( $mu_plugin );
      }
      break;
    case 'eos-mu-activate':
      if( wp_verify_nonce( $nonce,'eos-activate-mu-plugin_'.$mu_plugin ) ){
        eos_mupm_activate_mu_plugin( $mu_plugin );
      }
      break;
    case 'eos-mu-delete':
      if( wp_verify_nonce( $nonce,'eos-delete-mu-plugin_'.$mu_plugin ) ){
        eos_mupm_delete_mu_plugin( $mu_plugin );
      }
      break;
    case 'eos-mu-download':
      if( wp_verify_nonce( $nonce,'eos-download-mu-plugin_'.$mu_plugin ) ){
        eos_mupm_download_mu_plugin( $mu_plugin );
      }
      break;
  }
},0 );

add_filter( 'plugin_action_links', function( $actions, $plugin_file, $plugin_data, $context ) {
  if( 'mu-manager.php' === $plugin_file ) {
    return $actions;
  }
  if ( 'mustuse' !== $context && 'mu-plugin' !== $context ) {
    return $actions;
  }
  if ( is_multisite() && ! is_network_admin() ) {
    return $actions;
  }
  $plugin_file = eos_mupm_normalize_mu_plugin_basename( $plugin_file );
  if ( '' === $plugin_file || ! eos_mupm_is_allowed_mu_plugin_name( $plugin_file ) ) {
    return $actions;
  }
  // Add action links to the mu-plugins.
  $plugin_id_attr = isset( $plugin_data['Name'] ) ? sanitize_key( str_replace( '.php', '', $plugin_data['Name'] ) ) : 'plugin-' . uniqid();
  if ( eos_mupm_user_can_manage_mu_plugins( 'deactivate_plugin' ) && '.php' === substr( $plugin_file, -4 ) ) {
    $actions['deactivate'] = sprintf(
      '<a href="%s" id="deactivate-%s" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-deactivate&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-deactivate-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      esc_attr( sprintf( _x( 'Deactivate %s', 'plugin' ), $plugin_data['Name'] ) ),
      __( 'Deactivate' )
    );
  }
  if ( eos_mupm_user_can_manage_mu_plugins( 'activate_plugins' ) && '.php' !== substr( $plugin_file, -4 ) ) {
    $actions['activate'] = sprintf(
      '<a href="%s" id="activate-%s" class="edit" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-activate&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-activate-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      esc_attr( sprintf( _x( 'Activate %s', 'plugin' ), $plugin_data['Name'] ) ),
      __( 'Activate' )
    );
  }
  if ( current_user_can( 'edit_plugins' ) && ( 'mustuse' === $context || 'mu-plugin' === $context ) ) {
    $editor_file = eos_mupm_editor_plugin_file( $plugin_file );
    if ( '' !== $editor_file ) {
      $plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_file;
      $editor_base = is_multisite() ? network_admin_url( 'plugin-editor.php' ) : admin_url( 'plugin-editor.php' );
      $actions['edit'] = sprintf(
        '<a href="%s" id="edit-%s" class="edit" aria-label="%s">%s</a>',
        esc_url( add_query_arg( array( 'plugin' => $editor_file, 'file' => $editor_file ), $editor_base ) ),
        esc_attr( $plugin_id_attr ),
        /* translators: %s: Plugin name. */
        esc_attr( sprintf( __( 'Edit %s', 'mu-manager' ), $plugin_name ) ),
        __( 'Edit' )
      );
    }
  }
  if ( eos_mupm_user_can_manage_mu_plugins( 'activate_plugins' ) && ( 'mustuse' === $context || 'mu-plugin' === $context ) ) {
    $plugin_name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_file;
    $actions['download'] = sprintf(
      '<a href="%s" id="download-%s" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-download&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-download-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      /* translators: %s: Plugin name. */
      esc_attr( sprintf( __( 'Download %s', 'mu-manager' ), $plugin_name ) ),
      __( 'Download', 'mu-manager' )
    );
  }
  if ( ! is_multisite() && eos_mupm_user_can_manage_mu_plugins( 'delete_plugins' ) && '.php' !== substr( $plugin_file, -4 ) ) {
    $actions['delete'] = sprintf(
      '<a href="%s" id="delete-%s" class="delete" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-delete&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-delete-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      esc_attr( sprintf( _x( 'Delete %s', 'plugin' ), $plugin_data['Name'] ) ),
      __( 'Delete' )
    );
  }
  return $actions;
},20,4 );

add_action( 'after_plugin_row',function(){
  // Add disabled mu-plugins after the active mu-plugins.
  if( isset( $_GET['plugin_status'] ) && 'mustuse' === sanitize_text_field( $_GET['plugin_status'] ) ){
    static $mun = 0;
    ++$mun;
    $mu_plugins = wp_get_mu_plugins();
    if( in_array( WPMU_PLUGIN_DIR . '/index.php', $mu_plugins ) ) {
      unset( $mu_plugins[array_search( WPMU_PLUGIN_DIR . '/index.php', $mu_plugins )] );
    }
    $mu_plugins = array_values( $mu_plugins );
    if( $mu_plugins && is_array( $mu_plugins ) && $mun === count( array_keys( $mu_plugins ) ) ){
      $disabled_mus = eos_mupm_get_disabled_mu_plugins();
      if( $disabled_mus && is_array( $disabled_mus ) && !empty( $disabled_mus ) ){
        $mu_data = eos_mupm_get_disabled_mu_plugins_data();
        foreach( $disabled_mus as $disabled_mu ){
          $mu_info = $mu_data[sanitize_text_field( $disabled_mu )];
          $actions = apply_filters( 'plugin_action_links',array(), $disabled_mu,$mu_info,'mu-plugin' );
          $plugin_meta = array();
          $kses_args = array( 'a' => array( 'href' => array(),'class' => array(),'id' => array(),'aria-label' => array() ),'span' => array( 'class' => array() ) );
					if ( ! empty( $mu_info['Version'] ) ) {
						/* translators: %s: Plugin version number. */
						$plugin_meta[] = sprintf( __( 'Version %s' ), esc_attr( $mu_info['Version'] ) );
					}
					if ( ! empty( $mu_info['Author'] ) ) {
						$author = $mu_info['Author'];
						if ( ! empty( $mu_info['AuthorURI'] ) ) {
							$author = '<a href="' . esc_url( $mu_info['AuthorURI'] ) . '">' .esc_html(  $mu_info['Author'] ) . '</a>';
						}
						$plugin_meta[] = sprintf( __( 'By %s' ), $author );
					}
					if ( isset( $mu_info['slug'] ) && current_user_can( 'install_plugins' ) ) {
						$plugin_meta[] = sprintf(
							'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
							esc_url(
								network_admin_url(
									'plugin-install.php?tab=plugin-information&mu-plugin=' .esc_attr( $mu_info['slug'] ).
									'&TB_iframe=true&width=600&height=550'
								)
							),
							/* translators: %s: Plugin name. */
							esc_attr( sprintf( __( 'More information about %s' ), $plugin_name ) ),
							esc_attr( $plugin_name ),
							__( 'View details' )
						);
					} elseif ( ! empty( $mu_info['PluginURI'] ) ) {
						$aria_label = sprintf( __( 'Visit plugin site for %s' ),esc_attr( $plugin_name ) );

						$plugin_meta[] = sprintf(
							'<a href="%s" aria-label="%s">%s</a>',
							esc_url( $mu_info['PluginURI'] ),
							esc_attr( $aria_label ),
							__( 'Visit plugin site' )
						);
					}

        ?>
        <tr scope="row" class="inactive is-uninstallable">
          <th class="check-column"></th>
          <td class="plugin-title column-primary">
            <strong><?php echo isset( $mu_info['Title'] ) && '' !== sanitize_text_field( $mu_info['Title'] ) ? esc_html( $mu_info['Title'] ) : esc_html( str_replace( pathinfo( $disabled_mu, PATHINFO_EXTENSION ), '.php',$disabled_mu ) ); ?></strong>
            <div class="row-actions visible">
              <?php echo wp_kses( implode( ' | ',$actions ),$kses_args ); ?>
            </div>
          </td>
          <td class="column-description desc">
            <div class="plugin-description"><?php echo isset( $mu_info['Description'] ) ? esc_html( $mu_info['Description'] ) : ''; ?></div>
            <div><?php echo wp_kses( implode( ' | ', $plugin_meta ),$kses_args ); ?></div>
          </td>
        </tr>
        <?php
        }
      }
    }
  }
} );

add_action( 'in_admin_header',function(){
  ?>
  <script id="mu-plugin-manager">
  function eos_mupm_init(){
    var url='<?php echo esc_js( admin_url( 'plugins.php?plugin_status=mustuse' ) ); ?>';
    history.pushState({id: 'MU Plugin Manager',source:'mu-plugin-manager'},document.title,url);
  }
  eos_mupm_init();
  </script>
  <style id="mu-manager-css">
  #the-list tr[data-slug="mu-manager"],#the-list tr[data-slug="mu-manager-php"]{display:none !important}
  </style>
  <?php
} );
