<?php

function e($data, $echo = false) {
    if( $echo ) {
        echo "<pre>" . print_r($data, true) . "</pre>";
    }
    else {
        error_log(print_r($data, true));
    }
}

function send_json($data) {
    header("content-type: application/json");
    echo json_encode($data);
}

function init() {

    if( function_exists('acf_add_options_page') ) {
        acf_add_options_page();
    }

    register_nav_menus(
        array(
          'main_menu' => 'Main menu',
          'footer_menu' => 'Footer menu',
        )
    );

    include 'acf/fields.php';
    //include 'post_types/how-to.php';
}
add_action('init', 'init');

// setup theme
function jensnilsson_theme_setup() {
    add_image_size( 'wide', 1080, 300, true ); // cropped, wide
    add_image_size( 'square', 640, 640, true ); // cropped square
    add_image_size( '1080', 1080 ); // site width
    add_image_size( '1080.16/9', 1080, 607, true); // site width 16/9
    add_image_size( '1280', 1280); // 1280
    add_image_size( 'avatar', 50, 50, true );
    add_image_size( 'avatarx2', 100, 100, true );

    add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'jensnilsson_theme_setup' );

function request_cache_reset() {
    file_get_contents(PUBLIC_URL . '/clear-cache');
}

// clear cache on save
function clear_node_cache( $post_id ) {
    // if this is just a revision, don't clear
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    request_cache_reset();
}
add_action( 'save_post', 'clear_node_cache' );

// get data about an author
function get_full_author_profile( $author_id, $update_transient = false ) {

    if ( ( false === ( $author = get_transient( 'full_author_profile_' . $author_id ) ) ) || $update_transient == true ) {
         // this code runs when there is no valid transient set
        $author = new stdClass();

        $fields = array(
            array('display_name', 'displayName'),
            array('user_nicename', 'niceName'),
        );

        $custom_fields = array(
            array('profile_image', 'profileImage'),
            array('social_links', 'socialLinks'),
            array('profile_description', 'profileDescription'),
            array('instagram_user_id', 'instagramUserId'),
            array('website', 'website'),
            array('location', 'location'),
            array('public_email', 'email'),
        );

        // get built-in author meta
        foreach( $fields as $field ) {
            $author->$field[1] = get_the_author_meta( $field[0], $author_id );
        }

        // get custom author meta
        foreach( $custom_fields as $field ) {
            $author->$field[1] = get_field( $field[0], 'user_' . $author_id );
        }

        $author->url = get_author_posts_url($author_id, $author->niceName);

        set_transient('full_author_profile_' . $author_id, $author, YEAR_IN_SECONDS*100);
    }

    return $author;
}

function full_author_profile_updated( $user_id, $old_user_data ) {
    // update transient for the user
    get_full_author_profile( $user_id, true );

    // request a cache reset
    request_cache_reset();
}
add_action( 'profile_update', 'full_author_profile_updated', 10, 2 );

// filter post post-type
function filter_post( $post ) {
    $public_post = new stdClass();

    $public_attr = array(
        array('ID', 'id'),
        array('post_date', 'postDate'),
        array('post_date_gmt', 'postDateGMT'),
        array('post_title', 'postTitle'),
        array('post_status', 'postStatus'),
        array('post_name', 'postName'),
        array('post_modified', 'postModified'),
        array('post_modified_gmt', 'postModifiedGMT'),
        array('post_parent', 'postParent'),
        array('guid', 'guid'),
        array('post_type', 'postType'),

        array(null, 'permalink', get_permalink( $post->ID )),
        array(null, 'content', get_field( 'content', $post->ID )),
        array(null, 'contentBlocks', get_field( 'content_blocks', $post->ID )),
        array(null, 'intro', get_field( 'intro', $post->ID )),
        array(null, 'contentType', 'markdown'),
        array(null, 'heroImage', get_field( 'hero_image', $post->ID )),
        array(null, 'title', get_the_title()),
        array(null, 'author', get_full_author_profile( $post->post_author )),
        array(null, 'category', get_the_category( $post->ID )),
        array(null, 'tag', apply_filters('tags-filter', get_the_tags( $post->ID ))),
        array(null, 'template', 'post')
    );

    // filter down the post attributes
    foreach( $public_attr as $attr ) {
        if( $attr[0] == null ) {
            $public_post->$attr[1] = $attr[2];
        }
        else {
            $public_post->$attr[1] = $post->$attr[0];
        }
    }

    //$public_post->menu = get_menu_json('main');
    return $public_post;
}
add_filter( 'post-filter', 'filter_post', 10, 1 );

