<?php
/**
 * Plugin Name: Open bunq Payments for WordPress
 * Description: Community-built bunq payments for WooCommerce, SureCart and generic WordPress integrations. OAuth/API verification, provider-truth reconciliation and extensible adapters.
 * Version: 3.0.0
 * Author: Open bunq Payments contributors
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: open-bunq-payments
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'OBP_VERSION', '3.0.0' );
define( 'OBP_FILE', __FILE__ );
define( 'OBP_DIR', plugin_dir_path( __FILE__ ) );
define( 'OBP_URL', plugin_dir_url( __FILE__ ) );

require_once OBP_DIR . 'includes/class-obp-crypto.php';
require_once OBP_DIR . 'includes/class-obp-settings.php';
require_once OBP_DIR . 'includes/class-obp-logger.php';
require_once OBP_DIR . 'includes/class-obp-bunq-api.php';
require_once OBP_DIR . 'includes/class-obp-store.php';
require_once OBP_DIR . 'includes/class-obp-provider.php';
require_once OBP_DIR . 'includes/class-obp-payment-service.php';
require_once OBP_DIR . 'includes/class-obp-rest.php';
require_once OBP_DIR . 'includes/class-obp-admin.php';
require_once OBP_DIR . 'includes/class-obp-integration-registry.php';
require_once OBP_DIR . 'includes/class-obp-cli.php';
require_once OBP_DIR . 'includes/integrations/generic/class-obp-generic.php';
require_once OBP_DIR . 'includes/integrations/surecart/class-obp-surecart.php';

add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['obp_five_minutes'] ) ) { $schedules['obp_five_minutes'] = array( 'interval' => 300, 'display' => __( 'Every 5 minutes (Open bunq Payments)', 'open-bunq-payments' ) ); }
    return $schedules;
} );

register_activation_hook( __FILE__, function() {
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) { deactivate_plugins( plugin_basename( __FILE__ ) ); wp_die( esc_html__( 'Open bunq Payments requires PHP 7.4 or newer.', 'open-bunq-payments' ) ); }
    if ( ! extension_loaded( 'openssl' ) ) { deactivate_plugins( plugin_basename( __FILE__ ) ); wp_die( esc_html__( 'Open bunq Payments requires the OpenSSL PHP extension.', 'open-bunq-payments' ) ); }
    OBP_Store::install(); OBP_Settings::webhook_key();
    if ( ! wp_next_scheduled( 'obp_reconcile_cron' ) ) { wp_schedule_event( time() + 120, 'obp_five_minutes', 'obp_reconcile_cron' ); }
} );
register_deactivation_hook( __FILE__, function() { wp_clear_scheduled_hook( 'obp_reconcile_cron' ); } );

add_action('plugins_loaded',array('OBP_Store','maybe_upgrade'),5);
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'open-bunq-payments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    $woo_detected=class_exists('WooCommerce')&&class_exists('WC_Payment_Gateway');
    if ( OBP_Settings::get( 'woocommerce_enabled', 'yes' ) === 'yes' && $woo_detected ) {
        require_once OBP_DIR . 'includes/integrations/woocommerce/class-obp-wc-gateway.php';
        add_filter( 'woocommerce_payment_gateways', function( $gateways ) { $gateways[] = 'OBP_WC_Gateway'; return $gateways; } );
        add_action( 'open_bunq/payment_verified', array( 'OBP_WC_Gateway', 'on_verified' ), 10, 2 );
        add_action( 'open_bunq/payment_terminal', array( 'OBP_WC_Gateway', 'on_terminal' ), 10, 2 );
    }
    OBP_Integration_Registry::register('woocommerce',array('name'=>'WooCommerce','detected'=>$woo_detected,'enabled'=>OBP_Settings::get('woocommerce_enabled','yes')==='yes','recurring'=>false,'notes'=>'One-time products; provider-verified payment_complete().'));

    OBP_SureCart::init();
    OBP_Integration_Registry::register('surecart',array('name'=>'SureCart','detected'=>class_exists('SureCart\\Models\\Checkout'),'enabled'=>OBP_Settings::get('surecart_enabled','yes')==='yes','recurring'=>false,'notes'=>'Manual Payment Method + provider-verified manually_pay.'));

    OBP_Generic::init();
    OBP_Integration_Registry::register('generic',array('name'=>'Generic / Developer API','detected'=>true,'enabled'=>OBP_Settings::get('generic_enabled','yes')==='yes','recurring'=>false,'notes'=>'Shortcode, PHP API and authenticated REST creation.'));
}, 20 );

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) { \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true ); }
} );
add_action( 'woocommerce_blocks_loaded', function() {
    if ( ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) || OBP_Settings::get( 'woocommerce_enabled', 'yes' ) !== 'yes' ) { return; }
    require_once OBP_DIR . 'includes/integrations/woocommerce/class-obp-wc-blocks.php';
    add_action( 'woocommerce_blocks_payment_method_type_registration', function( $registry ) { $registry->register( new OBP_WC_Blocks() ); } );
} );

add_action( 'rest_api_init', array( 'OBP_REST', 'register' ) );
OBP_Admin::init(); OBP_CLI::register();
add_action( 'obp_reconcile_cron', function() { ( new OBP_Payment_Service() )->reconcile_pending( 100 ); } );

function obp_admin_back($args=array()){ return add_query_arg($args,admin_url('options-general.php?page=open-bunq-payments')); }
function obp_redirect_error(Exception $e){ wp_safe_redirect(obp_admin_back(array('obp_error'=>rawurlencode($e->getMessage())))); exit; }

add_action( 'admin_post_obp_oauth_start', function() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); } check_admin_referer( 'obp_oauth_start' );
    try { wp_safe_redirect( ( new OBP_Provider() )->oauth_start_url() ); exit; } catch ( Exception $e ) { obp_redirect_error($e); }
} );

function obp_oauth_callback_handler(){
    $code=isset($_GET['code'])?sanitize_text_field(wp_unslash($_GET['code'])):''; $state=isset($_GET['state'])?sanitize_text_field(wp_unslash($_GET['state'])):'';
    try{(new OBP_Provider())->oauth_callback($code,$state); wp_safe_redirect(obp_admin_back(array('obp_connected'=>'1'))); exit;}catch(Exception $e){obp_redirect_error($e);}
}
add_action('admin_post_obp_oauth_callback','obp_oauth_callback_handler');
add_action('admin_post_nopriv_obp_oauth_callback','obp_oauth_callback_handler');

add_action( 'admin_post_obp_refresh_accounts', function() { if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('obp_refresh_accounts');try{(new OBP_Provider())->account_choices();wp_safe_redirect(obp_admin_back());exit;}catch(Exception $e){obp_redirect_error($e);} } );
add_action( 'admin_post_obp_disconnect', function() { if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('obp_disconnect');(new OBP_Provider())->disconnect();wp_safe_redirect(obp_admin_back());exit; } );
add_action( 'admin_post_obp_reconcile_now', function() { if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('obp_reconcile_now');(new OBP_Payment_Service())->reconcile_pending(250);wp_safe_redirect(obp_admin_back());exit; } );
add_action( 'admin_post_obp_surecart_provision', function() { if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('obp_surecart_provision');try{OBP_SureCart::provision();wp_safe_redirect(obp_admin_back());exit;}catch(Exception $e){obp_redirect_error($e);} } );

add_action( 'template_redirect', function() {
    if ( empty( $_GET['open_bunq_return'] ) ) { return; }
    $token=sanitize_text_field(wp_unslash($_GET['open_bunq_return'])); $record=OBP_Store::by_token($token); if(!$record)return;
    (new OBP_Payment_Service())->reconcile($record); $record=OBP_Store::get($record['id']); $target=!empty($record['return_url'])?$record['return_url']:home_url('/');
    $target=add_query_arg(array('open_bunq_status'=>$record['status'],'open_bunq_token'=>$record['public_token']),$target); wp_safe_redirect($target); exit;
} );

/** Public developer API. */
function open_bunq_create_payment( array $args ) { return ( new OBP_Payment_Service() )->create( $args ); }
function open_bunq_get_payment( $token ) { return OBP_Store::by_token( $token ); }
function open_bunq_reconcile_payment( $token ) { $r=OBP_Store::by_token($token); return $r?(new OBP_Payment_Service())->reconcile($r):false; }
