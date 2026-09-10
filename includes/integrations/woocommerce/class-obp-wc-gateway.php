<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class OBP_WC_Gateway extends WC_Payment_Gateway {
    public function __construct(){
        $this->id='open_bunq'; $this->method_title=__('bunq','open-bunq-payments'); $this->method_description=__('Provider-verified bunq payment request.','open-bunq-payments');
        $this->has_fields=false; $this->supports=array('products'); $this->init_form_fields(); $this->init_settings();
        $this->title=$this->get_option('title',__('bunq','open-bunq-payments')); $this->description=$this->get_option('description',__('Pay securely using a bunq payment request.','open-bunq-payments')); $this->enabled=$this->get_option('enabled','no');
        add_action('woocommerce_update_options_payment_gateways_'.$this->id,array($this,'process_admin_options'));
    }
    public function init_form_fields(){ $this->form_fields=array('enabled'=>array('title'=>'Enable','type'=>'checkbox','label'=>'Enable bunq','default'=>'no'),'title'=>array('title'=>'Title','type'=>'text','default'=>'bunq'),'description'=>array('title'=>'Description','type'=>'textarea','default'=>'Pay securely using bunq.')); }
    public function is_available(){
        if(OBP_Settings::get('woocommerce_enabled','yes')!=='yes')return false;
        if(OBP_Settings::get('backend','api')==='api' && (!OBP_Settings::connected() || (int)OBP_Settings::get('monetary_account_id',0)<=0))return false;
        if(OBP_Settings::get('backend','api')==='bunqme' && trim((string)OBP_Settings::get('bunqme_alias',''))==='')return false;
        return parent::is_available();
    }
    public function process_payment($order_id){
        $order=wc_get_order($order_id); if(!$order) return array('result'=>'failure');
        try{
            $existing=OBP_Store::latest_for_object('woocommerce',(string)$order_id);
            if($existing && in_array($existing['status'],array('pending','verified'),true) && !empty($existing['payment_url'])){ $record=$existing; }
            else{
                $record=(new OBP_Payment_Service())->create(array('integration'=>'woocommerce','object_id'=>(string)$order_id,'user_id'=>$order->get_user_id(),'amount'=>$order->get_total(),'currency'=>$order->get_currency(),'description'=>sprintf('Order %s at %s',$order->get_order_number(),get_bloginfo('name')),'merchant_reference'=>'wc-'.$order_id,'return_url'=>$this->get_return_url($order),'metadata'=>array('order_key'=>$order->get_order_key())));
            }
            if($order->needs_payment() && $order->get_status()!=='on-hold'){ $order->update_status('on-hold',__('Awaiting bunq payment verification.','open-bunq-payments')); }
            return array('result'=>'success','redirect'=>$record['payment_url']);
        }catch(Exception $e){ wc_add_notice(__('Unable to start bunq payment: ','open-bunq-payments').$e->getMessage(),'error'); OBP_Logger::log('error','WooCommerce payment start failed.',array('order_id'=>$order_id,'error'=>$e->getMessage())); return array('result'=>'failure'); }
    }
    public static function on_verified($record,$request){ if($record['integration']!=='woocommerce')return; $order=wc_get_order((int)$record['object_id']); if(!$order)return; if($order->needs_payment()){ $order->payment_complete('bunq-request-'.$record['bunq_request_id']); $order->add_order_note(sprintf('bunq RequestInquiry %d verified by Open bunq Payments.',(int)$record['bunq_request_id'])); } }
    public static function on_terminal($record,$request){ if($record['integration']!=='woocommerce')return; $order=wc_get_order((int)$record['object_id']); if($order&&$order->needs_payment()){ $order->update_status('failed','bunq payment request '.$record['provider_status'].'.'); } }
}
