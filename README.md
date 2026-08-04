# Laravel Utils

## Table des matières

1. [Description](#description)
2. [Installation](#installation)
3. [Prérequis](#prérequis)
4. [Fonctionnalités](#fonctionnalités)
   - [4.1 TransformableProxy](#transformableproxy)
   - [4.2 AttributeProxy](#attributeproxy)
   - [4.3 GitPushDirective](#gitpushdirective)
   - [4.4 GitDiffDirective](#gitdiffdirective)
   - [4.5 GitTagDirective](#gittagdirective)
5. [Configuration](#configuration)
   - [5.1 Configuration des dépôts Git](#configuration-des-dépôts-git)
   - [5.2 Configuration des extensions](#configuration-des-extensions)
6. [Documentation](#documentation)
7. [Tests](#tests)
8. [Contribuer](#contribuer)
9. [Licence](#licence)
10. [Auteur](#auteur)
11. [Dépendances](#dépendances)

---

## Description

Package d'utilitaires pour Laravel offrant des proxies pour l'hydratation automatique d'objets `Transformable` (Value Objects, Records, DTOs) depuis des sources variées (tableaux, JSON, colonnes de base de données) ainsi que des directives CLI pour automatiser les workflows Git (push, génération de diff pour revue IA, et versionnement sémantique).

---

## Installation

```bash
composer require andydefer/laravel-utils
```

---

## Prérequis

- PHP 8.1 ou supérieur
- Laravel 10.x, 11.x, 12.x, 13.x, 14.x ou 15.x
- `andydefer/domain-structures` ^1.0
- Git (pour les directives CLI)
- VS Code (optionnel, pour l'ouverture automatique des fichiers)

---

## Fonctionnalités

### TransformableProxy

Proxy statique pour créer des objets `Transformable` depuis diverses sources :

```php
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use App\ValueObjects\SlugVO;

// Depuis une chaîne
$slug = TransformableProxy::make(SlugVO::class, 'mon-slug');

// Depuis un tableau
$user = TransformableProxy::make(UserRecord::class, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Depuis du JSON
$product = TransformableProxy::make(ProductRecord::class, '{"name":"Laptop","price":999}');

// Avec nullable
$coordinates = TransformableProxy::make(CoordinatesVO::class, null, nullable: true);
```

---

### AttributeProxy

Proxy pour créer des attributs Eloquent qui hydratent automatiquement des objets `Transformable` :

```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\SlugVO;
use App\ValueObjects\CoordinatesVO;
use App\Records\SettingsRecord;

class User extends Model
{
    // Attribut simple
    protected function slug(): Attribute
    {
        return AttributeProxy::make(SlugVO::class);
    }

    // Attribut nullable
    protected function coordinates(): Attribute
    {
        return AttributeProxy::nullable(CoordinatesVO::class);
    }

    // Attribut avec colonne différente
    protected function settings(): Attribute
    {
        return AttributeProxy::make(SettingsRecord::class, column: 'metadata');
    }
}

// Utilisation
$user = User::find(1);
echo $user->slug->value;        // 'john-doe'
echo $user->coordinates->lat;   // 48.8566
echo $user->settings->theme;    // 'dark'
```

---

### GitPushDirective

Directive CLI pour pousser du code vers des dépôts Git distants configurés avec mode interactif et options avancées.

```bash
# Mode interactif (demande le message, les cibles et les dossiers)
./bin/afya ugp

# Push avec simulation (dry-run)
./bin/afya ugp [github] --dry-run <message="Fix bug">

# Push avec force-with-lease
./bin/afya ugp [github] --force-with-lease <message="Hotfix">

# Push sans tests
./bin/afya ugp [github, o2switch] --no-tests <message="Feature: Ajout API">
```

**Paramètres :**

| Paramètre | Description |
|-----------|-------------|
| `{sources*}` | Alias des dépôts configurés (vide = push vers tous) |
| `{folders*}` | Dossiers à ajouter (vide = tous les fichiers) |
| `--no-tests` | Ignorer l'exécution des tests |
| `--force-with-lease` | Utiliser `--force-with-lease` au lieu de `--force` |
| `--force` | Forcer le push même si les tests échouent |
| `--no-interactive` | Désactiver le mode interactif |
| `--dry-run` | Simuler l'opération sans rien exécuter |

---

### GitDiffDirective

Directive CLI pour générer un diff Git formaté pour la revue de code par IA.

```bash
# Génération simple (interactif)
./bin/afya ugd

# Génération non-interactive avec chemins et extensions
./bin/afya ugd [src, tests] [.php, .js] --no-interactive

# Utilisation des recettes d'extensions
./bin/afya ugd --frontend                # Frontend uniquement
./bin/afya ugd --backend                 # Backend uniquement
./bin/afya ugd --recipes                 # Sélection interactive des recettes

# Simulation (dry-run)
./bin/afya ugd [src] --dry-run

# Génération avec résumé de travail
./bin/afya ugd [src] --with-summary
```

**Paramètres :**

| Paramètre | Description |
|-----------|-------------|
| `{paths*}` | Chemins à inclure (ex: `[src, tests]`) |
| `{extensions*}` | Extensions à filtrer (ex: `[.php, .js]`) |
| `--frontend` | Utiliser les extensions frontend de la config |
| `--backend` | Utiliser les extensions backend de la config |
| `--recipes` | Sélectionner les recettes d'extensions interactivement |
| `--with-summary` | Créer un résumé de travail après le diff |
| `--no-interactive` | Désactiver le mode interactif |
| `--dry-run` | Simuler l'opération sans écrire de fichier |

**Recettes d'extensions par défaut :**

| Recette | Extensions |
|---------|------------|
| `frontend` | js, ts, tsx, jsx, vue, css, scss, sass, less, html, xml |
| `backend` | php, py, rb, go, rs, java, c, cpp, h, hpp |

---

### GitTagDirective

Directive CLI pour créer des tags de version Git avec versioning sémantique (SemVer).

```bash
# Création d'un tag patch (défaut)
./bin/afya ugt

# Création d'un tag minor
./bin/afya ugt minor

# Création d'un tag major
./bin/afya ugt major

# Re-publication du dernier tag
./bin/afya ugt --republish

# Tag avec message personnalisé et simulation
./bin/afya ugt patch --dry-run <message="Release v1.0.1">

# Tag sans push automatique
./bin/afya ugt minor --no-push
```

**Paramètres :**

| Paramètre | Description |
|-----------|-------------|
| `{type}` | Type de tag : `patch`, `minor`, `major` (défaut: patch) |
| `--no-push` | Ne pas pousser le tag vers le dépôt distant |
| `--republish` | Re-publier le dernier tag (force push) |
| `--dry-run` | Simuler l'opération sans rien exécuter |
| `{message}` | Message personnalisé pour le tag |

**Fonctionnalités :**
- Versionnement sémantique automatique (SemVer)
- Message personnalisé pour les tags
- Simulation (dry-run) sans effectuer de modifications
- Re-publication forcée des tags existants
- Gestion des erreurs et des cas limites
- Interface utilisateur enrichie avec couleurs et icônes

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Configuration:

🏷️  Type        minor
📦 Last tag     v0.1.0
🆕 New tag      v0.2.0
💬 Message      Version 0.2.0 - Ajout de nouvelles fonctionnalités
📤 Push         ✅ Yes

📦 Creating tag: v0.2.0

✅ Tag created: v0.2.0

📤 Pushing tag to remote...

✅ Tag pushed: v0.2.0

✅ Tag operation completed successfully!
```

---

## Configuration

### Publication de la configuration

```bash
php artisan vendor:publish --tag=utils-config
```

### Configuration des dépôts Git

```php
// config/utils.php
return [
    'repositories' => [
        'github' => 'git@github.com:andydefer/afya-medical.git',
        'o2switch' => 'ssh://user@domain.com/home/user/git/repo.git',
    ],
];
```

### Configuration des extensions

```php
// config/utils.php
return [
    // Extensions par défaut pour le diff
    'default_extensions' => ['php', 'js', 'ts', 'css', 'html', 'json', 'yaml', 'md'],
    
    // Recettes d'extensions
    'extension_recipes' => [
        'frontend' => ['js', 'ts', 'tsx', 'jsx', 'vue', 'css', 'scss', 'sass', 'less', 'html', 'xml'],
        'backend' => ['php', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'h', 'hpp'],
        'fullstack' => ['php', 'js', 'ts', 'tsx', 'jsx', 'vue', 'css', 'scss', 'html'],
    ],
];
```

### Configuration des tags Git

```php
// config/utils.php
return [
    // ... autres configurations ...
    
    // Les tags utilisent les paramètres Git par défaut
    // Aucune configuration spécifique n'est requise
];
```

---

## Documentation

- [TransformableProxy - Référence Technique](docs/api-reference/transformable-proxy.md)
- [AttributeProxy - Référence Technique](docs/api-reference/attribute-proxy.md)
- [GitPushDirective - Référence Technique](docs/api-reference/git-push-directive.md)
- [GitDiffDirective - Référence Technique](docs/api-reference/git-diff-directive.md)
- [GitTagDirective - Référence Technique](docs/api-reference/git-tag-directive.md)

---

## Tests

```bash
composer test
```

---

## Contribuer

1. Forker le projet
2. Créer une branche (`git checkout -b feature/ma-fonctionnalite`)
3. Commiter les changements (`git commit -m 'Ajout de ma fonctionnalité'`)
4. Pusher (`git push origin feature/ma-fonctionnalite`)
5. Ouvrir une Pull Request

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)

---

## Auteur

- **Andy Defer** - [GitHub](https://github.com/andydefer)

---

## Dépendances

- `andydefer/domain-structures` - Interfaces et classes de base pour les objets transformables
- `illuminate/database` - Pour les attributs Eloquent
- `symfony/process` - Pour l'exécution des commandes Git
- `andydefer/laravel-directive` - Pour l'infrastructure des directives CLI
- `andydefer/console-writer` - Pour l'interface utilisateur en CLI