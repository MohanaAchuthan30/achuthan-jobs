<?php
/** Isolated Elementor API contract test. NOT a WordPress integration test. */
namespace Elementor {
    abstract class Widget_Base {
        public array $controls = [];
        public array $sections = [];
        protected array $settings = [];
        protected function start_controls_section($id,$args) { $this->sections[$id] = $args; }
        protected function end_controls_section() {}
        protected function add_control($name,$options) { $this->controls[$name] = $options; }
        protected function add_responsive_control($name,$options) { $this->controls[$name] = $options + ['responsive'=>true]; }
        public function get_settings_for_display() { return $this->settings; }
        public function set_test_settings($settings) { $this->settings = $settings; }
        public function setup_test_controls() { $this->register_controls(); }
        public function render_test() { $this->render(); }
    }
    final class Controls_Manager {
        const TAB_CONTENT='content', TAB_STYLE='style', SELECT='select', SWITCHER='switcher',
              NUMBER='number', TEXT='text', TEXTAREA='textarea', COLOR='color',
              SLIDER='slider', DIMENSIONS='dimensions';
    }
}
namespace {
    define('ABSPATH', '/mock-wordpress/');
    final class Achuthan_Jobs {
        public static array $received=[];
        public static function render_elementor_list($settings) {
            self::$received=$settings;
            return '<section class="achuthan-example">Widget rendered</section>';
        }
    }
    $checks=0;
    function must($result,$label){global $checks;if(!$result)throw new \RuntimeException($label);$checks++;echo "PASS: $label\n";}
    require_once dirname(__DIR__).'/includes/class-achuthan-elementor-widget.php';
    $w=new \Achuthan_Jobs_Elementor_Widget();
    must($w->get_name()==='achuthan_jobs_list','Native widget has persistent Elementor ID');
    must(str_contains($w->get_title(),'Achuthan Jobs'),'Widget displays as Achuthan Jobs');
    must(in_array('general',$w->get_categories(),true),'Widget available in Elementor standard panel');
    must(in_array('achuthan-jobs',$w->get_categories(),true),'Widget listed under Achuthan Jobs category');
    must(in_array('achuthan-jobs-front',$w->get_style_depends(),true),'Frontend style registered as widget dependency');
    must(in_array('achuthan-jobs-front',$w->get_script_depends(),true),'Frontend script registered as widget dependency');
    $w->setup_test_controls();
    $controls=$w->controls;
    must(isset($controls['layout']['options']['grid'],$controls['layout']['options']['list'],$controls['layout']['options']['compact'],$controls['layout']['options']['featured']),'Four distinct layouts selectable');
    must(!empty($controls['columns']['responsive']),'Responsive column picker present');
    must(isset($controls['heading'],$controls['filters'],$controls['department'],$controls['job_type'],$controls['order'],$controls['limit']),'Listing and filter controls present');
    must(isset($controls['show_job_id'],$controls['show_meta'],$controls['show_excerpt']),'Job field visibility controls present');
    must(isset($controls['card_background'],$controls['card_hover_background'],$controls['text_color'],$controls['accent']),'Native color controls present');
    must(isset($controls['gap'],$controls['radius'],$controls['card_padding']),'Native spacing and radius controls present');
    $w->set_test_settings(['layout'=>'compact','columns'=>'3','filters'=>'yes','show_excerpt'=>'yes']);
    ob_start();$w->render_test();$html=ob_get_clean();
    must(str_contains($html,'Widget rendered'),'Elementor render callback produces listing');
    must(Achuthan_Jobs::$received['layout']==='compact','Elementor settings passed to core renderer');
    echo "ALL $checks NATIVE ELEMENTOR WIDGET MOCK ASSERTIONS PASSED.\n";
}
