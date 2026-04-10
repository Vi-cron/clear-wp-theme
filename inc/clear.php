<?php

remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'feed_links',2);
remove_action('wp_head', 'feed_links_extra',3);
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
remove_action('wp_head', 'wp_shortlink_wp_head',10,0);

function delRSS() {
    wp_die('<p>RSS-ленты на сайте не доступны!</p>');
}
add_action('do_feed',      'delRSS', 1);
add_action('do_feed_rdf',  'delRSS', 1);
add_action('do_feed_rss',  'delRSS', 1);
add_action('do_feed_rss2', 'delRSS', 1);
add_action('do_feed_atom', 'delRSS', 1);


add_filter('emoji_svg_url', '__return_empty_string');
add_action( 'init', 'disable_emojis' );

function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', 'disable_emojis_tinymce' );
}

function disable_emojis_tinymce( $plugins ) {
 if ( is_array( $plugins ) ) 
	return array_diff( $plugins, array( 'wpemoji' ) );
	else return array();
}

/*add_action('get_header', 'start_inline_css_removal');
function start_inline_css_removal() {
    ob_start('clean_inline_css');
}

function clean_inline_css($buffer) {
    // Не применяем для админ-панели
    if (is_admin() || (is_user_logged_in() && current_user_can('manage_options'))) {
        return $buffer;
    }
    
    // Список ID стилей для удаления
    $style_ids = [
        'wp-img-auto-sizes-contain-inline-css',
        'wp-block-library-inline-css',
        'classic-theme-styles-inline-css',
        'global-styles-inline-css',
        'woocommerce-inline-inline-css',
    ];
    
    foreach ($style_ids as $id) {
        $buffer = preg_replace(
            '/<style[^>]*id=[\'"]' . preg_quote($id, '/') . '[\'"][^>]*>.*?<\/style>/s',
            '',
            $buffer
        );
    }
    
    return $buffer;
}*/