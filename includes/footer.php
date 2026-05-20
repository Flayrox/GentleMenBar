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
    $dow = (int)$now->format('N');

    if ($dow === 7) {
        $schedule = parse_schedule_window(config_value('horaires_dimanche', '12:00 - 00:00'));
    } elseif (in_array($dow, [5, 6], true)) {
        $schedule = parse_schedule_window(config_value('horaires_weekend', '11:00 - 05:00'));
    } else {
        $schedule = parse_schedule_window(config_value('horaires_semaine', '11:00 - 02:00'));
    }

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
            <h4 class="font-headline-md text-lg text-primary mb-4">Horaires</h4>
            <ul class="text-on-surface-variant text-sm space-y-1">
                <li>Semaine: <?php echo e(config_value('horaires_semaine')); ?></li>
                <li>Weekend: <?php echo e(config_value('horaires_weekend')); ?></li>
                <li>Dimanche: <?php echo e(config_value('horaires_dimanche')); ?></li>
                <li class="mt-3 font-semibold text-<?php echo $openStatusClass; ?>"><?php echo e($openStatus); ?></li>
            </ul>
        </div>
        <div>
            <h4 class="font-headline-md text-lg text-primary mb-4">Contact</h4>
            <p class="text-on-surface-variant text-sm"><?php echo e($address); ?></p>
            <p class="text-on-surface-variant text-sm"><a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $telephone); ?>" class="text-primary hover:text-primary-fixed"><?php echo e($telephone); ?></a></p>
            <div class="flex gap-3 mt-4 text-sm">
                <a href="<?php echo e($facebook); ?>" target="_blank" rel="noopener" class="text-primary hover:text-primary-fixed">Facebook</a>
                <a href="<?php echo e($instagram); ?>" target="_blank" rel="noopener" class="text-primary hover:text-primary-fixed">Instagram</a>
            </div>
        </div>
    </div>
    <div class="border-t border-outline-variant/20 text-center text-on-surface-variant text-sm py-4">
        © <?php echo date('Y'); ?> <?php echo e($siteName); ?> — <?php echo e($copyText); ?> · <a href="/le-comptoir" class="text-primary hover:text-primary-fixed"><?php echo e($privacyLabel); ?></a>
    </div>
</footer>

</body>
</html>

