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
		
		// AJAX listeners for both logged-in and logged-out users
		add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
		add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
	}

	/**
	 * Global Settings page with detailed descriptions and upload instructions.
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
						'name'        => 'wp4gf_pass_type_id',
						'label'       => esc_html__( 'Pass Type ID', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'text',
						'class'       => 'medium',
						'required'    => true,
						'description' => esc_html__( 'The identifier created in your Apple Developer portal (starts with "pass.").', 'wallet-pass-generator-for-gravity-forms' ),
					),
					array(
						'name'        => 'wp4gf_team_id',
						'label'       => esc_html__( 'Team ID', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'text',
						'class'       => 'small',
						'required'    => true,
						'description' => esc_html__( 'Your 10-character Apple Developer Team ID found in your Membership details.', 'wallet-pass-generator-for-gravity-forms' ),
					),
					array(
						'name'        => 'wp4gf_p12_path',
						'label'       => esc_html__( 'Absolute Path to .p12', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'text',
						'class'       => 'large',
						'description' => 'Target: ' . $target_dir . 'your-certificate.p12',
					),
					array(
						'name'        => 'wp4gf_p12_password',
						'label'       => esc_html__( 'Cert Password', 'wallet-pass-generator-for-gravity-forms' ),
						'type'        => 'text',
						'input_type'  => 'password',
						'description' => esc_html__( 'The password used when exporting the .p12 file from Keychain Access.', 'wallet-pass-generator-for-gravity-forms' ),
					),
				),
			),
		);
	}

	/**
	 * Form Settings with Preview, Generic Mapping, and Image paths.
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
						'name'    => 'wp4gf_preview',
						'label'   => esc_html__( 'Pass Preview', 'wallet-pass-generator-for-gravity-forms' ),
						'type'    => 'pass_preview',
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

	/**
	 * Renders the visual pass preview in the settings UI.
	 */
	public function settings_pass_preview( $field, $echo = true ) {
		$html = '
		<div id="wp4gf-pass-preview" style="background-color: #ff66cc; width: 320px; border-radius: 15px; padding: 20px; color: #fff; font-family: -apple-system, BlinkMacSystemFont, sans-serif; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
			<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
				<div style="font-weight: bold; font-size: 18px;">Logo Text</div>
				<div style="width: 50px; height: 50px; border-radius: 4px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 10px;">THUMB</div>
			</div>
			
			<div style="margin-bottom: 20px;">
				<div style="font-size: 10px; text-transform: uppercase; opacity: 0.8;">Primary Label</div>
				<div style="font-size: 24px; font-weight: 300;">Johnny Appleseed</div>
			</div>

			<div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
				<div>
					<div style="font-size: 10px; text-transform: uppercase; opacity: 0.8;">Secondary</div>
					<div style="font-size: 16px;">Value</div>
				</div>
				<div style="text-align: right;">
					<div style="font-size: 10px; text-transform: uppercase; opacity: 0.8;">Auxiliary</div>
					<div style="font-size: 16px;">Value</div>
				</div>
			</div>

			<div style="background: #fff; padding: 10px; border-radius: 5px; text-align: center; margin-top: 10px;">
				<div style="color: #000; font-size: 12px; border: 2px solid #000; height: 80px; display: flex; align-items: center; justify-content: center; font-weight: bold;">
					QR CODE AREA
				</div>
			</div>
			
			<div style="position: absolute; bottom: 10px; right: 15px; font-size: 12px; opacity: 0.8;">ⓘ</div>
		</div>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function wp4gf_add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		$merge_tags[] = array( 'label' => 'Wallet Pass Download Link', 'tag' => '{wp4gf_download_link}' );
		return $merge_tags;
	}

	/**
	 * Replaces merge tag with a link containing a secure entry hash.
	 */
	public function wp4gf_replace_download_link( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		if ( strpos( $text, '{wp4gf_download_link}' ) === false || empty( $entry ) ) {
			return $text;
		}

		// Generate a secure hash instead of a user-specific nonce to allow public access
		$hash = wp_hash( $entry['id'] . 'wp4gf_secure_download' );

		$url  = add_query_arg( array( 
			'action'   => 'wp4gf_download_pass', 
			'entry_id' => $entry['id'], 
			'hash'     => $hash 
		), admin_url( 'admin-ajax.php' ) );

		$link = sprintf( '<a href="%s" class="wp4gf-btn">%s</a>', esc_url( $url ), __( 'Download Apple Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ) );
		return str_replace( '{wp4gf_download_link}', $link, $text );
	}

	/**
	 * Handles the pass download using hash verification for security.
	 */
	public function wp4gf_handle_pass_download() {
		$entry_id      = rgget( 'entry_id' );
		$received_hash = rgget( 'hash' );

		// Verify the hash matches the entry ID for secure public access
		$expected_hash = wp_hash( $entry_id . 'wp4gf_secure_download' );

		if ( ! hash_equals( $expected_hash, $received_hash ) ) {
			wp_die( 'Unauthorized access. Secure link invalid.', 'Unauthorized', array( 'response' => 403 ) );
		}

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			wp_die( 'Entry not found.' );
		}

		$form      = GFAPI::get_form( $entry['form_id'] );
		$pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );
		
		header( 'Content-Type: application/vnd.apple.pkpass' );
		header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
		echo $pass_data;
		exit;
	}
}
