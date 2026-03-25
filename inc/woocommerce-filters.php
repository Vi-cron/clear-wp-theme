<?php
/**
 * WooCommerce filters widgets
 */

function get_category_attributes_optimized($category_id) {
    global $wpdb;
    
    // Получаем ID товаров в категории одним запросом
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
    
    // Прямой SQL-запрос для получения атрибутов
    $query = $wpdb->prepare("
        SELECT DISTINCT 
            taxonomy, 
            term_id,
            term_taxonomy_id
        FROM {$wpdb->prefix}term_relationships AS tr
        INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tr.object_id IN ($ids_string)
            AND tt.taxonomy LIKE 'pa_%%'
    ");
    
    $results = $wpdb->get_results($query);
    
    // Форматируем результат
    $attributes = array();
    foreach ($results as $row) {
        $term = get_term($row->term_id, $row->taxonomy);
        if ($term && !is_wp_error($term)) {
            $attributes[$row->taxonomy]['label'] = wc_attribute_label($row->taxonomy);
            $attributes[$row->taxonomy]['terms'][$term->slug] = $term->name;
        }
    }
    
    return $attributes;
}

// Display filters sidebar
function wc_theme_display_filters() {
    $active_filters = wc_theme_get_active_filters();
    
    if (empty($active_filters)) {
        return;
    }
    
    echo '<aside class="shop-filters">';
    echo '<div class="filters-header">';
        echo '<h3 class="filters-title">Фильтры</h3>';
        // Display clear filters button
        wc_theme_clear_filters_button();
    echo '</div>';
    
    // Price filter
    if (in_array('price', $active_filters) && function_exists('woocommerce_price_filter')) {
        echo '<div class="filter-section">';
        echo '<h4>Цена</h4>';
        the_widget('WC_Widget_Price_Filter');
        echo '</div>';
    }
    
    // Categories filter
    if (in_array('categories', $active_filters) && function_exists('woocommerce_product_categories_widget')) {
        echo '<div class="filter-section">';
        echo '<h4>Категории</h4>';
        the_widget('WC_Widget_Product_Categories', array(
            'dropdown' => 0,
            'count' => 1,
            'hierarchical' => 1,
            'show_children_only' => 0,
            'hide_empty' => 1
        ));
        echo '</div>';
    }
    
    // Attributes filters
    if (in_array('attributes', $active_filters)) {
        wc_theme_display_attribute_filters();
    }
    
    // Rating filter
    if (in_array('rating', $active_filters) && function_exists('woocommerce_rating_filter')) {
        echo '<div class="filter-section">';
        echo '<h4>Рейтинг</h4>';
        the_widget('WC_Widget_Rating_Filter');
        echo '</div>';
    }

    echo '<div class="down-filters-btn">';
        echo '<button type="button" class="apply-filters-btn">Применить</button>';
        // Display clear filters button
        wc_theme_clear_filters_button();
    echo '</div>';    
    
    echo '</aside>';
}

// Display attribute filters dynamically
function wc_theme_display_attribute_filters() {
    // Get all product attributes
    $attributes = wc_get_attribute_taxonomies();
    
    if (empty($attributes)) {
        echo '<p class="no-attributes">Нет доступных атрибутов. <a href="' . admin_url('edit.php?post_type=product&page=product_attributes') . '">Создайте атрибуты</a> для товаров.</p>';
        return;
    }
    
    foreach ($attributes as $attribute) {
        $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
        
        // Check if taxonomy exists and has terms
        if (taxonomy_exists($taxonomy)) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
            ));
            
            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<div class="filter-section">';
                echo '<h4>' . esc_html($attribute->attribute_label) . '</h4>';
                
                // Display as checkboxes
                echo '<div class="filter-attributes">';
                foreach ($terms as $term) {
                    $checked = isset($_GET['filter_' . $attribute->attribute_name]) && 
                               in_array($term->slug, (array) $_GET['filter_' . $attribute->attribute_name]);
                    ?>
                    <label class="attribute-checkbox">
                        <input type="checkbox" 
                               name="filter_<?php echo esc_attr($attribute->attribute_name); ?>[]" 
                               value="<?php echo esc_attr($term->slug); ?>"
                               <?php checked($checked); ?>
                               data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
                               data-attribute-name="<?php echo esc_attr($attribute->attribute_name); ?>"
                               class="attribute-filter">
                        <?php echo esc_html($term->name); ?>
                        <span class="count">(<?php echo $term->count; ?>)</span>
                    </label>
                    <?php
                }
                echo '</div>';
                echo '</div>';
            }
        }
    }
}

// Enqueue filter scripts - удаляем обработчик перезагрузки
function wc_theme_filter_scripts() {
    if (is_shop() || is_product_category() || is_product_tag()) {
        // Никаких обработчиков на чекбоксы! Вся логика теперь в shop-ajax.js
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Только инициализация price slider
            const priceButton = document.querySelector('.price_slider_amount .button');
            if (priceButton) {
                priceButton.setAttribute('type', 'button');
            }
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'wc_theme_filter_scripts');

// Process attribute filters
function wc_theme_process_attribute_filters($query) {
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category())) {
        $tax_query = $query->get('tax_query', array());
        
        // Get all product attributes
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
add_action('pre_get_posts', 'wc_theme_process_attribute_filters');

// Clear filters button
function wc_theme_clear_filters_button() {
    $has_filters = false;
    $attributes = wc_get_attribute_taxonomies();
    
    foreach ($attributes as $attribute) {
        if (isset($_GET['filter_' . $attribute->attribute_name]) && !empty($_GET['filter_' . $attribute->attribute_name])) {
            $has_filters = true;
            break;
        }
    }
    
    if (isset($_GET['min_price']) || isset($_GET['max_price']) || isset($_GET['rating_filter'])) {
        $has_filters = true;
    }
    
    if ($has_filters) {
        echo '<div class="clear-filters">';
        echo '<a href="' . wc_get_page_permalink('shop') . '" class="clear-filters-btn">Сбросить все</a>';
        echo '</div>';
    }
}