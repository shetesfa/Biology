<?php
/**
 * Template Name: Body System Page
 * Use this template for all 6 body system pages.
 */
get_header();

// ── Data for each system page ──────────────────────────────────────────────
$slug   = get_post_field( 'post_name', get_the_ID() );
$systems = [
    'skeletal-system' => [
        'icon'   => '🦴',
        'title'  => 'Skeletal System',
        'color'  => '#aab8ff',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9', // replace with real ID
        'meta'   => ['206 Bones','Your Body\'s Framework','Lifelong Remodelling'],
        'facts'  => [
            ['🦷 Structure','The skeleton provides the rigid framework that supports soft tissues and gives the body its shape.'],
            ['🛡️ Protection','Bones protect vital organs — the skull shields the brain, and the ribcage guards the heart and lungs.'],
            ['🩸 Blood Production','Red and white blood cells are produced inside red bone marrow found in large bones.'],
            ['⚡ Movement','Muscles attach to bones via tendons; when muscles contract they pull on bones to create movement.'],
            ['🧪 Mineral Storage','Bones store 99% of the body\'s calcium and 85% of its phosphorus.'],
        ],
        'organs' => [['❤️','Heart','/heart'],['🦷','Joints','/'],['🧬','Cartilage','/']]
    ],
    'circulatory-system' => [
        'icon'   => '❤️',
        'title'  => 'Circulatory System',
        'color'  => '#e94560',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'meta'   => ['Heart + Blood Vessels','60,000 Miles of Vessels','100,000 Beats/Day'],
        'facts'  => [
            ['❤️ The Heart','A fist-sized muscle that pumps 5 litres of blood per minute — that\'s 7,200 litres a day.'],
            ['🩸 Blood','Blood carries oxygen, nutrients, hormones, and waste products. It makes up about 7–8% of body weight.'],
            ['🔴 Red Blood Cells','RBCs carry oxygen using haemoglobin. They live ~120 days and travel 1,000 miles in that time.'],
            ['⚪ White Blood Cells','WBCs are the immune system\'s soldiers, fighting bacteria, viruses, and foreign substances.'],
            ['🩺 Blood Pressure','Normal adult blood pressure is around 120/80 mmHg — the top number is systolic, bottom diastolic.'],
        ],
        'organs' => [['❤️','Heart','/heart'],['🩸','Arteries','/'],['🔵','Veins','/']]
    ],
    'respiratory-system' => [
        'icon'   => '🫁',
        'title'  => 'Respiratory System',
        'color'  => '#00ccff',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'meta'   => ['Lungs & Airways','20,000 Breaths/Day','Gas Exchange'],
        'facts'  => [
            ['🫁 Lungs','Your two lungs together have a surface area of roughly 70m² — the size of a tennis court.'],
            ['💨 Breathing','You breathe about 20,000 times per day, inhaling around 11,000 litres of air.'],
            ['🔬 Alveoli','Tiny air sacs called alveoli (300 million!) are where oxygen enters the blood and CO₂ leaves.'],
            ['👃 The Nose','The nose warms, filters, and humidifies air before it reaches the lungs.'],
            ['🩺 Diaphragm','A dome-shaped muscle below the lungs that does most of the work of breathing.'],
        ],
        'organs' => [['🫁','Lungs','/lungs'],['👃','Nose','/'],['🗣️','Trachea','/']]
    ],
    'nervous-system' => [
        'icon'   => '⚡',
        'title'  => 'Nervous System',
        'color'  => '#ffcc00',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'meta'   => ['Brain + Nerves','86 Billion Neurons','260 mph Signals'],
        'facts'  => [
            ['🧠 The Brain','The brain has about 86 billion neurons connected by 100 trillion synaptic connections.'],
            ['⚡ Nerve Speed','Electrical signals travel along nerves at up to 260 mph — that\'s why reactions feel instant.'],
            ['🌙 Two Systems','The CNS (brain + spinal cord) and PNS (all other nerves) work together to control everything.'],
            ['💤 Sleep','During sleep, the brain consolidates memories and flushes out waste products.'],
            ['🔄 Reflex Arcs','Reflex actions bypass the brain for speed — they complete within the spinal cord.'],
        ],
        'organs' => [['🧠','Brain','/brain'],['🫀','Spinal Cord','/'],['👁️','Eyes','/']]
    ],
    'muscular-system' => [
        'icon'   => '💪',
        'title'  => 'Muscular System',
        'color'  => '#ff6464',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'meta'   => ['600+ Muscles','40% of Body Weight','3 Muscle Types'],
        'facts'  => [
            ['💪 Types','Three types: skeletal (voluntary), smooth (organs), and cardiac (heart only).'],
            ['🔥 Heat','Muscles produce 85% of body heat — that\'s why you shiver; it\'s muscles contracting rapidly.'],
            ['🦾 Strongest','The masseter (jaw muscle) is the strongest muscle for its size in the body.'],
            ['⚡ Always Working','The heart muscle (cardiac) never rests — it beats continuously from before birth.'],
            ['🏋️ Growth','Muscles grow by repairing micro-tears caused by exercise — that post-workout soreness is it.'],
        ],
        'organs' => [['💪','Bicep','/'],['❤️','Heart','/heart'],['🫁','Diaphragm','/']]
    ],
    'digestive-system' => [
        'icon'   => '🍽️',
        'title'  => 'Digestive System',
        'color'  => '#00cc88',
        'sketchfab' => '4e7b5e5b4abb4bfa8fbf2c79e9e3e4c9',
        'meta'   => ['30-foot Tract','24–72 hr Transit','7 Major Organs'],
        'facts'  => [
            ['🍎 Digestion Start','Digestion begins in the mouth — saliva contains enzymes that start breaking down carbohydrates.'],
            ['🔴 Stomach Acid','Stomach acid (HCl) is strong enough to dissolve metal. Mucus protects the stomach wall.'],
            ['🧬 Small Intestine','At 6 metres long, the small intestine is where 90% of nutrient absorption happens.'],
            ['🦠 Gut Bacteria','You have 38 trillion bacteria in your gut — outnumbering your own body cells.'],
            ['🔴 Liver','The liver has 500+ functions including detoxification, protein synthesis, and bile production.'],
        ],
        'organs' => [['🥘','Stomach','/stomach'],['🔴','Liver','/liver'],['🫘','Kidneys','/kidneys']]
    ],
];

