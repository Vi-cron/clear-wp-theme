<?php
/**
 * Product card template
 */
global $product;

// Ensure visibility
if (empty($product) || !$product->is_visible()) {
    return;
}
?>
<div <?php wc_product_class('product-card', $product); ?>>
    <div class="product-image">
        <a href="<?php the_permalink(); ?>">
            <?php echo woocommerce_get_product_thumbnail('medium'); ?>
        </a>
        <?php if ($product->is_on_sale()): ?>
            <span class="sale-badge"><?php echo esc_html__('Sale', 'woocommerce'); ?></span>
        <?php endif; ?>
        <button class="wishlist-btn" data-product-id="<?php echo get_the_ID(); ?>">
            <span class="icon icon-heart">❤️</span>
        </button>
    </div>
    <div class="product-info">
        <h3 class="product-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        
        <?php if ($product->get_average_rating()): ?>
            <div class="product-rating">
                <?php echo wc_get_rating_html($product->get_average_rating()); ?>
            </div>
        <?php endif; ?>
        
        <div class="product-price">
            <?php echo $product->get_price_html(); ?>
        </div>
        
    </div>
    <div class="product-info-buttons">
        <a class="product-info-button" href="<?php the_permalink(); ?>">
            <?php echo esc_html__('Подробнее', 'wc-theme'); ?>
        </a>
        <button class="product-info-button add-to-cart-btn" data-product-id="<?php echo get_the_ID(); ?>">
            <?php echo esc_html__('В корзину', 'wc-theme'); ?>
            <span class="icon icon-cart">🛒</span>
        </button>
    </div>
</div>