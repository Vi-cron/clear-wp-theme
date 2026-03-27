<?php
/**
 * AJAX handlers for Add to Cart and Mini Cart
*/

if (!defined('ABSPATH')) exit;

// Получение мини-корзины
add_action('wp_ajax_wc_theme_get_mini_cart', 'wc_theme_ajax_get_mini_cart');
add_action('wp_ajax_nopriv_wc_theme_get_mini_cart', 'wc_theme_ajax_get_mini_cart');

function wc_theme_ajax_get_mini_cart() {
    check_ajax_referer('wc_ajax_nonce', 'nonce');
    
    if (!WC()->cart) {
        wp_send_json_error(['message' => 'Корзина недоступна']);
    }
    
    $cart = WC()->cart;
    $items = [];
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        if (!$product) continue;
        
        $items[] = [
            'key' => $cart_item_key,
            'title' => $product->get_name(),
            'url' => get_permalink($product->get_id()),
            'price' => $product->get_price(),
            'price_html' => wc_price($product->get_price()),
            'quantity' => $cart_item['quantity'],
            'subtotal' => $cart->get_product_subtotal($product, $cart_item['quantity']),
            'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src(),
        ];
    }
    
    wp_send_json_success([
        'items' => $items,
        'count' => $cart->get_cart_contents_count(),
        'total' => $cart->get_total('edit'),
        'total_html' => wc_price($cart->get_total()),
    ]);
}

// Добавление в корзину
add_action('wp_ajax_wc_theme_add_to_cart', 'wc_theme_ajax_add_to_cart');
add_action('wp_ajax_nopriv_wc_theme_add_to_cart', 'wc_theme_ajax_add_to_cart');

function wc_theme_ajax_add_to_cart() {
    check_ajax_referer('wc_ajax_nonce', 'nonce');
    
    $product_id = absint($_POST['product_id']);
    $quantity = absint($_POST['quantity'] ?: 1);
    $variation_id = absint($_POST['variation_id'] ?: 0);
    $attributes = isset($_POST['attributes']) ? (array) $_POST['attributes'] : [];
    
    if (!$product_id) {
        wp_send_json_error(['message' => 'ID товара не указан']);
    }
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Товар не найден']);
    }
    
    // Проверяем наличие
    if (!$product->is_in_stock()) {
        wp_send_json_error(['message' => 'Товар временно отсутствует']);
    }
    
    // Для вариативных товаров
    if ($product->is_type('variable') && $variation_id) {
        $variation = wc_get_product($variation_id);
        
        if (!$variation || !$variation->is_in_stock()) {
            wp_send_json_error(['message' => 'Выбранная вариация недоступна']);
        }
        
        $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $attributes);
    } else {
        $added = WC()->cart->add_to_cart($product_id, $quantity);
    }
    
    if ($added) {
        wp_send_json_success([
            'message' => 'Товар добавлен в корзину',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_total(),
        ]);
    } else {
        wp_send_json_error(['message' => 'Не удалось добавить товар в корзину']);
    }
}

// Получение количества товаров в корзине
add_action('wp_ajax_wc_theme_get_cart_count', 'wc_theme_ajax_get_cart_count');
add_action('wp_ajax_nopriv_wc_theme_get_cart_count', 'wc_theme_ajax_get_cart_count');

function wc_theme_ajax_get_cart_count() {
    check_ajax_referer('wc_ajax_nonce', 'nonce');
    
    $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    
    wp_send_json_success(['count' => $count]);
}

// Удаление товара из корзины
add_action('wp_ajax_wc_theme_remove_cart_item', 'wc_theme_ajax_remove_cart_item');
add_action('wp_ajax_nopriv_wc_theme_remove_cart_item', 'wc_theme_ajax_remove_cart_item');

function wc_theme_ajax_remove_cart_item() {
    check_ajax_referer('wc_ajax_nonce', 'nonce');
    
    $item_key = sanitize_text_field($_POST['item_key']);
    
    if (!$item_key) {
        wp_send_json_error(['message' => 'Не указан товар']);
    }
    
    $removed = WC()->cart->remove_cart_item($item_key);
    
    if ($removed) {
        wp_send_json_success([
            'message' => 'Товар удален',
            'cart_count' => WC()->cart->get_cart_contents_count(),
        ]);
    } else {
        wp_send_json_error(['message' => 'Не удалось удалить товар']);
    }
}

// Очистка корзины
add_action('wp_ajax_wc_theme_clear_cart', 'wc_theme_ajax_clear_cart');
add_action('wp_ajax_nopriv_wc_theme_clear_cart', 'wc_theme_ajax_clear_cart');

function wc_theme_ajax_clear_cart() {
    check_ajax_referer('wc_ajax_nonce', 'nonce');
    
    WC()->cart->empty_cart();
    
    wp_send_json_success([
        'message' => 'Корзина очищена',
        'cart_count' => 0,
    ]);
}

// Локализация для AJAX
add_action('wp_enqueue_scripts', 'wc_theme_localize_ajax', 100);

function wc_theme_localize_ajax() {
    wp_localize_script('jquery', 'wc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_ajax_nonce'),
    ]);
}