<?php defined('ABSPATH') || exit; ?>
<footer class="site-footer">
    <div class="cai-container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand"><?php bloginfo('name'); ?></div>
                <p><?php esc_html_e('Il commerciale digitale che raccoglie, organizza e trasforma ogni opportunità in una prossima azione concreta.', 'commerciale-ai'); ?></p>
            </div>
            <div>
                <h3><?php esc_html_e('Prodotto', 'commerciale-ai'); ?></h3>
                <?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'fallback_cb' => 'cai_theme_menu_fallback']); ?>
            </div>
            <div>
                <h3><?php esc_html_e('Contatti', 'commerciale-ai'); ?></h3>
                <p><a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>"><?php echo esc_html(get_option('admin_email')); ?></a></p>
                <p><a href="<?php echo esc_url(cai_account_url()); ?>"><?php esc_html_e('Area cliente', 'commerciale-ai'); ?></a></p>
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
