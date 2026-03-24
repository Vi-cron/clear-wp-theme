<?php
/**
 * Theme options for WooCommerce filters
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

// Register settings
function wc_theme_register_settings() {
    register_setting('wc_theme_options_group', 'wc_theme_active_filters', 'wc_theme_sanitize_filters');
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

// Options page HTML
function wc_theme_options_page() {
    $active_filters = get_option('wc_theme_active_filters', array('price', 'categories', 'attributes'));
    ?>
    <div class="wrap">
        <h1>Настройки фильтров каталога</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wc_theme_options_group'); ?>
            <table class="form-table">
                <table>
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
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2>Как добавить атрибуты для фильтров</h2>
        <p>1. Перейдите в <a href="<?php echo admin_url('edit.php?post_type=product&page=product_attributes'); ?>">Товары → Атрибуты</a></p>
        <p>2. Создайте атрибуты (например: Размер, Цвет, Материал)</p>
        <p>3. Добавьте значения атрибутов к товарам</p>
        <p>4. Фильтры по атрибутам появятся автоматически</p>
    </div>
    <?php
}

// Get active filters
function wc_theme_get_active_filters() {
    return get_option('wc_theme_active_filters', array('price', 'categories', 'attributes'));
}