<?php
/** In-memory WordPress API shim. Security smoke tests; not a real WP integration test. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__.'/');
define('HOUR_IN_SECONDS',3600);
$hooks=[]; $nonces=[]; $roles=[]; $caps=[]; $scannerResult=null; $salt='test-secret-do-not-deploy'; $metaUpdates=[]; $postUpdates=[]; $outbox=[]; $transients=[];
class MockWpdb {
    public $prefix='wp_';public $updated=[];public $deleted=[];public $inserted=[];public $transactions=[];public $fail_deletion=false;public $row_override=null;public $question_files=[];
    public function update($table,$data,$where,...$more){$this->updated[]=[$table,$data,$where];return 1;}
    public function delete($table,$where,...$more){$this->deleted[]=[$table,$where];return $this->fail_deletion && $table==='wp_bbs_applications' ? false : 1;}
    public function insert($table,$data,...$more){$this->inserted[]=[$table,$data];return 1;}
    public function prepare($query,...$args){return $query;}
    public function get_var($query){return 'applicant@example.test';}
    public function get_results($query){return str_contains($query,'wp_aj_answer_files') ? $this->question_files : [];}
    public function get_row($query){return $this->row_override??(object)['id'=>31,'job_id'=>123,'full_name'=>'Test Candidate','email'=>'applicant@example.test'];}
    public function query($sql){$this->transactions[]=$sql;return 1;}
}
$wpdb=new MockWpdb;
function plugin_basename($file){return 'achuthan-jobs/achuthan-jobs.php';}
function get_page_by_path($path){return null;}
function add_rewrite_rule($regex,$query,$after='bottom'){ $GLOBALS['testRewriteRules'][]=[$regex,$query,$after]; }
function register_post_type($type,$args){ $GLOBALS['testRegisteredType']=[$type,$args]; }
function is_page($slug){ return $slug==='careers'; }
function is_singular($type=''){ return false; }
function plugin_dir_path($file){return dirname($file).'/';}

function sanitize_html_class($s){return preg_replace('/[^a-zA-Z0-9_-]/','',(string)$s);}
function add_action($name,$cb,...$rest){global $hooks;$hooks[$name][]=$cb;}
function add_filter($name,$cb,...$rest){add_action($name,$cb);}
function add_shortcode($name,$cb){global $hooks;$hooks['shortcode_'.$name][]=$cb;}
function register_activation_hook($file,$cb){} function register_deactivation_hook($file,$cb){}
function wp_salt($scheme){global $salt;return $salt.'|'.$scheme;}
function apply_filters($name,$default,...$args){global $scannerResult;return $name==='achuthan_jobs_scan_pdf'?$scannerResult:$default;}
function sanitize_text_field($val){return strip_tags((string)$val);} function sanitize_textarea_field($val){return strip_tags((string)$val);} function wp_unslash($val){return $val;}
function wp_die($msg,$title='',$args=[]){throw new RuntimeException((string)$msg,isset($args['response'])?(int)$args['response']:500);}
function absint($val){return abs((int)$val);} function current_user_can($cap,...$args){global $caps;return $caps[$cap]??false;}
function check_admin_referer($nonce){global $nonces;if(empty($nonces[$nonce]))wp_die('Invalid nonce','',['response'=>403]);}
function get_current_user_id(){return 7;}
class WP_Error {public function __construct(public $code,public $message) {}} function is_wp_error($value){return $value instanceof WP_Error;}
function wp_safe_redirect($url,$status=302){throw new RuntimeException('REDIRECT '.$status.' '.$url,302);}
function admin_url($url=''){return 'http://localhost/wp-admin/'.$url;}
function add_query_arg($key,$value,$url){return $url.(strpos($url,'?')===false?'?':'&').$key.'='.$value;}
function get_post_type($id){return $id===123?'bbs_job':'post';}
function get_post_status($id){return 'publish';}
function get_post_meta($id,$key){global $questionMeta;return $key==='_aj_questions'?($questionMeta[$id]??''):'';}
function get_posts($args){return $GLOBALS['testJobs']??[];}
function get_privacy_policy_url(){return 'https://example.com/privacy';}
function wp_http_validate_url($url){return filter_var($url,FILTER_VALIDATE_URL)!==false;}
function wp_strip_all_tags($str){return strip_tags($str);}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_textarea($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function home_url($s=''){return 'http://localhost'.$s;}
function plugin_dir_url($f){return 'http://localhost/wp-content/plugins/achuthan-jobs/';}
function wp_enqueue_style(...$v){} function wp_enqueue_script(...$v){}
function wp_date($format){return date($format);}
function get_the_title($id){return 'Graphic Designer';}
function get_date_from_gmt($date,$format){return '30 Oct 2026';}
function selected($value,$expected,$echo=true){return $value===$expected?'selected="selected"':'';}
function get_permalink($id){return 'http://localhost/careers/graphic-designer/';}
function wp_nonce_field(...$args){$value='NONCE '.($args[0]??'');if(!isset($args[3])||$args[3]!==false)echo $value;return $value;}
function get_option($key,$default=null){return $default;}
function update_option(...$args){}
function get_transient($key){global $transients;return $transients[$key]??0;}
function set_transient($key,$val,$seconds){global $transients;$transients[$key]=$val;}
function wp_update_post($arr,$err=false){global $postUpdates;$postUpdates[]=$arr;return $arr['ID'];}
function wp_trash_post($id){global $postUpdates;$postUpdates[]=['ID'=>$id,'post_status'=>'trash'];return $id;}
function update_post_meta($id,$key,$val){global $metaUpdates;$metaUpdates[]=[$id,$key,$val];return true;}
function wp_mail($to,$subject,$body){global $outbox;$outbox[]=[$to,$subject,$body];return true;}
function is_email($v){return (bool)filter_var($v,FILTER_VALIDATE_EMAIL);}
function current_time($format,$gmt=false){return '2026-10-30 12:00:00';}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function get_the_ID(){return 123;}
require dirname(__DIR__).'/achuthan-jobs.php';
$pass=0;
function check($condition,$message){global $pass; if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);} $pass++; echo "PASS: $message\n";}
function private_call($method,...$args){$r=new ReflectionMethod(Achuthan_Jobs::class,$method);return $r->invoke(null,...$args);}
check(Achuthan_Jobs::VERSION==='0.11.0','Version registered');
check(isset($GLOBALS['hooks']['shortcode_achuthan_jobs']),'Shortcode registered');
check(isset($GLOBALS['hooks']['wp_ajax_nopriv_aj_live_jobs']),'Public jobs refresh endpoint registered');
check(isset($GLOBALS['hooks']['wp_ajax_nopriv_aj_extra_questions']),'Public additional job questions endpoint registered');
check(isset($GLOBALS['hooks']['wp_ajax_nopriv_aj_fresh_application_form']),'Uncached applicant form endpoint registered');
foreach($GLOBALS['hooks'] as $event=>$callbacks) foreach($callbacks as $cb) check(is_callable($cb),'Hook valid: '.$event);
check(Achuthan_Jobs::decrypt_resume('garbage')===false,'Random data is not decryptable');
$pdf="%PDF-1.4\n1 0 obj <</Type /Catalog>> endobj\n%%EOF";
$encrypted=Achuthan_Jobs::encrypt_resume($pdf);
check(is_string($encrypted) && !str_contains($encrypted,$pdf),'Private resume encrypted at rest');
check(Achuthan_Jobs::decrypt_resume($encrypted)===$pdf,'Resume decrypt roundtrip');
$tampered=$encrypted;$tampered[45]=chr(ord($tampered[45])^1);
check(Achuthan_Jobs::decrypt_resume($tampered)===false,'Authenticated encryption rejects tampering');
$GLOBALS['salt']='another-secret';
check(Achuthan_Jobs::decrypt_resume($encrypted)===false,'Different site keys cannot decrypt resumes');
$GLOBALS['salt']='test-secret-do-not-deploy';
check(private_call('valid_date','2027-02-28'),'Valid date accepted');
check(!private_call('valid_date','2027-02-30'),'Invalid calendar date rejected');
check(private_call('valid_date',''),'Optional date supported');
check(private_call('csv_safe','=HYPERLINK("x")')[0]==="'",'CSV formula injection neutralized');
check(private_call('csv_safe','   @SUM(1)')[0]==="'",'CSV formula injection with whitespace neutralized');
$_POST['job_ids']=['123','456','123'];
check(private_call('selected_ids','job_ids')===[123,456],'Bulk selection deduplicated');
$_POST['job_ids']=['123','12 OR 1=1'];
try{private_call('selected_ids','job_ids');check(false,'Reject SQLish ID');}catch(RuntimeException $e){check($e->getCode()===400,'Malicious bulk ID rejected');}
$_POST=[]; $GLOBALS['caps']=[];$_SERVER['REQUEST_METHOD']='POST';
try{Achuthan_Jobs::bulk_jobs();check(false,'Unauthorized bulk job action');}catch(RuntimeException $e){check($e->getCode()===403,'Unauthorized bulk jobs blocked');}
try{Achuthan_Jobs::candidate_email();check(false,'Unauthorized candidate email');}catch(RuntimeException $e){check($e->getCode()===403,'Unauthorized candidate email blocked');}
$GLOBALS['caps']=['manage_bbs_applicants'=>true]; $_SERVER['REQUEST_METHOD']='GET';
try{Achuthan_Jobs::download_resume();check(false,'GET resume download');}catch(RuntimeException $e){check($e->getCode()===405,'GET resume downloads blocked');}
$_SERVER['REQUEST_METHOD']='POST';
try{Achuthan_Jobs::download_resume();check(false,'CSRF resume download');}catch(RuntimeException $e){check($e->getCode()===403,'Resume download requires CSRF nonce');}
// Admin bulk job operations: permission + nonce + selected ID + intended metadata.
$GLOBALS['caps']=['edit_bbs_jobs'=>true,'edit_post'=>true,'publish_bbs_jobs'=>true];
$GLOBALS['nonces']['aj_jobs_bulk']=true;
$_POST=['bulk_action'=>'close','job_ids'=>['123']];
try{Achuthan_Jobs::bulk_jobs();}catch(RuntimeException $e){check($e->getCode()===302,'Job bulk close redirects');}
check(end($GLOBALS['metaUpdates'])===[123,'_bbs_status','closed'],'Job bulk close modifies only selected job');
$_POST=['bulk_action'=>'publish','job_ids'=>['123']];
try{Achuthan_Jobs::bulk_jobs();}catch(RuntimeException $e){check($e->getCode()===302,'Bulk publish redirects');}
check(end($GLOBALS['postUpdates'])['post_status']==='publish','Bulk publish uses WordPress post update');
$GLOBALS['caps']=['edit_bbs_jobs'=>true,'edit_post'=>true];
$_POST=['bulk_action'=>'publish','job_ids'=>['123']];
$before=count($GLOBALS['postUpdates']);
try{Achuthan_Jobs::bulk_jobs();}catch(RuntimeException $e){}
check(count($GLOBALS['postUpdates'])===$before,'Publish capability enforced per job');
// Candidate stage can be bulk-managed without accessing resume bytes.
$GLOBALS['caps']=['manage_bbs_applicants'=>true];
$GLOBALS['nonces']['aj_candidates_bulk']=true;
$_POST=['bulk_action'=>'stage_interview','candidate_ids'=>['31','32']];
try{Achuthan_Jobs::bulk_candidates();}catch(RuntimeException $e){check($e->getCode()===302,'Bulk candidate updates redirect');}
check(count($wpdb->updated)===2 && $wpdb->updated[0][1]['stage']==='interview','Both selected candidates moved to interview');
$_POST=['bulk_action'=>'stage_fake','candidate_ids'=>['31']];
try{Achuthan_Jobs::bulk_candidates();check(false,'Invalid stage permitted');}catch(RuntimeException $e){check($e->getCode()===400,'Invalid candidate stage rejected');}
// Restricted candidate correspondence, no public email endpoint.
$GLOBALS['nonces']['aj_candidate_email_31']=true;
$_POST=['id'=>'31','subject'=>'Interview request','body'=>'Please confirm your availability.'];
try{Achuthan_Jobs::candidate_email();}catch(RuntimeException $e){check($e->getCode()===302,'Candidate email redirects on success');}
check(count($GLOBALS['outbox'])===1 && $GLOBALS['outbox'][0][0]==='applicant@example.test','Candidate correspondence restricted to stored recipient');
$_POST=['id'=>'31','subject'=>"Injected\nBcc: attacker@example.test",'body'=>'test'];
try{Achuthan_Jobs::candidate_email();check(false,'Header injection sent');}catch(RuntimeException $e){check($e->getCode()===400,'Email header injection blocked');}
$GLOBALS['scannerResult']=null;
check(private_call('scan_pdf','/tmp/example.pdf')==='validated','No antivirus service is required');
$GLOBALS['scannerResult']=false;
check(private_call('scan_pdf','/tmp/example.pdf')==='rejected','Optional scanner rejection still blocks PDF');
$GLOBALS['scannerResult']=true;
check(private_call('scan_pdf','/tmp/example.pdf')==='clean','Optional scanner clearance recognised');
$html=Achuthan_Jobs::shortcode();
check(str_contains($html,'No current openings'),'Shortcode renders empty state');
check(str_contains($html,'data-bbs-ajax='),'Public careers shortcode contains AJAX endpoint');
check(str_contains($html,'data-bbs-live='),'Jobs list declares live refresh');

// Dashboard should be locked to HR, and be the default plugin page.
$GLOBALS['caps']=[];
try { Achuthan_Jobs::dashboard(); check(false,'Dashboard blocked without HR capability'); }
catch(RuntimeException $e) { check($e->getCode()===403,'Dashboard blocked without HR capability'); }
$GLOBALS['caps']=['manage_bbs_applicants'=>true];
$_GET=['view'=>'overview'];
ob_start();Achuthan_Jobs::dashboard();$html=ob_get_clean();
check(strpos($html,'Your hiring workspace')!==false,'HR overview is the plugin landing page');
check(strpos($html,'Recent applications')!==false,'HR dashboard displays recent applications');
check(strpos($html,'Upcoming interviews')!==false,'HR dashboard displays interviews');
$_GET=['view'=>'overview','compose'=>'31'];ob_start();Achuthan_Jobs::dashboard();$html=ob_get_clean();
check(str_contains($html,'Email applicant'),'Email applicant composer renders on HR dashboard');
check(str_contains($html,'aj_candidate_email_31'),'Dashboard composer uses candidate-specific nonce');
$_GET=['view'=>'pipeline'];ob_start();Achuthan_Jobs::dashboard();$html=ob_get_clean();
check(strpos($html,'Candidate pipeline')!==false,'Pipeline tab renders');
$_GET=['view'=>'interviews'];ob_start();Achuthan_Jobs::dashboard();$html=ob_get_clean();
check(strpos($html,'Interview schedule')!==false,'Interview tab renders');
// New v0.8.0 form builder and security smoke checks.
$normalized=Achuthan_Questions::normalize([
 ['id'=>'q_abcdef1234','label'=>'Preferred location?','type'=>'select','required'=>1,'options'=>"Bengaluru\nRemote\nRemote",'allow'=>'pdf'],
 ['id'=>'q_fedcba9876','label'=>'Upload sample','type'=>'file','required'=>1,'allow'=>'images'],
]);
check(count($normalized)===2,'Custom questions normalize valid job schema');
check($normalized[0]['options']===['Bengaluru','Remote'],'Custom answers deduplicate choice options');
check($normalized[1]['allow']==='images','Job upload format restriction preserved');
$questionMeta[123]=$normalized;
$_POST=['aj_answers'=>['q_abcdef1234'=>'Remote']];$_FILES=[];
$r=Achuthan_Questions::validate(123);
check(is_wp_error($r)&&$r->code==='missing_file','Missing required custom file rejected');
$questionMeta[123]=[$normalized[0]];
$_POST=['aj_answers'=>['q_abcdef1234'=>'Paris']];
$r=Achuthan_Questions::validate(123);
check(is_wp_error($r)&&$r->code==='invalid_option','Custom dropdown rejects spoofed choices');
$_POST=['aj_answers'=>['q_abcdef1234'=>'Bengaluru']];
$r=Achuthan_Questions::validate(123);
check(!is_wp_error($r)&&$r['answers'][0]['value']==='Bengaluru','Validated job-specific answer retained');
$render=Achuthan_Questions::render(123);
check(str_contains($render,'Additional questions for this role')&&str_contains($render,'Bengaluru'),'Public form displays choices from job');
$render=Achuthan_Questions::render(123,true);
check(str_contains($render,'aj_answers[123][q_abcdef1234]'),'Per-job questions are correctly namespaced');
$_POST=['aj_answers'=>[123=>['q_abcdef1234'=>'Bengaluru']]];
$r=Achuthan_Questions::validate(123,true);
check(!is_wp_error($r)&&$r['answers'][0]['value']==='Bengaluru','Nested per-job answers validate server-side');
check(Achuthan_Jobs::job_code(123)==='AJ-000123','Job ID auto-generated with stable WordPress post ID');
check(!Achuthan_Jobs::valid_pdf_bytes('%PDF-1.5' . str_repeat('a',100)),'PDF must contain EOF marker');
check(!Achuthan_Jobs::valid_pdf_bytes('%PDF-1.5' . str_repeat('a',100).' /JavaScript %%EOF'),'PDF with active JavaScript is rejected');
check(!Achuthan_Questions::valid_document_bytes(str_repeat('x',200),'image/png','test.png'),'Non-image bytes cannot pass document download validation');
$req=Achuthan_Questions::normalize([['id'=>'q_abc1239999','label'=>'Required number','type'=>'number','required'=>true]]);
$questionMeta[123]=$req; $_POST=['aj_answers'=>['q_abc1239999'=>'nope']];
check(is_wp_error(Achuthan_Questions::validate(123)),'Invalid numeric answer rejected');
$_POST=['aj_answers'=>['q_abc1239999'=>'2.5']];
check(!is_wp_error(Achuthan_Questions::validate(123)),'Valid numeric answer accepted');
// v0.9.0 multi-job and file-export guard rails.
$questionMeta[123]=[$normalized[0]];
$form=private_call('application_form',123);
check(str_contains($form,'name="aj_answers[123][q_abcdef1234]"'),'Application form retains per-role question keys');
check(str_contains($form,'name="resume"') && str_contains($form,'3 MB'),'Resume upload clearly limited to 3 MB');
check(str_contains($form,'name="bbs_consent"'),'Privacy consent remains required');
check(Achuthan_Questions::MAX_FILE_BYTES===3145728 && Achuthan_Jobs::MAX_RESUME===3145728,'Each file capped at 3 MiB');
$GLOBALS['caps']=[];$_SERVER['REQUEST_METHOD']='POST';
try{Achuthan_Jobs::download_bulk_files([31]);check(false,'Unauthorized bulk ZIP download');}catch(RuntimeException $e){check($e->getCode()===403,'Bulk ZIP exports restricted to HR');}
$GLOBALS['caps']=['manage_bbs_applicants'=>true];$_SERVER['REQUEST_METHOD']='GET';
try{Achuthan_Jobs::download_bulk_files([31]);check(false,'GET bulk ZIP permitted');}catch(RuntimeException $e){check($e->getCode()===405,'Bulk ZIP requires POST');}
$_SERVER['REQUEST_METHOD']='POST';
try{Achuthan_Jobs::download_bulk_files(range(1,21));check(false,'Bulk ZIP >20 accepted');}catch(RuntimeException $e){check($e->getCode()===400,'Bulk ZIP caps at 20 applications');}
check(isset($hooks['admin_post_aj_answer_file_download']),'Private attachment handler registered');
$GLOBALS['caps']=[];$_SERVER['REQUEST_METHOD']='POST';
try{Achuthan_Questions::download();check(false,'Unauthorized download permitted');}catch(RuntimeException $e){check($e->getCode()===403,'Custom file download requires HR permission');}
$GLOBALS['caps']=['manage_bbs_applicants'=>true];$_SERVER['REQUEST_METHOD']='GET';
try{Achuthan_Questions::download();check(false,'GET custom attachment permitted');}catch(RuntimeException $e){check($e->getCode()===405,'Custom file downloads require POST');}
check(is_readable(dirname(__DIR__).'/templates/single-bbs_job.php'),'Native job template bundled');
check(is_readable(dirname(__DIR__).'/assets/questions-admin.js'),'Question builder JavaScript bundled');

echo "\nALL $pass SMOKE ASSERTIONS PASSED. WP AND ELEMENTOR INTEGRATION NOT TESTED HERE.\n";

// v0.10: secure inline preview and atomic per-application deletion.
check(isset($GLOBALS['hooks']['admin_post_aj_preview_resume']), 'Authenticated resume preview route registered');
check(isset($GLOBALS['hooks']['admin_post_aj_answer_file_preview']), 'Authenticated question attachment preview route registered');
check(!isset($GLOBALS['hooks']['admin_post_nopriv_aj_preview_resume']), 'No public resume preview route registered');
check(!isset($GLOBALS['hooks']['admin_post_nopriv_aj_answer_file_preview']), 'No public attachment preview route registered');
$preview=Achuthan_Jobs::preview_control('aj_preview_resume',31,'aj_preview_resume_31','Candidate <script>.pdf');
check(str_contains($preview,'method="post"')&&str_contains($preview,'target="aj-private-document-frame"'), 'Preview uses scoped POST submission into frame');
check(str_contains($preview,'aj_preview_resume_31')&&str_contains($preview,'&lt;script&gt;'), 'Preview nonce attached and filenames escaped');
$GLOBALS['caps']=[];$_POST=['id'=>'31'];$_SERVER['REQUEST_METHOD']='POST';
try { Achuthan_Jobs::preview_resume();check(false,'Unauthorised preview permitted'); }
catch(RuntimeException $ex){check($ex->getCode()===403,'Non-HR user cannot preview resume');}
$GLOBALS['caps']=['manage_bbs_applicants'=>true];$GLOBALS['nonces']=[];
try { Achuthan_Jobs::preview_resume();check(false,'Missing preview nonce permitted'); }
catch(RuntimeException $ex){check($ex->getCode()===403,'Missing resume preview nonce blocked');}
$GLOBALS['nonces']=['aj_preview_resume_31'=>true];
$wpdb->row_override=(object)['id'=>31,'resume_filename'=>'blocked.pdf','resume_data'=>'bad','scan_status'=>'rejected'];
try { Achuthan_Jobs::preview_resume();check(false,'Rejected resume preview permitted'); }
catch(RuntimeException $ex){check($ex->getCode()===403,'Rejected resume is denied in preview');}
$wpdb->row_override=(object)['id'=>31,'resume_filename'=>'bad.pdf','resume_data'=>'bad','scan_status'=>'validated'];
try { Achuthan_Jobs::preview_resume();check(false,'Corrupt resume preview permitted'); }
catch(RuntimeException $ex){check($ex->getCode()===422,'Corrupt resume is denied in preview');}
$wpdb->row_override=null;
$GLOBALS['caps']=[];$_POST=['id'=>'5'];
try{Achuthan_Questions::preview();check(false,'Unauthorised custom attachment preview permitted');}
catch(RuntimeException $ex){check($ex->getCode()===403,'Non-HR user cannot preview custom attachment');}
$GLOBALS['caps']=['manage_bbs_applicants'=>true];$GLOBALS['nonces']=[];
try{Achuthan_Questions::preview();check(false,'Missing custom-file nonce permitted');}
catch(RuntimeException $ex){check($ex->getCode()===403,'Missing custom attachment nonce blocked');}
$wpdb->transactions=[];$wpdb->deleted=[];$wpdb->fail_deletion=false;
check(private_call('erase_application',31)===true,'Application deletion succeeds');
check($wpdb->transactions===['START TRANSACTION','COMMIT'],'Application deletion is transaction-wrapped');
check(count($wpdb->deleted)===2&&$wpdb->deleted[0][0]==='wp_aj_answer_files'&&$wpdb->deleted[1][0]==='wp_bbs_applications','Application deletion includes encrypted question files and resume record');
check($wpdb->deleted[0][1]===['application_id'=>31]&&$wpdb->deleted[1][1]===['id'=>31],'Deletion only targets one job application');
$wpdb->transactions=[];$wpdb->deleted=[];$wpdb->fail_deletion=true;
check(private_call('erase_application',31)===false,'Failed application deletion is reported');
check($wpdb->transactions===['START TRANSACTION','ROLLBACK'],'Failed deletion rolls back attachment changes');
$wpdb->fail_deletion=false;
$admin_css=file_get_contents(dirname(__DIR__).'/assets/admin.css');
check(str_contains($admin_css,'.aj-preview-modal')&&str_contains($admin_css,'Poppins'),'Private preview styling and font present');
$preview_js=file_get_contents(dirname(__DIR__).'/assets/admin-preview.js');
check(str_contains($preview_js,"event.target.closest('.aj-preview-form')")&&str_contains($preview_js,"event.key === 'Escape'"),'Modal JS reacts to previews and Escape');
echo "Final checks passed: ".$pass." assertions\n";

$wpdb->row_override=(object)[
 'id'=>31,'job_id'=>123,'full_name'=>'Test Candidate','email'=>'applicant@example.test','phone'=>'9876543210','portfolio'=>'',
 'message'=>'Interested in this job','question_answers'=>json_encode([['id'=>'q_abcd123456','type'=>'file','label'=>'Portfolio file','value'=>'sample.png']]),
 'candidate_rating'=>0,'resume_filename'=>'resume.pdf','scan_status'=>'validated','stage'=>'new','notes'=>'',
 'interview_at'=>null,'interview_time'=>'','interview_mode'=>'','offer_salary'=>'','joining_date'=>null,
 'consent_at'=>'2026-10-30 12:00:00','created_at'=>'2026-10-30 12:00:00'
];
$wpdb->question_files=[(object)['id'=>53,'question_id'=>'q_abcd123456','filename'=>'sample.png','scan_status'=>'validated']];
ob_start();private_call('applicant_detail',31);$html=ob_get_clean();
check(str_contains($html,'Preview file')&&str_contains($html,'aj_preview_resume'),'Candidate detail renders private resume preview');
check(str_contains($html,'aj_answer_file_preview')&&str_contains($html,'sample.png'),'Candidate detail renders private question file preview');
check(str_contains($html,'id="aj-document-preview"')&&str_contains($html,'sandbox="allow-scripts"'),'Candidate detail renders isolated iframe modal');
check(str_contains($html,'bbs_delete_applicant')&&str_contains($html,'Permanently delete application'),'Candidate detail includes permanent deletion action');
check(str_contains($html,'Download PDF')&&str_contains($html,'aj_answer_file_download'),'Original per-file downloads remain available');
$wpdb->row_override=null;$wpdb->question_files=[];
echo "UI-render checks passed: ".$pass." assertions\n";


// v0.11 regressions for URL, Careers ownership, and real multi-choice controls.
Achuthan_Jobs::register();
check(($GLOBALS['testRegisteredType'][1]['rewrite']['slug']??null)==='careers','Job permalinks use /careers/{slug}/');
check(str_contains($GLOBALS['testRewriteRules'][0][0]??'', 'careers/job/'),'Legacy /careers/job/{slug}/ rewrite remains registered');
check(is_readable(dirname(__DIR__).'/templates/archive-careers.php'),'Live plugin-owned /careers/ page template bundled');
$careerTpl=Achuthan_Questions::job_template('theme-old-template.php');
check(str_ends_with($careerTpl,'templates/archive-careers.php'),'Careers page uses dynamic plugin template by default');
$questionMeta[123] = Achuthan_Questions::normalize([
 ['id'=>'q_123abc456','label'=>'Select your tools','type'=>'checkbox','required'=>1,'options'=>"Figma\nPhotoshop\nIllustrator"],
 ['id'=>'q_789abc123','label'=>'Work arrangement','type'=>'radio','required'=>1,'options'=>"Onsite\nHybrid"],
]);
$choiceHtml=Achuthan_Questions::render(123,true);
check(str_contains($choiceHtml,'type="checkbox"') && str_contains($choiceHtml,'data-aj-required-choice="1"'),'Multi-choice question renders actual accessible checkboxes');
check(str_contains($choiceHtml,'name="aj_answers[123][q_123abc456][]"'),'Multi-select checkboxes submit arrays for the correct job');
check(str_contains($choiceHtml,'type="radio"') && str_contains($choiceHtml,'required'),'Single-choice questions render required radio controls');
$_POST=['aj_answers'=>[123=>['q_123abc456'=>['Figma','Illustrator'],'q_789abc123'=>'Hybrid']]];
$answer=Achuthan_Questions::validate(123,true);
check(!is_wp_error($answer) && $answer['answers'][0]['value']===['Figma','Illustrator'],'Server accepts multiple valid selected choices');
$_POST=['aj_answers'=>[123=>['q_123abc456'=>['Invalid'],'q_789abc123'=>'Hybrid']]];
check(is_wp_error(Achuthan_Questions::validate(123,true)),'Server rejects choice not defined by HR');
$_POST=['aj_answers'=>[123=>['q_123abc456'=>[],'q_789abc123'=>'Hybrid']]];
check(is_wp_error(Achuthan_Questions::validate(123,true)),'Server blocks missing required multi-choice response');
$css=file_get_contents(dirname(__DIR__).'/assets/front.css');
check(str_contains($css,'label.aj-option input[type="checkbox"]') && str_contains($css,'appearance:auto!important'),'Frisk/Elementor CSS cannot hide choice inputs');
$js=file_get_contents(dirname(__DIR__).'/assets/front.js');
check(str_contains($js,'validateRequiredChoices(form, box)'),'Client validates required checkbox groups');
check(str_contains($js,"url.searchParams.set('action', 'aj_live_jobs')"),'Careers listing refreshes from uncached endpoint');
echo "ALL $pass CHECKS INCLUDING V0.11 REGRESSIONS PASSED.\n";

$GLOBALS['testJobs']=[(object)['ID'=>123,'post_title'=>'Graphic Designer','post_content'=>'Creative brand work']];
$liveHtml=Achuthan_Jobs::shortcode();
check(str_contains($liveHtml,'Graphic Designer') && str_contains($liveHtml,'/careers/graphic-designer/'),'Published WordPress jobs become live career cards with canonical URL');
$GLOBALS['testJobs']=[];
echo "ALL $pass MOCK ASSERTIONS PASSED. REAL WP INTEGRATION REMAINS UNTESTED.\n";
