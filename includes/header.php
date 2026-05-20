<?php
$siteName = config_value('site_name', 'Le Gentleman Pub');
$siteTagline = config_value('site_tagline', 'Pub irlandais à Saint-Michel, Paris');
if (!isset($page_title)) {
  $page_title = $siteName;
}
if (!isset($meta_description)) {
  $meta_description = $siteTagline;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo e($page_title); ?></title>
    <meta name="description" content="<?php echo e($meta_description); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              pubgreen: '#0B2516',
              pubdark: '#121212',
              pubaccent: '#D4AF37',
              pubnav: '#1A1A1A'
            },
            fontFamily: {
              display: ['Playfair Display', 'serif'],
              body: ['Inter', 'system-ui', 'sans-serif']
            }
          }
        }
      }
    </script>
    <meta name="robots" content="index, follow">
</head>
<body class="bg-[--pub-bg] text-gray-100" style="background-color:#0B2516; font-family:Inter, sans-serif;">
<header class="bg-[--pubnav]" style="background-color:#1A1A1A;">
  <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
    <a href="/" class="flex items-center gap-3 text-white">
      <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-amber-600 flex items-center justify-center text-black font-bold">G</div>
      <div>
        <div class="text-lg font-display"><?php echo e($siteName); ?></div>
        <div class="text-sm text-gray-300"><?php echo e($siteTagline); ?></div>
      </div>
    </a>
    <nav class="hidden md:flex gap-6 items-center text-sm">
      <a href="#matchs" class="hover:text-amber-300"><?php echo e(config_value('nav_matchs_label', 'Matchs')); ?></a>
      <a href="#carte" class="hover:text-amber-300"><?php echo e(config_value('nav_carte_label', 'Carte')); ?></a>
      <a href="#contact" class="hover:text-amber-300"><?php echo e(config_value('nav_infos_label', 'Infos')); ?></a>
    </nav>
    <div class="md:hidden">
      <button id="nav-toggle" aria-label="Menu" class="text-gray-200">☰</button>
    </div>
  </div>
  <script>
    document.getElementById('nav-toggle')?.addEventListener('click', function(){
      alert('Menu mobile - à implémenter');
    });
  </script>
</header>

<main class="min-h-screen">
