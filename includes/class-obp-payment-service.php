<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_Payment_Service {
    private $provider;
    public function __construct() { $this->provider = new OBP_Provider(); }

    public function create( array $args ) {
        $args=(array)apply_filters('open_bunq/payment_args',$args);
        $required=array('integration','object_id','amount','currency','description','return_url');
        foreach($required as $key){ if(!isset($args[$key]) || $args[$key]===''){ throw new InvalidArgumentException('Missing payment field: '.$key); } }
        $integration=sanitize_key($args['integration']); $object_id=(string)$args['object_id'];
        if($integration===''||$object_id==='')throw new InvalidArgumentException('Invalid integration/object id.');
        $currency=strtoupper((string)$args['currency']); if(!preg_match('/^[A-Z]{3}$/',$currency))throw new InvalidArgumentException('Invalid currency.');
        $amount=number_format((float)$args['amount'],2,'.',''); if((float)$amount<=0)throw new InvalidArgumentException('Amount must be positive.');
        $return_url=$this->normalize_return_url((string)$args['return_url']);
        $lock=OBP_Store::acquire_object_lock($integration,$object_id); if(!$lock)throw new RuntimeException('A payment for this object is already being created. Retry shortly.');
        try{
            $existing=OBP_Store::latest_for_object($integration,$object_id);
            if($existing && in_array($existing['status'],array('pending','processing','verified'),true) && !empty($existing['payment_url'])) return $existing;
            $account_id=(int)OBP_Settings::get('monetary_account_id',0); $backend=OBP_Settings::get('backend','api');
            $record=OBP_Store::create(array(
                'integration'=>$integration,'object_id'=>$object_id,'user_id'=>isset($args['user_id'])?(int)$args['user_id']:0,
                'expected_amount'=>$amount,'currency'=>$currency,'description'=>sanitize_text_field($args['description']),
                'merchant_reference'=>!empty($args['merchant_reference'])?sanitize_text_field($args['merchant_reference']):$object_id,
                'return_url'=>$return_url,'bunq_account_id'=>$account_id,'metadata'=>isset($args['metadata'])&&is_array($args['metadata'])?$args['metadata']:array(),'status'=>'created'
            ));
            try{
                if($backend==='bunqme'){
                    $alias=trim((string)OBP_Settings::get('bunqme_alias','')); if($alias==='')throw new RuntimeException('bunq.me alias is not configured.');
                    $url='https://bunq.me/'.rawurlencode($alias).'/'.rawurlencode($amount).'/'.rawurlencode('#'.(string)$record['id']);
                    if(!OBP_Provider::is_trusted_payment_url($url))throw new RuntimeException('Generated bunq.me URL was rejected by the payment URL policy.');
                    $record=OBP_Store::update($record['id'],array('payment_url'=>$url,'status'=>'pending','provider_status'=>'MANUAL'));
                }else{
                    if(!OBP_Settings::connected())throw new RuntimeException('bunq OAuth is not connected.');
                    if($account_id<=0)throw new RuntimeException('Select a bunq monetary account first.');
                    $return=add_query_arg('open_bunq_return',$record['public_token'],home_url('/'));
                    $created=$this->provider->api()->create_request_inquiry($account_id,$amount,$currency,(string)$args['description'],(string)$record['merchant_reference'],$return);
                    if(empty($created['url'])||!OBP_Provider::is_trusted_payment_url($created['url']))throw new RuntimeException('bunq returned an untrusted payment URL.');
                    $record=OBP_Store::update($record['id'],array('bunq_request_id'=>(int)$created['id'],'payment_url'=>$created['url'],'status'=>'pending','provider_status'=>OBP_Bunq_API::request_status(is_array($created['request'])?$created['request']:array())));
                }
                do_action('open_bunq/payment_created',$record,$args); return $record;
            }catch(Exception $e){ OBP_Store::update($record['id'],array('status'=>'error','last_error'=>$e->getMessage())); do_action('open_bunq/payment_error',$record['id'],$e,$args); throw $e; }
        } finally { OBP_Store::release_object_lock($lock); }
    }

    public function reconcile( array $record ) {
        if(empty($record['id'])||$record['status']==='verified')return $record['status']==='verified';
        if(OBP_Settings::get('backend','api')!=='api')return false;
        if((int)$record['bunq_request_id']<=0||(int)$record['bunq_account_id']<=0)return false;
        $lock=OBP_Store::acquire_object_lock('reconcile',(string)$record['id'],45); if(!$lock)return false;
        try{
            $record=OBP_Store::get($record['id']); if(!$record||$record['status']==='verified')return true;
            $request=$this->provider->api()->get_request_inquiry((int)$record['bunq_account_id'],(int)$record['bunq_request_id']);
            $status=OBP_Bunq_API::request_status($request); $amount=OBP_Bunq_API::request_amount($request); $hash=hash('sha256',wp_json_encode($request));
            OBP_Store::update($record['id'],array('provider_status'=>$status,'provider_payload_hash'=>$hash,'last_error'=>''));
            if($status==='ACCEPTED'){
                $expected=number_format((float)$record['expected_amount'],2,'.',''); $actual=$amount['value']===''?'':number_format((float)$amount['value'],2,'.','');
                $currency_ok=strtoupper((string)$record['currency'])===strtoupper((string)$amount['currency']); $amount_ok=$actual!==''&&hash_equals($expected,$actual);
                if(!$currency_ok||!$amount_ok){
                    OBP_Store::update($record['id'],array('status'=>'mismatch','last_error'=>'Provider accepted payment but amount/currency did not match.','provider_status'=>$status,'provider_payload_hash'=>$hash));
                    do_action('open_bunq/payment_mismatch',OBP_Store::get($record['id']),$request); return false;
                }
                $transition=OBP_Store::mark_verified($record['id'],$status,$hash);
                if($transition['changed'])do_action('open_bunq/payment_verified',$transition['record'],$request);
                return $transition['record'] && $transition['record']['status']==='verified';
            }
            if(in_array($status,array('REJECTED','REVOKED','EXPIRED'),true)){
                $transition=OBP_Store::mark_terminal($record['id'],strtolower($status),$status,$hash);
                if($transition['changed'])do_action('open_bunq/payment_terminal',$transition['record'],$request);
            }else{ OBP_Store::update($record['id'],array('status'=>'pending','provider_status'=>$status,'provider_payload_hash'=>$hash)); }
        }catch(Exception $e){ OBP_Store::update($record['id'],array('last_error'=>$e->getMessage())); OBP_Logger::log('warning','Payment reconciliation failed.',array('payment_id'=>$record['id'],'error'=>$e->getMessage())); }
        finally{ OBP_Store::release_object_lock($lock); }
        return false;
    }

    public function reconcile_pending($limit=50){ foreach(OBP_Store::pending($limit) as $record){ $this->reconcile($record); } }

    private function normalize_return_url($url){
        $url=esc_url_raw($url); if($url==='')throw new InvalidArgumentException('Invalid return URL.');
        $target=wp_parse_url($url); $home=wp_parse_url(home_url('/'));
        $same=is_array($target)&&is_array($home)&&!empty($target['host'])&&!empty($home['host'])&&strtolower($target['host'])===strtolower($home['host']);
        $allowed=(bool)apply_filters('open_bunq/allow_external_return_url',$same,$url);
        if(!$allowed)throw new InvalidArgumentException('Return URL must use this WordPress site unless explicitly allowed by a developer filter.');
        return $url;
    }
}
