<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Human Body Explorer – Interactive Biology Learning Platform">
    <?php wp_head(); ?>
    <!-- Critical override: Hello Elementor reset.css sets body{background:#fff} — force dark theme -->
    <style>
    html, body, body.bio-site {
        background-color: #050b1a !important;
        background: #050b1a !important;
        color: #ffffff !important;
    }
    </style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ═══════════ HEADER ═══════════ -->
<header id="bio-header" role="banner">
    <div class="header-inner">

        <!-- Logo -->
        <a href="<?php echo esc_url( home_url('/') ); ?>" class="bio-logo" aria-label="Home">
            <span class="logo-icon">🔬</span>
            <span>Bio<span>Explorer</span></span>
        </a>

        <!-- Primary Navigation -->
        <nav class="bio-nav" id="bio-nav" role="navigation" aria-label="Main navigation">
            <a href="<?php echo esc_url( home_url('/') ); ?>"
               class="<?php echo is_front_page() ? 'active' : ''; ?>">Home</a>

            <div class="dropdown">
                <a href="<?php echo esc_url( home_url('/systems') ); ?>">Systems</a>
                <div class="dropdown-menu">
                    <a href="<?php echo esc_url( home_url('/skeletal-system') ); ?>">🦴 Skeletal</a>
                    <a href="<?php echo esc_url( home_url('/circulatory-system') ); ?>">❤️ Circulatory</a>
                    <a href="<?php echo esc_url( home_url('/respiratory-system') ); ?>">🫁 Respiratory</a>
                    <a href="<?php echo esc_url( home_url('/nervous-system') ); ?>">⚡ Nervous</a>
                    <a href="<?php echo esc_url( home_url('/muscular-system') ); ?>">💪 Muscular</a>
                    <a href="<?php echo esc_url( home_url('/digestive-system') ); ?>">🍽️ Digestive</a>
                </div>
            </div>

            <div class="dropdown">
                <a href="<?php echo esc_url( home_url('/organs') ); ?>">Organs</a>
                <div class="dropdown-menu">
                    <a href="<?php echo esc_url( home_url('/heart') ); ?>">❤️ Heart</a>
                    <a href="<?php echo esc_url( home_url('/brain') ); ?>">🧠 Brain</a>
                    <a href="<?php echo esc_url( home_url('/lungs') ); ?>">🫁 Lungs</a>
                    <a href="<?php echo esc_url( home_url('/liver') ); ?>">🔴 Liver</a>
                    <a href="<?php echo esc_url( home_url('/kidneys') ); ?>">🫘 Kidneys</a>
                    <a href="<?php echo esc_url( home_url('/stomach') ); ?>">🥘 Stomach</a>
                </div>
            </div>

            <a href="<?php echo esc_url( home_url('/3d-explorer') ); ?>"
               class="<?php echo is_page('3d-explorer') ? 'active' : ''; ?>">3D Explorer</a>

            <a href="<?php echo esc_url( home_url('/ai-tutor') ); ?>"
               class="<?php echo is_page('ai-tutor') ? 'active' : ''; ?>">AI Tutor</a>
        </nav>

        <!-- Auth Area -->
        <div class="header-auth">
            <?php if ( is_user_logged_in() ) : ?>
                <?php $user = wp_get_current_user(); ?>
                <div class="header-user">
                    <div class="user-avatar" title="<?php echo esc_attr( $user->display_name ); ?>">
                        <?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?>
                    </div>
                    <span class="user-name"><?php echo esc_html( $user->display_name ); ?></span>
                    <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="btn-logout">Logout</a>
                </div>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url('/login') ); ?>" class="btn-login">Login</a>
                <a href="<?php echo esc_url( home_url('/register') ); ?>" class="btn-register">Sign Up</a>
            <?php endif; ?>
        </div>

        <!-- Hamburger (mobile) -->
        <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>

    </div><!-- .header-inner -->
</header>

<!-- Page content wrapper -->
<div class="page-content-wrap">
