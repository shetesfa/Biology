</div><!-- .page-content-wrap -->

<!-- ═══════════ FOOTER ═══════════ -->
<footer id="bio-footer" role="contentinfo">
    <div class="footer-inner">

        <!-- Brand -->
        <div class="footer-brand">
            <a href="<?php echo esc_url( home_url('/') ); ?>" class="bio-logo">
                <span class="logo-icon">🔬</span>
                <span>Bio<span>Explorer</span></span>
            </a>
            <p>An interactive human biology education platform with 3D models, AI tutoring, and comprehensive lessons.</p>
        </div>

        <!-- Body Systems -->
        <div class="footer-col">
            <h4>Body Systems</h4>
            <ul>
                <li><a href="<?php echo esc_url( home_url('/skeletal-system') ); ?>">🦴 Skeletal System</a></li>
                <li><a href="<?php echo esc_url( home_url('/circulatory-system') ); ?>">❤️ Circulatory System</a></li>
                <li><a href="<?php echo esc_url( home_url('/respiratory-system') ); ?>">🫁 Respiratory System</a></li>
                <li><a href="<?php echo esc_url( home_url('/nervous-system') ); ?>">⚡ Nervous System</a></li>
                <li><a href="<?php echo esc_url( home_url('/muscular-system') ); ?>">💪 Muscular System</a></li>
                <li><a href="<?php echo esc_url( home_url('/digestive-system') ); ?>">🍽️ Digestive System</a></li>
            </ul>
        </div>

        <!-- Featured Organs -->
        <div class="footer-col">
            <h4>Featured Organs</h4>
            <ul>
                <li><a href="<?php echo esc_url( home_url('/heart') ); ?>">❤️ Heart</a></li>
                <li><a href="<?php echo esc_url( home_url('/brain') ); ?>">🧠 Brain</a></li>
                <li><a href="<?php echo esc_url( home_url('/lungs') ); ?>">🫁 Lungs</a></li>
                <li><a href="<?php echo esc_url( home_url('/liver') ); ?>">🔴 Liver</a></li>
                <li><a href="<?php echo esc_url( home_url('/kidneys') ); ?>">🫘 Kidneys</a></li>
                <li><a href="<?php echo esc_url( home_url('/stomach') ); ?>">🥘 Stomach</a></li>
            </ul>
        </div>

        <!-- Explore -->
        <div class="footer-col">
            <h4>Explore</h4>
            <ul>
                <li><a href="<?php echo esc_url( home_url('/3d-explorer') ); ?>">🚀 3D Body Explorer</a></li>
                <li><a href="<?php echo esc_url( home_url('/ai-tutor') ); ?>">🤖 AI Tutor</a></li>
                <?php if ( ! is_user_logged_in() ) : ?>
                <li><a href="<?php echo esc_url( home_url('/login') ); ?>">🔑 Login</a></li>
                <li><a href="<?php echo esc_url( home_url('/register') ); ?>">📝 Register</a></li>
                <?php else : ?>
                <li><a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">🚪 Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>

    </div><!-- .footer-inner -->

    <div class="footer-bottom">
        <span>&copy; <?php echo date('Y'); ?> BioExplorer &mdash; Interactive Human Biology</span>
        <span>Built with WordPress &amp; Elementor</span>
    </div>
</footer>

<!-- ═══════════ FLOATING BUTTONS ═══════════ -->
<button id="dark-toggle" title="Toggle dark/light mode" aria-label="Toggle theme">🌓</button>
<button id="scroll-top" title="Scroll to top" aria-label="Scroll to top">↑</button>

<!-- ═══════════ GLOBAL SCRIPTS ═══════════ -->
<script>
/* ── Mobile hamburger ── */
(function() {
    var btn = document.getElementById('hamburger');
    var nav = document.getElementById('bio-nav');
    if (!btn || !nav) return;
    btn.addEventListener('click', function() {
        nav.classList.toggle('open');
        btn.setAttribute('aria-expanded', nav.classList.contains('open'));
    });
})();

/* ── Scroll to top ── */
(function() {
    var btn = document.getElementById('scroll-top');
    if (!btn) return;
    window.addEventListener('scroll', function() {
        btn.classList.toggle('visible', window.scrollY > 400);
    });
    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();

/* ── Dark/Light mode ── */
(function() {
    var btn = document.getElementById('dark-toggle');
    if (!btn) return;
    var root = document.documentElement;
    var stored = localStorage.getItem('bioTheme');

    function setTheme(mode) {
        if (mode === 'light') {
            root.style.setProperty('--bg-dark', '#f0f4f8');
            root.style.setProperty('--bg-card', 'rgba(0,0,0,0.05)');
            root.style.setProperty('--text-white', '#111827');
            root.style.setProperty('--text-muted', '#4b5563');
            btn.textContent = '☀️';
        } else {
            root.style.removeProperty('--bg-dark');
            root.style.removeProperty('--bg-card');
            root.style.removeProperty('--text-white');
            root.style.removeProperty('--text-muted');
            btn.textContent = '🌓';
        }
        localStorage.setItem('bioTheme', mode);
    }

    if (stored) setTheme(stored);

    btn.addEventListener('click', function() {
        var current = localStorage.getItem('bioTheme') || 'dark';
        setTheme(current === 'dark' ? 'light' : 'dark');
    });
})();

/* ── Scroll fade-in animations ── */
(function() {
    var els = document.querySelectorAll('.fade-up');
    if (!els.length) return;
    var obs = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    els.forEach(function(el) { obs.observe(el); });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
