<?php
// AJAX handler for loading products
function load_products_ajax() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
    $paged = isset($filters['paged']) ? intval($filters['paged']) : 1;
    
    // Build query args
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => 12,
        'paged' => $paged,
        'post_status' => 'publish',
    );
    
    // Add tax queries for filters
    $tax_query = array();
    $meta_query = array();
    
    // Category filter
    if (!empty($filters['product_cat'])) {
        $tax_query[] = array(
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => sanitize_text_field($filters['product_cat']),
        );
    }
    
    // Attribute filters
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
    
    // Price filter
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
    
    // Sorting
    if (!empty($filters['orderby'])) {
        switch ($filters['orderby']) {
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
    } else {
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
    }
    
    $query = new WP_Query($args);
    
    // Get total products count
    $total_products = $query->found_posts;
    $products_per_page = $args['posts_per_page'];
    
    if ($query->have_posts()) {
        ob_start();
        
        // No ul wrapper - just product cards
        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }
        
        $html = ob_get_clean();
        
        // Generate pagination
        $big = 999999999;
        $pagination = paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => $paged,
            'total' => $query->max_num_pages,
            'type' => 'array',
            'prev_text' => '«',
            'next_text' => '»',
        ));
        
        $pagination_html = '';
        if (is_array($pagination)) {
            $pagination_html = '<div class="pagination">' . implode('', $pagination) . '</div>';
        }
        
        wp_send_json_success(array(
            'html' => $html,
            'max_pages' => $query->max_num_pages,
            'pagination' => $pagination_html,
            'total_products' => $total_products,
            'products_per_page' => $products_per_page,
        ));
    } else {
        wp_send_json_success(array(
            'html' => '<div class="no-products-found">Товары не найдены</div>',
            'max_pages' => 0,
            'pagination' => '',
            'total_products' => 0,
            'products_per_page' => $products_per_page,
        ));
    }
    
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_load_products_ajax', 'load_products_ajax');
add_action('wp_ajax_nopriv_load_products_ajax', 'load_products_ajax');