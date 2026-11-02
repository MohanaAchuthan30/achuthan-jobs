<?php
/** Site-specific sender settings, job-specific acknowledgement messages and HR attachment mail. */
if (!defined('ABSPATH')) exit;
final class Achuthan_Mailer {
    const MAX_FILE_BYTES = 3145728;
    const MAX_FILES = 3;

    public static function initialize() {
        add_action('add_meta_boxes_bbs_job',array(__CLASS__,'metabox'));
        add_action('save_post_bbs_job',array(__CLASS__,'save_job'),25);
    }
    public static function sender_email() {
        $email=sanitize_email((string)get_option('aj_mail_sender_email',''));
        return is_email($email)?$email:'';
    }
    public static function sender_name() {
        $name=trim((string)get_option('aj_mail_sender_name',''));
        return $name!==''?$name:Achuthan_Jobs::employer_name();
    }
    public static function settings_fields() {
        return '<fieldset class="aj-mail-settings"><legend><strong>Candidate email sender</strong></legend>' .
            '<p class="description">Choose a verified email address on your own domain and configure WordPress SMTP with SPF, DKIM and DMARC. Changing From does not configure an email mailbox or guarantee delivery.</p>'.
            '<label><strong>Sender display name</strong><input type="text" name="aj_mail_sender_name" maxlength="120" value="'.esc_attr(self::sender_name()).'"></label>'.
            '<label><strong>From email address</strong><input type="email" name="aj_mail_sender_email" maxlength="190" value="'.esc_attr(self::sender_email()).'" placeholder="careers@example.com"></label>'.
            '<p class="description">Leave From email blank to use the sender configured by your SMTP plugin.</p></fieldset>';
    }
    public static function save_settings() {
        $name=Achuthan_Jobs::employer_name();
        if(isset($_POST['aj_mail_sender_name']) && is_scalar($_POST['aj_mail_sender_name']))$name=trim(sanitize_text_field(wp_unslash($_POST['aj_mail_sender_name'])));
        $email='';
        if(isset($_POST['aj_mail_sender_email']) && is_scalar($_POST['aj_mail_sender_email']))$email=sanitize_email(wp_unslash($_POST['aj_mail_sender_email']));
        if(strlen($name)>120 || ($email!=='' && !is_email($email)))wp_die('Invalid sender email settings.','',array('response'=>400));
        update_option('aj_mail_sender_name',$name);
        update_option('aj_mail_sender_email',$email);
    }
    public static function default_subject() { return 'We received your application — {job_title}'; }
    public static function default_body() {
        return "Hi {candidate_name},\n\nThank you for applying to {job_title} ({job_id}) at {company_name}. Your application number is {application_id}.\n\nOur hiring team will review your application and contact you if there is a match.\n\n{company_name}";
    }
    public static function metabox() {
        add_meta_box('aj-job-ack-email','Candidate acknowledgement email for this job',array(__CLASS__,'metabox_html'),'bbs_job','normal','default');
    }
    public static function metabox_html($post) {
        wp_nonce_field('aj_job_ack_'.$post->ID,'aj_job_ack_nonce');
        $subject=(string)get_post_meta($post->ID,'_aj_ack_subject',true);
        $body=(string)get_post_meta($post->ID,'_aj_ack_body',true);
        if($subject==='')$subject=self::default_subject();
        if($body==='')$body=self::default_body();
        echo '<p>Send a different confirmation message for each job, including when one person applies to several positions.</p>';
        echo '<p><label for="aj_ack_subject"><strong>Email subject</strong></label><br><input type="text" id="aj_ack_subject" name="aj_ack_subject" maxlength="150" style="width:100%" value="'.esc_attr($subject).'"></p>';
        echo '<p><label for="aj_ack_body"><strong>Email message (plain text)</strong></label><br><textarea id="aj_ack_body" name="aj_ack_body" maxlength="5000" rows="9" style="width:100%">'.esc_textarea($body).'</textarea></p>';
        echo '<p class="description">Placeholders: <code>{candidate_name}</code>, <code>{job_title}</code>, <code>{job_id}</code>, <code>{application_id}</code>, <code>{company_name}</code>. Empty fields use safe defaults. Sender address is set in Achuthan Jobs → Settings.</p>';
    }
    public static function save_job($post_id) {
        if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;
        if(!isset($_POST['aj_job_ack_nonce'])||!is_scalar($_POST['aj_job_ack_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aj_job_ack_nonce'])),'aj_job_ack_'.$post_id))return;
        if(!current_user_can('edit_post',$post_id))return;
        $sub=isset($_POST['aj_ack_subject'])&&is_scalar($_POST['aj_ack_subject'])?sanitize_text_field(wp_unslash($_POST['aj_ack_subject'])):'';
        $body=isset($_POST['aj_ack_body'])&&is_scalar($_POST['aj_ack_body'])?sanitize_textarea_field(wp_unslash($_POST['aj_ack_body'])):'';
        if(strlen($sub)>150 || preg_match('/[\r\n]/',$sub) || strlen($body)>5000)return;
        update_post_meta($post_id,'_aj_ack_subject',$sub);
        update_post_meta($post_id,'_aj_ack_body',$body);
    }
    public static function render_template($template,$variables) {
        $replacements=array();
        foreach($variables as $key=>$value)$replacements['{'.$key.'}']=(string)$value;
        return strtr((string)$template,$replacements);
    }
    private static function mail_headers() {
        $email=self::sender_email();
        if(!$email)return array();
        $name=preg_replace('/[\r\n<>]/','',self::sender_name());
        return array('From: '.$name.' <'.$email.'>');
    }
    public static function acknowledge($email,$name,$job_id,$application_id) {
        $sub=(string)get_post_meta($job_id,'_aj_ack_subject',true);
        $body=(string)get_post_meta($job_id,'_aj_ack_body',true);
        if($sub==='')$sub=self::default_subject();
        if($body==='')$body=self::default_body();
        $tokens=array('candidate_name'=>$name,'job_title'=>get_the_title($job_id),'job_id'=>Achuthan_Jobs::job_code($job_id),'application_id'=>$application_id,'company_name'=>Achuthan_Jobs::employer_name());
        return wp_mail($email,sanitize_text_field(self::render_template($sub,$tokens)),self::render_template($body,$tokens),self::mail_headers());
    }
    public static function attachment_field() {
        return '<label><strong>Attach files (optional)</strong><input type="file" name="aj_mail_attachments[]" multiple accept=".pdf,.docx,.jpg,.jpeg,.png,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png"></label>'.
            '<p class="description">PDF, DOCX, JPG or PNG only; at most 3 files, 3 MB each. Files are sent only in this email and are not saved as applicant documents.</p>';
    }
    public static function validate_attachments($uploads) {
        if(!$uploads || !isset($uploads['name']))return array();
        if(!is_array($uploads['name']) || !is_array($uploads['tmp_name']??null) || !is_array($uploads['size']??null) || !is_array($uploads['error']??null))
            return new WP_Error('aj_bad_upload','Invalid attachment form.');
        $names=$uploads['name'];
        if(count($names)>self::MAX_FILES)return new WP_Error('aj_too_many_files','Maximum 3 attachments allowed.');
        $valid=[];
        foreach($names as $i=>$name){
            $err=(int)($uploads['error'][$i]??UPLOAD_ERR_NO_FILE);
            if($err===UPLOAD_ERR_NO_FILE)continue;
            if($err!==UPLOAD_ERR_OK)return new WP_Error('aj_upload_error','Attachment upload failed.');
            $size=(int)($uploads['size'][$i]??0);$tmp=$uploads['tmp_name'][$i]??'';
            if($size<16||$size>self::MAX_FILE_BYTES)return new WP_Error('aj_file_size','Each attachment must be 3 MB or less.');
            if(!is_string($tmp)||!is_uploaded_file($tmp)||!is_string($name)||strlen($name)>180)return new WP_Error('aj_file_invalid','Invalid uploaded attachment.');
            $safe=sanitize_file_name($name);$ext=strtolower(pathinfo($safe,PATHINFO_EXTENSION));
            if(!in_array($ext,['pdf','docx','jpg','jpeg','png'],true))return new WP_Error('aj_file_type','Only PDF, DOCX, JPG and PNG are allowed.');
            if(!class_exists('finfo'))return new WP_Error('aj_file_type','File inspection unavailable on this server.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if($ext==='pdf'){
                $h=file_get_contents($tmp,false,null,0,8);
                if($mime!=='application/pdf'||substr($h,0,5)!=='%PDF-')return new WP_Error('aj_invalid_pdf','Invalid PDF attachment.');
            }elseif(in_array($ext,['jpg','jpeg','png'],true)){
                $info=@getimagesize($tmp);
                $expected=$ext==='png'?'image/png':'image/jpeg';
                if($mime!==$expected||!is_array($info)||($info['mime']??'')!==$expected)return new WP_Error('aj_invalid_image','Invalid image attachment.');
            }else{
                if(!in_array($mime,['application/zip','application/octet-stream','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],true)||!class_exists('ZipArchive'))return new WP_Error('aj_invalid_docx','DOCX inspection is unavailable or invalid.');
                $zip=new ZipArchive();
                if($zip->open($tmp)!==true)return new WP_Error('aj_invalid_docx','Invalid DOCX attachment.');
                $ok=$zip->locateName('word/document.xml')!==false;
                $zip->close();if(!$ok)return new WP_Error('aj_invalid_docx','Invalid DOCX attachment.');
            }
            $valid[]=array('path'=>$tmp,'filename'=>$safe,'ext'=>$ext);
        }
        return $valid;
    }
    public static function send_hr_message($to,$subject,$body,$uploads=null) {
        $files=self::validate_attachments($uploads);
        if(is_wp_error($files))return $files;
        if(!$files)return wp_mail($to,$subject,$body,self::mail_headers());
        $temp=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'aj-mail-'.bin2hex(random_bytes(12));
        if(!@mkdir($temp,0700))return new WP_Error('aj_temp_failed','Secure mail attachment directory unavailable.');
        $paths=[];
        try {
            foreach($files as $i=>$file){
                $dest=$temp.DIRECTORY_SEPARATOR.$i.'-'.basename($file['filename']);
                if(!@copy($file['path'],$dest))return new WP_Error('aj_temp_failed','Could not prepare the email attachment.');
                @chmod($dest,0600);
                $paths[]=$dest;
            }
            return wp_mail($to,$subject,$body,self::mail_headers(),$paths);
        }finally{
            foreach($paths as $path)@unlink($path);
            @rmdir($temp);
        }
    }
}
