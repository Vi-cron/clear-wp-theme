<?php
/**
 * Product page functions
 */

if (!defined('ABSPATH')) exit;

// Enqueue product page scripts and styles
function wc_theme_product_page_scripts() {
    if (is_product()) {
        // Fancybox для lightbox
        wp_enqueue_style('fancybox-css', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css', array(), '5.0');
        wp_enqueue_script('fancybox-js', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js', array(), '5.0', true);
        
        // Product page styles
        wp_enqueue_style('wc-theme-product', get_template_directory_uri() . '/assets/css/product-page.css', array(), '1.0.0');
        
        // Product page scripts
        wp_enqueue_script('wc-theme-product', get_template_directory_uri() . '/assets/js/product-page.js', array('swiper-js'), '1.0.0', true);
        
        // Локализация для product page
        wp_localize_script('wc-theme-product', 'product_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ajax_nonce'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'wc_theme_product_page_scripts', 20);

/**
 * Галерея товара с Swiper слайдером
 */
function wc_theme_product_gallery($product) {
    $attachment_ids = $product->get_gallery_image_ids();
    $thumbnail_id = $product->get_image_id();
    
    // Если есть основное изображение, добавляем его в начало массива
    $all_images = array();
    if ($thumbnail_id) {
        $all_images[] = $thumbnail_id;
    }
    if (!empty($attachment_ids)) {
        $all_images = array_merge($all_images, $attachment_ids);
    }
    
    // Если нет изображений, показываем заглушку
    if (empty($all_images)) {
        echo '<div class="product-gallery-placeholder">';
        echo '<img src="' . wc_placeholder_img_src() . '" alt="Нет изображения">';
        echo '</div>';
        return;
    }
    ?>
    <div class="product-gallery-main">
        <div id="product-gallery-main-swiper" class="swiper product-gallery-main-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($all_images as $image_id): 
                    $image_url = wp_get_attachment_image_url($image_id, 'large');
                    $image_full = wp_get_attachment_image_url($image_id, 'full');
                    $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                ?>
                    <div class="swiper-slide">
                        <a href="<?php echo esc_url($image_full); ?>" class="product-gallery-lightbox" data-fancybox="product-gallery">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt ?: $product->get_name()); ?>" loading="lazy">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        
        <?php if (count($all_images) > 1): ?>
        <div class="product-gallery-thumbs">
            <div class="swiper product-gallery-thumbs-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($all_images as $image_id): 
                        $thumb_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                    ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($image_alt ?: $product->get_name()); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

add_filter('woocommerce_show_variation_price', function() { 
    return TRUE; 
});

/**
 * Единая форма добавления в корзину для всех типов товаров
 * Объединяет функционал для простых и вариативных товаров
 */
function wc_theme_add_to_cart_form($product) {
    if (!$product->is_in_stock()) {
        echo '<div class="out-of-stock-message">';
        echo '<span class="icon">❌</span>';
        echo 'Товар временно отсутствует';
        echo '</div>';
        return;
    }
    
    // Для вариативных товаров
    if ($product->is_type('variable')) {
        wc_theme_variable_add_to_cart_form($product);
    } 
    // Для простых и других типов товаров
    else {
        wc_theme_simple_add_to_cart_form($product);
    }
}

/**
 * Форма добавления в корзину для вариативных товаров
 */
function wc_theme_variable_add_to_cart_form($product) {
    $attributes = $product->get_variation_attributes();
    $available_variations = $product->get_available_variations();
    
    if (empty($attributes)) return;
    ?>
    <div class="product-variations">
        <form class="variations_form cart" method="post" enctype='multipart/form-data' data-product_id="<?php echo $product->get_id(); ?>" data-product_variations="<?php echo esc_attr(wp_json_encode($available_variations)); ?>">
            <div class="variations">
                <?php foreach ($attributes as $attribute_name => $options): 
                    // Формируем имя таксономии
                    $taxonomy = $attribute_name;
                    
                    // Получаем красивое название атрибута
                    $attribute_taxonomies = wc_get_attribute_taxonomies();
                    $attribute_label = $attribute_name;
                    
                    foreach ($attribute_taxonomies as $tax) {
                        if ('pa_' . $tax->attribute_name === $attribute_name) {
                            $attribute_label = $tax->attribute_label;
                            break;
                        }
                    }
                    
                    // Получаем выбранное значение (если есть)
                    $selected_value = isset($_REQUEST['attribute_' . $taxonomy]) 
                        ? wc_clean(stripslashes(urldecode($_REQUEST['attribute_' . $taxonomy]))) 
                        : $product->get_variation_default_attribute($taxonomy);
                ?>
                    <div class="variation-item">
                        <label for="<?php echo esc_attr($taxonomy); ?>">
                            <?php echo esc_html($attribute_label); ?>:
                        </label>
                        <div class="variation-values">
                            <?php if (is_array($options)): ?>
                                <?php foreach ($options as $option): 
                                    $option_slug = sanitize_title($option);
                                    $checked = ($selected_value === $option_slug) ? 'checked' : '';
                                    
                                    // Получаем красивое название значения
                                    $term = get_term_by('slug', $option_slug, $taxonomy);
                                    if ($term && !is_wp_error($term)) {
                                        $option_name = $term->name;
                                    } else {
                                        $all_terms = get_terms(array(
                                            'taxonomy' => $taxonomy,
                                            'hide_empty' => false,
                                            'slug' => $option_slug
                                        ));
                                        if (!empty($all_terms) && !is_wp_error($all_terms)) {
                                            $option_name = $all_terms[0]->name;
                                        } else {
                                            $option_name = $option;
                                        }
                                    }
                                ?>
                                    <label class="variation-option <?php echo $checked ? 'selected' : ''; ?>">
                                        <input type="radio" 
                                               name="attribute_<?php echo esc_attr($taxonomy); ?>" 
                                               value="<?php echo esc_attr($option_slug); ?>" 
                                               <?php echo $checked; ?>
                                               data-attribute-name="<?php echo esc_attr($taxonomy); ?>">
                                        <span class="variation-label"><?php echo esc_html($option_name); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="single_variation_wrap">
                <div class="woocommerce-variation single_variation"></div>
                <div class="woocommerce-variation-add-to-cart variations_button">
                    <?php wc_theme_cart_buttons($product); ?>
                    <input type="hidden" name="add-to-cart" value="<?php echo $product->get_id(); ?>">
                    <input type="hidden" name="product_id" value="<?php echo $product->get_id(); ?>">
                    <input type="hidden" name="variation_id" class="variation_id" value="0">
                </div>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Форма добавления в корзину для простых товаров
 */
function wc_theme_simple_add_to_cart_form($product) {
    ?>
    <form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype='multipart/form-data'>
        <?php wc_theme_cart_buttons($product); ?>
        <input type="hidden" name="add-to-cart" value="<?php echo $product->get_id(); ?>">
    </form>
    <?php
}

/**
 * Блок кнопок корзины и избранного (общий для всех типов товаров)
 */
function wc_theme_cart_buttons($product) {
    ?>
    <div class="quantity-wrapper">
        <label for="quantity">Количество:</label>
        <div class="quantity">
            <button type="button" class="quantity-minus">-</button>
            <input type="number" class="input-text qty text" step="1" min="1" max="<?php echo $product->get_stock_quantity() ?: ''; ?>" name="quantity" value="1" title="Qty" size="4" inputmode="numeric">
            <button type="button" class="quantity-plus">+</button>
        </div>
    </div>

    <div class="cart-buttons-row">
        <button type="submit" class="single-add-to-cart-btn">
            <span class="icon icon-cart">🛒</span>
            Добавить в корзину
        </button>
        
        <button type="button" class="single-wishlist-btn-icon" data-product-id="<?php echo $product->get_id(); ?>">
            <span class="icon icon-heart">❤</span>
        </button>
    </div>
    <?php
}

/**
 * Отображение атрибутов товара
 */
function wc_theme_product_attributes($product) {
    $attributes = $product->get_attributes();
    
    if (empty($attributes)) {
        echo '<p>Характеристики не указаны.</p>';
        return;
    }
    
    echo '<table class="product-attributes-table">';
    foreach ($attributes as $attribute) {
        if ($attribute->is_taxonomy()) {
            $taxonomy = $attribute->get_taxonomy();
            $name = wc_attribute_label($taxonomy);
            $terms = wp_get_post_terms($product->get_id(), $taxonomy, array('fields' => 'names'));
            $value = implode(', ', $terms);
        } else {
            $name = $attribute->get_name();
            $value = $attribute->get_options();
            $value = is_array($value) ? implode(', ', $value) : $value;
        }
        
        if (!empty($value)) {
            echo '<tr>';
            echo '<th>' . esc_html($name) . '</th>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
}

/**
 * Похожие товары
 */
function wc_theme_related_products($product) {
    $related_ids = wc_get_related_products($product->get_id(), 8);
    
    if (empty($related_ids)) return;
    
    $related_query = new WP_Query(array(
        'post_type' => 'product',
        'posts_per_page' => 8,
        'post__in' => $related_ids,
        'orderby' => 'post__in'
    ));
    
    if (!$related_query->have_posts()) return;
    ?>
    <div class="related-products">
        <h2 class="section-title">Похожие товары</h2>
        
        <div class="related-products-slider">
            <div class="swiper related-products-swiper">
                <div class="swiper-wrapper">
                    <?php while ($related_query->have_posts()): $related_query->the_post(); 
                        $related_product = wc_get_product(get_the_ID());
                        if (!$related_product) continue;
                    ?>
                        <div class="swiper-slide">
                            <div class="product-card">
                                <div class="product-image">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php echo woocommerce_get_product_thumbnail('medium'); ?>
                                    </a>
                                    <?php if ($related_product->is_on_sale()){
                                        $sale_percentage = wc_theme_get_sale_percentage($related_product);
                                        if ($sale_percentage) 
                                            echo '<span class="sale-badge">-'.$sale_percentage.'%</span>';
                                    } 
                                    ?>
                                    <button class="wishlist-btn" data-product-id="<?php echo get_the_ID(); ?>">
                                        <span class="icon icon-heart"></span>
                                    </button>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-title">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    <div class="product-price">
                                        <?php echo $related_product->get_price_html(); ?>
                                    </div>
                                </div>
                                <div class="product-info-buttons">
                                    <a class="product-info-button" href="<?php the_permalink(); ?>">
                                        Подробнее
                                    </a>
                                    <button class="product-info-button add-to-cart-btn" data-product-id="<?php echo get_the_ID(); ?>">
                                        В корзину
                                        <span class="icon icon-cart">🛒</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
    <?php
    wp_reset_postdata();
}

/**
 * Получить процент скидки
 */
function wc_theme_get_sale_percentage($product) {
    if (!$product->is_on_sale()) return '';
    
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    
    if ($regular_price > 0 && $sale_price > 0) {
        $percentage = round(100 - ($sale_price / $regular_price * 100));
        return $percentage;
    }
    return '';
}