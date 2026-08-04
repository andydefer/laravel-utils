# Laravel Utils

## Table des matières

1. [Description](#description)
2. [Installation](#installation)
3. [Prérequis](#prérequis)
4. [Fonctionnalités](#fonctionnalités)
   - [4.1 TransformableProxy](#transformableproxy)
   - [4.2 AttributeProxy](#attributeproxy)
   - [4.3 GitPushDirective](#gitpushdirective)
5. [Configuration](#configuration)
   - [5.1 Configuration des dépôts Git](#configuration-des-dépôts-git)
6. [Documentation](#documentation)
7. [Tests](#tests)
8. [Contribuer](#contribuer)
9. [Licence](#licence)
10. [Auteur](#auteur)
11. [Dépendances](#dépendances)

---

## Description

Package d'utilitaires pour Laravel offrant des proxies pour l'hydratation automatique d'objets `Transformable` (Value Objects, Records, DTOs) depuis des sources variées (tableaux, JSON, colonnes de base de données) ainsi qu'une directive CLI pour automatiser les pushes Git vers des dépôts distants.

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
- `pngquant` et `jpegoptim` pour la compression CLI (optionnel)

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

## Configuration

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

### Publication de la configuration

```bash
php artisan vendor:publish --tag=utils-config
```

---

## Documentation

- [TransformableProxy - Référence Technique](docs/TransformableProxy.md)
- [AttributeProxy - Référence Technique](docs/AttributeProxy.md)
- [GitPushDirective - Référence Technique](docs/GitPushDirective.md)

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
---