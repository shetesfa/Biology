<?php
/**
 * Biology Child Theme - functions.php
 */

/* ── 1. Enqueue styles ─────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'biology_enqueue_styles', 20 );
function biology_enqueue_styles() {
    // Enqueue parent theme base stylesheet
    wp_enqueue_style( 'hello-elementor-style', get_template_directory_uri() . '/style.css' );

    // Child style depends on both parent style AND Hello Elementor's reset/theme CSS
    // so it always loads LAST and our overrides win over reset.css (body { background:#fff })
    $deps = [ 'hello-elementor-style' ];
    foreach ( [ 'hello-elementor-theme-style', 'hello-elementor-reset', 'hello-elementor' ] as $handle ) {
        if ( wp_style_is( $handle, 'registered' ) ) {
            $deps[] = $handle;
        }
    }
    wp_enqueue_style(
        'biology-child-style',
        get_stylesheet_uri(),
        $deps,
        wp_get_theme()->get( 'Version' )
    );
}

/* ── 2. Register menus ─────────────────────────────────────── */
add_action( 'after_setup_theme', 'biology_register_menus' );
function biology_register_menus() {
    register_nav_menus(['primary' => 'Primary Navigation', 'footer' => 'Footer Navigation']);
}

/* ── 3. Enable registration ─────────────────────────────────── */
add_action( 'init', 'biology_allow_registration' );
function biology_allow_registration() {
    if ( ! get_option('users_can_register') ) {
        update_option('users_can_register', 1);
    }
}

/* ── 4. Hide admin bar for non-admins ───────────────────────── */
add_filter( 'show_admin_bar', function($show) {
    return current_user_can('manage_options') ? $show : false;
});

/* ── 5. Redirect wp-login.php → our custom /login page ─────── */
add_action( 'init', 'biology_redirect_wp_login' );
function biology_redirect_wp_login() {
    $request = $_SERVER['REQUEST_URI'] ?? '';
    // Only redirect GET requests, not POST (WP needs POST for actual login)
    if ( strpos($request, 'wp-login.php') !== false && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
        $login_page = get_page_by_path('login');
        if ( $login_page && ! is_user_logged_in() ) {
            wp_redirect( get_permalink($login_page->ID) );
            exit();
        }
    }
}

/* ── 6. Show welcome message after registration ─────────────── */
add_action( 'wp_head', 'biology_welcome_notice' );
function biology_welcome_notice() {
    if ( isset($_GET['registered']) && $_GET['registered'] === '1' ) {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var m = document.createElement("div");
            m.style.cssText = "position:fixed;top:80px;left:50%;transform:translateX(-50%);background:#00aaff;color:#fff;padding:.9rem 2rem;border-radius:50px;z-index:99999;font-weight:700;box-shadow:0 8px 24px rgba(0,170,255,0.4);white-space:nowrap;font-family:inherit";
            m.textContent = "✅ Welcome to BioExplorer! Your account is ready.";
            document.body.appendChild(m);
            setTimeout(function(){ m.style.transition="opacity .5s"; m.style.opacity="0"; setTimeout(function(){ m.remove(); }, 500); }, 4000);
        });
        </script>';
    }
}

/* ── 7. Add body classes ────────────────────────────────────── */
add_filter( 'body_class', function($classes) {
    $classes[] = 'bio-site';
    if ( is_user_logged_in() ) $classes[] = 'bio-logged-in';
    return $classes;
});

/* ── 8. LOCK PAGES — guests redirected to login ─────────────── */
add_action( 'template_redirect', 'biology_protect_pages' );
function biology_protect_pages() {
    // Allow if logged in
    if ( is_user_logged_in() ) return;
    // Allow home/front page
    if ( is_front_page() || is_home() ) return;
    // Allow login and register pages
    if ( is_page(['login', 'register', 'lost-password']) ) return;
    // Allow search, 404, etc.
    if ( is_search() || is_404() ) return;

    // Everything else → redirect to login
    $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $login_page  = get_page_by_path('login');
    $target      = $login_page ? get_permalink($login_page->ID) : home_url('/login/');

    wp_redirect( add_query_arg([
        'locked'      => '1',
        'redirect_to' => urlencode($current_url),
    ], $target) );
    exit();
}

/* ── 9. Remove all default WordPress meta from <head> ───────── */
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
// Remove default login/register links from wp_head
remove_action('wp_head', 'rel_canonical');

/* ── 10. Prevent non-admins accessing wp-admin dashboard ────── */
add_action('admin_init', function() {
    if ( ! current_user_can('manage_options') && ! wp_doing_ajax() ) {
        wp_redirect( home_url() );
        exit();
    }
});

/* ── 11. Redirect WP lost password → our custom page ────────── */
add_filter( 'lostpassword_url', function( $url, $redirect ) {
    $page = get_page_by_path('lost-password');
    if ( $page ) return get_permalink($page->ID);
    return home_url('/lost-password/');
}, 10, 2 );

/* ── 12. Also allow lost-password page without login ────────── */
// (already handled in protect_pages via is_page check — adding slug here)
add_action( 'template_redirect', 'biology_allow_lostpassword', 1 );
function biology_allow_lostpassword() {
    if ( is_page('lost-password') ) {
        remove_action('template_redirect', 'biology_protect_pages');
    }
}

