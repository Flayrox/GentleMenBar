<?php
require_once __DIR__ . '/config/db.php';

$page_title = config_value('site_name', 'Le Gentleman Pub') . ' - ' . config_value('site_tagline', 'Pub Irlandais & Sports Bar à Paris');
$meta_description = config_value('hero_subtitle', 'Pintes fraîches, sports en direct et soirées au cœur de Saint-Germain.');
require __DIR__ . '/includes/header.php';

// Récupérer les prochains matchs actifs
$stmt = $pdo->prepare('SELECT * FROM matchs WHERE is_active = 1 AND date_match >= NOW() ORDER BY date_match ASC LIMIT 6');
$stmt->execute();
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
?>

<!-- Hero Section Premium -->
<section class="relative w-full min-h-[85vh] flex items-center pt-24 pb-12" style="background-image: linear-gradient(rgba(17,20,21,0.95), rgba(17,20,21,0.80)), url('<?php echo e($heroBg); ?>'); background-size: cover; background-position: center;">
    <div class="relative z-10 w-full max-w-6xl mx-auto px-4 flex flex-col gap-8 md:gap-12">
        <div class="flex flex-col max-w-4xl">
            <span class="font-label-caps text-label-caps text-primary-container/70 tracking-[0.3em] uppercase mb-4"><?php echo e(config_value('site_tagline')); ?></span>
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
            <button class="bg-primary-container/10 border border-primary-container text-primary-container font-title-sm text-title-sm py-5 px-10 shadow-[inset_0_0_20px_rgba(212,175,55,0.15)] hover:bg-primary-container/20 hover:shadow-[inset_0_0_30px_rgba(212,175,55,0.3)] transition-all duration-300 flex-1 sm:flex-none text-center transform active:scale-95 backdrop-blur-md uppercase tracking-wider text-glow" onclick="document.getElementById('matchs').scrollIntoView({behavior:'smooth'})">
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

<?php require __DIR__ . '/includes/footer.php'; ?>
