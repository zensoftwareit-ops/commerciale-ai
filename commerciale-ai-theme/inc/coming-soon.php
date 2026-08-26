<?php
/**
 * Coming soon mode for public visitors.
 *
 * @package Commerciale_AI
 */

defined('ABSPATH') || exit;

function cai_coming_soon_is_enabled(): bool
{
    return (bool) get_option('cai_coming_soon_enabled', true);
}

function cai_render_coming_soon_for_guests(): void
{
    $administrator_preview = is_user_logged_in()
        && current_user_can('manage_options')
        && isset($_GET['cai_preview_coming_soon']);

    if ((! cai_coming_soon_is_enabled() || is_user_logged_in()) && ! $administrator_preview) return;
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) return;
    if (defined('WP_CLI') && WP_CLI) return;

    status_header(200);
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    require get_template_directory().'/coming-soon.php';
    exit;
}
add_action('template_redirect', 'cai_render_coming_soon_for_guests', -1000);

function cai_coming_soon_admin_menu(): void
{
    add_theme_page(
        __('Coming soon', 'commerciale-ai'),
        __('Coming soon', 'commerciale-ai'),
        'manage_options',
        'cai-coming-soon',
        'cai_coming_soon_settings_page'
    );
}
add_action('admin_menu', 'cai_coming_soon_admin_menu');

function cai_coming_soon_register_setting(): void
{
    register_setting('cai_coming_soon', 'cai_coming_soon_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => static fn (mixed $value): bool => (bool) $value,
    ]);
}
add_action('admin_init', 'cai_coming_soon_register_setting');

function cai_coming_soon_settings_page(): void
{
    if (! current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Coming soon', 'commerciale-ai'); ?></h1>
        <p><?php esc_html_e('Quando la modalità è attiva, i visitatori non autenticati vedono soltanto la pagina di attesa. Utenti loggati, wp-admin, API e webhook continuano a funzionare normalmente.', 'commerciale-ai'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('cai_coming_soon'); ?>
            <input type="hidden" name="cai_coming_soon_enabled" value="0">
            <label>
                <input type="checkbox" name="cai_coming_soon_enabled" value="1" <?php checked(cai_coming_soon_is_enabled()); ?>>
                <strong><?php esc_html_e('Mostra la pagina Coming soon ai visitatori non autenticati', 'commerciale-ai'); ?></strong>
            </label>
            <?php submit_button(__('Salva impostazione', 'commerciale-ai')); ?>
        </form>
        <p><a class="button button-secondary" target="_blank" rel="noopener" href="<?php echo esc_url(add_query_arg('cai_preview_coming_soon', '1', home_url('/'))); ?>"><?php esc_html_e('Anteprima Coming soon', 'commerciale-ai'); ?></a></p>
    </div>
    <?php
}
