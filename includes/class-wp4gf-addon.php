<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Version: 1.4.4
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {

	protected $_version                  = '1.4.4';
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'wallet-pass-generator-for-gravity-forms';
	protected $_path                     = 'wallet-pass-generator-for-gravity-forms/wallet-pass-generator-for-gravity-forms.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Wallet Pass Generator';
	protected $_short_title              = 'Wallet Pass';

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function pre_init() {
		parent::pre_init();
		register_activation_hook( $this->_full_path, array( $this, 'create_secure_upload_folder' ) );
	}

	/**
	 * Create a secure folder in the uploads directory for certificates.
	 */
	public function create_secure_upload_folder() {
		$upload_dir = wp_upload_dir();
		$secure_dir = $upload_dir['basedir'] . '/wp4gf'; //
		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );
			// Protect the directory from directory listing
			file_put_contents( $secure_dir . '/index.php', '<?php // Silence is golden' );
		}
	}

	public function init() {
		parent::init();
		add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );
		add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
		add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
		
		// Correctly enqueue admin scripts for the preview logic
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register and enqueue the admin preview script.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'forms_page_gf_settings' ) === false ) {
			return;
		}

		wp_enqueue_script( 'wp4gf-admin-preview', plugin_dir_url( __FILE__ ) . '../assets/js/admin-preview.js', array( 'jquery' ), $this->_version, true );
		
		wp_localize_script( 'wp4gf-admin-preview', 'wp4gf_vars', array(
			'site_url'    => home_url( '/' ),
			'content_dir' => WP_CONTENT_DIR,
		) );
	}

	/**
	 * Define Global Plugin Settings.
	 */
	public function plugin_settings_fields() {
		$p12_path = $this->get_plugin_setting( 'wp4gf_p12_path' );
		$path_status = '';
		if ( ! empty( $p12_path ) ) {
			$path_status = file_exists( $p12_path ) ? 
				'<div style="color:green; font-weight:bold; margin-top:5px;">✅ Certificate Found.</div>' : 
				'<div style="color:red; font-weight:bold; margin-top:5px;">❌ ERROR: Certificate NOT found.</div>';
		}

		return array(
			array(
				'title'  => esc_html__( 'Apple Certificate Settings', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_pass_type_id', 'label' => 'Pass Type ID', 'type' => 'text', 'required' => true ),
					array( 'name' => 'wp4gf_team_id', 'label' => 'Team ID', 'type' => 'text', 'required' => true ),
					array( 
						'name'  => 'wp4gf_p12_upload', 
						'label' => 'Upload .p12 Certificate', 
						'type'  => 'file_upload', 
						'description' => 'Upload your exported Apple Certificate (.p12) here.' 
					),
					array( 
						'name'     => 'wp4gf_p12_path', 
						'label'    => 'Current Certificate Path', 
						'type'     => 'text', 
						'class'    => 'large', 
						'readonly' => true,
						'description' => wp_kses_post( $path_status ) 
					),
					array( 'name' => 'wp4gf_p12_password', 'label' => 'Cert Password', 'type' => 'text', 'input_type' => 'password' ),
					array(
						'name'  => 'wp4gf_wwdr_info',
						'label' => 'WWDR Certificate Instruction',
						'type'  => 'html',
						'html'  => sprintf(
							'<p>%s <a href="https://www.apple.com/certificateauthority/" target="_blank" rel="noopener">%s</a>. %s</p>',
							esc_html__( 'Download the "Worldwide Developer Relations - G4" certificate from the', 'wallet-pass-generator-for-gravity-forms' ),
							esc_html__( 'Apple Certificate Authority site', 'wallet-pass-generator-for-gravity-forms' ),
							esc_html__( 'This is required for signing your passes.', 'wallet-pass-generator-for-gravity-forms' )
						)
					),
				),
			),
		);
	}

	/**
	 * Custom renderer for the file upload field in global settings.
	 */
	public function settings_file_upload( $field, $echo = true ) {
		$html = sprintf(
			'<input type="file" name="%s" id="%s" accept=".p12" />',
			esc_attr( $field['name'] ),
			esc_attr( $field['name'] )
		);
		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $html;
	}

	/**
	 * Process the file upload when global settings are saved.
	 */
	public function plugin_settings_save( $settings ) {
		if ( ! empty( $_FILES['wp4gf_p12_upload']['name'] ) ) {
			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/wp4gf/';
			$file_name  = sanitize_file_name( $_FILES['wp4gf_p12_upload']['name'] );
			$target_path = $target_dir . $file_name;

			if ( move_uploaded_file( $_FILES['wp4gf_p12_upload']['tmp_name'], $target_path ) ) {
				$settings['wp4gf_p12_path'] = $target_path;
			}
		}
		return parent::plugin_settings_save( $settings );
	}

	/**
	 * Define Form-Specific Settings.
	 */
	public function form_settings_fields( $form ) {
		return array(
			array(
				'title'  => esc_html__( 'Status & Preview', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_enabled', 'label' => 'Enable Wallet Pass', 'type' => 'toggle' ),
					array( 'name' => 'wp4gf_preview', 'label' => 'Pass Preview', 'type' => 'pass_preview' ),
				),
			),
			array(
				'title'  => esc_html__( '1. Primary Field (REQUIRED)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_primary', 'label' => 'Display Label', 'type' => 'text', 'required' => true ),
					array( 'name' => 'wp4gf_src_primary', 'label' => 'Source', 'type' => 'radio', 'horizontal' => true, 'choices' => array( array( 'label' => 'Field', 'value' => 'field' ), array( 'label' => 'Custom', 'value' => 'custom' ) ), 'default_value' => 'field' ),
					array( 'name' => 'wp4gf_val_primary', 'label' => 'Select Form Field', 'type' => 'field_select' ),
					array( 'name' => 'wp4gf_txt_primary', 'label' => 'Custom Text Value', 'type' => 'text' ),
				),
			),
			array(
				'title'  => esc_html__( '2. Secondary Field', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_secondary', 'label' => 'Display Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_src_secondary', 'label' => 'Source', 'type' => 'radio', 'horizontal' => true, 'choices' => array( array( 'label' => 'Field', 'value' => 'field' ), array( 'label' => 'Custom', 'value' => 'custom' ) ), 'default_value' => 'field' ),
					array( 'name' => 'wp4gf_val_secondary', 'label' => 'Select Form Field', 'type' => 'field_select' ),
					array( 'name' => 'wp4gf_txt_secondary', 'label' => 'Custom Text Value', 'type' => 'text' ),
				),
			),
			array(
				'title'  => esc_html__( '3. Auxiliary Field (Hidden if QR is active)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_auxiliary', 'label' => 'Display Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_src_auxiliary', 'label' => 'Source', 'type' => 'radio', 'horizontal' => true, 'choices' => array( array( 'label' => 'Field', 'value' => 'field' ), array( 'label' => 'Custom', 'value' => 'custom' ) ), 'default_value' => 'field' ),
					array( 'name' => 'wp4gf_val_auxiliary', 'label' => 'Select Form Field', 'type' => 'field_select' ),
					array( 'name' => 'wp4gf_txt_auxiliary', 'label' => 'Custom Text Value', 'type' => 'text' ),
				),
			),
			array(
				'title'  => esc_html__( '4. Back Content & QR Code', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_back', 'label' => 'Back Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_val_back', 'label' => 'Back Content', 'type' => 'textarea', 'class' => 'medium' ),
					array( 'name' => 'wp4gf_barcode_message', 'label' => 'QR Code Message', 'type' => 'text', 'class' => 'large' ),
				),
			),
			array(
				'title'  => esc_html__( '5. Visuals', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_logo_path',  'label' => 'Logo Path', 'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_icon_path',  'label' => 'Icon Path', 'type' => 'text', 'class' => 'large' ),
				),
			),
		);
	}

	/**
	 * Renders the HTML for the pass preview in form settings.
	 */
	public function settings_pass_preview( $field, $echo = true ) {
		$assets_url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
		$html = '
		<div id="wp4gf-pass-preview" style="background:#f3f3f3; width:320px; border:1px solid #ccc; border-radius:15px; padding:20px; font-family:-apple-system, sans-serif; color:#000;">
			<div style="background:#fff; border-radius:10px; padding:15px; box-shadow:0 4px 10px rgba(0,0,0,0.1); position:relative; min-height: 250px;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
					<img class="prev-logo-img" src="' . esc_url( $assets_url . 'logo.png' ) . '" style="max-width:100px; max-height:35px; object-fit:contain;">
				</div>
				<div class="prev-box-primary" style="margin-bottom:15px;">
					<div class="prev-lbl-primary" style="font-size:9px; font-weight:bold; text-transform:uppercase; color:#666;">PRIMARY</div>
					<div class="prev-val-primary" style="font-size:24px; font-weight:500;">Value</div>
				</div>
				<div class="prev-box-secondary" style="margin-bottom:15px; border-top:1px solid #eee; padding-top:10px;">
					<div class="prev-lbl-secondary" style="font-size:9px; font-weight:bold; text-transform:uppercase; color:#666;">SECONDARY</div>
					<div class="prev-val-secondary" style="font-size:14px;">Value</div>
				</div>
				<div class="prev-box-auxiliary" style="margin-bottom:15px; border-top:1px solid #eee; padding-top:10px;">
					<div class="prev-lbl-auxiliary" style="font-size:9px; font-weight:bold; text-transform:uppercase; color:#666;">AUXILIARY</div>
					<div class="prev-val-auxiliary" style="font-size:14px;">Value</div>
				</div>
				<div class="prev-qr" style="margin-top:10px; padding:10px; border:1px solid #000; width:80px; height:80px; margin-left:auto; margin-right:auto; text-align:center; display:none; background:#fff;">
					<div style="font-size:9px; font-weight:bold; margin-top:25px;">QR CODE</div>
				</div>
			</div>
		</div>';
		if ( $echo ) {
			echo wp_kses_post( $html ); //
		}
		return $html;
	}

	public function wp4gf_add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		$merge_tags[] = array( 'label' => 'Wallet Pass Download Link', 'tag' => '{wp4gf_download_link}' );
		return $merge_tags;
	}

	public function wp4gf_replace_download_link( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		if ( strpos( $text, '{wp4gf_download_link}' ) === false || empty( $entry ) ) return $text;
		$form_settings = $this->get_form_settings( $form );
		if ( ! isset( $form_settings['wp4gf_enabled'] ) || $form_settings['wp4gf_enabled'] !== '1' ) {
			return str_replace( '{wp4gf_download_link}', '', $text );
		}
		$hash = wp_hash( $entry['id'] . 'wp4gf_secure_download' );
		$url  = add_query_arg( array( 'action' => 'wp4gf_download_pass', 'entry_id' => $entry['id'], 'hash' => $hash ), admin_url( 'admin-ajax.php' ) );
		$link = sprintf( '<a href="%s" class="wp4gf-btn" style="background:#000; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;">Download Pass</a>', esc_url( $url ) );
		return str_replace( '{wp4gf_download_link}', $link, $text );
	}

	/**
	 * AJAX Handler for secure pass downloads.
	 */
	public function wp4gf_handle_pass_download() {
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		
		$entry_id = rgget( 'entry_id' );
		if ( ! hash_equals( wp_hash( $entry_id . 'wp4gf_secure_download' ), rgget( 'hash' ) ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wallet-pass-generator-for-gravity-forms' ) );
		}
		
		$entry = GFAPI::get_entry( $entry_id );
		$form  = GFAPI::get_form( $entry['form_id'] );
		$form_settings = $this->get_form_settings( $form );
		
		if ( rgar( $form_settings, 'wp4gf_enabled' ) !== '1' ) {
			wp_die( wp_kses_post( '<h3>Access Denied</h3><p>Wallet Pass generation is currently disabled for this form.</p>' ) );
		}

		try {
			$pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );

			GFAPI::add_note( $entry['id'], 0, 'Wallet Pass', 'Apple Wallet Pass was generated/downloaded.' );

			header( 'Content-Type: application/vnd.apple.pkpass' );
			header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
			
			/**
			 * We cannot escape the binary pass data because it would corrupt the Apple Wallet file structure.
			 * The data is generated internally through WP4GF_PKPass_Factory using secure libraries.
			 */
			echo $pass_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		} catch ( Exception $e ) {
			wp_die( sprintf( wp_kses_post( '<h3>Wallet Pass Error</h3><p>%s</p>' ), esc_html( $e->getMessage() ) ) );
		}
	}
}
