</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-widgets">
            <div class="footer-widget">
                <h3>О нас</h3>
                <p>Интернет-магазин качественной мебели для вашего дома.</p>
            </div>
            
            <div class="footer-widget">
                <h3>Информация</h3>
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_class' => '',
                    'container' => false,
                    'depth' => 1,
                ));
                ?>
            </div>
            
            <div class="footer-widget">
                <h3>Контакты</h3>
                <?
                $phones = wc_theme_get_phones();
                if (!empty($phones)) {
                    echo '<ul>';
                    foreach ($phones as $phone) {
                    // Очищаем номер для ссылки tel:
                    $clean_phone = preg_replace('/[^0-9+]/', '', trim($phone));
                    echo '<li><a href="tel:' . esc_attr($clean_phone) . '">' . esc_html(trim($phone)) . '</a></li>';
                }
                echo '</ul>';
                }
                ?>
            </div>
            
            <div class="footer-widget">
                <h3>Режим работы</h3>
                <ul>
                    <li>Пн-Пт: 10:00 - 20:00</li>
                    <li>Сб-Вс: 11:00 - 18:00</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. Все права защищены.</p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>