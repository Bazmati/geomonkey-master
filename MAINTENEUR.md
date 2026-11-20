# MAINTENEUR — guide de reprise de geomonkey.fr

Ce document est le mode d'emploi complet pour reprendre la main sur
l'application (déploiement, tests, secrets, pièges connus) si le
développeur habituel n'est pas disponible. À garder à jour avec le code.

## 1. Stack en deux lignes

- **Application** : Symfony 7 / PHP 8.4, PostgreSQL, AssetMapper (pas de
  Webpack), Stripe (adhésions + dons), GMX en IMAP/SMTP pour la messagerie
  intégrée (webklex/php-imap pure PHP).
- **Front bilingue FR/EN** (catalogues `translations/messages.{fr,en}.php`,
  locale en session/cookie `gm_locale`, route `/locale/{fr|en}`) ;
  backoffice, contenus base et mails transactionnels restent en français
  (choix assumé).

## 2. Environnements

| Environnement | Où | Bases / notes |
|---|---|---|
| Dev local | poste du dev | `.env.local` (non versionné), base `geomonkey` |
| Tests | même machine | base `geomonkey_test` (suffixe `_test` via `config/packages/doctrine.yaml`) |
| Production | Proxmox **CT 202**, `/var/www/geomonkey` | `.env.local` du CT, nginx + php8.4-fpm |

Les autres CT équipent le serveur (200, 203) : ne jamais rien installer
dans le CT 202 hors des besoins de l'app.

## 3. Variables d'environnement (`.env.local`)

Fichier exemple : `.env.local.example`. Les indispensables :

- `APP_SECRET`, `DATABASE_URL`
- `MAILER_DSN` (SMTP GMX ; encoder `#` → `%23`, `%` → `%25` dans le mot de passe — piège déjà rencontré)
- Stripe : `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, URLs de succès/annulation (adhésion + don)
- `CONTACT_EMAIL`, `WHATSAPP_GROUP_LINK`, `MAIL_FROM_ADDRESS`
- Boîte mail asso : `MAILBOX_HOST/PORT/ENCRYPTION/USER/PASSWORD` (+ `MAILBOX_SENT_FOLDER` optionnel, défaut `INBOX.Sent`)

**En test** (`phpunit.dist.xml`) : `MAILBOX_HOST/PORT/USER/PASSWORD` sont
forcés vides et le mailer est `null://null`. Le captcha Turnstile est
désactivé (`config/packages/test/zmkr_cloudflare_turnstile.yaml`).

## 4. Lancer les tests

```bash
# Base de test régénérée à la main (JAMAIS par migrations — la chaîne SQL
# n'est pas rejouable depuis zéro, une vieille migration référence une table absente) :
php bin/console doctrine:database:create --if-not-exists --env=test
php bin/console doctrine:schema:update --force --env=test

php bin/phpunit        # en local (phpunit.xml local, non versionné)
php bin/phpunit -c phpunit.dist.xml   # version commitée de la config
```

La CI GitHub (`.github/workflows/tests.yml`) joue la suite entière sur un
PostgreSQL 15 de service à chaque push sur `main` — si elle est rouge,
ne pas déployer le CT 202.

DAMA DoctrineTestBundle isole chaque test dans une transaction : les
entités sont détachées après la requête, re-fetcher l'entité pour
vérifier un changement en base.

## 5. Déployer en production (CT 202)

```bash
pcat# pct exec 202 -- bash -c 'cd /var/www/geomonkey && git pull'
pct exec 202 -- bash -c 'cd /var/www/geomonkey && composer install --no-dev --optimize-autoloader'
pct exec 202 -- bash -c 'cd /var/www/geomonkey && php bin/console cache:clear --env=prod'
pct exec 202 -- bash -c 'chown -R www-data:www-data /var/www/geomonkey/var'
pct exec 202 -- bash -c 'systemctl reload php8.4-fpm'   # vider l'opcache
```

Accès root direct (pas de sudo). Vérifier ensuite la page d'accueil et
`https://www.geomonkey.fr` avec une navigation privée.

## 6. Gouvernance et droits

Toute la matrice d'accès vit dans `src/Security/EntityVoter.php` :

