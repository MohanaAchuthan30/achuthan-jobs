<?php
/** Live, plugin-managed careers page. No Elementor/static listing involved. */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main id="primary" class="aj-careers-native-page" role="main">
    <?php echo Achuthan_Jobs::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by shortcode methods. ?>
</main>
<?php get_footer(); ?>
