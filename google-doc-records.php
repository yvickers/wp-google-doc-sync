<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.redclayinteractive.com
 * @since             1.0.0
 * @package           Google_Doc_Records
 *
 * @wordpress-plugin
 * Plugin Name:       Google Document Records
 * Plugin URI:        http://www.redclayinteractive.com/google-doc-records-uri/
 * Description:       Syncs records of a custom post type to a google document so that the records can be managed via google
 * Version:           1.0.0
 * Author:            Red Clay Interactive
 * Author URI:        http://www.redclayinteractive.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       google-doc-records
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-google-doc-records-activator.php
 */
function activate_google_doc_records() {
	require plugin_dir_path( __FILE__ ) . 'includes/class-google-doc-records-activator.php';
	Google_Doc_Records_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-google-doc-records-deactivator.php
 */
function deactivate_google_doc_records() {
	require plugin_dir_path( __FILE__ ) . 'includes/class-google-doc-records-deactivator.php';
	Google_Doc_Records_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_google_doc_records' );
register_deactivation_hook( __FILE__, 'deactivate_google_doc_records' );

/**
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-google-doc-records.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_google_doc_records() {

	$plugin = new Google_Doc_Records();
	$plugin->run();

}
if(is_admin()){
	run_google_doc_records();
}