#!/usr/bin/env python3
"""Static rebranding regression assertions; not a WordPress functional test."""
from pathlib import Path
root=Path(__file__).resolve().parent.parent
plugin=(root/'plugin/achuthan-jobs/achuthan-jobs.php').read_text()
job=(root/'plugin/achuthan-jobs/templates/single-bbs_job.php').read_text()
assert ' * Plugin Name: Achuthan Jobs' in plugin
assert ' * Author: Achuthan' in plugin
assert "const VERSION = '0.13.0'" in plugin
assert 'BIG BOX STUDIO' not in plugin + job
assert 'Big Box Studio' not in plugin + job
assert 'self::employer_name()' in plugin
assert 'self::employer_name()' in plugin
assert 'esc_html(self::employer_name())' in plugin
assert 'Achuthan_Jobs::employer_name()' in job
assert "const CPT = 'bbs_job'" in plugin
assert "'rewrite' => array('slug'=>'careers'" in plugin
assert "add_shortcode('achuthan_jobs'" in plugin
assert "add_shortcode('bbs_careers'" in plugin
assert "get_option('bbs_hr_email'" in plugin
print('Achuthan branding and upgrade identifiers: PASS (static assertions)')

assert "const META_MIN_YEARS = '_aj_min_years';" in (root/'plugin/achuthan-jobs/includes/class-achuthan-eligibility.php').read_text()
