<?php
/**
 * Template Name: Home Page
 */
get_header(); ?>

<!-- ═══ HERO ═══ -->
<section class="bio-hero">
    <div class="hero-badge">🔬 Interactive Human Biology Platform</div>
    <h1>Explore the<br>Human Body</h1>
    <p class="subtitle">Discover 11 body systems, 3D organ models, and AI-powered lessons — all in one place.</p>

    <form class="hero-search" role="search" action="<?php echo esc_url( home_url('/') ); ?>" method="GET">
        <input type="text" name="s" placeholder="Search organs, systems, functions…" aria-label="Search biology topics">
        <button type="submit">Search</button>
    </form>

    <div class="hero-btns">
        <a href="<?php echo esc_url( home_url('/3d-explorer') ); ?>" class="btn-primary">🚀 Open 3D Explorer</a>
        <a href="<?php echo esc_url( home_url('/ai-tutor') ); ?>" class="btn-outline">🤖 Ask AI Tutor</a>
    </div>
</section>

<!-- ═══ STATS BAR ═══ -->
<div class="stats-bar">
    <div class="stat-item"><div class="stat-number">11</div><div class="stat-label">Body Systems</div></div>
    <div class="stat-item"><div class="stat-number">78</div><div class="stat-label">Human Organs</div></div>
    <div class="stat-item"><div class="stat-number">37T</div><div class="stat-label">Body Cells</div></div>
    <div class="stat-item"><div class="stat-number">3D</div><div class="stat-label">Interactive Models</div></div>
</div>

<!-- ═══ BODY SYSTEMS ═══ -->
<section class="bio-section">
    <div class="section-header fade-up">
        <div class="section-tag">Explore</div>
        <h2>Body Systems</h2>
        <p>Click any system to explore its organs, functions, and 3D models</p>
    </div>
    <div class="systems-grid">
        <a href="<?php echo esc_url( home_url('/skeletal-system') ); ?>" class="system-card fade-up" style="--card-color: rgba(200,200,255,0.2)">
            <span class="system-icon">🦴</span>
            <h3>Skeletal System</h3>
            <p>206 bones that give structure, protect organs, and enable movement.</p>
            <span class="card-link">Learn more →</span>
            <div class="accent-bar"></div>
        </a>
        <a href="<?php echo esc_url( home_url('/circulatory-system') ); ?>" class="system-card fade-up" style="--card-color: rgba(233,69,96,0.2)">
            <span class="system-icon">❤️</span>
            <h3>Circulatory System</h3>
            <p>Heart and 60,000 miles of blood vessels delivering oxygen to every cell.</p>
            <span class="card-link">Learn more →</span>
            <div class="accent-bar" style="background:var(--accent-red)"></div>
        </a>
        <a href="<?php echo esc_url( home_url('/respiratory-system') ); ?>" class="system-card fade-up" style="--card-color: rgba(0,200,255,0.15)">
            <span class="system-icon">🫁</span>
            <h3>Respiratory System</h3>
            <p>Lungs and airways that exchange oxygen and carbon dioxide 20,000×/day.</p>
            <span class="card-link">Learn more →</span>
            <div class="accent-bar" style="background:#00ccff"></div>
        </a>
        <a href="<?php echo esc_url( home_url('/nervous-system') ); ?>" class="system-card fade-up" style="--card-color: rgba(255,200,0,0.1)">
            <span class="system-icon">⚡</span>
            <h3>Nervous System</h3>
            <p>Brain, spinal cord, and 86 billion neurons controlling every action.</p>
            <span class="card-link">Learn more →</span>
            <div class="accent-bar" style="background:#ffcc00"></div>
        </a>
        <a href="<?php echo esc_url( home_url('/muscular-system') ); ?>" class="system-card fade-up" style="--card-color: rgba(255,100,100,0.12)">
            <span class="system-icon">💪</span>
            <h3>Muscular System</h3>
            <p>Over 600 muscles that power movement, posture, and vital organ function.</p>
            <span class="card-link">Learn more →</span>
            <div class="accent-bar" style="background:#ff6464"></div>
        </a>
        <a href="<?php echo esc_url( home_url('/digestive-system') ); ?>" class="system-card fade-up" style="--card-color: rgba(0,255,150,0.1)">
            <span class="system-icon">🍽️</span>
            <h3>Digestive System</h3>
            <p>30-foot long system breaking down food into energy and nutrients.</p>
            <span class="card-link">Learn more →</span>
            <div class="accent-bar" style="background:#00cc88"></div>
        </a>
    </div>
</section>

<!-- ═══ DAILY FACT ═══ -->
<div class="fact-section">
    <div class="fact-inner">
        <h2>💡 Did You Know?</h2>
        <p id="dailyFact">Loading an amazing biology fact…</p>
        <button class="btn-new-fact" onclick="newFact()">Next Fact 🎲</button>
    </div>
</div>

