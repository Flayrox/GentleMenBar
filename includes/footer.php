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
$siteName = config_value('site_name', 'Le Gentleman Pub');
$address = config_value('bar_adresse');
$telephone = config_value('bar_telephone');
$instagram = config_value('insta_link');
$facebook = config_value('facebook_link');
$copyText = config_value('footer_copy_text', 'Tous droits réservés');
$privacyLabel = config_value('footer_privacy_label', 'Espace Privé');

?>
    </main>

    <footer class="bg-[#121212] text-gray-200 mt-12">
      <div class="max-w-6xl mx-auto px-6 py-10 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <h3 class="text-2xl font-display text-amber-400"><?php echo e($siteName); ?></h3>
          <p class="mt-2"><?php echo e(config_value('site_tagline', 'Pub irlandais à Saint-Michel, Paris')); ?></p>
          <p class="mt-3"><?php echo e(config_value('footer_address_title', 'Adresse')); ?>: <span class="text-gray-300"><?php echo e($address); ?></span></p>
          <p class="mt-2"><?php echo e(config_value('footer_phone_label', 'Téléphone')); ?>: <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $telephone)); ?>" class="text-amber-300"><?php echo e($telephone); ?></a></p>
        </div>
        <div>
          <h4 class="font-semibold"><?php echo e(config_value('footer_hours_title', 'Horaires')); ?></h4>
          <p class="mt-2">Semaine: <?php echo e(config_value('horaires_semaine')); ?><br>Weekend: <?php echo e(config_value('horaires_weekend')); ?><br>Dimanche: <?php echo e(config_value('horaires_dimanche')); ?></p>
          <p class="mt-3">Statut actuel: <span class="font-bold text-amber-300"><?php echo e($openStatus); ?></span></p>
        </div>
        <div>
          <h4 class="font-semibold"><?php echo e(config_value('footer_socials_title', 'Réseaux')); ?></h4>
          <ul class="mt-2 space-y-2">
            <li><a href="<?php echo e($facebook); ?>" class="text-amber-300" target="_blank" rel="noopener">Facebook</a></li>
            <li><a href="<?php echo e($instagram); ?>" class="text-amber-300" target="_blank" rel="noopener">Instagram</a></li>
            <li><a href="#" class="text-amber-300">X (Twitter)</a></li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-800 py-4 text-center text-sm text-gray-400">
        © <?php echo date('Y'); ?> <?php echo e($siteName); ?> — <?php echo e($copyText); ?> · <a href="/le-comptoir" class="text-amber-300 hover:text-amber-200"><?php echo e($privacyLabel); ?></a>
      </div>
    </footer>

    <script>
      // petit script pour accentuer les CTA, ex : mise en avant progressive
      document.querySelectorAll('a').forEach(a=>{
        a.addEventListener('mouseover', ()=> a.classList.add('scale-105'));
        a.addEventListener('mouseout', ()=> a.classList.remove('scale-105'));
      });
    </script>
</body>
</html>
