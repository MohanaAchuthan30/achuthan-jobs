<?php
/** Standalone isolated tests. NOT a running WordPress integration test. */
define('ABSPATH', '/fake-wp/');
define('MINUTE_IN_SECONDS',60);
define('HOUR_IN_SECONDS',3600);
class WP_Error { public $code; public $message; public function __construct($code,$message){$this->code=$code;$this->message=$message;} }
$GLOBALS['aj_test_filters']=[];
$GLOBALS['aj_test_transients']=[];
$GLOBALS['aj_test_options']=['aj_github_auto_update'=>'1'];
$GLOBALS['aj_test_api_response']=null;
$GLOBALS['aj_test_payload']='';
function plugin_basename($p){return 'achuthan-jobs/'.basename($p);}
function add_filter($hook,$cb,$pri,$argc){$GLOBALS['aj_test_filters'][$hook]=$cb;}
function add_action($hook,$cb,$pri=10,$argc=1){$GLOBALS['aj_test_filters'][$hook]=$cb;}
function get_site_transient($name){return $GLOBALS['aj_test_transients'][$name]??false;}
function set_site_transient($name,$value,$seconds){$GLOBALS['aj_test_transients'][$name]=$value;}
function get_option($name,$fallback=null){return $GLOBALS['aj_test_options'][$name]??$fallback;}
function wp_safe_remote_get($url,$args){return $GLOBALS['aj_test_api_response'];}
function wp_remote_retrieve_response_code($r){return $r['code']??0;}
function wp_remote_retrieve_body($r){return $r['body']??'';}
function is_wp_error($r){return $r instanceof WP_Error;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}
function wp_nonce_url($u,$nonce){return $u."&_wpnonce=fake";}
function self_admin_url($u){return "https://example.test/wp-admin/".$u;}
function current_user_can($cap){return $GLOBALS["aj_test_can_update"]??false;}
function wp_doing_ajax(){return false;}
function download_url($url,$timeout){$path=tempnam(sys_get_temp_dir(),'aj_dl_');file_put_contents($path,$GLOBALS['aj_test_payload']);return $path;}
function verify($ok,$message){static $count=0;$count++;if(!$ok){fwrite(STDERR,"FAIL ($count): $message\n");exit(1);} echo "PASS ($count): $message\n";}
require_once dirname(__DIR__).'/includes/class-achuthan-github-updater.php';
Achuthan_Github_Updater::boot('/fake-wp/wp-content/plugins/achuthan-jobs/achuthan-jobs.php','0.14.0');
verify(isset($GLOBALS['aj_test_filters']['update_plugins_github.com']),'Registers correct WordPress Update URI host filter');
verify(isset($GLOBALS['aj_test_filters']['auto_update_plugin']),'Registers WP automatic-update hook');
verify(isset($GLOBALS['aj_test_filters']['upgrader_pre_download']),'Registers download validation hook');
$base='https://github.com/MohanaAchuthan30/achuthan-jobs';
$payload='mock plugin zip bytes - local test only';
$sha=hash('sha256',$payload);
function release_example($tag='v0.15.0',$digest=''){
    global $base,$sha;
    if ($digest==='')$digest='sha256:'.$sha;
    return ['tag_name'=>$tag,'draft'=>false,'prerelease'=>false,'body'=>'Test release', 'assets'=>[[
       'name'=>'achuthan-jobs.zip','state'=>'uploaded','size'=>10000,'digest'=>$digest,
       'browser_download_url'=>$base.'/releases/download/'.$tag.'/achuthan-jobs.zip',
    ]]];
}
$r=release_example();$info=Achuthan_Github_Updater::parse_release($r);
verify(is_array($info)&&$info['version']==='0.15.0','Accepts valid tagged release with SHA-256');
verify($info['url']===$base.'/releases/download/v0.15.0/achuthan-jobs.zip','Keeps official package URL');
$r2=$r;$r2['prerelease']=true;
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects prereleases');
$r2=$r;$r2['draft']=true;
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects drafts');
$r2=$r;$r2['tag_name']='test/latest';
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects unversioned release');
$r2=$r;$r2['assets'][0]['name']='source.zip';
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects GitHub source archives');
$r2=$r;$r2['assets'][0]['digest']=null;
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects missing SHA-256');
$r2=$r;$r2['assets'][0]['browser_download_url']='https://badhost.example/achuthan-jobs.zip';
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects foreign download location');
$r2=$r;$r2['assets'][0]['size']=30000000;
verify(Achuthan_Github_Updater::parse_release($r2)===false,'Rejects oversized package');
$GLOBALS['aj_test_api_response']=['code'=>200,'body'=>json_encode($r)];
$cb=$GLOBALS['aj_test_filters']['update_plugins_github.com'];
$p='achuthan-jobs/achuthan-jobs.php';
$data=['UpdateURI'=>$base];
$offer=$cb(false,$data,$p,[]);
verify(is_array($offer)&&$offer['version']==='0.15.0','New release offered to WordPress');
verify(($offer['autoupdate']??false)===true,'Autoupdate enabled by site default');
$GLOBALS['aj_test_can_update']=true;
ob_start();Achuthan_Github_Updater::required_update_notice();$notice=ob_get_clean();
verify(str_contains($notice,'update required') && str_contains($notice,'Update Achuthan Jobs now'),'Persistent mandatory update notice shown for administrator');
$GLOBALS['aj_test_can_update']=false;
ob_start();Achuthan_Github_Updater::required_update_notice();$notice=ob_get_clean();
verify($notice==='','Update notice hidden from users without update capability');
$GLOBALS['aj_test_can_update']=true;

verify($cb(false,$data,'other-plugin/plugin.php',[])===false,'Does not override unrelated plugin');
$af=$GLOBALS['aj_test_filters']['auto_update_plugin'];
verify($af(null,(object)['plugin'=>$p,'id'=>$base,'package'=>$info['url']])===true,'Allows Achuthan background updates');
verify($af(null,(object)['plugin'=>'other-plugin/other.php'])===null,'Leaves other plugin updates untouched');
$GLOBALS['aj_test_options']['aj_github_auto_update']='0';
verify($af(true,(object)['plugin'=>$p,'id'=>$base,'package'=>$info['url']])===false,'Site can disable own auto-updates');
$GLOBALS['aj_test_options']['aj_github_auto_update']='1';
$GLOBALS['aj_test_payload']=$payload;
$vf=$GLOBALS['aj_test_filters']['upgrader_pre_download'];
$fn=$vf(false,$info['url'],null,['type'=>'plugin','action'=>'update','plugin'=>$p]);
verify(is_string($fn)&&file_exists($fn),'Verified package downloaded');
if(is_string($fn))@unlink($fn);
$GLOBALS['aj_test_payload']='tampered bytes';
$failed=$vf(false,$info['url'],null,['type'=>'plugin','action'=>'update','plugin'=>$p]);
verify($failed instanceof WP_Error && $failed->code==='aj_update_checksum_mismatch','Checksum mismatch blocks installation');
verify($vf(false,'https://plugins.example.com/test.zip',null,[])===false,'Does not interfere with other package downloads');
$GLOBALS['aj_test_transients']=['aj_github_public_release_v1'=>'unavailable'];
verify($cb(false,$data,$p,[])===false,'No update offered if API inaccessible');
verify(true,'All isolated GitHub updater assertions passed');
