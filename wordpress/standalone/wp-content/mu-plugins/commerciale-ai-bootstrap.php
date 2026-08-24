<?php
/**
 * Plugin Name: Commerciale AI Bootstrap
 * Description: Completa automaticamente l'integrazione iniziale di tema e plugin.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

add_action('admin_init', static function (): void {
    if (get_option('cai_wordpress_bootstrapped') === '1') return;
    if (! current_user_can('activate_plugins') || ! current_user_can('switch_themes')) return;

    if (! function_exists('activate_plugin')) require_once ABSPATH.'wp-admin/includes/plugin.php';

    $plugin = 'commerciale-ai-client/commerciale-ai-client.php';
    if (! is_plugin_active($plugin)) {
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) return;
    }

    $theme = wp_get_theme('commerciale-ai-theme');
    if (! $theme->exists()) return;
    if (get_stylesheet() !== 'commerciale-ai-theme') switch_theme('commerciale-ai-theme');

    update_option('blogdescription', 'Ogni lead merita una risposta.');
    update_option('timezone_string', 'Europe/Rome');
    update_option('default_comment_status', 'closed');
    update_option('default_ping_status', 'closed');
    update_option('permalink_structure', '/%postname%/');
    flush_rewrite_rules(false);
    update_option('cai_wordpress_bootstrapped', '1', false);
});
