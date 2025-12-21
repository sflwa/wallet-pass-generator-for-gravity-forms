<?php
class WP4GF_PKPass_Factory {

	public static function generate( $entry, $form ) {
		$addon         = WP4GF_Addon::get_instance();
		$settings      = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		$p12_path     = rgar( $settings, 'wp4gf_p12_path' );
		$p12_password = rgar( $settings, 'wp4gf_p12_password' );

		if ( ! file_exists( $p12_path ) ) throw new Exception( 'Certificate file not found.' );

		// Validate Cert
		$certs = array();
		if ( ! openssl_pkcs12_read( file_get_contents( $p12_path ), $certs, $p12_password ) ) {
			throw new Exception( 'OpenSSL Error: ' . openssl_error_string() );
		}

		require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
		$pass = new \WP4GF\PKPass\PKPass( $p12_path, $p12_password );

		// Images
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
			'generic'            => array()
		);

		$process_area = function( $area, $type, $key ) use ( &$json_data, $addon, $form, $entry, $form_settings ) {
			$label = rgar( $form_settings, 'wp4gf_lbl_' . $area );
			if ( empty( $label ) && $area !== 'primary' ) return;

			$source = rgar( $form_settings, 'wp4gf_src_' . $area );
			$value  = ( $source === 'custom' ) ? 
				GFCommon::replace_variables( rgar( $form_settings, "wp4gf_txt_{$area}" ), $form, $entry ) : 
				$addon->get_field_value( $form, $entry, rgar( $form_settings, "wp4gf_val_{$area}" ) );

			$json_data['generic'][$type . 'Fields'][] = array( 'key' => $key, 'label' => $label, 'value' => (string)$value );
		};

		$process_area( 'primary', 'primary', 'p1' );
		$process_area( 'header', 'header', 'h1' );
		$process_area( 'secondary', 'secondary', 's1' );
		$process_area( 'auxiliary', 'auxiliary', 'a1' );

		$back_lbl = rgar( $form_settings, 'wp4gf_lbl_back' ) ?: 'DETAILS';
		$back_val = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_val_back' ), $form, $entry );
		$json_data['generic']['backFields'][] = array( 'key' => 'b1', 'label' => $back_lbl, 'value' => (string)$back_val );

		$barcode_msg = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_barcode_message' ), $form, $entry );
		if ( ! empty( $barcode_msg ) ) {
			$json_data['barcodes'] = array( array( 'format' => 'PKBarcodeFormatQR', 'message' => (string)$barcode_msg, 'messageEncoding' => 'iso-8859-1' ) );
		}

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}

	private static function resolve_image_path( $url, $fallback = '' ) {
		if ( empty( $url ) ) return $fallback;
		$local_path = str_replace( content_url(), WP_CONTENT_DIR, $url );
		if ( ! file_exists( $local_path ) ) $local_path = ABSPATH . str_replace( home_url( '/' ), '', $url );
		return file_exists( $local_path ) ? $local_path : $fallback;
	}
}
