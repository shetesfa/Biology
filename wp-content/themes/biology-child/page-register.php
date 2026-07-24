<?php
/**
 * Template Name: Register Page
 */
if ( is_user_logged_in() ) { wp_redirect( home_url() ); exit; }

$errors = [];

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio_action']) && $_POST['bio_action'] === 'register' ) {

    $username = sanitize_user( trim( $_POST['reg_user'] ?? '' ) );
    $email    = sanitize_email( trim( $_POST['reg_email'] ?? '' ) );
    $password = trim( $_POST['reg_pass'] ?? '' );
    $confirm  = trim( $_POST['reg_confirm'] ?? '' );

    if ( empty($username) )                  { $errors[] = 'Please enter a username.'; }
    elseif ( strlen($username) < 3 )         { $errors[] = 'Username must be at least 3 characters.'; }
    elseif ( ! validate_username($username) ) { $errors[] = 'Username can only contain letters, numbers, spaces, and underscores.'; }
    elseif ( username_exists( $username ) )  { $errors[] = 'That username is taken. Please choose another.'; }

    if ( empty($email) )          { $errors[] = 'Please enter your email address.'; }
    elseif ( ! is_email($email) ) { $errors[] = 'Please enter a valid email address.'; }
    elseif ( email_exists($email) ){ $errors[] = 'That email is already registered. Try logging in instead.'; }

    if ( strlen($password) < 6 )       { $errors[] = 'Password must be at least 6 characters.'; }
    elseif ( $password !== $confirm )  { $errors[] = 'Passwords do not match. Please retype them.'; }

    if ( empty($errors) ) {
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error($user_id) ) {
            $errors[] = $user_id->get_error_message();
        } else {
            wp_update_user(['ID' => $user_id, 'display_name' => $username]);
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, true );
            do_action( 'wp_login', $username, get_user_by('id', $user_id) );
            wp_redirect( home_url('/?registered=1') );
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
  <title>Register &mdash; <?php bloginfo('name'); ?></title>
  <?php wp_head(); ?>
  <style>
    /* ===== FORCE DARK BACKGROUND — overrides everything ===== */
    html, body { background:#050b1a !important; color:#fff !important; margin:0 !important; padding:0 !important; }
    #wpadminbar, .page-header, .entry-title, .site-header, .site-footer,
    .elementor, #content, .site-main, .page-content { display:none !important; }

    /* ===== AUTH LAYOUT ===== */
    #bio-register-wrap {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    #bio-reg-header {
      position: fixed;
      top: 0; left: 0; width: 100%;
      z-index: 9999;
      background: rgba(5,11,26,0.97);
      border-bottom: 1px solid rgba(255,255,255,0.12);
      backdrop-filter: blur(20px);
    }
    #bio-reg-header .h-inner {
      max-width: 1300px; margin: 0 auto;
      padding: 0 2rem; height: 70px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .bio-logo {
      display: flex; align-items: center; gap: .5rem;
      font-size: 1.2rem; font-weight: 800; color: #fff; text-decoration: none;
    }
    .bio-logo .logo-icon { font-size: 1.5rem; }
    .bio-logo span span { color: #00aaff; }
    .h-btns { display: flex; gap: .75rem; align-items: center; }
    .h-btn-login {
      padding: .45rem 1.2rem; border-radius: 50px;
      border: 1px solid rgba(255,255,255,0.25); color: #fff;
      font-size: .88rem; font-weight: 600; text-decoration: none;
      transition: .3s;
    }
    .h-btn-login:hover { background: rgba(255,255,255,0.08); }
    .h-btn-signup {
      padding: .45rem 1.2rem; border-radius: 50px;
      background: #00aaff; color: #fff;
      font-size: .88rem; font-weight: 600; text-decoration: none;
      transition: .3s;
    }
    .h-btn-signup:hover { background: #0088dd; }

    /* ===== FORM AREA ===== */
    #bio-reg-body {
      flex: 1;
      display: flex; align-items: center; justify-content: center;
      padding: 100px 1rem 3rem;
      background: linear-gradient(135deg, #020a18, #0a1628, #050b1a);
    }
    .reg-card {
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 20px;
      padding: 2.5rem;
      width: 100%; max-width: 440px;
      backdrop-filter: blur(20px);
      box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    }
    .reg-logo { text-align: center; margin-bottom: 1.8rem; }
    .reg-logo .icon { font-size: 2.5rem; display: block; margin-bottom: .4rem; }
    .reg-logo h1 { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; }
    .reg-logo p  { color: #88aacc; font-size: .85rem; margin: 0; }

    .reg-tabs { display: flex; background: rgba(0,0,0,0.35); border-radius: 10px; padding: 4px; margin-bottom: 1.8rem; }
    .reg-tab {
      flex: 1; padding: .55rem; border-radius: 8px; text-align: center;
      font-size: .85rem; font-weight: 600; color: #88aacc;
      text-decoration: none; transition: .3s;
    }
    .reg-tab.active { background: #00aaff; color: #fff; }

    .reg-notice {
      border-radius: 8px; padding: .8rem 1rem;
      font-size: .88rem; line-height: 1.5; margin-bottom: 1.2rem;
      background: rgba(233,69,96,.12); border: 1px solid rgba(233,69,96,.3); color: #ff6680;
    }
    .reg-notice p { margin: .15rem 0; }

    .reg-card .field { margin-bottom: 1.2rem; }
    .reg-card label {
      display: block; font-size: .8rem; font-weight: 600;
      color: #88aacc; margin-bottom: .4rem; letter-spacing: .03em;
    }
    .reg-card input[type="text"],
    .reg-card input[type="password"] {
      width: 100%; padding: .8rem 1rem;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 8px; color: #fff;
      font-size: .95rem; outline: none;
      transition: .3s; box-sizing: border-box;
      font-family: inherit;
    }
    .reg-card input[type="text"]:focus,
    .reg-card input[type="password"]:focus {
      border-color: #00aaff;
      background: rgba(0,170,255,0.08);
      box-shadow: 0 0 0 3px rgba(0,170,255,0.15);
    }
    .str-bar-wrap { height:4px; background:#1a2a3a; border-radius:2px; margin: -.2rem 0 .4rem; }
    #str-bar { height:100%; border-radius:2px; width:0; transition:.3s; }
    #str-lbl { font-size:.75rem; color:#88aacc; min-height:1em; margin-bottom:.6rem; }

    .reg-card input[type="submit"] {
      width: 100%; padding: .85rem;
      background: linear-gradient(135deg, #00aaff, #0066cc);
      color: #fff; border: none; border-radius: 50px;
      font-size: 1rem; font-weight: 700; cursor: pointer;
      transition: .3s; font-family: inherit; margin-top: .4rem;
    }
    .reg-card input[type="submit"]:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0,170,255,0.4);
    }
    .reg-footer { text-align: center; margin-top: 1.5rem; font-size: .85rem; color: #88aacc; }
    .reg-footer a { color: #00aaff; text-decoration: none; }
    .reg-footer a:hover { text-decoration: underline; }
  </style>
</head>
<body <?php body_class('bio-register-page'); ?>>
<?php wp_body_open(); ?>

<div id="bio-register-wrap">

  <!-- HEADER -->
  <header id="bio-reg-header">
    <div class="h-inner">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="bio-logo">
        <span class="logo-icon">🔬</span>
        <span>Bio<span>Explorer</span></span>
      </a>
      <div class="h-btns">
        <a href="<?php echo esc_url(home_url('/login')); ?>" class="h-btn-login">Login</a>
        <a href="<?php echo esc_url(home_url('/register')); ?>" class="h-btn-signup">Sign Up</a>
      </div>
    </div>
  </header>

  <!-- FORM BODY -->
  <div id="bio-reg-body">
    <div class="reg-card">

      <div class="reg-logo">
        <span class="icon">🔬</span>
        <h1>Create Account</h1>
        <p>Join BioExplorer — completely free</p>
      </div>

      <div class="reg-tabs">
        <a href="<?php echo esc_url(home_url('/login')); ?>" class="reg-tab">Login</a>
        <a href="<?php echo esc_url(home_url('/register')); ?>" class="reg-tab active">Register</a>
      </div>

      <?php if ( ! empty($errors) ) : ?>
      <div class="reg-notice">
        <?php foreach ($errors as $e) : ?>
          <p>❌ <?php echo esc_html($e); ?></p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" autocomplete="off" id="bioRegForm">
        <input type="hidden" name="bio_action" value="register">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

        <div class="field">
          <label for="reg_user">Username</label>
          <input type="text" id="reg_user" name="reg_user" required
                 value="<?php echo esc_attr($_POST['reg_user'] ?? ''); ?>"
                 placeholder="Choose a username (min. 3 chars)" autocomplete="off">
        </div>

        <div class="field">
          <label for="reg_email_field">Email Address</label>
          <input type="text" id="reg_email_field" name="reg_email" required
                 value="<?php echo esc_attr($_POST['reg_email'] ?? ''); ?>"
                 placeholder="your@email.com" autocomplete="off">
        </div>

        <div class="field">
          <label for="reg_pass">Password</label>
          <input type="password" id="reg_pass" name="reg_pass" required
                 placeholder="At least 6 characters" autocomplete="new-password">
          <div class="str-bar-wrap"><div id="str-bar"></div></div>
          <div id="str-lbl"></div>
        </div>

        <div class="field">
          <label for="reg_confirm">Confirm Password</label>
          <input type="password" id="reg_confirm" name="reg_confirm" required
                 placeholder="Repeat your password" autocomplete="new-password">
        </div>

        <input type="submit" value="Create My Account →">
      </form>

      <div class="reg-footer">
        <p>Already have an account? <a href="<?php echo esc_url(home_url('/login')); ?>">Login →</a></p>
      </div>

    </div>
  </div>

</div><!-- #bio-register-wrap -->

<script>
(function(){
  var pw=document.getElementById('reg_pass'),bar=document.getElementById('str-bar'),lbl=document.getElementById('str-lbl');
  if(!pw)return;
  pw.addEventListener('input',function(){
    var v=this.value,s=0;
    if(v.length>=6)s++;if(v.length>=10)s++;
    if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    var c=['','#e94560','#ff8800','#ffcc00','#00cc88','#00ffcc'];
    var l=['','Weak','Fair','Good','Strong','Very Strong'];
    bar.style.width=(s/5*100)+'%';bar.style.background=c[s]||'#333';
    lbl.textContent=l[s]?'Strength: '+l[s]:'';lbl.style.color=c[s]||'';
  });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
