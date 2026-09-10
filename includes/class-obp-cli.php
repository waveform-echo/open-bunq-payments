<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_CLI {
    public static function register(){ if(defined('WP_CLI')&&WP_CLI){ WP_CLI::add_command('open-bunq',__CLASS__); } }
    /** Show configuration/health without revealing secrets. */
    public function health(){
        WP_CLI::line('Version: '.OBP_VERSION);
        WP_CLI::line('Backend: '.OBP_Settings::get('backend','api'));
        WP_CLI::line('Environment: '.OBP_Settings::get('environment','sandbox'));
        WP_CLI::line('OAuth connected: '.(OBP_Settings::connected()?'yes':'no'));
        WP_CLI::line('Monetary account selected: '.((int)OBP_Settings::get('monetary_account_id',0)>0?'yes':'no'));
        WP_CLI::line('Pending: '.count(OBP_Store::pending(250)));
    }
    /** Reconcile pending provider-backed payments. */
    public function reconcile($args,$assoc){ $limit=isset($assoc['limit'])?max(1,min(250,(int)$assoc['limit'])):100; (new OBP_Payment_Service())->reconcile_pending($limit); WP_CLI::success('Reconciliation pass completed.'); }
    /** Inspect a payment by public token. */
    public function status($args){ if(empty($args[0]))WP_CLI::error('Pass a payment token.'); $r=OBP_Store::by_token($args[0]); if(!$r)WP_CLI::error('Payment not found.'); WP_CLI::print_value(array_intersect_key($r,array_flip(array('id','public_token','integration','object_id','expected_amount','currency','status','provider_status','created_at','updated_at','verified_at','last_error'))),array('format'=>'json')); }
}
