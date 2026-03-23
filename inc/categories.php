<?php
// Get category icons menu
function get_wp_category_icons(){ 
	$categories = get_terms(array(
                      'taxonomy' => 'product_cat',
                      'hide_empty' => true,
                      'parent' => 0,
                      'number' => 5,
    ));

if (!empty($categories) && !is_wp_error($categories)) {
    // Собираем ID категорий, у которых нет изображения
    $categories_without_image = array();
    $categories_images = array();
    
    foreach ($categories as $category) {
        $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
        $image = wp_get_attachment_url($thumbnail_id);
        
        if ($image) {
            $categories_images[$category->term_id] = $image;
        } else {
            $categories_without_image[] = $category->term_id;
        }
    }
    
    // Если есть категории без изображений, получаем товары одним запросом
    if (!empty($categories_without_image)) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories_without_image,
                ),
            ),
            'fields' => 'ids',
        );
        
        $products = get_posts($args);
        
        // Группируем товары по категориям
        $products_by_category = array();
        foreach ($products as $product_id) {
            $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            foreach ($product_cats as $cat_id) {
                if (in_array($cat_id, $categories_without_image)) {
                    if (!isset($products_by_category[$cat_id])) {
                        $products_by_category[$cat_id] = array();
                    }
                    $products_by_category[$cat_id][] = $product_id;
                }
            }
        }
        
        // Для каждой категории берем первый товар и его изображение
        foreach ($products_by_category as $cat_id => $product_ids) {
            if (!empty($product_ids)) {
                $first_product_id = $product_ids[0];
                $thumbnail_id = get_post_thumbnail_id($first_product_id);
                
                if ($thumbnail_id) {
                    $categories_images[$cat_id] = wp_get_attachment_url($thumbnail_id);
                } else {
                    // Ищем первую прикрепленную картинку
                    $attachments = get_attached_media('image', $first_product_id);
                    if (!empty($attachments)) {
                        $first_attachment = reset($attachments);
                        $categories_images[$cat_id] = wp_get_attachment_url($first_attachment->ID);
                    }
                }
            }
        }
    }
    
    // Выводим категории
    foreach ($categories as $category) {
        $image = isset($categories_images[$category->term_id]) ? $categories_images[$category->term_id] : false;
        ?>
        <a href="<?php echo get_term_link($category); ?>" class="category-icon-item">
            <div class="category-icon-circle">
                <?php if ($image): ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($category->name); ?>" loading="lazy">
                <?php else: ?>
                    <span style="font-size: 24px;">🛋️</span>
                <?php endif; ?>
            </div>
            <span class="category-icon-name"><?php echo $category->name; ?></span>
        </a>
        <?php
    }
}
}