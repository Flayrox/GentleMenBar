<?php
require_once __DIR__ . '/config/db.php';
// Page match SEO dynamique

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (!preg_match('/^[a-z0-9\-]+$/i', $slug)) {
    http_response_code(404);
    $page_title = 'Match introuvable - Le Gentleman Pub';
    require __DIR__ . '/includes/header.php';
    echo '<div class="max-w-4xl mx-auto p-8 text-center"><h1 class="text-3xl font-display">Match introuvable</h1><p class="mt-4">Le match demandé est introuvable.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM matchs WHERE slug = :slug AND is_active = 1 LIMIT 1');
$stmt->execute([':slug' => $slug]);
$match = $stmt->fetch();

if (!$match) {
    http_response_code(404);
    $page_title = 'Match introuvable - Le Gentleman Pub';
    require __DIR__ . '/includes/header.php';
    echo '<div class="max-w-4xl mx-auto p-8 text-center"><h1 class="text-3xl font-display">Match introuvable</h1><p class="mt-4">Le match demandé est introuvable.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Préparer les données SEO
$e1 = $match['equipe_1'];
$e2 = $match['equipe_2'];
$date = new DateTimeImmutable($match['date_match'], new DateTimeZone('Europe/Paris'));
$isoDate = $date->format(DateTime::ATOM);
$humanDate = $date->format('d F Y \à H:i');
$siteName = config_value('site_name', 'Le Gentleman Pub');
$siteTagline = config_value('site_tagline', 'Pub irlandais à Saint-Michel, Paris');
$matchImage = !empty($match['image_path']) ? $match['image_path'] : '/assets/uploads/default-match.svg';

$page_title = "Où voir le match {$e1} - {$e2} à Paris Saint-Michel ? | {$siteName}";
$meta_description = e("Réservez votre place au {$siteName} pour voir {$e1} - {$e2} à Saint-Michel. {$siteTagline}.");

require __DIR__ . '/includes/header.php';

// JSON-LD SportsEvent ultra-optimisé SEO Local
$event = [
    '@context' => 'https://schema.org',
    '@type' => 'SportsEvent',
    'name' => "Diffusion en direct : {$e1} vs {$e2} au {$siteName}",
    'sport' => $match['sport'] ?? 'Football',
    'competitor' => [
        ['@type' => 'SportsTeam', 'name' => $e1],
        ['@type' => 'SportsTeam', 'name' => $e2]
    ],
    'startDate' => $isoDate,
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'location' => [
        '@type' => 'Place',
        'name' => $siteName,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => config_value('bar_adresse', '14 Rue Saint Germain'),
            'addressLocality' => 'Paris',
            'postalCode' => '75006',
            'addressCountry' => 'FR'
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => 48.8524,
            'longitude' => 2.3436
        ]
    ],
    'image' => [
        !empty($match['image_path']) ? $match['image_path'] : '/assets/uploads/hero-bg.jpg'
    ],
    'description' => "Regarder le match {$e1} contre {$e2} en direct sur écrans HD au {$siteName} à Paris Saint-Germain / Saint-Michel. Ambiance pub, bières fraîches et Happy Hour.",
    'organizer' => [
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    ]
];

echo "<script type=\"application/ld+json\">" . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>";

