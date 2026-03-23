<?php get_header(); ?>

<div class="container">
    <div class="shop-header">
        <h1 class="page-title">
            <?php if (is_product_category()): ?>
                <?php single_term_title(); ?>
            <?php else: ?>
                <?php _e('Каталог товаров', 'wc-theme'); ?>
            <?php endif; ?>
        </h1>
        
        <?php do_action('woocommerce_before_main_content'); ?>
    </div>
    
    <div class="shop-content">
        <?php if (woocommerce_product_loop()): ?>
            <div class="products-grid">
                <?php woocommerce_product_loop_start(); ?>
                
                <?php if (wc_get_loop_prop('total')): ?>
                    <?php while (have_posts()): the_post(); ?>
                        <?php wc_get_template_part('content', 'product'); ?>
                    <?php endwhile; ?>
                <?php endif; ?>
                
                <?php woocommerce_product_loop_end(); ?>
            </div>
            
            <?php do_action('woocommerce_after_shop_loop'); ?>
        <?php else: ?>
            <?php do_action('woocommerce_no_products_found'); ?>
        <?php endif; ?>
    </div>
    
    <?php do_action('woocommerce_after_main_content'); ?>
</div>

<?php get_footer(); ?>