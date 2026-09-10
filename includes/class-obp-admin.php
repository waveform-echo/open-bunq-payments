<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class OBP_Admin {
    public static function init(){ add_action('admin_menu',array(__CLASS__,'menu')); add_action('admin_init',array(__CLASS__,'register')); }
    public static function menu(){ add_options_page(__('Open bunq Payments','open-bunq-payments'),__('Open bunq Payments','open-bunq-payments'),'manage_options','open-bunq-payments',array(__CLASS__,'page')); }
    public static function register(){ register_setting('obp_settings_group',OBP_Settings::OPTION,array('sanitize_callback'=>array(__CLASS__,'sanitize'))); }
    public static function sanitize($in){
        $old=OBP_Settings::all(); $out=$old; $in=is_array($in)?$in:array();
        $out['environment']=isset($in['environment'])&&$in['environment']==='live'?'live':'sandbox';
        $out['backend']=isset($in['backend'])&&$in['backend']==='bunqme'?'bunqme':'api';
        $out['client_id']=isset($in['client_id'])?sanitize_text_field($in['client_id']):'';
        $out['monetary_account_id']=isset($in['monetary_account_id'])?(string)absint($in['monetary_account_id']):'';
        $out['bunqme_alias']=isset($in['bunqme_alias'])?sanitize_text_field($in['bunqme_alias']):'';
        $out['merchant_label']=isset($in['merchant_label'])?sanitize_text_field($in['merchant_label']):get_bloginfo('name');
        foreach(array('debug','woocommerce_enabled','surecart_enabled','generic_enabled') as $k){$out[$k]=!empty($in[$k])?'yes':'no';}
        if(!empty($in['clear_client_secret']))$out['client_secret_enc']=''; elseif(isset($in['client_secret'])&&trim((string)$in['client_secret'])!=='')$out['client_secret_enc']=OBP_Crypto::encrypt(trim((string)$in['client_secret']));
        if(!empty($in['clear_surecart_api_key']))$out['surecart_api_key_enc']=''; elseif(isset($in['surecart_api_key'])&&trim((string)$in['surecart_api_key'])!=='')$out['surecart_api_key_enc']=OBP_Crypto::encrypt(trim((string)$in['surecart_api_key']));
        if($old['environment']!==$out['environment']||$old['client_id']!==$out['client_id'])OBP_Settings::reset_connection();
        return $out;
    }
    private static function yes($v){return $v?'<span style="color:#137333;font-weight:600">✓ '.esc_html__('Ready','open-bunq-payments').'</span>':'<span style="color:#a00;font-weight:600">○ '.esc_html__('Needs setup','open-bunq-payments').'</span>';}
    public static function page(){
        if(!current_user_can('manage_options'))return; $s=OBP_Settings::all(); $accounts=get_option(OBP_Settings::ACCOUNT_CACHE_OPTION,array());if(!is_array($accounts))$accounts=array(); $health=self::health(); $recent=OBP_Store::recent(20); $counts=OBP_Store::counts();
        $api_ready=$s['backend']==='bunqme'||(OBP_Settings::connected()&&(int)$s['monetary_account_id']>0); $provider_cfg=$s['backend']==='bunqme'?$s['bunqme_alias']!=='':($s['client_id']!==''&&OBP_Settings::get_secret('client_secret')!=='');
        ?>
        <div class="wrap"><h1><?php esc_html_e('Open bunq Payments for WordPress','open-bunq-payments');?></h1>
        <p><?php esc_html_e('Community-built payment integration for bunq. WooCommerce, SureCart and developer/generic payments share one provider-verification core. Not affiliated with or endorsed by bunq B.V.','open-bunq-payments');?></p>
        <?php if(isset($_GET['obp_error'])):?><div class="notice notice-error"><p><?php echo esc_html(wp_unslash($_GET['obp_error']));?></p></div><?php endif;?>
        <?php if(isset($_GET['obp_connected'])):?><div class="notice notice-success"><p><?php esc_html_e('bunq OAuth connection established.','open-bunq-payments');?></p></div><?php endif;?>
        <h2><?php esc_html_e('Setup checklist','open-bunq-payments');?></h2>
        <table class="widefat striped" style="max-width:1000px"><tbody>
        <tr><td>1. Provider credentials</td><td><?php echo self::yes($provider_cfg);?></td><td>Register this site redirect URL in your bunq OAuth application.</td></tr>
        <tr><td>2. Connect/select account</td><td><?php echo self::yes($api_ready);?></td><td>Sandbox first; switch to Live only after end-to-end acceptance.</td></tr>
        <tr><td>3. Commerce adapter</td><td><?php echo self::yes((class_exists('WooCommerce')&&$s['woocommerce_enabled']==='yes')||(class_exists('SureCart\\Models\\Checkout')&&$s['surecart_enabled']==='yes')||$s['generic_enabled']==='yes');?></td><td>Enable WooCommerce, SureCart, or generic/developer payments.</td></tr>
        <tr><td>4. Provider truth</td><td><?php echo self::yes($s['backend']==='api'&&$api_ready);?></td><td>API mode can verify provider status; bunq.me fallback is always manual.</td></tr>
        </tbody></table>

        <h2><?php esc_html_e('Health','open-bunq-payments');?></h2><table class="widefat striped" style="max-width:1000px"><tbody><?php foreach($health as $label=>$value):?><tr><td style="width:300px"><strong><?php echo esc_html($label);?></strong></td><td><?php echo wp_kses_post($value);?></td></tr><?php endforeach;?></tbody></table>

        <form method="post" action="options.php" style="max-width:1000px"><?php settings_fields('obp_settings_group');?>
        <h2>Provider</h2><table class="form-table">
        <tr><th>Backend</th><td><select name="<?php echo esc_attr(OBP_Settings::OPTION);?>[backend]"><option value="api" <?php selected($s['backend'],'api');?>>bunq API / OAuth (recommended, verified)</option><option value="bunqme" <?php selected($s['backend'],'bunqme');?>>bunq.me legacy/manual fallback</option></select><p class="description">Manual fallback never marks a payment paid automatically.</p></td></tr>
        <tr><th>Environment</th><td><select name="<?php echo esc_attr(OBP_Settings::OPTION);?>[environment]"><option value="sandbox" <?php selected($s['environment'],'sandbox');?>>Sandbox</option><option value="live" <?php selected($s['environment'],'live');?>>Live</option></select></td></tr>
        <tr><th>Merchant label</th><td><input class="regular-text" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[merchant_label]" value="<?php echo esc_attr($s['merchant_label']);?>"></td></tr></table>

        <h2>bunq OAuth/API</h2><table class="form-table">
        <tr><th>Client ID</th><td><input class="regular-text" autocomplete="off" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[client_id]" value="<?php echo esc_attr($s['client_id']);?>"></td></tr>
        <tr><th>Client Secret</th><td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[client_secret]" value="" placeholder="<?php echo OBP_Settings::get_secret('client_secret')!==''?'Stored — leave blank to keep':'Enter secret';?>"> <label><input type="checkbox" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[clear_client_secret]" value="1"> Clear stored secret</label><p class="description">Encrypted at rest using WordPress salts. Never shown after save.</p></td></tr>
        <tr><th>OAuth redirect URL</th><td><code><?php echo esc_html(OBP_Settings::oauth_redirect_uri());?></code></td></tr>
        <tr><th>Webhook URL</th><td><code><?php echo esc_html(OBP_Settings::webhook_url());?></code><p class="description">Webhook is only a wake-up signal. Payment status is re-fetched from bunq.</p></td></tr>
        <tr><th>Monetary account</th><td><select name="<?php echo esc_attr(OBP_Settings::OPTION);?>[monetary_account_id]"><option value="">— Select after OAuth —</option><?php foreach($accounts as $id=>$label):?><option value="<?php echo esc_attr($id);?>" <?php selected((string)$s['monetary_account_id'],(string)$id);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></td></tr></table>

        <h2>Integrations</h2><table class="form-table">
        <?php foreach(array('woocommerce_enabled'=>'WooCommerce','surecart_enabled'=>'SureCart','generic_enabled'=>'Generic shortcode + developer/REST API') as $key=>$label):?><tr><th><?php echo esc_html($label);?></th><td><label><input type="checkbox" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[<?php echo esc_attr($key);?>]" value="1" <?php checked($s[$key],'yes');?>> Enabled</label></td></tr><?php endforeach;?>
        <tr><th>SureCart Secret API Key</th><td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[surecart_api_key]" value="" placeholder="<?php echo !empty($s['surecart_api_key_enc'])?'Stored — leave blank to keep':'sk_…';?>"> <label><input type="checkbox" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[clear_surecart_api_key]" value="1"> Clear stored key</label><p class="description">Required only for SureCart. Sent server-side to api.surecart.com to provision the manual method and mark a provider-verified checkout paid.</p></td></tr>
        <tr><th>bunq.me alias</th><td><input class="regular-text" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[bunqme_alias]" value="<?php echo esc_attr($s['bunqme_alias']);?>"><p class="description">Only used in manual fallback mode.</p></td></tr>
        <tr><th>Debug logging</th><td><label><input type="checkbox" name="<?php echo esc_attr(OBP_Settings::OPTION);?>[debug]" value="1" <?php checked($s['debug'],'yes');?>> Enable non-secret diagnostics</label></td></tr></table>
        <?php submit_button();?></form>
        <p><?php if($s['backend']==='api'&&!OBP_Settings::connected()):?><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=obp_oauth_start'),'obp_oauth_start'));?>">Connect with bunq OAuth</a><?php elseif(OBP_Settings::connected()):?><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=obp_refresh_accounts'),'obp_refresh_accounts'));?>">Refresh bunq accounts</a> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=obp_disconnect'),'obp_disconnect'));?>">Disconnect bunq</a><?php endif;?> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=obp_reconcile_now'),'obp_reconcile_now'));?>">Reconcile pending</a><?php if(class_exists('SureCart\\Models\\Checkout')):?><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=obp_surecart_provision'),'obp_surecart_provision'));?>">Provision/repair SureCart method</a><?php endif;?></p>

        <h2><?php esc_html_e('Payment ledger','open-bunq-payments');?></h2><p><?php foreach($counts as $k=>$n):?><code><?php echo esc_html($k.': '.$n);?></code> <?php endforeach;?></p>
        <table class="widefat striped" style="max-width:1200px"><thead><tr><th>ID</th><th>Integration</th><th>Object</th><th>Amount</th><th>Status</th><th>Provider</th><th>Updated</th><th>Error</th></tr></thead><tbody>
        <?php if(!$recent):?><tr><td colspan="8">No payment records yet.</td></tr><?php else:foreach($recent as $r):?><tr><td><?php echo (int)$r['id'];?></td><td><?php echo esc_html($r['integration']);?></td><td><?php echo esc_html($r['object_id']);?></td><td><?php echo esc_html(number_format((float)$r['expected_amount'],2,'.','').' '.$r['currency']);?></td><td><?php echo esc_html($r['status']);?></td><td><?php echo esc_html($r['provider_status']);?></td><td><?php echo esc_html($r['updated_at']);?></td><td><?php echo esc_html($r['last_error']);?></td></tr><?php endforeach;endif;?></tbody></table>
        </div><?php
    }
    public static function health(){
        return array(
            'PHP'=>esc_html(PHP_VERSION.(version_compare(PHP_VERSION,'7.4','>=')?' ✓':' ✗')),
            'OpenSSL'=>extension_loaded('openssl')?'✓ loaded':'✗ missing',
            'HTTPS'=>is_ssl()?'✓':'⚠ required for production callbacks/OAuth',
            'bunq backend'=>esc_html(OBP_Settings::get('backend','api')),
            'bunq OAuth'=>OBP_Settings::connected()?'✓ connected':'Not connected',
            'Selected account'=>(int)OBP_Settings::get('monetary_account_id',0)>0?'✓ selected':'Not selected',
            'WooCommerce'=>class_exists('WooCommerce')?'Detected':'Not detected',
            'SureCart'=>class_exists('SureCart\\Models\\Checkout')?'Detected':'Not detected',
            'SureCart method'=>get_option(OBP_SureCart::METHOD_OPTION,'')!==''?'Provisioned':'Not provisioned',
            'Ledger table'=>self::table_exists()?'✓':'Missing — reactivate plugin',
            'Automatic recurring'=> 'Not supported by RequestInquiry — fail-closed',
        );
    }
    private static function table_exists(){global $wpdb;$t=OBP_Store::table();return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t;}
}
