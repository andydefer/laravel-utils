# GitTagDirective - Référence Technique

## Description

Directive CLI permettant de créer des tags de version Git basés sur le versioning sémantique (SemVer).

## Hiérarchie

```
AbstractDirective
    └── GitTagDirective
```

## Rôle principal

Automatise la création de tags de version en incrémentant automatiquement les numéros de version selon la convention SemVer (patch, minor, major). La directive gère également la publication des tags vers le dépôt distant et offre des fonctionnalités de re-publication forcée.

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la commande pour le système de directives.

**Retourne :** `string` - Signature de la commande

**Exemple :**
```php
$directive = new GitTagDirective();
$signature = $directive->getSignature();
// 'utils:git-tag ::type->[patch,minor,major]=?#"Tag type..." ...'
```

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la commande.

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
$directive = new GitTagDirective();
$aliases = $directive->getAliases();
// Collection contenant 'ugt'
```

---

### `getDescription(): string`

Retourne la description de la commande.

**Retourne :** `string` - Description de la commande

**Exemple :**
```php
$directive = new GitTagDirective();
$description = $directive->getDescription();
// 'Create a Git version tag (patch, minor, or major)'
```

---

### `beforeExecute(): void`

Méthode appelée avant l'exécution de la commande. Initialise la console et charge la configuration.

**Exceptions :** `Illuminate\Contracts\Container\BindingResolutionException` - Si le conteneur ne peut pas résoudre `UtilsConfigInterface`

**Exemple :**
```php
// Appelée automatiquement par le système de directives
$directive->beforeExecute();
```

---

### `execute(): ExitCode`

Méthode principale d'exécution de la directive.

**Retourne :** `ExitCode` - Code de sortie (SUCCESS ou FAILURE)

**Exceptions :** `Exception` - En cas d'erreur d'exécution des commandes Git

**Exemple :**
```php
$result = $directive->execute();
// ExitCode::SUCCESS ou ExitCode::FAILURE
```

## Cas d'utilisation

### Cas 1 : Création d'un tag patch (défaut)

Crée automatiquement un tag patch en incrémentant le dernier chiffre de la version.

```php
// Exécution en ligne de commande
// ./bin/afya ugt
// ou
// ./bin/afya ugt patch

// Crée v0.0.1 si la dernière version était v0.0.0
```

### Cas 2 : Création d'un tag minor

Incrémente le numéro de version mineur et réinitialise le patch à 0.

```php
// Exécution en ligne de commande
// ./bin/afya ugt minor

// Crée v0.1.0 si la dernière version était v0.0.1
```

### Cas 3 : Création d'un tag major

Incrémente le numéro de version majeur et réinitialise les autres à 0.

```php
// Exécution en ligne de commande
// ./bin/afya ugt major

// Crée v1.0.0 si la dernière version était v0.1.0
```

### Cas 4 : Re-publication d'un tag existant

Force la re-publication du dernier tag vers le dépôt distant.

```php
// Exécution en ligne de commande
// ./bin/afya ugt --republish

// Force push du dernier tag vers origin
```

### Cas 5 : Simulation sans exécution

Teste la création du tag sans effectuer de modifications réelles.

```php
// Exécution en ligne de commande
// ./bin/afya ugt --dry-run

// Affiche ce qui serait fait sans effectuer d'opération
```

## Flux d'exécution

```
beforeExecute()
    ├── Initialise Console
    └── Charge la configuration (UtilsConfigInterface)

execute()
    ├── Type = 'republish'
    │   └── republishTag()
    │       ├── getLastTag()
    │       ├── Affiche configuration
    │       └── pushTagForce() → SUCCESS/FAILURE
    │
    └── Type = 'patch'|'minor'|'major'
        ├── getLastTag()
        ├── parseVersion() → [$major, $minor, $patch]
        ├── Incrémente selon le type
        ├── Nouveau tag : v{major}.{minor}.{patch}
        ├── Affiche configuration
        ├── Si dry-run : retourne SUCCESS
        ├── createTag() → SUCCESS/FAILURE
        └── Si !noPush
            └── pushTag() → SUCCESS/FAILURE

afterExecute()
    └── Affiche le résultat final
```

## Gestion des erreurs

| Situation | Exception/Code | Message |
|-----------|---------------|---------|
| Échec de création du tag | `ExitCode::FAILURE` | `Failed to create tag: {error}` |
| Échec de poussée du tag | `ExitCode::FAILURE` | `Failed to push tag: {error}` |
| Échec de re-publication | `ExitCode::FAILURE` | `Failed to republish tag: {error}` |
| Aucun tag trouvé pour republish | `ExitCode::FAILURE` | `No tags found to republish` |
| Type de tag invalide | `ExitCode::SUCCESS` | `⚠️ Invalid tag type. Using default: patch` |

## Intégration

La directive s'intègre avec :

- **`AbstractDirective`** : Classe parente fournissant les fonctionnalités de base des directives
- **`Console`** : Gestion de l'interface console
- **`UtilsConfigInterface`** : Configuration des utilitaires Laravel
- **`StringTypedCollection`** : Gestion typée des alias
- **`Symfony\Process\Process`** : Exécution des commandes Git

## Performance

- **Complexité temporelle** : O(n) pour la récupération des tags (liste des tags Git)
- **Complexité spatiale** : O(n) pour la liste des tags
- **Timeout** : 120 secondes pour les opérations de push
- **Validation** : Vérification rapide du format de version via regex `'/^v?\d+\.\d+\.\d+$/'`
- **Parsing** : Découpage simple des chaînes pour l'extraction des composants de version

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Laravel | Support |
|-----------------|---------|
| Laravel 10.x | ✅ Complet |
| Laravel 9.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Directives\GitTagDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\ConsoleWriter\Console\Console;

// Utilisation directe avec la signature de la commande
$directive = new GitTagDirective();

// La directive est généralement exécutée via le système de directives
// ou via la ligne de commande :

// 1. Création d'un tag patch (défaut)
// $ ./bin/afya ugt

// 2. Création d'un tag minor sans push
// $ ./bin/afya ugt minor --no-push

// 3. Simulation d'un tag major avec message personnalisé
// $ ./bin/afya ugt major --dry-run <message="Version 1.0.0 majeure">

// 4. Re-publication du dernier tag
// $ ./bin/afya ugt --republish

// 5. Re-publication forcée sans push automatique
// $ ./bin/afya ugt --republish --no-push
```

## Voir aussi

- `AbstractDirective` - Classe de base pour les directives CLI
- `Console` - Gestionnaire d'interface console
- `UtilsConfigInterface` - Configuration des utilitaires Laravel
- `StringTypedCollection` - Collection typée pour les chaînes
- `SemVer` - Convention de versioning sémantique (https://semver.org/)