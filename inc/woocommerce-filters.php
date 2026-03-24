<?php
/**
 * WooCommerce filters widgets
 */

// Display filters sidebar
function wc_theme_display_filters() {
    $active_filters = wc_theme_get_active_filters();
    
    if (empty($active_filters)) {
        return;
    }
    
    echo '<aside class="shop-filters">';
    echo '<h3 class="filters-title">Фильтры</h3>';
    
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

// Enqueue filter scripts
function wc_theme_filter_scripts() {
    if (is_shop() || is_product_category() || is_product_tag()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle attribute filter checkboxes
            const checkboxes = document.querySelectorAll('.attribute-filter');
            const urlParams = new URLSearchParams(window.location.search);
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const taxonomy = this.dataset.taxonomy;
                    const paramName = 'filter_' + taxonomy.replace('pa_', '');
                    const selectedValues = [];
                    
                    // Get all checked values for this taxonomy
                    document.querySelectorAll(`.attribute-filter[data-taxonomy="${taxonomy}"]:checked`).forEach(cb => {
                        selectedValues.push(cb.value);
                    });
                    
                    // Remove existing param
                    urlParams.delete(paramName);
                    
                    // Add new param if values exist
                    if (selectedValues.length > 0) {
                        urlParams.set(paramName, selectedValues.join(','));
                    }
                    
                    // Update URL and reload
                    window.location.search = urlParams.toString();
                });
            });
            
            // Price filter submit
            const priceFilter = document.querySelector('.price_slider_wrapper');
            if (priceFilter) {
                const priceSlider = priceFilter.querySelector('.price_slider');
                if (priceSlider && !priceSlider.hasAttribute('data-initialized')) {
                    priceSlider.setAttribute('data-initialized', 'true');
                    // WooCommerce price slider initializes automatically
                }
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
        echo '<a href="' . wc_get_page_permalink('shop') . '" class="clear-filters-btn">Сбросить все фильтры</a>';
        echo '</div>';
    }
}