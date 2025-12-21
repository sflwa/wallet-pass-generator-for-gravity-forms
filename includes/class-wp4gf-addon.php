<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Version: 1.4.2
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
 */

GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {

	protected $_version                  = '1.4.2';
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

	public function init() {
		parent::init();
		add_filter( 'gform_custom_merge_tags', array( $this, 'wp4gf_add_custom_merge_tags' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'wp4gf_replace_download_link' ), 10, 7 );
		add_action( 'wp_ajax_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
		add_action( 'wp_ajax_nopriv_wp4gf_download_pass', array( $this, 'wp4gf_handle_pass_download' ) );
	}

	public function plugin_settings_fields() {
		$p12_path = $this->get_plugin_setting( 'wp4gf_p12_path' );
		$path_status = '';
		if ( ! empty( $p12_path ) ) {
			$path_status = file_exists( $p12_path ) ? 
				'<div style="color:green; font-weight:bold; margin-top:5px;">✅ File Found.</div>' : 
				'<div style="color:red; font-weight:bold; margin-top:5px;">❌ ERROR: File NOT found.</div>';
		}

		return array(
			array(
				'title'  => esc_html__( 'Apple Certificate Settings', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_pass_type_id', 'label' => 'Pass Type ID', 'type' => 'text', 'required' => true ),
					array( 'name' => 'wp4gf_team_id', 'label' => 'Team ID', 'type' => 'text', 'required' => true ),
					array( 'name' => 'wp4gf_p12_path', 'label' => 'Absolute Path to .p12', 'type' => 'text', 'class' => 'large', 'description' => 'Root: ' . ABSPATH . '<br>' . $path_status ),
					array( 'name' => 'wp4gf_p12_password', 'label' => 'Cert Password', 'type' => 'text', 'input_type' => 'password' ),
				),
			),
		);
	}

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
					array( 'name' => 'wp4gf_txt_primary', 'label' => 'Custom Text Value', 'type' => 'text', 'attrs' => array( 'maxlength' => '50' ) ),
				),
			),
			array(
				'title'  => esc_html__( '2. Header Field', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_header', 'label' => 'Display Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_src_header', 'label' => 'Source', 'type' => 'radio', 'horizontal' => true, 'choices' => array( array( 'label' => 'Field', 'value' => 'field' ), array( 'label' => 'Custom', 'value' => 'custom' ) ), 'default_value' => 'field' ),
					array( 'name' => 'wp4gf_val_header', 'label' => 'Select Form Field', 'type' => 'field_select' ),
					array( 'name' => 'wp4gf_txt_header', 'label' => 'Custom Text Value', 'type' => 'text', 'attrs' => array( 'maxlength' => '50' ) ),
				),
			),
			array(
				'title'  => esc_html__( '3. Secondary Field', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_secondary', 'label' => 'Display Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_src_secondary', 'label' => 'Source', 'type' => 'radio', 'horizontal' => true, 'choices' => array( array( 'label' => 'Field', 'value' => 'field' ), array( 'label' => 'Custom', 'value' => 'custom' ) ), 'default_value' => 'field' ),
					array( 'name' => 'wp4gf_val_secondary', 'label' => 'Select Form Field', 'type' => 'field_select' ),
					array( 'name' => 'wp4gf_txt_secondary', 'label' => 'Custom Text Value', 'type' => 'text', 'attrs' => array( 'maxlength' => '50' ) ),
				),
			),
			array(
				'title'  => esc_html__( '4. Auxiliary Field', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_auxiliary', 'label' => 'Display Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_src_auxiliary', 'label' => 'Source', 'type' => 'radio', 'horizontal' => true, 'choices' => array( array( 'label' => 'Field', 'value' => 'field' ), array( 'label' => 'Custom', 'value' => 'custom' ) ), 'default_value' => 'field' ),
					array( 'name' => 'wp4gf_val_auxiliary', 'label' => 'Select Form Field', 'type' => 'field_select' ),
					array( 'name' => 'wp4gf_txt_auxiliary', 'label' => 'Custom Text Value', 'type' => 'text', 'attrs' => array( 'maxlength' => '50' ) ),
				),
			),
			array(
				'title'  => esc_html__( '5. Back Field & Visuals', 'wallet-pass-generator-for-gravity-forms' ),
				'fields' => array(
					array( 'name' => 'wp4gf_lbl_back', 'label' => 'Back Label', 'type' => 'text' ),
					array( 'name' => 'wp4gf_val_back', 'label' => 'Back Content', 'type' => 'textarea', 'class' => 'medium' ),
					array( 'name' => 'wp4gf_barcode_message', 'label' => 'QR Code Message', 'type' => 'text', 'class' => 'large' ),
					array( 'name' => 'wp4gf_logo_path', 'label' => 'Logo Path', 'type' => 'text', 'class' => 'large', 'description' => 'Path: ' . ABSPATH . 'wp-content/uploads/logo.png' ),
					array( 'name' => 'wp4gf_icon_path', 'label' => 'Icon Path', 'type' => 'text', 'class' => 'large', 'description' => 'Path: ' . ABSPATH . 'wp-content/uploads/icon.png' ),
				),
			),
		);
	}

	public function settings_pass_preview( $field, $echo = true ) {
		$assets_url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
		$site_url = home_url('/');
		$abs_path = ABSPATH;

		$html = '
		<div id="wp4gf-pass-preview" style="background-color: #ffffff; width: 320px; border: 2px solid #000; border-radius: 15px; padding: 25px; color: #000; font-family: -apple-system, BlinkMacSystemFont, sans-serif; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px;">
			<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
				<img class="prev-logo-img" src="' . $assets_url . 'logo.png" style="max-width: 120px; max-height: 40px; object-fit: contain;">
				<div class="prev-box-header" style="display:none;"><div class="prev-val-header" style="font-size: 10px; font-weight: bold;">INFO</div></div>
			</div>
			<div class="prev-box-primary" style="margin-bottom: 15px;"><div class="prev-lbl-primary" style="font-size: 10px; text-transform: uppercase; font-weight: bold;">LABEL</div><div class="prev-val-primary" style="font-size: 28px; font-weight: 500;">Value</div></div>
			<div class="prev-box-secondary" style="margin-bottom: 15px; display:none;"><div class="prev-lbl-secondary" style="font-size: 10px; text-transform: uppercase; font-weight: bold;">LABEL</div><div class="prev-val-secondary" style="font-size: 16px;">Value</div></div>
			<div class="prev-box-auxiliary" style="margin-bottom: 25px; display:none;"><div class="prev-lbl-auxiliary" style="font-size: 10px; text-transform: uppercase; font-weight: bold;">LABEL</div><div class="prev-val-auxiliary" style="font-size: 16px;">Value</div></div>
			<div class="prev-qr" style="display:none; border: 2px solid #000; height: 80px; text-align: center; line-height: 80px; font-weight: bold;">QR CODE ACTIVE</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			const siteUrl = "' . $site_url . '";
			const absPath = "' . $abs_path . '";
			function updatePreview() {
				var logoInput = $("input[name*=\'wp4gf_logo_path\']").val();
				if(logoInput) {
					var logoUrl = logoInput.replace(absPath, siteUrl);
					if(logoUrl.startsWith("http")) $(".prev-logo-img").attr("src", logoUrl);
				}
				const areas = ["primary", "header", "secondary", "auxiliary"];
				areas.forEach(id => {
					let source = $("input[name*=\'wp4gf_src_" + id + "\']:checked").val();
					let lblVal = $("input[name*=\'wp4gf_lbl_" + id + "\']").val();
					let val = source === "custom" ? $("input[name*=\'wp4gf_txt_" + id + "\']").val() : $("select[name*=\'wp4gf_val_" + id + "\'] option:selected").text();
					if(id !== "primary") lblVal ? $(".prev-box-" + id).show() : $(".prev-box-" + id).hide();
					$(".prev-lbl-" + id).text(lblVal || id.toUpperCase());
					$(".prev-val-" + id).text(val || "Value");
				});
				$("input[name*=\'wp4gf_barcode_message\']").val() ? $(".prev-qr").show() : $(".prev-qr").hide();
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

		try {
			$pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );
			header( 'Content-Type: application/vnd.apple.pkpass' );
			header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
			echo $pass_data;
			exit;
		} catch ( Exception $e ) {
			wp_die( sprintf( 
				'<h3>Wallet Pass Generation Error</h3><p><strong>Error Details:</strong> %s</p><p><a href="javascript:history.back()">« Go Back</a></p>', 
				esc_html( $e->getMessage() ) 
			) );
		}
	}
}
