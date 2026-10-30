<?php
/** Per-job screening questions and access-controlled application attachments. */
if (!defined('ABSPATH')) exit;
final class Achuthan_Questions {
    const MAX_QUESTIONS=15;
    const MAX_FILES=4;
    const MAX_FILE_BYTES=3145728;
    const MAX_TOTAL_BYTES=12582912;
    private static function table() { global $wpdb; return $wpdb->prefix.'aj_answer_files'; }
    public static function initialize() {
        add_action('add_meta_boxes_bbs_job', array(__CLASS__,'metabox'));
        add_action('save_post_bbs_job', array(__CLASS__,'save'), 20);
        add_action('admin_post_aj_answer_file_download', array(__CLASS__,'download'));
        add_action('admin_post_aj_answer_file_preview', array(__CLASS__,'preview'));
        add_action('admin_post_aj_answer_file_rescan', array(__CLASS__,'rescan'));
        add_filter('template_include', array(__CLASS__,'job_template'), 9999);
    }
    public static function install() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=self::table(); $collate=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            question_id varchar(40) NOT NULL,
            filename varchar(180) NOT NULL,
            mime varchar(80) NOT NULL,
            encrypted_data longblob NOT NULL,
            scan_status varchar(20) NOT NULL DEFAULT 'quarantine',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY application_id (application_id),
            KEY question_id (question_id)
        ) $collate;");
    }
    public static function job_template($template) {
        $base=plugin_dir_path(dirname(__FILE__)).'templates/';
        // A native WP page with slug careers exists on this site. Render the
        // live plugin listing instead of stale Elementor/static page content.
        // Site owners can opt out in Achuthan Jobs > Settings.
        if (is_page('careers') && get_option('aj_own_careers_page','1')==='1') {
            $custom=$base.'archive-careers.php';
            if (is_readable($custom)) return $custom;
        }
        if (is_singular('bbs_job')) {
            $custom=$base.'single-bbs_job.php';
            if (is_readable($custom)) return $custom;
        }
        return $template;
    }
    public static function allowed_types() {return array('text'=>'Short answer','textarea'=>'Long answer','number'=>'Number','date'=>'Date','select'=>'Dropdown','radio'=>'Single choice','checkbox'=>'Multiple choice','file'=>'File upload');}
    public static function questions($id) {
        $q=get_post_meta($id,'_aj_questions',true);
        return is_array($q)?$q:array();
    }
    public static function normalize($raw) {
        if (!is_array($raw) || count($raw)>self::MAX_QUESTIONS) return array();
        $out=array();$ids=array();$files=0;
        foreach($raw as $r) {
            if(!is_array($r))continue;
            $label=sanitize_text_field(wp_unslash($r['label']??''));
            if($label==='')continue;
            $label=function_exists('mb_substr')?mb_substr($label,0,160):substr($label,0,160);
            $type=sanitize_key((string)($r['type']??'text'));
            if(!isset(self::allowed_types()[$type]))$type='text';
            $id=sanitize_key((string)($r['id']??''));
            if(!preg_match('/^q_[a-z0-9]{6,24}$/D',$id) || isset($ids[$id])) {
                $id='q_'.substr(bin2hex(random_bytes(9)),0,18);
            }
            $ids[$id]=true;
            if($type==='file' && ++$files>self::MAX_FILES) continue;
            $opts=array();
            if(in_array($type,array('select','radio','checkbox'),true)) {
                $text=is_scalar($r['options']??null)?(string)wp_unslash($r['options']):'';
                foreach(preg_split('/\r?\n/',$text) as $option){
                    $option=sanitize_text_field(trim($option));
                    $option=function_exists('mb_substr')?mb_substr($option,0,100):substr($option,0,100);
                    if($option!==''&&!in_array($option,$opts,true))$opts[]=$option;
                    if(count($opts)>=20)break;
                }
                if(count($opts)===0) continue;
            }
            $allow=sanitize_key((string)($r['allow']??'pdf'));
            if(!in_array($allow,array('pdf','images','both'),true))$allow='pdf';
            $out[]=array('id'=>$id,'label'=>$label,'type'=>$type,'required'=>!empty($r['required']),'options'=>$opts,'allow'=>$allow);
        }
        return $out;
    }
    public static function metabox() {
        add_meta_box('aj_job_questions','Job application questions',array(__CLASS__,'metabox_html'),'bbs_job','normal','default');
    }
    public static function metabox_html($post) {
        wp_nonce_field('aj_save_questions_'.$post->ID,'aj_questions_nonce');
        echo '<p><strong>Build a custom application for this job.</strong> Add required questions, dropdown answer options, and up to four additional PDF/image uploads (3 MB each). These questions appear only on this vacancy.</p>';
        echo '<div id="aj-question-builder">';
        $q=self::questions($post->ID);
        foreach($q as $index=>$item)self::row($index,$item);
        echo '</div><p><button type="button" class="button button-secondary" id="aj-add-question">+ Add question</button> <span class="description">Maximum 15 questions. Choice options: one per line.</span></p>';
        echo '<template id="aj-question-template">';self::row('__INDEX__',array());echo '</template>';
    }
    private static function row($i,$q) {
        $base='aj_questions['.$i.']';$type=$q['type']??'text';
        echo '<div class="aj-question-row" style="padding:14px;margin:10px 0;border:1px solid #dde5d4;border-radius:8px;background:#fff">';
        echo '<input type="hidden" name="'.esc_attr($base).'[id]" value="'.esc_attr($q['id']??'').'" class="aj-question-id">';
        echo '<div class="aj-builder-grid"><label><strong>Question</strong><input type="text" maxlength="160" name="'.esc_attr($base).'[label]" value="'.esc_attr($q['label']??'').'" placeholder="e.g. Are you willing to relocate?" style="width:100%"></label>';
        echo '<label><strong>Answer type</strong><select class="aj-question-type" name="'.esc_attr($base).'[type]" style="width:100%">';
        foreach(self::allowed_types() as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($type,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label></div>';
        echo '<div class="aj-choice-section"'.(!in_array($type,array('select','radio','checkbox'),true)?' hidden':'').'><label><strong>Answer choices — one per line</strong><textarea rows="3" style="width:100%" name="'.esc_attr($base).'[options]">'.esc_textarea(implode("\n",(array)($q['options']??array()))).'</textarea></label></div>';
        echo '<div class="aj-file-section"'.($type!=='file'?' hidden':'').'><label>Accepted files <select name="'.esc_attr($base).'[allow]">';
        foreach(array('pdf'=>'PDF documents','images'=>'JPG/PNG images','both'=>'PDF and JPG/PNG') as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($q['allow']??'pdf',$k,false).'>'.esc_html($v).'</option>';
        echo '</select></label></div>';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:9px"><label><input type="checkbox" value="1" name="'.esc_attr($base).'[required]" '.checked(!empty($q['required']),true,false).'> Required answer</label><button type="button" class="button button-link-delete aj-remove-question">Remove question</button></div></div>';
    }
    public static function save($post_id) {
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)return;
        if(!isset($_POST['aj_questions_nonce']) || !is_scalar($_POST['aj_questions_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aj_questions_nonce'])),'aj_save_questions_'.$post_id))return;
        if(!current_user_can('edit_post',$post_id))return;
        $raw=isset($_POST['aj_questions'])&&is_array($_POST['aj_questions'])?$_POST['aj_questions']:array();
        update_post_meta($post_id,'_aj_questions',self::normalize($raw));
    }
    public static function render($job_id,$nested=false) {
        $qs=self::questions($job_id);
        if(!$qs)return '';
        $out='<fieldset class="aj-custom-questions"><legend>Additional questions for this role</legend><p>Questions marked * are required.</p>';
        foreach($qs as $q) {
            $id=$q['id']; $type=$q['type']; $required=!empty($q['required']);
            $req=$required?' required':''; $star=$required?' *':'';
            $name=$nested?'aj_answers['.$job_id.']['.$id.']':'aj_answers['.$id.']';
            $label=esc_html($q['label']).$star;
            $dom_id=($nested?'job-'.$job_id.'-':'').$id;
            $control_id='aj-'.esc_attr($dom_id);
            // A labelled fieldset is more accessible than a label pointing at
            // a group <div>. It also avoids Frisk/Elementor label overrides.
            if ($type==='radio' || $type==='checkbox') {
                $out.='<fieldset class="aj-question aj-choice-set"'.($required?' data-aj-required-choice="1"':'').'><legend class="aj-question-label">'.$label.'</legend>';
                $out.='<div class="aj-options" role="group" aria-label="'.esc_attr($q['label']).'">';
                foreach((array)$q['options'] as $index=>$opt) {
                    $input_name=$type==='checkbox'?$name.'[]':$name;
                    $option_id=$control_id.'-'.(int)$index;
                    $out.='<label class="aj-option" for="'.esc_attr($option_id).'"><input id="'.esc_attr($option_id).'" type="'.esc_attr($type).'" name="'.esc_attr($input_name).'" value="'.esc_attr($opt).'"'.($type==='radio' && $required?' required':'').'><span>'.esc_html($opt).'</span></label>';
                }
                $out.='</div><p class="aj-choice-error" role="alert" hidden>Select at least one option.</p></fieldset>';
                continue;
            }
            $out.='<div class="aj-question"><label class="aj-question-label" for="'.$control_id.'">'.$label.'</label>';
            if($type==='textarea')$out.='<textarea id="'.$control_id.'" name="'.esc_attr($name).'" maxlength="3000" rows="4"'.$req.'></textarea>';
            elseif($type==='number')$out.='<input id="'.$control_id.'" name="'.esc_attr($name).'" type="number" step="any"'.$req.'>';
            elseif($type==='date')$out.='<input id="'.$control_id.'" name="'.esc_attr($name).'" type="date"'.$req.'>';
            elseif($type==='select') {
                $out.='<select id="'.$control_id.'" name="'.esc_attr($name).'"'.$req.'><option value="">Select an answer</option>';
                foreach((array)$q['options'] as $opt)$out.='<option value="'.esc_attr($opt).'">'.esc_html($opt).'</option>';
                $out.='</select>';
            } elseif($type==='file') {
                $allow=$q['allow']??'pdf';
                $accept=$allow==='images'?'.jpg,.jpeg,.png,image/jpeg,image/png':($allow==='both'?'.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png':'.pdf,application/pdf');
                $out.='<input type="file" id="'.$control_id.'" name="aj_files['.($nested?esc_attr($job_id).'][':'').esc_attr($id).']" accept="'.esc_attr($accept).'"'.$req.'><small>Maximum 3 MB. '.esc_html($allow==='pdf'?'PDF only':($allow==='images'?'JPG/PNG only':'PDF, JPG or PNG')).'. Files are privately reviewed by HR.</small>';
            } else $out.='<input id="'.$control_id.'" type="text" name="'.esc_attr($name).'" maxlength="500"'.$req.'>';
            $out.='</div>';
        }
        return $out.'</fieldset>';
    }
    private static function valid_uploaded($file,$allow) {
        if(!is_array($file) || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))return false;
        $bytes=(int)($file['size']??0);
        if($bytes<100 || $bytes>self::MAX_FILE_BYTES || !is_string($file['name']??null) || strlen($file['name'])>180)return false;
        $name=sanitize_file_name($file['name']);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,array('pdf','png','jpg','jpeg'),true))return false;
        if($allow==='pdf'&&$ext!=='pdf')return false;
        if($allow==='images'&&$ext==='pdf')return false;
        if(!class_exists('finfo'))return false;
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $expected=array('pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg');
        if(($expected[$ext]??null)!==$mime)return false;
        $content=file_get_contents($file['tmp_name']);if(!is_string($content)||strlen($content)!==$bytes)return false;
        if($ext==='pdf') {
            if(substr($content,0,5)!=='%PDF-' || strpos(substr($content,-1024),'%%EOF')===false || preg_match('~/(?:JavaScript|JS|OpenAction|Launch|EmbeddedFile|RichMedia|XFA)\b~i',$content))return false;
        }else{
            $info=@getimagesize($file['tmp_name']);
            if(!$info || $info[0]<1||$info[1]<1||$info[0]>12000||$info[1]>12000||!in_array($info[2],array(IMAGETYPE_JPEG,IMAGETYPE_PNG),true))return false;
        }
        return array('name'=>$name,'mime'=>$mime,'data'=>$content,'tmp'=>$file['tmp_name']);
    }
    public static function validate($job_id,$nested=false) {
        $qs=self::questions($job_id);$submitted=isset($_POST['aj_answers']) && is_array($_POST['aj_answers'])?wp_unslash($_POST['aj_answers']):array();
        $files=isset($_FILES['aj_files'])&&is_array($_FILES['aj_files'])?$_FILES['aj_files']:array();
        if($nested){$submitted=isset($submitted[$job_id])&&is_array($submitted[$job_id])?$submitted[$job_id]:array();
            foreach(array('name','type','tmp_name','error','size') as $key)$files[$key]=$files[$key][$job_id]??array();}
        $answers=array();$attachments=array();$total=0;
        foreach($qs as $q) {
            $id=$q['id'];$kind=$q['type'];$required=!empty($q['required']);$val=$submitted[$id]??'';
            if($kind==='file'){
                $file=isset($files['name'][$id])?array('name'=>$files['name'][$id],'type'=>$files['type'][$id]??'','tmp_name'=>$files['tmp_name'][$id]??'','error'=>$files['error'][$id]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$id]??0):null;
                $has=$file && $file['error']!==UPLOAD_ERR_NO_FILE;
                if($required && !$has)return new WP_Error('missing_file','Please upload a required document.');
                if($has){$valid=self::valid_uploaded($file,$q['allow']??'pdf');if(!$valid)return new WP_Error('invalid_file','One custom question file is invalid or exceeds 3 MB.');
                    $total+=strlen($valid['data']);if($total>self::MAX_TOTAL_BYTES)return new WP_Error('size','Too many files.');
                    $valid['scan']='validated';$valid['question_id']=$id;$attachments[]=$valid;
                }
                $answers[]=array('id'=>$id,'label'=>$q['label'],'type'=>$kind,'value'=>$has?$valid['name']:'');continue;
            }
            if(is_array($val)){
                if($kind!=='checkbox' || count($val)>20)return new WP_Error('invalid_answer','Invalid answers to screening questions.');
                $val=array_map('sanitize_text_field',$val);
                $val=array_values(array_unique($val));
                if(count(array_diff($val,$q['options'])))return new WP_Error('invalid_option','Invalid selected answer.');
            }else{
                if(!is_scalar($val))return new WP_Error('invalid_answer','Invalid answer format.');
                $val=sanitize_text_field((string)$val);
                if($kind==='textarea')$val=sanitize_textarea_field((string)$val);
                $max=$kind==='textarea'?3000:500;
                if((function_exists('mb_strlen')?mb_strlen($val):strlen($val))>$max)return new WP_Error('long_answer','Answer exceeds its character limit.');
                if(in_array($kind,array('select','radio'),true)&&$val!==''&&!in_array($val,$q['options'],true))return new WP_Error('invalid_option','Invalid selected answer.');
                if($kind==='number'&&$val!==''&&(!is_numeric($val)||!is_finite((float)$val)))return new WP_Error('invalid_number','Invalid numeric answer.');
                if($kind==='date'&&$val!==''){
                    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$val);
                    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$val)||$d===false||$d->format('Y-m-d')!==$val)return new WP_Error('invalid_date','Invalid date answer.');
                }
            }
            if($required&&($val===''||$val===array()))return new WP_Error('required_answer','Please answer all required questions.');
            $answers[]=array('id'=>$id,'label'=>$q['label'],'type'=>$kind,'value'=>$val);
        }
        return array('answers'=>$answers,'files'=>$attachments);
    }
    public static function save_files($application_id,$attachments) {
        global $wpdb;
        foreach($attachments as $file){
            $encrypted=Achuthan_Jobs::encrypt_resume($file['data']);
            if(!is_string($encrypted))return false;
            $ok=$wpdb->insert(self::table(),array('application_id'=>$application_id,'question_id'=>$file['question_id'],'filename'=>$file['name'],'mime'=>$file['mime'],'encrypted_data'=>$encrypted,'scan_status'=>$file['scan'],'created_at'=>current_time('mysql',true)));
            if($ok===false)return false;
        }
        return true;
    }
    public static function render_admin($application_id,$answers_json) {
        $answers=json_decode((string)$answers_json,true);
        if(!is_array($answers)||!$answers)return;
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT id,question_id,filename,scan_status FROM '.self::table().' WHERE application_id=%d',$application_id));
        $files=array();foreach((array)$rows as $r)$files[$r->question_id]=$r;
        echo '<div class="bbs-panel"><h2>Screening answers & documents</h2><dl>';
        foreach($answers as $answer){
            if(!is_array($answer))continue;$id=$answer['id']??'';
            $value=$answer['value']??'';
            echo '<dt>'.esc_html($answer['label']??'Question').'</dt><dd>';
            if(($answer['type']??'')==='file') {
                if(isset($files[$id])){
                    $f=$files[$id];echo '<strong>'.esc_html($f->filename).'</strong> <small>('.esc_html($f->scan_status).')</small> ';
                    if($f->scan_status!=='rejected'){
                        echo Achuthan_Jobs::preview_control('aj_answer_file_preview',$f->id,'aj_answer_file_preview_'.$f->id,$f->filename);
                        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="aj-private-download"><input type="hidden" name="action" value="aj_answer_file_download"><input type="hidden" name="id" value="'.esc_attr($f->id).'">';
                        wp_nonce_field('aj_answer_file_'.$f->id);echo '<button class="button" type="submit">Download</button></form>';
                    }
                }else echo 'No file submitted';
            }else echo nl2br(esc_html(is_array($value)?implode(', ',$value):$value));
            echo '</dd>';
        }
        echo '</dl></div>';
    }
    private static function fetch_file() {
        global $wpdb;$id=isset($_POST['id'])?absint(wp_unslash($_POST['id'])):0;
        if(!$id)wp_die('Missing file ID.','',array('response'=>400));
        $record=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d',$id));
        if(!$record)wp_die('File not found.','',array('response'=>404));
        return $record;
    }
    private static function require_download() {
        if(!current_user_can('manage_bbs_applicants'))wp_die('Forbidden.','',array('response'=>403));
        if(strtoupper($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Method not allowed.','',array('response'=>405));
    }
    public static function valid_document_bytes($data,$mime,$name) {
        if(!is_string($data)||strlen($data)<100||strlen($data)>self::MAX_FILE_BYTES)return false;
        $ext=strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));
        if($mime==='application/pdf' && $ext==='pdf')return Achuthan_Jobs::valid_pdf_bytes($data);
        if(($mime==='image/jpeg' && in_array($ext,array('jpg','jpeg'),true))||($mime==='image/png' && $ext==='png')){
            if(!function_exists('getimagesizefromstring'))return false;
            $info=@getimagesizefromstring($data);
            return $info && $info[0]>0 && $info[1]>0 && $info[0]<=12000 && $info[1]<=12000
                && (($mime==='image/jpeg' && $info[2]===IMAGETYPE_JPEG)||($mime==='image/png' && $info[2]===IMAGETYPE_PNG));
        }
        return false;
    }
    /** Only called by authorised, nonce-protected bulk download route. */
    public static function downloadable_files($application_id){
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT id,question_id,filename,mime,encrypted_data,scan_status FROM '.self::table().' WHERE application_id=%d',absint($application_id)));
        $files=array();foreach((array)$rows as $r){
            if($r->scan_status==='rejected')continue;
            $bytes=Achuthan_Jobs::decrypt_resume($r->encrypted_data);
            if(!self::valid_document_bytes($bytes,$r->mime,$r->filename))continue;
            $files[]=array('id'=>$r->id,'question_id'=>$r->question_id,'filename'=>$r->filename,'data'=>$bytes);
        }
        return $files;
    }
    public static function preview() {
        self::require_download();
        $r=self::fetch_file();
        check_admin_referer('aj_answer_file_preview_'.$r->id);
        if ($r->scan_status==='rejected') wp_die('Rejected file cannot be previewed.','',array('response'=>403));
        $data=Achuthan_Jobs::decrypt_resume($r->encrypted_data);
        if (!self::valid_document_bytes($data,$r->mime,$r->filename)) wp_die('Invalid or unavailable document.','',array('response'=>422));
        Achuthan_Jobs::stream_preview($data,$r->mime,$r->filename,$r->application_id,'answer_file_previewed');
    }
    public static function download() {
        self::require_download();$r=self::fetch_file();check_admin_referer('aj_answer_file_'.$r->id);
        if($r->scan_status==='rejected')wp_die('Rejected file.','',array('response'=>403));
        $data=Achuthan_Jobs::decrypt_resume($r->encrypted_data);
        if(!self::valid_document_bytes($data,$r->mime,$r->filename))wp_die('Cannot open attachment.','',array('response'=>500));
        Achuthan_Jobs::log_event('answer_file_downloaded',(int)$r->application_id);
        while(ob_get_level())ob_end_clean();
        nocache_headers();header('X-Content-Type-Options: nosniff');header('Content-Security-Policy: sandbox');
        header('Content-Type: application/octet-stream');
        $filename=preg_replace('/[^a-zA-Z0-9._-]/','_',basename($r->filename));
        header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.strlen($data));echo $data;exit;
    }
    public static function rescan() {
        self::require_download();$r=self::fetch_file();check_admin_referer('aj_answer_rescan_'.$r->id);
        $data=Achuthan_Jobs::decrypt_resume($r->encrypted_data);
        if(!is_string($data))wp_die('Cannot decrypt attachment.','',array('response'=>500));
        $tmp=tempnam(sys_get_temp_dir(),'aj-scan-');if(!$tmp)wp_die('Cannot create scan temp file.','',array('response'=>500));
        $status='quarantine';
        try{
            if(file_put_contents($tmp,$data,LOCK_EX)!==false){@chmod($tmp,0600);
                $scan=$r->mime==='application/pdf'?apply_filters('achuthan_jobs_scan_pdf',null,$tmp):apply_filters('achuthan_jobs_scan_attachment',null,$tmp,$r->mime);
                $status=$scan===true?'clean':($scan===false?'rejected':'quarantine');
            }
        }finally{@unlink($tmp);}
        global $wpdb;$wpdb->update(self::table(),array('scan_status'=>$status),array('id'=>$r->id));
        Achuthan_Jobs::log_event('answer_file_rescanned',(int)$r->application_id,$status);
        wp_safe_redirect(admin_url('admin.php?page=bbs-applicants&view='.$r->application_id.'&rescanned=1'),303);exit;
    }
    public static function purge_for($app_id) {
        global $wpdb; return $wpdb->delete(self::table(),array('application_id'=>absint($app_id)),array('%d'));
    }
    public static function prune_orphans() {
        global $wpdb;
        $files=self::table();$apps=$wpdb->prefix.'bbs_applications';
        $wpdb->query("DELETE f FROM $files f LEFT JOIN $apps a ON a.id=f.application_id WHERE a.id IS NULL");
    }
}
