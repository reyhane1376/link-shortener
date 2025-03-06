<?php
namespace App\Utils;

class HashTable {
    /**
     * Generate a short code using a base62 encoding of a hash value
     * 
     * @param string $url The URL to encode
     * @param int $length The desired length of the short code
     * @return string The generated short code
     */
    public static function generateShortCode($url, $length = 6) {
        // Generate a hash of the URL + a timestamp to ensure uniqueness
        $hash = md5($url . microtime());
        
        // Convert the hex hash to a base62 representation (using 0-9, a-z, A-Z)
        $base62 = self::convertToBase62($hash);
        
        // Take only the first $length characters
        return substr($base62, 0, $length);
    }
    
    /**
     * Convert a hexadecimal string to base62 (0-9, a-z, A-Z)
     * 
     * @param string $hex The hexadecimal input string
     * @return string The base62 encoded string
     */
    private static function convertToBase62($hex) {
        // Convert hex to decimal
        $decimal = base_convert($hex, 16, 10);
        
        // Define the character set for base62
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($chars);
        
        // Convert to base62
        $result = '';
        $value = gmp_init($decimal, 10);
        
        while (gmp_cmp($value, 0) > 0) {
            $remainder = gmp_mod($value, $base);
            $result = $chars[gmp_intval($remainder)] . $result;
            $value = gmp_div_q($value, $base);
        }
        
        return $result ?: '0';
    }
    
    /**
     * Generate a collision-resistant unique short code
     * 
     * @param string $url The URL to encode
     * @param array $existingCodes Array of existing codes to check against
     * @param int $length The desired length of the short code
     * @return string The generated collision-free short code
     */
    public static function generateUniqueCode($url, $existingCodes = [], $length = 6) {
        $attempts = 0;
        $maxAttempts = 5;
        
        do {
            // Add attempt number to ensure different hashes on retry
            $shortCode = self::generateShortCode($url . $attempts, $length);
            $attempts++;
            
            // Check if code already exists
            if (!in_array($shortCode, $existingCodes)) {
                return $shortCode;
            }
        } while ($attempts < $maxAttempts);
        
        // If all attempts failed, increase the length and try again
        return self::generateUniqueCode($url, $existingCodes, $length + 1);
    }
}
