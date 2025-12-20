<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Extends the Gravity Forms Add-On Framework.
 */

GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {

    protected $_version = '1.0.0';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'wallet-pass-generator-for-gravity-forms';
    protected $_path = 'wallet-pass-generator-for-gravity-forms/wallet-pass-generator-for-gravity-forms.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Wallet Pass Generator';
    protected $_short_title = 'Wallet Pass';

    private static $_instance = null;

    /**
     * Singleton instance.
     */
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Initialize frontend hooks and AJAX listeners.
     */
    public function init() {
        parent::init();

        // Custom Merge Tag Hooks
        add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
        add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );

        // AJAX Download Handler
        add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
        add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
    }

    /**
     * Global Plugin Settings (Apple Credentials).
     */
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
                        'required' => true,
                    ),
                    array(
                        'name'     => 'wp4gf_team_id',
                        'label'    => esc_html__( 'Team ID', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'     => 'text',
                        'class'    => 'small',
                        'required' => true,
                    ),
                    array(
                        'name'     => 'wp4gf_p12_path',
                        'label'    => esc_html__( 'Absolute Server Path to .p12', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'     => 'text',
                        'class'    => 'large',
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

    /**
     * Form-Specific Settings with Field Mapping.
     */
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
                    // Dynamic Field Mapping
                    array(
                        'name'      => 'wp4gf_field_map',
                        'label'     => esc_html__( 'Field Mapping', 'wallet-pass-generator-for-gravity-forms' ),
                        'type'      => 'field_map',
                        'field_map' => array(
                            array( 'name' => 'primary_value',   'label' => 'Guest Name', 'required' => true ),
                            array( 'name' => 'secondary_value', 'label' => 'Check-in Date', 'required' => false ),
                            array( 'name' => 'barcode_value',   'label' => 'QR Code Data', 'required' => true ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Merge Tag Dropdown UI.
     */
    public function wp4gf_add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
        $merge_tags[] = array( 'label' => 'Wallet Pass Download Link', 'tag' => '{wp4gf_download_link}' );
        return $merge_tags;
    }

    /**
     * Merge Tag Replacement.
     */
    public function wp4gf_replace_download_link( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
        $tag = '{wp4gf_download_link}';
        if ( strpos( $text, $tag ) === false || empty( $entry ) ) {
            return $text;
        }

        $url = add_query_arg( array(
            'action'   => 'wp4gf_download_pass',
            'entry_id' => $entry['id'],
            'nonce'    => wp_create_nonce( 'wp4gf_download_' . $entry['id'] )
        ), admin_url( 'admin-ajax.php' ) );

        $link = sprintf( '<a href="%s" class="wp4gf-btn">%s</a>', esc_url( $url ), __( 'Download Pass', 'wallet-pass-generator-for-gravity-forms' ) );
        return str_replace( $tag, $link, $text );
    }

    /**
     * Download Handler.
     */
    public function wp4gf_handle_pass_download() {
        $entry_id = rgget( 'entry_id' );
        $nonce    = rgget( 'nonce' );

        if ( ! wp_verify_nonce( $nonce, 'wp4gf_download_' . $entry_id ) ) {
            wp_die( 'Unauthorized.' );
        }

        $entry = GFAPI::get_entry( $entry_id );
        $form  = GFAPI::get_form( $entry['form_id'] );

        $pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );

        header( 'Content-Type: application/vnd.apple.pkpass' );
        header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
        echo $pass_data;
        exit;
    }
}
