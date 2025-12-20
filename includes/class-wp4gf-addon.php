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
     * Singleton instance of the class.
     * Cites:
     */
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Initialize frontend hooks and AJAX listeners.
     * Cites:
     */
    public function init() {
        parent::init();

        // Register custom Merge Tag
        add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
        add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );

        // AJAX Handlers for secure download
        add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
        add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
    }

    /**
     * Define Global Plugin Settings (Apple Credentials).
     * Accessible via Forms > Settings > Wallet Pass.
     * Cites:
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
                        'description' => esc_html__( 'Secure location (e.g., /home/user/certs/cert.p12).', 'wallet-pass-generator-for-gravity-forms' ),
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
     * Define Form Settings (Per-Form Activation).
     * Accessible via Form Settings > Wallet Pass.
     * Cites:
     */
    public function form_settings_fields( $form ) {
        return array(
            array(
                'title'  => esc_html__( 'Wallet Pass Configuration', 'wallet-pass-generator-for-gravity-forms' ),
                'fields' => array(
                    array(
                        'name'    => 'wp4gf_enabled',
                        'label'   => esc_html__( 'Enable Wallet Pass for this form', 'wallet-pass-generator-for-gravity-forms' ),
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

    /**
     * Add {wp4gf_download_link} to the Merge Tag dropdown UI.
     * Cites:
     */
    public function wp4gf_add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
        $merge_tags[] = array(
            'label' => 'Wallet Pass Download Link',
            'tag'   => '{wp4gf_download_link}'
        );
        return $merge_tags;
    }

    /**
     * Replace {wp4gf_download_link} with a unique secure AJAX URL.
     * Cites:
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

        $link = sprintf( '<a href="%s" class="wp4gf-btn">%s</a>', esc_url( $url ), __( 'Download Apple Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ) );
        
        return str_replace( $tag, $link, $text );
    }

    /**
     * Securely handle the binary download when the link is clicked.
     * Cites:
     */
    public function wp4gf_handle_pass_download() {
        $entry_id = rgget( 'entry_id' );
        $nonce    = rgget( 'nonce' );

        if ( ! wp_verify_nonce( $nonce, 'wp4gf_download_' . $entry_id ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'wallet-pass-generator-for-gravity-forms' ) );
        }

        $entry = GFAPI::get_entry( $entry_id );
        if ( is_wp_error( $entry ) ) {
            wp_die( esc_html__( 'Entry not found.', 'wallet-pass-generator-for-gravity-forms' ) );
        }

        $form = GFAPI::get_form( $entry['form_id'] );

        // Call factory to generate binary .pkpass
        $pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );

        header( 'Pragma: no-cache' );
        header( 'Content-Type: application/vnd.apple.pkpass' );
        header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
        
        echo $pass_data;
        exit;
    }
}
