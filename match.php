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

$page_title = "Où voir le match {$e1} - {$e2} à Paris Saint-Michel ? | Le Gentleman Pub";
$meta_description = e("Réservez votre place au Gentleman Pub pour voir {$e1} - {$e2} à Saint-Michel. Ambiance sportive, écrans HD et Happy Hour.");

require __DIR__ . '/includes/header.php';

// JSON-LD Event pour SEO
$event = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => "{$e1} vs {$e2} au Gentleman Pub",
    'startDate' => $isoDate,
    'location' => [
        '@type' => 'Place',
        'name' => 'Le Gentleman Pub',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Saint-Michel, Paris 5e',
            'addressLocality' => 'Paris',
            'postalCode' => '75005',
            'addressCountry' => 'FR'
        ]
    ],
    'description' => "Venez voir {$e1} contre {$e2} au Gentleman Pub, écrans grands formats et Happy Hour !",
    'eventStatus' => 'https://schema.org/EventScheduled',
];

echo "<script type=\"application/ld+json\">" . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>";

?>
<div class="max-w-4xl mx-auto p-8">
  <article class="bg-[#121212] rounded-xl p-6 shadow-lg">
    <header class="flex items-center justify-between">
      <h1 class="text-3xl font-display text-amber-300"><?php echo e($e1); ?> <span class="text-gray-200">vs</span> <?php echo e($e2); ?></h1>
      <div class="text-sm text-gray-300"><?php echo e($date->format('d/m/Y')); ?> — <?php echo e($date->format('H:i')); ?></div>
    </header>
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
      <div class="col-span-2">
        <p class="text-gray-200">Competition: <?php echo e($match['competition']); ?></p>
        <p class="mt-4 text-lg">Lieu: <strong>Le Gentleman Pub — Saint-Michel, Paris 5e</strong></p>
        <p class="mt-4 text-yellow-300 font-semibold">Happy Hour applicable sur certaines boissons — renseignez-vous au bar.</p>
      </div>
      <div class="flex flex-col items-center gap-3">
        <a href="https://www.privateaser.com/" target="_blank" rel="noopener" class="bg-amber-400 text-black px-4 py-2 rounded-md font-semibold">Réserver / Privatiser</a>
        <a href="#carte" class="text-sm text-gray-300">Voir la carte & Happy Hour</a>
      </div>
    </div>
    <div class="mt-6">
      <p class="text-sm text-gray-400">Arrivez tôt pour vous assurer une bonne place — places limitées lors des gros matches.</p>
    </div>
  </article>
</div>

<?php
require __DIR__ . '/includes/footer.php';
