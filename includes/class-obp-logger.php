<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class OBP_Logger {
    public static function log( $level, $message, array $context=array() ) {
        if ( OBP_Settings::get('debug','no') !== 'yes' && ! in_array($level,array('error','warning'),true) ) { return; }
        if ( function_exists('wc_get_logger') ) {
            wc_get_logger()->log( $level, $message, array_merge(array('source'=>'open-bunq-payments'),$context) );
        } elseif ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[Open bunq][' . strtoupper($level) . '] ' . $message . ( $context ? ' ' . wp_json_encode($context) : '' ) );
        }
    }
}
