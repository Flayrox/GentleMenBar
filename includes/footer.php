<?php
// Calcul simple ouverture/fermeture
function is_open_now(): bool
{
    // Heure d'ouverture fixe (11:00) et fermeture variable
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));
    $dow = (int)$now->format('N'); // 1 (Mon) .. 7 (Sun)

    // Week-end défini ici comme vendredi (5) et samedi (6)
    $isWeekendNight = in_array($dow, [5,6], true);
    $openHour = 11; // 11:00
    $closeHour = $isWeekendNight ? 5 : 2; // fermeture à 05:00 le weekend, 02:00 sinon

    // Construire timestamps d'ouverture et de fermeture
    $open = $now->setTime($openHour, 0);

    // Si fermeture dépasse minuit, la fermeture est le lendemain
    if ($closeHour <= 6) {
        $close = $open->setTime($closeHour, 0)->modify('+1 day');
    } else {
        $close = $open->setTime($closeHour, 0);
    }

    // Cas simple : si maintenant est entre open et close
    return ($now >= $open && $now <= $close) || ($now->format('H') < 6 && $isWeekendNight && $now <= $close);
}

$openStatus = is_open_now() ? 'OUVERT' : 'FERMÉ';

?>
    </main>

    <footer class="bg-[#121212] text-gray-200 mt-12">
      <div class="max-w-6xl mx-auto px-6 py-10 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <h3 class="text-2xl font-display text-amber-400">Le Gentleman Pub</h3>
          <p class="mt-2">Saint-Michel, Paris 5e<br>3 Rue imaginaire, 75005 Paris</p>
          <p class="mt-3">Téléphone: <a href="tel:+33123456789" class="text-amber-300">01 23 45 67 89</a></p>
        </div>
        <div>
          <h4 class="font-semibold">Horaires</h4>
          <p class="mt-2">Lun-Jeu: 11:00 - 02:00<br>Ven-Sam: 11:00 - 05:00<br>Dim: 12:00 - 00:00</p>
          <p class="mt-3">Statut actuel: <span class="font-bold text-amber-300"><?php echo e($openStatus); ?></span></p>
        </div>
        <div>
          <h4 class="font-semibold">Réseaux</h4>
          <ul class="mt-2 space-y-2">
            <li><a href="#" class="text-amber-300">Facebook</a></li>
            <li><a href="#" class="text-amber-300">Instagram</a></li>
            <li><a href="#" class="text-amber-300">X (Twitter)</a></li>
          </ul>
        </div>
      </div>
      <div class="border-t border-gray-800 py-4 text-center text-sm text-gray-400">
        © <?php echo date('Y'); ?> Le Gentleman Pub — Tous droits réservés · <a href="/le-comptoir" class="text-amber-300 hover:text-amber-200">Espace Privé</a>
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
