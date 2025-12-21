=== Wallet Pass Generator for Gravity Forms ===
Contributors: Your Name
Tags: gravity forms, apple wallet, pkpass, wallet, checkin
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later

Generate Apple Wallet passes locally from Gravity Forms submissions without 3rd party subscription fees.

== Description ==
This plugin allows you to generate signed .pkpass files for Apple Wallet directly from your WordPress server. It integrates natively with Gravity Forms, providing a custom merge tag for download links in confirmations and notifications.

== Requirements ==
* **Apple Developer Program Account ($99/year):** Required to generate the necessary Pass Type ID and Signing Certificates.
* **Gravity Forms:** Must be installed and active.
* **PHP OpenSSL Extension:** Required for cryptographic signing of the pass.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Forms > Settings > Wallet Pass** to enter your Apple Team ID and Certificate paths.
4. Enable the generator on a per-form basis under **Form Settings > Wallet Pass**.

== Security Note ==
To protect your Apple certificates, it is highly recommended to add the following code to your site's main `.htaccess` file in the root directory:

`
# Block direct access to Wallet Pass certificates in uploads
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^wp-content/uploads/wp4gf/.*$ - [F,L]
</IfModule>
`

== Instructions ==
1. **Certificates:** Obtain a Pass Type ID and Certificate from the Apple Developer Portal.
2. **Secure Storage:** Store your .p12 certificate in `/wp-content/uploads/wp4gf/`. This directory is protected against plugin updates.
3. **Merge Tag:** Use `{wp4gf_download_link}` in your form's confirmation or notification email to provide the pass to your users.
