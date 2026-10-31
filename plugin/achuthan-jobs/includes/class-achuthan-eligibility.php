<?php
/** Transparent, rule-based screening support. This class NEVER rejects or ranks candidates automatically. */
if (!defined('ABSPATH')) exit;
final class Achuthan_Eligibility {
    const META_MIN_YEARS = '_aj_min_years';
    public static function min_years($job_id) {
        $v = get_post_meta($job_id, self::META_MIN_YEARS, true);
        return is_numeric($v) && (float)$v >= 0 ? (float)$v : 0.0;
    }
    public static function rules($job_id) {
        $out = array();
        foreach (Achuthan_Questions::questions($job_id) as $q) {
            if (empty($q['screen'])) continue;
            if (!in_array($q['type'],array('select','radio','checkbox','number'),true)) continue;
            $out[] = array('id'=>$q['id'],'label'=>$q['label'],'type'=>$q['type'],
                'accepted'=>isset($q['accepted']) && is_array($q['accepted'])?$q['accepted']:array(),
                'minimum'=>isset($q['minimum']) ? (float)$q['minimum'] : 0.0);
        }
        return $out;
    }
    /** Pure evaluation for auditability and unit tests. No AI, inference or protected attributes. */
    public static function evaluate_rules($minimum_years, $rules, $years, $answers) {
        $reasons = array(); $unknown = array(); $checks = 0;
        $minimum_years = max(0, (float)$minimum_years);
        if ($minimum_years > 0) {
            ++$checks;
            if ($years === null || $years === '' || !is_numeric($years)) {
                $unknown[] = 'Relevant years of experience not provided';
            } elseif ((float)$years < $minimum_years) {
                $reasons[] = 'Relevant experience: '.(float)$years.' years; minimum required: '.$minimum_years.' years';
            }
        }
        $submitted = array();
        foreach ((array)$answers as $answer) {
            if (is_array($answer) && isset($answer['id'])) $submitted[(string)$answer['id']] = $answer['value'] ?? '';
        }
        foreach ((array)$rules as $rule) {
            if (!is_array($rule) || !isset($rule['id'],$rule['label'],$rule['type'])) continue;
            $kind = $rule['type']; $label = (string)$rule['label'];
            if (!in_array($kind,array('select','radio','checkbox','number'),true)) continue;
            $allowed = array_values(array_filter((array)($rule['accepted']??array()),'is_scalar'));
            if ($kind !== 'number' && !$allowed) continue; // never assess a misconfigured rule
            ++$checks;
            $value = $submitted[(string)$rule['id']] ?? null;
            if ($value === null || $value === '' || $value === array()) {
                $unknown[] = $label.': answer not provided';
            } elseif ($kind === 'number') {
                if (!is_scalar($value) || !is_numeric($value)) $unknown[] = $label.': answer is not numeric';
                elseif ((float)$value < (float)($rule['minimum']??0)) $reasons[] = $label.': below minimum of '.(float)($rule['minimum']??0);
            } elseif ($kind === 'checkbox') {
                $selected = is_array($value)?$value:array();
                if (!array_intersect($allowed,$selected)) $reasons[] = $label.': no qualifying option selected';
            } else {
                if (!is_scalar($value)) $unknown[] = $label.': answer format needs review';
                elseif (!in_array((string)$value,$allowed,true)) $reasons[] = $label.': selected answer does not meet configured requirement';
            }
        }
        if (!$checks) $status='not_configured';
        elseif ($reasons) $status='not_met';
        elseif ($unknown) $status='review';
        else $status='meets';
        return array('status'=>$status,'reasons'=>array_slice($reasons,0,20),'unknown'=>array_slice($unknown,0,20),'checks'=>$checks);
    }
    public static function evaluate($job_id,$years,$answers) {
        return self::evaluate_rules(self::min_years($job_id),self::rules($job_id),$years,$answers);
    }
    public static function labels() {return array('not_met'=>'Requirements not met','review'=>'Needs HR review','meets'=>'Meets stated requirements','not_configured'=>'Not configured','unassessed'=>'Not yet assessed');}
    public static function label($status) { $labels=self::labels();return $labels[$status]??$labels['unassessed']; }
    public static function applicant_field($job_id) {
        $min=self::min_years($job_id);
        if ($min<=0) return '';
        return '<label class="aj-experience-field">Years of relevant experience for '.esc_html(get_the_title($job_id)).' (minimum '.esc_html(number_format($min,1)).' years)<input type="number" min="0" max="70" step="0.5" inputmode="decimal" name="aj_years['.esc_attr($job_id).']" placeholder="e.g. 1.5" aria-describedby="aj-screening-help-'.esc_attr($job_id).'"></label><p id="aj-screening-help-'.esc_attr($job_id).'" class="aj-screening-help">Answer accurately. Applications are reviewed by HR even if you do not meet every requirement.</p>';
    }
}
