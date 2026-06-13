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
      if ($action === 'import_match_api') {
        $equipe1 = trim((string)($_POST['equipe_1'] ?? ''));
        $equipe2 = trim((string)($_POST['equipe_2'] ?? ''));
        $competition = trim((string)($_POST['competition'] ?? ''));
        $dateMatch = trim((string)($_POST['date_match'] ?? ''));
        $imagePathHome = trim((string)($_POST['image_path_home'] ?? ''));
        $imagePathAway = trim((string)($_POST['image_path_away'] ?? ''));
        $sport = trim((string)($_POST['sport'] ?? 'Soccer'));
        $apiEventId = trim((string)($_POST['api_event_id'] ?? ''));
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

        if ($equipe1 === '' || $equipe2 === '' || $dateMatch === '') {
            throw new RuntimeException('Données de match incomplètes.');
        }

        if ($isFeatured === 1) {
            $pdo->exec('UPDATE `matchs` SET `is_featured` = 0');
        }

        $slug = generate_unique_match_slug($pdo, $equipe1, $equipe2, $dateMatch);

        $stmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, image_path, image_path_away, sport, api_event_id, statut, is_active, is_featured) 
            VALUES (:slug, :equipe_1, :equipe_2, :competition, :date_match, :image_path_home, :image_path_away, :sport, :api_event_id, \'scheduled\', 1, :is_featured) 
            ON DUPLICATE KEY UPDATE is_active = 1, is_featured = VALUES(is_featured), date_match = VALUES(date_match), image_path = VALUES(image_path), image_path_away = VALUES(image_path_away)');
        $stmt->execute([
            ':slug' => $slug,
            ':equipe_1' => $equipe1,
            ':equipe_2' => $equipe2,
            ':competition' => $competition,
            ':date_match' => $dateMatch,
            ':image_path_home' => $imagePathHome !== '' ? $imagePathHome : null,
            ':image_path_away' => $imagePathAway !== '' ? $imagePathAway : null,
            ':sport' => $sport,
            ':api_event_id' => $apiEventId !== '' ? $apiEventId : null,
            ':is_featured' => $isFeatured,
        ]);

        set_flash('success', 'Match "' . $equipe1 . ' vs ' . $equipe2 . '" importé avec succès !');
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

      if ($action === 'bulk_sync_matchs') {
        $fixturesJson = $_POST['fixtures_json'] ?? '[]';
        $fixtures = json_decode($fixturesJson, true) ?: [];

        if (empty($fixtures)) {
            throw new RuntimeException('Aucun match sélectionné pour la synchronisation.');
        }

        $importedCount = 0;
        foreach ($fixtures as $fix) {
            $equipe1 = trim((string)($fix['equipe_1'] ?? ''));
            $equipe2 = trim((string)($fix['equipe_2'] ?? ''));
            $competition = trim((string)($fix['competition'] ?? ''));
            $dateMatch = trim((string)($fix['date_match'] ?? ''));
            $imagePathHome = trim((string)($fix['image_path_home'] ?? ''));
            $imagePathAway = trim((string)($fix['image_path_away'] ?? ''));
            $sport = trim((string)($fix['sport'] ?? 'Soccer'));
            $apiEventId = trim((string)($fix['api_event_id'] ?? ''));

            if ($equipe1 === '' || $equipe2 === '' || $dateMatch === '') {
                continue;
            }

            $exists = false;
            if ($apiEventId !== '') {
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM matchs WHERE api_event_id = :api_event_id');
                $checkStmt->execute([':api_event_id' => $apiEventId]);
                if ((int)$checkStmt->fetchColumn() > 0) {
                    $exists = true;
                }
            }

            if (!$exists) {
                $dayStart = date('Y-m-d 00:00:00', strtotime($dateMatch));
                $dayEnd = date('Y-m-d 23:59:59', strtotime($dateMatch));
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM matchs WHERE equipe_1 = :e1 AND equipe_2 = :e2 AND date_match BETWEEN :dstart AND :dend');
                $checkStmt->execute([
                    ':e1' => $equipe1,
                    ':e2' => $equipe2,
                    ':dstart' => $dayStart,
                    ':dend' => $dayEnd,
                ]);
                if ((int)$checkStmt->fetchColumn() > 0) {
                    $exists = true;
                }
            }

            if ($exists) {
                if ($apiEventId !== '') {
                    $updateStmt = $pdo->prepare('UPDATE matchs SET date_match = :date_match, image_path = :image_path_home, image_path_away = :image_path_away, competition = :competition, sport = :sport WHERE api_event_id = :api_event_id');
                    $updateStmt->execute([
                        ':date_match' => $dateMatch,
                        ':image_path_home' => $imagePathHome !== '' ? $imagePathHome : null,
                        ':image_path_away' => $imagePathAway !== '' ? $imagePathAway : null,
                        ':competition' => $competition,
                        ':sport' => $sport,
                        ':api_event_id' => $apiEventId,
                    ]);
                }
                continue;
            }

            $slug = generate_unique_match_slug($pdo, $equipe1, $equipe2, $dateMatch);

            $insertStmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, image_path, image_path_away, sport, api_event_id, statut, is_active) VALUES (:slug, :equipe_1, :equipe_2, :competition, :date_match, :image_path_home, :image_path_away, :sport, :api_event_id, \'scheduled\', 1)');
            $insertStmt->execute([
                ':slug' => $slug,
                ':equipe_1' => $equipe1,
                ':equipe_2' => $equipe2,
                ':competition' => $competition,
                ':date_match' => $dateMatch,
                ':image_path_home' => $imagePathHome !== '' ? $imagePathHome : null,
                ':image_path_away' => $imagePathAway !== '' ? $imagePathAway : null,
                ':sport' => $sport,
                ':api_event_id' => $apiEventId !== '' ? $apiEventId : null,
            ]);
            $importedCount++;
        }

        set_flash('success', $importedCount . ' match(s) synchronisé(s) et ajouté(s) à la programmation !');
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

$matchs = $pdo->query('SELECT * FROM matchs ORDER BY date_match DESC')->fetchAll();
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
      <!-- API Import Section -->
      <div class="mb-8 rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
        <div class="border-b border-white/10 pb-4 flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="text-2xl font-display text-amber-300 flex items-center gap-2">Smart Fixtures Feed ⭐</h2>
            <p class="text-xs text-gray-400 mt-1">Les prochains grands matchs de foot (Ligue 1, Ligue des Champions, Coupe du Monde) et rugby (Top 14, 6 Nations) sont pré-sélectionnés. Modifiez et validez.</p>
          </div>
          <div>
            <form id="bulk-sync-form" method="post" onsubmit="submitBulkSync(event)">
              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="action" value="bulk_sync_matchs">
              <input type="hidden" name="fixtures_json" id="fixtures-json-input" value="[]">
              <button type="submit" id="bulk-sync-btn" disabled class="rounded-lg bg-gray-600 px-5 py-3 font-semibold text-gray-400 text-sm transition-all cursor-not-allowed">
                Synchroniser la sélection (<span id="selected-count">0</span> match(s))
              </button>
            </form>
          </div>
        </div>

        <!-- Feed Loader & Table -->
        <div id="smart-feed-status" class="py-8 text-center text-sm text-gray-400 bg-black/20 rounded-xl mt-4">
          <span class="inline-block animate-pulse">Chargement intelligent des flux de match (Ligue 1, Top 14, Champions League...)</span>
        </div>

        <div id="smart-feed-container" class="mt-6 hidden space-y-8">
          <!-- Football Table -->
          <div class="space-y-3">
            <h3 class="text-lg font-display text-amber-300 flex items-center gap-2">⚽ Football</h3>
            <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#121212]">
              <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-400 uppercase bg-white/5 border-b border-white/10">
                  <tr>
                    <th class="px-4 py-3 w-12 text-center">Diffuser</th>
                    <th class="px-4 py-3">Match</th>
                    <th class="px-4 py-3">Compétition / Date</th>
                    <th class="px-4 py-3 text-center">Pré-sélection</th>
                  </tr>
                </thead>
                <tbody id="smart-feed-body-soccer" class="divide-y divide-white/5">
                </tbody>
              </table>
            </div>
          </div>

          <!-- Rugby Table -->
          <div class="space-y-3">
            <h3 class="text-lg font-display text-amber-300 flex items-center gap-2">🏉 Rugby</h3>
            <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#121212]">
              <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-400 uppercase bg-white/5 border-b border-white/10">
                  <tr>
                    <th class="px-4 py-3 w-12 text-center">Diffuser</th>
                    <th class="px-4 py-3">Match</th>
                    <th class="px-4 py-3">Compétition / Date</th>
                    <th class="px-4 py-3 text-center">Pré-sélection</th>
                  </tr>
                </thead>
                <tbody id="smart-feed-body-rugby" class="divide-y divide-white/5">
                </tbody>
              </table>
            </div>
          </div>

          <!-- Other Sports Table -->
          <div class="space-y-3">
            <h3 class="text-lg font-display text-amber-300 flex items-center gap-2">🏆 Autres Sports</h3>
            <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#121212]">
              <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-400 uppercase bg-white/5 border-b border-white/10">
                  <tr>
                    <th class="px-4 py-3 w-12 text-center">Diffuser</th>
                    <th class="px-4 py-3">Match</th>
                    <th class="px-4 py-3">Compétition / Date</th>
                    <th class="px-4 py-3 text-center">Pré-sélection</th>
                  </tr>
                </thead>
                <tbody id="smart-feed-body-others" class="divide-y divide-white/5">
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Toggle for manual search -->
        <div class="mt-6 border-t border-white/10 pt-4">
          <button type="button" onclick="document.getElementById('manual-search-section').classList.toggle('hidden')" class="text-xs text-amber-300 hover:underline flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">search</span>
            Besoin de rechercher une autre équipe spécifique ? (PSG, Marseille, Lyon, etc.)
          </button>
          
          <div id="manual-search-section" class="hidden mt-4 pt-4 border-t border-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4">
              <div class="flex-1 min-w-[250px] flex gap-3">
                <input type="text" id="api-search-input" placeholder="Rechercher une équipe spécifique (ex: Monaco, Arsenal, Toulouse...)" class="flex-1 rounded-lg bg-[#121212] border border-white/10 px-4 py-2 text-white outline-none focus:border-amber-400 text-xs">
                <button type="button" onclick="searchTeam()" class="rounded-lg bg-white/10 border border-white/20 px-4 py-2 font-semibold text-white hover:bg-white/20 text-xs transition-colors">Rechercher</button>
              </div>
              <div class="flex flex-wrap gap-1">
                <button type="button" onclick="quickSearch('133714')" class="rounded-full bg-white/5 border border-white/10 px-2 py-1 text-[10px] hover:bg-amber-400 hover:text-black transition-colors">PSG</button>
                <button type="button" onclick="quickSearch('133707')" class="rounded-full bg-white/5 border border-white/10 px-2 py-1 text-[10px] hover:bg-amber-400 hover:text-black transition-colors">OM</button>
                <button type="button" onclick="quickSearch('134989')" class="rounded-full bg-white/5 border border-white/10 px-2 py-1 text-[10px] hover:bg-amber-400 hover:text-black transition-colors">France</button>
                <button type="button" onclick="quickSearch('133739')" class="rounded-full bg-white/5 border border-white/10 px-2 py-1 text-[10px] hover:bg-amber-400 hover:text-black transition-colors">Real</button>
                <button type="button" onclick="quickSearch('133738')" class="rounded-full bg-white/5 border border-white/10 px-2 py-1 text-[10px] hover:bg-amber-400 hover:text-black transition-colors">Barça</button>
              </div>
            </div>

            <!-- Team Results -->
            <div id="team-results" class="mt-4 hidden grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3"></div>

            <!-- Loading / No events indicator -->
            <div id="api-status" class="mt-6 text-sm text-gray-400 hidden text-center py-4 bg-black/20 rounded-xl"></div>

            <!-- Matches Results Table -->
            <div id="matches-results-container" class="mt-6 hidden">
              <h3 class="text-xs font-semibold text-gray-300 uppercase tracking-wider mb-3">Résultats de recherche</h3>
              <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#121212]">
                <table class="w-full text-left text-sm text-gray-300">
                  <thead class="text-xs text-gray-400 uppercase bg-white/5 border-b border-white/10">
                    <tr>
                      <th class="px-4 py-2">Match</th>
                      <th class="px-4 py-2">Compétition / Date</th>
                      <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="matches-results-body" class="divide-y divide-white/5">
                  </tbody>
                </table>
              </div>
            </div>
          </div>
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
                  $homeLogo = !empty($match['image_path']) ? $match['image_path'] : null;
                  $awayLogo = !empty($match['image_path_away']) ? $match['image_path_away'] : null;
                ?>
                  <tr class="align-middle hover:bg-white/5 transition-colors">
                    <td class="px-4 py-4">
                      <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                          <?php if ($homeLogo): ?>
                            <img src="<?php echo e($homeLogo); ?>" alt="" class="h-8 w-8 object-contain">
                          <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[10px] text-gray-500 font-semibold">H</div>
                          <?php endif; ?>
                          <span class="text-gray-500 text-xs">vs</span>
                          <?php if ($awayLogo): ?>
                            <img src="<?php echo e($awayLogo); ?>" alt="" class="h-8 w-8 object-contain">
                          <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[10px] text-gray-500 font-semibold">A</div>
                          <?php endif; ?>
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

    const SPORTSDB_API_KEY = "<?php echo e(config_value('sportsdb_api_key', '3')); ?>";
    let allFixtures = [];

    // Ligue IDs to sync (Foot & Rugby, divisions majeures)
    const leaguesToSync = [
      { id: '4334', name: 'Ligue 1' },
      { id: '4480', name: 'Champions League' },
      { id: '4429', name: 'Coupe du Monde' },
      { id: '4562', name: 'Euro' },
      { id: '4328', name: 'Premier League' },
      { id: '4335', name: 'La Liga' },
      { id: '4332', name: 'Serie A' },
      { id: '4396', name: 'Ligue 2' },
      { id: '4484', name: 'Coupe de France' },
      { id: '4415', name: 'Top 14 Rugby' },
      { id: '4417', name: 'Six Nations Rugby' }
    ];

    async function loadSmartFeed() {
      const statusDiv = document.getElementById('smart-feed-status');
      const container = document.getElementById('smart-feed-container');
      const bodySoccer = document.getElementById('smart-feed-body-soccer');
      const bodyRugby = document.getElementById('smart-feed-body-rugby');
      const bodyOthers = document.getElementById('smart-feed-body-others');
      
      statusDiv.classList.remove('hidden');
      statusDiv.innerHTML = '<span class="inline-block animate-pulse">Chargement intelligent des flux (Foot & Rugby)...</span>';
      container.classList.add('hidden');
      bodySoccer.innerHTML = '';
      bodyRugby.innerHTML = '';
      bodyOthers.innerHTML = '';
      allFixtures = [];
      
      try {
        const fetchPromises = leaguesToSync.map(async (league) => {
          try {
            const res = await fetch(`https://www.thesportsdb.com/api/v1/json/${SPORTSDB_API_KEY}/eventsnextleague.php?id=${league.id}`);
            const data = await res.json();
            if (data && data.events) {
              return data.events.map(ev => ({ ...ev, leagueName: league.name }));
            }
          } catch (e) {
            console.warn(`Could not load fixtures for league ${league.name}`, e);
          }
          return [];
        });
        
        const results = await Promise.all(fetchPromises);
        const mergedEvents = results.flat();
        
        if (mergedEvents.length === 0) {
          statusDiv.textContent = "Aucun match à venir trouvé dans les grands championnats.";
          return;
        }
        
        // Trier chronologiquement
        mergedEvents.sort((a, b) => new Date(a.dateEvent + 'T' + (a.strTime || '00:00:00')) - new Date(b.dateEvent + 'T' + (b.strTime || '00:00:00')));
        
        statusDiv.classList.add('hidden');
        container.classList.remove('hidden');
        
        let counts = { soccer: 0, rugby: 0, others: 0 };
        
        mergedEvents.forEach((event, index) => {
          const dateStr = event.dateEvent + 'T' + (event.strTime || '00:00:00');
          const dateObj = new Date(dateStr);
          
          // Filtrer les matchs prévus dans plus de 30 jours
          const maxDate = new Date();
          maxDate.setDate(maxDate.getDate() + 30);
          if (dateObj > maxDate) {
            return;
          }
          
          const formattedDate = dateObj.toLocaleDateString('fr-FR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
          });
          
          const pad = (n) => n.toString().padStart(2, '0');
          const sqlDate = `${dateObj.getFullYear()}-${pad(dateObj.getMonth()+1)}-${pad(dateObj.getDate())} ${pad(dateObj.getHours())}:${pad(dateObj.getMinutes())}:00`;
          
          const homeLogo = event.strHomeTeamBadge || '';
          const awayLogo = event.strAwayTeamBadge || '';
          const sportType = event.strSport || 'Soccer';
          const apiEventId = event.idEvent || '';
          
          // Règles de pré-sélection (Équipes françaises, PSG, Marseille, Toulouse Rugby, phases finales)
          const home = event.strHomeTeam.toLowerCase();
          const away = event.strAwayTeam.toLowerCase();
          const keywords = ['psg', 'paris sg', 'paris saint-germain', 'marseille', 'om', 'france', 'toulouse', 'monaco', 'lyon', 'real madrid', 'barcelona', 'toulon'];
          const matchesKeyword = keywords.some(kw => home.includes(kw) || away.includes(kw));
          const isFinalOrKey = event.strEvent.toLowerCase().includes('final') || event.strEvent.toLowerCase().includes('semi');
          const shouldAutoCheck = matchesKeyword || isFinalOrKey;
          
          allFixtures.push({
            index: index,
            equipe_1: event.strHomeTeam,
            equipe_2: event.strAwayTeam,
            competition: event.strLeague || event.leagueName,
            date_match: sqlDate,
            image_path_home: homeLogo,
            image_path_away: awayLogo,
            sport: sportType,
            api_event_id: apiEventId,
            checked: shouldAutoCheck
          });
          
          const tr = document.createElement('tr');
          tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors align-middle';
          tr.innerHTML = `
            <td class="px-4 py-4 text-center">
              <input type="checkbox" id="check-${index}" onchange="toggleFixture(${index})" ${shouldAutoCheck ? 'checked' : ''} class="w-5 h-5 rounded bg-[#121212] border-white/10 text-amber-400 focus:ring-amber-400 cursor-pointer">
            </td>
            <td class="px-4 py-4">
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 flex-shrink-0">
                  ${homeLogo ? `<img src="${homeLogo}" class="h-8 w-8 object-contain" />` : `<div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[10px] text-gray-500 font-semibold">H</div>`}
                  <span class="text-gray-500 text-[10px]">vs</span>
                  ${awayLogo ? `<img src="${awayLogo}" class="h-8 w-8 object-contain" />` : `<div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[10px] text-gray-500 font-semibold">A</div>`}
                </div>
                <div>
                  <span class="font-semibold text-white">${event.strHomeTeam}</span>
                  <span class="text-gray-500 mx-0.5">c.</span>
                  <span class="font-semibold text-white">${event.strAwayTeam}</span>
                </div>
              </div>
            </td>
            <td class="px-4 py-4">
              <div class="text-white text-sm font-semibold">${event.strLeague || event.leagueName}</div>
              <div class="text-gray-400 text-xs mt-0.5">${formattedDate}</div>
            </td>
            <td class="px-4 py-4 text-center">
              ${shouldAutoCheck ? '<span class="text-xs px-2.5 py-1 bg-amber-400/10 text-amber-300 rounded-full font-semibold border border-amber-400/20">🔥 Auto-Sélection</span>' : '<span class="text-xs text-gray-500">-</span>'}
            </td>
          `;
          
          if (sportType === 'Soccer') {
            bodySoccer.appendChild(tr);
            counts.soccer++;
          } else if (sportType === 'Rugby') {
            bodyRugby.appendChild(tr);
            counts.rugby++;
          } else {
            bodyOthers.appendChild(tr);
            counts.others++;
          }
        });
        
        toggleTableVisibility('smart-feed-body-soccer', counts.soccer);
        toggleTableVisibility('smart-feed-body-rugby', counts.rugby);
        toggleTableVisibility('smart-feed-body-others', counts.others);
        
        updateSelectedCount();
      } catch (err) {
        statusDiv.textContent = "Erreur de chargement du Smart Feed.";
        console.error(err);
      }
    }

    function toggleTableVisibility(bodyId, count) {
      const tableDiv = document.getElementById(bodyId).closest('.space-y-3');
      if (count === 0) {
        tableDiv.classList.add('hidden');
      } else {
        tableDiv.classList.remove('hidden');
      }
    }

    function toggleFixture(index) {
      if (allFixtures[index]) {
        allFixtures[index].checked = document.getElementById(`check-${index}`).checked;
      }
      updateSelectedCount();
    }

    function updateSelectedCount() {
      const count = allFixtures.filter(f => f.checked).length;
      document.getElementById('selected-count').textContent = count;
      const btn = document.getElementById('bulk-sync-btn');
      if (count > 0) {
        btn.disabled = false;
        btn.className = "rounded-lg bg-amber-400 px-5 py-3 font-semibold text-black hover:bg-amber-300 text-sm transition-all cursor-pointer shadow-[0_0_15px_rgba(212,175,55,0.2)] hover:shadow-[0_0_20px_rgba(212,175,55,0.4)]";
      } else {
        btn.disabled = true;
        btn.className = "rounded-lg bg-gray-600 px-5 py-3 font-semibold text-gray-400 text-sm transition-all cursor-not-allowed";
      }
    }

    function submitBulkSync(e) {
      const selected = allFixtures.filter(f => f.checked);
      document.getElementById('fixtures-json-input').value = JSON.stringify(selected);
    }

    // --- MANUALLY SEARCH ---
    async function quickSearch(teamId) {
      const statusDiv = document.getElementById('api-status');
      const container = document.getElementById('matches-results-container');
      const resultsBody = document.getElementById('matches-results-body');
      const teamResults = document.getElementById('team-results');
      
      teamResults.classList.add('hidden');
      statusDiv.classList.remove('hidden');
      statusDiv.textContent = "Chargement des matchs de l'équipe...";
      container.classList.add('hidden');
      
      try {
        const res = await fetch(`https://www.thesportsdb.com/api/v1/json/${SPORTSDB_API_KEY}/eventsnext.php?id=${teamId}`);
        const data = await res.json();
        
        if (!data || !data.events || data.events.length === 0) {
          statusDiv.textContent = "Aucun match à venir trouvé pour cette équipe.";
          return;
        }
        
        statusDiv.classList.add('hidden');
        container.classList.remove('hidden');
        resultsBody.innerHTML = '';
        
        data.events.forEach(event => {
          const dateStr = event.dateEvent + 'T' + (event.strTime || '00:00:00');
          const dateObj = new Date(dateStr);
          const formattedDate = dateObj.toLocaleDateString('fr-FR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
          });
          
          const pad = (n) => n.toString().padStart(2, '0');
          const sqlDate = `${dateObj.getFullYear()}-${pad(dateObj.getMonth()+1)}-${pad(dateObj.getDate())} ${pad(dateObj.getHours())}:${pad(dateObj.getMinutes())}:00`;
          const homeLogo = event.strHomeTeamBadge || '';
          const awayLogo = event.strAwayTeamBadge || '';
          const sportType = event.strSport || 'Soccer';
          const apiEventId = event.idEvent || '';
          
          const tr = document.createElement('tr');
          tr.className = 'border-b border-white/5 hover:bg-white/5 transition-colors align-middle';
          tr.innerHTML = `
            <td class="px-4 py-4">
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 flex-shrink-0">
                  ${homeLogo ? `<img src="${homeLogo}" class="h-8 w-8 object-contain" />` : `<div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[10px] text-gray-500 font-semibold">H</div>`}
                  <span class="text-gray-500 text-[10px]">vs</span>
                  ${awayLogo ? `<img src="${awayLogo}" class="h-8 w-8 object-contain" />` : `<div class="h-8 w-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-[10px] text-gray-500 font-semibold">A</div>`}
                </div>
                <div>
                  <span class="font-semibold text-white">${event.strHomeTeam}</span>
                  <span class="text-gray-500 mx-0.5">c.</span>
                  <span class="font-semibold text-white">${event.strAwayTeam}</span>
                </div>
              </div>
            </td>
            <td class="px-4 py-4">
              <div class="text-white text-sm font-semibold">${event.strLeague || 'Match Amical'}</div>
              <div class="text-gray-400 text-xs mt-0.5">${formattedDate}</div>
            </td>
            <td class="px-4 py-4 text-right">
              <div class="flex justify-end gap-2">
                <form method="post" style="display:inline-block">
                  <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="action" value="import_match_api">
                  <input type="hidden" name="equipe_1" value="${event.strHomeTeam}">
                  <input type="hidden" name="equipe_2" value="${event.strAwayTeam}">
                  <input type="hidden" name="competition" value="${event.strLeague || 'Football'}">
                  <input type="hidden" name="date_match" value="${sqlDate}">
                  <input type="hidden" name="image_path_home" value="${homeLogo}">
                  <input type="hidden" name="image_path_away" value="${awayLogo}">
                  <input type="hidden" name="sport" value="${sportType}">
                  <input type="hidden" name="api_event_id" value="${apiEventId}">
                  <button type="submit" class="rounded bg-white/5 border border-white/10 px-3 py-1.5 text-xs text-gray-200 hover:bg-amber-400 hover:text-black transition-colors font-medium">Importer & Diffuser</button>
                </form>
                <form method="post" style="display:inline-block">
                  <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="action" value="import_match_api">
                  <input type="hidden" name="equipe_1" value="${event.strHomeTeam}">
                  <input type="hidden" name="equipe_2" value="${event.strAwayTeam}">
                  <input type="hidden" name="competition" value="${event.strLeague || 'Football'}">
                  <input type="hidden" name="date_match" value="${sqlDate}">
                  <input type="hidden" name="image_path_home" value="${homeLogo}">
                  <input type="hidden" name="image_path_away" value="${awayLogo}">
                  <input type="hidden" name="sport" value="${sportType}">
                  <input type="hidden" name="api_event_id" value="${apiEventId}">
                  <input type="hidden" name="is_featured" value="1">
                  <button type="submit" class="rounded bg-amber-400 px-3 py-1.5 text-xs text-black hover:bg-amber-300 transition-colors font-semibold">★ Importer & Mettre à l'affiche</button>
                </form>
              </div>
            </td>
          `;
          resultsBody.appendChild(tr);
        });
      } catch (err) {
        statusDiv.textContent = "Erreur de connexion à l'API.";
        console.error(err);
      }
    }

    async function searchTeam() {
      const query = document.getElementById('api-search-input').value.trim();
      const statusDiv = document.getElementById('api-status');
      const teamResults = document.getElementById('team-results');
      const container = document.getElementById('matches-results-container');
      
      if (!query) return;
      
      statusDiv.classList.remove('hidden');
      statusDiv.textContent = "Recherche de l'équipe...";
      teamResults.classList.add('hidden');
      container.classList.add('hidden');
      
      try {
        const res = await fetch(`https://www.thesportsdb.com/api/v1/json/${SPORTSDB_API_KEY}/searchteams.php?t=${encodeURIComponent(query)}`);
        const data = await res.json();
        
        if (!data || !data.teams || data.teams.length === 0) {
          statusDiv.textContent = "Aucune équipe correspondante trouvée.";
          return;
        }
        
        statusDiv.classList.add('hidden');
        teamResults.classList.remove('hidden');
        teamResults.innerHTML = '';
        
        data.teams.slice(0, 10).forEach(team => {
          if (team.strSport !== 'Soccer' && team.strSport !== 'Rugby') return;
          
          const div = document.createElement('button');
          div.type = 'button';
          div.onclick = () => quickSearch(team.idTeam);
          div.className = 'flex flex-col items-center justify-center p-3 rounded-xl border border-white/10 bg-white/5 hover:border-amber-400/50 hover:bg-white/10 transition-all text-center';
          div.innerHTML = `
            ${team.strBadge ? `<img src="${team.strBadge}" class="h-12 w-12 object-contain mb-2" />` : ''}
            <span class="text-xs font-semibold text-white truncate w-full">${team.strTeam}</span>
            <span class="text-[9px] text-gray-500 mt-0.5">${team.strCountry}</span>
          `;
          teamResults.appendChild(div);
        });
        
        if (teamResults.children.length === 0) {
          statusDiv.classList.remove('hidden');
          statusDiv.textContent = "Aucune équipe trouvée.";
          teamResults.classList.add('hidden');
        }
      } catch (err) {
        statusDiv.textContent = "Erreur lors de la recherche.";
        console.error(err);
      }
    }

    // Trigger search on Enter key
    document.getElementById('api-search-input').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchTeam();
      }
    });

    // Auto-load smart feed on tab matches
    document.addEventListener("DOMContentLoaded", () => {
      const tab = new URLSearchParams(window.location.search).get('tab') || 'matchs';
      if (tab === 'matchs') {
        loadSmartFeed();
      }
    });
  </script>
</body>
</html>
