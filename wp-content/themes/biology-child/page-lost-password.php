<?php
/**
 * Template Name: Lost Password Page
 */
if ( is_user_logged_in() ) { wp_redirect( home_url() ); exit; }

$error    = '';
$password = '';
$found_user = null;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio_action']) && $_POST['bio_action'] === 'lostpass' ) {
    $email_or_user = sanitize_text_field( trim( $_POST['lp_user'] ?? '' ) );

    if ( empty($email_or_user) ) {
        $error = 'Please enter your email address or username.';
    } else {
        $user = is_email($email_or_user)
            ? get_user_by('email', $email_or_user)
            : get_user_by('login', $email_or_user);

        if ( ! $user ) {
            $error = 'No account found with that email or username.';
        } else {
            // Generate a new random password and update the user
            $new_password = wp_generate_password( 10, false );
            wp_set_password( $new_password, $user->ID );
            $found_user = $user;
            $password   = $new_password;
        }
    }
}

get_header();
?>

<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-logo">
      <span class="logo-icon">🔬</span>
      <h1><?php echo $password ? '✅ Password Reset!' : '🔑 Forgot Password?'; ?></h1>
      <p><?php echo $password ? 'Your new password is shown below' : 'Enter your email or username'; ?></p>
    </div>

    <?php if ( $error ) : ?>
    <div class="auth-notice auth-notice--error">❌ <?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <?php if ( $password ) : ?>
      <!-- Show new password -->
      <div style="background:rgba(0,200,100,0.08);border:1px solid rgba(0,200,100,0.3);border-radius:12px;padding:1.4rem;text-align:center;margin-bottom:1.5rem;">
        <p style="color:#88aacc;font-size:.8rem;margin-bottom:.6rem;letter-spacing:.05em;text-transform:uppercase;">Account</p>
        <p style="color:#fff;font-size:1rem;font-weight:600;margin-bottom:1.2rem;"><?php echo esc_html($found_user->user_login); ?></p>
        <p style="color:#88aacc;font-size:.8rem;margin-bottom:.6rem;letter-spacing:.05em;text-transform:uppercase;">Your New Password</p>
        <div style="background:rgba(0,0,0,0.3);border:1px solid rgba(0,170,255,0.4);border-radius:8px;padding:.9rem 1.2rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
          <span id="pw-display" style="color:#00ffcc;font-size:1.3rem;font-weight:800;letter-spacing:.08em;font-family:monospace;"><?php echo esc_html($password); ?></span>
          <button onclick="navigator.clipboard.writeText('<?php echo esc_js($password); ?>');this.textContent='✅ Copied!';setTimeout(()=>this.textContent='📋 Copy',2000);"
                  style="background:rgba(0,170,255,0.15);border:1px solid rgba(0,170,255,0.4);color:#00aaff;padding:.4rem .9rem;border-radius:6px;cursor:pointer;font-size:.8rem;white-space:nowrap;">
            📋 Copy
          </button>
        </div>
        <p style="color:#88aacc;font-size:.78rem;margin-top:.8rem;">⚠️ Copy this password now — you won't see it again.</p>
      </div>
      <div style="text-align:center;">
        <a href="<?php echo esc_url(home_url('/login')); ?>"
           style="display:inline-block;padding:.85rem 2rem;background:linear-gradient(135deg,#00aaff,#0066cc);color:white;border-radius:50px;font-weight:700;text-decoration:none;">
          Login Now →
        </a>
      </div>

    <?php else : ?>
      <!-- Show form -->
      <form method="POST" action="">
        <input type="hidden" name="bio_action" value="lostpass">
        <p>
          <label for="lp_user">Email Address or Username</label>
          <input type="text" id="lp_user" name="lp_user" required
                 value="<?php echo esc_attr($_POST['lp_user'] ?? ''); ?>"
                 placeholder="Enter your email or username"
                 autocomplete="email">
        </p>
        <p><input type="submit" value="Get My Password →"></p>
      </form>
      <div class="auth-footer">
        <p>Remembered it? <a href="<?php echo esc_url(home_url('/login')); ?>">Back to Login →</a></p>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php get_footer(); ?>
