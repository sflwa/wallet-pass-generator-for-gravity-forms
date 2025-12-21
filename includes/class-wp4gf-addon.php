<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Version: 1.2.2
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
 */

GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {

	protected $_version                  = '1.2.2';
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

	public function create_secure_upload_folder() {
		$upload_dir = wp_upload_dir();
		$secure_dir = $upload_dir['basedir'] . '/wp4gf';
		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );
		}
	}

	public function init() {
		parent::init();
		add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );
		add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
		add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
	}

	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Apple Certificate Settings', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_pass_type_id', 'label' => 'Pass Type ID', 'type' => 'text', 'required' => true ),
					array( 'name' => 'wp4gf_team_id', 'label' => 'Team ID', 'type' => 'text', 'required' => true ),
					array( 'name' => 'wp4gf_p12_path', 'label' => 'Absolute Path to .p12', 'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_p12_password', 'label' => 'Cert Password', 'type' => 'text', 'input_type' => 'password' ),
				),
			),
		);
	}

	/**
	 * Form Settings v1.2.2 - Individual Sections with Textarea for Back Field
	 */
	public function form_settings_fields( $form ) {
		return array(
			array(
				'title'  => esc_html__( 'Wallet Pass Status & Preview', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_enabled', 'label' => esc_html__( 'Enable Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ), 'type' => 'toggle' ),
					array( 'name' => 'wp4gf_preview', 'label' => esc_html__( 'Pass Preview', 'wallet-pass-generator-for-gravity-forms' ), 'type' => 'pass_preview' ),
				),
			),
			array(
				'title'  => esc_html__( '1. Header Field (Top Right)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_header', 'label' => 'Display Label', 'type' => 'text', 'placeholder' => 'INFO' ),
					array( 'name' => 'wp4gf_val_header', 'label' => 'Value Source', 'type' => 'field_select' ),
				),
			),
			array(
				'title'  => esc_html__( '2. Primary Field (Large Center Text)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_primary', 'label' => 'Display Label', 'type' => 'text', 'placeholder' => 'GUEST' ),
					array( 'name' => 'wp4gf_val_primary', 'label' => 'Value Source', 'type' => 'field_select' ),
				),
			),
			array(
				'title'  => esc_html__( '3. Secondary Field (Below Primary)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_secondary', 'label' => 'Display Label', 'type' => 'text', 'placeholder' => 'DATE' ),
					array( 'name' => 'wp4gf_val_secondary', 'label' => 'Value Source', 'type' => 'field_select' ),
				),
			),
			array(
				'title'  => esc_html__( '4. Auxiliary Field (Bottom Right)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_auxiliary', 'label' => 'Display Label', 'type' => 'text', 'placeholder' => 'TYPE' ),
					array( 'name' => 'wp4gf_val_auxiliary', 'label' => 'Value Source', 'type' => 'field_select' ),
				),
			),
			array(
				'title'  => esc_html__( '5. Back Field (Reverse Side Content)', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_back', 'label' => 'Display Label', 'type' => 'text', 'placeholder' => 'DETAILS' ),
					array( 'name' => 'wp4gf_val_back', 'label' => 'Back Content', 'type' => 'textarea', 'class' => 'medium merge-tag-support', 'description' => 'Enter text or merge tags for the back of the pass.' ),
				),
			),
			array(
				'title'  => esc_html__( 'Visuals & Barcode', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_barcode_message', 'label' => esc_html__( 'QR Code Message', 'wallet-pass-generator-for-gravity-forms' ), 'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_logo_path',  'label' => 'Logo Path (Absolute)',  'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_icon_path',  'label' => 'Icon Path (Absolute)',  'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_thumb_path', 'label' => 'Thumb Path (Absolute)',  'type' => 'text', 'class' => 'large' ),
				),
			),
		);
	}

	public function settings_pass_preview( $field, $echo = true ) {
		$assets_url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
		$html = '
		<div id="wp4gf-pass-preview" style="background-color: #ffffff; width: 320px; border: 2px solid #000; border-radius: 15px; padding: 20px; color: #000; font-family: -apple-system, BlinkMacSystemFont, sans-serif; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px;">
			<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
				<img class="prev-logo-img" src="' . $assets_url . 'logo.png" style="max-width: 120px; max-height: 40px; object-fit: contain;">
				<div class="prev-val-header" style="font-size: 10px; font-weight: bold; text-transform: uppercase;">INFO</div>
			</div>
			
			<div style="margin-bottom: 20px;">
				<div class="prev-lbl-primary" style="font-size: 10px; text-transform: uppercase; opacity: 0.7;">Primary Label</div>
				<div class="prev-val-primary" style="font-size: 24px; font-weight: 300;">Value</div>
			</div>

			<div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
				<div>
					<div class="prev-lbl-secondary" style="font-size: 10px; text-transform: uppercase; opacity: 0.7;">Secondary Label</div>
					<div class="prev-val-secondary" style="font-size: 16px;">Value</div>
				</div>
				<div style="text-align: right;">
					<div class="prev-lbl-aux" style="font-size: 10px; text-transform: uppercase; opacity: 0.7;">Auxiliary Label</div>
					<div class="prev-val-aux" style="font-size: 16px;">Value</div>
				</div>
			</div>

			<div class="prev-qr" style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 5px; text-align: center; margin-top: 10px; display: none;">
				<div style="color: #000; font-size: 12px; border: 2px solid #000; height: 80px; display: flex; align-items: center; justify-content: center; font-weight: bold;">QR CODE ACTIVE</div>
			</div>
			
			<div style="position: absolute; bottom: 10px; right: 15px; font-size: 12px; opacity: 0.5;">ⓘ</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			function updatePreview() {
				var logoPath = $("input[name*=\'wp4gf_logo_path\']").val();
				if(logoPath && logoPath.startsWith("http")) { $(".prev-logo-img").attr("src", logoPath); }

				$(".prev-lbl-primary").text($("input[name*=\'wp4gf_lbl_primary\']").val() || "Primary Label");
				$(".prev-lbl-secondary").text($("input[name*=\'wp4gf_lbl_secondary\']").val() || "Secondary Label");
				$(".prev-lbl-aux").text($("input[name*=\'wp4gf_lbl_auxiliary\']").val() || "Auxiliary Label");

				$(".prev-val-header").text($("select[name*=\'wp4gf_val_header\'] option:selected").text() || "INFO");
				$(".prev-val-primary").text($("select[name*=\'wp4gf_val_primary\'] option:selected").text() || "Value");
				$(".prev-val-secondary").text($("select[name*=\'wp4gf_val_secondary\'] option:selected").text() || "Value");
				$(".prev-val-aux").text($("select[name*=\'wp4gf_val_auxiliary\'] option:selected").text() || "Value");

				var qrMsg = $("input[name*=\'wp4gf_barcode_message\']").val();
				qrMsg ? $(".prev-qr").show() : $(".prev-qr").hide();
			}
			$(document).on("change keyup", "input, select, textarea", updatePreview);
			updatePreview();
		});
		</script>';
		if ( $echo ) echo $html;
		return $html;
	}

	public function wp4gf_add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		$merge_tags[] = array( 'label' => 'Wallet Pass Download Link', 'tag' => '{wp4gf_download_link}' );
		return $merge_tags;
	}

	public function wp4gf_replace_download_link( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		if ( strpos( $text, '{wp4gf_download_link}' ) === false || empty( $entry ) ) return $text;
		$hash = wp_hash( $entry['id'] . 'wp4gf_secure_download' );
		$url  = add_query_arg( array( 'action' => 'wp4gf_download_pass', 'entry_id' => $entry['id'], 'hash' => $hash ), admin_url( 'admin-ajax.php' ) );
		$link = sprintf( '<a href="%s" class="wp4gf-btn">%s</a>', esc_url( $url ), __( 'Download Apple Wallet Pass', 'wallet-pass-generator-for-gravity-forms' ) );
		return str_replace( '{wp4gf_download_link}', $link, $text );
	}

	public function wp4gf_handle_pass_download() {
		$entry_id = rgget( 'entry_id' );
		if ( ! hash_equals( wp_hash( $entry_id . 'wp4gf_secure_download' ), rgget( 'hash' ) ) ) wp_die( 'Unauthorized.' );
		
		$entry = GFAPI::get_entry( $entry_id );
		$form  = GFAPI::get_form( $entry['form_id'] );
		$pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );
		
		header( 'Content-Type: application/vnd.apple.pkpass' );
		header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
		echo $pass_data;
		exit;
	}
}
