<?php
require_once __DIR__ . '/config/db.php';

$page_title = 'Le Gentleman Pub - Pub irlandais à Saint-Michel';
$meta_description = 'Le Gentleman Pub à Saint-Michel — ambiance irlandaise, retransmissions sportives et Happy Hour. Découvrez les prochains matchs et notre carte.';
require __DIR__ . '/includes/header.php';

// Récupérer les 3 prochains matchs actifs
$stmt = $pdo->prepare('SELECT * FROM matchs WHERE is_active = 1 AND date_match >= NOW() ORDER BY date_match ASC LIMIT 3');
$stmt->execute();
$nextMatchs = $stmt->fetchAll();

// Récupérer la carte complète
$stmt2 = $pdo->prepare('SELECT * FROM carte_produits ORDER BY categorie, nom');
$stmt2->execute();
$carte = $stmt2->fetchAll();

// Regrouper par catégorie
$grouped = [];
foreach ($carte as $item) {
    $cat = $item['categorie'] ?: 'Autres';
    $grouped[$cat][] = $item;
}

// Ordre préféré des catégories
$order = ['Bières','Cocktails','Softs','Food','Planches'];

?>

<!-- Hero -->
<section class="py-20">
  <div class="max-w-6xl mx-auto px-6 text-center">
    <h1 class="text-5xl font-display text-amber-300">Le Gentleman Pub</h1>
    <p class="mt-4 text-lg text-gray-200">Pub irlandais & spot sports à Saint-Michel — bières, concerts et karaoké.</p>
    <div class="mt-6 flex justify-center gap-4">
      <a href="#matchs" class="bg-amber-400 text-black px-5 py-3 rounded-md font-semibold">Voir les prochains matchs</a>
      <a href="#carte" class="border border-amber-400 text-amber-300 px-5 py-3 rounded-md">Voir la carte</a>
    </div>
  </div>
</section>

<!-- Section Matchs -->
<section id="matchs" class="py-12">
  <div class="max-w-6xl mx-auto px-6">
    <h2 class="text-3xl font-display text-amber-300">Prochains matchs</h2>
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      <?php if (!empty($nextMatchs)): ?>
        <?php foreach ($nextMatchs as $m):
          $d = new DateTimeImmutable($m['date_match'], new DateTimeZone('Europe/Paris')); ?>
          <a href="/matchs/dates/<?php echo e($m['slug']); ?>" class="block bg-[#121212] p-4 rounded-lg hover:shadow-lg">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-lg font-semibold text-gray-100"><?php echo e($m['equipe_1']); ?> <span class="text-gray-400">vs</span> <?php echo e($m['equipe_2']); ?></div>
                <div class="text-sm text-gray-400"><?php echo e($m['competition']); ?></div>
              </div>
              <div class="text-sm text-gray-300"><?php echo e($d->format('d/m H:i')); ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-span-1 md:col-span-3 bg-[#121212] p-6 rounded-lg text-center">
          <h3 class="text-xl font-semibold">Aucun match prévu pour le moment</h3>
          <p class="mt-2 text-gray-300">Venez profiter de l'ambiance, des bières artisanales et des soirées karaoké.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Section Carte -->
<section id="carte" class="py-12 bg-[#071a12]">
  <div class="max-w-6xl mx-auto px-6">
    <h2 class="text-3xl font-display text-amber-300">La carte</h2>
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
      <?php
      // Afficher les catégories dans l'ordre préféré, puis le reste
      $printed = [];
      foreach ($order as $cat) {
        if (isset($grouped[$cat])) {
          echo '<div class="bg-[#121212] p-4 rounded-lg">';
          echo '<h3 class="text-xl font-semibold text-amber-300">'.e($cat).'</h3>';
          echo '<ul class="mt-3 space-y-3">';
          foreach ($grouped[$cat] as $it) {
            $price = number_format((float)$it['prix_normal'], 2, ',', ' ');
            $ph = $it['prix_happy_hour'] !== null ? number_format((float)$it['prix_happy_hour'], 2, ',', ' ') : null;
            echo '<li class="flex justify-between items-start">';
            echo '<div><div class="font-medium text-gray-100">'.e($it['nom']).'</div><div class="text-sm text-gray-400">'.e($it['description']).'</div></div>';
            echo '<div class="text-right">';
            if ($ph) {
              echo '<div class="text-amber-300 font-bold">€'.$ph.'</div>';
              echo '<div class="text-sm text-gray-400 line-through">€'.$price.'</div>';
            } else {
              echo '<div class="text-gray-100">€'.$price.'</div>';
            }
            echo '</div>';
            echo '</li>';
          }
          echo '</ul>';
          echo '</div>';
          $printed[] = $cat;
        }
      }

      // Reste des catégories
      foreach ($grouped as $cat => $items) {
        if (in_array($cat, $printed, true)) continue;
        echo '<div class="bg-[#121212] p-4 rounded-lg">';
        echo '<h3 class="text-xl font-semibold text-amber-300">'.e($cat).'</h3>';
        echo '<ul class="mt-3 space-y-3">';
        foreach ($items as $it) {
          $price = number_format((float)$it['prix_normal'], 2, ',', ' ');
          $ph = $it['prix_happy_hour'] !== null ? number_format((float)$it['prix_happy_hour'], 2, ',', ' ') : null;
          echo '<li class="flex justify-between items-start">';
          echo '<div><div class="font-medium text-gray-100">'.e($it['nom']).'</div><div class="text-sm text-gray-400">'.e($it['description']).'</div></div>';
          echo '<div class="text-right">';
          if ($ph) {
            echo '<div class="text-amber-300 font-bold">€'.$ph.'</div>';
            echo '<div class="text-sm text-gray-400 line-through">€'.$price.'</div>';
          } else {
            echo '<div class="text-gray-100">€'.$price.'</div>';
          }
          echo '</div>';
          echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
      }
      ?>
    </div>
  </div>
</section>

<?php
require __DIR__ . '/includes/footer.php';
