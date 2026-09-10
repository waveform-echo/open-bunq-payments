<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class OBP_REST {
    public static function register() {
        register_rest_route('open-bunq/v1','/webhook/(?P<key>[A-Za-z0-9]+)',array('methods'=>array('GET','POST'),'callback'=>array(__CLASS__,'webhook'),'permission_callback'=>'__return_true'));
        register_rest_route('open-bunq/v1','/payment/(?P<token>[A-Fa-f0-9]{32,64})',array('methods'=>'GET','callback'=>array(__CLASS__,'status'),'permission_callback'=>'__return_true'));
        register_rest_route('open-bunq/v1','/payments',array('methods'=>'POST','callback'=>array(__CLASS__,'create'),'permission_callback'=>array(__CLASS__,'can_create')));
    }
    public static function can_create($request){ return (bool)apply_filters('open_bunq/rest_can_create',current_user_can('manage_options'),$request); }
    public static function webhook(WP_REST_Request $request){
        $key=(string)$request['key']; $expected=OBP_Settings::webhook_key(); if(!hash_equals($expected,$key))return new WP_Error('forbidden','Invalid webhook token.',array('status'=>403));
        // Webhooks are hints, never payment proof. Re-fetch provider truth.
        (new OBP_Payment_Service())->reconcile_pending(100); return new WP_REST_Response(array('ok'=>true),200);
    }
    public static function status(WP_REST_Request $request){
        $record=OBP_Store::by_token((string)$request['token']); if(!$record)return new WP_Error('not_found','Payment not found.',array('status'=>404));
        return new WP_REST_Response(array('token'=>$record['public_token'],'integration'=>$record['integration'],'object_id'=>$record['object_id'],'status'=>$record['status'],'provider_status'=>$record['provider_status'],'amount'=>number_format((float)$record['expected_amount'],2,'.',''),'currency'=>$record['currency'],'updated_at'=>$record['updated_at'],'verified_at'=>$record['verified_at']),200);
    }
    public static function create(WP_REST_Request $request){
        $p=$request->get_json_params(); if(!is_array($p))$p=$request->get_params();
        try{$record=(new OBP_Payment_Service())->create(array(
            'integration'=>isset($p['integration'])?$p['integration']:'rest','object_id'=>isset($p['object_id'])?$p['object_id']:wp_generate_uuid4(),'user_id'=>get_current_user_id(),
            'amount'=>isset($p['amount'])?$p['amount']:'','currency'=>isset($p['currency'])?$p['currency']:'EUR','description'=>isset($p['description'])?$p['description']:'Payment',
            'merchant_reference'=>isset($p['merchant_reference'])?$p['merchant_reference']:'','return_url'=>isset($p['return_url'])?$p['return_url']:home_url('/'),'metadata'=>isset($p['metadata'])&&is_array($p['metadata'])?$p['metadata']:array('source'=>'rest')
        )); return new WP_REST_Response(array('token'=>$record['public_token'],'status'=>$record['status'],'payment_url'=>$record['payment_url']),201);}
        catch(Exception $e){return new WP_Error('payment_create_failed',$e->getMessage(),array('status'=>400));}
    }
}
