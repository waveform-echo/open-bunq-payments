<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_Settings {
    const OPTION = 'obp_settings_v3';
    const ACCESS_TOKEN_OPTION = 'obp_bunq_access_token_enc';
    const API_CONTEXT_OPTION = 'obp_bunq_api_context_enc';
    const WEBHOOK_KEY_OPTION = 'obp_webhook_key_enc';
    const ACCOUNT_CACHE_OPTION = 'obp_account_cache';
    const OAUTH_STATE_OPTION = 'obp_oauth_state';

    public static function defaults() {
        return array(
            'environment' => 'sandbox',
            'client_id' => '',
            'client_secret_enc' => '',
            'monetary_account_id' => '',
            'backend' => 'api',
            'bunqme_alias' => '',
            'debug' => 'no',
            'merchant_label' => get_bloginfo( 'name' ),
            'surecart_enabled' => 'yes',
            'surecart_api_key_enc' => '',
            'woocommerce_enabled' => 'yes',
            'generic_enabled' => 'yes',
        );
    }

    public static function all() {
        return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
    }

    public static function get( $key, $default = null ) {
        $all = self::all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    public static function update( array $values ) {
        $current = self::all();
        foreach ( $values as $key => $value ) { $current[ $key ] = $value; }
        update_option( self::OPTION, $current, false );
        return $current;
    }

    public static function set_secret( $key, $value ) {
        $all = self::all();
        $all[ $key . '_enc' ] = $value === '' ? '' : OBP_Crypto::encrypt( (string) $value );
        update_option( self::OPTION, $all, false );
    }

    public static function get_secret( $key ) {
        $all = self::all();
        return OBP_Crypto::decrypt( isset( $all[ $key . '_enc' ] ) ? $all[ $key . '_enc' ] : '' );
    }

    public static function access_token() {
        return OBP_Crypto::decrypt( get_option( self::ACCESS_TOKEN_OPTION, '' ) );
    }

    public static function set_access_token( $token ) {
        update_option( self::ACCESS_TOKEN_OPTION, OBP_Crypto::encrypt( (string) $token ), false );
    }

    public static function connected() {
        return self::access_token() !== '' && get_option( self::API_CONTEXT_OPTION, '' ) !== '';
    }

    public static function webhook_key() {
        $key = OBP_Crypto::decrypt( get_option( self::WEBHOOK_KEY_OPTION, '' ) );
        if ( $key === '' ) {
            $key = bin2hex( random_bytes( 24 ) );
            update_option( self::WEBHOOK_KEY_OPTION, OBP_Crypto::encrypt( $key ), false );
        }
        return $key;
    }

    public static function webhook_url() {
        return rest_url( 'open-bunq/v1/webhook/' . rawurlencode( self::webhook_key() ) );
    }

    public static function oauth_redirect_uri() {
        return admin_url( 'admin-post.php?action=obp_oauth_callback' );
    }

    public static function reset_connection() {
        delete_option( self::ACCESS_TOKEN_OPTION );
        delete_option( self::API_CONTEXT_OPTION );
        delete_option( self::ACCOUNT_CACHE_OPTION );
        delete_option( self::OAUTH_STATE_OPTION );
    }
}
