<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header" id="site-header">
    <div class="cai-container header-inner">
        <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php if (has_custom_logo()) : ?>
                <?php echo wp_get_attachment_image((int) get_theme_mod('custom_logo'), 'full', false, ['class' => 'custom-logo']); ?>
            <?php else : ?>
                <span class="brand-mark" aria-hidden="true">C</span>
                <span><?php bloginfo('name'); ?></span>
            <?php endif; ?>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu">Menu</button>
        <nav class="primary-nav" id="primary-menu" aria-label="<?php esc_attr_e('Navigazione principale', 'commerciale-ai'); ?>">
            <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => 'cai_theme_menu_fallback', 'depth' => 2]); ?>
        </nav>
        <div class="header-actions">
            <a class="cai-button cai-button--ghost" href="<?php echo esc_url(cai_account_url()); ?>"><?php echo is_user_logged_in() ? esc_html__('Il mio account', 'commerciale-ai') : esc_html__('Accedi', 'commerciale-ai'); ?></a>
            <a class="cai-button cai-button--coral" href="<?php echo esc_url(cai_page_url('prezzi')); ?>"><?php esc_html_e('Inizia ora', 'commerciale-ai'); ?></a>
        </div>
    </div>
</header>
