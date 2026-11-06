# GéoMonkey - Style Guide

## 🎨 Design System

### Tokens CSS

**Toute couleur passe par un token `--gm-*`** défini dans `:root` de `assets/styles/app.css`.

#### Couleurs principales
- `--gm-green`: #00A040 (couleur primaire)
- `--gm-green-dark`: #008a37 (survol, déclinaison)
- `--gm-yellow`: #E0C020 (couleur secondaire/warning)
- `--gm-bg`: #1C1C1A (fond sombre)
- `--gm-surface`: #242422 (surface, cards)
- `--gm-text`: #F5F0D0 (texte principal)
- `--gm-white`: #FFFFFF

#### Palette de gris (higher number = darker)
- `--gm-gray-900`: #1a1a18
- `--gm-gray-800`: #242422
- `--gm-gray-700`: #333
- `--gm-gray-600`: #444
- `--gm-gray-500`: #666
- `--gm-gray-400`: #888
- `--gm-gray-300`: #999
- `--gm-gray-200`: #adb5bd
- `--gm-gray-100`: #c0c0c0

#### Surcharge Bootstrap
Toutes les variables Bootstrap sont surchargées via les tokens:
```css
--bs-primary: var(--gm-green);
--bs-primary-rgb: 0, 160, 64;
--bs-warning: var(--gm-yellow);
--bs-warning-rgb: 224, 192, 32;
--bs-body-bg: var(--gm-bg);
--bs-body-color: var(--gm-text);
```

## 🚫 Interdictions

1. **Pas de couleurs hexadécimales en dur** dans les templates ou app.css (hors :root)
2. **Pas de `style="..."` inline** dans les templates
3. **Pas de blocs `<style>`** dans les templates (sauf dépendance Twig documentée)
4. **Pas de classes Bootstrap non tokenisées** (btn-primary, btn-success, bg-light, etc.)

## ✅ Bonnes pratiques

### boutons
- Utiliser les classes tokenisées : `btn-gm-green`, `btn-gm-yellow`, `btn-gm-surface`
- Pour les boutons outline : `btn-outline-gm-green`, `btn-outline-gm-yellow`, `btn-outline-gm-gray`

### Badges
- Utiliser : `badge bg-gm-green text-gm-white`, `badge bg-gm-yellow text-gm-bg`, `badge bg-gm-danger text-gm-white`

### Alertes
- Utiliser : `alert alert-gm-info`, `alert alert-gm-warning`, `alert alert-gm-success`

### Tableaux
- Toujours utiliser `table table-gm table-striped table-hover` pour les tableaux admin
- Ne jamais utiliser `table-light`, `table-dark`, `text-light`, `text-dark`

### Cartes
- Fond : `bg-gm-surface`
- En-tête : `card-header bg-gm-surface`
- Texte : `text-gm-text`

### Formulaires
- Fond des champs : déjà géré par le CSS global
- Texte : `text-gm-text`, `text-gm-muted`
- Badges de statut : utiliser les classes tokenisées

## 📁 Structure des templates

### Layout Admin
```twig
{% extends 'admin/base.html.twig' %}

{% block admin_content %}
    {% set page_title = 'Votre titre' %}
    <div class="p-4">
        {{ breadcrumb() }}
        {# Contenu #}
    </div>
{% endblock %}
```

### Composants réutilisables
- Toutes les partielles vont dans `templates/partials/`
- Nommage : `_nom.html.twig`
- Exemple : `_picture.html.twig` pour les images de galerie

## 🎯 Menu Admin

Structure dynamique via tableau Twig dans `_sidebar.html.twig`:
```twig
{% set menu = [
    {label: 'Tableau de bord', icon: 'house', route: 'app_admin_dashboard', match: 'app_admin_dashboard'},
    {label: 'Présentation', icon: 'newspaper', route: 'app_admin_presentation_index', match: 'app_admin_presentation'},
    # ...
] %}
{% for item in menu %}
    <a class="nav-link {{ route starts with item.match ? 'active' : '' }}"
       href="{{ path(item.route) }}">
        <i class="bi bi-{{ item.icon }}"></i> {{ item.label }}
    </a>
{% endfor %}
```

## 🔧 Conventions de nommage

### Routes
- **Incohérence actuelle** : Mix de `app_admin_*` et `admin_*`
- **Nouveaux modules** : Utiliser `app_admin_*` pour homogénéité

### CSS
- Classes utilitaires : préfixer par `gm-` (ex: `text-gm-muted`)
- Noms descriptifs : `admin-shell`, `admin-sidebar`, `admin-nav`, `admin-topbar`, `admin-content`

## 📱 Responsive

- Mobile-first
- Breakpoint Bootstrap : `lg` (992px) pour la sidebar
- Sidebar devient un drawer coulissant en dessous de `lg`

## ♿ Accessibilité

- Toujours fournir des attributs `aria-label` pour les icônes seules
- Contraste suffisant : fond sombre + texte clair
- Navigation clavier : tous les éléments interactifs sont accessibles
- Focus visibles : gérés par Bootstrap

## 🔄 Maintenance

Pour ajouter un nouveau module :
1. Créer le controller et les templates dans `templates/admin/nom_module/`
2. Ajouter l'entrée dans le menu de `_sidebar.html.twig`
3. Utiliser uniquement les tokens et classes existants
4. Respecter la structure `{% extends 'admin/base.html.twig' %}`
5. Définir `{% set page_title = '...' %}`

## 🎨 Exemples

### Card avec tableau
```twig
<div class="card shadow-sm">
    <div class="card-header bg-gm-surface">
        <h5 class="mb-0"><i class="bi bi-table"></i> Liste</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-gm table-striped table-hover">
                <thead>
                    <tr>
                        <th>Colonne 1</th>
                        <th>Colonne 2</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Valeur 1</td>
                        <td><span class="badge bg-gm-green text-gm-white">Actif</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

### Formulaire
```twig
<div class="card shadow-sm">
    <div class="card-body">
        {{ form_start(form) }}
        <div class="mb-3">
            {{ form_label(form.field) }}
            {{ form_widget(form.field, {'attr': {'class': 'form-control'}}) }}
            <div class="text-gm-danger">{{ form_errors(form.field) }}</div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-gm-green">
                <i class="bi bi-check-lg"></i> Enregistrer
            </button>
            <a href="{{ path('app_admin_module_index') }}" class="btn btn-gm-surface">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
        {{ form_end(form) }}
    </div>
</div>
```
