# AttributeProxy - Référence Technique

## Description

Proxy facilitant la création d'attributs Eloquent qui hydratent automatiquement des objets `Transformable` (Value Objects, Records, DTOs) à partir des colonnes de la base de données.

## Hiérarchie

```
AttributeProxy
```

## Rôle principal

Permet de déclarer des attributs Eloquent qui transforment automatiquement les valeurs des colonnes en objets `Transformable`. Il délègue à `TransformableProxy` l'hydratation réelle.

---

## API

### `make(string $class, bool $nullable = false, ?string $column = null): Attribute`

Crée un attribut Eloquent qui hydrate un objet `Transformable`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<T>` | La classe cible (doit implémenter `Transformable`) |
| `$nullable` | `bool` | Si `true`, retourne `null` quand la valeur est `null` |
| `$column` | `string|null` | Nom de la colonne (par défaut, le nom de la méthode) |

**Retourne :** `Attribute<T|null, never>` - Attribut Eloquent

**Exceptions :** `InvalidArgumentException` - Si la classe n'implémente pas `Transformable`

**Exemple :**
```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\SlugVO;

class Article extends Model
{
    protected function slug(): Attribute
    {
        return AttributeProxy::make(SlugVO::class);
    }
}

$article = Article::find(1);
echo $article->slug->value; // 'mon-article-slug'
```

---

### `nullable(string $class, ?string $column = null): Attribute`

Version simplifiée de `make()` avec `$nullable = true`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<T>` | La classe cible (doit implémenter `Transformable`) |
| `$column` | `string|null` | Nom de la colonne (par défaut, le nom de la méthode) |

**Retourne :** `Attribute<T|null, never>` - Attribut Eloquent avec support des valeurs null

**Exemple :**
```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\CoordinatesVO;

class Hospital extends Model
{
    protected function coordinates(): Attribute
    {
        return AttributeProxy::nullable(CoordinatesVO::class);
    }
}

$hospital = Hospital::find(1);
$coordinates = $hospital->coordinates; // CoordinatesVO|null
```

---

## Flux d'exécution

```
$attributes[$column] ou $value
    ├── null + nullable = true → retourne null
    ├── null + nullable = false → TransformableProxy::make() → Exception
    └── autre → TransformableProxy::make($class, $rawValue, $nullable)
```

---

## Cas d'utilisation

### Cas 1 : Attribut simple avec Value Object

```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\SlugVO;

class Article extends Model
{
    protected function slug(): Attribute
    {
        return AttributeProxy::make(SlugVO::class);
    }
}

// Création
$article = Article::create([
    'title' => 'Mon article',
    'slug' => 'mon-article-slug',
]);

// Lecture
$slug = $article->slug; // SlugVO
echo $slug->value; // 'mon-article-slug'

// Mise à jour
$article->slug = new SlugVO('nouveau-slug');
$article->save();
```

### Cas 2 : Attribut nullable

```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\CoordinatesVO;

class Hospital extends Model
{
    protected function coordinates(): Attribute
    {
        return AttributeProxy::nullable(CoordinatesVO::class);
    }
}

// Avec valeur
$hospital = Hospital::create([
    'name' => 'Hôpital Central',
    'coordinates' => '{"lat":48.8566,"lng":2.3522}',
]);

$coords = $hospital->coordinates; // CoordinatesVO

// Sans valeur
$hospital2 = Hospital::create([
    'name' => 'Hôpital Secondaire',
    'coordinates' => null,
]);

$coords2 = $hospital2->coordinates; // null
```

### Cas 3 : Colonne différente du nom de la méthode

```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\EmailVO;

class User extends Model
{
    protected function email(): Attribute
    {
        return AttributeProxy::make(EmailVO::class, column: 'email_address');
    }
}

$user = User::create([
    'name' => 'John Doe',
    'email_address' => 'john@example.com',
]);

echo $user->email->value; // 'john@example.com'
```

### Cas 4 : Attribut avec Record

```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\Records\SettingsRecord;

class User extends Model
{
    protected $casts = [
        'metadata' => 'array',
    ];

    protected function settings(): Attribute
    {
        return AttributeProxy::make(SettingsRecord::class, column: 'metadata');
    }
}

$user = User::create([
    'name' => 'John Doe',
    'metadata' => [
        'theme' => 'dark',
        'notifications' => true,
        'language' => 'fr',
    ],
]);

$settings = $user->settings; // SettingsRecord
echo $settings->theme; // 'dark'
echo $settings->language; // 'fr'
```

### Cas 5 : Attribut nullable avec colonne différente

```php
use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use App\ValueObjects\ProfileVO;

class Doctor extends Model
{
    protected function profile(): Attribute
    {
        return AttributeProxy::nullable(ProfileVO::class, column: 'profile_data');
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| La classe n'implémente pas `Transformable` | `InvalidArgumentException` | `Class {class} must implement Transformable interface.` |
| La valeur ne peut pas être hydratée | `InvalidArgumentException` | Message personnalisé de la classe cible |

---

## Intégration

- **`TransformableProxy`** : Délègue l'hydratation réelle
- **`Model`** : Utilisé comme attribut dans les modèles Eloquent
- **`Transformable`** : Interface que doivent implémenter les classes cibles

---

## Performance

- **Hydratation :** Effectuée uniquement lors de l'accès à l'attribut
- **Pas de cache :** L'objet est recréé à chaque accès
- **Utilisation :** Idéal pour les attributs rarement accédés ou les petites structures

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| Laravel 11+ | ✅ Complet |
| Laravel 12+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use Illuminate\Database\Eloquent\Model;

// ==================== VALUE OBJECTS ====================

final class SlugVO extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        if (empty($value) && $value !== '') {
            throw new InvalidArgumentException('Slug cannot be empty');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

final class CoordinatesVO extends AbstractValueObject
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng
    ) {}

    public function getValue(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }
}

// ==================== RECORD ====================

final class SettingsRecord extends AbstractRecord
{
    use Hydratable;

    public function __construct(
        public readonly string $theme,
        public readonly bool $notifications,
        public readonly string $language,
    ) {}
}

// ==================== MODÈLE ====================

final class User extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'slug', 'coordinates', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

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

// ==================== UTILISATION ====================

// Création
$user = User::create([
    'name' => 'John Doe',
    'slug' => 'john-doe',
    'coordinates' => '{"lat":48.8566,"lng":2.3522}',
    'metadata' => [
        'theme' => 'dark',
        'notifications' => true,
        'language' => 'fr',
    ],
]);

// Lecture
echo $user->slug->value; // 'john-doe'
echo $user->coordinates->lat; // 48.8566
echo $user->settings->theme; // 'dark'
echo $user->settings->language; // 'fr'

// Mise à jour
$user->slug = new SlugVO('john-doe-updated');
$user->coordinates = TransformableProxy::make(
    CoordinatesVO::class,
    ['lat' => 51.5074, 'lng' => -0.1278]
);
$user->save();

// Récupération fraîche
$fresh = User::find($user->id);
echo $fresh->slug->value; // 'john-doe-updated'
echo $fresh->coordinates->lat; // 51.5074

// Accès null
$user2 = User::create(['name' => 'Jane Doe']);
var_dump($user2->coordinates); // null
```

---

## Voir aussi

- `TransformableProxy` - Proxy d'hydratation sous-jacent
- `Transformable` - Interface des objets transformables
- `AbstractValueObject` - Classe de base pour les Value Objects
- `AbstractRecord` - Classe de base pour les Records
- `HydrationService` - Service d'hydratation avancé