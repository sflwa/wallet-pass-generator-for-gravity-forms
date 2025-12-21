<?php
/**
 * Factory class to generate the .pkpass binary.
 */
class WP4GF_PKPass_Factory {

    public static function generate( $entry, $form ) {
        $addon = WP4GF_Addon::get_instance();
        $settings = $addon->get_plugin_settings();
        $form_settings = $addon->get_form_settings( $form );

        // 1. Resolve field mapping
        $map = rgar( $form_settings, 'wp4gf_field_map' );
        $primary   = $addon->get_field_value( $form, $entry, $map['primary_value'] );
        $secondary = $addon->get_field_value( $form, $entry, $map['secondary_value'] );
        $auxiliary = $addon->get_field_value( $form, $entry, $map['auxiliary_value'] );
        $header    = $addon->get_field_value( $form, $entry, $map['header_value'] );
        $back      = $addon->get_field_value( $form, $entry, $map['back_value'] );

        // 2. Process dynamic QR message
        $barcode_raw = rgar( $form_settings, 'wp4gf_barcode_message' );
        $barcode_msg = GFCommon::replace_variables( $barcode_raw, $form, $entry );

        require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
        $pass = new PHP_PKPass\PKPass();
        $pass->setCertificate( $settings['wp4gf_p12_path'] );
        $pass->setCertificatePassword( $settings['wp4gf_p12_password'] );

        // 3. Add Custom Images
        if ( ! empty( $form_settings['wp4gf_logo_path'] ) )  { $pass->addFile( $form_settings['wp4gf_logo_path'], 'logo.png' ); }
        if ( ! empty( $form_settings['wp4gf_icon_path'] ) )  { $pass->addFile( $form_settings['wp4gf_icon_path'], 'icon.png' ); }
        if ( ! empty( $form_settings['wp4gf_thumb_path'] ) ) { $pass->addFile( $form_settings['wp4gf_thumb_path'], 'thumbnail.png' ); }

        // 4. Build JSON structure
        $json_data = array(
            'formatVersion'      => 1,
            'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
            'teamIdentifier'     => $settings['wp4gf_team_id'],
            'serialNumber'       => 'wp4gf_' . $entry['id'],
            'organizationName'   => get_bloginfo( 'name' ),
            'description'        => 'Generic Pass',
            'barcodes' => array(
                array( 'format' => 'PKBarcodeFormatQR', 'message' => (string) $barcode_msg, 'messageEncoding' => 'iso-8859-1' )
            ),
            'generic' => array(
                'headerFields'    => array( array( 'key' => 'h1', 'label' => 'INFO', 'value' => $header ) ),
                'primaryFields'   => array( array( 'key' => 'p1', 'label' => 'GUEST', 'value' => $primary ) ),
                'secondaryFields' => array( array( 'key' => 's1', 'label' => 'DATE',  'value' => $secondary ) ),
                'auxiliaryFields' => array( array( 'key' => 'a1', 'label' => 'TYPE',  'value' => $auxiliary ) ),
                'backFields'      => array( array( 'key' => 'b1', 'label' => 'DETAILS', 'value' => $back ) )
            )
        );

        $pass->setJSON( json_encode( $json_data ) );
        return $pass->create();
    }
}
