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