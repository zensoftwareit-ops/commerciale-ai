<?php
/**
 * Public coming soon page.
 *
 * @package Commerciale_AI
 */

defined('ABSPATH') || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php echo esc_html(sprintf(__('Presto online · %s', 'commerciale-ai'), get_bloginfo('name'))); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('cai-coming-soon-page'); ?>>
<?php wp_body_open(); ?>
<main class="coming-soon">
    <header class="coming-soon__header cai-container">
        <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php if (has_custom_logo()) : ?>
                <?php echo wp_get_attachment_image((int) get_theme_mod('custom_logo'), 'full', false, ['class' => 'custom-logo']); ?>
            <?php else : ?>
                <?php echo cai_brand_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </a>
        <a class="cai-button cai-button--ghost" href="<?php echo esc_url(wp_login_url(home_url('/'))); ?>"><?php esc_html_e('Accesso riservato', 'commerciale-ai'); ?></a>
    </header>

    <section class="coming-soon__hero cai-container">
        <div class="coming-soon__copy">
            <p class="eyebrow"><?php esc_html_e('Stiamo preparando il lancio', 'commerciale-ai'); ?></p>
            <h1><?php esc_html_e('Ogni lead merita una', 'commerciale-ai'); ?> <em><?php esc_html_e('risposta.', 'commerciale-ai'); ?></em></h1>
            <p class="coming-soon__intro"><?php esc_html_e('Commerciale AI sta arrivando: un ambiente operativo che raccoglie le richieste, mette ordine nelle priorità e prepara il prossimo passo insieme al tuo team.', 'commerciale-ai'); ?></p>
            <div class="coming-soon__status"><span></span><strong><?php esc_html_e('Sito in preparazione', 'commerciale-ai'); ?></strong><small><?php esc_html_e('Torneremo online appena tutto sarà pronto.', 'commerciale-ai'); ?></small></div>
        </div>

        <div class="coming-soon__visual" aria-label="<?php esc_attr_e('Anteprima illustrativa di Commerciale AI', 'commerciale-ai'); ?>">
            <div class="coming-card coming-card--main">
                <div class="coming-card__bar"><i></i><i></i><i></i><span><?php esc_html_e('Commerciale AI', 'commerciale-ai'); ?></span></div>
                <div class="coming-card__body">
                    <p class="coming-card__label"><?php esc_html_e('Prossima azione', 'commerciale-ai'); ?></p>
                    <h2><?php esc_html_e('Le opportunità importanti, prima.', 'commerciale-ai'); ?></h2>
                    <div class="coming-lead"><span class="coming-lead__avatar">SA</span><div><strong>Studio Arco</strong><small><?php esc_html_e('Richiesta preventivo · priorità alta', 'commerciale-ai'); ?></small></div><b><?php esc_html_e('Da rivedere', 'commerciale-ai'); ?></b></div>
                    <div class="coming-lead"><span class="coming-lead__avatar coming-lead__avatar--mint">NL</span><div><strong>Nova Lab</strong><small><?php esc_html_e('Consulenza B2B · follow-up oggi', 'commerciale-ai'); ?></small></div><b><?php esc_html_e('In contatto', 'commerciale-ai'); ?></b></div>
                </div>
            </div>
            <div class="coming-note coming-note--ai"><span>AI</span><div><strong><?php esc_html_e('Bozza pronta', 'commerciale-ai'); ?></strong><small><?php esc_html_e('Da verificare prima dell’invio', 'commerciale-ai'); ?></small></div></div>
            <div class="coming-note coming-note--human"><span>✓</span><div><strong><?php esc_html_e('Controllo umano', 'commerciale-ai'); ?></strong><small><?php esc_html_e('La decisione resta al team', 'commerciale-ai'); ?></small></div></div>
        </div>
    </section>

    <footer class="coming-soon__footer cai-container">
        <span>© <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span>
        <span><?php esc_html_e('Un prodotto Zen Software', 'commerciale-ai'); ?></span>
    </footer>
</main>
<?php wp_footer(); ?>
</body>
</html>
