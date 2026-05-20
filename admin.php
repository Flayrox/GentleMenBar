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

function slugify(string $value): string
{
    $value = trim($value);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($transliterated !== false) {
        $value = $transliterated;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'match';
}

  function apply_slug_synonyms(string $value): string
  {
    $normalized = trim($value);
    $synonyms = [
      '/\b(paris\s*saint[\s-]*germain|paris\s+sg|paris)\b/i' => 'psg',
      '/\b(olympique\s+de\s+marseille|marseille)\b/i' => 'om',
    ];

    foreach ($synonyms as $pattern => $replacement) {
      $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
    }

    return $normalized;
  }

  function sanitize_custom_slug(string $slug): string
  {
    return slugify($slug);
  }

  function generate_unique_match_slug(PDO $pdo, string $equipe1, string $equipe2, string $dateMatch, string $customSlug = '', int $ignoreId = 0): string
{
    if ($customSlug !== '') {
      $candidate = sanitize_custom_slug($customSlug);
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM matchs WHERE slug = :slug' . ($ignoreId > 0 ? ' AND id != :ignore_id' : ''));
      $params = [':slug' => $candidate];
      if ($ignoreId > 0) {
        $params[':ignore_id'] = $ignoreId;
      }
      $stmt->execute($params);
      if ((int)$stmt->fetchColumn() === 0) {
        return $candidate;
      }
    }

    $year = (new DateTimeImmutable($dateMatch, new DateTimeZone('Europe/Paris')))->format('Y');
    $team1 = apply_slug_synonyms($equipe1);
    $team2 = apply_slug_synonyms($equipe2);
    $base = slugify($team1 . ' ' . $team2 . ' ' . $year);
    $slug = $base;
    $index = 2;

    while (true) {
      $sql = 'SELECT COUNT(*) FROM matchs WHERE slug = :slug';
      if ($ignoreId > 0) {
        $sql .= ' AND id != :ignore_id';
      }
      $stmt = $pdo->prepare($sql);
      $params = [':slug' => $slug];
      if ($ignoreId > 0) {
        $params[':ignore_id'] = $ignoreId;
      }
      $stmt->execute($params);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $index;
        $index++;
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
      if ($action === 'save_match') {
        $matchId = (int)($_POST['match_id'] ?? 0);
            $equipe1 = trim((string)($_POST['equipe_1'] ?? ''));
            $equipe2 = trim((string)($_POST['equipe_2'] ?? ''));
            $competition = trim((string)($_POST['competition'] ?? ''));
            $dateInput = trim((string)($_POST['date_match'] ?? ''));
        $customSlug = trim((string)($_POST['slug_seo'] ?? ''));
        $score1 = $_POST['score_equipe_1'] !== '' ? (int)($_POST['score_equipe_1'] ?? 0) : null;
        $score2 = $_POST['score_equipe_2'] !== '' ? (int)($_POST['score_equipe_2'] ?? 0) : null;
        $minute = $_POST['minute_actuelle'] !== '' ? (int)($_POST['minute_actuelle'] ?? 0) : null;
        $statut = trim((string)($_POST['statut'] ?? 'scheduled'));
            $dateMatch = normalize_datetime_input($dateInput);

            if ($equipe1 === '' || $equipe2 === '' || !$dateMatch) {
                throw new RuntimeException('Veuillez remplir les champs obligatoires avec une date valide.');
            }

        $slug = generate_unique_match_slug($pdo, $equipe1, $equipe2, $dateMatch, $customSlug, $matchId);
        if (!empty($_FILES['match_image']['name'])) {
          $imagePath = upload_image_to_jpeg($_FILES['match_image'], $slug);
        } else {
          $imagePath = $matchId > 0 ? (string)($editMatch['image_path'] ?? '') : null;
        }

        if ($matchId > 0) {
          $stmt = $pdo->prepare('UPDATE matchs SET slug = :slug, equipe_1 = :equipe_1, equipe_2 = :equipe_2, competition = :competition, date_match = :date_match, image_path = :image_path, score_equipe_1 = :score_equipe_1, score_equipe_2 = :score_equipe_2, minute_actuelle = :minute_actuelle, statut = :statut WHERE id = :id');
          $stmt->execute([
            ':slug' => $slug,
            ':equipe_1' => $equipe1,
            ':equipe_2' => $equipe2,
            ':competition' => $competition,
            ':date_match' => $dateMatch,
            ':image_path' => $imagePath,
            ':score_equipe_1' => $score1,
            ':score_equipe_2' => $score2,
            ':minute_actuelle' => $minute,
            ':statut' => $statut,
            ':id' => $matchId,
          ]);
          set_flash('success', 'Match mis à jour avec le slug ' . $slug . '.');
        } else {
          $stmt = $pdo->prepare('INSERT INTO matchs (slug, equipe_1, equipe_2, competition, date_match, image_path, score_equipe_1, score_equipe_2, minute_actuelle, statut, is_active) VALUES (:slug, :equipe_1, :equipe_2, :competition, :date_match, :image_path, :score_equipe_1, :score_equipe_2, :minute_actuelle, :statut, 1)');
          $stmt->execute([
            ':slug' => $slug,
            ':equipe_1' => $equipe1,
            ':equipe_2' => $equipe2,
            ':competition' => $competition,
            ':date_match' => $dateMatch,
            ':image_path' => $imagePath,
            ':score_equipe_1' => $score1,
            ':score_equipe_2' => $score2,
            ':minute_actuelle' => $minute,
            ':statut' => $statut,
          ]);
          set_flash('success', 'Match ajouté avec le slug ' . $slug . '.');
        }
            redirect_admin('matchs');
        }

      if ($action === 'save_site_config') {
        upsert_site_config($pdo, [
          'hero_title' => trim((string)($_POST['hero_title'] ?? '')),
          'hero_subtitle' => trim((string)($_POST['hero_subtitle'] ?? '')),
          'bar_adresse' => trim((string)($_POST['bar_adresse'] ?? '')),
          'bar_telephone' => trim((string)($_POST['bar_telephone'] ?? '')),
          'insta_link' => trim((string)($_POST['insta_link'] ?? '')),
          'facebook_link' => trim((string)($_POST['facebook_link'] ?? '')),
          'horaires_semaine' => trim((string)($_POST['horaires_semaine'] ?? '')),
          'horaires_weekend' => trim((string)($_POST['horaires_weekend'] ?? '')),
          'horaires_dimanche' => trim((string)($_POST['horaires_dimanche'] ?? '')),
        ]);

        $flashConfig = [
          'hero_title' => trim((string)($_POST['hero_title'] ?? '')),
          'hero_subtitle' => trim((string)($_POST['hero_subtitle'] ?? '')),
          'bar_adresse' => trim((string)($_POST['bar_adresse'] ?? '')),
          'bar_telephone' => trim((string)($_POST['bar_telephone'] ?? '')),
          'insta_link' => trim((string)($_POST['insta_link'] ?? '')),
          'facebook_link' => trim((string)($_POST['facebook_link'] ?? '')),
          'horaires_semaine' => trim((string)($_POST['horaires_semaine'] ?? '')),
          'horaires_weekend' => trim((string)($_POST['horaires_weekend'] ?? '')),
          'horaires_dimanche' => trim((string)($_POST['horaires_dimanche'] ?? '')),
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
      <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
          <h2 class="text-2xl font-display text-amber-300"><?php echo $editMatch ? 'Éditer un match' : 'Ajouter un match'; ?></h2>
          <form method="post" enctype="multipart/form-data" class="mt-6 grid gap-4">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="save_match">
            <?php if ($editMatch): ?>
              <input type="hidden" name="match_id" value="<?php echo (int)$editMatch['id']; ?>">
            <?php endif; ?>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-2 block text-sm text-gray-300">Équipe 1</label>
                <input name="equipe_1" required value="<?php echo e(get_match_edit_value($editMatch, 'equipe_1')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
              </div>
              <div>
                <label class="mb-2 block text-sm text-gray-300">Équipe 2</label>
                <input name="equipe_2" required value="<?php echo e(get_match_edit_value($editMatch, 'equipe_2')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
              </div>
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Compétition</label>
              <input name="competition" value="<?php echo e(get_match_edit_value($editMatch, 'competition')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Slug SEO personnalisé</label>
              <input name="slug_seo" placeholder="ex: psg-om-2026" value="<?php echo e(get_match_edit_value($editMatch, 'slug')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
              <p class="mt-2 text-xs text-gray-500">Optionnel. Sinon, le slug est généré automatiquement avec les synonymes PSG / OM.</p>
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Date / Heure</label>
              <input type="datetime-local" name="date_match" required value="<?php echo e(mysql_to_datetime_local(get_match_edit_value($editMatch, 'date_match'))); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Affiche du match / logo compétition</label>
              <input type="file" name="match_image" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
              <p class="mt-2 text-xs text-gray-500">Format accepté: JPEG, PNG, WEBP. L'image est convertie en JPG et renommée avec le slug.</p>
            </div>
            <?php if ($editMatch && !empty($editMatch['image_path'])): ?>
              <div class="overflow-hidden rounded-xl border border-white/10">
                <img src="<?php echo e($editMatch['image_path']); ?>" alt="Image du match" class="h-48 w-full object-cover">
              </div>
            <?php endif; ?>
            
            <hr class="border-white/10">
            
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label class="mb-2 block text-sm text-gray-300">Score Équipe 1</label>
                <input type="number" name="score_equipe_1" min="0" max="999" value="<?php echo e(get_match_edit_value($editMatch, 'score_equipe_1')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400" placeholder="ex: 2">
              </div>
              <div>
                <label class="mb-2 block text-sm text-gray-300">Score Équipe 2</label>
                <input type="number" name="score_equipe_2" min="0" max="999" value="<?php echo e(get_match_edit_value($editMatch, 'score_equipe_2')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400" placeholder="ex: 1">
              </div>
              <div>
                <label class="mb-2 block text-sm text-gray-300">Minute (si LIVE)</label>
                <input type="number" name="minute_actuelle" min="0" max="120" value="<?php echo e(get_match_edit_value($editMatch, 'minute_actuelle')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400" placeholder="ex: 45">
              </div>
            </div>
            
            <div>
              <label class="mb-2 block text-sm text-gray-300">Statut du match</label>
              <select name="statut" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
                <option value="scheduled" <?php echo get_match_edit_value($editMatch, 'statut') === 'scheduled' || !$editMatch ? 'selected' : ''; ?>>À venir (UPCOMING)</option>
                <option value="live" <?php echo get_match_edit_value($editMatch, 'statut') === 'live' ? 'selected' : ''; ?>>En direct (LIVE)</option>
                <option value="finished" <?php echo get_match_edit_value($editMatch, 'statut') === 'finished' ? 'selected' : ''; ?>>Terminé (FINISHED)</option>
              </select>
              <p class="mt-2 text-xs text-gray-500">Le statut s'affichera sur la page d'accueil. LIVE affichera la minute actuelle.</p>
            </div>
            
            <button type="submit" class="rounded-lg bg-amber-400 px-4 py-3 font-semibold text-black hover:bg-amber-300"><?php echo $editMatch ? 'Mettre à jour le match' : 'Ajouter le match'; ?></button>
          </form>
        </div>

        <div class="rounded-2xl border border-white/10 bg-[#1A1A1A] p-6 shadow-lg">
          <h2 class="text-2xl font-display text-amber-300">Matchs existants</h2>
          <div class="mt-6 space-y-4">
            <?php foreach ($matchs as $match): ?>
              <div class="rounded-xl border border-white/10 bg-[#121212] p-4">
                <div class="flex gap-4">
                  <div class="h-20 w-32 overflow-hidden rounded-lg border border-white/10 bg-black/30 flex-shrink-0">
                    <img src="<?php echo e(!empty($match['image_path']) ? $match['image_path'] : '/assets/uploads/default-match.svg'); ?>" alt="<?php echo e($match['equipe_1'] . ' contre ' . $match['equipe_2']); ?>" class="h-full w-full object-cover">
                  </div>
                  <div class="flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div class="text-lg font-semibold text-white"><?php echo e($match['equipe_1']); ?> <span class="text-gray-500">vs</span> <?php echo e($match['equipe_2']); ?></div>
                        <div class="mt-1 text-sm text-gray-400"><?php echo e((string)($match['competition'] ?: 'Compétition non renseignée')); ?> · <?php echo e((new DateTimeImmutable($match['date_match']))->format('d/m/Y H:i')); ?></div>
                        <div class="mt-2 text-xs text-gray-500">Slug: <?php echo e($match['slug']); ?> · Statut: <?php echo (int)$match['is_active'] === 1 ? '<span class="text-emerald-300">Actif</span>' : '<span class="text-red-300">Désactivé</span>'; ?></div>
                      </div>
                      <div class="flex flex-wrap gap-2">
                        <a href="/le-comptoir?tab=matchs&edit_match=<?php echo (int)$match['id']; ?>" class="rounded-lg border border-amber-400/30 px-3 py-2 text-sm text-amber-300 hover:bg-amber-400 hover:text-black">Modifier</a>
                        <?php if ((int)$match['is_active'] === 1): ?>
                          <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="disable_match">
                            <input type="hidden" name="id" value="<?php echo (int)$match['id']; ?>">
                            <button class="rounded-lg border border-amber-400/30 px-3 py-2 text-sm text-amber-300 hover:bg-amber-400 hover:text-black">Désactiver</button>
                          </form>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Supprimer définitivement ce match ?');">
                          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                          <input type="hidden" name="action" value="delete_match">
                          <input type="hidden" name="id" value="<?php echo (int)$match['id']; ?>">
                          <button class="rounded-lg border border-red-500/40 px-3 py-2 text-sm text-red-300 hover:bg-red-500 hover:text-white">Supprimer</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($matchs)): ?>
              <p class="text-sm text-gray-400">Aucun match enregistré.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php elseif ($tab === 'design'): ?>
      <section class="grid gap-6 lg:grid-cols-2">
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
          <h3 class="text-xl font-display text-amber-300">Aperçu actuel</h3>
          <div class="mt-4 overflow-hidden rounded-xl border border-white/10">
            <img src="<?php echo e($heroBgImage); ?>" alt="Fond actuel du Hero" class="h-72 w-full object-cover">
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
              <label class="mb-2 block text-sm text-gray-300">Titre Hero</label>
              <input name="hero_title" value="<?php echo e(config_value('hero_title')); ?>" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="mb-2 block text-sm text-gray-300">Sous-titre Hero</label>
              <textarea name="hero_subtitle" rows="3" class="w-full rounded-lg bg-[#121212] border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400"><?php echo e(config_value('hero_subtitle')); ?></textarea>
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
          <div class="rounded-lg bg-[#121212] p-4 text-sm text-gray-300">
            <p><span class="text-amber-300">Hero:</span> <?php echo e(config_value('hero_title')); ?></p>
            <p class="mt-2"><span class="text-amber-300">Sous-titre:</span> <?php echo e(config_value('hero_subtitle')); ?></p>
            <p class="mt-2"><span class="text-amber-300">Adresse:</span> <?php echo e(config_value('bar_adresse')); ?></p>
            <p class="mt-2"><span class="text-amber-300">Téléphone:</span> <?php echo e(config_value('bar_telephone')); ?></p>
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
  </script>
</body>
</html>
