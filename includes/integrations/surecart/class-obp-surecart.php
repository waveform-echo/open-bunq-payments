<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_SureCart {
    const METHOD_OPTION='obp_surecart_manual_method_id';
    const API_BASE='https://api.surecart.com/v1';

    public static function init(){
        add_filter('surecart/request/response',array(__CLASS__,'filter_response'),20,3);
        add_filter('surecart/checkout/validate',array(__CLASS__,'validate_checkout'),20,3);
        add_action('surecart/order_created',array(__CLASS__,'order_created'),20,2);
        add_action('open_bunq/payment_verified',array(__CLASS__,'on_verified'),10,2);
        add_action('open_bunq/payment_terminal',array(__CLASS__,'on_terminal'),10,2);
        add_action('wp_enqueue_scripts',array(__CLASS__,'enqueue'));
    }

    public static function api_key(){ $s=OBP_Settings::all(); return OBP_Crypto::decrypt(isset($s['surecart_api_key_enc'])?$s['surecart_api_key_enc']:''); }

    private static function request($method,$path,$body=null){
        $key=self::api_key(); if($key==='') throw new RuntimeException('SureCart Secret API Key is not configured.');
        $args=array('method'=>strtoupper($method),'timeout'=>30,'redirection'=>0,'headers'=>array('Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json','Accept'=>'application/json'));
        if($body!==null) $args['body']=wp_json_encode($body);
        $r=wp_remote_request(self::API_BASE.'/'.ltrim($path,'/'),$args);
        if(is_wp_error($r)) throw new RuntimeException('SureCart API request failed: '.$r->get_error_message());
        $code=wp_remote_retrieve_response_code($r); $raw=wp_remote_retrieve_body($r); $json=$raw===''?array():json_decode($raw,true);
        if($code<200||$code>=300||!is_array($json)){ $msg=is_array($json)&&isset($json['message'])?(string)$json['message']:'HTTP '.$code; throw new RuntimeException('SureCart API error: '.$msg); }
        return $json;
    }

    public static function provision(){
        if(OBP_Settings::get('surecart_enabled','yes')!=='yes') throw new RuntimeException('SureCart integration is disabled.');
        if(OBP_Settings::get('backend','api')==='api' && (!OBP_Settings::connected() || (int)OBP_Settings::get('monetary_account_id',0)<=0)) throw new RuntimeException('Connect bunq OAuth and select a monetary account first.');
        if(OBP_Settings::get('backend','api')==='bunqme' && trim((string)OBP_Settings::get('bunqme_alias',''))==='') throw new RuntimeException('Configure the bunq.me alias first.');
        $id=(string)get_option(self::METHOD_OPTION,'');
        $payload=array('manual_payment_method'=>array(
            'name'=>'bunq',
            'description'=>'Pay securely with bunq. Payment is verified automatically before the order is marked paid.',
            'instructions'=>'You will be sent to bunq to complete payment. If the redirect does not open, use the bunq payment link shown or emailed by the store.',
            'reusable'=>false,
        ));
        if($id!==''){
            try { $obj=self::request('PATCH','/manual_payment_methods/'.rawurlencode($id),$payload); return $obj; } catch(Exception $e){ delete_option(self::METHOD_OPTION); }
        }
        $obj=self::request('POST','/manual_payment_methods',$payload);
        if(empty($obj['id'])) throw new RuntimeException('SureCart did not return a Manual Payment Method id.');
        update_option(self::METHOD_OPTION,(string)$obj['id'],false);
        return $obj;
    }

    public static function validate_checkout($errors,$args,$request){
        if(OBP_Settings::get('surecart_enabled','yes')!=='yes') return $errors;
        $method=(string)get_option(self::METHOD_OPTION,''); if($method==='') return $errors;
        $params=$request instanceof WP_REST_Request?$request->get_params():array();
        if(!self::contains_value($params,$method)) return $errors;
        $checkout=isset($params['checkout'])&&is_array($params['checkout'])?$params['checkout']:(is_array($args)?$args:array());
        if(!empty($checkout['reusable_payment_method_required']) || self::contains_recurring($checkout)){
            $errors->add('open_bunq_no_recurring',__('bunq via Open bunq Payments currently supports one-time payment requests only. Choose a recurring-capable processor for subscriptions.','open-bunq-payments'));
        }
        return $errors;
    }

    public static function filter_response($response,$args,$endpoint){
        if(OBP_Settings::get('surecart_enabled','yes')!=='yes') return $response;
        if(strpos((string)$endpoint,'checkouts/')===false || strpos((string)$endpoint,'/finalize')===false) return $response;
        $method=(string)get_option(self::METHOD_OPTION,''); if($method==='') return $response;
        $data=self::to_array($response);
        $selected=self::manual_method_matches($data,$method) || (is_array($args) && self::contains_value($args,$method));
        if(!$data || !$selected || !in_array(isset($data['status'])?$data['status']:'',array('processing','finalized'),true)) return $response;
        try{
            $record=self::ensure_payment_for_checkout($data);
            if($record && !empty($record['payment_url'])){
                if(is_object($response)) $response->open_bunq_payment_url=$record['payment_url'];
                elseif(is_array($response)) $response['open_bunq_payment_url']=$record['payment_url'];
            }
        }catch(Exception $e){ OBP_Logger::log('error','SureCart payment initiation failed.',array('error'=>$e->getMessage())); }
        return $response;
    }

    public static function order_created($order,$raw){
        if(OBP_Settings::get('surecart_enabled','yes')!=='yes') return;
        try{
            $order_id=is_object($order)&&isset($order->id)?(string)$order->id:(is_array($order)&&isset($order['id'])?(string)$order['id']:'');
            if($order_id==='') return;
            $obj=self::request('GET','/orders/'.rawurlencode($order_id).'?expand[]=checkout&expand[]=customer');
            $checkout=isset($obj['checkout'])&&is_array($obj['checkout'])?$obj['checkout']:array();
            if(!$checkout) return;
            $method=(string)get_option(self::METHOD_OPTION,'');
            if($method===''||!self::manual_method_matches($checkout,$method)) return;
            $record=self::ensure_payment_for_checkout($checkout);
            $email='';
            if(isset($obj['customer']['email']))$email=(string)$obj['customer']['email']; elseif(isset($checkout['email']))$email=(string)$checkout['email'];
            if($email!=='' && $record && !empty($record['payment_url'])){
                $flag='obp_sc_mail_'.$record['id'];
                if(!get_transient($flag)){ wp_mail($email,__('Complete your bunq payment','open-bunq-payments'),sprintf("Complete your payment securely with bunq:\n\n%s\n\nReference: %s",$record['payment_url'],$record['merchant_reference'])); set_transient($flag,1,DAY_IN_SECONDS); }
            }
        }catch(Exception $e){ OBP_Logger::log('warning','SureCart order fallback could not create/send bunq payment.',array('error'=>$e->getMessage())); }
    }

    private static function ensure_payment_for_checkout(array $checkout){
        if(empty($checkout['id'])) throw new RuntimeException('SureCart checkout id missing.');
        $id=(string)$checkout['id']; $existing=OBP_Store::latest_for_object('surecart',$id);
        if($existing&&in_array($existing['status'],array('pending','verified'),true)&&!empty($existing['payment_url'])) return $existing;
        if(!empty($checkout['reusable_payment_method_required']) || self::contains_recurring($checkout)) throw new RuntimeException('Recurring SureCart checkout is not supported by bunq RequestInquiry.');
        if(array_key_exists('live_mode',$checkout)){
            $bunq_live=OBP_Settings::get('environment','sandbox')==='live';
            if((bool)$checkout['live_mode']!==$bunq_live) throw new RuntimeException('SureCart checkout mode and bunq environment do not match.');
        }
        $minor=isset($checkout['remaining_amount_due'])?(int)$checkout['remaining_amount_due']:(isset($checkout['amount_due'])?(int)$checkout['amount_due']:(isset($checkout['total_amount'])?(int)$checkout['total_amount']:0));
        if($minor<=0) throw new RuntimeException('SureCart checkout amount is not payable.');
        $currency=strtoupper(isset($checkout['currency'])?(string)$checkout['currency']:'EUR');
        $return=isset($checkout['portal_url'])&&$checkout['portal_url']?esc_url_raw($checkout['portal_url']):home_url('/');
        return (new OBP_Payment_Service())->create(array('integration'=>'surecart','object_id'=>$id,'user_id'=>get_current_user_id(),'amount'=>$minor/100,'currency'=>$currency,'description'=>sprintf('SureCart checkout %s at %s',$id,get_bloginfo('name')),'merchant_reference'=>'sc-'.$id,'return_url'=>$return,'metadata'=>array('order'=>isset($checkout['order'])?$checkout['order']:'','email'=>isset($checkout['email'])?$checkout['email']:'')));
    }

    public static function on_verified($record,$request){
        if($record['integration']!=='surecart') return;
        try{
            $checkout=self::request('GET','/checkouts/'.rawurlencode($record['object_id']));
            if(isset($checkout['status'])&&$checkout['status']==='paid') return;
            if(!isset($checkout['status'])||!in_array($checkout['status'],array('finalized','processing'),true)) throw new RuntimeException('SureCart checkout is not finalized/processing.');
            self::request('PATCH','/checkouts/'.rawurlencode($record['object_id']).'/manually_pay',array());
            do_action('open_bunq/surecart_checkout_paid',$record['object_id'],$record);
        }catch(Exception $e){ OBP_Store::update($record['id'],array('last_error'=>'SureCart mark-paid failed: '.$e->getMessage())); OBP_Logger::log('error','Verified bunq payment could not mark SureCart checkout paid.',array('payment_id'=>$record['id'],'error'=>$e->getMessage())); }
    }
    public static function on_terminal($record,$request){ if($record['integration']==='surecart') do_action('open_bunq/surecart_payment_terminal',$record['object_id'],$record); }

    public static function enqueue(){ if(OBP_Settings::get('surecart_enabled','yes')==='yes' && class_exists('SureCart\\Models\\Checkout')) wp_enqueue_script('obp-surecart',OBP_URL.'assets/js/surecart.js',array(),OBP_VERSION,true); }

    private static function manual_method_matches(array $data,$method){
        if(isset($data['manual_payment_method'])){
            if(is_string($data['manual_payment_method'])&&$data['manual_payment_method']===$method)return true;
            if(is_array($data['manual_payment_method'])&&isset($data['manual_payment_method']['id'])&&(string)$data['manual_payment_method']['id']===$method)return true;
        }
        return self::contains_value($data,$method);
    }
    private static function contains_value($node,$needle){ if(is_array($node)){foreach($node as $v){if(is_scalar($v)&&(string)$v===(string)$needle)return true;if(is_array($v)&&self::contains_value($v,$needle))return true;}} return false; }
    private static function contains_recurring($node){ if(!is_array($node))return false; foreach($node as $k=>$v){ if(in_array((string)$k,array('recurring_interval','recurring_period','subscription'),true)&&!empty($v))return true; if(is_array($v)&&self::contains_recurring($v))return true; } return false; }
    private static function to_array($v){ if(is_array($v))return $v; if(is_object($v)){ if(method_exists($v,'toArray')){ $a=$v->toArray(); return is_array($a)?$a:array(); } return json_decode(wp_json_encode($v),true)?:array(); } return array(); }
}
