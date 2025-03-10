<?php
namespace App\Utils;

class HashFunc {
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
}
