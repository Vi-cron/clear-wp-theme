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
                <?php if (has_custom_logo()): ?>
                    <?php the_custom_logo(); ?>
                <?php else: ?>
                    <a href="<?php echo home_url(); ?>">
                        <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo.jpg" alt="<?php bloginfo('name'); ?>" width="150" height="80">
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Category Icons -->
            <div class="category-icons">
                <?php get_wp_category_icons(); ?>
			</div>
            
            <!-- Header Right -->
            <div class="header-right">
                <div class="phones">
					<span class="icon icon-phone">📞</span>
                    <a href="tel:+71111111111">+7(1111) 11-11-11</a>
                    <a href="tel:+71111111111">+7(1111) 11-11-11</a>
                </div>
                
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