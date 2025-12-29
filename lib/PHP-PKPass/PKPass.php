<?php
/**
 * Modified PKPass Library for WordPress Standards.
 * Version: 1.4.4
 * Prefix: WP4GF | Namespace: WP4GF\PKPass
 */

namespace WP4GF\PKPass; 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use ZipArchive;

class PKPass {
    const MIME_TYPE = 'application/vnd.apple.pkpass';

    protected $certPath;
    protected $certPass;
    protected $tempPath;
    protected $json;
    protected $files = [];

    public function __construct($certificatePath = null, $certificatePassword = null) {
        $this->tempPath = sys_get_temp_dir();
        if ($certificatePath) { $this->certPath = $certificatePath; }
        if ($certificatePassword) { $this->certPass = $certificatePassword; }
    }

    public function setJSON($data) {
        $this->json = (is_array($data) || is_object($data)) ? json_encode($data) : $data;
    }

    public function addFile($path, $name = null) {
        if (!file_exists($path)) {
            throw new \Exception(sprintf('File %s does not exist.', esc_html($path)));
        }
        $name = $name ?: basename($path);
        $this->files[$name] = $path;
    }

    public function create() {
        $manifest = $this->createManifest();
        $signature = $this->createSignature($manifest);
        return $this->createZip($manifest, $signature);
    }

    protected function createManifest() {
        $sha = ['pass.json' => sha1($this->json)];
        foreach ($this->files as $name => $path) {
            $sha[$name] = sha1(file_get_contents($path));
        }
        return json_encode((object)$sha);
    }

    protected function createSignature($manifest) {
        $manifest_path = tempnam($this->tempPath, 'pkpass');
        $signature_path = tempnam($this->tempPath, 'pkpass');
        file_put_contents($manifest_path, $manifest);

        $pkcs12 = file_get_contents($this->certPath);
        $certs = [];
        if (!openssl_pkcs12_read($pkcs12, $certs, $this->certPass)) {
            throw new \Exception('Could not read certificate. Check path and password.');
        }

        $certdata = openssl_x509_read($certs['cert']);
        $privkey = openssl_pkey_get_private($certs['pkey'], $this->certPass);

        openssl_pkcs7_sign($manifest_path, $signature_path, $certdata, $privkey, [], PKCS7_BINARY | PKCS7_DETACHED);

        $signature = file_get_contents($signature_path);
        
        // Use WordPress specific file deletion for security
        wp_delete_file($manifest_path);
        wp_delete_file($signature_path);

        return $this->convertPEMtoDER($signature);
    }

    protected function convertPEMtoDER($signature) {
        $begin = 'filename="smime.p7s"';
        $signature = substr($signature, strpos($signature, $begin) + strlen($begin));
        $signature = substr($signature, 0, strpos($signature, '------'));
        return base64_decode(trim($signature));
    }

    protected function createZip($manifest, $signature) {
        $zip = new ZipArchive();
        $filename = tempnam($this->tempPath, 'pkpass');
        if (!$zip->open($filename, ZipArchive::OVERWRITE)) {
            throw new \Exception('Could not open ' . esc_html(basename($filename)) . ' with ZipArchive.');
        }

        $zip->addFromString('signature', $signature);
        $zip->addFromString('manifest.json', $manifest);
        $zip->addFromString('pass.json', $this->json);
        foreach ($this->files as $name => $path) { $zip->addFile($path, $name); }
        $zip->close();

        $content = file_get_contents($filename);
        wp_delete_file($filename); // Use WordPress specific file deletion
        return $content;
    }
}
