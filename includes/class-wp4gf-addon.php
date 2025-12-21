<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
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

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function init() {
        parent::init();
        add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
        add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );
        add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
        add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
    }

    /**
     * Global Plugin Settings for Apple Certificates.
     */
    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'Apple Certificate Settings', 'wallet-pass-generator-for-gravity-forms' ),
                'fields' => array(
                    array( 'name' => 'wp4gf_pass_type_id', 'label' => 'Pass Type ID', 'type' => 'text', 'class' => 'medium', 'required' => true ),
                    array( 'name' => 'wp4gf_team_id', 'label' => 'Team ID', 'type' => 'text', 'class' => 'small', 'required' => true ),
                    array( 'name' => 'wp4gf_p12_path', 'label' => 'Absolute Path to .p12', 'type' => 'text', 'class' => 'large' ),
                    array( 'name' => 'wp4gf_p12_password', 'label' => 'Cert Password', 'type' => 'text', 'input_type' => 'password' ),
                ),
            ),
        );
    }

    /**
     * Form Settings using Generic Pass Terminology.
     */
    public function form_settings_fields( $form ) {
        return array(
            array(
                'title'  => esc_html__( 'Wallet Pass Configuration', 'wallet-pass-generator-for-gravity-forms' ),
                'fields' => array(
                    array( 'name' => 'wp4gf_enabled', 'label' => 'Enable Wallet Pass', 'type' => 'toggle' ),
                    array(
                        'name'      => 'wp4gf_field_map',
                        'label'     => 'Generic Field Mapping',
                        'type'      => 'field_map',
                        'field_map' => array(
                            array( 'name' => 'primary_value',   'label' => 'Primary Value',   'required' => true ),
                            array( 'name' => 'secondary_value', 'label' => 'Secondary Value' ),
                            array( 'name' => 'auxiliary_value', 'label' => 'Auxiliary Value' ),
                            array( 'name' => 'header_value',    'label' => 'Header Value' ),
                            array( 'name' => 'back_value',      'label' => 'Back Value' ),
                        ),
                    ),
                    array(
                        'name'    => 'wp4gf_barcode_message',
                        'label'   => 'QR Code Message / URL',
                        'type'    => 'text',
                        'class'   => 'large',
                        'description' => 'Supports merge tags, e.g., https://site.com/checkin?id={entry_id}',
                    ),
                ),
            ),
            array(
                'title'  => esc_html__( 'Pass Images (Absolute Paths)', 'wallet-pass-generator-for-gravity-forms' ),
                'fields' => array(
                    array( 'name' => 'wp4gf_logo_path',  'label' => 'Logo (320x100 PNG)', 'type' => 'text', 'class' => 'large' ),
                    array( 'name' => 'wp4gf_icon_path',  'label' => 'Icon (58x58 PNG)',   'type' => 'text', 'class' => 'large' ),
                    array( 'name' => 'wp4gf_thumb_path', 'label' => 'Thumbnail (180x180 PNG)', 'type' => 'text', 'class' => 'large' ),
                ),
            ),
        );
    }

    public function wp4gf_add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
        $merge_tags[] = array( 'label' => 'Wallet Pass Download Link', 'tag' => '{wp4gf_download_link}' );
        return $merge_tags;
    }

    public function wp4gf_replace_download_link( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
        if ( strpos( $text, '{wp4gf_download_link}' ) === false || empty( $entry ) ) {
            return $text;
        }
        $url = add_query_arg( array( 'action' => 'wp4gf_download_pass', 'entry_id' => $entry['id'], 'nonce' => wp_create_nonce( 'wp4gf_download_' . $entry['id'] ) ), admin_url( 'admin-ajax.php' ) );
        $link = sprintf( '<a href="%s" class="wp4gf-download">%s</a>', esc_url( $url ), __( 'Download Apple Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ) );
        return str_replace( '{wp4gf_download_link}', $link, $text );
    }

    public function wp4gf_handle_pass_download() {
        $entry_id = rgget( 'entry_id' );
        if ( ! wp_verify_nonce( rgget( 'nonce' ), 'wp4gf_download_' . $entry_id ) ) { wp_die( 'Unauthorized.' ); }
        $entry = GFAPI::get_entry( $entry_id );
        $form = GFAPI::get_form( $entry['form_id'] );
        $pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );
        header( 'Content-Type: application/vnd.apple.pkpass' );
        header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
        echo $pass_data;
        exit;
    }
}