// ── Fall back if slug not in our list ─────────────────────────────────────
$data = $systems[$slug] ?? [
    'icon'      => '🔬',
    'title'     => get_the_title(),
    'color'     => '#00aaff',
    'sketchfab' => '',
    'meta'      => [],
    'facts'     => [],
    'organs'    => [],
];
?>

<div class="bio-page-wrap">

    <!-- Back button -->
    <a href="<?php echo esc_url(home_url('/')); ?>" style="display:inline-flex;align-items:center;gap:8px;color:#88aaff;text-decoration:none;font-size:.9rem;margin-bottom:25px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:50px;padding:8px 18px;" onmouseover="this.style.borderColor='#00aaff';this.style.color='#00aaff'" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='#88aaff'">← Back to Home</a>

    <!-- Page hero -->
    <div class="bio-page-hero">
        <p style="font-size:3.5rem;"><?php echo $data['icon']; ?></p>
        <h1 style="--accent-blue:<?php echo esc_attr($data['color']); ?>"><?php echo esc_html($data['title']); ?></h1>
        <?php if ( $data['meta'] ) : ?>
        <div class="page-meta">
            <?php foreach ( $data['meta'] as $m ) : ?>
            <span class="meta-tag"><?php echo esc_html($m); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 3D Viewer -->
    <?php if ( $data['sketchfab'] ) : ?>
    <div class="viewer-wrap fade-up">
        <iframe
            title="<?php echo esc_attr($data['title']); ?> 3D Model"
            src="https://sketchfab.com/models/<?php echo esc_attr($data['sketchfab']); ?>/embed?autospin=1&ui_theme=dark&ui_controls=1"
            allow="autoplay; fullscreen; xr-spatial-tracking"
            allowfullscreen>
        </iframe>
        <div class="viewer-hint">
            <span>🖱️ Drag to rotate</span>
            <span>🔍 Scroll to zoom</span>
            <span>👆 Click parts to learn</span>
            <span>⛶ Fullscreen mode available</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info cards -->
    <?php if ( $data['facts'] ) : ?>
    <div class="info-grid">
        <?php foreach ( $data['facts'] as $f ) : ?>
        <div class="info-card fade-up">
            <h2><?php echo esc_html($f[0]); ?></h2>
            <p style="color:var(--text-muted);font-size:0.9rem;line-height:1.6;"><?php echo esc_html($f[1]); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Related organs -->
    <?php if ( $data['organs'] ) : ?>
    <div style="margin-top:2rem;">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;color:var(--text-muted)">Related Organs</h2>
        <div class="organs-grid" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr))">
            <?php foreach ( $data['organs'] as $o ) : ?>
            <a href="<?php echo esc_url( home_url($o[2]) ); ?>" class="organ-card fade-up">
                <span class="organ-icon"><?php echo $o[0]; ?></span>
                <h4><?php echo esc_html($o[1]); ?></h4>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
