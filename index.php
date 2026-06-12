<?php
require_once __DIR__ . '/config/db.php';

$page_title = config_value('site_name', 'Le Gentleman Pub') . ' - ' . config_value('site_tagline', 'Pub Irlandais & Sports Bar à Paris');
$meta_description = config_value('hero_subtitle', 'Pintes fraîches, sports en direct et soirées au cœur de Saint-Germain.');
require __DIR__ . '/includes/header.php';

// Récupérer le match mis en avant
$stmtFeatured = $pdo->query('SELECT * FROM matchs WHERE is_active = 1 AND is_featured = 1 LIMIT 1');
$featuredMatch = $stmtFeatured->fetch();

if (!$featuredMatch) {
    // Fallback automatique sur le prochain match à venir
    $stmtFeaturedAuto = $pdo->query('SELECT * FROM matchs WHERE is_active = 1 AND date_match >= NOW() ORDER BY date_match ASC LIMIT 1');
    $featuredMatch = $stmtFeaturedAuto->fetch() ?: null;
}

// Récupérer les prochains matchs actifs (exclure le match à l'affiche de la liste des petits blocs s'il est déjà en vedette)
$featuredId = $featuredMatch ? (int)$featuredMatch['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM matchs WHERE is_active = 1 AND id != :featured_id AND date_match >= NOW() ORDER BY date_match ASC LIMIT 6');
$stmt->execute([':featured_id' => $featuredId]);
$nextMatchs = $stmt->fetchAll();

// Récupérer la carte complète
$stmt2 = $pdo->prepare('SELECT * FROM carte_produits ORDER BY categorie, nom');
$stmt2->execute();
$carte = $stmt2->fetchAll();

// Grouper par catégorie
$grouped = [];
foreach ($carte as $item) {
    $cat = $item['categorie'] ?: 'Autres';
    $grouped[$cat][] = $item;
}

$order = ['Bières','Cocktails','Softs','Food','Planches'];
$heroBg = config_value('hero_bg_image', '/assets/uploads/hero-bg.jpg');
$heroTitle = config_value('hero_title', 'Le Gentleman Pub');
$heroSubtitle = config_value('hero_subtitle', 'Pintes fraîches, sports en direct et soirées karaoké au cœur de Saint-Germain.');
$ctaPrimary = config_value('hero_cta_primary', 'Voir les matchs ce soir');
$ctaSecondary = config_value('hero_cta_secondary', 'Découvrir la carte');
$ctaPrimaryLink = "document.getElementById('matchs').scrollIntoView({behavior:'smooth'})";

if ($featuredMatch) {
    if (!empty($featuredMatch['image_path'])) {
        $heroBg = $featuredMatch['image_path'];
    }
    $heroTitle = "CE SOIR AU BAR\\n" . $featuredMatch['equipe_1'] . " vs " . $featuredMatch['equipe_2'];
    $heroSubtitle = "Venez vivre " . $featuredMatch['equipe_1'] . " contre " . $featuredMatch['equipe_2'] . " en direct (" . ($featuredMatch['competition'] ?: 'Événement') . ") au Gentleman Pub !";
    $ctaPrimary = "Voir les détails du match";
    $ctaPrimaryLink = "window.location.href='/matchs/" . $featuredMatch['slug'] . "'";
}
?>

