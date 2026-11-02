<?php
/**
 * Native Elementor widget for live Achuthan Jobs listings.
 * Loaded only after Elementor has registered its Widget_Base class.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Achuthan_Jobs_Elementor_Widget extends \Elementor\Widget_Base {
    public function get_name(): string { return 'achuthan_jobs_list'; }
    public function get_title(): string { return 'Achuthan Jobs — Job Listings'; }
    public function get_icon(): string { return 'eicon-post-list'; }
    public function get_categories(): array { return array( 'achuthan-jobs', 'general' ); }
    public function get_keywords(): array { return array( 'achuthan', 'jobs', 'careers', 'vacancies', 'recruitment', 'hr' ); }
    public function get_style_depends(): array { return array( 'achuthan-jobs-poppins', 'achuthan-jobs-front' ); }
    public function get_script_depends(): array { return array( 'achuthan-jobs-front' ); }

    protected function register_controls(): void {
        $C = '\\Elementor\\Controls_Manager';
        $this->start_controls_section( 'aj_content', array( 'label' => 'Job listings', 'tab' => $C::TAB_CONTENT ) );
        $this->add_control( 'layout', array(
            'label'=>'Layout style', 'type'=>$C::SELECT, 'default'=>'grid',
            'options'=>array(
                'grid'=>'Cards grid', 'list'=>'Full-width list',
                'compact'=>'Compact tiles', 'featured'=>'Editorial cards',
            ),
        ) );
        $this->add_responsive_control( 'columns', array(
            'label'=>'Columns', 'type'=>$C::SELECT,
            'options'=>array( '1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4' ),
            'default'=>'3', 'tablet_default'=>'2', 'mobile_default'=>'1',
            'condition'=>array('layout!'=>'list'),
        ) );
        $this->add_control( 'limit', array(
            'label'=>'Maximum jobs', 'type'=>$C::NUMBER, 'default'=>9,
            'min'=>0, 'max'=>50, 'step'=>1,
            'description'=>'0 means all open positions.',
        ) );
        $this->add_control( 'department', array(
            'label'=>'Department (optional)', 'type'=>$C::TEXT, 'default'=>'',
            'description'=>'Exact department name from the job editor. Leave blank to show all.',
        ) );
        $this->add_control( 'job_type', array(
            'label'=>'Employment type (optional)', 'type'=>$C::SELECT, 'default'=>'',
            'options'=>array(''=>'All types','Full-time'=>'Full-time','Part-time'=>'Part-time','Contract'=>'Contract','Internship'=>'Internship','Temporary'=>'Temporary'),
        ) );
        $this->add_control( 'order', array(
            'label'=>'Sort jobs', 'type'=>$C::SELECT, 'default'=>'DESC',
            'options'=>array('DESC'=>'Newest first','ASC'=>'Oldest first'),
        ) );
        $this->add_control( 'heading', array('label'=>'Show section heading', 'type'=>$C::SWITCHER,'label_on'=>'Yes','label_off'=>'No','return_value'=>'yes','default'=>'yes') );
        $this->add_control( 'heading_text', array('label'=>'Heading text','type'=>$C::TEXT,'default'=>'Open positions','condition'=>array('heading'=>'yes')) );
        $this->add_control( 'subheading_text', array('label'=>'Intro text','type'=>$C::TEXTAREA,'default'=>'Explore our current job openings.','condition'=>array('heading'=>'yes')) );
        $this->add_control( 'filters', array('label'=>'Show search and filters','type'=>$C::SWITCHER,'label_on'=>'Yes','label_off'=>'No','return_value'=>'yes','default'=>'yes') );
        $this->add_control( 'show_job_id', array('label'=>'Show Job ID','type'=>$C::SWITCHER,'return_value'=>'yes','default'=>'yes') );
        $this->add_control( 'show_meta', array('label'=>'Show job details','type'=>$C::SWITCHER,'return_value'=>'yes','default'=>'yes') );
        $this->add_control( 'show_excerpt', array('label'=>'Show short description','type'=>$C::SWITCHER,'return_value'=>'yes','default'=>'no') );
        $this->end_controls_section();

        $this->start_controls_section('aj_style',array('label'=>'Card appearance','tab'=>$C::TAB_STYLE));
        $this->add_control('card_background',array(
            'label'=>'Card background','type'=>$C::COLOR,'default'=>'#ffffff',
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--aj-card-bg: {{VALUE}};'),
        ));
        $this->add_control('card_hover_background',array(
            'label'=>'Hover background','type'=>$C::COLOR,'default'=>'#f8faed',
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--aj-hover-bg: {{VALUE}};'),
        ));
        $this->add_control('featured_background',array(
            'label'=>'Featured card background','type'=>$C::COLOR,'default'=>'#f9fbe8',
            'condition'=>array('layout'=>'featured'),
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--aj-featured-bg: {{VALUE}};'),
        ));
        $this->add_control('accent',array(
            'label'=>'Accent color','type'=>$C::COLOR,'default'=>'#e3ff04',
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--bbs-lime: {{VALUE}};'),
        ));
        $this->add_control('text_color',array(
            'label'=>'Title color','type'=>$C::COLOR,
            'selectors'=>array('{{WRAPPER}} .aj-widget .bbs-job-card h3'=>'color: {{VALUE}};'),
        ));
        $this->add_responsive_control('gap',array(
            'label'=>'Card spacing','type'=>$C::SLIDER,
            'size_units'=>array('px'), 'range'=>array('px'=>array('min'=>0,'max'=>60)),
            'default'=>array('unit'=>'px','size'=>18),
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--aj-gap: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_responsive_control('radius',array(
            'label'=>'Corner radius','type'=>$C::SLIDER,
            'size_units'=>array('px'), 'range'=>array('px'=>array('min'=>0,'max'=>40)),
            'default'=>array('unit'=>'px','size'=>12),
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--aj-radius: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_responsive_control('card_padding',array(
            'label'=>'Card padding','type'=>$C::DIMENSIONS,'size_units'=>array('px'),
            'selectors'=>array('{{WRAPPER}} .aj-widget'=>'--aj-card-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'),
        ));
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        // All values are revalidated by the core listing renderer and AJAX endpoint.
        echo Achuthan_Jobs::render_elementor_list( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
