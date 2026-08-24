<?php
defined('ABSPATH') || exit;
get_header();
?>
<main class="site-main--content">
    <div class="cai-container">
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <header class="page-header"><p class="eyebrow"><?php echo esc_html(get_bloginfo('name')); ?></p><h1><?php the_title(); ?></h1></header>
                <div class="entry-content"><?php the_content(); ?></div>
            </article>
        <?php endwhile; ?>
    </div>
</main>
<?php get_footer(); ?>
