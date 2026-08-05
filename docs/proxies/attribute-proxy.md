# AttributeProxy - Référence Technique

## Description

Proxy pour créer des attributs Eloquent qui hydratent automatiquement des objets `Transformable` (Value Objects, Records, DTOs) depuis des colonnes de base de données.

## Hiérarchie / Implémentations

```
Proxy
    └── AttributeProxy (static)
```

## Rôle principal

Faciliter la création d'attributs Eloquent qui transforment automatiquement les valeurs des colonnes en objets `Transformable` (get) et vice-versa (set). Gère la conversion JSON, la normalisation et la validation des données.

## API / Méthodes publiques

### `required(string $class, ?string $column = null, ?callable $get = null, ?callable $set = null): Attribute`

Crée un attribut **requis** (non-nullable) qui hydratera automatiquement les valeurs en objet `Transformable`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<T>` | La classe Transformable à instancier |
| `$column` | `?string` | Nom de la colonne en base de données (optionnel) |
| `$get` | `?callable` | Callable personnalisé pour le get (optionnel) |
| `$set` | `?callable` | Callable personnalisé pour le set (optionnel) |

**Retourne :** `Attribute<T, never>` - Attribut Eloquent typé

**Exceptions :** `InvalidArgumentException` - Si la classe n'implémente pas `Transformable`

**Exemple :**
```php
protected function slug(): Attribute
{
    return AttributeProxy::required(SlugVO::class, column: 'slug');
}
```

---

### `nullable(string $class, ?string $column = null, ?callable $get = null, ?callable $set = null): Attribute`

Crée un attribut **nullable** qui accepte les valeurs `null` et retournera `null` si la colonne est `null`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<T>` | La classe Transformable à instancier |
| `$column` | `?string` | Nom de la colonne en base de données (optionnel) |
| `$get` | `?callable` | Callable personnalisé pour le get (optionnel) |
| `$set` | `?callable` | Callable personnalisé pour le set (optionnel) |

**Retourne :** `Attribute<T|null, never>` - Attribut Eloquent typé nullable

**Exceptions :** `InvalidArgumentException` - Si la classe n'implémente pas `Transformable`

**Exemple :**
```php
protected function coordinates(): Attribute
{
    return AttributeProxy::nullable(CoordinatesVO::class, column: 'coordinates');
}
```

---

### `make(string $class, bool $nullable = false, ?string $column = null, ?callable $get = null, ?callable $set = null): Attribute`

> **⚠️ DEPRECATED** - Utiliser `required()` ou `nullable()` à la place.

Méthode héritée pour compatibilité ascendante.

---

## Cas d'utilisation

### Cas 1 : Attribut slug (string → SlugVO)

**Problème :** Stocker des slugs en base de données sous forme de chaîne, mais les manipuler en tant que `SlugVO` dans le code métier.

**Solution :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = ['title', 'slug'];

    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(SlugVO::class, column: 'slug');
    }
}

// Utilisation
$article = new Article();
$article->slug = 'My Awesome Article';  // Automatiquement converti en SlugVO
echo $article->slug->getValue();        // 'my-awesome-article'
```

---

### Cas 2 : Attribut coordonnées géographiques (JSON → CoordinatesVO)

**Problème :** Stocker des coordonnées en JSON en base, mais les manipuler en tant que `CoordinatesVO` dans le code métier.

**Solution :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $fillable = ['name', 'coordinates'];

    protected $casts = [
        'coordinates' => 'array',
    ];

    protected function coordinates(): Attribute
    {
        return AttributeProxy::required(CoordinatesVO::class, column: 'coordinates');
    }
}

// Utilisation
$place = new Place();
$place->coordinates = ['latitude' => 48.8566, 'longitude' => 2.3522];
echo $place->coordinates->getLatitude()->getValue(); // 48.8566
```

---

### Cas 3 : Attribut avec transformation personnalisée

**Problème :** Appliquer une transformation spécifique avant l'hydratation.

**Solution :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(
            SlugVO::class,
            column: 'slug',
            get: function ($value, $attributes) {
                // Transformation personnalisée avant hydratation
                return strtolower(trim($value));
            },
            set: function ($value) {
                // Transformation personnalisée avant stockage
                return ['slug' => strtolower(trim($value))];
            }
        );
    }
}
```

---

## Flux d'exécution

### Getter (base de données → objet)
```
Colonne DB → rawValue
    ↓
JSON ? → json_decode
    ↓
null ET nullable ? → return null
    ↓
TransformableProxy::make()
    ↓
Objet Transformable
```

### Setter (objet → base de données)
```
Valeur → Transformable?
    ↓
Oui → NormalizerChain::normalize()
    ↓
Array/Objet ? → json_encode
    ↓
[$column => $value]
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Classe n'implémente pas Transformable | `InvalidArgumentException` | `Class X must implement Transformable interface.` |
| Set défini sans colonne | `InvalidArgumentException` | `You must specify a column name when defining a set callback.` |
| Valeur null non nullable | `InvalidArgumentException` | `Value cannot be null for X` |

---

## Intégration

### Avec TransformableProxy
`AttributeProxy` utilise `TransformableProxy` en interne pour l'hydratation des objets.

```php
return TransformableProxy::make($class, $rawValue, $nullable);
```

### Avec NormalizerChain
`AttributeProxy` utilise `NormalizerChain` pour normaliser les objets avant stockage.

```php
$normalized = NormalizerChain::get()->normalize($transformed);
```

### Avec Eloquent
`AttributeProxy` retourne une instance de `Illuminate\Database\Eloquent\Casts\Attribute`, compatible avec les modèles Eloquent.

---

## Performance

- **Get** : O(1) - Une seule vérification JSON + instanciation
- **Set** : O(n) - Normalization via `NormalizerChain` (dépend de la complexité de l'objet)
- **Cache** : Aucun cache interne, les attributs sont recréés à chaque accès
- **Recommandation** : Pour les objets complexes, envisager un cache applicatif

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'coordinates',
    ];

    protected $casts = [
        'coordinates' => 'array',
    ];

    // Attribut nullable (slug)
    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(SlugVO::class, column: 'slug');
    }

    // Attribut required (coordinates)
    protected function coordinates(): Attribute
    {
        return AttributeProxy::required(CoordinatesVO::class, column: 'coordinates');
    }

    // Attribut nullable avec callback personnalisé
    protected function customSlug(): Attribute
    {
        return AttributeProxy::nullable(
            SlugVO::class,
            column: 'slug',
            get: function ($value, $attributes) {
                // Force lowercase before hydration
                return strtolower(trim($value));
            },
            set: function ($value) {
                // Force lowercase before storage
                return ['slug' => strtolower(trim($value))];
            }
        );
    }
}

// Utilisation complète
$post = new Post();
$post->title = 'My Article';
$post->slug = 'My Article';  // 'my-article'
$post->coordinates = ['latitude' => 48.8566, 'longitude' => 2.3522];

$post->save();

$found = Post::find($post->id);
echo $found->slug->getValue();           // 'my-article'
echo $found->coordinates->getLatitude()->getValue(); // 48.8566
```

---

## Voir aussi

- [`TransformableProxy`](TransformableProxy.md) - Proxy pour créer des objets Transformable
- [`Transformable`](../../DomainStructures/Interfaces/Transformable.md) - Interface Transformable
- [`NormalizerChain`](../../DomainStructures/Normalizers/NormalizerChain.md) - Chaîne de normalisation
- [Proxies - Vue d'ensemble](../Proxies.md) - Documentation des proxies