<!-- ═══ FEATURED ORGANS ═══ -->
<section class="bio-section">
    <div class="section-header fade-up">
        <div class="section-tag">Featured</div>
        <h2>Major Organs</h2>
        <p>Explore our most important organs in detail</p>
    </div>
    <div class="organs-grid">
        <?php
        $organs = [
            ['❤️','Heart','/heart'], ['🧠','Brain','/brain'], ['🫁','Lungs','/lungs'],
            ['🔴','Liver','/liver'], ['🫘','Kidneys','/kidneys'], ['🥘','Stomach','/stomach'],
        ];
        foreach ($organs as $o) : ?>
        <a href="<?php echo esc_url( home_url($o[2]) ); ?>" class="organ-card fade-up">
            <span class="organ-icon"><?php echo $o[0]; ?></span>
            <h4><?php echo esc_html($o[1]); ?></h4>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ═══ QUIZ ═══ -->
<div class="quiz-section">
    <div class="quiz-inner">
        <div class="section-header">
            <div class="section-tag">Test Yourself</div>
            <h2>Quick Biology Quiz</h2>
        </div>
        <div class="quiz-card" id="quizBox">
            <p class="quiz-question" id="qText">Loading question…</p>
            <div class="quiz-options" id="qOptions"></div>
            <p id="qResult" style="margin-top:1rem;font-weight:600;min-height:1.5em;"></p>
            <button class="btn-new-fact" onclick="nextQuestion()" style="margin-top:1rem">Next Question →</button>
        </div>
    </div>
</div>

<!-- ═══ CTA (logged out only) ═══ -->
<?php if ( ! is_user_logged_in() ) : ?>
<section class="bio-section" style="text-align:center; padding:4rem 1.5rem;">
    <div class="fade-up" style="max-width:560px; margin:0 auto; background:rgba(0,170,255,0.07); border:1px solid rgba(0,170,255,0.2); border-radius:24px; padding:3rem 2rem;">
        <div style="font-size:2.5rem; margin-bottom:1rem;">🎓</div>
        <h2 style="font-size:1.8rem; margin-bottom:0.8rem;">Join Free Today</h2>
        <p style="color:var(--text-muted); margin-bottom:1.8rem;">Create your free account to save progress, take quizzes, and access exclusive lessons.</p>
        <div class="hero-btns" style="justify-content:center;">
            <a href="<?php echo esc_url( home_url('/register') ); ?>" class="btn-primary">Create Free Account</a>
            <a href="<?php echo esc_url( home_url('/login') ); ?>" class="btn-outline">Login</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Daily Facts + Quiz Script -->
<script>
var facts = [
    "The human heart beats about 100,000 times every single day.",
    "Your brain uses 20% of your total body energy, despite being only 2% of your body weight.",
    "The small intestine is about 6 meters (20 feet) long — yet it fits inside your abdomen!",
    "You have 206 bones as an adult, but babies are born with around 270 — they fuse over time.",
    "The cornea of your eye has no blood supply — it gets oxygen directly from the air.",
    "Red blood cells live for about 120 days and travel 1,000 miles in that time.",
    "Your lungs contain about 300 million tiny air sacs called alveoli.",
    "The femur (thigh bone) is the longest and strongest bone in your body.",
    "The liver performs over 500 different functions in the human body.",
    "Humans share 60% of their DNA with a banana.",
    "The surface area of your lungs is about the same size as a tennis court.",
    "It takes about 12 hours for food to completely digest in your stomach."
];
var fi = Math.floor(Math.random() * facts.length);
document.getElementById('dailyFact').textContent = facts[fi];
function newFact() { fi = (fi + 1) % facts.length; document.getElementById('dailyFact').textContent = facts[fi]; }

var questions = [
    { q:"What is the largest organ in the human body?", opts:["Heart","Skin","Liver","Lung"], ans:1 },
    { q:"How many chambers does the human heart have?", opts:["2","3","4","5"], ans:2 },
    { q:"Which bone is the longest in the human body?", opts:["Humerus","Radius","Femur","Tibia"], ans:2 },
    { q:"What is the basic unit of life?", opts:["Organ","Tissue","Atom","Cell"], ans:3 },
    { q:"Which organ filters blood and produces urine?", opts:["Liver","Kidney","Spleen","Pancreas"], ans:1 },
    { q:"How many bones does an adult human have?", opts:["106","206","306","406"], ans:1 },
];
var qi = 0, answered = false;
function renderQ() {
    answered = false;
    var q = questions[qi % questions.length];
    document.getElementById('qText').textContent = q.q;
    document.getElementById('qResult').textContent = '';
    var html = '';
    q.opts.forEach(function(o, i) {
        html += '<button class="quiz-option" onclick="checkAns(this,'+i+','+q.ans+')">' + String.fromCharCode(65+i) + '. ' + o + '</button>';
    });
    document.getElementById('qOptions').innerHTML = html;
}
function checkAns(el, chosen, correct) {
    if (answered) return; answered = true;
    var btns = document.querySelectorAll('.quiz-option');
    btns[correct].classList.add('correct');
    if (chosen !== correct) { el.classList.add('wrong'); document.getElementById('qResult').textContent = '❌ Not quite — see the correct answer highlighted.'; }
    else { document.getElementById('qResult').textContent = '✅ Correct! Well done!'; }
}
function nextQuestion() { qi++; renderQ(); }
renderQ();
</script>

<?php get_footer(); ?>
