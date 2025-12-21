<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
 */

GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {

	protected $_version                  = '1.0.0';
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'wallet-pass-generator-for-gravity-forms';
	protected $_path                     = 'wallet-pass-generator-for-gravity-forms/wallet-pass-generator-for-gravity-forms.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Wallet Pass Generator';
	protected $_short_title              = 'Wallet Pass';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return WP4GF_Addon
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Run on plugin activation to create the secure upload folder.
	 */
	public function pre_init() {
		parent::pre_init();
		register_activation_hook( $this->_full_path, array( $this, 'create_secure_upload_folder' ) );
	}

	/**
	 * Create the secure folder in wp-content/uploads/wp4gf/
	 */
	public function create_secure_upload_folder() {
		$upload_dir = wp_upload_dir();
		$secure_dir = $upload_dir['basedir'] . '/wp4gf';

		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );
		}
	}

	/**
	 * Initialize hooks and AJAX listeners.
	 */
	public function init() {
		parent::init();
		add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );
		add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
		add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
	}

	/**
	 * Configures the Global Settings page with upload instructions.
	 */
	public function plugin_settings_fields() {
		$upload_url = admin_url( 'media-new.php' );
		$target_dir = wp_upload_dir()['basedir'] . '/wp4gf/';

		return array(
			array(
				'title'  => esc_html__( 'Apple Certificate Settings', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array(
						'name'        => 'wp4gf_upload_instruction',
						'label'       => esc_html__( 'Certificate Management', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'save_instructions',
						'description' => sprintf(
							'1. <a href="%s" target="_blank">Upload your .p12 file here</a>.<br>2. Move it to <code>%s</code> via FTP.<br>3. Paste the absolute path to the file below.',
							esc_url( $upload_url ),
							esc_html( $target_dir )
						),
					),
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
						'name'        => 'wp4gf_p12_path',
						'label'       => esc_html__( 'Absolute Path to .p12', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'text',
						'class'       => 'large',
						'description' => 'Target: ' . $target_dir . 'your-certificate.p12',
					),
					array(
						'name'       => 'wp4gf_p12_password',
						'label'      => esc_html__( 'Cert Password', 'wallet-pass-generator-for-gravity-forms' ),
						'type'       => 'text',
						'input_type' => 'password',
					),
				),
			),
		);
	}

	/**
	 * Configures Form Settings with Generic Mapping and Custom Value support.
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
						'name'         => 'wp4gf_generic_map',
						'label'        => esc_html__( 'Generic Pass Mapping', 'wallet-pass-generator-for-gravity-forms' ),
						'type'         => 'generic_map',
						'key_field'    => array(
							'title'        => 'Apple Pass Field',
							'allow_custom' => false,
							'choices'      => array(
								array( 'label' => 'Primary Value',   'value' => 'primary_value' ),
								array( 'label' => 'Secondary Value', 'value' => 'secondary_value' ),
								array( 'label' => 'Auxiliary Value', 'value' => 'auxiliary_value' ),
								array( 'label' => 'Header Value',    'value' => 'header_value' ),
								array( 'label' => 'Back Value',      'value' => 'back_value' ),
							),
						),
						'value_field'  => array(
							'title'        => 'Form Value',
							'allow_custom' => true,
						),
					),
					array(
						'name'        => 'wp4gf_barcode_message',
						'label'       => esc_html__( 'QR Code Message / URL', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'text',
						'class'       => 'large',
						'description' => esc_html__( 'Supports merge tags like {entry_id}.', 'wallet-pass-generator-for-gravity-forms' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Pass Images (Absolute Paths)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_logo_path',  'label' => 'Logo Path',  'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_icon_path',  'label' => 'Icon Path',  'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_thumb_path', 'label' => 'Thumb Path', 'type' => 'text', 'class' => 'large' ),
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
		$url  = add_query_arg( array( 'action' => 'wp4gf_download_pass', 'entry_id' => $entry['id'], 'nonce' => wp_create_nonce( 'wp4gf_download_' . $entry['id'] ) ), admin_url( 'admin-ajax.php' ) );
		$link = sprintf( '<a href="%s" class="wp4gf-btn">%s</a>', esc_url( $url ), __( 'Download Apple Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ) );
		return str_replace( '{wp4gf_download_link}', $link, $text );
	}

	public function wp4gf_handle_pass_download() {
		$entry_id = rgget( 'entry_id' );
		if ( ! wp_verify_nonce( rgget( 'nonce' ), 'wp4gf_download_' . $entry_id ) ) {
			wp_die( 'Unauthorized.' );
		}
		$entry     = GFAPI::get_entry( $entry_id );
		$form      = GFAPI::get_form( $entry['form_id'] );
		$pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );
		header( 'Content-Type: application/vnd.apple.pkpass' );
		header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
		echo $pass_data;
		exit;
	}
}
