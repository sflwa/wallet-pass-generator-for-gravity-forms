<?php
/**
 * Plugin Name: Wallet Pass Generator for Gravity Forms
 * Plugin URI:  https://github.com/sflwa/wallet-pass-generator-for-gravity-forms/
 * Description: Generate Apple Wallet passes from Gravity Forms submissions locally.
 * Version:     1.4.3
 * Author:      South Florida Web Advisors
 * Text Domain: wallet-pass-generator-for-gravity-forms
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bootstrap the Wallet Pass Generator Add-On.
 * Uses the wp4gf prefix for all functions and classes.
 */
add_action( 'gform_loaded', array( 'WP4GF_Bootstrap', 'load' ), 5 );

class WP4GF_Bootstrap {

    /**
     * Load the required files from the includes folder.
     * Cites:
     */
    public static function load() {
        // Ensure Gravity Forms Add-On Framework is available
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        // Define the path to the includes directory
        $includes_path = plugin_dir_path( __FILE__ ) . 'includes/';

        // Include the Add-On class and the Factory engine
        require_once( $includes_path . 'class-wp4gf-addon.php' );
        require_once( $includes_path . 'pkpass-factory.php' );

        // Register the Add-On with Gravity Forms
        GFAddOn::register( 'WP4GF_Addon' );
    }
}
