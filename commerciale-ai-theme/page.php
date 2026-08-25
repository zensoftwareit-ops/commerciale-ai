<?php
defined('ABSPATH') || exit;
get_header();
?>
<main class="site-main--content">
    <div class="cai-container">
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class('marketing-page'); ?>>
                <?php $parent_id = (int) wp_get_post_parent_id(get_the_ID()); ?>
                <nav class="breadcrumbs" aria-label="<?php esc_attr_e('Percorso', 'commerciale-ai'); ?>"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'commerciale-ai'); ?></a><span>›</span><?php if ($parent_id > 0) : ?><a href="<?php echo esc_url(get_permalink($parent_id)); ?>"><?php echo esc_html(get_the_title($parent_id)); ?></a><span>›</span><?php endif; ?><span aria-current="page"><?php the_title(); ?></span></nav>
                <header class="page-header"><p class="eyebrow"><?php echo $parent_id > 0 ? esc_html(get_the_title($parent_id)) : esc_html(get_bloginfo('name')); ?></p><h1><?php the_title(); ?></h1><?php if (has_excerpt()) : ?><p class="page-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?></header>
                <div class="entry-content"><?php the_content(); ?></div>
            </article>
        <?php endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
