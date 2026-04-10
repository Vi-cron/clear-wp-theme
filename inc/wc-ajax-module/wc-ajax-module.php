<?php
/**
 * WooCommerce AJAX Module
 * Самодостаточный модуль для AJAX фильтрации и загрузки товаров
 * 
 * @package WC_Ajax_Module
 * @version 1.0
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

class WC_Ajax_Module {
    
    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;
    
    /**
     * Получить экземпляр класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Инициализация хуков
     */
    private function init_hooks() {
		
		
        // Подключение стилей и скриптов
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_load_products_ajax', array($this, 'load_products_ajax'));
        add_action('wp_ajax_nopriv_load_products_ajax', array($this, 'load_products_ajax'));
        
        // Фильтрация товаров
        add_action('pre_get_posts', array($this, 'process_attribute_filters'));
        
        // Короткие коды
        add_shortcode('wc_ajax_shop', array($this, 'render_ajax_shop'));
		//add_filter('style_loader_src', array($this, 'dell_woocommerce_styles'), 10, 2);
		add_filter('woocommerce_variable_price_html', array($this,'custom_variable_price_min_only'), 10, 2);
    }
    
    /**
     * Подключение скриптов и стилей
     */
    public function enqueue_scripts() {
        if (!$this->is_ajax_shop_page()) {
            return;
        }
        
        wp_enqueue_style(
		'wc-ajax-module',
		get_template_directory_uri() . '/inc/wc-ajax-module/shop-layout.css',
		array(),
		'1.0'
		);

		// JavaScript
		wp_enqueue_script(
			'wc-ajax-module',
			get_template_directory_uri() . '/inc/wc-ajax-module/shop-ajax.js',
			array('jquery'),
			'1.0',
		true
		);
        
        // Локализация для JS
        wp_localize_script('wc-ajax-module', 'wc_ajax_module', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ajax_nonce'),
            'shop_url' => wc_get_page_permalink('shop'),
        ));
    }
    
    /**
     * Проверка, нужно ли подключать модуль
     */
    private function is_ajax_shop_page() {
        return is_shop() || is_product_category() || is_product_tag() || 
               (is_singular() && has_shortcode(get_post()->post_content, 'wc_ajax_shop'));
    }

	/**
     * Удаляем стили woocommerce, когда работает модуль
     */
	public function dell_woocommerce_styles($src, $handle) {
		if (strpos($handle, 'woocommerce') !== false || strpos($handle, 'wc-blocks') !== false) 
			return ''; 
    	return $src;
	}
	
	public function custom_variable_price_min_only($price, $product) {
		$min_price = $product->get_variation_price('min', true);
    return wc_price($min_price);
	}
    
    /**
     * Получить активные атрибуты для категории (только те, что есть у товаров в категории)
     */
    public function get_category_attributes_optimized($category_id = null) {
        global $wpdb;
        
        // Если категория не указана, пытаемся определить текущую
        if (!$category_id) {
            $category_id = $this->get_current_category_id();
        }
        
        // Если нет категории, получаем все атрибуты
        if (!$category_id) {
            return $this->get_all_attributes();
        }
        
        // Получаем ID товаров в категории
        $product_ids = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            )
        ));
        
        if (empty($product_ids)) {
            return array();
        }
        
        $ids_string = implode(',', array_map('intval', $product_ids));
        
        // Прямой SQL-запрос для получения атрибутов, которые реально используются в товарах категории
        $query = $wpdb->prepare("
            SELECT DISTINCT 
                tt.taxonomy,
                tt.term_id,
                tt.term_taxonomy_id
            FROM {$wpdb->prefix}term_relationships AS tr
            INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id IN ($ids_string)
                AND tt.taxonomy LIKE 'pa_%%'
        ");
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return array();
        }
        
        // Собираем уникальные таксономии
        $taxonomies = array_unique(array_column($results, 'taxonomy'));
        
        // Форматируем результат
        $attributes = array();
        foreach ($taxonomies as $taxonomy) {
            // Получаем все термины для этого атрибута
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'object_ids' => $product_ids
            ));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $attribute = wc_get_attribute(wc_attribute_taxonomy_id_by_name($taxonomy));
                $attributes[$taxonomy] = array(
                    'label' => $attribute ? $attribute->name : wc_attribute_label($taxonomy),
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
                
                foreach ($terms as $term) {
                    $attributes[$taxonomy]['terms'][$term->slug] = array(
                        'name' => $term->name,
                        'count' => $term->count
                    );
                }
            }
        }
        
        return $attributes;
    }
    
    /**
     * Получить все атрибуты (для главной страницы магазина)
     */
    private function get_all_attributes() {
        $attributes = wc_get_attribute_taxonomies();
        $result = array();
        
        foreach ($attributes as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
            ));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $result[$taxonomy] = array(
                    'label' => $attribute->attribute_label,
                    'taxonomy' => $taxonomy,
                    'terms' => array()
                );
                
                foreach ($terms as $term) {
                    $result[$taxonomy]['terms'][$term->slug] = array(
                        'name' => $term->name,
                        'count' => $term->count
                    );
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Получить ID текущей категории
     */
    private function get_current_category_id() {
        if (is_product_category()) {
            $queried_object = get_queried_object();
            if ($queried_object && !is_wp_error($queried_object)) {
                return $queried_object->term_id;
            }
        }
        return null;
    }
    
    /**
     * Отображение фильтров
     */
    public function display_filters() {
        $current_category_id = $this->get_current_category_id();
        $attributes = $this->get_category_attributes_optimized($current_category_id);
        
        // Проверяем, есть ли активные фильтры
        $has_active_filters = $this->has_active_filters();
		//$has_active_filters = true;
        
        ?>
        <aside class="shop-filters">
            <div class="filters-header">
                <h3 class="filters-title">Фильтры</h3>
				<a href="<?php echo esc_url($this->get_clear_filters_url()); ?>" class="clear-filters-btn <?if (!$has_active_filters) echo 'hide'?>">Сбросить все</a>
                <!--div class="clear-filters"></div-->
            </div>
            
            <!-- Price Filter -->
            <?php if (function_exists('woocommerce_price_filter')): ?>
            <div class="filter-section">
                <h4>Цена</h4>
                <?php the_widget('WC_Widget_Price_Filter'); ?>
            </div>
            <?php endif; ?>
            
            <!-- Categories Filter -->
            <?php if (!is_product_category() && function_exists('woocommerce_product_categories_widget')): ?>
            <div class="filter-section">
                <h4>Категории</h4>
                <?php the_widget('WC_Widget_Product_Categories', array(
                    'dropdown' => 0,
                    'count' => 1,
                    'hierarchical' => 1,
                    'show_children_only' => 0,
                    'hide_empty' => 1
                )); ?>
            </div>
            <?php endif; ?>
            
            <!-- Attribute Filters - только те, что есть в текущей категории -->
            <?php if (!empty($attributes)): ?>
                <?php foreach ($attributes as $taxonomy => $attribute_data): ?>
                    <?php if (!empty($attribute_data['terms'])): ?>
                        <div class="filter-section">
                            <h4><?php echo esc_html($attribute_data['label']); ?></h4>
                            <div class="filter-attributes">
                                <?php foreach ($attribute_data['terms'] as $slug => $term_data): ?>
                                    <?php
                                    $checked = isset($_GET['filter_' . str_replace('pa_', '', $taxonomy)]) && 
                                               in_array($slug, (array) $_GET['filter_' . str_replace('pa_', '', $taxonomy)]);
                                    ?>
                                    <label class="attribute-checkbox">
                                        <input type="checkbox" 
                                               name="filter_<?php echo esc_attr(str_replace('pa_', '', $taxonomy)); ?>[]" 
                                               value="<?php echo esc_attr($slug); ?>"
                                               <?php checked($checked); ?>
                                               data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
                                               data-attribute-name="<?php echo esc_attr(str_replace('pa_', '', $taxonomy)); ?>"
                                               class="attribute-filter">
                                        <?php echo esc_html($term_data['name']); ?>
                                        <span class="count">(<?php echo intval($term_data['count']); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Rating Filter -->
            <?php if (function_exists('woocommerce_rating_filter')): ?>
            <div class="filter-section">
                <h4>Рейтинг</h4>
                <?php the_widget('WC_Widget_Rating_Filter'); ?>
            </div>
            <?php endif; ?>
            
            <div class="down-filters-btn">
				<button type="button" class="apply-filters-btn">Применить</button>
				<a href="<?php echo esc_url($this->get_clear_filters_url()); ?>" class="clear-filters-btn <?if (!$has_active_filters) echo 'hide'?>">Сбросить все</a>
            </div>
        </aside>
        <?php
    }
    
    /**
     * Проверка наличия активных фильтров
     */
    private function has_active_filters() {
        if (isset($_GET['min_price']) || isset($_GET['max_price']) || isset($_GET['rating_filter'])) {
            return true;
        }
        
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            if (isset($_GET['filter_' . $attribute->attribute_name]) && !empty($_GET['filter_' . $attribute->attribute_name])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Получить URL для сброса фильтров
     */
    private function get_clear_filters_url() {
        if (is_product_category()) {
            return get_term_link(get_queried_object());
        }
        return wc_get_page_permalink('shop');
    }
    
    /**
     * Обработка AJAX запроса загрузки товаров
     */
    public function load_products_ajax() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_ajax_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $paged = isset($filters['paged']) ? intval($filters['paged']) : 1;
        
        // Построение аргументов запроса
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 12,
            'paged' => $paged,
            'post_status' => 'publish',
        );
        
        $tax_query = array();
        $meta_query = array();
        
        // Категория
        if (!empty($filters['product_cat'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => sanitize_text_field($filters['product_cat']),
            );
        }
        
        // Атрибуты
        foreach ($filters as $key => $value) {
            if (strpos($key, 'filter_') === 0 && !empty($value)) {
                $attribute_name = str_replace('filter_', '', $key);
                $taxonomy = 'pa_' . $attribute_name;
                $terms = is_array($value) ? $value : explode(',', sanitize_text_field($value));
                
                if (!empty($terms)) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => $terms,
                        'operator' => 'IN',
                    );
                }
            }
        }
        
        // Цена
        if (!empty($filters['min_price']) && !empty($filters['max_price'])) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => array(floatval($filters['min_price']), floatval($filters['max_price'])),
                'type' => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }
        
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        // Сортировка
        if (!empty($filters['orderby'])) {
            $this->apply_sorting($args, $filters['orderby']);
        }
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            ob_start();
            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }
            $html = ob_get_clean();
            
            // Пагинация
            $pagination_html = $this->generate_pagination($query->max_num_pages, $paged);
            
            wp_send_json_success(array(
                'html' => $html,
                'max_pages' => $query->max_num_pages,
                'pagination' => $pagination_html,
                'total_products' => $query->found_posts,
                'products_per_page' => $args['posts_per_page'],
            ));
        } else {
            wp_send_json_success(array(
                'html' => '<div class="no-products-found">Товары не найдены</div>',
                'max_pages' => 0,
                'pagination' => '',
                'total_products' => 0,
                'products_per_page' => $args['posts_per_page'],
            ));
        }
        
        wp_reset_postdata();
        wp_die();
    }
    
    /**
     * Применение сортировки
     */
    private function apply_sorting(&$args, $orderby) {
        switch ($orderby) {
            case 'popularity':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'total_sales';
                $args['order'] = 'DESC';
                break;
            case 'rating':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_wc_average_rating';
                $args['order'] = 'DESC';
                break;
            case 'date':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            case 'price':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'ASC';
                break;
            case 'price-desc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_price';
                $args['order'] = 'DESC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
        }
    }
    
    /**
     * Генерация пагинации
     */
    private function generate_pagination($max_pages, $current_page) {
        if ($max_pages <= 1) {
            return '';
        }
        
        $pages = paginate_links(array(
            'base' => '#',
            'format' => '?paged=%#%',
            'current' => $current_page,
            'total' => $max_pages,
            'type' => 'array',
            'prev_text' => '«',
            'next_text' => '»',
        ));
        
        if (is_array($pages)) {
            return '<div class="pagination">' . implode('', $pages) . '</div>';
        }
        
        return '';
    }
    
    /**
     * Обработка фильтров атрибутов в основном запросе
     */
    public function process_attribute_filters($query) {
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category())) {
            $tax_query = $query->get('tax_query', array());
            
            $attributes = wc_get_attribute_taxonomies();
            
            foreach ($attributes as $attribute) {
                $param_name = 'filter_' . $attribute->attribute_name;
                if (isset($_GET[$param_name]) && !empty($_GET[$param_name])) {
                    $values = explode(',', sanitize_text_field($_GET[$param_name]));
                    $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                    
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => $values,
                        'operator' => 'IN'
                    );
                }
            }
            
            if (!empty($tax_query)) {
                $query->set('tax_query', $tax_query);
            }
        }
    }
    
    /**
     * Рендер AJAX магазина через шорткод
     */
    public function render_ajax_shop($atts) {
        $atts = shortcode_atts(array(
            'products_per_page' => 12,
            'show_filters' => 'yes',
        ), $atts);
        
        ob_start();
        ?>
        <div class="shop-container" data-products-per-page="<?php echo intval($atts['products_per_page']); ?>">
            <div class="shop-layout">
                <?php if ($atts['show_filters'] === 'yes'): ?>
                    <?php $this->display_filters(); ?>
                <?php endif; ?>
                
                <div class="shop-content" id="shop-content">
                    <?php do_action('woocommerce_before_shop_loop'); ?>
                    
                    <div class="products-grid" id="products-grid">
                        <div class="loading-spinner"></div>
                    </div>
                    
                    <div class="pagination-wrapper" id="pagination-wrapper"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Инициализация модуля
function wc_ajax_module_init() {
    return WC_Ajax_Module::get_instance();
}
add_action('plugins_loaded', 'wc_ajax_module_init');