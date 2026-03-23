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
                <ul>
                    <li>Телефон: +7(8422) 53-54-41</li>
                    <li>Телефон: +7(8422) 53-53-27</li>
                    <li>Email: info@example.com</li>
                </ul>
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