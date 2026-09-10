<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_Provider {
    private $api;

    public function api() {
        if ( $this->api instanceof OBP_Bunq_API ) { return $this->api; }
        $this->api = new OBP_Bunq_API(
            OBP_Settings::get( 'environment', 'sandbox' ),
            OBP_Settings::access_token(),
            OBP_Settings::API_CONTEXT_OPTION,
            array( $this, 'log_bridge' )
        );
        return $this->api;
    }

    public function log_bridge( $level, $message, $context ) { OBP_Logger::log( $level, $message, is_array($context)?$context:array() ); }

    private static function oauth_transient_key($state){ return 'obp_oauth_'.substr(hash('sha256',(string)$state),0,40); }

    public function oauth_start_url() {
        $client_id = (string) OBP_Settings::get( 'client_id', '' );
        if ( $client_id === '' ) { throw new RuntimeException( 'Enter a bunq OAuth Client ID first.' ); }
        $state = bin2hex( random_bytes( 24 ) );
        set_transient(self::oauth_transient_key($state),array('created'=>time(),'user_id'=>get_current_user_id()),15*MINUTE_IN_SECONDS);
        return OBP_Bunq_API::authorization_url( OBP_Settings::get( 'environment', 'sandbox' ), $client_id, OBP_Settings::oauth_redirect_uri(), $state );
    }

    public function oauth_callback( $code, $state ) {
        $key=self::oauth_transient_key($state); $stored=get_transient($key); delete_transient($key);
        if ( ! is_array( $stored ) || empty($stored['created']) || time()-(int)$stored['created']>900 || $code==='' || $state==='' ) {
            throw new RuntimeException( 'Invalid or expired OAuth state.' );
        }
        $client_id = (string) OBP_Settings::get( 'client_id', '' );
        $client_secret = OBP_Settings::get_secret( 'client_secret' );
        if ( $client_id === '' || $client_secret === '' ) { throw new RuntimeException( 'OAuth client credentials are incomplete.' ); }
        $token = OBP_Bunq_API::exchange_authorization_code( OBP_Settings::get('environment','sandbox'), $client_id, $client_secret, OBP_Settings::oauth_redirect_uri(), (string)$code );
        OBP_Settings::set_access_token( $token );
        delete_option( OBP_Settings::API_CONTEXT_OPTION );
        $this->api = null;
        $this->api()->ensure_context();
        $accounts = $this->api()->list_monetary_accounts();
        update_option( OBP_Settings::ACCOUNT_CACHE_OPTION, $accounts, false );
        $this->api()->merge_notification_filters( OBP_Settings::webhook_url() );
        return $accounts;
    }

    public function disconnect() {
        if ( OBP_Settings::connected() ) {
            try { $this->api()->remove_notification_target( OBP_Settings::webhook_url() ); } catch ( Exception $e ) { OBP_Logger::log('warning','Could not remove bunq webhook target.',array('error'=>$e->getMessage())); }
        }
        OBP_Settings::reset_connection(); $this->api = null;
    }

    public function account_choices() {
        if ( ! OBP_Settings::connected() ) { return array(); }
        try { $accounts=$this->api()->list_monetary_accounts(); update_option(OBP_Settings::ACCOUNT_CACHE_OPTION,$accounts,false); return $accounts; }
        catch(Exception $e){ $cached=get_option(OBP_Settings::ACCOUNT_CACHE_OPTION,array()); return is_array($cached)?$cached:array(); }
    }

    public static function is_trusted_payment_url($url){
        $p=wp_parse_url((string)$url); if(!is_array($p)||empty($p['scheme'])||empty($p['host'])||strtolower($p['scheme'])!=='https')return false;
        $host=strtolower(rtrim($p['host'],'.'));
        $ok=($host==='bunq.me'||$host==='bunq.com'||substr($host,-9)==='.bunq.com'||substr($host,-8)==='.bunq.me');
        return (bool)apply_filters('open_bunq/trusted_payment_url',$ok,$url,$host);
    }
}
