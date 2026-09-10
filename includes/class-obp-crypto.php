<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OBP_Crypto {
    private static function key() {
        $material = '';
        foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
            if ( defined( $constant ) ) {
                $material .= constant( $constant );
            }
        }
        if ( $material === '' ) {
            $material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
        }
        return hash( 'sha256', $material, true );
    }

    public static function encrypt( $plaintext ) {
        if ( $plaintext === null || $plaintext === '' ) {
            return '';
        }

        $key = self::key();

        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            $nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $cipher = sodium_crypto_secretbox( (string) $plaintext, $nonce, $key );
            return 'sodium:' . base64_encode( $nonce . $cipher );
        }

        $iv  = random_bytes( 12 );
        $tag = '';
        $cipher = openssl_encrypt(
            (string) $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ( $cipher === false ) {
            throw new RuntimeException( 'Unable to encrypt bunq secret.' );
        }

        return 'gcm:' . base64_encode( $iv . $tag . $cipher );
    }

    public static function decrypt( $encoded ) {
        if ( ! is_string( $encoded ) || $encoded === '' ) {
            return '';
        }

        $key = self::key();

        if ( strpos( $encoded, 'sodium:' ) === 0 ) {
            $raw = base64_decode( substr( $encoded, 7 ), true );
            if ( $raw === false || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
                return '';
            }
            $nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
            return $plain === false ? '' : $plain;
        }

        if ( strpos( $encoded, 'gcm:' ) === 0 ) {
            $raw = base64_decode( substr( $encoded, 4 ), true );
            if ( $raw === false || strlen( $raw ) <= 28 ) {
                return '';
            }
            $iv     = substr( $raw, 0, 12 );
            $tag    = substr( $raw, 12, 16 );
            $cipher = substr( $raw, 28 );
            $plain  = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            return $plain === false ? '' : $plain;
        }

        return '';
    }
}
