<?php defined('ABSPATH') || exit; ?>
<footer class="site-footer">
    <div class="cai-container">
        <div class="footer-grid footer-grid--wide">
            <div>
                <div class="footer-brand"><?php bloginfo('name'); ?></div>
                <p><?php esc_html_e('Raccoglie, comprende e prepara il prossimo passo. Le decisioni commerciali restano alle persone.', 'commerciale-ai'); ?></p>
            </div>
            <div>
                <h3><?php esc_html_e('Prodotto', 'commerciale-ai'); ?></h3>
                <ul><li><a href="<?php echo esc_url(cai_page_url('prodotto')); ?>"><?php esc_html_e('Funzionalità', 'commerciale-ai'); ?></a></li><li><a href="<?php echo esc_url(cai_page_url('come-funziona')); ?>"><?php esc_html_e('Come funziona', 'commerciale-ai'); ?></a></li><li><a href="<?php echo esc_url(cai_page_url('prezzi')); ?>"><?php esc_html_e('Prezzi', 'commerciale-ai'); ?></a></li></ul>
            </div>
            <div>
                <h3><?php esc_html_e('Per chi', 'commerciale-ai'); ?></h3>
                <ul><li><a href="<?php echo esc_url(cai_page_url('professionisti')); ?>"><?php esc_html_e('Professionisti', 'commerciale-ai'); ?></a></li><li><a href="<?php echo esc_url(cai_page_url('team-commerciali')); ?>"><?php esc_html_e('Team commerciali', 'commerciale-ai'); ?></a></li><li><a href="<?php echo esc_url(cai_page_url('agenzie-b2b')); ?>"><?php esc_html_e('Agenzie e B2B', 'commerciale-ai'); ?></a></li></ul>
            </div>
            <div>
                <h3><?php esc_html_e('Assistenza', 'commerciale-ai'); ?></h3>
                <ul><li><a href="<?php echo esc_url(cai_page_url('faq')); ?>"><?php esc_html_e('Domande frequenti', 'commerciale-ai'); ?></a></li><li><a href="<?php echo esc_url(cai_page_url('contatti')); ?>"><?php esc_html_e('Contatti', 'commerciale-ai'); ?></a></li><li><a href="<?php echo esc_url(cai_account_url()); ?>"><?php esc_html_e('Area cliente', 'commerciale-ai'); ?></a></li></ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span>
            <span><?php esc_html_e('Dati protetti · Pagamenti sicuri con Stripe', 'commerciale-ai'); ?></span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
