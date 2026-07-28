<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Page d'accueil -->
    <url>
        <loc><?php echo $baseUrl; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <!-- Liste des matchs actifs dans les 30 prochains jours -->
    <?php
    try {
        $stmt = $pdo->query("SELECT slug, date_match FROM matchs WHERE is_active = 1 AND date_match >= NOW() - INTERVAL 1 DAY ORDER BY date_match ASC");
        while ($m = $stmt->fetch()):
            $lastmod = date('Y-m-d', strtotime($m['date_match']));
    ?>
    <url>
        <loc><?php echo $baseUrl; ?>/match.php?slug=<?php echo urlencode($m['slug']); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <?php 
        endwhile;
    } catch (Throwable $e) {
        // Fallback silencieux en cas de problème BDD
    }
    ?>
</urlset>
