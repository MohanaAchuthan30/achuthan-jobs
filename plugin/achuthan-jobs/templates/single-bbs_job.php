<?php
/** Dedicated job template: not a page, blog entry, or theme portfolio item. */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main id="primary" class="aj-native-job-main" role="main">
<?php while (have_posts()) : the_post(); ?>
<article <?php post_class('aj-native-job'); ?> id="job-<?php the_ID(); ?>">
  <header class="aj-native-job-header">
    <p class="aj-native-job-label"><?php echo esc_html(Achuthan_Jobs::employer_name()); ?> / CAREERS / JOB OPENING</p>
    <h1><?php the_title(); ?></h1>
    <p>Explore the role below and submit your application directly to our recruitment team.</p>
  </header>
  <div class="aj-native-job-content"><?php the_content(); ?></div>
</article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
