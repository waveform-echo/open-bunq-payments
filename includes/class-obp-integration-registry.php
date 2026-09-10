<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_Integration_Registry {
    private static $items = array();
    public static function register($slug,array $meta=array()){
        $slug=sanitize_key($slug); if($slug==='')return;
        self::$items[$slug]=wp_parse_args($meta,array('name'=>$slug,'detected'=>true,'enabled'=>true,'recurring'=>false,'notes'=>''));
    }
    public static function all(){ return (array)apply_filters('open_bunq/integrations',self::$items); }
}
