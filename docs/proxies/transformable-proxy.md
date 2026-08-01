# TransformableProxy - Référence Technique

## Description

Proxy statique facilitant la création d'objets implémentant l'interface `Transformable` (Value Objects, Records, DTOs) à partir de sources variées.

## Hiérarchie

```
TransformableProxy
```

## Rôle principal

Agit comme un point d'entrée unifié pour l'hydratation d'objets `Transformable`. Il détecte automatiquement le type de la source (string JSON, tableau, scalaire) et délègue à la méthode appropriée (`from()` ou `fromJson()`) de la classe cible.

---

## API

### `make(string $class, mixed $value, bool $nullable = false): mixed`

Crée une instance de la classe cible à partir de la valeur fournie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<T>` | La classe cible (doit implémenter `Transformable`) |
| `$value` | `mixed` | La source de données (string, array, int, etc.) |
| `$nullable` | `bool` | Si `true`, retourne `null` quand `$value` est `null` |

**Retourne :** `T|null` - Instance de la classe cible ou `null` si `$nullable` est `true` et `$value` est `null`

**Exceptions :**
- `InvalidArgumentException` - Si la classe n'implémente pas `Transformable`
- `InvalidArgumentException` - Si `$value` est `null` et `$nullable` est `false`

**Exemple :**
```php
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use App\ValueObjects\EmailAddress;

// Depuis une chaîne
$email = TransformableProxy::make(EmailAddress::class, 'john@example.com');

// Depuis du JSON
$user = TransformableProxy::make(UserRecord::class, '{"name":"John","age":30}');

// Nullable
$email = TransformableProxy::make(EmailAddress::class, null, nullable: true);
```

---

## Flux d'exécution

```
$value
    ├── null + nullable = true → retourne null
    ├── null + nullable = false → InvalidArgumentException
    ├── string + JSON valide → $class::fromJson($value)
    └── autre → $class::from($value)
```

---

## Cas d'utilisation

### Cas 1 : Hydratation depuis une chaîne

```php
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use App\ValueObjects\SlugVO;

$slug = TransformableProxy::make(SlugVO::class, 'mon-article-slug');
// $slug->value === 'mon-article-slug'
```

### Cas 2 : Hydratation depuis un tableau

```php
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use App\Records\UserRecord;

$user = TransformableProxy::make(UserRecord::class, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
]);
// $user->name === 'John Doe'
// $user->email === 'john@example.com'
// $user->age === 30
```

### Cas 3 : Hydratation depuis du JSON

```php
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use App\Records\ProductRecord;

$json = '{"name":"Laptop","price":999.99,"in_stock":true}';
$product = TransformableProxy::make(ProductRecord::class, $json);
// $product->name === 'Laptop'
// $product->price === 999.99
// $product->in_stock === true
```

### Cas 4 : Gestion des valeurs null

```php
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use App\ValueObjects\CoordinatesVO;

// Avec nullable = true
$coordinates = TransformableProxy::make(CoordinatesVO::class, null, nullable: true);
// $coordinates === null

// Avec nullable = false
try {
    $coordinates = TransformableProxy::make(CoordinatesVO::class, null);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage();
    // 'Value cannot be null for CoordinatesVO. Use nullable=true if null is allowed.'
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| `$value` est `null` et `$nullable` est `false` | `InvalidArgumentException` | `Value cannot be null for {class}. Use nullable=true if null is allowed.` |
| La classe n'implémente pas `Transformable` | `InvalidArgumentException` | `Class {class} must implement Transformable interface.` |
| `$class::from()` lève une exception | `InvalidArgumentException` | Message personnalisé de la classe cible |
| `$class::fromJson()` lève une exception | `InvalidArgumentException` | Message personnalisé de la classe cible |

---

## Intégration

- **`AttributeProxy`** : Utilise ce proxy pour les attributs Eloquent
- **`HydrationService`** : Alternative pour l'hydratation avancée
- **`Transformable`** : Interface que doivent implémenter les classes cibles

---

## Performance

- **Détection JSON :** O(n) où n est la longueur de la chaîne
- **Appel dynamique :** `$class::from()` ou `$class::fromJson()` (appel statique)
- **Pas de cache :** Chaque appel est indépendant

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Traits\Hydratable;
use AndyDefer\DomainStructures\Interfaces\Transformable;

// Définition d'un Record Transformable
final class UserRecord extends AbstractRecord implements Transformable
{
    use Hydratable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly int $age,
    ) {}
}

// Définition d'un Value Object Transformable
final class EmailAddress implements Transformable
{
    public function __construct(public readonly string $value) {}

    public static function from(mixed $source): static
    {
        if (is_string($source)) {
            return new self($source);
        }
        throw new InvalidArgumentException('Email must be a string');
    }

    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['email'])) {
            throw new InvalidArgumentException('Invalid JSON for Email');
        }
        return new self($data['email']);
    }

    public static function collect(iterable $sources, string $collectionClass = Sequential::class)
    {
        // ...
    }
}

// ==================== UTILISATION ====================

// 1. Depuis un tableau
$user = TransformableProxy::make(UserRecord::class, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
]);

// 2. Depuis du JSON
$json = '{"name":"Jane Doe","email":"jane@example.com","age":25}';
$user2 = TransformableProxy::make(UserRecord::class, $json);

// 3. Depuis une chaîne simple
$email = TransformableProxy::make(EmailAddress::class, 'john@example.com');

// 4. Depuis du JSON pour un VO
$emailJson = '{"email":"jane@example.com"}';
$email2 = TransformableProxy::make(EmailAddress::class, $emailJson);

// 5. Avec nullable
$email3 = TransformableProxy::make(EmailAddress::class, null, nullable: true);
// $email3 === null

// 6. Gestion d'erreur
try {
    $invalid = TransformableProxy::make(EmailAddress::class, ['invalid']);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(); // 'Email must be a string'
}
```

---

## Voir aussi

- `AttributeProxy` - Proxy pour les attributs Eloquent
- `Transformable` - Interface des objets transformables
- `HydrationService` - Service d'hydratation avancé
- `AbstractRecord` - Classe de base pour les Records
- `AbstractValueObject` - Classe de base pour les Value Objects