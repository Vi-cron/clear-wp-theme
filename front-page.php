<?php get_header(); ?>

<!-- Hero Slider -->
<div class="hero-slider swiper">
    <div class="swiper-wrapper">
        <?php
        // You can add slides here dynamically using ACF or hardcode for now
        $slides = array(
            array(
                'image' => 'https://via.placeholder.com/1920x600',
                'title' => 'Качественные диваны',
                'subtitle' => 'Для вашего уюта',
                'button_text' => 'Смотреть каталог',
                'button_link' => '/shop/'
            ),
            array(
                'image' => 'https://via.placeholder.com/1920x600',
                'title' => 'Скидки до 30%',
                'subtitle' => 'На все модели',
                'button_text' => 'Узнать больше',
                'button_link' => '/sale/'
            )
        );
        
        foreach ($slides as $slide): ?>
            <div class="swiper-slide" style="background-image: url('<?php echo $slide['image']; ?>')">
                <div class="slide-content">
                    <h2><?php echo $slide['title']; ?></h2>
                    <p><?php echo $slide['subtitle']; ?></p>
                    <a href="<?php echo $slide['button_link']; ?>" class="slide-btn"><?php echo $slide['button_text']; ?></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="swiper-pagination"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
</div>

<div class="container">
    <!-- Featured Products -->
    <section class="featured-products">
        <h2 class="section-title">Популярные товары</h2>
        
        <div class="products-grid">
            <?php
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => 8,
                'meta_key' => 'total_sales',
                'orderby' => 'meta_value_num',
                'order' => 'DESC'
            );
            
            $featured_products = new WP_Query($args);
            
            if ($featured_products->have_posts()):
                while ($featured_products->have_posts()): $featured_products->the_post();
                    global $product;
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="<?php the_permalink(); ?>">
                                <?php echo woocommerce_get_product_thumbnail(); ?>
                            </a>
                            <button class="wishlist-btn" data-product-id="<?php echo get_the_ID(); ?>">
                                ❤️
                            </button>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            <div class="product-price">
                                <?php echo $product->get_price_html(); ?>
                            </div>
                            <button class="add-to-cart-btn" data-product-id="<?php echo get_the_ID(); ?>">
                                В корзину
                            </button>
                        </div>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </div>
    </section>
    
    <!-- About Section -->
    <section class="about-section">
        <div class="container">
            <div class="about-content">
                <h2>О нашей компании</h2>
                <div class="about-text">
                    <p>Мы предлагаем широкий ассортимент качественной мебели для вашего дома. Наша продукция отличается надежностью, стильным дизайном и доступными ценами.</p>
                    <p>Более 10 лет мы радуем наших клиентов комфортом и уютом. Все наши изделия сертифицированы и соответствуют высоким стандартам качества.</p>
                </div>
            </div>
            <div class="about-image">
                <img src="https://via.placeholder.com/600x400" alt="О компании" loading="lazy">
            </div>
        </div>
    </section>
</div>

<?php get_footer(); ?>