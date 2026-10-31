<?php
/**
 * Plugin Name: Achuthan Jobs
 * Description: Multi-site WordPress recruitment dashboard with jobs, applicant management, and transparent HR eligibility checks.
 * Version: 0.13.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: Proprietary (internal use only)
 * Author: Achuthan
 * Text Domain: achuthan-jobs
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Preserve the legacy class and data migration guard from older careers builds.
// Avoid crashing WordPress if someone accidentally activates both packages.
if ( class_exists( 'BBS_Careers', false ) ) {
    add_action( 'admin_notices', static function () {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p><strong>Achuthan Jobs:</strong> Deactivate the previous Careers plugin. Only one recruitment plugin may run at a time.</p></div>';
        }
    } );
    return;
}

final class Achuthan_Jobs {
    const VERSION = '0.13.0';
    const MAX_CSV_BYTES = 1048576;
    const MAX_CSV_ROWS = 100;
    // Legacy internal identifiers are intentionally retained for upgrade safety.
    const CPT = 'bbs_job';
    const CAP = 'manage_bbs_applicants';
    const MAX_RESUME = 3145728; // 3 MB, stored encrypted in the private database.
    const ENC_PREFIX = 'AJENC1';

    private static function encryption_ready() {
        return extension_loaded('openssl') && function_exists('openssl_encrypt') && function_exists('random_bytes');
    }
    private static function key() {
        return hash('sha256', wp_salt('auth').'|'.wp_salt('secure_auth').'|achuthan-jobs-resumes-v1', true);
    }
    public static function encrypt_resume($pdf) {
        if (!self::encryption_ready()) return false;
        try { $iv=random_bytes(12); } catch (Throwable $e) { return false; }
        $tag='';
        $encrypted=openssl_encrypt($pdf,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        return $encrypted === false || strlen($tag)!==16 ? false : self::ENC_PREFIX.$iv.$tag.$encrypted;
    }
    public static function decrypt_resume($encrypted) {
        if (!is_string($encrypted)) return false;
        // Legacy installs stored plain BLOBs; only reachable through authenticated,
        // POST/nonce-protected actions, and never downloadable until scanner-cleared.
        if (substr($encrypted,0,5)==='%PDF-') return $encrypted;
        if (substr($encrypted,0,6)!==self::ENC_PREFIX || strlen($encrypted)<35 || !self::encryption_ready()) return false;
        $iv=substr($encrypted,6,12); $tag=substr($encrypted,18,16);
        return openssl_decrypt(substr($encrypted,34),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
    }
    /** Antivirus is not a requirement in v0.9. Validate types and store encrypted.
     * This does not establish that uploaded documents are malware-free. */
    private static function scan_pdf($path) {
        $result=apply_filters('achuthan_jobs_scan_pdf',null,$path);
        return $result === true ? 'clean' : ($result === false ? 'rejected' : 'validated');
    }
    private static function valid_date($s) {
        if ($s==='') return true;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$s)) return false;
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }
    private static function rate_limited($subject,$max,$seconds) {
        // Best effort only: production websites also need host/WAF rate limiting.
        $key='aj_r_'.hash_hmac('sha256',$subject,wp_salt('nonce'));
        $num=(int)get_transient($key);
        if ($num >= $max) return true;
        set_transient($key,$num+1,$seconds);
        return false;
    }
    public static function log_event($action,$application_id,$detail='') { self::audit($action,$application_id,$detail); }
    private static function audit($action,$application_id,$detail='') {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'bbs_application_audit',array(
            'application_id'=>absint($application_id), 'actor_id'=>get_current_user_id(),
            'event_name'=>sanitize_key($action), 'detail'=>sanitize_text_field($detail),
            'created_at'=>current_time('mysql',true),
        ),array('%d','%d','%s','%s','%s'));
    }

    public static function stages() {
        return array(
            'new' => 'New', 'screening' => 'Screening', 'shortlisted' => 'Shortlisted',
            'interview' => 'Interview', 'offer' => 'Offer', 'hired' => 'Hired', 'rejected' => 'Rejected',
        );
    }
    private static function table() { global $wpdb; return $wpdb->prefix . 'bbs_applications'; }
    public static function employer_name() {
        $name=trim((string)get_option('aj_employer_name',''));
        return $name!=='' ? $name : (string)get_bloginfo('name');
    }
    private static function country() {
        $country=strtoupper((string)get_option('aj_employer_country',''));
        return preg_match('/^[A-Z]{2}$/D',$country)?$country:'';
    }
    private static function data( $key, $default = '' ) {
        return isset( $_POST[$key] ) && is_scalar( $_POST[$key] ) ? sanitize_text_field( wp_unslash( $_POST[$key] ) ) : $default;
    }
    public static function register() {
        register_post_type( self::CPT, array(
            'labels' => array('name'=>'Jobs', 'singular_name'=>'Job', 'add_new_item'=>'Add New Job', 'edit_item'=>'Edit Job', 'menu_name'=>'Jobs'),
            'public' => true, 'show_in_rest' => true, 'show_in_menu' => 'bbs-recruitment',
            'supports' => array('title','editor','revisions'), 'has_archive' => false,
            'rewrite' => array('slug'=>'careers', 'with_front'=>false),
            'capability_type' => array('bbs_job','bbs_jobs'), 'map_meta_cap' => true,
            'exclude_from_search' => false,
        ) );
        // Preserve links published by v0.10 and earlier. Redirect them to
        // /careers/{job-slug}/ on the next request (never to a portfolio page).
        add_rewrite_rule('^careers/job/([^/]+)/?$', 'index.php?bbs_job=$matches[1]', 'top');
    }
    public static function activate() {
        self::register();
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            full_name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            phone varchar(50) NOT NULL DEFAULT '',
            portfolio varchar(500) NOT NULL DEFAULT '',
            message longtext NOT NULL,
            resume_filename varchar(255) NOT NULL,
            resume_mime varchar(100) NOT NULL,
            resume_data longblob NOT NULL,
            scan_status varchar(20) NOT NULL DEFAULT 'quarantine',
            stage varchar(30) NOT NULL DEFAULT 'new',
            notes longtext NOT NULL,
            question_answers longtext NOT NULL,
            candidate_rating tinyint(3) unsigned NOT NULL DEFAULT 0,
            candidate_experience decimal(4,1) DEFAULT NULL,
            eligibility_status varchar(20) NOT NULL DEFAULT 'unassessed',
            eligibility_detail longtext NOT NULL,
            interview_at date DEFAULT NULL,
            interview_time varchar(5) NOT NULL DEFAULT '',
            interview_mode varchar(30) NOT NULL DEFAULT '',
            offer_salary varchar(100) NOT NULL DEFAULT '',
            joining_date date DEFAULT NULL,
            consent_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_id (job_id),
            KEY stage (stage),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset;");
        $audit=$wpdb->prefix.'bbs_application_audit';
        dbDelta("CREATE TABLE $audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            application_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_name varchar(50) NOT NULL,
            detail varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY application_id (application_id),
            KEY created_at (created_at)
        ) $charset;");
        Achuthan_Questions::install();
        update_option('achuthan_jobs_schema_version',self::VERSION,false);
        $caps = array('edit_bbs_jobs','edit_others_bbs_jobs','edit_published_bbs_jobs',
            'edit_private_bbs_jobs','publish_bbs_jobs','read_private_bbs_jobs','delete_bbs_jobs',
            'delete_others_bbs_jobs','delete_published_bbs_jobs','delete_private_bbs_jobs',self::CAP);
        foreach ( array('administrator','bbs_recruiter') as $role_name ) {
            $role = get_role($role_name);
            if (!$role && $role_name === 'bbs_recruiter') {
                $role = add_role('bbs_recruiter', 'Achuthan Recruiter', array('read'=>true));
            }
            if ($role) { foreach ($caps as $cap) { $role->add_cap($cap); } }
        }
        // Update the display label only. Keep bbs_recruiter as a legacy role ID
        // so existing recruiter memberships and capabilities remain intact.
        $roles = wp_roles();
        if ( $roles && isset($roles->roles['bbs_recruiter']) &&
             ($roles->roles['bbs_recruiter']['name'] ?? '') !== 'Achuthan Recruiter' ) {
            $roles->roles['bbs_recruiter']['name'] = 'Achuthan Recruiter';
            $roles->role_names['bbs_recruiter'] = 'Achuthan Recruiter';
            update_option($roles->role_key, $roles->roles);
        }
        // By default the existing /careers/ WordPress page is rendered by the
        // plugin. Elementor content is not used for this single public URL.
        // Turn this off in Settings to use a shortcode inside Elementor instead.
        if ( false === get_option('aj_own_careers_page', false) ) add_option('aj_own_careers_page', '1', '', false);
        if ( false === get_option('bbs_retention_days', false) ) add_option('bbs_retention_days', 180);
        if ( false === get_option('bbs_hr_email', false) ) add_option('bbs_hr_email', get_option('admin_email'));
        if ( false === get_option('aj_employer_name', false) ) add_option('aj_employer_name', get_bloginfo('name'));
        if ( false === get_option('aj_employer_country', false) ) add_option('aj_employer_country', '');
        if ( ! wp_next_scheduled('bbs_daily_cleanup') ) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'bbs_daily_cleanup');
        flush_rewrite_rules();
    }
    public static function deactivate() {
        wp_clear_scheduled_hook('bbs_daily_cleanup');
        flush_rewrite_rules();
    }
    public static function init() {
        Achuthan_Questions::initialize();
        add_action('init', array(__CLASS__,'register'));
        add_action('admin_init',array(__CLASS__,'maybe_upgrade'));
        add_action('template_redirect',array(__CLASS__,'protect_application_pages'),0);
        add_action('template_redirect',array(__CLASS__,'redirect_legacy_job_url'),1);
        add_action('add_meta_boxes', array(__CLASS__,'metabox'));
        add_action('save_post_' . self::CPT, array(__CLASS__,'save_meta'));
        add_filter('the_content', array(__CLASS__,'job_content'), 20);
        add_filter('document_title_parts', array(__CLASS__,'document_title'));
        add_shortcode('bbs_careers', array(__CLASS__,'shortcode'));
        add_shortcode('achuthan_jobs', array(__CLASS__,'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__,'frontend_assets'));
        add_action('admin_menu', array(__CLASS__,'menus'));
        add_action('admin_enqueue_scripts', array(__CLASS__,'admin_assets'));
        add_action('wp_ajax_nopriv_aj_live_jobs', array(__CLASS__,'live_jobs'));
        add_action('wp_ajax_aj_live_jobs', array(__CLASS__,'live_jobs'));
        add_action('wp_ajax_nopriv_aj_extra_questions', array(__CLASS__,'extra_questions'));
        add_action('wp_ajax_aj_extra_questions', array(__CLASS__,'extra_questions'));
        add_action('wp_ajax_nopriv_aj_fresh_application_form', array(__CLASS__,'fresh_application_form'));
        add_action('wp_ajax_aj_fresh_application_form', array(__CLASS__,'fresh_application_form'));
        add_action('admin_post_nopriv_bbs_apply', array(__CLASS__,'apply'));
        add_action('admin_post_bbs_apply', array(__CLASS__,'apply'));
        add_action('admin_post_bbs_update_applicant', array(__CLASS__,'update_applicant'));
        add_action('admin_post_aj_recheck_eligibility',array(__CLASS__,'recheck_eligibility'));
        add_action('admin_post_bbs_delete_applicant', array(__CLASS__,'delete_applicant'));
        add_action('admin_post_bbs_download_resume', array(__CLASS__,'download_resume'));
        add_action('admin_post_aj_preview_resume', array(__CLASS__,'preview_resume'));
        add_action('admin_post_bbs_save_settings', array(__CLASS__,'save_settings'));
        add_action('admin_post_aj_jobs_bulk', array(__CLASS__,'bulk_jobs'));
        add_action('admin_post_aj_jobs_import', array(__CLASS__,'import_jobs'));
        add_action('admin_post_aj_jobs_template', array(__CLASS__,'download_template'));
        add_action('admin_post_aj_candidates_bulk', array(__CLASS__,'bulk_candidates'));
        add_action('admin_post_aj_candidate_email', array(__CLASS__,'candidate_email'));
        add_action('admin_post_aj_candidates_export', array(__CLASS__,'export_candidates'));
        add_action('bbs_daily_cleanup', array(__CLASS__,'cleanup'));
        add_action('wp_head', array(__CLASS__,'job_schema'), 20);
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__,'privacy_eraser_register'));
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__,'privacy_export_register'));
        add_action('admin_notices', array(__CLASS__,'admin_notice'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__,'plugin_links'));
    }
    public static function maybe_upgrade() {
        if (get_option('achuthan_jobs_schema_version')!==self::VERSION && current_user_can('manage_options')) {
            self::activate();
        }
    }
    public static function protect_application_pages() {
        // Avoid stale cached nonces/application pages on individual vacancies.
        if (is_singular(self::CPT) || is_page('careers')) {
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
            nocache_headers();
        }
    }
    /** 301 redirect for previously shared /careers/job/{slug}/ links. */
    public static function redirect_legacy_job_url() {
        if (!is_singular(self::CPT)) return;
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!preg_match('~(?:^|/)careers/job/[^/]+/?$~D', $path)) return;
        $canonical = get_permalink(get_queried_object_id());
        if ($canonical) { wp_safe_redirect($canonical, 301); exit; }
    }
    public static function metabox() {
        add_meta_box('bbs_job_settings','Job details & publishing', array(__CLASS__,'metabox_html'),self::CPT,'normal','high');
    }
    public static function metabox_html($post) {
        wp_nonce_field('bbs_save_job_' . $post->ID,'bbs_job_nonce');
        $fields = array('department'=>'Department','location'=>'Location','type'=>'Employment type','experience'=>'Experience','salary'=>'Salary (optional)','deadline'=>'Applications close (optional)');
        echo '<p>Publish the WordPress job to make it visible. Set <strong>Closed</strong> to immediately hide it from listings and disable applications.</p>';
        echo '<p><label for="aj_job_code"><strong>Job ID</strong></label><br><input type="text" name="aj_job_code" id="aj_job_code" maxlength="30" style="width:100%;max-width:320px" pattern="[A-Za-z0-9-]{3,30}" value="'.esc_attr(self::job_code($post->ID)).'"><br><small>Unique Job ID, e.g. AJ-000123 or AJ-MKT-2026. 3–30 letters, numbers or hyphens.</small></p><div class="bbs-meta-grid">';
        foreach ($fields as $key => $label) {
            $val = get_post_meta($post->ID, '_bbs_'.$key, true);
            echo '<p><label for="bbs_'.$key.'"><strong>'.esc_html($label).'</strong></label><br>';
            if ($key === 'type') {
                echo '<select name="bbs_type" id="bbs_type">';
                foreach (array('Full-time','Part-time','Contract','Internship','Temporary') as $type) {
                    echo '<option '.selected($val,$type,false).'>'.esc_html($type).'</option>';
                }
                echo '</select>';
            } else {
                echo '<input style="width:100%" type="'.($key==='deadline'?'date':'text').'" id="bbs_'.esc_attr($key).'" name="bbs_'.esc_attr($key).'" value="'.esc_attr($val).'" placeholder="'.esc_attr($key==='location'?'City / Remote / Hybrid':($key==='department'?'Design':'')).'">';
            }
            echo '</p>';
        }
        $status = get_post_meta($post->ID, '_bbs_status', true) ?: 'open';
        echo '<p><label><strong>Hiring status</strong></label><br><select name="bbs_status">';
        foreach (array('open'=>'Open — accepting applications','closed'=>'Closed — stop applications') as $value=>$label) {
            echo '<option value="'.esc_attr($value).'" '.selected($status,$value,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></p></div>';
        $min=Achuthan_Eligibility::min_years($post->ID);
        echo '<div class="aj-screening-admin"><h3>Eligibility screening (HR guidance only)</h3><p>Set objective requirements for this position. Applicants who do not meet them are flagged for human review, never automatically rejected. Additional qualifying answers can be configured within the custom questions below.</p>';
        echo '<label for="aj_min_years"><strong>Minimum relevant years of experience</strong></label><br><input type="number" id="aj_min_years" name="aj_min_years" min="0" max="70" step="0.5" value="'.esc_attr($min?:'0').'"><p class="description">Example: 2. Set to 0 to disable the experience threshold. Unanswered questions are marked Needs HR review, not rejected.</p></div>';
    }
    public static function save_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['bbs_job_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bbs_job_nonce'])),'bbs_save_job_'.$post_id)) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $raw_code=strtoupper(self::data('aj_job_code'));
        if ($raw_code==='' || !preg_match('/^[A-Z0-9-]{3,30}$/D',$raw_code) || !self::available_code($raw_code,$post_id)) $raw_code='AJ-'.str_pad((string)$post_id,6,'0',STR_PAD_LEFT);
        update_post_meta($post_id,'_aj_job_code',$raw_code);
        $years=self::data('aj_min_years','0');
        $minimum= is_numeric($years) && (float)$years>=0 && (float)$years<=70 ? round((float)$years*2)/2 : 0;
        update_post_meta($post_id,Achuthan_Eligibility::META_MIN_YEARS,$minimum);
        foreach (array('department','location','type','experience','salary','deadline','status') as $key) {
            $value = self::data('bbs_'.$key);
            if ('deadline' === $key && !self::valid_date($value)) $value = '';
            if ('status' === $key && !in_array($value,array('open','closed'),true)) $value='closed';
            if ('type' === $key && !in_array($value,array('Full-time','Part-time','Contract','Internship','Temporary'),true)) $value='Full-time';
            update_post_meta($post_id,'_bbs_'.$key,$value);
        }
    }
    private static function open($job_id) {
        if (get_post_type($job_id)!==self::CPT || get_post_status($job_id)!=='publish') return false;
        if (get_post_meta($job_id,'_bbs_status',true)==='closed') return false;
        $deadline = get_post_meta($job_id,'_bbs_deadline',true);
        return !($deadline && wp_date('Y-m-d') > $deadline);
    }
    public static function frontend_assets() {
        // Enqueue only when rendered (including inside Elementor Shortcode widgets).
        // The fallback for job singular pages is handled by job_content().
        if (is_singular(self::CPT) || is_page('careers') || (is_singular() && has_shortcode((string)get_post_field('post_content',get_queried_object_id()),'achuthan_jobs')) || (is_singular() && has_shortcode((string)get_post_field('post_content',get_queried_object_id()),'bbs_careers'))) self::enqueue_front_assets();
    }
    private static function enqueue_front_assets() {
        wp_enqueue_style('achuthan-jobs-poppins','https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap',array(),null);
        wp_enqueue_style('achuthan-jobs-front',plugin_dir_url(__FILE__).'assets/front.css',array(),self::VERSION);
        wp_enqueue_script('achuthan-jobs-front',plugin_dir_url(__FILE__).'assets/front.js',array(),self::VERSION,true);
    }
    private static function arrow_svg() { return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>'; }
    private static function job_meta($id, $key) { return (string)get_post_meta($id, '_bbs_'.$key,true); }
    /** Human-readable job identifier. Auto generated from immutable WordPress post ID. */
    public static function job_code($id) {
        $saved=(string)get_post_meta($id,'_aj_job_code',true);
        return $saved!==''?$saved:'AJ-'.str_pad((string)absint($id),6,'0',STR_PAD_LEFT);
    }
    private static function available_code($code,$post_id) {
        $existing=get_posts(array('post_type'=>self::CPT,'post_status'=>'any','fields'=>'ids',
            'posts_per_page'=>2,'meta_key'=>'_aj_job_code','meta_value'=>$code,'exclude'=>array($post_id)));
        return !$existing;
    }

    public static function shortcode() {
        self::enqueue_front_assets();
        ob_start();
        echo '<section class="bbs-careers" data-bbs-ajax="'.esc_url(admin_url('admin-ajax.php')).'" aria-label="'.esc_attr(self::employer_name()).' careers">';
        echo '<div class="bbs-hero"><div class="bbs-eyebrow"><span class="bbs-dot"></span> '.esc_html(self::employer_name()).' / CAREERS</div><h1>Discover your next<br><em>opportunity.</em></h1><p>Explore open roles and find a team where your skills can make a difference.</p><a href="#bbs-openings" class="bbs-hero-link">Explore open positions '.self::arrow_svg().'</a><div class="bbs-hero-art" aria-hidden="true"><span></span><span></span><span></span></div></div>';
        echo '<div class="bbs-culture"><div><span class="bbs-overline">THE TEAM</span><h2>Your next chapter<br>starts here.</h2></div><p>Explore opportunities and learn where you can make a meaningful contribution.</p></div>';
        echo self::render_list();
        echo '<div class="bbs-bottom"><span class="bbs-overline">YOUR NEXT OPPORTUNITY</span><h2>Find a role<br>that fits.</h2><p>All advertised positions are managed by '.esc_html(self::employer_name()).'.</p></div></section>';
        return ob_get_clean();
    }
    /** Server-side output for SEO plus an uncached AJAX refresh for the live Careers page. */
    private static function render_list() {
        $jobs=get_posts(array('post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'date','order'=>'DESC','suppress_filters'=>false));
        ob_start();
        echo '<div id="bbs-openings" class="bbs-list" data-bbs-live="1"><div class="bbs-heading"><div><span class="bbs-overline">JOIN OUR TEAM</span><h2>Open positions<span class="bbs-count"></span></h2></div><p>Jobs shown here update automatically when our team publishes or closes a vacancy.</p></div>';
        echo '<div class="bbs-filters"><label class="bbs-search"><span class="screen-reader-text">Search jobs</span><input type="search" data-bbs-filter="query" placeholder="Search roles or skills"></label><label><span class="screen-reader-text">Department</span><select data-bbs-filter="department"><option value="">All departments</option>';
        $departments = array(); $types = array();
        foreach ($jobs as $job) { if (self::open($job->ID)) { $d=self::job_meta($job->ID,'department'); $t=self::job_meta($job->ID,'type'); if ($d) $departments[$d]=$d; if ($t) $types[$t]=$t; } }
        natcasesort($departments); natcasesort($types);
        foreach($departments as $d) echo '<option value="'.esc_attr(strtolower($d)).'">'.esc_html($d).'</option>';
        echo '</select></label><label><span class="screen-reader-text">Employment type</span><select data-bbs-filter="type"><option value="">All job types</option>';
        foreach($types as $t) echo '<option value="'.esc_attr(strtolower($t)).'">'.esc_html($t).'</option>';
        echo '</select></label></div><div class="bbs-cards">';
        $count=0;
        foreach($jobs as $job) {
            if (!self::open($job->ID)) continue;
            $count++;
            $dept=self::job_meta($job->ID,'department'); $loc=self::job_meta($job->ID,'location');
            $type=self::job_meta($job->ID,'type'); $exp=self::job_meta($job->ID,'experience');
            $search=strtolower($job->post_title.' '.$job->post_content.' '.$dept.' '.$loc.' '.$type);
            echo '<a class="bbs-job-card" href="'.esc_url(get_permalink($job)).'" data-query="'.esc_attr(wp_strip_all_tags($search)).'" data-department="'.esc_attr(strtolower($dept)).'" data-type="'.esc_attr(strtolower($type)).'">';
            echo '<div><span class="bbs-tag">'.esc_html($dept?:'Careers').'</span> <span class="bbs-job-id">'.esc_html(self::job_code($job->ID)).'</span><h3>'.esc_html(get_the_title($job)).'</h3><div class="bbs-chiprow">';
            foreach(array($loc,$type,$exp) as $v) if($v) echo '<span>'.esc_html($v).'</span>';
            echo '</div></div><span class="bbs-card-arrow" aria-hidden="true">'.self::arrow_svg().'</span></a>';
        }
        echo '</div><p class="bbs-no-results" hidden>No roles match your search. Try a different keyword or filter.</p>';
        if(!$count) echo '<div class="bbs-empty"><h3>No current openings</h3><p>There aren’t any published vacancies right now. Please check back later.</p></div>';
        echo '</div>';
        return ob_get_clean();
    }
    public static function live_jobs() {
        // Public endpoint contains only already-published job descriptions and links.
        nocache_headers();
        wp_send_json_success(array('html'=>self::render_list()));
    }
    public static function fresh_application_form() {
        // Always deliver fresh nonces and timestamps, even behind page/CDN caching.
        nocache_headers();
        $id=isset($_GET['job_id'])?absint(wp_unslash($_GET['job_id'])):0;
        if (!self::open($id)) wp_send_json_error(array('message'=>'This position is no longer accepting applications.'),404);
        wp_send_json_success(array('html'=>self::application_form($id)));
    }
    public static function extra_questions() {
        nocache_headers();
        $id=isset($_GET['job_id'])?absint(wp_unslash($_GET['job_id'])):0;
        if (!self::open($id)) wp_send_json_error(array('message'=>'This vacancy is no longer accepting applications.'),404);
        wp_send_json_success(array('html'=>Achuthan_Eligibility::applicant_field($id).Achuthan_Questions::render($id,true)));
    }
    public static function document_title($parts) {
        if (is_singular(self::CPT)) $parts['title'] = get_the_title() . ' — Careers at ' . self::employer_name();
        return $parts;
    }
    public static function job_content($content) {
        if (!is_singular(self::CPT) || !in_the_loop() || !is_main_query()) return $content;
        self::enqueue_front_assets();
        $id=get_the_ID();
        $fields=array('department'=>'Department','location'=>'Location','type'=>'Job type','experience'=>'Experience','salary'=>'Compensation','deadline'=>'Apply by');
        $out='<div class="bbs-careers bbs-detail" data-bbs-ajax="'.esc_url(admin_url('admin-ajax.php')).'"><a class="bbs-back" href="'.esc_url(home_url('/careers/')).'">← All opportunities</a><p class="bbs-overline">'.esc_html(self::employer_name()).' / OPPORTUNITY</p><div class="bbs-details-meta">';
        foreach($fields as $key=>$label) {
            $val=self::job_meta($id,$key);
            if($val) $out.='<div><small>'.esc_html($label).'</small><strong>'.esc_html($val).'</strong></div>';
        }
        $out.='</div><p class="bbs-job-id">JOB ID: '.esc_html(self::job_code($id)).'</p><div class="bbs-role-copy">'.$content.'</div>';
        if(self::open($id)) $out.=self::application_form($id);
        else $out.='<div class="bbs-closed">Applications for this position are currently closed.</div>';
        return $out.'</div>';
    }
    private static function application_form($id) {
        $status=isset($_GET['bbs_apply']) ? sanitize_key(wp_unslash($_GET['bbs_apply'])) : '';
        $messages=array('success'=>'Application received for your selected open jobs. Thank you — our team will review it.','invalid'=>'Please check the required fields, additional jobs and uploaded files.','consent'=>'Please tick the privacy consent checkbox before submitting.','expired'=>'This form expired. Refresh the page and submit again.','questions'=>'Please complete the questions for each selected job. Every file must be 3 MB or less.','pdf'=>'Please choose a valid PDF resume no larger than 3 MB.','slow'=>'Too many submissions. Please try again later.','error'=>'Unable to submit your application. Please try again or contact us.');
        $out='<div class="bbs-apply" id="bbs-apply" data-bbs-job="'.esc_attr($id).'"><span class="bbs-overline">TAKE YOUR NEXT STEP</span><h2>Apply for this role</h2><p>Tell us a little about yourself. Your resume must be a PDF, maximum 3 MB.</p>';
        if(isset($messages[$status])) $out.='<div class="bbs-alert '.($status==='success'?'bbs-success':'bbs-error').'" role="status">'.esc_html($messages[$status]).'</div>';
        if (!get_privacy_policy_url() || !self::encryption_ready()) {
            $out.='<div class="bbs-alert bbs-error" role="alert">Applications are temporarily unavailable. Please contact our team.</div></div>';
            return $out;
        }
        $out.='<div class="bbs-client-alert" role="alert" aria-live="assertive" hidden></div><form class="bbs-application-form" action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data">';
        $out.='<input type="hidden" name="action" value="bbs_apply"><input type="hidden" name="job_id" value="'.esc_attr($id).'"><input type="hidden" name="bbs_started" value="'.esc_attr(time()).'">';
        $out.=wp_nonce_field('bbs_apply_'.$id,'bbs_nonce',true,false);
        $out.='<div class="bbs-honey" aria-hidden="true"><label>Leave blank <input name="company_website" tabindex="-1" autocomplete="off"></label></div>';
        $out.='<div class="bbs-form-grid"><label>Full name *<input required maxlength="190" name="full_name" autocomplete="name"></label><label>Email address *<input type="email" required maxlength="190" name="email" autocomplete="email"></label><label>Phone number<input type="tel" name="phone" maxlength="50" autocomplete="tel"></label><label>Portfolio / LinkedIn link<input type="url" name="portfolio" placeholder="https://" maxlength="500"></label></div>';
        $out.='<label>Why would you like to join?<textarea name="message" rows="5" maxlength="6000" placeholder="Tell us about your work and what you would bring to the team..."></textarea></label>';
        $out.=Achuthan_Eligibility::applicant_field($id);
        $out.=Achuthan_Questions::render($id,true);
        $other_jobs=get_posts(array('post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>100,'orderby'=>'date','order'=>'DESC'));
        $more=array();foreach($other_jobs as $j) if($j->ID!==$id && self::open($j->ID)) $more[]=$j;
        if($more){
            $out.='<fieldset class="aj-multi-jobs"><legend>Apply for more than one job (optional)</legend><p>Choose up to 2 additional roles. Job-specific questions will appear below each selection.</p><div class="aj-other-list">';
            foreach($more as $j){$out.='<label class="aj-other-job"><input type="checkbox" name="extra_job_ids[]" data-aj-extra-job="'.esc_attr($j->ID).'" value="'.esc_attr($j->ID).'"> <span><strong>'.esc_html($j->post_title).'</strong><small>'.esc_html(self::job_code($j->ID)).'</small></span></label>';}
            $out.='</div><div class="aj-extra-question-groups" aria-live="polite"></div></fieldset>';
        }
        $out.='<label>Resume (PDF only, max 3 MB) *<input type="file" name="resume" required accept=".pdf,application/pdf"></label>';
        $privacy=get_privacy_policy_url();
        $text='I agree to '.self::employer_name().' processing my application and CV for recruitment purposes';
        $out.='<label class="bbs-consent"><input type="checkbox" name="bbs_consent" value="1" required><span>'.esc_html($text).($privacy?' under its <a target="_blank" rel="noopener" href="'.esc_url($privacy).'">privacy policy</a>':'').'.</span></label>';
        $out.='<button type="submit">Submit application '.self::arrow_svg().'</button><p class="bbs-form-note">Please do not include ID proofs, bank information or other sensitive documents.</p></form></div>';
        return $out;
    }
    private static function apply_redirect($id,$status) {
        $url = $id && get_permalink($id) ? get_permalink($id) : home_url('/careers/');
        wp_safe_redirect(add_query_arg('bbs_apply',$status,$url).'#bbs-apply',303);
        exit;
    }
    public static function apply() {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') wp_die('Method not allowed.', '', array('response'=>405));
        // Max 3 selected jobs (primary plus 2 additional). Up to 4 question files per job.
        $max_body=self::MAX_RESUME + (3 * Achuthan_Questions::MAX_TOTAL_BYTES) + 150000;
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH']>$max_body) wp_die('Upload too large. Each file is limited to 3 MB.','',array('response'=>413));
        $id=absint(self::data('job_id'));
        if (!get_privacy_policy_url() || !self::encryption_ready() || !self::open($id)) self::apply_redirect($id,'error');
        if (!isset($_POST['bbs_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bbs_nonce'])),'bbs_apply_'.$id)) self::apply_redirect($id,'expired');
        if (self::data('company_website')) self::apply_redirect($id,'success');
        $started=absint(self::data('bbs_started'));
        if (!$started || $started>time()+30 || time()-$started<3) self::apply_redirect($id,'invalid');
        if (time()-$started>12*HOUR_IN_SECONDS) self::apply_redirect($id,'expired');
        $extra=isset($_POST['extra_job_ids']) && is_array($_POST['extra_job_ids']) ? wp_unslash($_POST['extra_job_ids']) : array();
        if (count($extra)>2) self::apply_redirect($id,'invalid');
        $job_ids=array($id);
        foreach ($extra as $val){
            if(!is_scalar($val)||!preg_match('/^[1-9][0-9]*$/D',(string)$val))self::apply_redirect($id,'invalid');
            $other=(int)$val;if($other===$id || in_array($other,$job_ids,true) || !self::open($other))self::apply_redirect($id,'invalid');
            $job_ids[]=$other;
        }
        $name=self::data('full_name');$email=sanitize_email(self::data('email'));
        $phone=self::data('phone');$portfolio=esc_url_raw(self::data('portfolio'));
        $message=isset($_POST['message'])&&is_scalar($_POST['message'])?sanitize_textarea_field(wp_unslash($_POST['message'])):'';
        if(!isset($_POST['bbs_consent'])||$_POST['bbs_consent']!=='1')self::apply_redirect($id,'consent');
        if(!$name||!is_email($email)||strlen($name)>190||strlen($email)>190||strlen($phone)>50||strlen($portfolio)>500||strlen($message)>6000)self::apply_redirect($id,'invalid');
        if($portfolio && (!wp_http_validate_url($portfolio)||!in_array(wp_parse_url($portfolio,PHP_URL_SCHEME),array('http','https'),true)))self::apply_redirect($id,'invalid');
        $file=$_FILES['resume']??null;
        if(!is_array($file)||(int)($file['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>self::MAX_RESUME||($file['size']??0)<100)self::apply_redirect($id,'pdf');
        if(!is_string($file['name']??null)||strlen($file['name'])>180||preg_match('/[\x00-\x1F:]/',$file['name']))self::apply_redirect($id,'pdf');
        $filename=sanitize_file_name($file['name']);
        if(strtolower(pathinfo($filename,PATHINFO_EXTENSION))!=='pdf'||!class_exists('finfo'))self::apply_redirect($id,'pdf');
        $bytes=file_get_contents($file['tmp_name']);
        if(!self::valid_pdf_bytes($bytes) || (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])!=='application/pdf')self::apply_redirect($id,'pdf');
        $ip=isset($_SERVER['REMOTE_ADDR'])?(string)$_SERVER['REMOTE_ADDR']:'unknown';
        if(self::rate_limited('ip-minute|'.$ip,2,MINUTE_IN_SECONDS)||self::rate_limited('ip-day|'.$ip,15,DAY_IN_SECONDS)||self::rate_limited('email-day|'.$email,5,DAY_IN_SECONDS))self::apply_redirect($id,'slow');
        $responses=array();
        foreach($job_ids as $jid){
            $response=Achuthan_Questions::validate($jid,true);
            if(is_wp_error($response))self::apply_redirect($id,'questions');
            $raw_years=isset($_POST['aj_years']) && is_array($_POST['aj_years']) && isset($_POST['aj_years'][$jid]) && is_scalar($_POST['aj_years'][$jid]) ? sanitize_text_field(wp_unslash($_POST['aj_years'][$jid])) : '';
            if ($raw_years!=='' && (!is_numeric($raw_years) || (float)$raw_years<0 || (float)$raw_years>70 || round((float)$raw_years*2)/2 !== (float)$raw_years)) self::apply_redirect($id,'invalid');
            $response['experience']=$raw_years===''?null:(float)$raw_years;
            $response['eligibility']=Achuthan_Eligibility::evaluate($jid,$response['experience'],$response['answers']);
            $responses[$jid]=$response;
        }
        $encrypted=self::encrypt_resume($bytes);
        if(!is_string($encrypted))self::apply_redirect($id,'error');
        global $wpdb;$table=self::table();$now=current_time('mysql',true);$inserted=array();
        // A single batch is all-or-nothing. A same-day duplicate for an individual job is skipped.
        $wpdb->query('START TRANSACTION');$failed=false;
        foreach($job_ids as $jid){
            $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE job_id=%d AND email=%s AND created_at >= %s LIMIT 1",$jid,$email,gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS)));
            if($existing)continue;
            $ok=$wpdb->insert($table,array('job_id'=>$jid,'full_name'=>$name,'email'=>$email,'phone'=>$phone,
                'portfolio'=>$portfolio,'message'=>$message,'question_answers'=>wp_json_encode($responses[$jid]['answers']),
                'resume_filename'=>$filename,'resume_mime'=>'application/pdf','resume_data'=>$encrypted,
                'scan_status'=>'validated','stage'=>'new','notes'=>'',
                'candidate_experience'=>$responses[$jid]['experience'],
                'eligibility_status'=>$responses[$jid]['eligibility']['status'],
                'eligibility_detail'=>wp_json_encode($responses[$jid]['eligibility']),
                'consent_at'=>$now,'created_at'=>$now,'updated_at'=>$now));
            if($ok===false){$failed=true;break;}
            $application_id=(int)$wpdb->insert_id;
            if(!Achuthan_Questions::save_files($application_id,$responses[$jid]['files'])){$failed=true;break;}
            $inserted[]=array('application'=>$application_id,'job'=>$jid);
        }
        if($failed){$wpdb->query('ROLLBACK');self::apply_redirect($id,'error');}
        $wpdb->query('COMMIT');
        $notify=get_option('bbs_hr_email',get_option('admin_email'));
        foreach($inserted as $new){
            self::audit('application_submitted',$new['application'],'validated');
            self::audit('eligibility_assessed',$new['application'], $responses[$new['job']]['eligibility']['status']);
            if(is_email($notify)&&!wp_mail($notify,'New job application ['.self::job_code($new['job']).']: '.get_the_title($new['job']),
                "Candidate: ".$name."\nRole: ".get_the_title($new['job'])."\nJob ID: ".self::job_code($new['job'])."\nView: ".admin_url('admin.php?page=bbs-applicants&view='.$new['application'])))
                update_option('achuthan_jobs_mail_failed_at',$now,false);
        }
        if($inserted){
            $titles=array_map(static function($item){return get_the_title($item['job']);},$inserted);
            if(!wp_mail($email,'Applications received — '.self::employer_name(),"Hi ".$name.",\n\nWe received your application for: ".implode(', ',$titles).".\n\n".self::employer_name()))
                update_option('achuthan_jobs_mail_failed_at',$now,false);
        }
        self::apply_redirect($id,'success');
    }
    public static function valid_pdf_bytes($bytes) {
        return is_string($bytes)&&strlen($bytes)>=100&&strlen($bytes)<=self::MAX_RESUME&&substr($bytes,0,5)==='%PDF-'
            &&strpos(substr($bytes,-1024),'%%EOF')!==false
            &&!preg_match('~/(?:JavaScript|JS|OpenAction|Launch|EmbeddedFile|RichMedia|XFA)\b~i',$bytes);
    }
    public static function menus() {
        // Clicking the top-level plugin menu ALWAYS opens the private HR dashboard.
        add_menu_page('Achuthan Jobs | HR Dashboard','Achuthan Jobs',self::CAP,'bbs-recruitment',array(__CLASS__,'dashboard'),'dashicons-groups',26);
        add_submenu_page('bbs-recruitment','HR Dashboard','HR Dashboard',self::CAP,'bbs-recruitment',array(__CLASS__,'dashboard'));
        add_submenu_page('bbs-recruitment','Manage Jobs','Manage Jobs','edit_bbs_jobs','aj-manage-jobs',array(__CLASS__,'manage_jobs'));
        add_submenu_page('bbs-recruitment','Applications','Applications',self::CAP,'bbs-applicants',array(__CLASS__,'applicants'));
        add_submenu_page('bbs-recruitment','Settings','Settings',self::CAP,'bbs-recruitment-settings',array(__CLASS__,'settings'));
    }
    /** One preview control. Preview requests are POST-only and never expose stored file URLs. */
    public static function preview_control($action,$id,$nonce_action,$filename) {
        $id=absint($id);
        return '<form method="post" class="aj-preview-form" action="'.esc_url(admin_url('admin-post.php')).'" target="aj-private-document-frame">'
            .'<input type="hidden" name="action" value="'.esc_attr($action).'">'
            .'<input type="hidden" name="id" value="'.esc_attr($id).'">'
            .wp_nonce_field($nonce_action,'_wpnonce',true,false)
            .'<button type="submit" class="button aj-preview-button" data-aj-filename="'.esc_attr($filename).'">Preview file</button></form>';
    }
    private static function preview_modal() {
        echo '<div id="aj-document-preview" class="aj-preview-modal" role="dialog" aria-modal="true" aria-labelledby="aj-preview-title" hidden>';
        echo '<div class="aj-preview-shade" data-aj-preview-close></div><section class="aj-preview-panel">';
        echo '<header class="aj-preview-heading"><div><strong id="aj-preview-title">Private document preview</strong><small id="aj-preview-filename">PDF or image</small></div>';
        echo '<button type="button" class="button" data-aj-preview-close aria-label="Close document preview">Close ×</button></header>';
        echo '<iframe id="aj-private-document-frame" name="aj-private-document-frame" title="Private applicant document" sandbox="allow-scripts" referrerpolicy="no-referrer" loading="eager"></iframe>';
        echo '<p class="aj-preview-footnote">HR-only inline preview. Files are not made public. If the browser cannot display a PDF, use its Download action instead.</p>';
        echo '</section></div>';
    }
    /** Validate on the server and deliver a document in an isolated inline browser frame. */
    public static function stream_preview($data,$mime,$filename,$application_id,$event) {
        if (!in_array($mime,array('application/pdf','image/jpeg','image/png'),true)) wp_die('Unsupported preview format.','',array('response'=>415));
        $name=preg_replace('/[^a-zA-Z0-9._-]/','_',basename((string)$filename));
        if ($name==='') $name=$mime==='application/pdf'?'document.pdf':'image.png';
        self::audit($event,absint($application_id));
        while (ob_get_level()>0) ob_end_clean();
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer');
        // Restrict documents to opaque origins; never grant same-origin permissions.
        header("Content-Security-Policy: sandbox allow-scripts; default-src 'none'; base-uri 'none'; form-action 'none'");
        header('Content-Type: '.$mime);
        header('Content-Disposition: inline; filename="'.$name.'"');
        header('Content-Length: '.strlen($data));
        echo $data;exit;
    }
    public static function plugin_links($links) {
        if (current_user_can(self::CAP)) {
            array_unshift($links,'<a href="'.esc_url(admin_url('admin.php?page=bbs-recruitment')).'">HR Dashboard</a>');
        }
        return $links;
    }
    public static function admin_assets($hook) {
        if(strpos($hook,'bbs-')!==false || strpos($hook,'aj-manage-jobs')!==false || (isset($_GET['post_type']) && $_GET['post_type']===self::CPT) || ($hook==='post.php' && isset($_GET['post']) && get_post_type(absint($_GET['post']))===self::CPT)) {
            wp_enqueue_style('achuthan-jobs-poppins','https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap',array(),null);
            wp_enqueue_style('bbs-admin',plugin_dir_url(__FILE__).'assets/admin.css',array('achuthan-jobs-poppins'),self::VERSION);
            if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'bbs-applicants') {
                wp_enqueue_script('achuthan-jobs-preview',plugin_dir_url(__FILE__).'assets/admin-preview.js',array(),self::VERSION,true);
            }
            if (($hook==='post.php' && isset($_GET['post']) && get_post_type(absint($_GET['post']))===self::CPT) || (isset($_GET['post_type']) && $_GET['post_type']===self::CPT)) {
                wp_enqueue_script('achuthan-questions',plugin_dir_url(__FILE__).'assets/questions-admin.js',array(),self::VERSION,true);
            }
        }
    }
    private static function require_hr() { if(!current_user_can(self::CAP)) wp_die('Insufficient permissions.', '', array('response'=>403)); }
    private static function admin_candidate_url($id) {
        return admin_url('admin.php?page=bbs-applicants&view='.absint($id));
    }
    private static function admin_stage_badge($stage) {
        $label=self::stages()[$stage]??'Unknown';
        return '<span class="aj-stage-badge aj-stage-'.esc_attr(sanitize_html_class($stage)).'">'.esc_html($label).'</span>';
    }
    private static function dashboard_panel_header($title,$description='',$link='',$link_label='View all') {
        echo '<div class="aj-panel-heading"><div><h2>'.esc_html($title).'</h2>';
        if($description!=='') echo '<p>'.esc_html($description).'</p>';
        echo '</div>';
        if($link) echo '<a class="aj-text-link" href="'.esc_url($link).'">'.esc_html($link_label).' <span aria-hidden="true">↗</span></a>';
        echo '</div>';
    }
    private static function dashboard_recent($limit=8) {
        global $wpdb; $table=self::table();
        $limit=max(1,min(30,(int)$limit));
        // The table name is derived from the trusted WPDB prefix, never from request data.
        return (array)$wpdb->get_results($wpdb->prepare("SELECT id, job_id, full_name, stage, eligibility_status, created_at FROM $table ORDER BY created_at DESC LIMIT %d",$limit));
    }
    private static function dashboard_interviews($limit=12) {
        global $wpdb; $table=self::table();
        $limit=max(1,min(50,(int)$limit));
        return (array)$wpdb->get_results($wpdb->prepare(
            "SELECT id, job_id, full_name, stage, interview_at, interview_time, interview_mode FROM $table WHERE interview_at IS NOT NULL AND interview_at >= %s ORDER BY interview_at ASC, interview_time ASC LIMIT %d",
            current_time('Y-m-d'),$limit
        ));
    }
    private static function dashboard_interview_table($rows) {
        if(!$rows) { echo '<div class="aj-empty"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><b>No interviews scheduled yet</b><p>Open a candidate profile to schedule an interview.</p></div>'; return; }
        echo '<div class="aj-table-scroll"><table class="aj-data-table"><thead><tr><th>Candidate</th><th>Position</th><th>Date & time</th><th>Mode</th><th></th></tr></thead><tbody>';
        foreach($rows as $row) {
            $date=(string)$row->interview_at;
            echo '<tr><td><a class="aj-person" href="'.esc_url(self::admin_candidate_url($row->id)).'">'.esc_html($row->full_name).'</a></td>';
            echo '<td>'.esc_html(get_the_title($row->job_id)).'</td><td><strong>'.esc_html($date).'</strong> '.esc_html($row->interview_time?:'').'</td>';
            echo '<td>'.esc_html($row->interview_mode?:'Not set').'</td><td><a class="aj-icon-link" aria-label="Open candidate" href="'.esc_url(self::admin_candidate_url($row->id)).'">↗</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    private static function data_from_get($key) {
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }
    private static function dashboard_email_composer($id,$base) {
        global $wpdb;
        $person=$wpdb->get_row($wpdb->prepare('SELECT id, job_id, full_name, email FROM '.self::table().' WHERE id=%d',$id));
        if(!$person || !is_email($person->email)) { echo '<div class="notice notice-error inline"><p>Candidate not found.</p></div>'; return; }
        echo '<section class="aj-white-card aj-compose-card" id="aj-compose"><div class="aj-panel-heading"><div><h2>Email applicant</h2><p>Write directly to '.esc_html($person->full_name).' ('.esc_html($person->email).') about '.esc_html(get_the_title($person->job_id)).'.</p></div><a href="'.esc_url($base).'">Close ×</a></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="aj-compose-form"><input type="hidden" name="action" value="aj_candidate_email"><input type="hidden" name="return_to" value="dashboard"><input type="hidden" name="id" value="'.esc_attr($person->id).'">';
        wp_nonce_field('aj_candidate_email_'.$person->id);
        echo '<label><strong>Subject</strong><input type="text" required maxlength="150" name="subject" value="Regarding your application to '.esc_attr(self::employer_name()).'"></label>';
        echo '<label><strong>Message to '.esc_html($person->full_name).'</strong><textarea name="body" required maxlength="5000" rows="7" placeholder="Hi '.esc_attr($person->full_name).',"></textarea></label>';
        echo '<p class="description">Messages are sent via your WordPress email service. Configure and test SMTP before using with real candidates.</p><button class="button button-primary" type="submit">Send email to applicant</button></form></section>';
    }
    public static function dashboard() {
        self::require_hr();
        global $wpdb; $table=self::table();
        $tab=isset($_GET['view']) && is_scalar($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'overview';
        if(!in_array($tab,array('overview','pipeline','interviews'),true)) $tab='overview';
        $counts=array_fill_keys(array_keys(self::stages()),0);
        foreach((array)$wpdb->get_results("SELECT stage, COUNT(*) AS total FROM $table GROUP BY stage") as $row) {
            if(isset($counts[$row->stage])) $counts[$row->stage]=(int)$row->total;
        }
        $total=array_sum($counts);
        $open_jobs=0;
        $published=get_posts(array('post_type'=>self::CPT,'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
        foreach($published as $job_id) if(self::open($job_id)) $open_jobs++;
        $recent=self::dashboard_recent();
        $interviews=self::dashboard_interviews();
        $jobs=get_posts(array('post_type'=>self::CPT,'post_status'=>'publish','posts_per_page'=>5,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true));
        $title=$tab==='pipeline'?'Hiring pipeline':($tab==='interviews'?'Interview schedule':'Your hiring workspace');
        $sub=$tab==='pipeline'?'Review candidates at each hiring stage. Select a card to update details.':($tab==='interviews'?'See upcoming interviews and manage candidates from one place.':'Everything your HR team needs to move hiring forward.');
        $base=admin_url('admin.php?page=bbs-recruitment');
        $jobs_link=admin_url('admin.php?page=aj-manage-jobs');
        $apps_link=admin_url('admin.php?page=bbs-applicants');
        $careers=get_page_by_path('careers');
        $careers_link=$careers?get_permalink($careers):home_url('/careers/');
        echo '<div class="wrap bbs-admin aj-dashboard">';
        echo '<div class="aj-hero"><div class="aj-hero-top"><span class="aj-monogram" aria-hidden="true">aj.</span><div><strong>ACHUTHAN JOBS</strong><span>WORDPRESS RECRUITMENT MANAGEMENT</span></div><span class="aj-workspace-tag"><span class="aj-status-dot"></span> PRIVATE HR WORKSPACE</span></div>';
        echo '<div class="aj-hero-main"><div><p class="aj-kicker">RECRUITMENT CONTROL CENTER</p><h1>'.esc_html($title).'<span class="aj-lime-period">.</span></h1><p>'.esc_html($sub).'</p></div>';
        echo '<div class="aj-hero-actions"><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type='.self::CPT)).'"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Post a job</a>';
        echo '<a class="button" href="'.esc_url($apps_link).'">View applications <span aria-hidden="true">↗</span></a></div></div></div>';
        echo '<nav class="aj-dashboard-tabs" aria-label="HR dashboard sections">';
        foreach(array('overview'=>'Overview','pipeline'=>'Hiring pipeline','interviews'=>'Interviews') as $slug=>$label) {
            $url=$slug==='overview'?$base:add_query_arg('view',$slug,$base);
            echo '<a '.($tab===$slug?'class="active" aria-current="page"':'').' href="'.esc_url($url).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';
        echo '<div class="aj-summary-grid">';
        $summary=array(
            array('Open positions',$open_jobs,'dashicons-businessperson','aj-green'),
            array('Total candidates',$total,'dashicons-groups',''),
            array('New applications',$counts['new'],'dashicons-email-alt',''),
            array('In interviews',$counts['interview'],'dashicons-calendar-alt',''),
        );
        foreach($summary as $s) {
            echo '<div class="aj-metric '.esc_attr($s[3]).'"><div class="aj-metric-top"><span>'.esc_html($s[0]).'</span><span class="dashicons '.esc_attr($s[2]).'" aria-hidden="true"></span></div><strong>'.esc_html($s[1]).'</strong><span class="aj-metric-hint">'.($s[0]==='Open positions'?'Accepting applications':'Across your recruitment workflow').'</span></div>';
        }
        echo '</div>';
        if(isset($_GET['email_sent']) && self::data_from_get('email_sent')==='1') {
            echo '<div class="notice notice-success inline"><p>Email handed to the WordPress mail system. Check your SMTP/mail logs for actual delivery.</p></div>';
        }
        $compose_id=isset($_GET['compose'])?absint(wp_unslash($_GET['compose'])):0;
        if($compose_id) self::dashboard_email_composer($compose_id,$base);
        if($tab==='overview') {
            echo '<div class="aj-dashboard-grid">';
            echo '<section class="aj-white-card">';self::dashboard_panel_header('Recent applications','The latest people interested in joining your team.',$apps_link,'All applicants');
            if(!$recent) echo '<div class="aj-empty"><b>No applications yet</b><p>Applicants will appear here after submitting the Careers form.</p></div>';
            else {
                echo '<div class="aj-table-scroll"><table class="aj-data-table"><thead><tr><th>Candidate</th><th>Applied for</th><th>Stage</th><th>Eligibility</th><th>Applied</th><th></th></tr></thead><tbody>';
                foreach($recent as $a){
                    echo '<tr><td><a class="aj-person" href="'.esc_url(self::admin_candidate_url($a->id)).'">'.esc_html($a->full_name).'</a></td>';
                    echo '<td>'.esc_html(get_the_title($a->job_id)).'</td><td>'.self::admin_stage_badge($a->stage).'</td><td><span class="aj-eligibility-pill aj-eligibility-'.esc_attr($a->eligibility_status).'">'.esc_html(Achuthan_Eligibility::label($a->eligibility_status)).'</span></td>';
                    echo '<td>'.esc_html(get_date_from_gmt($a->created_at,'d M Y')).'</td><td><a class="aj-icon-link" aria-label="Open candidate" href="'.esc_url(self::admin_candidate_url($a->id)).'">↗</a> <a class="button aj-mail-action" href="'.esc_url(add_query_arg('compose',absint($a->id),$base)).'">Email</a></td></tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '</section><section class="aj-white-card">';self::dashboard_panel_header('Hiring snapshot','Candidate distribution by stage.',add_query_arg('view','pipeline',$base),'Full pipeline');
            echo '<div class="aj-stage-overview">';
            foreach(self::stages() as $code=>$label) {
                $width=$total?round(100*$counts[$code]/$total):0;
                echo '<a class="aj-stage-progress" href="'.esc_url(add_query_arg('stage',$code,$apps_link)).'"><span class="aj-progress-label"><span>'.esc_html($label).'</span><strong>'.esc_html($counts[$code]).'</strong></span><span class="aj-progress-track"><span style="width:'.esc_attr($width).'%"></span></span></a>';
            }
            echo '</div></section></div>';
            echo '<div class="aj-dashboard-grid aj-bottom-grid"><section class="aj-white-card">';
            self::dashboard_panel_header('Upcoming interviews','The next scheduled conversations.',add_query_arg('view','interviews',$base),'See all');
            self::dashboard_interview_table(array_slice($interviews,0,5));
            echo '</section><section class="aj-white-card">';self::dashboard_panel_header('Recently published jobs','Your live positions.',$jobs_link,'Manage jobs');
            if(!$jobs) echo '<div class="aj-empty"><b>No jobs yet</b><p>Publish your first vacancy to get started.</p></div>';
            else foreach($jobs as $job) {
                echo '<div class="aj-job-mini"><span><a href="'.esc_url(get_edit_post_link($job->ID)).'">'.esc_html(get_the_title($job->ID)).'</a><small>'.esc_html(self::job_meta($job->ID,'department')?:'General').' · '.esc_html(self::job_meta($job->ID,'location')?:'Location not set').'</small></span><span class="aj-job-state">'.(self::open($job->ID)?'Open':'Closed').'</span></div>';
            }
            echo '</section></div>';
        } elseif($tab==='pipeline') {
            echo '<section class="aj-white-card aj-pipeline-card">';self::dashboard_panel_header('Candidate pipeline','Select a candidate to edit their stage, private notes, interview and offer.',$apps_link,'All applicants');
            echo '<div class="aj-kanban">';
            foreach(self::stages() as $stage=>$label) {
                echo '<div class="aj-kanban-lane"><div class="aj-lane-title"><h3>'.esc_html($label).'</h3><span>'.esc_html($counts[$stage]).'</span></div>';
                $rows=$wpdb->get_results($wpdb->prepare("SELECT id, job_id, full_name, created_at FROM $table WHERE stage=%s ORDER BY created_at DESC LIMIT 20",$stage));
                foreach((array)$rows as $person) {
                    echo '<a class="aj-candidate-tile" href="'.esc_url(self::admin_candidate_url($person->id)).'"><strong>'.esc_html($person->full_name).'</strong><small>'.esc_html(get_the_title($person->job_id)).'</small><span>Open candidate <span aria-hidden="true">↗</span></span></a>';
                }
                if(!$rows) echo '<p class="aj-lane-empty">No candidates</p>';
                if($counts[$stage]>20) echo '<a class="aj-text-link" href="'.esc_url(add_query_arg('stage',$stage,$apps_link)).'">View remaining</a>';
                echo '</div>';
            }
            echo '</div><p class="aj-board-hint">Candidate details, stage updates and notes are saved through secured WordPress admin forms. Cards are not drag-and-drop in this version.</p></section>';
        } else {
            echo '<section class="aj-white-card">';self::dashboard_panel_header('Upcoming interviews','Dates use the time zone configured in WordPress.',$apps_link,'Manage candidates');
            self::dashboard_interview_table($interviews);
            echo '</section>';
        }
        echo '<div class="aj-footer-links"><a href="'.esc_url($jobs_link).'">Bulk manage jobs <span aria-hidden="true">↗</span></a><a href="'.esc_url($careers_link).'" target="_blank" rel="noopener noreferrer">Public Careers page <span aria-hidden="true">↗</span></a><a href="'.esc_url(admin_url('admin.php?page=bbs-recruitment-settings')).'">Settings <span aria-hidden="true">↗</span></a></div>';
        if(!get_privacy_policy_url()) echo '<div class="aj-dashboard-alert"><strong>Privacy setup required:</strong> Publish a recruitment privacy policy before accepting applications.</div>';
        echo '</div>';
    }
    public static function applicants() {
        self::require_hr(); global $wpdb; $table=self::table();
        $view=isset($_GET['view'])?absint($_GET['view']):0;
        if($view) { self::applicant_detail($view); return; }
        $status=isset($_GET['stage'])?sanitize_key(wp_unslash($_GET['stage'])):'';
        if($status && !isset(self::stages()[$status])) $status='';
        $q=isset($_GET['s'])?sanitize_text_field(wp_unslash($_GET['s'])):'';
        $page=max(1,isset($_GET['paged'])?absint($_GET['paged']):1); $per=25;
        $where='1=1'; $args=array();
        if($status) { $where.=' AND stage=%s'; $args[]=$status; }
        if($q) { $where.=' AND (full_name LIKE %s OR email LIKE %s)'; $like='%'.$wpdb->esc_like($q).'%'; $args[]=$like;$args[]=$like; }
        $total=(int)$wpdb->get_var($args?$wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where",$args):"SELECT COUNT(*) FROM $table WHERE $where");
        $sql="SELECT id, job_id, full_name, email, stage, eligibility_status, created_at, interview_at, candidate_rating FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows=$wpdb->get_results($wpdb->prepare($sql,array_merge($args,array($per,($page-1)*$per))));
        echo '<div class="wrap bbs-admin"><div class="bbs-admin-head"><p>RECRUITMENT / HR</p><h1>Applications</h1><span>'.esc_html($total).' candidate records match your filters</span></div>';
        echo '<form method="get" class="bbs-table-filter"><input type="hidden" name="page" value="bbs-applicants"><input type="search" name="s" value="'.esc_attr($q).'" placeholder="Search name or email"><select name="stage"><option value="">All stages</option>';
        foreach(self::stages() as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html($label).'</option>';
        echo '</select><button class="button button-primary">Filter</button></form>';
        echo '<form id="aj-app-bulk" method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return this.elements.bulk_action.value!==&quot;delete&quot; || confirm(&quot;Permanently delete selected applications and resumes? This cannot be undone.&quot;)"><input type="hidden" name="action" value="aj_candidates_bulk">';
        wp_nonce_field('aj_candidates_bulk');
        echo '<p class="aj-bulk-controls"><label><strong>Bulk action</strong> <select name="bulk_action" required><option value="">Choose action</option>';
        foreach(self::stages() as $code=>$label) echo '<option value="stage_'.esc_attr($code).'">Move to '.esc_html($label).'</option>';
        echo '<option value="download_files">Download selected files (ZIP)</option><option value="delete">Permanently delete</option></select></label> <button type="submit" class="button button-primary">Apply to selected</button> <span>Stage/delete: maximum 100. Download ZIP: maximum 20 candidates, 80 MB.</span></p>';
        echo '<table class="widefat striped bbs-table"><thead><tr><th><input type="checkbox" id="aj-app-select-all" aria-label="Select all on this page"></th><th>Candidate</th><th>Role / Job ID</th><th>Status</th><th>Eligibility</th><th>Applied</th><th>Rating</th><th>Interview</th><th></th></tr></thead><tbody>';
        foreach($rows as $row) echo '<tr><td><input type="checkbox" name="candidate_ids[]" value="'.esc_attr($row->id).'" class="aj-app-check" aria-label="Select candidate '.esc_attr($row->full_name).'"></td><td><strong>'.esc_html($row->full_name).'</strong><small>'.esc_html($row->email).'</small></td><td>'.esc_html(get_the_title($row->job_id)).'<small>'.esc_html(self::job_code($row->job_id)).'</small></td><td><span class="bbs-pill">'.esc_html(self::stages()[$row->stage]??$row->stage).'</span></td><td><span class="aj-eligibility-pill aj-eligibility-'.esc_attr($row->eligibility_status).'">'.esc_html(Achuthan_Eligibility::label($row->eligibility_status)).'</span></td><td>'.esc_html(get_date_from_gmt($row->created_at,'d M Y')).'</td><td>'.esc_html((string)($row->candidate_rating??0)).'/5</td><td>'.esc_html($row->interview_at?:'—').'</td><td><a class="button" href="'.esc_url(admin_url('admin.php?page=bbs-applicants&view='.$row->id)).'">View / preview</a> <button type="submit" form="aj-delete-one-'.esc_attr($row->id).'" class="button button-link-delete aj-delete-row">Delete</button></td></tr>';
        if(!$rows) echo '<tr><td colspan="9">No applications found.</td></tr>';
        echo '</tbody></table></form>';
        // Detached forms avoid invalid nested <form> markup within the bulk-action form.
        foreach($rows as $row) {
            echo '<form id="aj-delete-one-'.esc_attr($row->id).'" class="aj-delete-single-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(&quot;Permanently delete this application and all its files? Other applications by this person will remain. This cannot be undone.&quot;)">';
            echo '<input type="hidden" name="action" value="bbs_delete_applicant"><input type="hidden" name="id" value="'.esc_attr($row->id).'">';
            wp_nonce_field('bbs_delete_'.$row->id);
            echo '</form>';
        }

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="aj_candidates_export">';
        wp_nonce_field('aj_candidates_export');
        echo '<button class="button" type="submit">Export applications (CSV, authorised HR only)</button> <span class="description">Export excludes internal notes and resumes. Protect downloaded files.</span></form>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var a=document.getElementById("aj-app-select-all");if(a)a.addEventListener("change",function(){document.querySelectorAll(".aj-app-check").forEach(function(x){x.checked=a.checked;});});});</script>';
        if($total>$per) { echo '<div class="bbs-pages">';for($p=1;$p<=ceil($total/$per);$p++) echo '<a class="button '.($p===$page?'button-primary':'').'" href="'.esc_url(add_query_arg(array('page'=>'bbs-applicants','paged'=>$p,'stage'=>$status,'s'=>$q),admin_url('admin.php'))).'">'.esc_html($p).'</a> ';echo '</div>'; }
        echo '</div>';
    }
    private static function applicant_detail($id) {
        global $wpdb; $table=self::table();
        $r=$wpdb->get_row($wpdb->prepare("SELECT id, job_id, full_name, email, phone, portfolio, message, question_answers, candidate_rating, candidate_experience, eligibility_status, eligibility_detail, resume_filename, scan_status, stage, notes, interview_at, interview_time, interview_mode, offer_salary, joining_date, consent_at, created_at FROM $table WHERE id=%d",$id));
        if(!$r) { echo '<div class="wrap"><h1>Application not found</h1></div>';return; }
        $resume=''; // Resume retrieval uses POST + nonce; never expose a GET download token in access logs.
        $can_download=($r->scan_status!=='rejected');
        $scan_label=$can_download?'Private PDF (file type validated; not malware-scanned)':'File rejected';
        $resume_actions=$can_download?self::preview_control('aj_preview_resume',$r->id,'aj_preview_resume_'.$r->id,$r->resume_filename):'';
        if ($can_download) $resume_actions.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="aj-private-download"><input type="hidden" name="action" value="bbs_download_resume"><input type="hidden" name="id" value="'.esc_attr($r->id).'">'.wp_nonce_field('bbs_download_'.$r->id,'_wpnonce',true,false).'<button class="button" type="submit">Download PDF</button></form>';

        echo '<div class="wrap bbs-admin"><a href="'.esc_url(admin_url('admin.php?page=bbs-applicants')).'">← All applications</a><div class="bbs-admin-head"><p>APPLICATION #'.esc_html($id).'</p><h1>'.esc_html($r->full_name).'</h1><span>'.esc_html(get_the_title($r->job_id)).' · '.esc_html(self::job_code($r->job_id)).' · Applied '.esc_html(get_date_from_gmt($r->created_at,'d M Y')).'</span></div><div class="bbs-person-grid"><div class="bbs-panel"><h2>Candidate details</h2><dl><dt>Email</dt><dd><a href="'.esc_url('mailto:'.$r->email).'">'.esc_html($r->email).'</a></dd><dt>Phone</dt><dd>'.esc_html($r->phone?:'Not provided').'</dd><dt>Portfolio</dt><dd>'.($r->portfolio?'<a target="_blank" rel="noopener noreferrer" href="'.esc_url($r->portfolio).'">Open link ↗</a>':'Not provided').'</dd><dt>Resume</dt><dd>'.($can_download?$resume_actions:'<strong>Download locked</strong>').' <small>'.esc_html($r->resume_filename).' — '.esc_html($scan_label).'</small></dd><dt>Application</dt><dd>'.nl2br(esc_html($r->message?:'No message')).'</dd><dt>Consent recorded (UTC)</dt><dd>'.esc_html($r->consent_at).'</dd></dl></div>';
        $details=json_decode((string)$r->eligibility_detail,true);
        if(!is_array($details))$details=array();
        echo '<div class="bbs-panel aj-eligibility-panel"><h2>Eligibility check <span class="aj-eligibility-pill aj-eligibility-'.esc_attr($r->eligibility_status).'">'.esc_html(Achuthan_Eligibility::label($r->eligibility_status)).'</span></h2>';
        echo '<p>This is an HR decision aid based only on declared experience and objective answers. It does not reject, rank, or change the candidate stage.</p>';
        if($r->candidate_experience!==null)echo '<p><strong>Relevant experience declared:</strong> '.esc_html($r->candidate_experience).' years</p>';
        foreach(array('reasons'=>'Not met','unknown'=>'Manual review required') as $key=>$heading) {
            if(!empty($details[$key])&&is_array($details[$key])){
                echo '<h3>'.esc_html($heading).'</h3><ul>';
                foreach($details[$key] as $reason)echo '<li>'.esc_html($reason).'</li>';
                echo '</ul>';
            }
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="aj_recheck_eligibility"><input type="hidden" name="id" value="'.esc_attr($id).'">';
        wp_nonce_field('aj_recheck_eligibility_'.$id);
        echo '<button class="button" type="submit">Re-evaluate against current job requirements</button></form><p class="description">Requirements can change after an application is received. Re-evaluation is manual and audited.</p></div>';
        Achuthan_Questions::render_admin($id,$r->question_answers);
        echo '<div class="bbs-panel"><h2>Recruitment tracking</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bbs_update_applicant"><input type="hidden" name="id" value="'.esc_attr($id).'">';
        wp_nonce_field('bbs_update_'.$id);
        echo '<label><strong>Candidate stage</strong><select name="stage">';
        foreach(self::stages() as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($r->stage,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label><label><strong>Candidate rating (0–5)</strong><input type="number" name="candidate_rating" min="0" max="5" step="1" value="'.esc_attr((int)$r->candidate_rating).'"></label><label><strong>Interview date</strong><input type="date" name="interview_at" value="'.esc_attr($r->interview_at).'" /></label><label><strong>Interview time (24-hour)</strong><input type="time" name="interview_time" value="'.esc_attr($r->interview_time).'" /></label><label><strong>Interview mode</strong><select name="interview_mode">';
        foreach(array(''=>'Not set','In person'=>'In person','Video call'=>'Video call','Phone'=>'Phone') as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($r->interview_mode,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label><label><strong>Offer salary (HR private)</strong><input type="text" maxlength="100" name="offer_salary" value="'.esc_attr($r->offer_salary).'" /></label><label><strong>Proposed joining date</strong><input type="date" name="joining_date" value="'.esc_attr($r->joining_date).'" /></label><label><strong>Private HR notes</strong><textarea rows="12" name="notes">'.esc_textarea($r->notes).'</textarea></label><button class="button button-primary">Save candidate</button></form>';
        echo '<hr><h2>Contact candidate</h2><p>Send a manual message to the candidate. No automated rejection, offer, or interview emails are sent when you change stages.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="aj_candidate_email"><input type="hidden" name="id" value="'.esc_attr($id).'">';
        wp_nonce_field('aj_candidate_email_'.$id);
        echo '<label><strong>Subject</strong><input type="text" name="subject" required maxlength="150" value="Regarding your application to '.esc_attr(self::employer_name()).'"></label><label><strong>Message</strong><textarea name="body" rows="6" maxlength="5000" required></textarea></label><button class="button">Send candidate email</button></form>';
        echo '<hr><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Permanently delete this application, its resume and custom attachments? Other applications by this person will remain. This cannot be undone.\');"><input type="hidden" name="action" value="bbs_delete_applicant"><input type="hidden" name="id" value="'.esc_attr($id).'">';
        wp_nonce_field('bbs_delete_'.$id);
        echo '<button type="submit" class="button button-link-delete">Permanently delete application</button></form></div></div></div>';
        self::preview_modal();
    }
    public static function recheck_eligibility() {
        self::require_hr();self::require_post();
        $id=absint(self::data('id'));
        check_admin_referer('aj_recheck_eligibility_'.$id);
        global $wpdb;$table=self::table();
        $r=$wpdb->get_row($wpdb->prepare("SELECT id,job_id,candidate_experience,question_answers FROM $table WHERE id=%d",$id));
        if(!$r)wp_die('Application not found.','',array('response'=>404));
        $answers=json_decode((string)$r->question_answers,true);
        $result=Achuthan_Eligibility::evaluate($r->job_id,$r->candidate_experience,is_array($answers)?$answers:array());
        $updated=$wpdb->update($table,array('eligibility_status'=>$result['status'],'eligibility_detail'=>wp_json_encode($result),'updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s','%s'),array('%d'));
        if($updated===false)wp_die('Eligibility update failed.','',array('response'=>500));
        self::audit('eligibility_rechecked',$id,$result['status']);
        wp_safe_redirect(admin_url('admin.php?page=bbs-applicants&view='.$id.'&updated=1'),303);exit;
    }
    public static function update_applicant() {
        self::require_hr(); self::require_post(); $id=absint(self::data('id'));
        check_admin_referer('bbs_update_'.$id);
        $stage=self::data('stage'); if(!isset(self::stages()[$stage])) wp_die('Invalid stage.');
        $notes=isset($_POST['notes'])&&is_scalar($_POST['notes'])?sanitize_textarea_field(wp_unslash($_POST['notes'])):'';
        if (strlen($notes)>20000) wp_die('Notes too long.', '', array('response'=>400));
        $date=self::data('interview_at');
        $time=self::data('interview_time');
        $mode=self::data('interview_mode');
        $offer=self::data('offer_salary');
        $join=self::data('joining_date');
        $rating=self::data('candidate_rating','0');
        if(!ctype_digit($rating) || (int)$rating>5) wp_die('Invalid candidate rating.','',array('response'=>400));
        if(!self::valid_date($date) || !self::valid_date($join) ||
           ($time!=='' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)) ||
           !in_array($mode,array('','In person','Video call','Phone'),true) || strlen($offer)>100) wp_die('Invalid recruitment details.');
        global $wpdb;
        $updated=$wpdb->update(self::table(),array('stage'=>$stage,'notes'=>$notes,'candidate_rating'=>(int)$rating,'interview_at'=>$date?:null,'interview_time'=>$time,'interview_mode'=>$mode,'offer_salary'=>$offer,'joining_date'=>$join?:null,'updated_at'=>current_time('mysql',true)),array('id'=>$id));
        if ($updated===false) wp_die('Update failed.','',array('response'=>500));
        self::audit('applicant_updated',$id,'stage:'.$stage);
        wp_safe_redirect(admin_url('admin.php?page=bbs-applicants&view='.$id.'&updated=1'));exit;
    }
    /** Erase exactly one application plus its encrypted answer attachments. Other jobs remain. */
    private static function erase_application($id) {
        global $wpdb;
        $id=absint($id);
        if (!$id) return false;
        if ($wpdb->query('START TRANSACTION')===false) return false;
        $removed=Achuthan_Questions::purge_for($id);
        $deleted=$removed===false ? false : $wpdb->delete(self::table(),array('id'=>$id),array('%d'));
        if ($removed===false || $deleted!==1) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        if ($wpdb->query('COMMIT')===false) return false;
        self::audit('application_deleted',$id);
        return true;
    }
    public static function delete_applicant() {
        self::require_hr(); self::require_post(); $id=absint(self::data('id'));
        check_admin_referer('bbs_delete_'.$id);
        if (!self::erase_application($id)) wp_die('Could not securely delete the application and its files.','',array('response'=>500));
        wp_safe_redirect(admin_url('admin.php?page=bbs-applicants&deleted=1'));exit;
    }
    private static function require_post() {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') wp_die('POST required.','',array('response'=>405));
    }
    public static function rescan_resume() {
        self::require_hr(); self::require_post();
        $id=absint(self::data('id'));
        check_admin_referer('bbs_rescan_'.$id);
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare('SELECT scan_status, resume_data FROM '.self::table().' WHERE id=%d',$id));
        if (!$r) wp_die('Application not found.','',array('response'=>404));
        if ($r->scan_status==='rejected') wp_die('Rejected uploads cannot be rescanned.','',array('response'=>403));
        $pdf=self::decrypt_resume($r->resume_data);
        if ($pdf===false || strlen($pdf)>self::MAX_RESUME) wp_die('Resume decryption failed.','',array('response'=>500));
        $tmp=tempnam(sys_get_temp_dir(),'ajscan-');
        if (!$tmp) wp_die('Unable to prepare scan.','',array('response'=>500));
        $scan='quarantine';
        try {
            if (file_put_contents($tmp,$pdf,LOCK_EX)!==false) {
                @chmod($tmp,0600);
                $scan=self::scan_pdf($tmp);
            }
        } finally { @unlink($tmp); }
        // Only an installed trusted scanner integration can release quarantined resumes.
        $wpdb->update(self::table(),array('scan_status'=>$scan,'updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s'),array('%d'));
        self::audit('resume_rescan',$id,$scan);
        wp_safe_redirect(admin_url('admin.php?page=bbs-applicants&view='.$id.'&rescanned=1'));exit;
    }
    public static function preview_resume() {
        self::require_hr(); self::require_post();
        $id=absint(self::data('id'));
        check_admin_referer('aj_preview_resume_'.$id);
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare('SELECT resume_filename, resume_data, scan_status FROM '.self::table().' WHERE id=%d',$id));
        if (!$r) wp_die('Resume not found.','',array('response'=>404));
        if ($r->scan_status==='rejected') wp_die('Rejected document cannot be previewed.','',array('response'=>403));
        $pdf=self::decrypt_resume($r->resume_data);
        if (!self::valid_pdf_bytes($pdf)) wp_die('Invalid or unavailable private PDF.','',array('response'=>422));
        self::stream_preview($pdf,'application/pdf',$r->resume_filename,$id,'resume_previewed');
    }
    public static function download_resume() {
        self::require_hr(); self::require_post();
        $id=absint(self::data('id'));
        check_admin_referer('bbs_download_'.$id);
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare('SELECT resume_filename, resume_data, scan_status FROM '.self::table().' WHERE id=%d',$id));
        if(!$r) wp_die('Resume not found.','',array('response'=>404));
        if ($r->scan_status==='rejected') wp_die('File previously rejected.','',array('response'=>403));
        $pdf=self::decrypt_resume($r->resume_data);
        if (!self::valid_pdf_bytes($pdf)) wp_die('Unable to decrypt resume securely.','',array('response'=>500));
        $filename=preg_replace('/[^a-zA-Z0-9._-]/','_',basename($r->resume_filename));
        if (!preg_match('/\.pdf$/i',$filename)) $filename='resume.pdf';
        self::audit('resume_downloaded',$id);
        while (ob_get_level()>0) { ob_end_clean(); }
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;exit;
    }

    private static function selected_ids($field, $limit = 100) {
        $raw = isset($_POST[$field]) && is_array($_POST[$field]) ? wp_unslash($_POST[$field]) : array();
        if (count($raw) > $limit) wp_die('Too many records selected.', '', array('response'=>400));
        $ids=array();
        foreach ($raw as $val) {
            if (!is_scalar($val) || !preg_match('/^[1-9][0-9]*$/D',(string)$val)) wp_die('Invalid record ID.', '', array('response'=>400));
            $ids[]=(int)$val;
        }
        return array_values(array_unique($ids));
    }
    private static function csv_safe($val) {
        $val=str_replace(array("\r", "\n", "\0"),array(' ', ' ', ''),(string)$val);
        return preg_match('/^\s*[=+@\-\t]/u',$val) ? "'".$val : $val;
    }
    public static function manage_jobs() {
        if (!current_user_can('edit_bbs_jobs')) wp_die('Insufficient permissions.', '', array('response'=>403));
        $jobs=get_posts(array('post_type'=>self::CPT,'post_status'=>array('publish','draft','pending','private','future'), 'numberposts'=>100,'orderby'=>'date','order'=>'DESC'));
        echo '<div class="wrap bbs-admin"><div class="bbs-admin-head"><p>ACHUTHAN JOBS / BULK MANAGEMENT</p><h1>Manage vacancies</h1><span>Add or edit many vacancies without changing code. Actions apply only to selected jobs.</span></div>';
        if (isset($_GET['count'])) echo '<div class="notice notice-success"><p>Operation completed. Processed '.esc_html(absint($_GET['count'])).' job(s).</p></div>';
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type='.self::CPT)).'">Add single job</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type='.self::CPT)).'">Full WordPress jobs list</a></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return this.elements[\'bulk_action\'].value!==\'trash\' || confirm(\'Move the selected jobs to Trash?\')"><input type="hidden" name="action" value="aj_jobs_bulk">';
        wp_nonce_field('aj_jobs_bulk');
        echo '<p class="aj-bulk-controls"><label><strong>Bulk action</strong> <select required name="bulk_action"><option value="">Choose action</option><option value="publish">Publish</option><option value="draft">Move to draft</option><option value="open">Open applications</option><option value="close">Close applications</option><option value="trash">Move to Trash</option></select></label> <button class="button button-primary" type="submit">Apply to selected</button> <span>Up to 100 at a time.</span></p>';
        echo '<table class="widefat striped bbs-table"><thead><tr><th><input aria-label="Select all jobs" type="checkbox" id="aj-job-select-all"></th><th>Job title</th><th>Job ID</th><th>Publication</th><th>Hiring status</th><th>Deadline</th><th>Manage</th></tr></thead><tbody>';
        foreach($jobs as $j) {
            $status=self::job_meta($j->ID,'status');
            $edit=get_edit_post_link($j->ID);
            echo '<tr><td><input class="aj-job-check" type="checkbox" name="job_ids[]" value="'.esc_attr($j->ID).'" aria-label="Select '.esc_attr($j->post_title).'"></td><td>'.esc_html($j->post_title).'</td><td>'.esc_html(self::job_code($j->ID)).'</td><td>'.esc_html($j->post_status).'</td><td>'.esc_html($status?:'open').'</td><td>'.esc_html(self::job_meta($j->ID,'deadline')?:'—').'</td><td><a href="'.esc_url($edit).'">Edit</a></td></tr>';
        }
        if (!$jobs) echo '<tr><td colspan="7">No jobs yet.</td></tr>';
        echo '</tbody></table></form>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var a=document.getElementById("aj-job-select-all");if(a)a.addEventListener("change",function(){document.querySelectorAll(".aj-job-check").forEach(function(x){x.checked=a.checked;});});});</script>';
        echo '<div class="bbs-panel" style="max-width:850px;margin-top:24px"><h2>Bulk create and update jobs using CSV</h2><p>Upload a UTF-8 CSV (up to 100 rows, 1 MB). Provide <strong>job_id</strong> to update an existing job; leave it blank to create one. New jobs default to draft unless explicitly published by someone with permission. Never upload candidate information here.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data"><input type="hidden" name="action" value="aj_jobs_import">';
        wp_nonce_field('aj_jobs_import');
        echo '<p><label><strong>Jobs CSV file</strong><input required type="file" accept=".csv,text/csv" name="jobs_csv"></label></p><button type="submit" class="button button-primary">Import / update jobs</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="aj_jobs_template">';wp_nonce_field('aj_jobs_template');
        echo '<p><button class="button" type="submit">Download CSV template</button></p></form></div></div>';
    }
    public static function bulk_jobs() {
        self::require_post();
        if(!current_user_can('edit_bbs_jobs')) wp_die('Insufficient permissions.','',array('response'=>403));
        check_admin_referer('aj_jobs_bulk');
        $action=self::data('bulk_action');
        if(!in_array($action,array('publish','draft','open','close','trash'),true)) wp_die('Invalid bulk action.','',array('response'=>400));
        $ids=self::selected_ids('job_ids');
        $count=0;
        foreach($ids as $id) {
            if(get_post_type($id)!==self::CPT) continue;
            $cap=($action==='trash')?'delete_post':'edit_post';
            if(!current_user_can($cap,$id)) continue;
            if($action==='publish' && !current_user_can('publish_bbs_jobs')) continue;
            if($action==='trash') $ok=wp_trash_post($id);
            elseif($action==='publish' || $action==='draft') $ok=wp_update_post(array('ID'=>$id,'post_status'=>$action),true);
            else $ok=update_post_meta($id,'_bbs_status',$action==='close'?'closed':'open');
            if($ok && !is_wp_error($ok)) $count++;
        }
        wp_safe_redirect(add_query_arg('count',$count,admin_url('admin.php?page=aj-manage-jobs')),303);exit;
    }
    private static function csv_header() {
        return array('job_id','title','description','department','location','type','experience','salary','deadline','hiring_status','publish_status');
    }
    public static function download_template() {
        self::require_post();
        if(!current_user_can('edit_bbs_jobs')) wp_die('Insufficient permissions.','',array('response'=>403));
        check_admin_referer('aj_jobs_template');
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="achuthan-jobs-template.csv"');
        $out=fopen('php://output','w'); fputcsv($out,self::csv_header());
        fputcsv($out,array('','Project Coordinator','Coordinate team deliverables.','Operations','Remote','Full-time','1-3 years','','2027-06-30','open','draft'));
        fclose($out);exit;
    }
    public static function import_jobs() {
        self::require_post();
        if(!current_user_can('edit_bbs_jobs')) wp_die('Insufficient permissions.','',array('response'=>403));
        check_admin_referer('aj_jobs_import');
        $f=$_FILES['jobs_csv']??null;
        if(!is_array($f) || ($f['error']??1)!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']??'') || ($f['size']??0)<10 || ($f['size']??0)>self::MAX_CSV_BYTES) wp_die('Invalid CSV upload (1 MB maximum).','',array('response'=>400));
        if (strtolower(pathinfo(sanitize_file_name($f['name']??''),PATHINFO_EXTENSION))!=='csv') wp_die('CSV file required.','',array('response'=>400));
        $handle=fopen($f['tmp_name'],'rb'); if(!$handle) wp_die('Unable to open CSV.','',array('response'=>400));
        try {
            $headers=fgetcsv($handle,0,',','"','');
            if(!is_array($headers) || $headers!==self::csv_header()) wp_die('CSV headers do not match the provided template.','',array('response'=>400));
            $rows=array();$line=1;
            while(($row=fgetcsv($handle,0,',','"',''))!==false) {
                $line++;
                if(count($rows)>=self::MAX_CSV_ROWS) wp_die('CSV row limit exceeded.','',array('response'=>400));
                if(count($row)===1 && trim($row[0])==='') continue;
                if(count($row)!==count($headers)) wp_die('CSV row '.absint($line).' has wrong column count.','',array('response'=>400));
                $row=array_combine($headers,$row);
                $row=array_map(static function($v){return is_string($v)?trim($v):'';},$row);
                if(!preg_match('/^(?:|[1-9][0-9]*)$/D',$row['job_id']) || strlen($row['title'])<3 || strlen($row['title'])>180 || strlen($row['description'])>25000 || strlen($row['description'])<5) wp_die('Invalid job row '.absint($line).': title, description, or ID.','',array('response'=>400));
                if(!self::valid_date($row['deadline']) || !in_array($row['type'],array('Full-time','Part-time','Contract','Internship','Temporary'),true) || !in_array($row['hiring_status'],array('open','closed'),true) || !in_array($row['publish_status'],array('draft','publish'),true)) wp_die('Invalid job row '.absint($line).': type, dates or status.','',array('response'=>400));
                foreach(array('department','location','experience','salary') as $k) if(strlen($row[$k])>190) wp_die('CSV text too long in row '.absint($line).'.','',array('response'=>400));
                if($row['job_id']) {
                    $id=(int)$row['job_id'];
                    if(get_post_type($id)!==self::CPT || !current_user_can('edit_post',$id)) wp_die('You cannot update job ID '.absint($id).'.','',array('response'=>403));
                }
                if($row['publish_status']==='publish' && !current_user_can('publish_bbs_jobs')) wp_die('Publishing permission is required.','',array('response'=>403));
                $rows[]=$row;
            }
        } finally { fclose($handle); }
        $count=0;
        foreach($rows as $row) {
            $post=array('post_type'=>self::CPT,'post_title'=>sanitize_text_field($row['title']),'post_content'=>wp_kses_post($row['description']),'post_status'=>$row['publish_status']);
            if($row['job_id']) $post['ID']=(int)$row['job_id'];
            $id=isset($post['ID'])?wp_update_post($post,true):wp_insert_post($post,true);
            if(is_wp_error($id)|| !$id) continue;
            foreach(array('department','location','type','experience','salary','deadline') as $k) update_post_meta($id,'_bbs_'.$k,sanitize_text_field($row[$k]));
            update_post_meta($id,'_bbs_status',$row['hiring_status']);$count++;
        }
        wp_safe_redirect(add_query_arg('count',$count,admin_url('admin.php?page=aj-manage-jobs')),303);exit;
    }
    public static function bulk_candidates() {
        self::require_hr();self::require_post();check_admin_referer('aj_candidates_bulk');
        $action=self::data('bulk_action');$ids=self::selected_ids('candidate_ids');
        if($action==='download_files')self::download_bulk_files($ids);
        if($action!=='delete' && (!str_starts_with($action,'stage_') || !isset(self::stages()[substr($action,6)]))) wp_die('Invalid candidate action.','',array('response'=>400));
        global $wpdb; $count=0;
        foreach($ids as $id) {
            if($action==='delete') {
                if (self::erase_application($id)) $count++;
                continue;
            }
            $ok=$wpdb->update(self::table(),array('stage'=>substr($action,6),'updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s'),array('%d'));
            if($ok!==false && $ok>0){$count++;self::audit('stage_changed',$id,$action);}
        }
        wp_safe_redirect(add_query_arg('count',$count,admin_url('admin.php?page=bbs-applicants')),303);exit;
    }
    private static function zip_name($value) {
        $name=preg_replace('/[^a-zA-Z0-9._-]/','_',basename((string)$value));
        return trim($name,'._')?:'document';
    }
    public static function download_bulk_files($ids) {
        self::require_hr();self::require_post();
        if(!$ids || count($ids)>20)wp_die('Select 1–20 candidates for a ZIP download.','',array('response'=>400));
        if(!class_exists('ZipArchive'))wp_die('PHP ZipArchive extension is required for bulk downloads.','',array('response'=>503));
        $path=tempnam(sys_get_temp_dir(),'ajzip-');
        if(!$path)wp_die('Unable to create a temporary ZIP.','',array('response'=>500));
        @chmod($path,0600);$zip=new ZipArchive();$bytes=0;$count=0;$oversize=false;
        if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){@unlink($path);wp_die('Unable to open ZIP.','',array('response'=>500));}
        try{
            global $wpdb;$table=self::table();
            foreach($ids as $id){
                $r=$wpdb->get_row($wpdb->prepare("SELECT id,job_id,resume_filename,resume_data,scan_status FROM $table WHERE id=%d",$id));
                if(!$r)continue;
                $prefix=self::zip_name(self::job_code($r->job_id)).'/candidate-'.(int)$r->id.'/';
                if($r->scan_status!=='rejected'){
                    $pdf=self::decrypt_resume($r->resume_data);
                    if(self::valid_pdf_bytes($pdf)){
                        $bytes+=strlen($pdf);if($bytes>83886080){$oversize=true;break;}
                        $zip->addFromString($prefix.'resume-'.self::zip_name($r->resume_filename),$pdf);$count++;
                    }
                }
                foreach(Achuthan_Questions::downloadable_files((int)$r->id) as $f){
                    $bytes+=strlen($f['data']);if($bytes>83886080){$oversize=true;break;}
                    $zip->addFromString($prefix.'question-'.self::zip_name($f['question_id']).'-'.(int)$f['id'].'-'.self::zip_name($f['filename']),$f['data']);$count++;
                }
                if($oversize)break;
                self::audit('bulk_files_exported',(int)$r->id);
            }
        } finally {$zip->close();}
        if($oversize){@unlink($path);wp_die('Selection exceeds 80 MB. Choose fewer candidates.','',array('response'=>413));}
        if(!$count){@unlink($path);wp_die('No valid downloadable files found.','',array('response'=>404));}
        while(ob_get_level()>0)ob_end_clean();nocache_headers();
        header('X-Content-Type-Options: nosniff');header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="achuthan-jobs-files-'.gmdate('Ymd-His').'.zip"');
        header('Content-Length: '.filesize($path));
        readfile($path);@unlink($path);exit;
    }
    public static function candidate_email() {
        self::require_hr();self::require_post();
        $id=absint(self::data('id'));check_admin_referer('aj_candidate_email_'.$id);
        $subject=self::data('subject');
        $body=isset($_POST['body'])&&is_scalar($_POST['body'])?sanitize_textarea_field(wp_unslash($_POST['body'])):'';
        if(!$subject || !$body || strlen($subject)>150 || strlen($body)>5000 || preg_match('/[\r\n]/',$subject)) wp_die('Invalid email contents.','',array('response'=>400));
        if(self::rate_limited('hr-mail|'.get_current_user_id(),20,HOUR_IN_SECONDS)) wp_die('Email sending limit reached.','',array('response'=>429));
        global $wpdb;
        $recipient=$wpdb->get_var($wpdb->prepare('SELECT email FROM '.self::table().' WHERE id=%d',$id));
        if(!$recipient || !is_email($recipient)) wp_die('Applicant not found.','',array('response'=>404));
        if(!wp_mail($recipient,$subject,$body)) wp_die('Email delivery failed; check SMTP.','',array('response'=>502));
        self::audit('candidate_emailed',$id);
        if(self::data('return_to')==='dashboard') {
            wp_safe_redirect(add_query_arg('email_sent','1',admin_url('admin.php?page=bbs-recruitment')),303);
        } else {
            wp_safe_redirect(admin_url('admin.php?page=bbs-applicants&view='.$id.'&updated=1'),303);
        }
        exit;
    }
    public static function export_candidates() {
        self::require_hr();self::require_post();check_admin_referer('aj_candidates_export');
        global $wpdb;
        $table=self::table();
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="achuthan-jobs-applicants-'.gmdate('Ymd').'.csv"');
        $out=fopen('php://output','w');
        fputcsv($out,array('ID','Job ID','Job','Full name','Email','Phone','Portfolio','Stage','Eligibility','Experience (years)','Rating','Interview date','Application answers (JSON)','Applied at UTC'));
        for($offset=0;$offset<10000;$offset+=200) {
            $rows=$wpdb->get_results($wpdb->prepare("SELECT id,job_id,full_name,email,phone,portfolio,stage,eligibility_status,candidate_experience,candidate_rating,interview_at,question_answers,created_at FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",200,$offset));
            foreach((array)$rows as $r) {
                $vals=array($r->id,self::job_code($r->job_id),get_the_title($r->job_id),$r->full_name,$r->email,$r->phone,$r->portfolio,$r->stage,$r->eligibility_status,$r->candidate_experience,$r->candidate_rating,$r->interview_at,$r->question_answers,$r->created_at);
                fputcsv($out,array_map(array(__CLASS__,'csv_safe'),$vals));
            }
            if(count((array)$rows)<200) break;
        }
        fclose($out);exit;
    }

    public static function settings() {
        self::require_hr();
        $email=get_option('bbs_hr_email',get_option('admin_email'));
        $days=(int)get_option('bbs_retention_days',180);
        echo '<div class="wrap bbs-admin"><div class="bbs-admin-head"><p>ACHUTHAN JOBS / SETTINGS</p><h1>Recruitment settings</h1></div><div class="bbs-panel" style="max-width:640px"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bbs_save_settings">';
        wp_nonce_field('bbs_save_settings');
        echo '<label><strong>Employer / hiring organisation name</strong><input type="text" name="employer_name" maxlength="150" required value="'.esc_attr(self::employer_name()).'"></label>';
        echo '<label><strong>Country (ISO two-letter code, optional)</strong><input type="text" maxlength="2" name="employer_country" placeholder="US, IN, GB, AU..." value="'.esc_attr(self::country()).'"></label>';
        echo '<label><strong>Recruitment notification email</strong><input type="email" name="hr_email" value="'.esc_attr($email).'" required></label><label><strong>Candidate data retention (days)</strong><input type="number" min="30" max="1095" name="retention_days" value="'.esc_attr($days).'" required></label><p><label><input type="checkbox" name="aj_own_careers_page" value="1" '.checked(get_option('aj_own_careers_page','1'),'1',false).'> <strong>Automatically control /careers/ with Achuthan Jobs</strong></label></p><p class="description">Recommended and enabled by default. Published jobs always come from the plugin database, even if the previous Careers page used static Elementor sections. Disable this option only if you want to use the [achuthan_jobs] shortcode in Elementor yourself.</p><p>Old applications and their resumes are permanently deleted by the daily WordPress scheduled task. WordPress cron relies on site traffic unless a server cron is configured.</p><button class="button button-primary">Save settings</button></form></div><p><strong>Live Careers URL:</strong> <a href="'.esc_url(home_url('/careers/')).'" target="_blank" rel="noopener">'.esc_html(home_url('/careers/')).'</a>. The WordPress page with slug <code>careers</code> must exist.</p><p><strong>Manual Elementor shortcode:</strong> <code>[achuthan_jobs]</code> (legacy <code>[bbs_careers]</code> also works) — used only if automatic Careers control is turned off.</p><p><strong>Privacy:</strong> Configure a Privacy Policy in Settings → Privacy, update recruitment data handling, and configure SMTP.</p></div>';
    }
    public static function save_settings() {
        self::require_hr();self::require_post();check_admin_referer('bbs_save_settings');
        $email=sanitize_email(self::data('hr_email'));
        $days=absint(self::data('retention_days'));
        if(!is_email($email) || $days<30 || $days>1095) wp_die('Invalid settings.');
        $employer=trim(self::data('employer_name'));
        $country=strtoupper(self::data('employer_country'));
        if(!$employer||strlen($employer)>150||($country!==''&&!preg_match('/^[A-Z]{2}$/D',$country)))wp_die('Invalid employer details.','',array('response'=>400));
        update_option('aj_employer_name',$employer);update_option('aj_employer_country',$country);
        update_option('bbs_hr_email',$email);update_option('bbs_retention_days',$days);
        update_option('aj_own_careers_page',isset($_POST['aj_own_careers_page']) && $_POST['aj_own_careers_page']==='1' ? '1':'0');
        wp_safe_redirect(admin_url('admin.php?page=bbs-recruitment-settings&saved=1'));exit;
    }
    public static function cleanup() {
        global $wpdb;
        $days=max(30,min(1095,(int)get_option('bbs_retention_days',180)));
        $wpdb->query($wpdb->prepare('DELETE FROM '.self::table().' WHERE created_at < %s',gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS)));
        Achuthan_Questions::prune_orphans();
        // Audit events are intentionally devoid of PII; purge old events too.
        $wpdb->query($wpdb->prepare('DELETE FROM '.$wpdb->prefix.'bbs_application_audit WHERE created_at < %s',gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS)));
    }
    public static function admin_notice() {
        if (!current_user_can(self::CAP)) return;
        if (!get_privacy_policy_url() && isset($_GET['page']) && strpos(sanitize_key(wp_unslash($_GET['page'])),'bbs-')===0) {
            echo '<div class="notice notice-warning"><p><strong>Achuthan Jobs:</strong> Set a WordPress Privacy Policy page and add recruitment data handling information before accepting applications.</p></div>';
        }
        if(isset($_GET['updated']) || isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>Saved successfully.</p></div>';
        if (isset($_GET['page']) && strpos(sanitize_key(wp_unslash($_GET['page'])),'bbs-')===0) {
            // No antivirus service is required. Files remain type-validated and encrypted; site owner accepts residual malware risk.
            if (get_option('achuthan_jobs_mail_failed_at')) echo '<div class="notice notice-warning"><p><strong>Achuthan Jobs:</strong> At least one recruitment email failed to dispatch. Configure and test a transactional SMTP service.</p></div>';
        }
    }
    public static function job_schema() {
        if(!is_singular(self::CPT)) return;
        $id=get_queried_object_id();if(!self::open($id))return;
        $type=self::job_meta($id,'type');
        $types=array('Full-time'=>'FULL_TIME','Part-time'=>'PART_TIME','Contract'=>'CONTRACTOR','Internship'=>'INTERN','Temporary'=>'TEMPORARY');
        $data=array('@context'=>'https://schema.org','@type'=>'JobPosting','title'=>get_the_title($id),
            'description'=>wp_strip_all_tags(get_post_field('post_content',$id)),
            'identifier'=>array('@type'=>'PropertyValue','name'=>self::employer_name(),'value'=>self::job_code($id)),
            'datePosted'=>get_the_date('c',$id),
            'hiringOrganization'=>array('@type'=>'Organization','name'=>self::employer_name(),'sameAs'=>home_url('/')),
            'employmentType'=>$types[$type]??'FULL_TIME',
            'directApply'=>true,
        );
        $location=self::job_meta($id,'location');
        if ($location) { $address=array('@type'=>'PostalAddress','addressLocality'=>$location);
            if(self::country()) $address['addressCountry']=self::country();
            $data['jobLocation']=array('@type'=>'Place','address'=>$address); }
        $deadline=self::job_meta($id,'deadline');
        if($deadline) { $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$deadline.' 23:59:59',wp_timezone()); if($dt) $data['validThrough']=$dt->format('c'); }
        echo '<script type="application/ld+json">'.wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
    }
    public static function privacy_eraser_register($erasers) {
        $erasers['bbs-applications']=array('eraser_friendly_name'=>'Achuthan job applications','callback'=>array(__CLASS__,'privacy_erase'));
        return $erasers;
    }
    public static function privacy_erase($email,$page=1) {
        global $wpdb;
        $ids=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.self::table().' WHERE email=%s',sanitize_email($email)));
        $deleted=$wpdb->delete(self::table(),array('email'=>sanitize_email($email)),array('%s'));
        if ($deleted!==false) foreach ((array)$ids as $id) Achuthan_Questions::purge_for((int)$id);
        return array('items_removed'=>$deleted!==false && $deleted>0,'items_retained'=>false,'messages'=>array(),'done'=>true);
    }
    public static function privacy_export_register($exporters) {
        $exporters['bbs-applications']=array('exporter_friendly_name'=>'Achuthan job applications','callback'=>array(__CLASS__,'privacy_export'));
        return $exporters;
    }
    public static function privacy_export($email,$page=1) {
        global $wpdb;$table=self::table();$limit=20;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,job_id,full_name,email,phone,portfolio,message,question_answers,resume_filename,stage,eligibility_status,candidate_experience,created_at FROM $table WHERE email=%s ORDER BY id LIMIT %d OFFSET %d",sanitize_email($email),$limit,($page-1)*$limit));
        $data=array();
        foreach($rows as $r) {
            $data[]=array('group_id'=>'bbs-job-applications','group_label'=>'Job applications',
                'item_id'=>'bbs-application-'.$r->id,'data'=>array(
                    array('name'=>'Job','value'=>get_the_title($r->job_id)),array('name'=>'Name','value'=>$r->full_name),
                    array('name'=>'Email','value'=>$r->email),array('name'=>'Phone','value'=>$r->phone),
                    array('name'=>'Portfolio','value'=>$r->portfolio),array('name'=>'Application message','value'=>$r->message),
                    array('name'=>'Resume filename (resume stored privately; request a copy from HR)','value'=>$r->resume_filename),
                    array('name'=>'Applied at (UTC)','value'=>$r->created_at),
                    array('name'=>'Custom application answers','value'=>$r->question_answers),
                    array('name'=>'Candidate-declared experience (years)','value'=>(string)$r->candidate_experience),
                    array('name'=>'Rule-based HR eligibility flag','value'=>Achuthan_Eligibility::label($r->eligibility_status)),
                ));
        }
        return array('data'=>$data,'done'=>count($rows)<$limit);
    }
}
require_once __DIR__.'/includes/class-achuthan-questions.php';
require_once __DIR__.'/includes/class-achuthan-eligibility.php';
register_activation_hook(__FILE__,array('Achuthan_Jobs','activate'));
register_deactivation_hook(__FILE__,array('Achuthan_Jobs','deactivate'));
Achuthan_Jobs::init();
