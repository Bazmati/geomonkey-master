# 🐒 Géomonkey — Plateforme web pour associations

**Géomonkey** est une application web complète destinée aux associations : site vitrine public + espace de gestion des adhérents, des événements et des contenus. Développée avec **Symfony 8 / PHP 8.4**, elle a été imaginée pour une association réelle — ce qui en fait un projet de démonstration concret, avec de vraies contraintes (RGPD, paiements, formulaires sécurisés).

> 🌐 **Avancement du projet :** [www.geomonkey.fr](https://www.geomonkey.fr) — mise en production prévue fin novembre 2026, hébergée sur un serveur Proxmox (conteneur LXC).

## 👥 À qui s'adresse ce projet ?

- **Les associations** qui cherchent une solution libre et auto-hébergeable pour gérer leur site, leurs adhérents, leurs événements et leur galerie photos.
- **Les développeurs PHP/Symfony** (étudiants, juniors, autodidactes) qui veulent étudier un projet Symfony 8 complet et réaliste : CRUD, formulaires, sécurité, webhooks, traitement d'images…
- **Les formateurs et élèves** en CDA / TP / dev web qui cherchent un cas d'étude concret d'architecture MVC moderne.
- **Les recruteurs** qui veulent évaluer un niveau réel sur Symfony/PHP : code structuré, commits réguliers, projet en production.
- **Les responsables associatifs** (clubs, collectifs, zines) qui veulent s'inspirer d'un fonctionnement éprouvé.

## ✨ Fonctionnalités (V1)

### ✅ Terminées


| Module               | Détail                                                          |
| ------------------   | --------------------------------------------------------------- |
| 👥 Utilisateurs      | CRUD complet, rôles et permissions, inscription                 |
| 📅 Événements        | CRUD des événements, gestion des participants                   |
| 🖼️ Galerie photos    | CRUD galerie, import d'images en 3 tailles, conversion **WebP** |
| 🔐 Sécurité          | Formulaires sécurisés, validation des données, contrôle d'accès |
| ⚖️ RGPD              | Consentement, données personnelles maîtrisées                   |
| 📜 Pages légales     | Conditions générales d'adhésion, politique de confidentialité   |
| 📰 Blog              | CRUD BlogPost + pages publiques                                 |
| 📝 Présentation      | CRUD Présentation (entité PresentationPage)                     |
| 💳 Adhésion en ligne | Paiement **Stripe** avec webhook signé                          |


### 🚧 En cours

- 🎨 Refonte du style des pages
- 🗂️ **CRUD Projets** avec relation événement ↔ article

### 📋 À faire (V1)

- 📈 SEO : métadonnées, sitemap, balisage
- 📱 Style responsive
- 📆 FullCalendar pour l'affichage des événements
- ❤️ Dons en ligne : le paiement Stripe étant opérationnel pour les adhésions, c'est une extension naturelle

## 🔮 Feuille de route

### V2

- 🛍️ **Boutique de goodies**
- 🎟️ **Billeterie** pour les événements
- 💰 **Gestionnaire de comptabilité** pour les associations loi 1901 (suivi des cotisations — l'entité `MembershipFee` est déjà en place — et exports)

### V3

- 🎫 Intégration des outils **Flyers Generator** et **Ticket Generator** (génération de supports pour les événements)
- 🌍 Multi-associations (une instance, plusieurs associations)

## 🛠️ Stack technique

- **Backend** : PHP 8.4, Symfony 8, Doctrine ORM 3, Doctrine Migrations
- **Frontend** : Twig, Bootstrap 5.3, Stimulus, Turbo (Symfony UX)
- **Base de données** : PostgreSQL
- **Images** : Intervention Image (redimensionnement + conversion WebP)
- **Paiements** : Stripe (adhésions en ligne, webhook dédié)
- **Tests** : PHPUnit
- **Hébergement** : production sur serveur Proxmox (conteneur LXC) — Docker Compose fourni pour le développement local 

## 🧠 Choix techniques

- **Stripe pour les paiements** : bundle officiel Symfony, sécurité et conformité PCI DSS prises en charge par Stripe, et aucune donnée de carte stockée par l'application — ce qui allège considérablement le dossier RGPD de l'association.
- **Pas de bundle pour les images** : le traitement (redimensionnement, WebP) est un composant cœur de l'application — le garder en code maîtrisé évite de dépendre du calendrier de mise à jour d'un tiers et de ses éventuelles failles. Un bundle quand il protège, du code maîtrisé quand c'est le produit.
- **Assistance IA** : le projet a été développé seul à un rythme soutenu avec l'assistance de l'IA — chaque choix tranché après compréhension, chaque ligne relue et validée avant commit.

## 🚀 Installation locale

### Prérequis

- PHP 8.4+ avec les extensions usuelles
- Composer
- Node.js + npm
- PostgreSQL
- Docker

### Avec Docker (recommandé)

```bash
git clone https://github.com/Bazmati/geomonkey-master.git
cd geomonkey-master
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate
```

### Sans Docker

```bash
git clone https://github.com/Bazmati/geomonkey-master.git
cd geomonkey-master
composer install
npm install && npm run build
cp .env.local.example .env.local   # puis configurer DATABASE_URL
php bin/console doctrine:migrations:migrate
symfony serve
```

L'application est alors accessible sur `https://localhost:8000`.

## 🌐 Mise en production

- La mise en production est prévue **fin novembre 2026** sur [**www.geomonkey.fr**](https://www.geomonkey.fr), sur un serveur Proxmox en conteneur LXC.
- `.env.local.example` liste les variables à définir (base de données, SMTP, Stripe…).
- Paiement : renseigner les clés Stripe et configurer le webhook (voir `StripeWebhookController`).

## 🧪 Tests

```bash
php bin/phpunit
```

## 🤝 Contribuer

Les retours sont bienvenus : ouvrez une issue ou une pull request. Toute suggestion (code, UI, accessibilité, RGPD) est appréciée.

## 👤 Auteur

**Basile Malin** — Concepteur-Développeur d'Applications (ENI, 2022-2024)

- 💼 CV : [bazmati.github.io/basile-malin-cv-2026](https://bazmati.github.io/basile-malin-cv-2026/)
- ✍️ Blog : [basile-malin.fr](https://basile-malin.fr)
- 🐙 GitHub : [github.com/Bazmati](https://github.com/Bazmati)

## 📄 Licence

Distribué sous licence **MIT**. Voir [`LICENSE`](LICENSE) pour plus d'informations.
