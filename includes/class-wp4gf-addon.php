<?php
/**
 * Main Add-On Class for Wallet Pass Generator.
 * Version: 1.6.3
 * Prefix: wp4gf | Text Domain: wallet-pass-generator-for-gravity-forms
 */

GFForms::include_addon_framework();

class WP4GF_Addon extends GFAddOn {

	protected $_version                  = '1.6.3';
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
		add_filter( 'gform_entry_list_columns', array( $this, 'wp4gf_add_entry_column' ), 10, 2 );
		add_action( 'gform_entry_list_column_wp4gf_last_gen', array( $this, 'wp4gf_entry_column_content' ), 10, 3 );
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

	public function settings_pass_preview( $field, $echo = true ) {
		$assets_url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
		$site_url = home_url('/');
		$abs_path = ABSPATH;
		$html = '
		<div id="wp4gf-pass-preview" style="background:#f3f3f3; width:320px; border:1px solid #ccc; border-radius:15px; padding:20px; font-family:-apple-system, sans-serif; color:#000;">
			<div style="background:#fff; border-radius:10px; padding:15px; box-shadow:0 4px 10px rgba(0,0,0,0.1); position:relative; min-height: 250px;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
					<img class="prev-logo-img" src="' . $assets_url . 'logo.png" style="max-width:100px; max-height:35px; object-fit:contain;">
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
		</div>
		<script>
		jQuery(document).ready(function($) {
			function update() {
				const siteUrl = "' . $site_url . '";
				const absPath = "' . $abs_path . '";
				var logo = $("input[name*=\'wp4gf_logo_path\']").val();
				if(logo) $(".prev-logo-img").attr("src", logo.replace(absPath, siteUrl));

				let qrActive = $("input[name*=\'wp4gf_barcode_message\']").val().length > 0;

				["primary", "secondary", "auxiliary"].forEach(f => {
					let lbl = $("input[name*=\'wp4gf_lbl_" + f + "\']").val();
					let src = $("input[name*=\'wp4gf_src_" + f + "\']:checked").val();
					let val = src === "custom" ? $("input[name*=\'wp4gf_txt_" + f + "\']").val() : $("select[name*=\'wp4gf_val_" + f + "\'] option:selected").text();
					
					$(".prev-lbl-" + f).text(lbl || f.toUpperCase());
					$(".prev-val-" + f).text(val || "Value");
					
					// Core Logic: Hide auxiliary if QR is active
					if (f === "auxiliary" && qrActive) {
						$(".prev-box-auxiliary").hide();
					} else {
						(lbl || (val && val !== "Select a field")) ? $(".prev-box-" + f).show() : (f !== "primary" ? $(".prev-box-" + f).hide() : null);
					}
				});
				qrActive ? $(".prev-qr").show() : $(".prev-qr").hide();
			}
			$(document).on("change keyup", "input, select, textarea", update);
			update();
		});
		</script>';
		if ( $echo ) { // phpcs:ignore
			echo $html;
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
		return str_replace( '{wp4gf_download_link}', sprintf( '<a href="%s" class="wp4gf-btn" style="background:#000; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;">Download Pass</a>', esc_url( $url ) ), $text );
	}

	public function wp4gf_add_entry_column( $columns, $form_id ) {
		$columns['wp4gf_last_gen'] = 'Pass Generated';
		return $columns;
	}

	public function wp4gf_entry_column_content( $form_id, $field_id, $value ) {
		return ! empty( $value ) ? esc_html( $value ) : '-';
	}

	public function wp4gf_handle_pass_download() {
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		
		$entry_id = rgget( 'entry_id' );
		if ( ! hash_equals( wp_hash( $entry_id . 'wp4gf_secure_download' ), rgget( 'hash' ) ) ) wp_die( 'Unauthorized.' );
		
		$entry = GFAPI::get_entry( $entry_id );
		$form_id = $entry['form_id'];

		global $wpdb;
		$table_name = $wpdb->prefix . 'gf_form_meta';
		$form_meta = $wpdb->get_var( $wpdb->prepare( "SELECT display_meta FROM $table_name WHERE form_id = %d", $form_id ) );
		$form_meta = json_decode( $form_meta, true );
		
		if ( ! isset( $form_meta[$this->_slug]['wp4gf_enabled'] ) || $form_meta[$this->_slug]['wp4gf_enabled'] !== '1' ) {
			wp_die( '<h3>Access Denied</h3><p>Wallet Pass generation is currently disabled for this form.</p>' );
		}

		try {
			$form = GFAPI::get_form( $form_id );
			$pass_data = WP4GF_PKPass_Factory::generate( $entry, $form );
			gform_update_meta( $entry['id'], 'wp4gf_last_gen', current_time( 'Y-m-d H:i:s' ) );
			header( 'Content-Type: application/vnd.apple.pkpass' );
			header( 'Content-Disposition: attachment; filename="pass.pkpass"' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $pass_data;
			exit;
		} catch ( Exception $e ) {
			wp_die( sprintf( '<h3>Wallet Pass Error</h3><p>%s</p>', esc_html( $e->getMessage() ) ) );
		}
	}
}
