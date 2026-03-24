<?php
/**
 * Shop page template
 */

get_header();

// Include theme options
require_once get_template_directory() . '/inc/theme-options.php';
require_once get_template_directory() . '/inc/woocommerce-filters.php';
?>

<div class="container shop-container">
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
    
    <div class="shop-layout">
        <!-- Filters Sidebar -->
        <?php wc_theme_display_filters(); ?>
        
        <!-- Products Main Content -->
        <div class="shop-content">
            <?php
            // Display clear filters button
            wc_theme_clear_filters_button();
            
            // Display sorting and results count
            do_action('woocommerce_before_shop_loop');
            ?>
            
            <div class="products-grid">
                <?php
                if (woocommerce_product_loop() && have_posts()):
                    woocommerce_product_loop_start();
                    
                    while (have_posts()): the_post();
                        wc_get_template_part('content', 'product');
                    endwhile;
                    
                    woocommerce_product_loop_end();
                else:
                    do_action('woocommerce_no_products_found');
                endif;
                ?>
            </div>
            
            <?php do_action('woocommerce_after_shop_loop'); ?>
        </div>
    </div>
    
    <?php do_action('woocommerce_after_main_content'); ?>
</div>

<?php get_footer(); ?>