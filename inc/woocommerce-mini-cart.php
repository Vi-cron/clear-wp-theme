<?php
/**
 * AJAX handlers for Add to Cart and Mini Cart
 * Universal WooCommerce Mini Cart Module
 */

if (!defined('ABSPATH')) exit;

/**
 * Инициализация модуля мини-корзины
 */
class WC_Theme_Mini_Cart {
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Инициализируем модуль только если WooCommerce активен
        add_action('init', array($this, 'init'), 20);
    }
    
    /**
     * Инициализация хуков
     */
    public function init() {
        // Проверяем наличие WooCommerce
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // AJAX handlers
        $this->register_ajax_handlers();
        
        // Подключаем скрипты и стили
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Локализация AJAX
        add_action('wp_enqueue_scripts', array($this, 'localize_ajax'), 100);
        
        // Обновление фрагментов корзины
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'add_to_cart_fragments'));
    }
    
    /**
     * Регистрация AJAX обработчиков
     */
    private function register_ajax_handlers() {
        // Получение мини-корзины
        add_action('wp_ajax_wc_theme_get_mini_cart', array($this, 'ajax_get_mini_cart'));
        add_action('wp_ajax_nopriv_wc_theme_get_mini_cart', array($this, 'ajax_get_mini_cart'));
        
        // Добавление в корзину
        add_action('wp_ajax_wc_theme_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_wc_theme_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        // Получение количества товаров
        add_action('wp_ajax_wc_theme_get_cart_count', array($this, 'ajax_get_cart_count'));
        add_action('wp_ajax_nopriv_wc_theme_get_cart_count', array($this, 'ajax_get_cart_count'));
        
        // Удаление товара
        add_action('wp_ajax_wc_theme_remove_cart_item', array($this, 'ajax_remove_cart_item'));
        add_action('wp_ajax_nopriv_wc_theme_remove_cart_item', array($this, 'ajax_remove_cart_item'));
        
        // Очистка корзины
        add_action('wp_ajax_wc_theme_clear_cart', array($this, 'ajax_clear_cart'));
        add_action('wp_ajax_nopriv_wc_theme_clear_cart', array($this, 'ajax_clear_cart'));
        
        // Обновление мини-корзины
        add_action('wp_ajax_wc_theme_refresh_mini_cart', array($this, 'ajax_get_mini_cart'));
        add_action('wp_ajax_nopriv_wc_theme_refresh_mini_cart', array($this, 'ajax_get_mini_cart'));
    }
    
    /**
     * Подключение скриптов и стилей
     */
    public function enqueue_scripts() {
        // Пути к файлам
        $js_path = get_template_directory_uri() . '/assets/js/mini-cart.js';
        $css_path = get_template_directory_uri() . '/assets/css/mini-cart.css';
        
        // Подключаем JS
        wp_enqueue_script(
            'wc-theme-mini-cart',
            $js_path,
            array('jquery'),
            '2.4',
            true
        );
        
        // Подключаем CSS
        wp_enqueue_style(
            'wc-theme-mini-cart',
            $css_path,
            array(),
            '2.4'
        );
    }
    
    /**
     * Локализация AJAX
     */
    public function localize_ajax() {
        wp_localize_script('wc-theme-mini-cart', 'wc_mini_cart_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ajax_nonce'),
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
            'strings' => array(
                'added_to_cart' => __('Товар добавлен в корзину', 'wc-theme'),
                'error_adding' => __('Ошибка при добавлении товара', 'wc-theme'),
                'cart_empty' => __('Корзина пуста', 'wc-theme'),
                'view_cart' => __('Просмотр корзины', 'wc-theme'),
                'checkout' => __('Оформить заказ', 'wc-theme'),
            )
        ));
    }
    
    /**
     * Получение мини-корзины (AJAX)
     */
    public function ajax_get_mini_cart() {
        // Nonce проверка
        if (isset($_POST['nonce'])) {
            check_ajax_referer('wc_ajax_nonce', 'nonce');
        }
        
        if (!WC()->cart) {
            wp_send_json_error(array('message' => __('Корзина недоступна', 'wc-theme')));
        }
        
        $cart = WC()->cart;
        $items = array();
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            if (!$product) continue;
            
            // Получаем изображение
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();
            
            $items[] = array(
                'key' => $cart_item_key,
                'title' => $product->get_name(),
                'url' => get_permalink($product->get_id()),
                'price' => $product->get_price(),
                'price_html' => wc_price($product->get_price()),
                'quantity' => $cart_item['quantity'],
                'subtotal' => $cart->get_product_subtotal($product, $cart_item['quantity']),
                'image' => $image_url,
            );
        }
        
        wp_send_json_success(array(
            'items' => $items,
            'count' => $cart->get_cart_contents_count(),
            'total' => $cart->get_total('edit'),
            'total_html' => wc_price($cart->get_total()),
        ));
    }
    
    /**
     * Получить первый доступный вариант вариативного товара
     */
    private function get_first_available_variation($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            return null;
        }
        
        $available_variations = $product->get_available_variations();
        
        if (empty($available_variations)) {
            return null;
        }
        
        foreach ($available_variations as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            $variation = wc_get_product($variation_id);
            
            if ($variation && $variation->is_in_stock()) {
                return array(
                    'variation_id' => $variation_id,
                    'attributes' => $variation_data['attributes'],
                    'price' => $variation->get_price(),
                    'stock_quantity' => $variation->get_stock_quantity()
                );
            }
        }
        
        $first_variation = $available_variations[0];
        return array(
            'variation_id' => $first_variation['variation_id'],
            'attributes' => $first_variation['attributes'],
            'price' => $first_variation['display_price'],
            'stock_quantity' => $first_variation['max_qty'] ?? null
        );
    }
    
    /**
     * Получить атрибуты вариации
     */
    private function get_variation_attributes_formatted($variation_id, $product_id) {
        $variation = wc_get_product($variation_id);
        
        if (!$variation) {
            return array();
        }
        
        $attributes = array();
        $variation_attributes = $variation->get_attributes();
        
        foreach ($variation_attributes as $taxonomy => $value) {
            $attribute_name = 'attribute_' . $taxonomy;
            $attributes[$attribute_name] = $value;
        }
        
        return $attributes;
    }
    
    /**
     * Добавление в корзину (AJAX)
     */
    public function ajax_add_to_cart() {
        // Nonce проверка
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Ошибка безопасности', 'wc-theme')));
        }
        
        $product_id = absint($_POST['product_id']);
        $quantity = absint($_POST['quantity'] ?: 1);
        $variation_id = absint($_POST['variation_id'] ?: 0);
        
        // Собираем атрибуты из POST данных
        $attributes = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                $attributes[$key] = sanitize_text_field($value);
            }
        }
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('ID товара не указан', 'wc-theme')));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Товар не найден', 'wc-theme')));
        }
        
        if (!$product->is_in_stock()) {
            wp_send_json_error(array('message' => __('Товар временно отсутствует', 'wc-theme')));
        }
        
        $added = false;
        $selected_variation_id = $variation_id;
        $selected_attributes = $attributes;
        
        // Для вариативных товаров
        if ($product->is_type('variable')) {
            if ($selected_variation_id == 0) {
                $first_variation = $this->get_first_available_variation($product_id);
                
                if ($first_variation) {
                    $selected_variation_id = $first_variation['variation_id'];
                    $selected_attributes = $first_variation['attributes'];
                } else {
                    wp_send_json_error(array('message' => __('Нет доступных вариантов товара', 'wc-theme')));
                }
            }
            
            if ($selected_variation_id == 0) {
                wp_send_json_error(array('message' => __('Не выбран вариант товара', 'wc-theme')));
            }
            
            $variation = wc_get_product($selected_variation_id);
            
            if (!$variation) {
                wp_send_json_error(array('message' => __('Вариация товара не найдена', 'wc-theme')));
            }
            
            if (!$variation->is_in_stock()) {
                wp_send_json_error(array('message' => __('Выбранная вариация недоступна', 'wc-theme')));
            }
            
            if (empty($selected_attributes)) {
                $selected_attributes = $this->get_variation_attributes_formatted($selected_variation_id, $product_id);
            }
            
            $added = WC()->cart->add_to_cart($product_id, $quantity, $selected_variation_id, $selected_attributes);
            
            if (!$added) {
                $added = WC()->cart->add_to_cart($product_id, $quantity, $selected_variation_id);
            }
        } else {
            // Простой товар
            $added = WC()->cart->add_to_cart($product_id, $quantity);
        }
        
        if ($added) {
            wp_send_json_success(array(
                'message' => __('Товар добавлен в корзину', 'wc-theme'),
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_total(),
                'cart_total_html' => wc_price(WC()->cart->get_total()),
                'variation_id' => $selected_variation_id ?? 0,
                'variation_found' => isset($selected_variation_id) && $selected_variation_id > 0
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Не удалось добавить товар в корзину', 'wc-theme'),
            ));
        }
    }
    
    /**
     * Получение количества товаров (AJAX)
     */
    public function ajax_get_cart_count() {
        if (isset($_POST['nonce'])) {
            check_ajax_referer('wc_ajax_nonce', 'nonce');
        }
        
        $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        wp_send_json_success(array('count' => $count));
    }
    
    /**
     * Удаление товара из корзины (AJAX)
     */
    public function ajax_remove_cart_item() {
        if (isset($_POST['nonce'])) {
            check_ajax_referer('wc_ajax_nonce', 'nonce');
        }
        
        $item_key = sanitize_text_field($_POST['item_key']);
        
        if (!$item_key) {
            wp_send_json_error(array('message' => __('Не указан товар', 'wc-theme')));
        }
        
        $removed = WC()->cart->remove_cart_item($item_key);
        
        if ($removed) {
            wp_send_json_success(array(
                'message' => __('Товар удален', 'wc-theme'),
                'cart_count' => WC()->cart->get_cart_contents_count(),
            ));
        } else {
            wp_send_json_error(array('message' => __('Не удалось удалить товар', 'wc-theme')));
        }
    }
    
    /**
     * Очистка корзины (AJAX)
     */
    public function ajax_clear_cart() {
        if (isset($_POST['nonce'])) {
            check_ajax_referer('wc_ajax_nonce', 'nonce');
        }
        
        WC()->cart->empty_cart();
        
        wp_send_json_success(array(
            'message' => __('Корзина очищена', 'wc-theme'),
            'cart_count' => 0,
        ));
    }
    
    /**
     * Обновление фрагментов корзины
     */
    public function add_to_cart_fragments($fragments) {
        // Обновляем счетчик корзины
        ob_start();
        ?>
        <span class="cart-count"><?php echo WC()->cart ? WC()->cart->get_cart_contents_count() : '0'; ?></span>
        <?php
        $fragments['.cart-count'] = ob_get_clean();
        
        return $fragments;
    }
}

/**
 * Инициализация модуля
 */
new WC_Theme_Mini_Cart();