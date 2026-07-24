<?php
/**
 * Template Name: Login Page
 */
if ( is_user_logged_in() ) { wp_redirect( home_url() ); exit; }

$login_error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio_action']) && $_POST['bio_action'] === 'login' ) {

    $username    = sanitize_user( trim( $_POST['log_user'] ?? '' ) );
    $password    = $_POST['log_pass'] ?? '';
    $remember    = isset($_POST['log_remember']);
    $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url();

    if ( empty($username) || empty($password) ) {
        $login_error = 'Please enter both your username and password.';
    } else {
        $user = wp_authenticate( $username, $password );
        if ( is_wp_error($user) ) {
            $login_error = 'Incorrect username or password. Please try again.';
        } else {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, $remember );
            do_action( 'wp_login', $user->user_login, $user );
            wp_redirect( $redirect_to );
            exit;
        }
    }
}

$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login &mdash; <?php bloginfo('name'); ?></title>
  <?php wp_head(); ?>
  <style>
    html, body {
      background: #050b1a !important;
      color: #fff !important;
      margin: 0 !important;
      padding: 0 !important;
      min-height: 100vh;
    }
    .page-header, .entry-title { display: none !important; }
    #wpadminbar { display: none !important; }
    html { margin-top: 0 !important; }
  </style>
</head>
<body <?php body_class('bio-login-page'); ?>>
<?php wp_body_open(); ?>

<!-- ═══════════ HEADER ═══════════ -->
<header id="bio-header" role="banner">
  <div class="header-inner">
    <a href="<?php echo esc_url( home_url('/') ); ?>" class="bio-logo" aria-label="Home">
      <span class="logo-icon">🔬</span>
      <span>Bio<span>Explorer</span></span>
    </a>
    <div class="header-auth">
      <a href="<?php echo esc_url( home_url('/login') ); ?>" class="btn-login">Login</a>
      <a href="<?php echo esc_url( home_url('/register') ); ?>" class="btn-register">Sign Up</a>
    </div>
  </div>
</header>

<!-- ═══════════ LOGIN FORM ═══════════ -->
<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-logo">
      <span class="logo-icon">🔬</span>
      <h1>Welcome Back</h1>
      <p>Login to continue your biology journey</p>
    </div>

    <div class="auth-tabs">
      <a href="<?php echo esc_url( home_url('/login') ); ?>" class="auth-tab active">Login</a>
      <a href="<?php echo esc_url( home_url('/register') ); ?>" class="auth-tab">Register</a>
    </div>

    <?php if ( isset($_GET['locked']) ) : ?>
    <div class="auth-notice auth-notice--warn">
      🔒 Please <strong>login or create a free account</strong> to view that page.
    </div>
    <?php endif; ?>

    <?php if ( $login_error ) : ?>
    <div class="auth-notice auth-notice--error">
      ❌ <?php echo esc_html($login_error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="bioLoginForm">
      <input type="hidden" name="bio_action" value="login">
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

      <p>
        <label for="log_user">Username or Email</label>
        <input type="text" id="log_user" name="log_user" required
               value="<?php echo esc_attr( $_POST['log_user'] ?? '' ); ?>"
               placeholder="Enter your username or email"
               autocomplete="username">
      </p>

      <p>
        <label for="log_pass">Password</label>
        <input type="password" id="log_pass" name="log_pass" required
               placeholder="Enter your password"
               autocomplete="current-password">
      </p>

      <p style="display:flex;align-items:center;gap:.5rem;margin:-.3rem 0 .8rem">
        <input type="checkbox" id="log_remember" name="log_remember"
               style="width:auto;accent-color:var(--accent-blue)">
        <label for="log_remember" style="font-size:.85rem;color:var(--text-muted);margin:0;cursor:pointer">
          Remember me
        </label>
      </p>

      <p>
        <input type="submit" value="Login →">
      </p>
    </form>

    <div class="auth-footer">
      <p>No account yet? <a href="<?php echo esc_url( home_url('/register') ); ?>">Create one free →</a></p>
      <p style="margin-top:.5rem">
        <a href="<?php echo esc_url(home_url('/lost-password')); ?>">Forgot your password?</a>
      </p>
    </div>

  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
