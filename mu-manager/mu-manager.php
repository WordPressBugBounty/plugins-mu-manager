<?php
/*
Plugin Name: Mu Manager
Description: It lets you disable, enable, delete, download, and edit mu-plugins like you do with all other standard plugins.
Author: Jose Mortellaro
Author URI: https://josemortellaro.com
Plugin URI: https://josemortellaro.com
Domain Path: /languages/
Text Domain: mu-manager
Version: 0.0.7
*/
/*  This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/
defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

//Definitions
define( 'MU_PLUGIN_MANAGER_PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );

require_once MU_PLUGIN_MANAGER_PLUGIN_DIR . '/admin/mupm-editor.php';
register_deactivation_hook( __FILE__, 'eos_mupm_delete_all_editor_bridges' );

if( is_admin() && isset( $_GET['plugin_status'] ) && 'mustuse' === sanitize_text_field( $_GET['plugin_status'] ) ){
  // Load backend scripts only in the mu-plugins page.
  require_once MU_PLUGIN_MANAGER_PLUGIN_DIR . '/admin/mupm-admin.php';
}
