=== Wallet Pass Generator for Gravity Forms ===
Contributors: gemini-ai
Stable tag: 1.2.4
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

A secure, dynamic solution for generating Apple Wallet passes from Gravity Forms entries. This plugin allows administrators to map form fields directly to Apple Wallet pass locations including Primary, Secondary, Auxiliary, Header, and Back fields.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure your Apple `.p12` certificate is moved to the secure directory: `wp-content/uploads/wp4gf/`.

== Configuration ==

1. **Global Settings**: Navigate to the Wallet Pass global settings page. Enter your Apple Team ID, Pass Type ID, and the absolute server path to your `.p12` certificate file.
2. **Form Setup**: Open a specific Gravity Form and navigate to **Settings > Wallet Pass**.
3. **Primary Field**: Map a label and a field source. This field is **required** to generate a valid pass.
4. **Optional Fields**: Provide labels for Header, Secondary, Auxiliary, or Back fields. If a label is left blank, that specific field will be omitted from the generated pass.
5. **Visuals**: Specify absolute paths for your logo and icon. For best results, use 320x100 PNG for logos and 58x58 PNG for icons.
6. **QR Code**: Enter a URL or text in the QR Code Message field to enable the barcode on the pass.

== Frequently Asked Questions ==

= How do I provide the pass to my users? =
Use the `{wp4gf_download_link}` merge tag in your Gravity Forms confirmations or email notifications.

= Why is the pass not generating? =
Ensure the Primary Field label and value are set, and verify that your `.p12` path and password are correct in the global settings.

== Changelog ==

= 1.2.4 =
* Enforced Primary field as a required setting.
* Added conditional logic to hide optional fields if labels are empty.

= 1.2.3 =
* Updated Preview CSS for vertical field alignment.
* Implemented white-background and black-border styling for the admin preview.

= 1.2.2 =
* Converted Back Field to a textarea for multi-line support and merge tag compatibility.

= 1.2.1 =
* Major UI overhaul: Replaced dynamic mapping table with fixed, individual field sections for better stability.

= 1.0.0 =
* Initial release.