- `ENTITY_EDIT` (édition) : président, VP, secrétaire — **pas** le trésorier
- `ENTITY_TREASURY` (données financières, liste des dons) : bureau étendu (trésorier inclus)
- `ENTITY_CASH` (encaissements hors-ligne, cotisation et don) : **trésorier + président**
- `ENTITY_DELETE`, `ENTITY_PUBLISH`, `ENTITY_SETTINGS` : président seul
- `ROLE_SUPER_ADMIN` : break-glass total (blocage de gouvernance uniquement)
- Règle de sécurité : un bureau sans adhésion active est rétrogradé
  automatiquement (`User::applyMembershipStatus()`), revérifié en base à
  chaque vote.

L'encaissement hors-ligne (chèque/espèces/virement) est traçable : les
enregistrements portent `MANUEL-<moyen>-<Ymd-His>` (cotisations :
`stripe_payment_id`, dons : `stripe_session_id`) et sont badgés
« hors-ligne » dans le backoffice.

## 7. Rotation d'un secret (GMX, Stripe…)

Symptôme typique d'un mot de passe GMX désynchronisé : la **liste** des
messages s'affiche encore (cache PostgreSQL du cron `app:mailbox:sync`)
mais chaque clic sur un message répond « Message introuvable ou boîte
mail indisponible » (le détail fait une lecture IMAP en direct).
Diagnostic :

```bash
pct exec 202 -- bash -c 'cd /var/www/geomonkey && php bin/console app:mailbox:sync -v'
```

`NO authentication failed` = mettre à jour `MAILBOX_PASSWORD` **et**
`MAILER_DSN` dans le `.env.local` du CT 202 (encodage `%23`/`%25`), puis
relancer la synchro jusqu'au `[OK]`. Le cron reprend tout seul.

Pour Stripe : faire tourner la clé dans le Dashboard, mettre à jour
`STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET`, re-tester un paiement en
mode test avant de redéployer.

## 8. Pièges connus (ne pas retomber dedans)

- **DSN** : `#` → `%23`, `%` → `%25` dans les mots de passe du `MAILER_DSN` ;
  vérifier que la dernière ligne de `.env.local` a bien un retour à la
  ligne avant d'ajouter une variable (des lignes fusionnées ont déjà
  cassé un déploiement).
- **Flashs Twig** : ne jamais itérer ni compter le flash bag pour
  « tester » s'il est vide — l'itérateur appelle `all()` et **vide le sac
  de façon irréversible**. Pour masquer la zone quand elle est vide :
  `{% if app.session and app.session.flashbag.peekAll|length > 0 %}`.
  Seul `peekAll()` regarde sans consommer.
- **Formulaires** : passer `$form` (pas `$form->createView()`) à
  `render()` pour que Symfony réponde 422 automatiquement en soumission
  invalide. Les Constructeurs des services traduits exigent le
  `TranslatorInterface`.
- **Migrations** : non rejouables depuis zéro ; passer toujours par
  `doctrine:schema:update --env=test` pour la base de test. (Backlog :
  rendre la chaîne rejouable.)
- **Uploads** : limite 50M côté nginx **et** php.ini FPM (les valeurs du
  CLI ne comptent pas).
- **Webhook Stripe** : vérifier que la valeur du secret ne contient pas
  un préfixe `whsec_` dupliqué ; symptôme : tous les webhooks en 400 et
  les dons restés `pending`.
- **Base de données** : PostgreSQL sur le CT 202, rôle `postgres`
  (`su - postgres -c 'psql geomonkey ...'`). Tables notables :
  `membership_fees` (avec un s), `memberships`, `donations` (avec un s),
  `user`, `mailbox_messages` (cache messagerie). `membership_valid_until`
  du user n'est qu'un repli d'affichage, la vérité est dans
  `memberships.hasActiveMembership()`.

## 9. Cron et sauvegardes

- `app:mailbox:sync` : synchro IMAP → cache `mailbox_messages` (cron).
- `scripts/backup.sh` : sauvegarde quotidienne recommandée (voir le script).
- Webhook Stripe en prod : `POST /webhook/stripe` (test « Envoyer un
  événement de test » `checkout.session.completed` depuis le Dashboard →
  réponse 200 attendue).

## 10. Rituel de contribution

1. tests verts au complet (`php bin/phpunit`),
2. commit + push sur `main` (la CI doit passer),
3. déploiement CT 202 (section 5),
4. vérification visuelle en prod.
