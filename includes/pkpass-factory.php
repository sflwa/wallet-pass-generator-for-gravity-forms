<?php
class WP4GF_PKPass_Factory {

	public static function generate( $entry, $form ) {
		$addon         = WP4GF_Addon::get_instance();
		$settings      = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		$p12_path     = rgar( $settings, 'wp4gf_p12_path' );
		$p12_password = rgar( $settings, 'wp4gf_p12_password' );

		if ( ! file_exists( $p12_path ) ) throw new Exception( 'Certificate file not found.' );

		$certs = array();
		if ( ! openssl_pkcs12_read( file_get_contents( $p12_path ), $certs, $p12_password ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( 'OpenSSL Error: ' . openssl_error_string() );
		}

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
			// BACK TO SQUARE QR
			$json_data['barcodes'] = array( array( 
				'format' => 'PKBarcodeFormatQR', 
				'message' => (string)$barcode_msg, 
				'messageEncoding' => 'iso-8859-1' 
			) );
		}

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}

	private static function resolve_image_path( $url, $fallback ) {
		if ( empty( $url ) ) return $fallback;
		$path = str_replace( content_url(), WP_CONTENT_DIR, $url );
		if ( ! file_exists( $path ) ) $path = ABSPATH . str_replace( home_url( '/' ), '', $url );
		return file_exists( $path ) ? $path : $fallback;
	}
}
