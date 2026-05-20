<?php
require_once __DIR__ . '/config/db.php';

$page_title = config_value('site_name', 'Le Gentleman Pub') . ' - ' . config_value('site_tagline', 'Pub irlandais à Saint-Michel, Paris');
$meta_description = config_value('hero_subtitle', 'Ambiance irlandaise, sports en direct, karaoké et grandes soirées à Saint-Michel.');
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
$heroBg = config_value('hero_bg_image', '/assets/uploads/hero-bg.jpg');
$heroTitle = config_value('hero_title', config_value('site_name', 'Le Gentleman Pub'));
$heroSubtitle = config_value('hero_subtitle', 'Ambiance irlandaise, sports en direct, karaoké et grandes soirées à Saint-Michel.');
$ctaPrimary = config_value('hero_cta_primary', 'Voir les prochains matchs');
$ctaSecondary = config_value('hero_cta_secondary', 'Découvrir la carte');
$matchsTitle = config_value('section_matchs_title', 'Prochains matchs');
$carteTitle = config_value('section_carte_title', 'La carte');
$emptyMatchsMessage = config_value('no_matchs_message', 'Aucun match prévu pour le moment');

?>

<!-- Hero -->
<section class="py-20 relative overflow-hidden" style="background-image: linear-gradient(rgba(11,37,22,.82), rgba(11,37,22,.92)), url('<?php echo e($heroBg); ?>'); background-size: cover; background-position: center;">
  <div class="max-w-6xl mx-auto px-6 text-center">
    <h1 class="text-5xl font-display text-amber-300"><?php echo e($heroTitle); ?></h1>
    <p class="mt-4 text-lg text-gray-200"><?php echo e($heroSubtitle); ?></p>
    <div class="mt-6 flex justify-center gap-4">
      <a href="#matchs" class="bg-amber-400 text-black px-5 py-3 rounded-md font-semibold"><?php echo e($ctaPrimary); ?></a>
      <a href="#carte" class="border border-amber-400 text-amber-300 px-5 py-3 rounded-md"><?php echo e($ctaSecondary); ?></a>
    </div>
  </div>
</section>

<!-- Section Matchs -->
<section id="matchs" class="py-12">
  <div class="max-w-6xl mx-auto px-6">
    <h2 class="text-3xl font-display text-amber-300"><?php echo e($matchsTitle); ?></h2>
    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      <?php if (!empty($nextMatchs)): ?>
        <?php foreach ($nextMatchs as $m):
          $d = new DateTimeImmutable($m['date_match'], new DateTimeZone('Europe/Paris')); ?>
          <a href="/matchs/<?php echo e($m['slug']); ?>" class="block bg-[#121212] p-4 rounded-lg hover:shadow-lg">
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
          <h3 class="text-xl font-semibold"><?php echo e($emptyMatchsMessage); ?></h3>
          <p class="mt-2 text-gray-300"><?php echo e(config_value('hero_subtitle', 'Ambiance irlandaise, sports en direct, karaoké et grandes soirées à Saint-Michel.')); ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Section Carte -->
<section id="carte" class="py-12 bg-[#071a12]">
  <div class="max-w-6xl mx-auto px-6">
    <h2 class="text-3xl font-display text-amber-300"><?php echo e($carteTitle); ?></h2>
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

<section id="contact" class="py-12">
  <div class="max-w-6xl mx-auto px-6 grid gap-6 md:grid-cols-3">
    <div class="rounded-2xl bg-[#121212] p-6 border border-white/10">
      <h3 class="text-xl font-display text-amber-300"><?php echo e(config_value('footer_address_title', 'Adresse')); ?></h3>
      <p class="mt-3 text-gray-300"><?php echo e(config_value('bar_adresse')); ?></p>
    </div>
    <div class="rounded-2xl bg-[#121212] p-6 border border-white/10">
      <h3 class="text-xl font-display text-amber-300"><?php echo e(config_value('footer_phone_label', 'Téléphone')); ?></h3>
      <p class="mt-3 text-gray-300"><a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', config_value('bar_telephone'))); ?>" class="hover:text-amber-300"><?php echo e(config_value('bar_telephone')); ?></a></p>
    </div>
    <div class="rounded-2xl bg-[#121212] p-6 border border-white/10">
      <h3 class="text-xl font-display text-amber-300"><?php echo e(config_value('footer_socials_title', 'Réseaux')); ?></h3>
      <div class="mt-3 flex flex-col gap-2 text-gray-300">
        <a href="<?php echo e(config_value('facebook_link')); ?>" target="_blank" rel="noopener" class="hover:text-amber-300">Facebook</a>
        <a href="<?php echo e(config_value('insta_link')); ?>" target="_blank" rel="noopener" class="hover:text-amber-300">Instagram</a>
      </div>
    </div>
  </div>
</section>

<?php
require __DIR__ . '/includes/footer.php';
