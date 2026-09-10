<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
final class OBP_WC_Blocks extends AbstractPaymentMethodType {
    protected $name='open_bunq';
    public function initialize(){ $this->settings=get_option('woocommerce_open_bunq_settings',array()); }
    public function is_active(){
        if(empty($this->settings['enabled'])||$this->settings['enabled']!=='yes'||OBP_Settings::get('woocommerce_enabled','yes')!=='yes')return false;
        if(OBP_Settings::get('backend','api')==='api')return OBP_Settings::connected()&&(int)OBP_Settings::get('monetary_account_id',0)>0;
        return trim((string)OBP_Settings::get('bunqme_alias',''))!=='';
    }
    public function get_payment_method_script_handles(){ wp_register_script('obp-wc-blocks',OBP_URL.'assets/js/wc-blocks.js',array('wc-blocks-registry','wc-settings','wp-element','wp-html-entities'),OBP_VERSION,true); return array('obp-wc-blocks'); }
    public function get_payment_method_data(){ return array('title'=>isset($this->settings['title'])?$this->settings['title']:'bunq','description'=>isset($this->settings['description'])?$this->settings['description']:'Pay securely using bunq.','supports'=>array('products')); }
}
