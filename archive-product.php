<?php
/**
 * Shop page template with AJAX filters
 * Сервер рендерит начальную страницу, AJAX подгружает при фильтрации
 */

get_header();

// Подключаем модуль
if (file_exists(get_template_directory() . '/inc/wc-ajax-module/wc-ajax-module.php')) {
    require_once get_template_directory() . '/inc/wc-ajax-module/wc-ajax-module.php';
    $wc_module = WC_Ajax_Module::get_instance();
}
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
    </div>
    
    <div class="shop-layout">
        <!-- Filters Sidebar -->
        <?php 
        if (isset($wc_module) && function_exists('wc_ajax_module_init')) {
            $wc_module->display_filters();
        } else {
            // Fallback на старые функции если модуль не загружен
            if (function_exists('wc_theme_display_filters')) {
                wc_theme_display_filters();
            }
        }
        ?>
        
        <!-- Products Main Content -->
        <div class="shop-content" id="shop-content">
            <?php
            // Display sorting and results count (стандартный WooCommerce)
            do_action('woocommerce_before_shop_loop');
            ?>
            
            <div class="products-grid" id="products-grid">
                <?php
                if (woocommerce_product_loop() && have_posts()):
                    // Стандартный серверный рендер начальных товаров
                    while (have_posts()): the_post();
                        wc_get_template_part('content', 'product');
                    endwhile;
                else:
                    do_action('woocommerce_no_products_found');
                endif;
                ?>
            </div>
            
            <div class="pagination-wrapper" id="pagination-wrapper">
                <?php 
                // Стандартная пагинация для начальной загрузки
                global $wp_query;
                $big = 999999999;
                $pages = paginate_links(array(
                    'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format' => '?paged=%#%',
                    'current' => max(1, get_query_var('paged')),
                    'total' => $wp_query->max_num_pages,
                    'type' => 'array',
                    'prev_text' => '«',
                    'next_text' => '»',
                ));
                
                if (is_array($pages)) {
                    echo '<div class="pagination">';
                    foreach ($pages as $page) {
                        echo $page;
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <?php do_action('woocommerce_after_main_content'); ?>
</div>

<?php get_footer(); ?>