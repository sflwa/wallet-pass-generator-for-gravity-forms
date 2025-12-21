<?php
/**
 * Factory class to generate the .pkpass binary.
 * Handles form data mapping and conditional QR codes.
 */
class WP4GF_PKPass_Factory {

	/**
	 * Generate the .pkpass file content.
	 */
	public static function generate( $entry, $form ) {
		$addon         = WP4GF_Addon::get_instance();
		$settings      = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		// 1. Resolve field mapping from the generic_map
		$map = rgar( $form_settings, 'wp4gf_generic_map' );
		
		$primary   = self::get_mapped_value( $addon, $form, $entry, $map, 'primary_value' );
		$secondary = self::get_mapped_value( $addon, $form, $entry, $map, 'secondary_value' );
		$auxiliary = self::get_mapped_value( $addon, $form, $entry, $map, 'auxiliary_value' );
		$header    = self::get_mapped_value( $addon, $form, $entry, $map, 'header_value' );
		$back      = self::get_mapped_value( $addon, $form, $entry, $map, 'back_value' );

		// 2. Process dynamic QR message
		$barcode_raw = rgar( $form_settings, 'wp4gf_barcode_message' );
		$barcode_msg = GFCommon::replace_variables( $barcode_raw, $form, $entry );

		// 3. Initialize the PKPass library
		require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
		$pass = new \WP4GF\PKPass\PKPass( $settings['wp4gf_p12_path'], $settings['wp4gf_p12_password'] );

		// 4. Handle Images with Fallbacks (prevents "Invalid Data" errors)
		$assets_path = plugin_dir_path( __FILE__ ) . '../assets/';

		// Logo Fallback
		if ( ! empty( $form_settings['wp4gf_logo_path'] ) && file_exists( $form_settings['wp4gf_logo_path'] ) ) {
			$pass->addFile( $form_settings['wp4gf_logo_path'], 'logo.png' );
		} else {
			$pass->addFile( $assets_path . 'logo.png', 'logo.png' );
		}

		// Icon Fallback (MANDATORY for Apple Wallet)
		if ( ! empty( $form_settings['wp4gf_icon_path'] ) && file_exists( $form_settings['wp4gf_icon_path'] ) ) {
			$pass->addFile( $form_settings['wp4gf_icon_path'], 'icon.png' );
		} else {
			$pass->addFile( $assets_path . 'icon.png', 'icon.png' );
		}

		// Thumbnail (Optional)
		if ( ! empty( $form_settings['wp4gf_thumb_path'] ) && file_exists( $form_settings['wp4gf_thumb_path'] ) ) {
			$pass->addFile( $form_settings['wp4gf_thumb_path'], 'thumbnail.png' );
		}

		// 5. Build JSON structure
		$json_data = array(
			'formatVersion'      => 1,
			'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
			'teamIdentifier'     => $settings['wp4gf_team_id'],
			'serialNumber'       => 'wp4gf_' . $entry['id'],
			'organizationName'   => get_bloginfo( 'name' ),
			'description'        => 'Generic Pass',
			'generic' => array(
				'headerFields'    => array( array( 'key' => 'h1', 'label' => 'INFO', 'value' => $header ) ),
				'primaryFields'   => array( array( 'key' => 'p1', 'label' => 'GUEST', 'value' => $primary ) ),
				'secondaryFields' => array( array( 'key' => 's1', 'label' => 'DATE',  'value' => $secondary ) ),
				'auxiliaryFields' => array( array( 'key' => 'a1', 'label' => 'TYPE',  'value' => $auxiliary ) ),
				'backFields'      => array( array( 'key' => 'b1', 'label' => 'DETAILS', 'value' => $back ) )
			)
		);

		// Issue 2 Fix: Only include the barcodes array if a message exists
		if ( ! empty( $barcode_msg ) ) {
			$json_data['barcodes'] = array(
				array( 
					'format'          => 'PKBarcodeFormatQR', 
					'message'         => (string) $barcode_msg, 
					'messageEncoding' => 'iso-8859-1' 
				)
			);
		}

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}

	/**
	 * Issue 1 Fix: Correctly retrieves mapped values from the generic_map.
	 */
	private static function get_mapped_value( $addon, $form, $entry, $map, $key ) {
		if ( empty( $map ) || ! is_array( $map ) ) {
			return '';
		}

		foreach ( $map as $setting ) {
			if ( rgar( $setting, 'key' ) === $key ) {
				$value = rgar( $setting, 'value' );
				
				// If numeric, it's a field ID; fetch the entry data
				if ( is_numeric( $value ) ) {
					return $addon->get_field_value( $form, $entry, $value );
				}
				
				// Otherwise, process as a string with merge tags
				return GFCommon::replace_variables( $value, $form, $entry );
			}
		}
		return '';
	}
}
