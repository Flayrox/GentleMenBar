<?php
$siteName = config_value('site_name', 'Le Gentleman Pub');
$siteTagline = config_value('site_tagline', 'Pub Irlandais & Sports Bar à Paris');
if (!isset($page_title)) {
    $page_title = $siteName;
}
if (!isset($meta_description)) {
    $meta_description = $siteTagline;
}
?>
<!DOCTYPE html>
<html class="dark" lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo e($page_title); ?></title>
    <meta name="description" content="<?php echo e($meta_description); ?>"/>
    <meta name="robots" content="index, follow">
    
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f2ca50",
                        "primary-container": "#d4af37",
                        "primary-fixed": "#ffe088",
                        "surface": "#121212",
                        "surface-container": "#1d2022",
                        "background": "#111415",
                        "on-surface": "#e1e2e4",
                        "on-surface-variant": "#d0c5af",
                        "on-background": "#e1e2e4",
                        "status-live": "#22C55E",
                        "status-closed": "#EF4444",
                        "outline-variant": "#4d4635"
                    },
                    fontFamily: {
                        "display-lg": ["Playfair Display"],
                        "headline-md": ["Playfair Display"],
                        "body-base": ["Inter"],
                        "label-caps": ["Inter"]
                    },
                    fontSize: {
                        "display-lg": ["48px", { "lineHeight": "56px", "fontWeight": "700" }],
                        "headline-md": ["32px", { "lineHeight": "40px", "fontWeight": "700" }],
                        "body-base": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                        "label-caps": ["12px", { "lineHeight": "16px", "fontWeight": "600" }]
                    }
                }
            }
        }
    </script>
    <style>
        .text-glow { text-shadow: 0 0 20px rgba(212, 175, 55, 0.4); }
        .neon-text-gold { text-shadow: 0 0 12px rgba(212, 175, 55, 0.6); }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400; }
        body { min-height: 100dvh; }
    </style>
    <script>
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
</head>
<body class="bg-background text-on-background font-body-base antialiased min-h-screen flex flex-col pb-24 md:pb-0 relative">

<!-- Fixed Background -->
<div class="fixed inset-0 z-[-1]">
    <img alt="Pub ambiance" class="w-full h-full object-cover" src="/assets/uploads/hero-bg.jpg"/>
    <div class="absolute inset-0 bg-gradient-to-b from-background/95 via-background/80 to-background/95"></div>
</div>

<!-- TopAppBar -->
<header class="fixed top-0 w-full z-50">
    <div class="flex justify-between items-center px-4 h-[72px] max-w-6xl mx-auto w-full">
        <button aria-label="Menu" class="text-primary-container hover:text-primary transition-colors active:scale-95 duration-200 p-2 -ml-2">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 0;">menu</span>
        </button>
        <h1 class="font-display-lg text-2xl text-primary-container uppercase tracking-widest text-glow"><?php echo e($siteName); ?></h1>
        <a href="/le-comptoir" aria-label="Admin" class="w-10 h-10 rounded-full bg-primary-container/10 flex items-center justify-center border border-primary-container/30 active:scale-95 duration-200 backdrop-blur-sm hover:bg-primary-container/20 transition-colors">
            <span class="material-symbols-outlined text-primary-container" style="font-variation-settings: 'FILL' 1;">person</span>
        </a>
    </div>
</header>

<main class="flex-grow mt-[72px]">

