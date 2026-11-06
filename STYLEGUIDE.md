# GéoMonkey Style Guide

## 🎨 Design System

**Theme**: Dark theme mandatory everywhere
- Background: `--gm-bg` (#1C1C1A)
- Surface: `--gm-surface` (#242422)
- Text: `--gm-text` (#F5F0D0)
- Primary: `--gm-green` (#00A040)
- Warning: `--gm-yellow` (#E0C020)

## 📜 Rules

### 1. **Colors - Token First**
✅ **DO**: Use CSS custom properties (tokens) for ALL colors
```css
/* In app.css :root */
--gm-bg: #1C1C1A;
--gm-surface: #242422;
--gm-text: #F5F0D0;

/* In HTML/Twig */
<div class="bg-gm-surface text-gm-text">...</div>
```

❌ **DON'T**: Use hardcoded hex colors or Bootstrap color classes
```css
/* BAD - Hardcoded hex */
color: #333;

/* BAD - Bootstrap color classes */
<div class="bg-dark text-white">...</div>
```

### 2. **Styles - Classes Only**
✅ **DO**: Use utility classes from app.css
```html
<div class="max-w-800 max-h-150 rounded-circle-80">...</div>
```

❌ **DON'T**: Use inline styles
```html
<!-- BAD -->
<div style="max-width: 800px; height: 150px;">...</div>
```

### 3. **Exceptions**
✅ **ALLOWED**: `<style>` blocks in templates ONLY when they require Twig variables
```twig
{# front/base.html.twig - Hero background image #}
<style>
    .hero-section {
        background-image: url('{{ asset("images/background.jpg") }}');
    }
</style>
```

### 4. **Bootstrap Overrides**
✅ **DO**: Customize Bootstrap via CSS variables
```css
:root {
    --bs-primary: var(--gm-green);
    --bs-warning: var(--gm-yellow);
    --bs-body-bg: var(--gm-bg);
    --bs-body-color: var(--gm-text);
}
```

❌ **DON'T**: Override Bootstrap with !important or inline styles

## 🗂️ File Structure

```
assets/styles/
├── app.css          # All design tokens and utility classes

templates/
├── partials/        # Reusable components (_picture.html.twig, etc.)
│
├── front/           # Public-facing templates
│   ├── base.html.twig
│   ├── security/     # Login, registration, password reset
│   ├── membership/   # Membership flows
│   └── ...
│
└── admin/           # Admin templates
    ├── base.html.twig    # Autonomous (no front/ inheritance)
    ├── partials/
    │   ├── _sidebar.html.twig  # Dynamic menu with route matching
    │   ├── _topbar.html.twig   # Sticky: title + breadcrumb + back button
    │   └── _footer.html.twig
    └── ...
```

## 🏗️ Component Patterns

### Admin Layout (All Pages)
```twig
{# templates/admin/base.html.twig #}
<div class="admin-shell">
    <aside class="admin-sidebar">
        {% include 'admin/partials/_sidebar.html.twig' %}
    </aside>
    <div class="admin-main">
        {% include 'admin/partials/_topbar.html.twig' %}
        <div class="admin-content">
            {% block admin_content %}{% endblock %}
        </div>
        {% include 'admin/partials/_footer.html.twig' %}
    </div>
</div>
```

### Sidebar Menu
Dynamic active state based on current route:
```twig
{% set route = app.request.attributes.get('_route') %}
{% set menu = [
    {label: 'Tableau de bord', icon: 'house', route: 'app_admin_dashboard', match: 'app_admin_dashboard'},
    {label: 'Blog', icon: 'newspaper', route: 'app_admin_blog_index', match: 'app_admin_blog'},
    # Future modules (commented):
    {# {label: 'Boutique', icon: 'shop', route: '#', match: 'app_admin_shop'} #}
] %}
{% for item in menu %}
    <a class="nav-link {{ route starts with item.match ? 'active bg-gm-warning text-gm-bg' : 'text-gm-warning' }}"
       href="{{ path(item.route) }}">
        <i class="bi bi-{{ item.icon }}"></i> {{ item.label }}
    </a>
{% endfor %}
```

### Forms
- Use `form-control` classes (already styled for dark theme in app.css)
- Input groups with icons: use `bg-gm-light text-gm-text` for the icon container
- Error messages: use `text-gm-danger`

## 📝 Naming Conventions

### Routes
**Current inconsistency**: `app_admin_*` vs `admin_*`
- **New modules**: Use `app_admin_*` prefix for consistency
- **Existing**: Document both patterns, prefer `app_admin_*` for new code

### CSS Classes
- **Tokens**: `--gm-*` (colors, spacing)
- **Utilities**: `.max-w-800`, `.min-w-200`, `.h-150`, `.rounded-circle-80`
- **Components**: `.admin-shell`, `.admin-sidebar`, `.admin-topbar`, `.admin-content`
- **Bootstrap**: Keep standard Bootstrap classes (btn, card, etc.)

### Images
- **Avatar/logo**: Use `.img-avatar` (60x60px, rounded)
- **Cover images**: Use `.img-cover` (object-fit: cover)
- **Thumbnails**: Use `.rounded-circle-80` (80x80px, rounded)

## 🔮 Future Modules

### Menu Placement
Add new admin modules to `_sidebar.html.twig` menu array:
```twig
{label: 'Boutique', icon: 'shop', route: 'app_admin_shop_index', match: 'app_admin_shop'},
{label: 'Billetterie', icon: 'ticket-perforated', route: 'app_admin_ticket_index', match: 'app_admin_ticket'},
```

### Search Slot
Reserved in `_topbar.html.twig` for future global search (Ctrl+K):
```twig
<div class="search-slot min-w-200"></div>
```

### Reusable Components
- **Tables**: See `admin/gallery_image/index.html.twig` for canonical pattern
- **Cards**: Consistent styling across all list views
- **Buttons**: Use Bootstrap button classes with token colors

## ✅ Checklist Before PR

- [ ] No hardcoded hex colors outside `:root` in app.css
- [ ] No inline `style="..."` attributes in templates
- [ ] No `bg-dark`, `bg-light`, `text-white`, `text-dark`, `text-muted` (use token classes)
- [ ] All form controls use dark theme styles from app.css
- [ ] Admin pages extend `admin/base.html.twig`
- [ ] Public pages extend `front/base.html.twig`
- [ ] New reusable partials go in `templates/partials/`
- [ ] Future modules added as commented entries in sidebar menu
