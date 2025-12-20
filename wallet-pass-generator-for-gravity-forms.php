<?php
/**
 * Plugin Name: Wallet Pass Generator for Gravity Forms
 * Plugin URI:  https://yourdomain.com
 * Description: Generate Apple Wallet passes from Gravity Forms submissions locally.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: wallet-pass-generator-for-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix check and Bootstrap
add_action( 'gform_loaded', array( 'WP4GF_Bootstrap', 'load' ), 5 );

class WP4GF_Bootstrap {
    public static function load() {
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( __DIR__ . '/class-wp4gf-addon.php' );
        require_once( __DIR__ . '/pkpass-factory.php' );

        GFAddOn::register( 'WP4GF_Addon' );
    }
}
