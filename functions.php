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
    
    // Scripts - подключаем jQuery первым
    wp_enqueue_script('jquery');
    
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true);
    wp_enqueue_script('wc-theme-main', get_template_directory_uri() . '/assets/js/main.js', array('jquery', 'swiper-js'), '1.0.0', true);

}
add_action('wp_enqueue_scripts', 'wc_theme_scripts');


require_once get_template_directory() . '/inc/clear.php';
require_once get_template_directory() . '/inc/categories.php';
require_once get_template_directory() . '/inc/theme-options.php';

require_once get_template_directory() . '/inc/woocommerce-product-functions.php';

require_once get_template_directory() . '/inc/woocommerce-mini-cart.php';

require_once get_template_directory() . '/inc/wc-ajax-module/wc-ajax-module.php';
// Инициализация модуля
add_action('after_setup_theme', function() {
    wc_ajax_module_init();
});