<!-- Hero Section Premium -->
<section class="relative w-full min-h-[85vh] flex items-center pt-24 pb-12" style="background-image: linear-gradient(rgba(17,20,21,0.92), rgba(17,20,21,0.75)), url('<?php echo e($heroBg); ?>'); background-size: cover; background-position: center;">
    <div class="relative z-10 w-full max-w-6xl mx-auto px-4 flex flex-col gap-8 md:gap-12">
        <div class="flex flex-col max-w-4xl">
            <?php if ($featuredMatch): ?>
                <span class="inline-flex items-center gap-2 font-label-caps text-label-caps text-primary tracking-[0.3em] uppercase mb-4 animate-pulse">⭐ MATCH À L'AFFICHE</span>
            <?php else: ?>
                <span class="font-label-caps text-label-caps text-primary-container/70 tracking-[0.3em] uppercase mb-4"><?php echo e(config_value('site_tagline')); ?></span>
            <?php endif; ?>
            <h2 class="font-display-lg text-[3.5rem] leading-[0.85] md:text-[5rem] text-primary-container text-glow tracking-tighter">
                <?php 
                    $lines = explode('\n', $heroTitle);
                    foreach ($lines as $i => $line) {
                        echo e($line);
                        if ($i < count($lines) - 1) echo '<br/>';
                    }
                ?>
            </h2>
            <p class="font-body-base text-lg md:text-xl text-on-surface-variant mt-8 max-w-md border-l-2 border-primary-container/50 pl-4 leading-relaxed">
                <?php echo e($heroSubtitle); ?>
            </p>
        </div>

        <!-- Asymmetric CTAs -->
        <div class="flex flex-col sm:flex-row gap-6 mt-4 w-full sm:w-auto">
            <button class="bg-primary-container/10 border border-primary-container text-primary-container font-title-sm text-title-sm py-5 px-10 shadow-[inset_0_0_20px_rgba(212,175,55,0.15)] hover:bg-primary-container/20 hover:shadow-[inset_0_0_30px_rgba(212,175,55,0.3)] transition-all duration-300 flex-1 sm:flex-none text-center transform active:scale-95 backdrop-blur-md uppercase tracking-wider text-glow" onclick="<?php echo $ctaPrimaryLink; ?>">
                <?php echo e($ctaPrimary); ?>
            </button>
            <button class="bg-surface/20 border border-outline-variant/40 text-on-surface hover:border-primary-container/50 hover:text-primary-container font-title-sm text-title-sm py-5 px-10 transition-all duration-300 flex-shrink-0 text-center transform active:scale-95 backdrop-blur-sm uppercase tracking-wider" onclick="document.getElementById('carte').scrollIntoView({behavior:'smooth'})">
                <?php echo e($ctaSecondary); ?>
            </button>
        </div>
    </div>
</section>

<!-- Live Match Feature Area -->
<section id="matchs" class="relative py-section-mobile md:py-section-desktop max-w-none mx-auto overflow-hidden border-t border-primary-container/10">
    <div class="relative z-10 max-w-6xl mx-auto px-4 flex flex-col gap-12">
        <div class="flex items-center justify-between border-b border-primary-container/20 pb-4">
            <h3 class="font-display-lg text-3xl md:text-4xl text-on-surface uppercase tracking-widest opacity-90"><?php echo e(config_value('section_matchs_title', 'Événements & Matchs')); ?></h3>
        </div>

        <?php if (!empty($nextMatchs)): ?>
            <!-- Live Matches -->
            <div>
                <h4 class="font-headline-md text-2xl text-primary-container mb-6 uppercase tracking-wide">Matchs à venir</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
                    <?php foreach ($nextMatchs as $m):
                        $d = new DateTimeImmutable($m['date_match'], new DateTimeZone('Europe/Paris'));
                        $badge = get_match_status_badge($m['statut'] ?? 'scheduled', $m['minute_actuelle'] ?? null);
                        $score = format_score($m['score_equipe_1'] ?? null, $m['score_equipe_2'] ?? null);
                        $isLive = is_match_live($m['statut'] ?? 'scheduled');
                    ?>
                        <a href="/matchs/<?php echo e($m['slug']); ?>" class="relative py-md border-b border-primary/20 group cursor-pointer transition-all duration-300 hover:border-primary/50">
                            <div class="flex justify-between items-start mb-md relative z-10">
                                <?php if ($isLive): ?>
                                    <span class="inline-flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-status-live animate-pulse"></span>
                                        <span class="font-label-caps text-label-caps text-status-live"><?php echo e($badge); ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="font-label-caps text-label-caps text-primary/60 tracking-widest uppercase"><?php echo e($badge); ?></span>
                                <?php endif; ?>
                                <span class="font-label-caps text-label-caps text-primary/60 tracking-widest uppercase"><?php echo e($m['competition'] ?: 'Sport'); ?></span>
                            </div>
                            <div class="flex-1 flex flex-col justify-center gap-sm relative z-10 mt-auto">
                                <div class="flex justify-between items-center pb-sm">
                                    <span class="font-display-lg text-2xl md:text-3xl text-on-surface"><?php echo e($m['equipe_1']); ?></span>
                                    <?php if ($score): ?>
                                        <span class="font-display-lg text-3xl md:text-4xl text-primary-container neon-text-gold"><?php echo e(explode(' - ', $score)[0]); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-between items-center pt-xs">
                                    <span class="font-display-lg text-2xl md:text-3xl text-on-surface/80"><?php echo e($m['equipe_2']); ?></span>
                                    <?php if ($score): ?>
                                        <span class="font-display-lg text-3xl md:text-4xl text-primary-container neon-text-gold"><?php echo e(explode(' - ', $score)[1]); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity -z-10"></div>
                            <div class="text-xs text-on-surface-variant mt-3"><?php echo e($d->format('d/m/Y H:i')); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <p class="text-on-surface-variant text-lg"><?php echo e(config_value('no_matchs_message', 'Aucun match prévu pour le moment.')); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- La Carte Section - Art Deco Style -->
