<?php
/**
 * Plugin Name: Simple Dynamic Form
 * Description: A dynamic form builder plugin.
 * Version: 1.0.0
 * Author: Shreyansh Pandey
 * Text Domain: simple-dynamic-form
 */


// Main entry point of plugin


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SDF_VERSION', '1.0.0' );
define( 'SDF_PLUGIN_FILE', __FILE__ );
define( 'SDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );



// Include required plugin files
require_once SDF_PLUGIN_DIR . 'includes/class-sdf-fields.php';
require_once SDF_PLUGIN_DIR . 'includes/class-sdf-activator.php';
require_once SDF_PLUGIN_DIR . 'includes/class-sdf-admin.php';
require_once SDF_PLUGIN_DIR . 'includes/class-sdf-shortcode.php';
require_once SDF_PLUGIN_DIR . 'includes/class-sdf-submission.php';


// Activation hook
register_activation_hook(
    __FILE__,
    array( 'SDF_Activator', 'activate')
);

// Initialize plugin components
SDF_Admin::init();
SDF_Shortcode::init();
SDF_Submission::init();
