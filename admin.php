<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => $isHttps,
  'httponly' => true,
  'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();
require_once __DIR__ . '/config/db.php';

// Identifiants simples et robustes pour Hostinger
$ADMIN_USERNAME = 'admin';
// Hash généré avec password_hash('Gentleman2026!', PASSWORD_BCRYPT)
$ADMIN_PASSWORD_HASH = '$2b$10$O3EqPTpkCa0aiiq/429CrOiS812Zq88cP2OFrccx0pvcn0tEXw.LO';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = null;
}

function redirect_admin(string $tab = 'matchs'): void
{
  header('Location: /le-comptoir?tab=' . urlencode($tab));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function clear_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    $_SESSION['flash'] = null;
    return $flash;
}

function require_csrf(): void
{
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        http_response_code(403);
        exit('Requête invalide.');
    }
}



function normalize_datetime_input(string $input): ?string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $input, new DateTimeZone('Europe/Paris'));
    if (!$date) {
        return null;
    }
    return $date->format('Y-m-d H:i:s');
}

function upsert_site_config(PDO $pdo, array $values): void
{
  $stmt = $pdo->prepare('INSERT INTO site_config (`cle`, `valeur`) VALUES (:cle, :valeur) ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)');

  foreach ($values as $key => $value) {
    $stmt->execute([
      ':cle' => (string)$key,
      ':valeur' => (string)$value,
    ]);
  }
}

function upload_image_to_jpeg(array $file, string $prefix): string
{
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Upload image invalide.');
  }

  if (!is_uploaded_file($file['tmp_name'])) {
    throw new RuntimeException('Le fichier uploadé est invalide.');
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']) ?: '';
  $allowedMimes = [
    'image/jpeg' => true,
    'image/png' => true,
    'image/webp' => true,
  ];

  if (!isset($allowedMimes[$mime])) {
    throw new RuntimeException('Format image refusé. Utilisez JPEG, PNG ou WEBP.');
  }

  $binary = file_get_contents($file['tmp_name']);
  if ($binary === false) {
    throw new RuntimeException('Impossible de lire le fichier uploadé.');
  }

  $image = imagecreatefromstring($binary);
  if ($image === false) {
    throw new RuntimeException('Le fichier image est corrompu ou non supporté.');
  }

  $uploadDir = __DIR__ . '/assets/uploads';
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    imagedestroy($image);
    throw new RuntimeException('Impossible de créer le dossier d’upload.');
  }

  $safePrefix = slugify($prefix);
  $targetFile = $uploadDir . '/' . $safePrefix . '.jpg';
  if (!imagejpeg($image, $targetFile, 85)) {
    imagedestroy($image);
    throw new RuntimeException('Impossible d’enregistrer l’image.');
  }

  imagedestroy($image);
  return '/assets/uploads/' . $safePrefix . '.jpg';
}

function get_match_edit_value(?array $match, string $key, string $default = ''): string
{
  return $match[$key] ?? $default;
}

function mysql_to_datetime_local(?string $value): string
{
  if (!$value) {
    return '';
  }

  try {
    return (new DateTimeImmutable($value, new DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i');
  } catch (Throwable $e) {
    return '';
  }
}

function is_admin_authenticated(): bool
{
    return !empty($_SESSION['admin_authenticated']);
}

$tab = $_GET['tab'] ?? 'matchs';
if (!in_array($tab, ['matchs', 'carte', 'design', 'infos'], true)) {
    $tab = 'matchs';
}

// Logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    require_csrf();
    session_destroy();
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  header('Location: /le-comptoir');
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    require_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === $ADMIN_USERNAME && password_verify($password, $ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $username;
        set_flash('success', 'Connexion réussie.');
        redirect_admin('matchs');
    }

    set_flash('error', 'Identifiants invalides.');
    header('Location: /le-comptoir');
    exit;
}

  $editMatch = null;
  $editMatchId = (int)($_GET['edit_match'] ?? 0);
  if (is_admin_authenticated() && $editMatchId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM matchs WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $editMatchId]);
    $editMatch = $stmt->fetch() ?: null;
  }