<section id="carte" class="relative py-section-mobile md:py-section-desktop max-w-none mx-auto">
    <div class="relative z-10 pt-[50px] pb-[120px] md:pb-section-desktop px-4">
        <div class="max-w-4xl mx-auto bg-surface/95 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.8)] border border-outline-variant/30 relative overflow-hidden">
            <!-- Inner Content -->
            <div class="relative z-20 border-[1.5px] border-primary/20 m-3 p-6 md:p-12 space-y-section-mobile md:space-y-section-desktop bg-[#121212]/50 backdrop-blur-sm">
                
                <!-- Header -->
                <div class="text-center">
                    <h2 class="font-display-lg text-4xl md:text-5xl text-primary tracking-widest uppercase">
                        <?php echo e(config_value('section_carte_title', 'La Carte')); ?>
                    </h2>
                    <div style="width: 100%; height: 1px; background: linear-gradient(90deg, rgba(212,175,55,0) 0%, rgba(212,175,55,0.6) 50%, rgba(212,175,55,0) 100%); margin: 32px 0; position: relative;">
                        <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); color: #d4af37; font-size: 14px; padding: 0 12px; background-color: transparent;">❖</div>
                    </div>
                    <p class="font-body-muted text-lg text-on-surface-variant max-w-2xl mx-auto italic">
                        <?php echo e(config_value('hero_subtitle', 'Découvrez notre sélection de bières, cocktails et plats au cœur de Paris.')); ?>
                    </p>
                </div>

                <!-- Menu Categories -->
                <?php foreach ($order as $cat):
                    if (isset($grouped[$cat])): ?>
                        <section>
                            <h3 class="font-display-lg text-2xl text-primary-container uppercase tracking-[0.2em] mb-lg text-center flex items-center justify-center gap-4">
                                <span style="height: 1px; width: 48px; background: rgba(212,175,55,0.3);"></span>
                                <?php echo e($cat); ?>
                                <span style="height: 1px; width: 48px; background: rgba(212,175,55,0.3);"></span>
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10 mt-8">
                                <?php foreach ($grouped[$cat] as $item):
                                    $ph = $item['prix_happy_hour'];
                                    $price = number_format((float)$item['prix_normal'], 2, ',', ' ');
                                    $priceHH = $ph ? number_format((float)$ph, 2, ',', ' ') : null;
                                ?>
                                    <article class="flex flex-col group cursor-pointer">
                                        <div class="flex justify-between items-start">
                                            <h4 class="font-headline-md text-xl text-on-surface group-hover:text-primary transition-colors tracking-wide flex items-center gap-2">
                                                <?php echo e($item['nom']); ?>
                                                <?php if ($ph): ?>
                                                    <span class="material-symbols-outlined text-[16px] text-primary" style="font-variation-settings: 'FILL' 1;">star</span>
                                                <?php endif; ?>
                                            </h4>
                                            <div style="flex-grow: 1; height: 1px; background: linear-gradient(90deg, rgba(212,175,55,0) 0%, rgba(212,175,55,0.4) 50%, rgba(212,175,55,0) 100%); margin: 0 16px;"></div>
                                            <div class="flex items-center gap-3">
                                                <?php if ($priceHH): ?>
                                                    <span class="font-body-muted text-body-muted text-on-surface-variant/60 line-through">€<?php echo e($price); ?></span>
                                                    <span class="font-headline-md text-xl text-primary-container drop-shadow-[0_0_8px_rgba(212,175,55,0.4)]">€<?php echo e($priceHH); ?></span>
                                                <?php else: ?>
                                                    <span class="font-headline-md text-xl text-primary-container drop-shadow-[0_0_8px_rgba(212,175,55,0.3)]">€<?php echo e($price); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($item['description'])): ?>
                                            <p class="font-body-muted text-body-muted text-on-surface-variant/80 mt-2 italic">
                                                <?php echo e($item['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif;
                endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Galerie / Carrousel du Bar -->
<?php
$carouselJson = config_value('carousel_images', '[]');
$carouselImages = json_decode($carouselJson, true) ?: [];
if (!empty($carouselImages)):
?>
<section class="relative py-12 border-t border-primary-container/10 overflow-hidden bg-surface/30">
    <div class="max-w-6xl mx-auto px-4">
        <h3 class="font-display-lg text-3xl md:text-4xl text-center text-primary-container uppercase tracking-widest mb-10">Le Bar en Images</h3>
        <div class="relative w-full max-w-4xl mx-auto overflow-hidden rounded-2xl border border-white/10 group">
            <!-- Slides wrapper -->
            <div id="carousel-slides" class="flex transition-transform duration-500 ease-in-out">
                <?php foreach ($carouselImages as $index => $img): ?>
                    <div class="min-w-full h-[300px] md:h-[450px] relative">
                        <img src="<?php echo e($img); ?>" alt="Ambiance du Gentleman Pub" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-background/90 via-transparent to-transparent"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Controls -->
            <button onclick="prevSlide()" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/60 hover:bg-primary-container text-white hover:text-black flex items-center justify-center border border-white/10 transition-all active:scale-95 duration-200">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <button onclick="nextSlide()" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/60 hover:bg-primary-container text-white hover:text-black flex items-center justify-center border border-white/10 transition-all active:scale-95 duration-200">
                <span class="material-symbols-outlined">chevron_right</span>
            </button>

            <!-- Dots -->
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                <?php foreach ($carouselImages as $index => $img): ?>
                    <button onclick="goToSlide(<?php echo $index; ?>)" class="carousel-dot w-2 h-2 rounded-full bg-white/40 hover:bg-white transition-all duration-300"></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<script>
let currentSlide = 0;
const slides = document.getElementById('carousel-slides');
const dots = document.querySelectorAll('.carousel-dot');
const totalSlides = <?php echo count($carouselImages); ?>;

function updateCarousel() {
    slides.style.transform = `translateX(-${currentSlide * 100}%)`;
    dots.forEach((dot, index) => {
        if (index === currentSlide) {
            dot.classList.add('bg-primary-container', 'w-4');
            dot.classList.remove('bg-white/40');
        } else {
            dot.classList.remove('bg-primary-container', 'w-4');
            dot.classList.add('bg-white/40');
        }
    });
}

function nextSlide() {
    currentSlide = (currentSlide + 1) % totalSlides;
    updateCarousel();
}

function prevSlide() {
    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
    updateCarousel();
}

function goToSlide(index) {
    currentSlide = index;
    updateCarousel();
}

// Auto scroll
let autoScroll = setInterval(nextSlide, 5000);
document.getElementById('carousel-slides').parentElement.addEventListener('mouseenter', () => clearInterval(autoScroll));
document.getElementById('carousel-slides').parentElement.addEventListener('mouseleave', () => autoScroll = setInterval(nextSlide, 5000));

// Initial render
updateCarousel();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
