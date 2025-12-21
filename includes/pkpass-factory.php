<?php
class WP4GF_PKPass_Factory {

	public static function generate( $entry, $form ) {
		$addon = WP4GF_Addon::get_instance();
		$settings = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		// 1. Map dynamic data
		$field_map = rgar( $form_settings, 'wp4gf_field_map' );
		$guest_name = $addon->get_field_value( $form, $entry, $field_map['primary_value'] );
		
		// 2. Process QR Code Data (Replace merge tags)
		$barcode_raw = rgar( $field_map, 'barcode_value' );
		$barcode_msg = GFCommon::replace_variables( $barcode_raw, $form, $entry );

		require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
		$pass = new PHP_PKPass\PKPass();
		$pass->setCertificate( $settings['wp4gf_p12_path'] );
		$pass->setCertificatePassword( $settings['wp4gf_p12_password'] );

		// 3. Add Custom Images
		if ( ! empty( $form_settings['wp4gf_logo_path'] ) ) { $pass->addFile( $form_settings['wp4gf_logo_path'], 'logo.png' ); }
		if ( ! empty( $form_settings['wp4gf_icon_path'] ) ) { $pass->addFile( $form_settings['wp4gf_icon_path'], 'icon.png' ); }

		$json_data = array(
			'formatVersion' => 1,
			'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
			'teamIdentifier' => $settings['wp4gf_team_id'],
			'serialNumber' => 'wp4gf_' . $entry['id'],
			'organizationName' => get_bloginfo( 'name' ),
			'description' => 'Check-in',
			'barcodes' => array(
				array( 'format' => 'PKBarcodeFormatQR', 'message' => (string)$barcode_msg, 'messageEncoding' => 'iso-8859-1' )
			),
			'generic' => array(
				'primaryFields' => array( array( 'key' => 'name', 'label' => 'GUEST', 'value' => $guest_name ) )
			)
		);

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}
}
