<?php
function parse_schedule_window(string $schedule): array
{
    if (!preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $schedule, $matches)) {
        return ['open' => '11:00', 'close' => '02:00'];
    }
    return ['open' => $matches[1], 'close' => $matches[2]];
}

function build_datetime_from_clock(DateTimeImmutable $base, string $clock): DateTimeImmutable
{
    [$hour, $minute] = array_map('intval', explode(':', $clock));
    return $base->setTime($hour, $minute);
}

function is_open_now(): bool
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
    $dow = (int)$now->format('N'); // 1 = Lundi, 7 = Dimanche

    $dayKeys = [
        1 => 'horaires_lundi',
        2 => 'horaires_mardi',
        3 => 'horaires_mercredi',
        4 => 'horaires_jeudi',
        5 => 'horaires_vendredi',
        6 => 'horaires_samedi',
        7 => 'horaires_dimanche'
    ];

    $defaultSchedules = [
        1 => '11:00 - 02:00',
        2 => '11:00 - 02:00',
        3 => '11:00 - 02:00',
        4 => '11:00 - 02:00',
        5 => '11:00 - 05:00',
        6 => '11:00 - 05:00',
        7 => '12:00 - 00:00'
    ];

    $configKey = $dayKeys[$dow] ?? 'horaires_lundi';
    $defaultSchedule = $defaultSchedules[$dow] ?? '11:00 - 02:00';

    $schedule = parse_schedule_window(config_value($configKey, $defaultSchedule));

    $open = build_datetime_from_clock($now, $schedule['open']);
    $close = build_datetime_from_clock($now, $schedule['close']);

    if ($close <= $open) {
        $close = $close->modify('+1 day');
    }

    if ($now < $open) {
        $previousOpen = $open->modify('-1 day');
        $previousClose = $close->modify('-1 day');
        if ($previousClose <= $previousOpen) {
            $previousClose = $previousClose->modify('+1 day');
        }
        if ($now >= $previousOpen && $now <= $previousClose) {
            return true;
        }
    }

    return $now >= $open && $now <= $close;
}

$openStatus = is_open_now() ? 'OUVERT' : 'FERMÉ';
$openStatusClass = is_open_now() ? 'status-live' : 'status-closed';
$siteName = config_value('site_name', 'Le Gentleman Pub');
$address = config_value('bar_adresse');
$telephone = config_value('bar_telephone');
$instagram = config_value('insta_link');
$facebook = config_value('facebook_link');
$copyText = config_value('footer_copy_text', 'Tous droits réservés');
$privacyLabel = config_value('footer_privacy_label', 'Espace Privé');
?>

</main>

<!-- BottomNavBar Mobile -->
<nav class="md:hidden fixed bottom-0 w-full z-50 bg-background/80 backdrop-blur-2xl border-t border-primary-container/10 flex justify-around items-center h-24 pb-safe">
    <a href="/" class="flex flex-col items-center justify-center text-on-surface-variant/60 hover:text-primary-container w-16 active:scale-90 transition-transform">
        <span class="material-symbols-outlined">home</span>
        <span class="font-label-caps text-[10px] tracking-widest uppercase">Accueil</span>
    </a>
    <a href="/#matchs" class="flex flex-col items-center justify-center text-on-surface-variant/60 hover:text-primary-container w-16 active:scale-90 transition-transform">
        <span class="material-symbols-outlined">sports_score</span>
        <span class="font-label-caps text-[10px] tracking-widest uppercase">Matchs</span>
    </a>
    <a href="/#carte" class="flex flex-col items-center justify-center text-on-surface-variant/60 hover:text-primary-container w-16 active:scale-90 transition-transform">
        <span class="material-symbols-outlined">menu_book</span>
        <span class="font-label-caps text-[10px] tracking-widest uppercase">Carte</span>
    </a>
    <a href="/#contact" class="flex flex-col items-center justify-center text-on-surface-variant/60 hover:text-primary-container w-16 active:scale-90 transition-transform">
        <span class="material-symbols-outlined">info</span>
        <span class="font-label-caps text-[10px] tracking-widest uppercase">Infos</span>
    </a>
</nav>

<!-- Desktop Footer -->
<footer class="hidden md:block bg-surface-container/40 border-t border-outline-variant/20 mt-12">
    <div class="max-w-6xl mx-auto px-4 py-8 grid grid-cols-3 gap-8">
        <div>
            <h3 class="font-display-lg text-2xl text-primary mb-4"><?php echo e($siteName); ?></h3>
            <p class="text-on-surface-variant text-sm"><?php echo e(config_value('site_tagline')); ?></p>
        </div>
        <div>
            <h4 class="font-headline-md text-lg text-primary mb-4"><?php echo e(config_value('footer_hours_title', 'Horaires')); ?></h4>
            <ul class="text-on-surface-variant text-xs space-y-1">
                <li>Lun - Mer: <?php echo e(config_value('horaires_lundi', '11:00 - 02:00')); ?></li>
                <li>Jeu: <?php echo e(config_value('horaires_jeudi', '11:00 - 02:00')); ?></li>
                <li>Ven - Sam: <?php echo e(config_value('horaires_vendredi', '11:00 - 05:00')); ?></li>
                <li>Dimanche: <?php echo e(config_value('horaires_dimanche', '12:00 - 00:00')); ?></li>
                <li class="mt-3 font-semibold text-<?php echo $openStatusClass; ?>"><?php echo e($openStatus); ?></li>
            </ul>
        </div>
        <div>
            <h4 class="font-headline-md text-lg text-primary mb-4"><?php echo e(config_value('footer_socials_title', 'Contact')); ?></h4>
            <p class="text-on-surface-variant text-sm"><?php echo e($address); ?></p>
            <p class="text-on-surface-variant text-sm"><a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $telephone); ?>" class="text-primary hover:text-primary-fixed"><?php echo e($telephone); ?></a></p>
            <div class="flex gap-3 mt-4 text-sm">
                <a href="<?php echo e($facebook); ?>" target="_blank" rel="noopener" class="text-primary hover:text-primary-fixed">Facebook</a>
                <a href="<?php echo e($instagram); ?>" target="_blank" rel="noopener" class="text-primary hover:text-primary-fixed">Instagram</a>
            </div>
        </div>
    </div>
    <div class="border-t border-outline-variant/20 text-center text-on-surface-variant text-sm py-4">
        © <?php echo date('Y'); ?> <?php echo e($siteName); ?> — <?php echo e($copyText); ?> · <a href="/admin.php" class="text-primary hover:text-primary-fixed"><?php echo e($privacyLabel); ?></a>
    </div>
</footer>
</body>
</html>

