# Migration Integration Tailwind + Scores — Le Gentleman Pub

**Date**: 20 mai 2026

## 📋 Résumé des changements

### Phase 1: Infrastructure Backend ✅ COMPLÈTE
- ✅ `schema.sql`: Ajout colonnes `score_equipe_1`, `score_equipe_2`, `minute_actuelle`, `statut` à table `matchs`
- ✅ `api/update-scores.php`: Nouvelle API endpoint pour synchronisation scores en direct
- ✅ `config/db.php`: Helpers `get_match_status_badge()`, `is_match_live()`, `format_score()`
- ✅ `admin.php`: Ajout formulaire saisie scores + statut dans tab Matchs

### Phase 2: Design Frontend ✅ COMPLÈTE
- ✅ `includes/header.php`: Remplacement design Premium Tailwind (TopAppBar fixe, Material Symbols, text-glow animations)
- ✅ `includes/footer.php`: Remplacement design Premium (navbar mobile fixe + footer desktop)
- ✅ `index.php`: 3 sections redesignées (hero immersive, matchs avec badges LIVE, carte Art Deco)

### Phase 3: Assets ⏳ MANUAL
- Images dans `/assets/uploads/` prêtes (vous choisissez lesquelles utiliser)

---

## 🚀 ÉTAPES DE DÉPLOIEMENT

### Étape 1: Exécuter la migration SQL

**Avant tout**, tu DOIS mettre à jour la structure de la base de données. Deux options:

#### Option A: Réinitialiser complète (recommandé si dev)
```sql
-- Réexécute schema.sql complet
SOURCE schema.sql;
```
Cela:
- Crée toutes les tables (avec les nouvelles colonnes pour scores)
- Réinsère les données officielles (30 config, 16 produits)
- Réinsère le sample match PSG vs OM

#### Option B: Migration en place (production)
```sql
-- Si la table matchs existe déjà, ajoute juste les colonnes manquantes:
ALTER TABLE `matchs`
ADD COLUMN `score_equipe_1` INT DEFAULT NULL AFTER `image_path`,
ADD COLUMN `score_equipe_2` INT DEFAULT NULL AFTER `score_equipe_1`,
ADD COLUMN `minute_actuelle` INT DEFAULT NULL AFTER `score_equipe_2`,
ADD COLUMN `statut` ENUM('scheduled', 'live', 'finished') DEFAULT 'scheduled' AFTER `minute_actuelle`,
ADD KEY `statut_idx` (`statut`);
```

⚠️ **IMPORTANT**: Sans ces colonnes, les nouveaux formulaires admin vont échouer silencieusement.

---

### Étape 2: Vérifier les images

Les images uploadées sont ici: `assets/uploads/`

```
6b07ca26-cd72-4a94-918d-93b53df4d3cf.jpg  (hero-bg? à vérifier)
6d3f8b1f-1335-4e21-bd39-255e68eb6d8a.jpg  (photos pub)
761b222c-51cb-4bd8-9393-a8a29207f372.jpg  (...
7de1c8dd-1ede-4349-9916-e34e5c105943.jpg
9ade76d6-09d0-4755-9d0d-3673960d773f.png
cf9bb6b3-9d91-438d-bff2-c859a60706ac.jpg
dafafef6-c9ad-4200-8a79-378a6f5e9cb7.jpg
default-match.svg                          (placeholder match, existing)
```

**Action**: Renomme une image appropriée (pub/bar) en `hero-bg.jpg` pour le Hero, ou mets à jour `site_config.hero_bg_image` en DB.

---

### Étape 3: Tester le déploiement

#### A. Admin Dashboard
- Accède à `/le-comptoir` (credentials: admin / Gentleman2026!)
- Tabs Matchs: Vérifie que les nouveaux champs score/minute/statut s'affichent
- Ajoute/édite un match et remplis les scores → déjà fonctionnel ✅

#### B. Page d'accueil
- Visite `/` → Hero section doit afficher
- Scrolle → Section matchs avec badges dynamiques
- Si scores existent → affiche "2 - 1" au format neon-text-gold
- Scrolle → Section carte groupée par catégories Art Deco style

#### C. API Scores (test manual)
```bash
curl -X POST http://localhost/api/update-scores.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: default_secret_key" \
  -d '{
    "match_id": 1,
    "score_equipe_1": 2,
    "score_equipe_2": 1,
    "minute_actuelle": 45,
    "statut": "live"
  }'
```

Response attendue:
```json
{
  "success": true,
  "message": "Score updated successfully",
  "match_id": 1,
  "score": "2 - 1",
  "statut": "live",
  "minute": 45
}
```

---

## 📝 Configuration globale (site_config)

Les 30 clés de configuration suivantes contrôlent le site:

