<?php
/**
 * Factory class for Wallet Pass Generator v1.2.2.
 * Processes individual section settings into a signed .pkpass binary.
 */
class WP4GF_PKPass_Factory {

	/**
	 * Main generation method.
	 */
	public static function generate( $entry, $form ) {
		$addon         = WP4GF_Addon::get_instance();
		$settings      = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		// 1. Retrieve individual field data using the new sectioned keys
		$header    = self::get_field_data( $addon, $form, $entry, $form_settings, 'header' );
		$primary   = self::get_field_data( $addon, $form, $entry, $form_settings, 'primary' );
		$secondary = self::get_field_data( $addon, $form, $entry, $form_settings, 'secondary' );
		$auxiliary = self::get_field_data( $addon, $form, $entry, $form_settings, 'auxiliary' );

		// 2. Process Back Field (Textarea support)
		$back_lbl = rgar( $form_settings, 'wp4gf_lbl_back' ) ?: 'DETAILS';
		$back_val = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_val_back' ), $form, $entry );

		// 3. Process QR Code
		$barcode_msg = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_barcode_message' ), $form, $entry );

		// 4. Initialize PKPass Library
		require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
		$pass = new \WP4GF\PKPass\PKPass( $settings['wp4gf_p12_path'], $settings['wp4gf_p12_password'] );

		// 5. Handle Images with Fallbacks
		$assets_path = plugin_dir_path( __FILE__ ) . '../assets/';

		$logo_file  = ( ! empty( $form_settings['wp4gf_logo_path'] ) && file_exists( $form_settings['wp4gf_logo_path'] ) ) ? $form_settings['wp4gf_logo_path'] : $assets_path . 'logo.png';
		$icon_file  = ( ! empty( $form_settings['wp4gf_icon_path'] ) && file_exists( $form_settings['wp4gf_icon_path'] ) ) ? $form_settings['wp4gf_icon_path'] : $assets_path . 'icon.png';
		
		$pass->addFile( $logo_file, 'logo.png' );
		$pass->addFile( $icon_file, 'icon.png' );

		if ( ! empty( $form_settings['wp4gf_thumb_path'] ) && file_exists( $form_settings['wp4gf_thumb_path'] ) ) {
			$pass->addFile( $form_settings['wp4gf_thumb_path'], 'thumbnail.png' );
		}

		// 6. Build Pass JSON
		$json_data = array(
			'formatVersion'      => 1,
			'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
			'teamIdentifier'     => $settings['wp4gf_team_id'],
			'serialNumber'       => 'wp4gf_' . $entry['id'],
			'organizationName'   => get_bloginfo( 'name' ),
			'description'        => 'Wallet Pass',
			'generic' => array(
				'headerFields'    => array( array( 'key' => 'h1', 'label' => $header['label'] ?: 'INFO',    'value' => (string) $header['value'] ) ),
				'primaryFields'   => array( array( 'key' => 'p1', 'label' => $primary['label'] ?: 'GUEST',  'value' => (string) $primary['value'] ) ),
				'secondaryFields' => array( array( 'key' => 's1', 'label' => $secondary['label'] ?: 'DATE', 'value' => (string) $secondary['value'] ) ),
				'auxiliaryFields' => array( array( 'key' => 'a1', 'label' => $auxiliary['label'] ?: 'TYPE', 'value' => (string) $auxiliary['value'] ) ),
				'backFields'      => array( array( 'key' => 'b1', 'label' => $back_lbl, 'value' => (string) $back_val ) )
			)
		);

		// Barcode logic
		if ( ! empty( $barcode_msg ) ) {
			$json_data['barcodes'] = array(
				array( 'format' => 'PKBarcodeFormatQR', 'message' => (string) $barcode_msg, 'messageEncoding' => 'iso-8859-1' )
			);
		}

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}

	/**
	 * Helper to retrieve label and value from individual sectioned settings.
	 */
	private static function get_field_data( $addon, $form, $entry, $settings, $key ) {
		$label = rgar( $settings, 'wp4gf_lbl_' . $key );
		$value_id = rgar( $settings, 'wp4gf_val_' . $key );
		$value = '';

		if ( is_numeric( $value_id ) ) {
			// It is a form field ID
			$value = $addon->get_field_value( $form, $entry, $value_id );
		} else {
			// It is a static string or merge tag
			$value = GFCommon::replace_variables( $value_id, $form, $entry );
		}

		return array( 'label' => $label, 'value' => $value );
	}
}
