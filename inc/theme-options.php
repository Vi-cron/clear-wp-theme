<?php
/**
* Theme options for WooCommerce filters and general settings
*/

// Add theme options page
function wc_theme_add_admin_menu() {
    add_theme_page(
        'Настройки каталога',
        'Настройки каталога',
        'manage_options',
        'wc-theme-options',
        'wc_theme_options_page'
    );
}
add_action('admin_menu', 'wc_theme_add_admin_menu');

// Enqueue media uploader for logo
function wc_theme_admin_enqueue_scripts($hook) {
    if ($hook !== 'appearance_page_wc-theme-options') {
        return;
    }
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'wc_theme_admin_enqueue_scripts');

// Register settings
function wc_theme_register_settings() {
    // Existing filters setting
    register_setting('wc_theme_options_group', 'wc_theme_active_filters', 'wc_theme_sanitize_filters');
    
    // New general settings
    register_setting('wc_theme_options_group', 'wc_theme_logo', 'esc_url_raw');
    register_setting('wc_theme_options_group', 'wc_theme_phones', 'sanitize_textarea_field');
    
    // Social networks
    register_setting('wc_theme_options_group', 'wc_theme_social_vk', 'esc_url_raw');
    register_setting('wc_theme_options_group', 'wc_theme_social_tg', 'esc_url_raw');
    register_setting('wc_theme_options_group', 'wc_theme_social_wa', 'esc_url_raw');
    
    // Buttons (Cart, Wishlist, Compare)
    register_setting('wc_theme_options_group', 'wc_theme_show_cart', 'wc_theme_sanitize_checkbox');
    register_setting('wc_theme_options_group', 'wc_theme_show_wishlist', 'wc_theme_sanitize_checkbox');
    register_setting('wc_theme_options_group', 'wc_theme_show_compare', 'wc_theme_sanitize_checkbox');
}
add_action('admin_init', 'wc_theme_register_settings');

// Sanitize filters array
function wc_theme_sanitize_filters($input) {
    if (!is_array($input)) {
        return array();
    }
    $allowed_filters = array('price', 'categories', 'attributes', 'rating');
    return array_intersect($input, $allowed_filters);
}

// Sanitize checkbox (1 or 0)
function wc_theme_sanitize_checkbox($input) {
    return !empty($input) ? 1 : 0;
}

// Options page HTML
function wc_theme_options_page() {
    // Get existing options with defaults
    $active_filters = get_option('wc_theme_active_filters', array('price', 'categories', 'attributes'));
    $logo_url = get_option('wc_theme_logo', '');
    $phones = get_option('wc_theme_phones', '');
    $social_vk = get_option('wc_theme_social_vk', '');
    $social_tg = get_option('wc_theme_social_tg', '');
    $social_wa = get_option('wc_theme_social_wa', '');
    
    // Buttons defaults to 1 (enabled)
    $show_cart = get_option('wc_theme_show_cart', 1);
    $show_wishlist = get_option('wc_theme_show_wishlist', 1);
    $show_compare = get_option('wc_theme_show_compare', 1);
    ?>
    <div class="wrap">
        <h1>Настройки темы магазина</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wc_theme_options_group'); ?>
            
            <h2 class="title">Фильтры каталога</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Активные фильтры</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc_theme_active_filters[]" value="price" <?php checked(in_array('price', $active_filters)); ?>>
                            Фильтр по цене
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_theme_active_filters[]" value="categories" <?php checked(in_array('categories', $active_filters)); ?>>
                            Фильтр по категориям
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_theme_active_filters[]" value="attributes" <?php checked(in_array('attributes', $active_filters)); ?>>
                            Фильтры по атрибутам
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_theme_active_filters[]" value="rating" <?php checked(in_array('rating', $active_filters)); ?>>
                            Фильтр по рейтингу
                        </label>
                        <p class="description">Выберите фильтры, которые будут отображаться на странице каталога</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Общие настройки</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Логотип</th>
                    <td>
                        <input type="text" name="wc_theme_logo" id="wc_theme_logo" value="<?php echo esc_attr($logo_url); ?>" class="regular-text">
                        <button type="button" class="button" id="wc_theme_logo_upload">Загрузить логотип</button>
                        <button type="button" class="button" id="wc_theme_logo_remove">Удалить</button>
                        <p class="description">Рекомендуемый размер: 200x50px</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Номера телефонов</th>
                    <td>
                        <textarea name="wc_theme_phones" rows="3" class="large-text"><?php echo esc_textarea($phones); ?></textarea>
                        <p class="description">Введите номера телефонов, каждый с новой строки</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Социальные сети</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">ВКонтакте</th>
                    <td>
                        <input type="url" name="wc_theme_social_vk" value="<?php echo esc_attr($social_vk); ?>" class="regular-text" placeholder="https://vk.com/...">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Telegram</th>
                    <td>
                        <input type="url" name="wc_theme_social_tg" value="<?php echo esc_attr($social_tg); ?>" class="regular-text" placeholder="https://t.me/...">
                    </td>
                </tr>
                <tr>
                    <th scope="row">WhatsApp</th>
                    <td>
                        <input type="url" name="wc_theme_social_wa" value="<?php echo esc_attr($social_wa); ?>" class="regular-text" placeholder="https://wa.me/...">
                    </td>
                </tr>
            </table>

            <h2 class="title">Кнопки действий</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Отображение кнопок</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc_theme_show_cart" value="1" <?php checked($show_cart, 1); ?>>
                            Корзина
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_theme_show_wishlist" value="1" <?php checked($show_wishlist, 1); ?>>
                            Избранное
                        </label><br>
                        <label>
                            <input type="checkbox" name="wc_theme_show_compare" value="1" <?php checked($show_compare, 1); ?>>
                            Сравнить
                        </label>
                        <p class="description">Управление видимостью кнопок в карточке товара</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>
        <h2>Как добавить атрибуты для фильтров</h2>
        <p>1. Перейдите в <a href="<?php echo admin_url('edit.php?post_type=product&page=product_attributes'); ?>">Товары → Атрибуты</a></p>
        <p>2. Создайте атрибуты (например: Размер, Цвет, Материал)</p>
        <p>3. Добавьте значения атрибутов к товарам</p>
        <p>4. Фильтры по атрибутам появятся автоматически</p>
    </div>

    <script>
        jQuery(document).ready(function($){
            var mediaUploader;
            $('#wc_theme_logo_upload').click(function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media.frames.file = wp.media({
                    title: 'Выберите логотип',
                    button: { text: 'Использовать логотип' },
                    library: { type: 'image' },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#wc_theme_logo').val(attachment.url);
                });
                mediaUploader.open();
            });
            $('#wc_theme_logo_remove').click(function(e) {
                e.preventDefault();
                $('#wc_theme_logo').val('');
            });
        });
    </script>
    <?php
}

// Get active filters
function wc_theme_get_active_filters() {
    return get_option('wc_theme_active_filters', array('price', 'categories', 'attributes'));
}

// Get logo URL
function wc_theme_get_logo() {
    return get_option('wc_theme_logo', '');
}

// Get phones (returns array of lines)
function wc_theme_get_phones() {
    $phones = get_option('wc_theme_phones', '');
    if (empty($phones)) {
        return array();
    }
    return array_filter(explode("\n", $phones));
}

// Get social links
function wc_theme_get_social($network) {
    $option_name = 'wc_theme_social_' . $network;
    return get_option($option_name, '');
}

// Check if button is enabled
function wc_theme_is_button_enabled($button_type) {
    $option_name = 'wc_theme_show_' . $button_type;
    return get_option($option_name, 1) == 1;
}