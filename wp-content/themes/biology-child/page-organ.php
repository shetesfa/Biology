<?php
/**
 * Template Name: Organ Detail Page
 * Use this template for individual organ pages.
 */
get_header();

$slug = get_post_field('post_name', get_the_ID());

$organs = [
    'heart' => [
        'icon'  => '❤️', 'title' => 'The Heart', 'color' => '#e94560',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'system' => 'Circulatory System', 'system_url' => '/circulatory-system',
        'meta'  => ['Weight: ~300g','4 Chambers','Beats 100,000×/Day'],
        'sections' => [
            ['❤️ What is the Heart?','The heart is a hollow, muscular organ about the size of your fist. It sits slightly left of centre in your chest, between the lungs, and is protected by the rib cage.'],
            ['🏗️ Structure','The heart has 4 chambers: 2 atria (upper) and 2 ventricles (lower). The right side pumps blood to the lungs; the left side pumps oxygenated blood to the body.'],
            ['⚙️ How It Works','Electrical signals from the SA node trigger contractions. Each beat: atria fill with blood → contract → push blood into ventricles → ventricles contract → pump blood out.'],
            ['🩺 Common Conditions','Coronary artery disease, heart failure, arrhythmia, and heart attack are among the most common heart conditions worldwide.'],
            ['💪 Keeping It Healthy','Regular aerobic exercise, a heart-healthy diet (low saturated fat, high fibre), not smoking, and managing stress all protect heart health.'],
        ],
        'facts' => ['The heart beats about 3 billion times in an average lifetime.', 'A woman\'s heart beats slightly faster than a man\'s.', 'The heart creates enough pressure to squirt blood 30 feet.'],
    ],
    'brain' => [
        'icon'  => '🧠', 'title' => 'The Brain', 'color' => '#ffcc00',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'system' => 'Nervous System', 'system_url' => '/nervous-system',
        'meta'  => ['Weight: ~1.4kg','86B Neurons','20% of Energy'],
        'sections' => [
            ['🧠 What is the Brain?','The brain is the control centre of the body. It receives, interprets, and sends signals via the nervous system to coordinate everything from breathing to complex thought.'],
            ['🏗️ Structure','The brain has three main parts: the cerebrum (thought, memory, senses), cerebellum (balance, coordination), and brainstem (automatic functions like breathing and heartbeat).'],
            ['⚡ Neurons','Neurons communicate via electrical and chemical signals across synaptic gaps. The brain has ~86 billion neurons with ~100 trillion connections.'],
            ['💤 Sleep & Memory','During sleep, the brain consolidates memories and removes waste via the glymphatic system — making good sleep crucial for brain health.'],
            ['🧪 Neuroplasticity','The brain can rewire itself (neuroplasticity) throughout life — learning new skills literally changes brain structure.'],
        ],
        'facts' => ['The brain generates about 23 watts of power — enough to power a light bulb.', 'The brain is 73% water.', 'There are no pain receptors in the brain itself.'],
    ],
    'lungs' => [
        'icon'  => '🫁', 'title' => 'The Lungs', 'color' => '#00ccff',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'system' => 'Respiratory System', 'system_url' => '/respiratory-system',
        'meta'  => ['Weight: ~1.3kg','300M Alveoli','Tennis-Court Surface Area'],
        'sections' => [
            ['🫁 What are the Lungs?','You have two spongy lungs — the right with 3 lobes, the left with 2 (to make room for the heart). Together they weigh about 1.3 kg.'],
            ['🔬 Gas Exchange','Alveoli are tiny balloon-like sacs where oxygen passes into the blood and CO₂ passes out. There are ~300 million alveoli, with a surface area of ~70m².'],
            ['💨 Breathing Mechanics','When you inhale, the diaphragm flattens and chest expands — reducing pressure so air rushes in. Exhaling is mostly passive: muscles relax and lungs recoil.'],
            ['🛡️ Defence','The respiratory system has multiple defences: nasal hairs, mucus, cilia, and immune cells all trap and remove particles.'],
            ['🩺 Lung Health','Smoking, air pollution, and infections are the biggest threats to lung health. Regular exercise strengthens the respiratory muscles.'],
        ],
        'facts' => ['The right lung is slightly bigger than the left.', 'Lungs float on water — the only organ that does.', 'You exhale 17,000–23,000 times per day.'],
    ],
    'liver' => [
        'icon'  => '🔴', 'title' => 'The Liver', 'color' => '#ff8844',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'system' => 'Digestive System', 'system_url' => '/digestive-system',
        'meta'  => ['Weight: ~1.5kg','500+ Functions','Can Regenerate'],
        'sections' => [
            ['🔴 What is the Liver?','The liver is the largest internal organ, weighing about 1.5 kg. It sits in the upper right abdomen under the ribcage and is unique in its ability to regenerate.'],
            ['⚙️ Key Functions','The liver detoxifies blood, produces bile for digestion, synthesises proteins, stores glycogen for energy, and metabolises drugs and alcohol.'],
            ['🩸 Blood Filtering','Every minute, about 1.5 litres of blood passes through the liver to be filtered and processed.'],
            ['🔄 Regeneration','The liver can regrow from as little as 25% of its original tissue — making it the only major organ capable of full regeneration.'],
            ['🍺 Alcohol','The liver can process about one standard drink per hour. Excess alcohol leads to fatty liver, hepatitis, and eventually cirrhosis.'],
        ],
        'facts' => ['The liver performs over 500 different chemical functions.', 'It is the only organ that can completely regenerate.', 'The liver produces about 1 litre of bile per day.'],
    ],
    'kidneys' => [
        'icon'  => '🫘', 'title' => 'The Kidneys', 'color' => '#aa66ff',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'system' => 'Urinary System', 'system_url' => '/',
        'meta'  => ['2 Kidneys','Filter 200L/Day','1M Nephrons Each'],
        'sections' => [
            ['🫘 What are the Kidneys?','Two bean-shaped organs about the size of a fist, located at the back of the abdomen. Each kidney contains roughly 1 million tiny filtering units called nephrons.'],
            ['🔬 Filtration','The kidneys filter all your blood about 40 times per day, producing ~1–2 litres of urine. They regulate water, salt, and electrolyte balance precisely.'],
            ['⚖️ Hormone Production','Kidneys produce erythropoietin (stimulates red blood cell production), renin (blood pressure regulation), and activate Vitamin D.'],
            ['🩺 Kidney Disease','Diabetes and hypertension are the leading causes of chronic kidney disease. Early stages often have no symptoms.'],
            ['💧 Hydration','Drinking enough water is the simplest way to protect kidney health — it prevents stones and helps flush waste.'],
        ],
        'facts' => ['Each kidney contains about 1 million nephrons.', 'Kidneys filter 200 litres of blood daily.', 'You can live with just one kidney.'],
    ],
    'stomach' => [
        'icon'  => '🥘', 'title' => 'The Stomach', 'color' => '#00cc88',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'system' => 'Digestive System', 'system_url' => '/digestive-system',
        'meta'  => ['Volume: ~1 Litre','pH 1.5–3.5','Empties in 4–5hrs'],
        'sections' => [
            ['🥘 What is the Stomach?','A J-shaped muscular bag that holds food after swallowing. It can expand to hold about 1 litre of food and liquid at a time.'],
            ['⚗️ Stomach Acid','Gastric acid (HCl) at pH 1.5–3.5 kills most bacteria and begins protein digestion. Mucus protects the stomach lining from the acid.'],
            ['🔄 Churning','Powerful muscular contractions mix food with acid and enzymes into a liquid called chyme, which then moves into the small intestine.'],
            ['🧪 Enzymes','Pepsinogen (activated to pepsin by acid) begins protein digestion. Gastric lipase starts breaking down fats.'],
            ['🩺 Common Issues','Gastritis, ulcers, and GERD (acid reflux) are common. H. pylori bacteria cause most peptic ulcers.'],
        ],
        'facts' => ['The stomach lining replaces itself every 3–4 days.', 'An empty stomach is only the size of your fist.', 'Stomach rumbling is called "borborygmus".'],
    ],
];

