<?php
/**
 * Commerciale AI theme functions.
 *
 * @package Commerciale_AI
 */

defined('ABSPATH') || exit;

define('CAI_THEME_VERSION', '1.0.0');

function cai_theme_setup(): void
{
    load_theme_textdomain('commerciale-ai', get_template_directory().'/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 80, 'width' => 300, 'flex-height' => true, 'flex-width' => true]);
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    register_nav_menus([
        'primary' => __('Navigazione principale', 'commerciale-ai'),
        'footer'  => __('Navigazione footer', 'commerciale-ai'),
    ]);
}
add_action('after_setup_theme', 'cai_theme_setup');

function cai_theme_assets(): void
{
    wp_enqueue_style('commerciale-ai', get_stylesheet_uri(), [], CAI_THEME_VERSION);
    wp_enqueue_script('commerciale-ai', get_template_directory_uri().'/assets/js/site.js', [], CAI_THEME_VERSION, true);
}
add_action('wp_enqueue_scripts', 'cai_theme_assets');

function cai_theme_menu_fallback(): void
{
    echo '<ul>';
    echo '<li><a href="'.esc_url(home_url('/#funzioni')).'">'.esc_html__('Funzioni', 'commerciale-ai').'</a></li>';
    echo '<li><a href="'.esc_url(home_url('/#come-funziona')).'">'.esc_html__('Come funziona', 'commerciale-ai').'</a></li>';
    echo '<li><a href="'.esc_url(home_url('/#prezzi')).'">'.esc_html__('Prezzi', 'commerciale-ai').'</a></li>';
    echo '</ul>';
}

function cai_account_url(): string
{
    $page_id = (int) get_option('cai_account_page_id');
    return $page_id > 0 ? (string) get_permalink($page_id) : home_url('/area-cliente/');
}
