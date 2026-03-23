<?php get_header(); ?>

<div class="container">
    <div class="blog-content">
        <?php if (have_posts()): while (have_posts()): the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <div class="entry-meta">
                    <span class="post-date"><?php echo get_the_date(); ?></span>
                </div>
                <div class="entry-content">
                    <?php the_excerpt(); ?>
                </div>
            </article>
        <?php endwhile; ?>
        
        <?php the_posts_pagination(); ?>
        
        <?php else: ?>
            <p><?php _e('Постов не найдено.', 'wc-theme'); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>