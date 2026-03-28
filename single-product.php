<?php
/**
 * Single Product Page
 */

get_header();

// Получаем глобальный объект товара 
global $product;

// Если $product не инициализирован, пробуем получить его через wc_get_product()
if (empty($product) || !is_object($product)) {
    $product_id = get_the_ID();
    $product = wc_get_product($product_id);
}

// Проверяем, что товар существует и видим
if (!$product || !is_object($product) || !$product->is_visible()) {
    echo '<div class="container"><p>Товар не найден или недоступен для просмотра.</p></div>';
    get_footer();
    return;
}
?>

<div class="container single-product-container">
    <div class="single-product-wrapper">
        <!-- Основная информация о товаре -->
        <div class="product-main-info">
            <!-- Левая колонка: Галерея изображений -->
            <div class="product-gallery">
                <?php 
                if (function_exists('wc_theme_product_gallery')) {
                    wc_theme_product_gallery($product);
                } else {
                    // Fallback на стандартную галерею WooCommerce
                    woocommerce_show_product_images();
                }
                ?>
            </div>
            
            <!-- Правая колонка: Информация о товаре -->
            <div class="product-summary">
                <h1 class="product-title-single"><?php the_title(); ?></h1>
                
                <!-- Рейтинг товара -->
                <?php if ($product->get_average_rating() > 0): ?>
                <div class="product-rating-single">
                    <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                    <span class="rating-count">(<?php echo $product->get_review_count(); ?> отзывов)</span>
                </div>
                <?php endif; ?>
                
                <!-- Цена -->
                <div class="product-price-single">
                    <?php echo $product->get_price_html(); ?>
                </div>
                
                <!-- Краткое описание -->
                <div class="product-short-description">
                    <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
                </div>
                
                <!-- Форма добавления в корзину  -->
                <div class="product-cart-form">
                    <?php 
                    if (function_exists('wc_theme_add_to_cart_form')) {
                        wc_theme_add_to_cart_form($product);
                    } else {
                        // Fallback на стандартную форму
                        if ($product->is_in_stock()) {
                            woocommerce_template_single_add_to_cart();
                        } else {
                            echo '<div class="out-of-stock-message">Товар временно отсутствует</div>';
                        }
                    }
                    ?>
                </div>
                
                <!-- Дополнительная информация -->
                <div class="product-meta">
                    <div class="product-sku">
                        <strong>Артикул:</strong> <?php echo $product->get_sku() ?: 'Нет'; ?>
                    </div>
                    <div class="product-categories">
                        <strong>Категории:</strong>
                        <?php echo wc_get_product_category_list($product->get_id(), ', '); ?>
                    </div>
					<?php
					$tag_list = wc_get_product_tag_list($product->get_id(), ', ');
					if ($tag_list): ?>
					<div class="product-tags">
						<strong>Теги:</strong>
						<?php echo $tag_list; ?>
					</div>
					<?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Табы с описанием -->
        <div class="product-tabs">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="description">Описание</button>
                <button class="tab-btn" data-tab="attributes">Характеристики</button>
                <button class="tab-btn" data-tab="reviews">Отзывы (<?php echo $product->get_review_count(); ?>)</button>
            </div>
            
            <div class="tabs-content">
                <div class="tab-pane active" id="tab-description">
                    <div class="product-description">
                        <?php echo apply_filters('the_content', $product->get_description()); ?>
                    </div>
                </div>
                
                <div class="tab-pane" id="tab-attributes">
                    <div class="product-attributes">
                        <?php 
                        if (function_exists('wc_theme_product_attributes')) {
                            wc_theme_product_attributes($product);
                        } else {
                            // Стандартные атрибуты WooCommerce
                            wc_display_product_attributes($product);
                        }
                        ?>
                    </div>
                </div>
                
                <div class="tab-pane" id="tab-reviews">
                    <div class="product-reviews">
                        <?php comments_template(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Похожие товары -->
        <?php 
        if (function_exists('wc_theme_related_products')) {
            wc_theme_related_products($product);
        } else {
            woocommerce_upsell_display();
            woocommerce_related_products();
        }
        ?>
        
    </div>
</div>

<?php get_footer(); ?>