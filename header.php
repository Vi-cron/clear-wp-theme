<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <!-- Logo -->
            <div class="logo">
                
                <?php 
                $logo_url = wc_theme_get_logo();
                if ($logo_url) {
                    echo '<a href="'. home_url().'"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '"/></a>';
                } else {
                    // Резервный текст или логотип по умолчанию
                    bloginfo('name');
                }
                ?>
            </div>
            
            <!-- Category Icons -->
            <div class="category-icons">
                <?php get_wp_category_icons(); ?>
			</div>
            
            <!-- Header Right -->
            <div class="header-right">

            <?php 
            $phones = wc_theme_get_phones();
            if (!empty($phones)) {
                echo '<div class="phones"><span class="icon icon-phone">📞</span>';
                foreach ($phones as $phone) {
                // Очищаем номер для ссылки tel:
                $clean_phone = preg_replace('/[^0-9+]/', '', trim($phone));
                echo '<a href="tel:' . esc_attr($clean_phone) . '">' . esc_html(trim($phone)) . '</a>';
                }
                echo '</div>';
            }

            if (wc_theme_is_button_enabled('wishlist')):?>
                <a href="<?php echo home_url('/wishlist/'); ?>" class="wishlist-link">
                    <span class="icon icon-heart">❤️</span>
                    <span class="wishlist-count">
                        <?php 
                        if (function_exists('get_wishlist_count')) {
                            echo get_wishlist_count();
                        } else {
                            echo '0';
                        }
                        ?>
                    </span>
                </a>
            <?endif;
            
            if (wc_theme_is_button_enabled('cart')):?>
                <a href="<?php echo wc_get_cart_url(); ?>" class="cart-link">
                    <span class="icon icon-cart">🛒</span>
                    <span class="cart-count">
                        <?php 
                        if (function_exists('WC') && WC()->cart) {
                            echo WC()->cart->get_cart_contents_count();
                        } else {
                            echo '0';
                        }
                        ?>
                    </span>
                </a>
            <?endif?>
                
            </div>
        </div>
    </div>
    
    <!-- Main Navigation -->
    <nav class="main-navigation">
        <div class="container">
            <button class="menu-toggle" aria-label="Menu">☰</button>
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_class' => 'primary-menu',
                    'container' => false,
                    'fallback_cb' => false,
                ));
            } else {
                echo '<ul class="primary-menu"><li><a href="' . home_url() . '">Главная</a></li></ul>';
            }
            ?>
        </div>
    </nav>
    
    <!-- Breadcrumbs -->
    <?php if (!is_front_page() && !is_home()): ?>
    <div class="breadcrumbs">
        <div class="container">
            <?php if (function_exists('woocommerce_breadcrumb')): ?>
                <?php woocommerce_breadcrumb(); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</header>

<main class="site-main">