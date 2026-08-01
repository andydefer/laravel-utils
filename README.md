# Laravel Utils

[![Latest Version](https://img.shields.io/packagist/v/andydefer/laravel-utils.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-utils)
[![Total Downloads](https://img.shields.io/packagist/dt/andydefer/laravel-utils.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-utils)
[![PHP Version](https://img.shields.io/packagist/php-v/andydefer/laravel-utils.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-utils)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-utils.svg?style=flat-square)](https://packagist.org/packages/andydefer/laravel-utils)

## Description

Package d'utilitaires pour Laravel offrant des proxies pour l'hydratation automatique d'objets `Transformable` (Value Objects, Records, DTOs) depuis des sources variées (tableaux, JSON, colonnes de base de données).

## Installation

```bash
composer require andydefer/laravel-utils
```

## Prérequis

- PHP 8.1 ou supérieur
- Laravel 10.x, 11.x ou 12.x
- `andydefer/domain-structures` ^1.0

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

## Documentation

- [TransformableProxy - Référence Technique](docs/TransformableProxy.md)
- [AttributeProxy - Référence Technique](docs/AttributeProxy.md)

## Tests

```bash
composer test
```

## Contribuer

1. Forker le projet
2. Créer une branche (`git checkout -b feature/ma-fonctionnalite`)
3. Commiter les changements (`git commit -m 'Ajout de ma fonctionnalité'`)
4. Pusher (`git push origin feature/ma-fonctionnalite`)
5. Ouvrir une Pull Request

## Licence

MIT © [Andy Defer](https://github.com/andydefer)

## Auteur

- **Andy Defer** - [GitHub](https://github.com/andydefer)

## Dépendances

- `andydefer/domain-structures` - Interfaces et classes de base pour les objets transformables
- `illuminate/database` - Pour les attributs Eloquent
---