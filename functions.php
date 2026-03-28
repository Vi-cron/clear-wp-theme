<?php
/**
 * Theme functions and definitions
 */

// Theme setup
function wc_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('custom-logo', array(
        'height' => 80,
        'width' => 150,
        'flex-height' => true,
        'flex-width' => true,
    ));
    
    // Register menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'wc-theme'),
    ));
}
add_action('after_setup_theme', 'wc_theme_setup');

// Enqueue scripts and styles
function wc_theme_scripts() {
    // Styles
    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0.0');
    wp_enqueue_style('wc-theme-style', get_stylesheet_uri(), array(), '1.0.0');
    wp_enqueue_style('wc-theme-main', get_template_directory_uri() . '/assets/css/main.css', array(), '1.0.0');
    wp_enqueue_style('wc-theme-icons', get_template_directory_uri() . '/assets/css/icons.css', array(), '1.0.0');
    
    // Shop layout CSS
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_enqueue_style('wc-theme-shop-css', get_template_directory_uri() . '/assets/css/shop-layout.css', array(), '1.0.0');
    }
    
    // Scripts - подключаем jQuery первым
    wp_enqueue_script('jquery');
    
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true);
    wp_enqueue_script('wc-theme-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery', 'swiper-js'), '1.0.0', true);
    
    
    
    // Подключаем mini-cart.js после локализации
    //wp_enqueue_script('wc-theme-mini-cart', get_template_directory_uri() . '/assets/js/mini-cart.js', array('jquery', 'wc-theme-main'), '1.0.0', true);
    
    // Shop AJAX script
    if (is_shop() || is_product_category() || is_product_tag()) {
        
        /*wp_localize_script('wc-theme-shop-js', 'my_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ajax_nonce') 
        ));

        wp_enqueue_script('wc-theme-shop-js', get_template_directory_uri() . '/assets/js/shop-ajax.js', array('jquery'), '1.0.0', true);*/
    }
}
add_action('wp_enqueue_scripts', 'wc_theme_scripts');

// AJAX cart count update
function update_cart_count() {
    echo WC()->cart->get_cart_contents_count();
    wp_die();
}
add_action('wp_ajax_update_cart_count', 'update_cart_count');
add_action('wp_ajax_nopriv_update_cart_count', 'update_cart_count');

// Get wishlist count
function get_wishlist_count() {
    if (class_exists('TInvWL_Wishlist')) {
        $wishlist = new TInvWL_Wishlist();
        $products = $wishlist->get_products_by_wishlist();
        return count($products);
    }
    return 0;
}

// Add custom body classes
function wc_theme_body_classes($classes) {
    if (is_shop() || is_product_category() || is_product()) {
        $classes[] = 'woocommerce-page';
    }
    return $classes;
}
add_filter('body_class', 'wc_theme_body_classes');

// Remove WooCommerce default styles
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

// Custom excerpt length
function wc_theme_excerpt_length($length) {
    return 20;
}
add_filter('excerpt_length', 'wc_theme_excerpt_length');

require_once get_template_directory() . '/inc/clear.php';
require_once get_template_directory() . '/inc/categories.php';
require_once get_template_directory() . '/inc/theme-options.php';
require_once get_template_directory() . '/inc/woocommerce-filters.php';
require_once get_template_directory() . '/inc/woocommerce-ajax_products_load.php';
require_once get_template_directory() . '/inc/woocommerce-product-functions.php';
require_once get_template_directory() . '/inc/woocommerce-mini-cart.php';

// Добавьте в конец functions.php для отладки
/*add_action('wp_footer', function() {
    if (is_product()) {
        ?>
        <script>
        // Добавляем глобальную переменную для отладки
        window.wc_debug = true;
        
        // Перехватываем AJAX запросы для отладки
        $(document).ajaxError(function(event, jqXHR, settings, error) {
            console.error('AJAX Error Details:', {
                url: settings.url,
                data: settings.data,
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText
            });
        });
        </script>
        <?php
    }
});*/