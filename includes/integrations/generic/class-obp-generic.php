<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class OBP_Generic {
    public static function init(){ add_shortcode('open_bunq_payment',array(__CLASS__,'shortcode')); add_action('admin_post_obp_generic_pay',array(__CLASS__,'handle')); add_action('admin_post_nopriv_obp_generic_pay',array(__CLASS__,'handle')); }
    public static function shortcode($atts){
        if(OBP_Settings::get('generic_enabled','yes')!=='yes') return '';
        $a=shortcode_atts(array('amount'=>'','currency'=>'EUR','description'=>'Payment','reference'=>'','label'=>'Pay with bunq','return_url'=>''),$atts,'open_bunq_payment');
        $amount=number_format((float)$a['amount'],2,'.',''); if((float)$amount<=0)return '<em>'.esc_html__('Invalid bunq payment amount.','open-bunq-payments').'</em>';
        $payload=array('amount'=>$amount,'currency'=>strtoupper($a['currency']),'description'=>sanitize_text_field($a['description']),'reference'=>sanitize_text_field($a['reference']),'return_url'=>$a['return_url']?esc_url_raw($a['return_url']):self::current_url(),'exp'=>time()+HOUR_IN_SECONDS);
        $encoded=rtrim(strtr(base64_encode(wp_json_encode($payload)),'+/','-_'),'='); $sig=hash_hmac('sha256',$encoded,wp_salt('nonce'));
        ob_start();?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="obp_generic_pay"><input type="hidden" name="config" value="<?php echo esc_attr($encoded);?>"><input type="hidden" name="sig" value="<?php echo esc_attr($sig);?>"><?php wp_nonce_field('obp_generic_pay','obp_nonce');?><button type="submit" class="button open-bunq-pay-button"><?php echo esc_html($a['label']);?></button></form><?php return ob_get_clean();
    }
    public static function handle(){
        if(OBP_Settings::get('generic_enabled','yes')!=='yes')wp_die('Generic bunq payments disabled.',403); check_admin_referer('obp_generic_pay','obp_nonce');
        $cfg=isset($_POST['config'])?sanitize_text_field(wp_unslash($_POST['config'])):''; $sig=isset($_POST['sig'])?sanitize_text_field(wp_unslash($_POST['sig'])):'';
        if($cfg===''||$sig===''||!hash_equals(hash_hmac('sha256',$cfg,wp_salt('nonce')),$sig))wp_die('Invalid payment configuration.',403);
        $pad=strlen($cfg)%4; $b64=strtr($cfg,'-_','+/').($pad?str_repeat('=',4-$pad):''); $raw=base64_decode($b64,true); $data=json_decode((string)$raw,true);
        if(!is_array($data)||empty($data['exp'])||time()>(int)$data['exp'])wp_die('Payment configuration expired.',403);
        try{
            $object='generic-'.wp_generate_uuid4(); $record=(new OBP_Payment_Service())->create(array('integration'=>'generic','object_id'=>$object,'user_id'=>get_current_user_id(),'amount'=>$data['amount'],'currency'=>$data['currency'],'description'=>$data['description'],'merchant_reference'=>$data['reference']!==''?$data['reference']:$object,'return_url'=>$data['return_url'],'metadata'=>array('source'=>'shortcode')));
            if(!OBP_Provider::is_trusted_payment_url($record['payment_url']))wp_die('Payment provider returned an untrusted URL.',500);
            wp_redirect($record['payment_url'],302,'Open bunq Payments'); exit;
        }catch(Exception $e){ wp_die(esc_html('Unable to start bunq payment: '.$e->getMessage()),500); }
    }
    private static function current_url(){ $uri=isset($_SERVER['REQUEST_URI'])?wp_unslash($_SERVER['REQUEST_URI']):'/'; $path=wp_parse_url($uri,PHP_URL_PATH); $query=wp_parse_url($uri,PHP_URL_QUERY); $url=home_url($path?$path:'/'); return $query?add_query_arg(array(),$url.'?'.$query):$url; }
}