$data = $organs[$slug] ?? [
    'icon' => '🔬', 'title' => get_the_title(), 'color' => '#00aaff',
    'sketchfab' => '', 'system' => '', 'system_url' => '/',
    'meta' => [], 'sections' => [], 'facts' => [],
];
?>

<div class="bio-page-wrap">

    <a href="<?php echo esc_url(home_url('/')); ?>" style="display:inline-flex;align-items:center;gap:8px;color:#88aaff;text-decoration:none;font-size:.9rem;margin-bottom:25px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:50px;padding:8px 18px;" onmouseover="this.style.borderColor='#00aaff';this.style.color='#00aaff'" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='#88aaff'">← Back to Home</a>

    <div class="bio-page-hero">
        <p style="font-size:3.5rem;"><?php echo $data['icon']; ?></p>
        <h1 style="--accent-blue:<?php echo esc_attr($data['color']); ?>"><?php echo esc_html($data['title']); ?></h1>
        <?php if ($data['system']) : ?>
        <p style="color:var(--text-muted);margin-top:0.5rem;">
            Part of the <a href="<?php echo esc_url( home_url($data['system_url']) ); ?>" style="color:var(--accent-blue)"><?php echo esc_html($data['system']); ?></a>
        </p>
        <?php endif; ?>
        <?php if ($data['meta']) : ?>
        <div class="page-meta">
            <?php foreach ($data['meta'] as $m) : ?>
            <span class="meta-tag"><?php echo esc_html($m); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($data['sketchfab']) : ?>
    <div class="viewer-wrap fade-up">
        <iframe title="<?php echo esc_attr($data['title']); ?> 3D Model"
            src="https://sketchfab.com/models/<?php echo esc_attr($data['sketchfab']); ?>/embed?autospin=1&ui_theme=dark"
            allow="autoplay; fullscreen; xr-spatial-tracking" allowfullscreen></iframe>
        <div class="viewer-hint">
            <span>🖱️ Drag to rotate</span><span>🔍 Scroll to zoom</span><span>⛶ Fullscreen available</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="info-grid">
        <?php foreach ($data['sections'] as $s) : ?>
        <div class="info-card fade-up">
            <h2><?php echo esc_html($s[0]); ?></h2>
            <p style="color:var(--text-muted);font-size:0.9rem;line-height:1.6;"><?php echo esc_html($s[1]); ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($data['facts']) : ?>
    <div class="info-card fade-up" style="margin-top:1.5rem;">
        <h2>⚡ Amazing Facts</h2>
        <ul>
            <?php foreach ($data['facts'] as $f) : ?>
            <li><?php echo esc_html($f); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
