<?php
class WP4GF_PKPass_Factory {

    public static function generate( $entry, $form ) {
        $addon    = WP4GF_Addon::get_instance();
        $settings = $addon->get_plugin_settings();
        $form_settings = $addon->get_form_settings( $form );
        
        // Retrieve dynamic field mapping
        $mapped_fields = rgar( $form_settings, 'wp4gf_field_map' );
        $guest_name    = $addon->get_field_value( $form, $entry, $mapped_fields['primary_value'] );
        $barcode_data  = $addon->get_field_value( $form, $entry, $mapped_fields['barcode_value'] );

        if ( ! class_exists( 'PHP_PKPass\PKPass' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
        }

        $pass = new PHP_PKPass\PKPass();
        $pass->setCertificate( $settings['wp4gf_p12_path'] );
        $pass->setCertificatePassword( $settings['wp4gf_p12_password'] );

        $json_data = array(
            'formatVersion'      => 1,
            'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
            'teamIdentifier'     => $settings['wp4gf_team_id'],
            'serialNumber'       => 'wp4gf_' . $entry['id'],
            'organizationName'   => get_bloginfo( 'name' ),
            'description'        => 'Wallet Pass',
            'barcodes' => array(
                array(
                    'format'          => 'PKBarcodeFormatQR',
                    'message'         => (string) $barcode_data, // Dynamic QR Code Data
                    'messageEncoding' => 'iso-8859-1',
                )
            ),
            'generic' => array(
                'primaryFields' => array(
                    array( 'key' => 'name', 'label' => 'GUEST', 'value' => $guest_name )
                )
            )
        );

        $pass->setJSON( json_encode( $json_data ) );
        return $pass->create();
    }
}
