<?php
defined('ABSPATH') || exit;
get_header();
?>
<main class="site-main--content"><div class="cai-container"><div class="page-header"><p class="eyebrow">404</p><h1><?php esc_html_e('Questa pagina ha perso il filo.', 'commerciale-ai'); ?></h1><p><?php esc_html_e('Torniamo alla pagina principale e riprendiamo da lì.', 'commerciale-ai'); ?></p><a class="cai-button cai-button--coral" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Torna alla home', 'commerciale-ai'); ?></a></div></div></main>
<?php get_footer(); ?>
