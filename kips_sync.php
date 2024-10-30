<?php
/*
Plugin Name:  Kips Sync
Plugin URI:   https://kips.io
Description:  Synchronisation des flux produits / commandes avec Kips.
Version:      1.0.6
Author:       Kips
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  kips
Domain Path:  /languages
*/

// Little security
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// Define KIPS_PLUGIN_FILE.
if ( ! defined( 'KIPS_PLUGIN_FILE' ) ) {
	define( 'KIPS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'KIPS_PLUGIN_VERSION' ) ) {
	$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
	$plugin_version = $plugin_data['Version'];
	define( 'KIPS_PLUGIN_VERSION', $plugin_version );
}

// register_activation_hook( __FILE__, array( 'Kips', 'plugin_activation' ) );
// register_deactivation_hook( __FILE__, array( 'Kips', 'plugin_deactivation' ) );

// Include the main Kips class.
if ( ! class_exists( 'Kips' ) ) {
	include_once dirname( __FILE__) . '/includes/class.kips.php';
}

Kips::instance(); // instantiate before initializing hooks
Kips::init_hooks();
