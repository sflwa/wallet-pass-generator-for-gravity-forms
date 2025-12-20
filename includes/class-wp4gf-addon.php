<?php
GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {
    protected $_version = '1.0.0';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'wallet-pass-generator-for-gravity-forms';
    protected $_path = 'wallet-pass-generator-for-gravity-forms/wallet-pass-generator.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Wallet Pass Generator';
    protected $_short_title = 'Wallet Pass';

    // Global Plugin Settings
    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Apple Certificate Settings', 'wallet-pass-generator-for-gravity-forms' ),
                'fields' => array(
                    array(
                        'name'     => 'wp4gf_pass_type_id',
                        'label'    => esc_html__( 'Pass Type ID', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'     => 'text',
                        'class'    => 'medium',
                    ),
                    array(
                        'name'     => 'wp4gf_team_id',
                        'label'    => esc_html__( 'Team ID', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'     => 'text',
                        'class'    => 'small',
                    ),
                    array(
                        'name'     => 'wp4gf_p12_path',
                        'label'    => esc_html__( 'Absolute Server Path to .p12', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'     => 'text',
                        'class'    => 'large',
                        'description' => 'Example: /home/user/secure/cert.p12',
                    ),
                    array(
                        'name'     => 'wp4gf_p12_password',
                        'label'    => esc_html__( 'Certificate Password', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'     => 'text',
                        'input_type' => 'password',
                    ),
                ),
            ),
        );
    }

    // Form-Specific Settings
    public function form_settings_fields( $form ) {
        return array(
            array(
                'title'  => esc_html__( 'Wallet Pass Configuration', 'wallet-pass-generator-for-gravity-forms' ),
                'fields' => array(
                    array(
                        'name'    => 'wp4gf_enabled',
                        'label'   => esc_html__( 'Enable Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'    => 'toggle',
                    ),
                    array(
                        'name'    => 'wp4gf_pass_style',
                        'label'   => esc_html__( 'Pass Style', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'    => 'select',
                        'choices' => array(
                            array( 'label' => 'Event Ticket', 'value' => 'eventTicket' ),
                            array( 'label' => 'Generic', 'value' => 'generic' ),
                        ),
                    ),
                ),
            ),
        );
    }
}
