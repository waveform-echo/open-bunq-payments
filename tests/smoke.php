<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('AUTH_KEY','test-auth-key');
define('SECURE_AUTH_KEY','test-secure-key');
define('LOGGED_IN_KEY','test-login-key');
define('NONCE_KEY','test-nonce-key');
function wp_salt($scheme='auth'){return 'test-salt-'.$scheme;}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function apply_filters($tag,$value){return $value;}
function add_query_arg($args,$url){$sep=strpos($url,'?')===false?'?':'&';return $url.$sep.http_build_query($args);}
function esc_url_raw($url){return filter_var($url,FILTER_SANITIZE_URL);}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}

require_once dirname(__DIR__).'/includes/class-obp-crypto.php';
require_once dirname(__DIR__).'/includes/class-obp-bunq-api.php';
require_once dirname(__DIR__).'/includes/class-obp-provider.php';

function ok($cond,$msg){if(!$cond){fwrite(STDERR,"FAIL: $msg\n");exit(1);}echo "ok - $msg\n";}

$secret='super-secret-'.bin2hex(random_bytes(4));
$enc=OBP_Crypto::encrypt($secret);
ok($enc!==$secret && $enc!=='','crypto encrypts');
ok(OBP_Crypto::decrypt($enc)===$secret,'crypto roundtrip');

$r=array('status'=>'ACCEPTED','amount_responded'=>array('value'=>'12.50','currency'=>'eur'));
ok(OBP_Bunq_API::request_status($r)==='ACCEPTED','status parser');
$a=OBP_Bunq_API::request_amount($r);
ok($a['value']==='12.50'&&$a['currency']==='EUR','amount parser');

$tree=array('Response'=>array(array('MonetaryAccountBank'=>array('id'=>1)),array('MonetaryAccountSavings'=>array('id'=>2))));
$objs=OBP_Bunq_API::find_all_objects_prefix($tree,'MonetaryAccount');
ok(count($objs)===2,'generic monetary-account parser');

ok(OBP_Provider::is_trusted_payment_url('https://bunq.me/example/5.00'),'bunq.me trusted');
ok(OBP_Provider::is_trusted_payment_url('https://oauth.bunq.com/auth'),'bunq subdomain trusted');
ok(!OBP_Provider::is_trusted_payment_url('https://evil.example/pay'),'foreign provider URL rejected');

$auth=OBP_Bunq_API::authorization_url('sandbox','client','https://shop.example/callback','state123');
ok(strpos($auth,'oauth.sandbox.bunq.com')!==false && strpos($auth,'state=state123')!==false,'sandbox OAuth URL');

echo "SMOKE GREEN\n";
