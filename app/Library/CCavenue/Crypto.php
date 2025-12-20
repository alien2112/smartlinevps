<?php

namespace App\Library\CCavenue;

class Crypto
{
    /**
     * SECURITY NOTE: Upgraded from MD5 to SHA-256 for better security.
     * Using first 16 bytes of SHA-256 hash for AES-128-CBC compatibility.
     *
     * IMPORTANT: If CCavenue API requires specific encryption format,
     * verify this change is compatible with their system.
     * Consider upgrading to their latest SDK if available.
     */
    public static function cc_encrypt($plainText,$key)
    {
        // Use SHA-256 instead of MD5, truncate to 16 bytes for AES-128
        $secretKey = substr(hash('sha256', $key, true), 0, 16);
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = openssl_encrypt($plainText, "AES-128-CBC", $secretKey, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($encryptedText);
        return $encryptedText;
    }

    public static function cc_decrypt($encryptedText,$key)
    {
        // Use SHA-256 instead of MD5, truncate to 16 bytes for AES-128
        $secretKey         = substr(hash('sha256', $key, true), 0, 16);
        $initVector         =  pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText      = hex2bin($encryptedText);
        $decryptedText         =  openssl_decrypt($encryptedText,"AES-128-CBC", $secretKey, OPENSSL_RAW_DATA, $initVector);
        return $decryptedText;
    }
    //*********** Padding Function *********************

    public function pkcs5_pad($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    //********** Hexadecimal to Binary function for php 4.0 version ********

    public function hex2bin($hexString)
    {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            $packedString = pack("H*", $subString);
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }

            $count += 2;
        }
        return $binString;
    }
}
