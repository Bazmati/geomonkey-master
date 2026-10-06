# 🐒 Géomonkey — Plateforme web pour associations

**Géomonkey** est une application web complète destinée aux associations : site vitrine public + espace de gestion des adhérents, des événements et des contenus. Développée avec **Symfony 8 / PHP 8.4**, elle a été imaginée pour une association réelle — ce qui en fait un projet de démonstration concret, avec de vraies contraintes (RGPD, paiements, formulaires sécurisés).

> 🚀 **Démo en ligne :** [www.geomonkey.fr](https://www.geomonkey.fr)

## 👥 À qui s'adresse ce projet ?

- **Les associations** qui cherchent une solution libre et auto-hébergeable pour gérer leur site, leurs adhérents, leurs événements et leur galerie photos.
- **Les développeurs PHP/Symfony** (étudiants, juniors, autodidactes) qui veulent étudier un projet Symfony 8 complet et réaliste : CRUD, formulaires, sécurité, webhooks, traitement d'images…
- **Les formateurs et élèves** en CDA / TP / dev web qui cherchent un cas d'étude concret d'architecture MVC moderne.
- **Les recruteurs** qui veulent évaluer un niveau réel sur Symfony/PHP : code structuré, commits réguliers, projet en production.
- **Les responsables associatifs** (clubs, collectifs, zines) qui veulent s'inspirer d'un fonctionnement éprouvé.

## ✨ Fonctionnalités (V1)

### ✅ Terminées


| Module             | Détail                                                          |
| ------------------ | --------------------------------------------------------------- |
| 👥 Utilisateurs    | CRUD complet, rôles et permissions, inscription                 |
| 📅 Événements      | CRUD des événements, gestion des participants                   |
| 🖼️ Galerie photos | CRUD galerie, import d'images en 3 tailles, conversion **WebP** |
| 🔐 Sécurité        | Formulaires sécurisés, validation des données, contrôle d'accès |
| ⚖️ RGPD            | Consentement, données personnelles maîtrisées                   |
| 📜 Pages légales   | Conditions générales d'adhésion, politique de confidentialité   |


### 🚧 En cours

- 🎨 Refonte du style des pages
- 📝 CRUD Présentation (l'entité `PresentationPage` est déjà en place)
- 🖼️ Sélecteur d'images pour la présentation

### 📋 À faire (V1)

- 📰 **Blog** (l'entité `BlogPost` existe déjà, reste le CRUD et les pages publiques)
- 🗂️ **CRUD Projets** avec relation événement ↔ article
- 📈 SEO : métadonnées, sitemap, balisage
- 📱 Style responsive
- 📆 **FullCalendar** pour l'affichage des événements

## 🔮 Feuille de route (V2)

- 🎫 Intégration des outils **Flyers Generator** et **Ticket Generator** (génération de supports pour les événements)
- 💰 **Comptabilité de l'association** : suivi des cotisations (l'entité `MembershipFee` est déjà en place), exports
- ❤️ **Dons en ligne** : le paiement Stripe est déjà opérationnel pour les adhésions, les dons sont une extension naturelle
- 🌍 Multi-associations (une instance, plusieurs associations)

## 🛠️ Stack technique

Symfony  
PHP  
Twig  
Bootstrap 5  
{-PostgreSQL-}  
{+MySQL+}  
Docker

- **Backend** : PHP 8.4, Symfony 8, Doctrine ORM 3, Doctrine Migrations
- **Frontend** : Twig, Bootstrap 5.3, Stimulus, Turbo (Symfony UX)
- {-**Base de données** : PostgreSQL-}
- {+**Base de données** : MySQL / MariaDB+}
- **Images** : Intervention Image (redimensionnement + conversion WebP)
- **Paiements** : Stripe (adhésions en ligne, webhook dédié)
- **Tests** : PHPUnit
- **Conteneurs** : Docker Compose fourni (`compose.yaml`)

## 🚀 Installation locale

### Prérequis

- PHP 8.4+ avec les extensions usuelles
- Composer
- Node.js + npm
- {-PostgreSQL ou Docker-}
- {+MySQL ou Docker+}

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

- Le projet tourne en production sur [**www.geomonkey.fr**](https://www.geomonkey.fr).
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
- 🐙 GitHub : [github.com/Bazmati](https://github.com/Bazmati)

## 📄 Licence

Distribué sous licence **MIT**. Voir [`LICENSE`](LICENSE) pour plus d'informations.