?>
<div class="max-w-4xl mx-auto p-8">
  <article class="bg-[#121212] rounded-xl p-6 shadow-lg">
    <header class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-3">
        <h1 class="text-3xl font-display text-amber-300"><?php echo e($e1); ?> <span class="text-gray-200">vs</span> <?php echo e($e2); ?></h1>
        <?php 
          $badge = get_match_status_badge($match);
          $isLive = is_match_live($match);
          if ($isLive):
        ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold uppercase animate-pulse border border-emerald-500/20">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
            <?php echo e($badge); ?>
          </span>
        <?php elseif ($badge === 'FINISHED'): ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white/5 text-gray-400 text-xs font-semibold uppercase border border-white/10">
            <?php echo e($badge); ?>
          </span>
        <?php else: ?>
          <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-400/10 text-amber-300 text-xs font-semibold uppercase border border-amber-400/20">
            <?php echo e($badge); ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="text-sm text-gray-400"><?php echo e($date->format('d/m/Y')); ?> — <?php echo e($date->format('H:i')); ?></div>
    </header>

    <div class="mb-8 overflow-hidden rounded-xl border border-white/10 bg-[#1A1A1A] p-8 md:p-12 shadow-[inset_0_0_30px_rgba(212,175,55,0.05)] relative flex flex-col md:flex-row items-center justify-between gap-8 bg-cover bg-center" style="background-image: linear-gradient(rgba(18,18,18,0.95), rgba(18,18,18,0.92)), url('/assets/uploads/hero-bg.jpg');">
      <!-- Team 1 -->
      <div class="flex flex-col items-center text-center flex-1">
        <?php echo render_team_logo_html($e1, $match['image_path'], 'h-28 w-28 object-contain drop-shadow-[0_0_15px_rgba(212,175,55,0.3)] bg-black/30 p-2 rounded-2xl border border-white/10 transition-transform hover:scale-105 duration-300', mb_substr($e1, 0, 1)); ?>
        <div class="text-2xl font-display text-white mt-4 font-bold tracking-wide"><?php echo e($e1); ?></div>
      </div>

      <!-- VS / Score -->
      <div class="flex flex-col items-center justify-center flex-shrink-0 min-w-[120px]">
        <?php 
          $score = format_score($match['score_equipe_1'] ?? null, $match['score_equipe_2'] ?? null);
          if ($score):
        ?>
          <div class="text-4xl md:text-5xl font-display text-amber-300 font-extrabold tracking-widest bg-black/40 px-6 py-3 rounded-xl border border-white/10 drop-shadow-[0_0_10px_rgba(212,175,55,0.2)]">
            <?php echo e($score); ?>
          </div>
        <?php else: ?>
          <div class="text-4xl font-display text-gray-400 font-bold bg-white/5 px-6 py-3 rounded-full border border-white/5">
            VS
          </div>
        <?php endif; ?>
        <span class="text-xs text-gray-500 uppercase tracking-widest mt-3"><?php echo e($match['competition'] ?: 'Événement'); ?></span>
      </div>

      <!-- Team 2 -->
      <div class="flex flex-col items-center text-center flex-1">
        <?php echo render_team_logo_html($e2, $match['image_path_away'], 'h-28 w-28 object-contain drop-shadow-[0_0_15px_rgba(212,175,55,0.3)] bg-black/30 p-2 rounded-2xl border border-white/10 transition-transform hover:scale-105 duration-300', mb_substr($e2, 0, 1)); ?>
        <div class="text-2xl font-display text-white mt-4 font-bold tracking-wide"><?php echo e($e2); ?></div>
      </div>
    </div>
    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6 items-center border-t border-white/10 pt-6">
      <div class="col-span-2 space-y-3">
        <p class="text-gray-300 flex items-center gap-2">
            <span>📺</span>
            <span>Diffusion sur <strong><?php echo (int)($match['ecran_nb'] ?? 4); ?> Écrans HD</strong> (Chaîne: <strong><?php echo e($match['chaine_tv'] ?? 'Écrans HD'); ?></strong>)</span>
        </p>
        <p class="text-gray-300 flex items-center gap-2">
            <span>📍</span>
            <span>Lieu: <strong><?php echo e($siteName); ?> — <?php echo e(config_value('bar_adresse', '14 Rue Saint Germain, 75006 Paris')); ?></strong></span>
        </p>
        <p class="text-amber-300 font-semibold text-sm flex items-center gap-2">
            <span>🍻</span>
            <span>Happy Hour actif de 17h à 20h sur les bières & cocktails !</span>
        </p>
      </div>
      <div class="flex flex-col items-center gap-3 w-full">
        <a href="<?php echo e(config_value('booking_privateaser_url', 'https://www.privateaser.com/lieu/5113-le-gentleman-pub')); ?>" target="_blank" rel="noopener" class="w-full text-center bg-amber-400 text-black px-4 py-3 rounded-xl font-bold uppercase tracking-wider hover:bg-amber-300 transition-all shadow-[0_0_15px_rgba(212,175,55,0.2)]">
            🎯 Réserver une table
        </a>
        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', config_value('bar_telephone', '0171717171')); ?>" class="w-full text-center border border-amber-400/40 text-amber-300 px-4 py-2.5 rounded-xl font-semibold hover:bg-amber-400/10 transition-all text-xs flex items-center justify-center gap-2">
            📞 Appeler le bar (<?php echo e(config_value('bar_telephone', '01 71 71 71 71')); ?>)
        </a>
        <a href="/#carte" class="text-xs text-gray-400 hover:text-amber-300 transition-colors mt-1">Découvrir la carte des boissons →</a>
      </div>
    </div>
    <div class="mt-6 text-center border-t border-white/5 pt-4">
      <p class="text-xs text-gray-400 italic">💡 Conseils : Arrivez au moins 30 minutes avant le coup d'envoi pour avoir une table près des grands écrans.</p>
    </div>
  </article>
</div>

<?php
require __DIR__ . '/includes/footer.php';