| Clé | Type | Description |
|-----|------|-------------|
| `site_name` | String | "Le Gentleman Pub" |
| `site_tagline` | String | "Pub Irlandais & Sports Bar à Paris" |
| `hero_title` | String | Titre hero (titre principal) |
| `hero_subtitle` | String | Sous-titre hero (description) |
| `hero_cta_primary` | String | CTA 1 (ex: "Voir les matchs ce soir") |
| `hero_cta_secondary` | String | CTA 2 (ex: "Découvrir la carte") |
| `hero_bg_image` | String | `/assets/uploads/hero-bg.jpg` |
| `bar_adresse` | String | "14 Rue Saint Germain, 75006 Paris" |
| `bar_telephone` | String | "01 71 71 71 71" |
| `insta_link` | String | URL Instagram |
| `facebook_link` | String | URL Facebook |
| `horaires_semaine` | String | Format: "HH:MM - HH:MM" (ex: "11:00 - 02:00") |
| `horaires_weekend` | String | Format: "HH:MM - HH:MM" (ex: "11:00 - 05:00") |
| `horaires_dimanche` | String | Format: "HH:MM - HH:MM" (ex: "12:00 - 00:00") |
| ... + 16 autres clés |

**Modification**: Admin Dashboard tab "Infos du Bar" permet d'éditer toutes les valeurs directement.

---

## 🔄 API Synchronisation Scores

### Endpoint: `POST /api/update-scores.php`

**Headers requis:**
```
Content-Type: application/json
X-API-Key: <API_KEY>
```

**Payload:**
```json
{
  "match_id": 1,
  "score_equipe_1": 2,
  "score_equipe_2": 1,
  "minute_actuelle": 45,
  "statut": "live"
}
```

**Réponse succès (200 OK):**
```json
{
  "success": true,
  "message": "Score updated successfully",
  "match_id": 1,
  "score": "2 - 1",
  "statut": "live",
  "minute": 45
}
```

**Erreurs:**
- `400`: Paramètre invalide
- `403`: API key incorrecte
- `404`: Match non trouvé
- `500`: Erreur serveur

**API Key par défaut:** `default_secret_key` (modifie en production!)

---

## 🎨 Personnalisation Design

### Palette de couleurs Tailwind
- **Primary**: `#f2ca50` (gold, boutons + accents)
- **Primary Container**: `#d4af37` (gold darker, textes)
- **Surface**: `#121212` (bg sombre)
- **Background**: `#111415` (bg très sombre)
- **Status Live**: `#22C55E` (vert, badges LIVE)
- **Status Closed**: `#EF4444` (rouge, fermé)

### Typographies
- Display: Playfair Display (titres)
- Body: Inter (textes)
- Icons: Material Symbols Outlined

### Animations
- `.text-glow`: Gold shadow sur textes
- `.neon-text-gold`: Plus intense gold shadow
- `.animate-pulse-glow`: Pulse vert pour LIVE badges

---

## 📂 Structure fichiers modifiés

```
GentleMenBar/
├── schema.sql                    (✅ ALTER TABLE matchs)
├── config/db.php                 (✅ +3 helpers)
├── api/
│   └── update-scores.php         (✅ NEW)
├── admin.php                     (✅ Form scores + statut)
├── includes/
│   ├── header.php                (✅ Premium Tailwind)
│   └── footer.php                (✅ Premium Tailwind)
├── index.php                     (✅ Hero/Matchs/Carte redesign)
├── index_old.php                 (Backup ancien)
└── assets/uploads/
    ├── hero-bg.jpg               (à renommer depuis UUID)
    ├── match-*.jpg               (photos)
    └── default-match.svg         (existing)
```

---

## ✅ Checklist pré-production

- [ ] Exécuté `schema.sql` ou ALTER TABLE (nouvelles colonnes scores)
- [ ] Renommé une image en `hero-bg.jpg` ou mis à jour config
- [ ] Testé /le-comptoir admin (formulaire matchs avec scores)
- [ ] Testé `/` homepage (hero + sections matchs + carte affichent)
- [ ] Testé API `/api/update-scores.php` avec curl
- [ ] Vérifié badges LIVE s'affichent correctement
- [ ] Testé mobile navbar (bottom nav 4 icons)
- [ ] Testé responsive design (desktop + mobile)
- [ ] Git push et deploy

---

## 🐛 Troubleshooting

### "Unknown column 'score_equipe_1' in 'field list'"
**Cause**: schema.sql ALTER TABLE pas exécuté.
**Solution**: Exécute `source schema.sql;` ou les ALTER TABLE ci-dessus.

### "Headers already sent"
**Cause**: Output avant `require header.php`.
**Solution**: Vérifi aucun echo avant `<?php` ou require.

### Images n'affichent pas
**Cause**: Chemin incorrect ou permissions.
**Solution**: Vérifi `/assets/uploads/` existe et fichiers sont lisibles. Teste avec `http://localhost/assets/uploads/hero-bg.jpg`.

### Mobile nav manque
**Cause**: CSS Tailwind mobile breakpoints.
**Solution**: Vérifi `md:hidden` / `hidden md:block` classes correctes.

---

## 📞 Points de contact

- **Admin Dashboard**: `/le-comptoir`
- **API Scores**: `/api/update-scores.php`
- **Config DB**: Onglet admin "Infos du Bar"
- **Images**: `/assets/uploads/`

---

Déploiement complet = **Backend ✅ + Frontend ✅ + Async API ✅** 🎉
