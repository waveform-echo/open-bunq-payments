<?php
if(!defined('WP_UNINSTALL_PLUGIN'))exit;
// Financial/audit records are preserved by default. To erase on uninstall, explicitly
// define OPEN_BUNQ_REMOVE_DATA_ON_UNINSTALL=true before uninstalling.
if(!defined('OPEN_BUNQ_REMOVE_DATA_ON_UNINSTALL')||OPEN_BUNQ_REMOVE_DATA_ON_UNINSTALL!==true)return;
global $wpdb;
$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'open_bunq_payments');
foreach(array('obp_settings_v3','obp_bunq_access_token_enc','obp_bunq_api_context_enc','obp_webhook_key_enc','obp_account_cache','obp_oauth_state','obp_db_version','obp_surecart_manual_method_id') as $option)delete_option($option);
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'obp_lock_%'");
