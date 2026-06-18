<?php

/**
 * Encryption/Decryption utilities using AES-256-CBC
 * Used for securing sensitive data like webhook Bearer tokens
 */

/**
 * Encrypt sensitive data using AES-256-CBC with HMAC-SHA256 authentication
 * 
 * Format: base64(HMAC[32] | IV[16] | raw_ciphertext)
 * 
 * @param string $plaintext The data to encrypt
 * @return string Base64-encoded authenticated encrypted data
 * @throws Exception If encryption fails
 */
function encrypt_data(string $plaintext): string {
    static $encryption_config = null;

    if ($encryption_config === null) {
        require_once __DIR__ . '/env.php';
        $config = load_config();
        $encryption_config = [
            'key' => $config['encryption']['key'],
            'cipher' => $config['encryption']['cipher'],
        ];
    }

    $key = $encryption_config['key'];
    $cipher = $encryption_config['cipher'];
    
    if (strlen($key) !== 32) {
        throw new Exception('Encryption key must be exactly 32 characters for AES-256');
    }
    
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    
    if ($ciphertext === false) {
        throw new Exception('Encryption failed: ' . openssl_error_string());
    }
    
    $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    
    return base64_encode($mac . $iv . $ciphertext);
}

/**
 * Decrypt sensitive data encrypted with encrypt_data()
 * 
 * Verifies HMAC-SHA256 authentication before decryption to prevent tampering
 * 
 * @param string $encrypted Base64-encoded authenticated encrypted data
 * @return string The decrypted plaintext
 * @throws Exception If decryption or HMAC verification fails
 */
function decrypt_data(string $encrypted): string {
    static $encryption_config = null;

    if ($encryption_config === null) {
        require_once __DIR__ . '/env.php';
        $config = load_config();
        $encryption_config = [
            'key' => $config['encryption']['key'],
            'cipher' => $config['encryption']['cipher'],
        ];
    }

    $key = $encryption_config['key'];
    $cipher = $encryption_config['cipher'];
    
    if (strlen($key) !== 32) {
        throw new Exception('Encryption key must be exactly 32 characters for AES-256');
    }
    
    $data = base64_decode($encrypted, true);
    
    if ($data === false) {
        throw new Exception('Invalid encrypted data: base64 decode failed');
    }
    
    $iv_length = openssl_cipher_iv_length($cipher);
    $mac_length = 32;
    
    if (strlen($data) < $mac_length + $iv_length) {
        throw new Exception('Invalid encrypted data: too short');
    }
    
    $mac = substr($data, 0, $mac_length);
    $iv = substr($data, $mac_length, $iv_length);
    $ciphertext = substr($data, $mac_length + $iv_length);
    
    $expected_mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    if (!hash_equals($expected_mac, $mac)) {
        throw new Exception('Decryption failed: HMAC verification failed (data may be tampered)');
    }
    
    $plaintext = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    
    if ($plaintext === false) {
        throw new Exception('Decryption failed: ' . openssl_error_string());
    }
    
    return $plaintext;
}