// filter pages
function filter_page( $post ) {
    $public_post = new stdClass();
    return $public_post;
}
add_filter( 'page-filter', 'filter_page', 10, 1 );

function filter_tags( $tags ) {

    $tags = is_array($tags) ? $tags : array();

    foreach( $tags as $index => $tag ) {
       $tags[$index] = apply_filters('tag-filter', $tag);
    }
    return $tags;
}
add_filter( 'tags-filter', 'filter_tags', 10, 1);

function filter_tag( $tag ) {
    $tag->id = $tag->term_id;
    $tag->permalink = get_tag_link($tag->id);
    return $tag;
}
add_filter( 'tag-filter', 'filter_tag', 10, 1 );


// filter for content_blocks field
function filter_content_blocks( $value, $post_id, $field ) {
    if( is_array($value) ) {
        foreach($value as $index => $block) {
            // decorate each block with a more logical name (frontend-wise) for the acf_fc_layout
            $block['blockType'] = $block['acf_fc_layout'];
            $value[$index] = $block;
        }
    }
    return $value;
}
add_filter('acf/load_value/name=content_blocks', 'filter_content_blocks', 10, 3);

function jensnilsson_get_nav_menu( $menu_name ) {
    $cleaned_menu_items = array();

    if ( ( $locations = get_nav_menu_locations() ) && isset( $locations[ $menu_name ] ) ) {
        $menu = wp_get_nav_menu_object( $locations[ $menu_name ] );

        $menu_items = wp_get_nav_menu_items($menu->term_id);

        foreach ( (array) $menu_items as $key => $menu_item ) {

            array_push(
                $cleaned_menu_items,
                array(
                    'title' => $menu_item->title,
                    'url' => $menu_item->url,
                )
            );

        }
    }

    return $cleaned_menu_items;
}


// decorates the passed object with the main menu.
function apply_main_menu( $obj ) {
    $obj->mainMenu = jensnilsson_get_nav_menu( 'main_menu' );
}
add_filter( 'apply-main-menu', 'apply_main_menu', 10, 1 );

// decorates the passed object with the sites global settings.
function apply_site_settings( $obj ) {
    $obj->siteSettings = new stdClass();
    $obj->siteSettings->googleAnalytics = get_field( 'google_analytics_tracking_code', 'options' );
    $obj->siteSettings->disqusShortname = get_field( 'disqus_shortname', 'options' );
    $obj->siteSettings->backgroundImage = get_field( 'background_image', 'options' );
    $obj->siteSettings->mapboxAccessToken = get_field( 'mapbox_api_access_token', 'options' );
    $obj->siteSettings->mapboxMapId = get_field( 'mapbox_map_id', 'options' );
    $obj->siteSettings->optimizelyExperimentId = get_field( 'optimizely_experiment_id', 'options' );

    apply_filters('apply-feed-links', $obj->siteSettings);
}
add_filter( 'apply-site-settings', 'apply_site_settings', 10, 1 );

// decorates the passed object with page-specific meta-data.
function apply_page_meta($obj) {
    $obj->pageMeta = array(
        'title' => wp_title( '|', false, 'right' ) . get_bloginfo( 'name' )
    );
}
add_filter( 'apply-page-meta', 'apply_page_meta', 10, 1);

function apply_feed_links( $obj ) {
    $obj->feeds = new stdClass();
    $obj->feeds->rdf = get_bloginfo('rdf_url');
    $obj->feeds->rss = get_bloginfo('rss_url');
    $obj->feeds->rss2 = get_bloginfo('rss2_url');
    $obj->feeds->atom = get_bloginfo('atom_url');
}
add_filter( 'apply-feed-links', 'apply_feed_links', 10, 1);