if (is_admin_authenticated() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
      if ($action === 'add_manual_match') {
        $equipe1 = trim((string)($_POST['equipe_1'] ?? ''));
        $equipe2 = trim((string)($_POST['equipe_2'] ?? ''));
        $competition = trim((string)($_POST['competition'] ?? ''));
        $dateMatch = trim((string)($_POST['date_match'] ?? ''));
        $sport = trim((string)($_POST['sport'] ?? 'Soccer'));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if ($equipe1 === '' || $equipe2 === '' || $dateMatch === '') {
            throw new RuntimeException('Veuillez remplir les équipes et la date.');
        }

        $normalizedDate = normalize_datetime_input($dateMatch);
        if ($normalizedDate === null) {
            throw new RuntimeException('Format de date et heure invalide.');
        }

        if ($isFeatured === 1) {
            $pdo->exec('UPDATE `matchs` SET `is_featured` = 0');
        }

        // Tenter de trouver s'il y a déjà un match pour ces équipes à +/- 3 jours
        $slug = null;
        $dayStart = date('Y-m-d 00:00:00', strtotime($normalizedDate) - 3 * 86400);
        $dayEnd = date('Y-m-d 23:59:59', strtotime($normalizedDate) + 3 * 86400);
        $checkStmt = $pdo->prepare('SELECT slug FROM matchs WHERE equipe_1 = :e1 AND equipe_2 = :e2 AND date_match BETWEEN :dstart AND :dend LIMIT 1');
        $checkStmt->execute([
            ':e1' => $equipe1,
            ':e2' => $equipe2,
            ':dstart' => $dayStart,
            ':dend' => $dayEnd,
        ]);
        $slug = $checkStmt->fetchColumn() ?: null;

        if ($slug === null) {
            $slug = generate_unique_match_slug($pdo, $equipe1, $equipe2, $normalizedDate);
        }

        $stmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, sport, statut, is_active, is_featured) 
            VALUES (:slug, :equipe_1, :equipe_2, :competition, :date_match, :sport, \'scheduled\', 1, :is_featured) 
            ON DUPLICATE KEY UPDATE is_active = 1, is_featured = VALUES(is_featured), date_match = VALUES(date_match), competition = VALUES(competition), sport = VALUES(sport)');
        $stmt->execute([
            ':slug' => $slug,
            ':equipe_1' => $equipe1,
            ':equipe_2' => $equipe2,
            ':competition' => $competition !== '' ? $competition : 'Autre',
            ':date_match' => $normalizedDate,
            ':sport' => $sport,
            ':is_featured' => $isFeatured,
        ]);

        set_flash('success', 'Match "' . $equipe1 . ' vs ' . $equipe2 . '" enregistré avec succès !');
        redirect_admin('matchs');
      }

      if ($action === 'set_featured_match') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->exec('UPDATE matchs SET is_featured = 0');
        $stmt = $pdo->prepare('UPDATE matchs SET is_featured = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        set_flash('success', 'Match mis en vedette à l\'affiche.');
        redirect_admin('matchs');
      }

      if ($action === 'remove_featured_match') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE matchs SET is_featured = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        set_flash('success', 'Match retiré de l\'affiche.');
        redirect_admin('matchs');
      }

      if ($action === 'save_site_config') {
        upsert_site_config($pdo, [
          'site_name' => trim((string)($_POST['site_name'] ?? '')),
          'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
          'hero_title' => trim((string)($_POST['hero_title'] ?? '')),
          'hero_subtitle' => trim((string)($_POST['hero_subtitle'] ?? '')),
          'hero_cta_primary' => trim((string)($_POST['hero_cta_primary'] ?? '')),
          'hero_cta_secondary' => trim((string)($_POST['hero_cta_secondary'] ?? '')),
          'bar_adresse' => trim((string)($_POST['bar_adresse'] ?? '')),
          'bar_telephone' => trim((string)($_POST['bar_telephone'] ?? '')),
          'insta_link' => trim((string)($_POST['insta_link'] ?? '')),
          'facebook_link' => trim((string)($_POST['facebook_link'] ?? '')),
          'booking_privateaser_url' => trim((string)($_POST['booking_privateaser_url'] ?? '')),
          'booking_mistergoodbeer_url' => trim((string)($_POST['booking_mistergoodbeer_url'] ?? '')),
          'horaires_semaine' => trim((string)($_POST['horaires_semaine'] ?? '')),
          'horaires_weekend' => trim((string)($_POST['horaires_weekend'] ?? '')),
          'horaires_dimanche' => trim((string)($_POST['horaires_dimanche'] ?? '')),
          'sportsdb_api_key' => trim((string)($_POST['sportsdb_api_key'] ?? '')),
        ]);

        $flashConfig = [
          'site_name' => trim((string)($_POST['site_name'] ?? '')),
          'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
          'hero_title' => trim((string)($_POST['hero_title'] ?? '')),
          'hero_subtitle' => trim((string)($_POST['hero_subtitle'] ?? '')),
          'hero_cta_primary' => trim((string)($_POST['hero_cta_primary'] ?? '')),
          'hero_cta_secondary' => trim((string)($_POST['hero_cta_secondary'] ?? '')),
          'bar_adresse' => trim((string)($_POST['bar_adresse'] ?? '')),
          'bar_telephone' => trim((string)($_POST['bar_telephone'] ?? '')),
          'insta_link' => trim((string)($_POST['insta_link'] ?? '')),
          'facebook_link' => trim((string)($_POST['facebook_link'] ?? '')),
          'booking_privateaser_url' => trim((string)($_POST['booking_privateaser_url'] ?? '')),
          'booking_mistergoodbeer_url' => trim((string)($_POST['booking_mistergoodbeer_url'] ?? '')),
          'horaires_semaine' => trim((string)($_POST['horaires_semaine'] ?? '')),
          'horaires_weekend' => trim((string)($_POST['horaires_weekend'] ?? '')),
          'horaires_dimanche' => trim((string)($_POST['horaires_dimanche'] ?? '')),
          'sportsdb_api_key' => trim((string)($_POST['sportsdb_api_key'] ?? '')),
        ];
        foreach ($flashConfig as $key => $value) {
          $config[$key] = $value;
        }

        set_flash('success', 'Infos du bar mises à jour.');
        redirect_admin('infos');
      }

      if ($action === 'upload_hero_bg') {
        if (empty($_FILES['hero_bg']) || (int)$_FILES['hero_bg']['error'] === UPLOAD_ERR_NO_FILE) {
          throw new RuntimeException('Veuillez sélectionner une image pour le hero.');
        }

        $path = upload_image_to_jpeg($_FILES['hero_bg'], 'hero-bg');
        upsert_site_config($pdo, ['hero_bg_image' => $path]);
        $config['hero_bg_image'] = $path;
        set_flash('success', 'Fond du hero mis à jour.');
        redirect_admin('design');
      }

      if ($action === 'upload_carousel_img') {
        if (empty($_FILES['carousel_img']) || (int)$_FILES['carousel_img']['error'] === UPLOAD_ERR_NO_FILE) {
          throw new RuntimeException('Veuillez sélectionner une image pour le carrousel.');
        }

        $slug = 'carousel-' . time();
        $path = upload_image_to_jpeg($_FILES['carousel_img'], $slug);

        $carouselJson = config_value('carousel_images', '[]');
        $carouselImages = json_decode($carouselJson, true) ?: [];
        $carouselImages[] = $path;

        upsert_site_config($pdo, ['carousel_images' => json_encode($carouselImages)]);
        set_flash('success', 'Photo ajoutée au carrousel.');
        redirect_admin('design');
      }

      if ($action === 'delete_carousel_img') {
        $imgPath = trim((string)($_POST['img_path'] ?? ''));
        $carouselJson = config_value('carousel_images', '[]');
        $carouselImages = json_decode($carouselJson, true) ?: [];

        $carouselImages = array_values(array_filter($carouselImages, function($img) use ($imgPath) {
            return $img !== $imgPath;
        }));

        $fullPath = __DIR__ . $imgPath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }

        upsert_site_config($pdo, ['carousel_images' => json_encode($carouselImages)]);
        set_flash('success', 'Photo retirée du carrousel.');
        redirect_admin('design');
      }

        if ($action === 'save_auto_feature_config') {
            $autoFeat = $_POST['auto_feat'] ?? [];
            if (!is_array($autoFeat)) {
                $autoFeat = [];
            }
            upsert_site_config($pdo, [
                'auto_feature_competitions' => json_encode($autoFeat)
            ]);
            $config['auto_feature_competitions'] = json_encode($autoFeat);
            set_flash('success', 'Filtres de mise à l\'affiche automatique mis à jour.');
            redirect_admin('matchs');
        }

        if ($action === 'disable_match') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE matchs SET is_active = 0 WHERE id = :id');
            $stmt->execute([':id' => $id]);
            set_flash('success', 'Match désactivé.');
            redirect_admin('matchs');
        }

        if ($action === 'delete_match') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM matchs WHERE id = :id');
            $stmt->execute([':id' => $id]);
            set_flash('success', 'Match supprimé.');
            redirect_admin('matchs');
        }

        if ($action === 'update_product') {
            $id = (int)($_POST['id'] ?? 0);
            $prixNormal = (string)($_POST['prix_normal'] ?? '0');
            $prixHappy = (string)($_POST['prix_happy_hour'] ?? '');

            $stmt = $pdo->prepare('UPDATE carte_produits SET prix_normal = :prix_normal, prix_happy_hour = :prix_happy_hour WHERE id = :id');
            $stmt->execute([
                ':prix_normal' => number_format((float)$prixNormal, 2, '.', ''),
                ':prix_happy_hour' => $prixHappy === '' ? null : number_format((float)$prixHappy, 2, '.', ''),
                ':id' => $id,
            ]);
            set_flash('success', 'Produit mis à jour.');
            redirect_admin('carte');
        }

        if ($action === 'add_product') {
            $categorie = trim((string)($_POST['categorie'] ?? ''));
            $nom = trim((string)($_POST['nom'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $prixNormal = (string)($_POST['prix_normal'] ?? '0');
            $prixHappy = (string)($_POST['prix_happy_hour'] ?? '');

            if ($categorie === '' || $nom === '') {
                throw new RuntimeException('La catégorie et le nom sont obligatoires.');
            }

            $stmt = $pdo->prepare('INSERT INTO carte_produits (categorie, nom, description, prix_normal, prix_happy_hour) VALUES (:categorie, :nom, :description, :prix_normal, :prix_happy_hour)');
            $stmt->execute([
                ':categorie' => $categorie,
                ':nom' => $nom,
                ':description' => $description,
                ':prix_normal' => number_format((float)$prixNormal, 2, '.', ''),
                ':prix_happy_hour' => $prixHappy === '' ? null : number_format((float)$prixHappy, 2, '.', ''),
            ]);

            set_flash('success', 'Produit ajouté.');
            redirect_admin('carte');
        }
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
        redirect_admin($tab);
    }
}

$flash = clear_flash();

if (!is_admin_authenticated()) {
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin - Le Gentleman Pub</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
          tailwind.config = {
            theme: {
              extend: {
                colors: {
                  pubdark: '#121212',
                  pubnav: '#1A1A1A',
                  pubaccent: '#D4AF37'
                },
                fontFamily: {
                  display: ['Playfair Display', 'serif'],
                  body: ['Inter', 'system-ui', 'sans-serif']
                }
              }
            }
          }
        </script>
    </head>
    <body class="min-h-screen bg-[#0B2516] text-gray-100 font-body">
      <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md rounded-2xl bg-[#1A1A1A] border border-white/10 shadow-2xl p-8">
          <div class="text-center mb-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-400 text-black font-bold mx-auto mb-4">G</div>
            <h1 class="text-3xl font-display text-amber-300">Admin Dashboard</h1>
            <p class="mt-2 text-sm text-gray-400">Le Gentleman Pub - accès sécurisé</p>
          </div>

          <?php if ($flash && $flash['type'] === 'error'): ?>
            <div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200"><?php echo e($flash['message']); ?></div>
          <?php endif; ?>

          <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="login">
            <div>
              <label class="mb-2 block text-sm text-gray-300">Identifiant</label>
              <input name="username" type="text" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Mot de passe</label>
              <input name="password" type="password" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <button type="submit" class="w-full rounded-lg bg-amber-400 px-4 py-3 font-semibold text-black hover:bg-amber-300">Se connecter</button>
          </form>
          <p class="mt-6 text-xs text-gray-500 text-center">Astuce: modifiez le couple d'accès en début de fichier pour production.</p>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$matchs = $pdo->query('SELECT * FROM matchs ORDER BY 
  (date_match < DATE_SUB(NOW(), INTERVAL 2 HOUR)) ASC,
  CASE WHEN date_match >= DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN date_match END ASC,
  CASE WHEN date_match < DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN date_match END DESC')->fetchAll();
$produits = $pdo->query('SELECT * FROM carte_produits ORDER BY categorie, nom')->fetchAll();
$categories = ['Bières', 'Cocktails', 'Softs', 'Food', 'Planches'];
$siteName = config_value('site_name', 'Le Gentleman Pub');
$heroBgImage = config_value('hero_bg_image', '/assets/uploads/hero-bg.jpg');

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?php echo e($siteName); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            pubdark: '#121212',
            pubnav: '#1A1A1A',
            pubaccent: '#D4AF37'
          },
          fontFamily: {
            display: ['Playfair Display', 'serif'],
            body: ['Inter', 'system-ui', 'sans-serif']
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-[#0B2516] text-gray-100 font-body">
  <header class="border-b border-white/10 bg-[#1A1A1A]">
    <div class="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display text-amber-300"><?php echo e($siteName); ?></h1>
        <p class="text-sm text-gray-400">Dashboard d'administration</p>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="logout">
        <button class="rounded-lg border border-amber-400/40 px-4 py-2 text-amber-300 hover:bg-amber-400 hover:text-black">Déconnexion</button>
      </form>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-4 py-8">
    <?php if ($flash): ?>
      <div class="mb-6 rounded-xl px-4 py-3 text-sm <?php echo $flash['type'] === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-200' : 'bg-red-500/10 border border-red-500/30 text-red-200'; ?>">
        <?php echo e($flash['message']); ?>
      </div>
    <?php endif; ?>

    <div class="mb-6 flex flex-wrap gap-3">
      <a href="/le-comptoir?tab=matchs" class="rounded-full px-4 py-2 <?php echo $tab === 'matchs' ? 'bg-amber-400 text-black' : 'bg-white/5 text-gray-300'; ?>">Matchs</a>
      <a href="/le-comptoir?tab=carte" class="rounded-full px-4 py-2 <?php echo $tab === 'carte' ? 'bg-amber-400 text-black' : 'bg-white/5 text-gray-300'; ?>">Carte</a>
      <a href="/le-comptoir?tab=design" class="rounded-full px-4 py-2 <?php echo $tab === 'design' ? 'bg-amber-400 text-black' : 'bg-white/5 text-gray-300'; ?>">Design & Photos</a>
      <a href="/le-comptoir?tab=infos" class="rounded-full px-4 py-2 <?php echo $tab === 'infos' ? 'bg-amber-400 text-black' : 'bg-white/5 text-gray-300'; ?>">Infos du Bar</a>
    </div>

    <?php if ($tab === 'matchs'): ?>
      <?php
      $lastImportTime = "Aucune synchronisation enregistrée";
      $lockFile = __DIR__ . '/api/last_import.txt';
      if (file_exists($lockFile)) {
          $lastImportTime = date('d/m/Y à H:i:s', filemtime($lockFile));
      }
      ?>

      <!-- iCal Auto-Import Panel -->
      <div class="grid gap-6 md:grid-cols-3 mb-8">
        <!-- iCal Sync Card -->
        <div class="md:col-span-2 rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4 mb-4">
              <h2 class="text-xl font-display text-amber-300 flex items-center gap-2">
                <span>🔄 Synchronisation iCal</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold border border-emerald-500/20">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Actif
                </span>
              </h2>
            </div>
            
            <p class="text-xs text-gray-400 leading-relaxed">
              Les prochains matchs des 15 prochains jours sont importés automatiquement toutes les 24h via les flux calendriers (.ics). Seules les grandes affiches correspondantes aux équipes du bar (ex: PSG, OM, France, Stade Toulousain...) sont importées pour garder le programme propre.
            </p>

            <div class="mt-4">
              <span class="text-[10px] text-gray-500 uppercase tracking-wider block mb-2 font-semibold font-body">Championnats & Calendriers synchronisés (Foot: iCal / Rugby: SportsDB) :</span>
              <div class="flex flex-wrap gap-1.5 mb-4">
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ Coupe du Monde</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ Ligue 1</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ Champions League</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ Europa League</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ Premier League</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ La Liga</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">⚽ Serie A</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">🏉 Top 14</span>
                <span class="rounded-full bg-white/5 border border-white/10 px-2 py-0.5 text-[10px] text-gray-300">🏉 Six Nations</span>
              </div>
              
              <form method="post" class="border-t border-white/10 pt-4">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_auto_feature_config">
                
                <span class="text-[10px] text-gray-400 uppercase tracking-wider block mb-3 font-semibold font-body">⭐️ Mettre automatiquement à l'affiche :</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  <?php
                  $autoFeatJson = config_value('auto_feature_competitions', '["Coupe du Monde"]');
                  $autoFeatList = json_decode($autoFeatJson, true) ?: [];
                  $allCompetitions = [
                      'Coupe du Monde' => '⚽ Coupe du Monde',
                      'Ligue 1' => '⚽ Ligue 1',
                      'Champions League' => '⚽ Champions League',
                      'Europa League' => '⚽ Europa League',
                      'Premier League' => '⚽ Premier League',
                      'La Liga' => '⚽ La Liga',
                      'Serie A' => '⚽ Serie A',
                      'Top 14' => '🏉 Top 14',
                      'Six Nations' => '🏉 Six Nations'
                  ];
                  foreach ($allCompetitions as $key => $label):
                      $checked = in_array($key, $autoFeatList, true) ? 'checked' : '';
                  ?>
                    <label class="flex items-center gap-2 text-xs text-gray-300 hover:text-white cursor-pointer select-none">
                      <input type="checkbox" name="auto_feat[]" value="<?php echo e($key); ?>" <?php echo $checked; ?> class="w-4 h-4 rounded bg-[#121212] border-white/10 text-amber-400 focus:ring-amber-400 cursor-pointer">
                      <span><?php echo e($label); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div class="mt-4 flex justify-end">
                  <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-xs font-semibold text-black hover:bg-amber-300 transition-all shadow-[0_0_10px_rgba(212,175,55,0.15)]">
                    Enregistrer l'affiche auto
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="mt-6 border-t border-white/10 pt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="text-xs text-gray-400">
              Dernière synchronisation : <br class="sm:hidden">
              <span id="last-sync-time" class="text-amber-300 font-semibold"><?php echo e($lastImportTime); ?></span>
            </div>
            
            <button type="button" id="btn-force-sync" onclick="triggerForceSync()" class="rounded-lg bg-amber-400 px-4 py-2 text-xs font-semibold text-black hover:bg-amber-300 transition-all shadow-[0_0_15px_rgba(212,175,55,0.1)] flex items-center gap-1.5">
              <svg id="sync-spinner" class="hidden animate-spin h-3.5 w-3.5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Synchroniser maintenant</span>
            </button>
          </div>
          
          <!-- Terminal log output during manual sync -->
          <div id="sync-console-container" class="mt-4 hidden">
            <div class="text-[10px] text-gray-500 font-semibold mb-1 uppercase tracking-wider">Console de synchronisation :</div>
            <div id="sync-console" class="bg-black/40 border border-white/5 rounded-xl p-3 font-mono text-[10px] text-emerald-400 max-h-32 overflow-y-auto space-y-1">
            </div>
          </div>
        </div>

        <!-- Manual Match Addition Card -->
        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg flex flex-col justify-between">
          <div>
            <h2 class="text-xl font-display text-amber-300 border-b border-white/10 pb-4 mb-4 flex items-center gap-2">
              <span>➕ Match Manuel</span>
            </h2>
            <p class="text-xs text-gray-400 leading-relaxed mb-4">
              Si un match n'apparaît pas dans l'auto-import ou si c'est un autre sport (ex: handball, formule 1, tennis...), vous pouvez l'ajouter ici.
            </p>
          </div>
          
          <button type="button" onclick="document.getElementById('manual-match-modal').classList.remove('hidden')" class="w-full rounded-lg border border-white/10 bg-white/5 py-3 text-xs font-semibold text-gray-300 hover:border-amber-400/50 hover:text-amber-300 hover:bg-amber-400/5 transition-all text-center">
            Créer un match personnalisé
          </button>
        </div>
      </div>

      <!-- Pop-up Modal for Manual Match (Saves page space, looks premium) -->
      <div id="manual-match-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm hidden">
        <div class="w-full max-w-lg rounded-2xl bg-[#1A1A1A] border border-white/10 shadow-2xl p-6 relative">
          <button type="button" onclick="document.getElementById('manual-match-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white text-xl">&times;</button>
          
          <h3 class="text-2xl font-display text-amber-300 border-b border-white/10 pb-3 mb-6">Ajouter un match manuel</h3>
          
          <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add_manual_match">
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Sport</label>
                <select name="sport" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white text-xs outline-none focus:border-amber-400">
                  <option value="Soccer">⚽ Football</option>
                  <option value="Rugby">🏉 Rugby</option>
                  <option value="Autre">🏆 Autre sport</option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Compétition</label>
                <input name="competition" type="text" placeholder="ex: Ligue 1, Top 14, Amical" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white text-xs outline-none focus:border-amber-400">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Équipe Domicile</label>
                <input name="equipe_1" type="text" placeholder="ex: PSG" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white text-xs outline-none focus:border-amber-400">
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Équipe Extérieur</label>
                <input name="equipe_2" type="text" placeholder="ex: OM" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white text-xs outline-none focus:border-amber-400">
              </div>
            </div>

            <div>
              <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase">Date & Heure du Match (Heure locale FR)</label>
              <input name="date_match" type="datetime-local" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white text-xs outline-none focus:border-amber-400">
            </div>

            <div class="flex items-center gap-2 pt-2">
              <input type="checkbox" name="is_featured" id="is_featured_modal" class="w-4 h-4 rounded bg-[#121212] border-white/10 text-amber-400 focus:ring-amber-400 cursor-pointer">
              <label for="is_featured_modal" class="text-xs text-gray-300 cursor-pointer">Mettre à l'affiche immédiatement (Bandeau d'accueil)</label>
            </div>

            <div class="flex justify-end gap-3 border-t border-white/10 pt-4 mt-6">
              <button type="button" onclick="document.getElementById('manual-match-modal').classList.add('hidden')" class="rounded-lg bg-white/5 border border-white/10 px-4 py-2 text-xs text-gray-300 hover:bg-white/10 transition-all">Annuler</button>
              <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-xs font-semibold text-black hover:bg-amber-300 transition-all shadow-[0_0_15px_rgba(212,175,55,0.2)]">Enregistrer le match</button>
            </div>
          </form>
        </div>
      </div>

      <?php
      $footMatchs = array_filter($matchs, function($m) { return $m['sport'] === 'Soccer'; });
      $rugbyMatchs = array_filter($matchs, function($m) { return $m['sport'] === 'Rugby'; });
      $autresMatchs = array_filter($matchs, function($m) { return $m['sport'] !== 'Soccer' && $m['sport'] !== 'Rugby'; });
      
      // Helper function to render a table of existing matches
      function render_existing_matchs_table(array $list, string $title, string $icon): void {
      ?>
        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
          <h3 class="text-xl font-display text-amber-300 flex items-center gap-2">
            <span><?php echo $icon; ?></span>
            <span><?php echo e($title); ?></span>
            <span class="text-xs font-normal text-gray-400">(<?php echo count($list); ?> match(s))</span>
          </h3>
          <div class="mt-6 overflow-x-auto rounded-xl border border-white/10 bg-[#121212]">
            <table class="w-full text-left text-sm text-gray-300">
              <thead class="text-xs text-gray-400 uppercase bg-white/5 border-b border-white/10">
                <tr>
                  <th class="px-4 py-3">Équipes</th>
                  <th class="px-4 py-3">Compétition / Date</th>
                  <th class="px-4 py-3 text-center">Statut</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <?php foreach ($list as $match): 
                  $d = new DateTimeImmutable($match['date_match']);
                  $badge = get_match_status_badge($match);
                  $isLive = is_match_live($match);
                  $homeLogo = !empty($match['image_path']) ? $match['image_path'] : get_team_logo($match['equipe_1']);
                  $awayLogo = !empty($match['image_path_away']) ? $match['image_path_away'] : get_team_logo($match['equipe_2']);
                  if ($homeLogo === '') { $homeLogo = null; }
                  if ($awayLogo === '') { $awayLogo = null; }
                  
                  // Vérifier si le match est aujourd'hui
                  $matchDate = $d->format('Y-m-d');
                  $todayDate = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
                  $isToday = ($matchDate === $todayDate);
                  
                  // Déterminer la classe de la ligne en fonction du statut
                  $rowClass = "align-middle hover:bg-white/5 transition-all border-l-2 border-transparent";
                  if ($isLive) {
                      $rowClass = "align-middle bg-emerald-500/5 hover:bg-emerald-500/10 transition-all border-l-2 border-emerald-500";
                  } elseif ($isToday && $badge !== 'FINISHED') {
                      $rowClass = "align-middle bg-amber-400/5 hover:bg-amber-400/10 transition-all border-l-2 border-amber-400/40";
                  } elseif ($badge === 'FINISHED') {
                      $rowClass = "align-middle hover:bg-white/5 opacity-60 transition-all border-l-2 border-transparent";
                  }
                ?>
                  <tr class="<?php echo $rowClass; ?>">
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                          <?php echo render_team_logo_html($match['equipe_1'], $match['image_path'], 'h-8 w-8 object-contain', mb_substr($match['equipe_1'], 0, 1)); ?>
                          <span class="text-gray-500 text-xs">vs</span>
                          <?php echo render_team_logo_html($match['equipe_2'], $match['image_path_away'], 'h-8 w-8 object-contain', mb_substr($match['equipe_2'], 0, 1)); ?>
                        </div>
                        <div>
                          <div class="font-semibold text-white"><?php echo e($match['equipe_1']); ?> <span class="text-gray-500 font-normal">c.</span> <?php echo e($match['equipe_2']); ?></div>
                          <div class="text-[10px] text-gray-500 mt-0.5">Slug: <?php echo e($match['slug']); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4">
                      <div class="text-white font-medium"><?php echo e((string)($match['competition'] ?: 'Compétition')); ?></div>
                      <div class="text-gray-400 text-xs mt-0.5"><?php echo e($d->format('d/m/Y H:i')); ?></div>
                    </td>
                    <td class="px-4 py-4 text-center">
                      <?php if ($isLive): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold uppercase animate-pulse border border-emerald-500/20">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                          <?php echo e($badge); ?>
                        </span>
                      <?php elseif ($badge === 'FINISHED'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/5 text-gray-400 text-xs font-semibold uppercase border border-white/10">
                          <?php echo e($badge); ?>
                        </span>
                      <?php elseif ($isToday): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-400/20 text-amber-300 text-xs font-bold uppercase border border-amber-400/40 shadow-[0_0_10px_rgba(212,175,55,0.15)] animate-pulse">
                          <span>📅 Aujourd'hui</span>
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-400/5 text-amber-300/80 text-xs font-semibold uppercase border border-amber-400/10">
                          <?php echo e($badge); ?>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <!-- Affiche Toggle -->
                        <form method="post" style="display:inline-block">
                          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                          <?php if ((int)($match['is_featured'] ?? 0) === 1): ?>
                            <input type="hidden" name="action" value="remove_featured_match">
                            <input type="hidden" name="id" value="<?php echo (int)$match['id']; ?>">
                            <button type="submit" class="rounded-lg bg-amber-400 px-3 py-1.5 text-xs text-black font-semibold hover:bg-amber-300 flex items-center gap-1 transition-all">
                              ★ À l'affiche
                            </button>
                          <?php else: ?>
                            <input type="hidden" name="action" value="set_featured_match">
                            <input type="hidden" name="id" value="<?php echo (int)$match['id']; ?>">
                            <button type="submit" class="rounded-lg bg-white/5 border border-white/10 px-3 py-1.5 text-xs text-gray-300 hover:border-amber-400/50 hover:text-amber-300 font-medium transition-all">
                              Mettre en avant
                            </button>
                          <?php endif; ?>
                        </form>
                        
                        <!-- Active/Disable -->
                        <?php if ((int)$match['is_active'] === 1): ?>
                          <form method="post" style="display:inline-block">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="disable_match">
                            <input type="hidden" name="id" value="<?php echo (int)$match['id']; ?>">
                            <button type="submit" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-gray-400 hover:bg-white/5 transition-all">Désactiver</button>
                          </form>
                        <?php endif; ?>
                        
                        <!-- Delete -->
                        <form method="post" onsubmit="return confirm('Supprimer définitivement ce match de la programmation ?');" style="display:inline-block">
                          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="action" value="delete_match">
                          <input type="hidden" name="id" value="<?php echo (int)$match['id']; ?>">
                          <button type="submit" class="rounded-lg border border-red-500/30 px-3 py-1.5 text-xs text-red-400 hover:bg-red-500/10 hover:text-white transition-all">Supprimer</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($list)): ?>
                  <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 italic">Aucun match programmé dans cette catégorie.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php
      }
      ?>

      <div class="space-y-8">
        <h2 class="text-2xl font-display text-amber-300 border-b border-white/10 pb-2">Programmation Active (Matchs Diffusés)</h2>
        <?php 
          render_existing_matchs_table($footMatchs, "Football", "⚽");
          render_existing_matchs_table($rugbyMatchs, "Rugby", "🏉");
          render_existing_matchs_table($autresMatchs, "Autres Sports", "🏆");
        ?>
      </div>
    <?php elseif ($tab === 'design'): 
      $carouselJson = config_value('carousel_images', '[]');
      $carouselImages = json_decode($carouselJson, true) ?: [];
    ?>
      <section class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
          <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
            <h2 class="text-2xl font-display text-amber-300">Design & Photos</h2>
            <form method="post" enctype="multipart/form-data" class="mt-6 space-y-4">
              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="action" value="upload_hero_bg">
              <div>
                <label class="mb-2 block text-sm text-gray-300">Fond de la section Hero</label>
                <input type="file" name="hero_bg" accept="image/jpeg,image/png,image/webp" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
                <p class="mt-2 text-xs text-gray-500">Le fichier sera converti et enregistré en <span class="text-amber-300">hero-bg.jpg</span>.</p>
              </div>
              <button class="rounded-lg bg-amber-400 px-4 py-3 font-semibold text-black hover:bg-amber-300">Mettre à jour le Hero</button>
            </form>
          </div>

          <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
            <h2 class="text-2xl font-display text-amber-300">Ajouter une photo au carrousel</h2>
            <form method="post" enctype="multipart/form-data" class="mt-6 space-y-4">
              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="action" value="upload_carousel_img">
              <div>
                <label class="mb-2 block text-sm text-gray-300">Sélectionner une photo</label>
                <input type="file" name="carousel_img" accept="image/jpeg,image/png,image/webp" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
              </div>
              <button class="rounded-lg bg-amber-400 px-4 py-3 font-semibold text-black hover:bg-amber-300">Ajouter la photo</button>
            </form>
          </div>
        </div>

        <div class="space-y-6">
          <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
            <h3 class="text-xl font-display text-amber-300">Aperçu du fond Hero</h3>
            <div class="mt-4 overflow-hidden rounded-xl border border-white/10">
              <img src="<?php echo e($heroBgImage); ?>" alt="Fond actuel du Hero" class="h-48 w-full object-cover">
            </div>
          </div>

          <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
            <h3 class="text-xl font-display text-amber-300">Photos actuelles du carrousel</h3>
            <div class="mt-4 grid grid-cols-2 gap-4">
              <?php foreach ($carouselImages as $img): ?>
                <div class="relative rounded-lg overflow-hidden border border-white/10 bg-black/40 group">
                  <img src="<?php echo e($img); ?>" class="h-32 w-full object-cover">
                  <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <form method="post" onsubmit="return confirm('Retirer cette photo ?');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="action" value="delete_carousel_img">
                      <input type="hidden" name="img_path" value="<?php echo e($img); ?>">
                      <button class="rounded bg-red-600 px-3 py-1.5 text-xs text-white hover:bg-red-700">Retirer</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($carouselImages)): ?>
                <p class="col-span-2 text-sm text-gray-400">Aucune photo dans le carrousel.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>
    <?php elseif ($tab === 'infos'): ?>
      <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
          <h2 class="text-2xl font-display text-amber-300">Infos du Bar</h2>
          <form method="post" class="mt-6 grid gap-4">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save_site_config">
            <div>
              <label class="mb-2 block text-sm text-gray-300">Nom du site</label>
              <input name="site_name" value="<?php echo e(config_value('site_name')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Slogan / Tagline du site</label>
              <input name="site_tagline" value="<?php echo e(config_value('site_tagline')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Titre Hero</label>
              <input name="hero_title" value="<?php echo e(config_value('hero_title')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Sous-titre Hero</label>
              <textarea name="hero_subtitle" rows="3" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400"><?php echo e(config_value('hero_subtitle')); ?></textarea>
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">CTA Principal par défaut (Bouton principal)</label>
              <input name="hero_cta_primary" value="<?php echo e(config_value('hero_cta_primary')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">CTA Secondaire (Bouton secondaire)</label>
              <input name="hero_cta_secondary" value="<?php echo e(config_value('hero_cta_secondary')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Adresse</label>
              <input name="bar_adresse" value="<?php echo e(config_value('bar_adresse')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Téléphone</label>
              <input name="bar_telephone" value="<?php echo e(config_value('bar_telephone')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Lien Privateaser</label>
              <input name="booking_privateaser_url" value="<?php echo e(config_value('booking_privateaser_url')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Lien MisterGoodBeer</label>
              <input name="booking_mistergoodbeer_url" value="<?php echo e(config_value('booking_mistergoodbeer_url')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Clé API TheSportsDB (optionnel)</label>
              <input name="sportsdb_api_key" placeholder="Par défaut: 3 (clé publique limitée)" value="<?php echo e(config_value('sportsdb_api_key')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
              <p class="mt-2 text-xs text-gray-500">Laissez vide ou '3' pour utiliser la clé gratuite. Une clé payante permet de charger la totalité des matchs de chaque championnat.</p>
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Lien Instagram</label>
              <input name="insta_link" value="<?php echo e(config_value('insta_link')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Lien Facebook</label>
              <input name="facebook_link" value="<?php echo e(config_value('facebook_link')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Horaires semaine</label>
              <input name="horaires_semaine" value="<?php echo e(config_value('horaires_semaine')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Horaires weekend</label>
              <input name="horaires_weekend" value="<?php echo e(config_value('horaires_weekend')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Horaires dimanche</label>
              <input name="horaires_dimanche" value="<?php echo e(config_value('horaires_dimanche')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <button class="rounded-lg bg-amber-400 px-4 py-3 font-semibold text-black hover:bg-amber-300">Enregistrer les infos</button>
          </form>
        </div>
        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg space-y-4">
          <h3 class="text-xl font-display text-amber-300">Résumé actuel</h3>
          <div class="rounded-lg bg-[#121212] p-4 text-sm text-gray-300 space-y-2">
            <p><span class="text-amber-300">Nom du site :</span> <?php echo e(config_value('site_name')); ?></p>
            <p><span class="text-amber-300">Slogan :</span> <?php echo e(config_value('site_tagline')); ?></p>
            <p><span class="text-amber-300">Hero :</span> <?php echo e(config_value('hero_title')); ?></p>
            <p><span class="text-amber-300">Adresse :</span> <?php echo e(config_value('bar_adresse')); ?></p>
            <p><span class="text-amber-300">Téléphone :</span> <?php echo e(config_value('bar_telephone')); ?></p>
            <p><span class="text-amber-300">Privateaser :</span> <a class="text-primary hover:underline text-xs" href="<?php echo e(config_value('booking_privateaser_url')); ?>" target="_blank"><?php echo e(config_value('booking_privateaser_url')); ?></a></p>
            <p><span class="text-amber-300">MisterGoodBeer :</span> <a class="text-primary hover:underline text-xs" href="<?php echo e(config_value('booking_mistergoodbeer_url')); ?>" target="_blank"><?php echo e(config_value('booking_mistergoodbeer_url')); ?></a></p>
          </div>
        </div>
      </section>
    <?php else: ?>
      <section class="space-y-6">
        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
          <h2 class="text-2xl font-display text-amber-300">Ajouter un produit</h2>
          <form method="post" class="mt-6 grid gap-4 md:grid-cols-2">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add_product">
            <div>
              <label class="mb-2 block text-sm text-gray-300">Catégorie</label>
              <select name="categorie" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
                <?php foreach ($categories as $category): ?>
                  <option value="<?php echo e($category); ?>"><?php echo e($category); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Nom</label>
              <input name="nom" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div class="md:col-span-2">
              <label class="mb-2 block text-sm text-gray-300">Description</label>
              <input name="description" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Prix normal</label>
              <input name="prix_normal" type="number" step="0.01" min="0" required class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Prix Happy Hour</label>
              <input name="prix_happy_hour" type="number" step="0.01" min="0" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div class="md:col-span-2">
              <button class="rounded-lg bg-amber-400 px-4 py-3 font-semibold text-black hover:bg-amber-300">Ajouter le produit</button>
            </div>
          </form>
        </div>

        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg overflow-x-auto">
          <h2 class="text-2xl font-display text-amber-300">Carte actuelle</h2>
          <table class="mt-6 min-w-full text-left text-sm">
            <thead class="text-gray-400">
              <tr class="border-b border-white/10">
                <th class="py-3 pr-4">Catégorie</th>
                <th class="py-3 pr-4">Produit</th>
                <th class="py-3 pr-4">Description</th>
                <th class="py-3 pr-4">Prix normal</th>
                <th class="py-3 pr-4">Happy Hour</th>
                <th class="py-3 pr-4">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($produits as $produit): ?>
                <tr class="border-b border-white/5 align-top">
                  <td class="py-4 pr-4 text-gray-300"><?php echo e($produit['categorie']); ?></td>
                  <td class="py-4 pr-4 text-white"><?php echo e($produit['nom']); ?></td>
                  <td class="py-4 pr-4 text-gray-400 max-w-xs"><?php echo e((string)($produit['description'] ?? '')); ?></td>
                  <td class="py-4 pr-4">
                    <form method="post" class="flex items-center gap-2">
                      <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                      <input type="hidden" name="action" value="update_product">
                      <input type="hidden" name="id" value="<?php echo (int)$produit['id']; ?>">
                      <input name="prix_normal" type="number" step="0.01" min="0" value="<?php echo e((string)$produit['prix_normal']); ?>" class="w-24 rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white">
                  </td>
                  <td class="py-4 pr-4">
                      <input name="prix_happy_hour" type="number" step="0.01" min="0" value="<?php echo $produit['prix_happy_hour'] !== null ? e((string)$produit['prix_happy_hour']) : ''; ?>" class="w-24 rounded-lg bg-[#121212] border border-white/10 px-3 py-2 text-white">
                  </td>
                  <td class="py-4 pr-4">
                      <button class="rounded-lg border border-amber-400/30 px-3 py-2 text-amber-300 hover:bg-amber-400 hover:text-black">Sauver</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($produits)): ?>
                <tr><td colspan="6" class="py-6 text-gray-400">Aucun produit en base.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script>
    document.querySelectorAll('a[href*="tab="]').forEach(link => {
      link.addEventListener('click', () => {
        try { localStorage.setItem('gentleman-admin-tab', new URL(link.href).searchParams.get('tab') || 'matchs'); } catch (e) {}
      });
    });

    async function triggerForceSync() {
      const btn = document.getElementById('btn-force-sync');
      const spinner = document.getElementById('sync-spinner');
      const consoleContainer = document.getElementById('sync-console-container');
      const consoleBox = document.getElementById('sync-console');
      const lastSyncSpan = document.getElementById('last-sync-time');

      btn.disabled = true;
      btn.classList.add('opacity-80', 'cursor-not-allowed');
      spinner.classList.remove('hidden');
      consoleContainer.classList.remove('hidden');
      consoleBox.innerHTML = ''; // Clear previous logs

      const log = (msg, isError = false) => {
        const time = new Date().toLocaleTimeString('fr-FR');
        const p = document.createElement('div');
        p.className = isError ? 'text-red-400 font-semibold' : 'text-emerald-400';
        p.innerHTML = `<span class="text-gray-500">[${time}]</span> ${msg}`;
        consoleBox.appendChild(p);
        consoleBox.scrollTop = consoleBox.scrollHeight;
      };

      log("Connexion aux serveurs de calendriers iCal...");
      log("Téléchargement des calendriers (Ligue 1, Champions League, Top 14...)...");

      try {
        const response = await fetch('/api/auto-import.php?force=1');
        const data = await response.json();
        
        if (data && data.success) {
          log("Lecture et décodage des flux terminés !");
          log(data.message);
          
          // Mise à jour de la date affichée
          const now = new Date();
          const pad = (n) => n.toString().padStart(2, '0');
          lastSyncSpan.textContent = `${pad(now.getDate())}/${pad(now.getMonth()+1)}/${now.getFullYear()} à ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
          
          log("Mise à jour de la programmation en cours... Rechargement dans 1.5s...");
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          log(data && data.message ? data.message : "Erreur inconnue lors de l'importation.", true);
          btn.disabled = false;
          btn.classList.remove('opacity-80', 'cursor-not-allowed');
          spinner.classList.add('hidden');
        }
      } catch (err) {
        log("Erreur réseau ou script injoignable : " + err.message, true);
        console.error(err);
        btn.disabled = false;
        btn.classList.remove('opacity-80', 'cursor-not-allowed');
        spinner.classList.add('hidden');
      }
    }

    function replaceWithEmoji(img, teamName, fallbackText) {
      const flags = {
        'france': '🇫🇷',
        'suisse': '🇨🇭', 'switzerland': '🇨🇭',
        'qatar': '🇶🇦',
        'allemagne': '🇩🇪', 'germany': '🇩🇪',
        'espagne': '🇪🇸', 'spain': '🇪🇸',
        'italie': '🇮🇹', 'italy': '🇮🇹',
        'belgique': '🇧🇪', 'belgium': '🇧🇪',
        'portugal': '🇵🇹',
        'croatie': '🇭🇷', 'croatia': '🇭🇷',
        'argentine': '🇦🇷', 'argentina': '🇦🇷',
        'bresil': '🇧🇷', 'brazil': '🇧🇷',
        'pays-bas': '🇳🇱', 'netherlands': '🇳🇱',
        'maroc': '🇲🇦', 'morocco': '🇲🇦',
        'senegal': '🇸🇳',
        'japon': '🇯🇵', 'japan': '🇯🇵',
        'etats-unis': '🇺🇸', 'usa': '🇺🇸',
        'mexique': '🇲🇽', 'mexico': '🇲🇽',
        'ethiopie': '🇪🇹', 'ethiopia': '🇪🇹',
        'canada': '🇨🇦',
        'bosnie': '🇧🇦', 'bosnia': '🇧🇦',
        'coree': '🇰🇷', 'korea': '🇰🇷',
        'republique tcheque': '🇨🇿', 'czech': '🇨🇿',
        'uruguay': '🇺🇾',
        'cameroun': '🇨🇲', 'cameroon': '🇨🇲',
        'algerie': '🇩🇿', 'algeria': '🇩🇿',
        'tunisie': '🇹🇳', 'tunisia': '🇹🇳',
        'pays de galles': '🏴󠁧󠁢󠁷󠁬󠁳󠁿', 'wales': '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
        'ecosse': '🏴󠁧󠁢󠁳󠁣󠁴󠁿', 'scotland': '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
        'irlande': '🇮🇪', 'ireland': '🇮🇪',
        'angleterre': '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'england': '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
        'nouvelle-zelande': '🇳🇿', 'new zealand': '🇳🇿',
        'afrique du sud': '🇿🇦', 'south africa': '🇿🇦',
        'australie': '🇦🇺', 'australia': '🇦🇺',
        'fidji': '🇫🇯', 'fiji': '🇫🇯',
        'georgie': '🇬🇪', 'georgia': '🇬🇪',
        'tonga': '🇹🇴', 'samoa': '🇼🇸',
        'roumanie': '🇷🇴', 'romania': '🇷🇴',
        'namibie': '🇳🇦', 'namibia': '🇳🇦'
      };
      const nameLower = teamName.toLowerCase().trim();
      let emoji = '';
      for (const [key, value] of Object.entries(flags)) {
        if (nameLower.includes(key)) {
          emoji = value;
          break;
        }
      }
      const container = document.createElement('div');
      container.className = img.className + ' flex items-center justify-center select-none';
      if (emoji) {
        container.textContent = emoji;
        container.style.fontSize = '1.25em';
      } else {
        container.textContent = fallbackText;
        if (!img.className.includes('h-20') && !img.className.includes('h-28')) {
          container.classList.add('rounded-full', 'bg-white/5', 'border', 'border-white/10', 'font-semibold', 'text-[10px]', 'text-gray-500');
        } else {
          container.classList.add('rounded-2xl', 'bg-white/5', 'border', 'border-white/10', 'font-bold', 'text-2xl', 'text-gray-500');
        }
      }
      img.parentNode.replaceChild(container, img);
    }
  </script>
</body>
</html>
