<?php
/**
 * PKPass Factory for Wallet Pass Generator.
 * Version: 1.4.4
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WP4GF_PKPass_Factory {

	/**
	 * Generate the Apple Wallet Pass binary data.
	 *
	 * @param array $entry The Gravity Forms entry object.
	 * @param array $form  The Gravity Forms form object.
	 * @throws Exception   If certificate is missing or OpenSSL fails.
	 * @return string      The binary content of the .pkpass file.
	 */
	public static function generate( $entry, $form ) {
		$addon         = WP4GF_Addon::get_instance();
		$settings      = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		// Retrieve the path saved during the upload process in global settings
		$p12_path     = rgar( $settings, 'wp4gf_p12_path' );
		$p12_password = rgar( $settings, 'wp4gf_p12_password' );

		if ( empty( $p12_path ) || ! file_exists( $p12_path ) ) {
			throw new Exception( esc_html__( 'Apple .p12 Certificate not found. Please upload it in the Wallet Pass global settings.', 'wallet-pass-generator-for-gravity-forms' ) );
		}

		$certs = array();
		if ( ! openssl_pkcs12_read( file_get_contents( $p12_path ), $certs, $p12_password ) ) {
			throw new Exception( 'OpenSSL Error: ' . openssl_error_string() );
		}

		// Include the library using plugin_dir_path for cross-platform compatibility
		require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
		$pass = new \WP4GF\PKPass\PKPass( $p12_path, $p12_password );

		$assets_path = plugin_dir_path( __FILE__ ) . '../assets/';
		$pass->addFile( self::resolve_image_path( rgar( $form_settings, 'wp4gf_logo_path' ), $assets_path . 'logo.png' ), 'logo.png' );
		$pass->addFile( self::resolve_image_path( rgar( $form_settings, 'wp4gf_icon_path' ), $assets_path . 'icon.png' ), 'icon.png' );

		$json_data = array(
			'formatVersion'      => 1,
			'passTypeIdentifier' => rgar( $settings, 'wp4gf_pass_type_id' ),
			'teamIdentifier'     => rgar( $settings, 'wp4gf_team_id' ),
			'serialNumber'       => 'wp4gf_' . $entry['id'],
			'organizationName'   => get_bloginfo( 'name' ),
			'description'        => 'Wallet Pass',
			'generic'            => array(
				'primaryFields'   => array(),
				'headerFields'    => array(),
				'secondaryFields' => array(),
				'auxiliaryFields' => array(),
				'backFields'      => array()
			)
		);

		$process_field = function( $area ) use ( &$json_data, $addon, $form, $entry, $form_settings ) {
			$label = rgar( $form_settings, 'wp4gf_lbl_' . $area );
			$source = rgar( $form_settings, 'wp4gf_src_' . $area );
			
			if ( $source === 'custom' ) {
				$value = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_txt_' . $area ), $form, $entry );
			} else {
				$field_id = rgar( $form_settings, 'wp4gf_val_' . $area );
				$value = $addon->get_field_value( $form, $entry, $field_id );
			}

			if ( ! empty( $value ) ) {
				$json_data['generic'][$area . 'Fields'][] = array(
					'key'   => 'key_' . $area,
					'label' => $label,
					'value' => (string)$value
				);
			}
		};

		foreach ( array( 'primary', 'header', 'secondary', 'auxiliary' ) as $area ) {
			$process_field( $area );
		}

		$back_val = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_val_back' ), $form, $entry );
		if ( ! empty( $back_val ) ) {
			$json_data['generic']['backFields'][] = array(
				'key' => 'back_field', 
				'label' => rgar( $form_settings, 'wp4gf_lbl_back' ) ?: 'Info', 
				'value' => (string)$back_val 
			);
		}

		$barcode_msg = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_barcode_message' ), $form, $entry );
		if ( ! empty( $barcode_msg ) ) {
			$json_data['barcodes'] = array( array( 
				'format' => 'PKBarcodeFormatQR', 
				'message' => (string)$barcode_msg, 
				'messageEncoding' => 'iso-8859-1' 
			) );
		}

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}

	/**
	 * Resolves a URL to a local server path for image inclusion.
	 *
	 * @param string $url      The image URL from settings.
	 * @param string $fallback The local fallback path.
	 * @return string          The resolved local file path.
	 */
	private static function resolve_image_path( $url, $fallback ) {
		if ( empty( $url ) ) {
			return $fallback;
		}
		
		$upload_dir = wp_upload_dir();
		
		// Convert URL to local path based on the WordPress uploads directory
		$path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
		
		// If the replacement didn't work or file doesn't exist, try manual resolution
		if ( ! file_exists( $path ) ) {
			$path = trailingslashit( ABSPATH ) . str_replace( home_url( '/' ), '', $url );
		}
		
		return file_exists( $path ) ? $path : $fallback;
	}
}
