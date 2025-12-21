<?php
/**
 * Factory class for Wallet Pass Generator v1.2.4.
 * Enforces conditional field display based on label presence.
 */
class WP4GF_PKPass_Factory {

	public static function generate( $entry, $form ) {
		$addon         = WP4GF_Addon::get_instance();
		$settings      = $addon->get_plugin_settings();
		$form_settings = $addon->get_form_settings( $form );

		require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
		$pass = new \WP4GF\PKPass\PKPass( $settings['wp4gf_p12_path'], $settings['wp4gf_p12_password'] );

		// Image Handling
		$assets_path = plugin_dir_path( __FILE__ ) . '../assets/';
		$pass->addFile( ( ! empty( $form_settings['wp4gf_logo_path'] ) && file_exists( $form_settings['wp4gf_logo_path'] ) ) ? $form_settings['wp4gf_logo_path'] : $assets_path . 'logo.png', 'logo.png' );
		$pass->addFile( ( ! empty( $form_settings['wp4gf_icon_path'] ) && file_exists( $form_settings['wp4gf_icon_path'] ) ) ? $form_settings['wp4gf_icon_path'] : $assets_path . 'icon.png', 'icon.png' );

		// Build Pass structure
		$json_data = array(
			'formatVersion'      => 1,
			'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
			'teamIdentifier'     => $settings['wp4gf_team_id'],
			'serialNumber'       => 'wp4gf_' . $entry['id'],
			'organizationName'   => get_bloginfo( 'name' ),
			'description'        => 'Wallet Pass',
			'generic'            => array()
		);

		// Helper to conditionally add fields
		$add_field = function( $type, $key_suffix, $id_prefix ) use ( &$json_data, $addon, $form, $entry, $form_settings ) {
			$label = rgar( $form_settings, 'wp4gf_lbl_' . $key_suffix );
			if ( empty( $label ) ) return;

			$val_id = rgar( $form_settings, 'wp4gf_val_' . $key_suffix );
			$value = is_numeric( $val_id ) ? $addon->get_field_value( $form, $entry, $val_id ) : GFCommon::replace_variables( $val_id, $form, $entry );
			
			$json_data['generic'][$type . 'Fields'][] = array( 'key' => $id_prefix . '1', 'label' => $label, 'value' => (string)$value );
		};

		// 1. Primary (Required - Always added)
		$p_lbl = rgar( $form_settings, 'wp4gf_lbl_primary' );
		$p_val = $addon->get_field_value( $form, $entry, rgar( $form_settings, 'wp4gf_val_primary' ) );
		$json_data['generic']['primaryFields'][] = array( 'key' => 'p1', 'label' => $p_lbl, 'value' => (string)$p_val );

		// 2. Optional Fields
		$add_field( 'header',    'header',    'h' );
		$add_field( 'secondary', 'secondary', 's' );
		$add_field( 'auxiliary', 'auxiliary', 'a' );
		$add_field( 'back',      'back',      'b' );

		// Barcode
		$barcode_msg = GFCommon::replace_variables( rgar( $form_settings, 'wp4gf_barcode_message' ), $form, $entry );
		if ( ! empty( $barcode_msg ) ) {
			$json_data['barcodes'] = array( array( 'format' => 'PKBarcodeFormatQR', 'message' => (string)$barcode_msg, 'messageEncoding' => 'iso-8859-1' ) );
		}

		$pass->setJSON( json_encode( $json_data ) );
		return $pass->create();
	}
}
