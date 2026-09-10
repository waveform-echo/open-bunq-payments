<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OBP_Store {
    const DB_VERSION = '2';

    public static function table() { global $wpdb; return $wpdb->prefix . 'open_bunq_payments'; }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        $sql = "CREATE TABLE {$table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_token varchar(64) NOT NULL,
            integration varchar(32) NOT NULL,
            object_id varchar(191) NOT NULL,
            user_id bigint unsigned NOT NULL DEFAULT 0,
            expected_amount decimal(20,8) NOT NULL,
            currency varchar(3) NOT NULL,
            description text NULL,
            merchant_reference varchar(191) NULL,
            return_url text NULL,
            bunq_account_id bigint unsigned NOT NULL DEFAULT 0,
            bunq_request_id bigint unsigned NOT NULL DEFAULT 0,
            payment_url text NULL,
            status varchar(32) NOT NULL DEFAULT 'created',
            provider_status varchar(32) NULL,
            provider_payload_hash varchar(64) NULL,
            metadata longtext NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            verified_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_token (public_token),
            KEY object_lookup (integration, object_id),
            KEY status (status),
            KEY bunq_request_id (bunq_request_id)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'obp_db_version', self::DB_VERSION, false );
    }

    public static function maybe_upgrade() {
        if ( get_option( 'obp_db_version', '' ) !== self::DB_VERSION ) { self::install(); }
    }

    public static function create( array $data ) {
        global $wpdb;
        $now = current_time( 'mysql', true );
        $row = array(
            'public_token' => isset( $data['public_token'] ) ? (string) $data['public_token'] : bin2hex( random_bytes( 24 ) ),
            'integration' => sanitize_key( $data['integration'] ),
            'object_id' => (string) $data['object_id'],
            'user_id' => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
            'expected_amount' => number_format( (float) $data['expected_amount'], 8, '.', '' ),
            'currency' => strtoupper( (string) $data['currency'] ),
            'description' => isset( $data['description'] ) ? (string) $data['description'] : '',
            'merchant_reference' => isset( $data['merchant_reference'] ) ? (string) $data['merchant_reference'] : '',
            'return_url' => isset( $data['return_url'] ) ? esc_url_raw( $data['return_url'] ) : '',
            'bunq_account_id' => isset( $data['bunq_account_id'] ) ? (int) $data['bunq_account_id'] : 0,
            'bunq_request_id' => isset( $data['bunq_request_id'] ) ? (int) $data['bunq_request_id'] : 0,
            'payment_url' => isset( $data['payment_url'] ) ? esc_url_raw( $data['payment_url'] ) : '',
            'status' => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'created',
            'provider_status' => isset( $data['provider_status'] ) ? sanitize_key( $data['provider_status'] ) : '',
            'provider_payload_hash' => isset( $data['provider_payload_hash'] ) ? (string) $data['provider_payload_hash'] : '',
            'metadata' => wp_json_encode( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array() ),
            'last_error' => isset( $data['last_error'] ) ? (string) $data['last_error'] : '',
            'created_at' => $now,
            'updated_at' => $now,
            'verified_at' => null,
        );
        $ok = $wpdb->insert( self::table(), $row );
        if ( ! $ok ) { throw new RuntimeException( 'Could not create Open bunq payment record.' ); }
        return self::get( (int) $wpdb->insert_id );
    }

    public static function get( $id ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', (int) $id ), ARRAY_A ); }
    public static function by_token( $token ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE public_token=%s', (string) $token ), ARRAY_A ); }
    public static function latest_for_object( $integration, $object_id ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE integration=%s AND object_id=%s ORDER BY id DESC LIMIT 1', sanitize_key( $integration ), (string) $object_id ), ARRAY_A ); }
    public static function pending( $limit = 50 ) { global $wpdb; $limit=max(1,min(250,(int)$limit)); return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE status IN ('created','pending','processing') AND bunq_request_id>0 ORDER BY updated_at ASC LIMIT %d", $limit ), ARRAY_A ); }
    public static function recent( $limit = 25 ) { global $wpdb; $limit=max(1,min(250,(int)$limit)); return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ); }
    public static function counts() { global $wpdb; $rows=$wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY status', ARRAY_A ); $out=array(); foreach((array)$rows as $r){$out[(string)$r['status']]=(int)$r['n'];} return $out; }

    public static function update( $id, array $data ) {
        global $wpdb;
        $allowed = array( 'bunq_account_id','bunq_request_id','payment_url','status','provider_status','provider_payload_hash','metadata','last_error','verified_at','return_url' );
        $row = array();
        foreach ( $allowed as $key ) {
            if ( array_key_exists( $key, $data ) ) {
                $value = $data[ $key ];
                if ( $key === 'metadata' && is_array( $value ) ) { $value = wp_json_encode( $value ); }
                $row[ $key ] = $value;
            }
        }
        if(!$row){ return self::get($id); }
        $row['updated_at'] = current_time( 'mysql', true );
        $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
        return self::get( $id );
    }

    /** Compare-and-swap state transition. Returns array(record, changed). */
    public static function transition( $id, array $from_statuses, $to_status, array $extra = array() ) {
        global $wpdb;
        $from_statuses=array_values(array_filter(array_map('sanitize_key',$from_statuses)));
        if(!$from_statuses){ return array('record'=>self::get($id),'changed'=>false); }
        $set=array('status'=>sanitize_key($to_status),'updated_at'=>current_time('mysql',true));
        foreach(array('provider_status','provider_payload_hash','last_error','verified_at') as $k){ if(array_key_exists($k,$extra))$set[$k]=$extra[$k]; }
        $sets=array(); $args=array();
        foreach($set as $k=>$v){$sets[]="{$k}=%s";$args[]=$v;}
        $marks=implode(',',array_fill(0,count($from_statuses),'%s'));
        $args[]=(int)$id; foreach($from_statuses as $s)$args[]=$s;
        $sql='UPDATE '.self::table().' SET '.implode(',',$sets).' WHERE id=%d AND status IN ('.$marks.')';
        $changed=(int)$wpdb->query($wpdb->prepare($sql,$args))>0;
        return array('record'=>self::get($id),'changed'=>$changed);
    }

    public static function mark_verified( $id, $provider_status, $hash ) {
        return self::transition($id,array('created','pending','processing'),'verified',array(
            'provider_status'=>$provider_status,
            'provider_payload_hash'=>$hash,
            'last_error'=>'',
            'verified_at'=>current_time('mysql',true),
        ));
    }

    public static function mark_terminal( $id, $status, $provider_status, $hash = '' ) {
        return self::transition($id,array('created','pending','processing'),sanitize_key($status),array(
            'provider_status'=>$provider_status,
            'provider_payload_hash'=>$hash,
            'last_error'=>'',
        ));
    }

    public static function acquire_object_lock( $integration, $object_id, $ttl = 60 ) {
        $key='obp_lock_'.md5(sanitize_key($integration).'|'.(string)$object_id);
        if(add_option($key,(string)time(),'','no')) return $key;
        $created=(int)get_option($key,0);
        if($created>0 && time()-$created>(int)$ttl){ delete_option($key); if(add_option($key,(string)time(),'','no'))return $key; }
        return false;
    }
    public static function release_object_lock( $key ) { if(is_string($key)&&strpos($key,'obp_lock_')===0)delete_option($key); }

    public static function decode_metadata( array $record ) {
        $m = ! empty( $record['metadata'] ) ? json_decode( $record['metadata'], true ) : array();
        return is_array( $m ) ? $m : array();
    }
}
