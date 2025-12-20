<?php
/**
 * Factory class to generate the .pkpass binary.
 */
class WP4GF_PKPass_Factory {

    /**
     * Generate the pass using entry data and global settings.
     * Cites:
     */
    public static function generate( $entry, $form ) {
        $addon    = WP4GF_Addon::get_instance();
        $settings = $addon->get_plugin_settings();
        
        // Include the signing library
        if ( ! class_exists( 'PHP_PKPass\PKPass' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . '../lib/PHP-PKPass/PKPass.php' );
        }
        
        $pass = new PHP_PKPass\PKPass();
        $pass->setCertificate( $settings['wp4gf_p12_path'] );
        $pass->setCertificatePassword( $settings['wp4gf_p12_password'] );

        // 1. Map QR Code Content (Example: Using a hidden field or entry ID)
        $qr_content = $entry['id']; // You can customize this with any entry field

        // 2. Build the JSON Structure
        $json_data = array(
            'formatVersion'      => 1,
            'passTypeIdentifier' => $settings['wp4gf_pass_type_id'],
            'teamIdentifier'     => $settings['wp4gf_team_id'],
            'serialNumber'       => 'wp4gf_' . $entry['id'],
            'organizationName'   => get_bloginfo( 'name' ),
            'description'        => 'Check-in Pass',
            'backgroundColor'    => 'rgb(255, 255, 255)',
            'foregroundColor'    => 'rgb(0, 0, 0)',
            
            // Barcode definition
            'barcodes' => array(
                array(
                    'format'          => 'PKBarcodeFormatQR',
                    'message'         => (string) $qr_content,
                    'messageEncoding' => 'iso-8859-1',
                    'altText'         => 'ID: ' . $qr_content
                )
            ),

            // Pass Content Mapping (Generic Style)
            'generic' => array(
                'primaryFields' => array(
                    array(
                        'key'   => 'guest_name',
                        'label' => 'GUEST',
                        'value' => rgar( $entry, '1' ) // Field ID 1 (e.g., Name)
                    )
                ),
                'secondaryFields' => array(
                    array(
                        'key'   => 'date',
                        'label' => 'CHECK-IN DATE',
                        'value' => date( 'M d, Y' )
                    )
                )
            )
        );

        $pass->setJSON( json_encode( $json_data ) );
        return $pass->create();
    }
}
