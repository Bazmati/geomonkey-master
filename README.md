# 🐒 Géomonkey — Site vitrine et gestion de l'association

Application web servant de vitrine et d'outil de gestion pour l'association Géomonkey.

## ✨ Fonctionnalités
- Site vitrine de présentation de l'association
- Gestion des adhérents et des utilisateurs avec rôles
- Gestion des contenus et des activités de l'association

## 🛠️ Technologies
![Symfony](https://img.shields.io/badge/-Symfony-000000?logo=symfony&logoColor=white)
![PHP](https://img.shields.io/badge/-PHP-777BB4?logo=php&logoColor=white)
![Twig](https://img.shields.io/badge/-Twig-39B54A)
![Bootstrap](https://img.shields.io/badge/-Bootstrap-7952B3?logo=bootstrap&logoColor=white)
![MySQL](https://img.shields.io/badge/-MySQL-4479A1?logo=mysql&logoColor=white)

## 🚀 Installation locale
```bash
git clone https://github.com/Bazmati/geomonkey-master.git
cd geomonkey-master
composer install
npm install && npm run build
# Configurer .env.local (DATABASE_URL)
php bin/console doctrine\:migrations\:migrate
symfony serve
