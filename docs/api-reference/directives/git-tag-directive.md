# GitTagDirective - Référence Technique

## Description

Directive CLI permettant de créer des tags de version Git basés sur le versioning sémantique (SemVer). Cette directive automatise la création de tags versionnés selon les conventions SemVer (patch, minor, major) avec des options avancées comme la re-publication forcée et la simulation.

## Hiérarchie

```
AbstractDirective
    └── GitTagDirective
```

## Rôle principal

Automatise la création de tags de version en incrémentant automatiquement les numéros de version selon la convention SemVer (patch, minor, major). La directive gère également la publication des tags vers le dépôt distant et offre des fonctionnalités de re-publication forcée pour les cas où un tag doit être mis à jour.

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

---

### `afterExecute(ExitCode $exitCode): void`

Méthode appelée après l'exécution de la commande. Affiche le résultat final.

**Paramètres :**

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Code de sortie de l'exécution |

**Exemple :**
```php
// Appelée automatiquement par le système de directives
$directive->afterExecute(ExitCode::SUCCESS);
```

## Cas d'utilisation

### Cas 1 : Création d'un tag patch (défaut)

Crée automatiquement un tag patch en incrémentant le dernier chiffre de la version. C'est le comportement par défaut lorsque aucun type n'est spécifié.

```bash
# Exécution en ligne de commande
./bin/afya ugt
# ou
./bin/afya ugt patch

# Crée v0.0.1 si la dernière version était v0.0.0
```

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Configuration:

🏷️  Type        patch
📦 Last tag     v0.0.0
🆕 New tag      v0.0.1
💬 Message      Release v0.0.1
📤 Push         ✅ Yes

📦 Creating tag: v0.0.1

✅ Tag created: v0.0.1

📤 Pushing tag to remote...

✅ Tag pushed: v0.0.1

✅ Tag operation completed successfully!
```

### Cas 2 : Création d'un tag minor

Incrémente le numéro de version mineur et réinitialise le patch à 0.

```bash
# Exécution en ligne de commande
./bin/afya ugt minor

# Crée v0.1.0 si la dernière version était v0.0.1
```

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Configuration:

🏷️  Type        minor
📦 Last tag     v0.0.1
🆕 New tag      v0.1.0
💬 Message      Release v0.1.0
📤 Push         ✅ Yes

📦 Creating tag: v0.1.0

✅ Tag created: v0.1.0

📤 Pushing tag to remote...

✅ Tag pushed: v0.1.0

✅ Tag operation completed successfully!
```

### Cas 3 : Création d'un tag major

Incrémente le numéro de version majeur et réinitialise les autres à 0.

```bash
# Exécution en ligne de commande
./bin/afya ugt major

# Crée v1.0.0 si la dernière version était v0.1.0
```

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Configuration:

🏷️  Type        major
📦 Last tag     v0.1.0
🆕 New tag      v1.0.0
💬 Message      Release v1.0.0
📤 Push         ✅ Yes

📦 Creating tag: v1.0.0

✅ Tag created: v1.0.0

📤 Pushing tag to remote...

✅ Tag pushed: v1.0.0

✅ Tag operation completed successfully!
```

### Cas 4 : Re-publication d'un tag existant

Force la re-publication du dernier tag vers le dépôt distant. Utile lorsque vous avez besoin de mettre à jour un tag existant (par exemple, après une correction de dernière minute).

```bash
# Exécution en ligne de commande
./bin/afya ugt --republish

# Force push du dernier tag vers origin
```

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Republishing last tag...

📦 Last tag: v1.0.0

📋 Configuration:

🏷️  Type        republish
📦 Last tag     v1.0.0
🆕 New tag      v1.0.0
💬 Message      Republish v1.0.0
📤 Push         ✅ Yes

📤 Republishing tag: v1.0.0 (force push)

✅ Tag republished: v1.0.0

✅ Tag operation completed successfully!
```

### Cas 5 : Simulation sans exécution

Teste la création du tag sans effectuer de modifications réelles. Idéal pour vérifier ce qui serait fait avant d'exécuter réellement la commande.

```bash
# Exécution en ligne de commande
./bin/afya ugt --dry-run

# Affiche ce qui serait fait sans effectuer d'opération
```

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Configuration:

🏷️  Type        patch
📦 Last tag     v1.0.0
🆕 New tag      v1.0.1
💬 Message      Release v1.0.1
📤 Push         ✅ Yes

✅ Dry run completed successfully!
📋 No actual changes were made.

✅ Tag operation completed successfully!
```

### Cas 6 : Tag avec message personnalisé et sans push

Crée un tag avec un message personnalisé et ne pousse pas automatiquement vers le dépôt distant.

```bash
# Exécution en ligne de commande
./bin/afya ugt minor --no-push <message="Version 0.2.0 - Ajout de nouvelles fonctionnalités">

# Crée le tag localement sans le pousser
```

**Exemple de sortie :**
```
🏷️ GIT TAG

📋 Configuration:

🏷️  Type        minor
📦 Last tag     v0.1.0
🆕 New tag      v0.2.0
💬 Message      Version 0.2.0 - Ajout de nouvelles fonctionnalités
📤 Push         ❌ No

📦 Creating tag: v0.2.0

✅ Tag created: v0.2.0

⏭️  Tag push skipped

✅ Tag operation completed successfully!
```

### Cas 7 : Gestion des types invalides

Lorsqu'un type de tag invalide est fourni, la directive utilise automatiquement le type par défaut (patch) et affiche un avertissement.

```bash
# Exécution en ligne de commande
./bin/afya ugt invalid

# Affiche un avertissement et utilise le type patch par défaut
```

**Exemple de sortie :**
```
🏷️ GIT TAG

⚠️  Invalid tag type. Using default: patch

📋 Configuration:

🏷️  Type        patch
📦 Last tag     v0.0.0
🆕 New tag      v0.0.1
💬 Message      Release v0.0.1
📤 Push         ✅ Yes

📦 Creating tag: v0.0.1

✅ Tag created: v0.0.1

📤 Pushing tag to remote...

✅ Tag pushed: v0.0.1

✅ Tag operation completed successfully!
```

## Flux d'exécution

```
beforeExecute()
    ├── Initialise Console
    └── Charge la configuration (UtilsConfigInterface)
        └── loadConfiguration()
            └── $this->config = $app->make(UtilsConfigInterface::class)

execute()
    ├── Récupère les arguments et flags
    │   ├── $type = $this->getArgument('type') ?? 'patch'
    │   ├── $noPush = $this->getFlag('no-push')
    │   ├── $dryRun = $this->getFlag('dry-run')
    │   └── $republish = $this->getFlag('republish')
    │
    ├── Si republish = true
    │   └── republishTag()
    │       ├── getLastTag() → string
    │       ├── Si lastTag = 'v0.0.0'
    │       │   └── ExitCode::FAILURE
    │       ├── displayConfiguration('republish', lastTag, lastTag, message, noPush)
    │       ├── Si dryRun = true
    │       │   └── ExitCode::SUCCESS
    │       └── pushTagForce(lastTag) → ExitCode
    │
    └── Sinon (création normale)
        ├── Vérifie la validité du type
        │   └── Si type invalide → alerte + type = 'patch'
        │
        ├── getLastTag() → string
        ├── parseVersion($lastTag) → [$major, $minor, $patch]
        │   └── ltrim('v') + explode('.')
        │
        ├── Incrémente selon le type
        │   ├── 'major' → $major++, $minor=0, $patch=0
        │   ├── 'minor' → $minor++, $patch=0
        │   └── 'patch' → $patch++
        │
        ├── Nouveau tag : "v{$major}.{$minor}.{$patch}"
        ├── Message : $this->getCustomDataItem('message', "Release {$newTag}")
        ├── displayConfiguration(type, lastTag, newTag, message, noPush)
        │
        ├── Si dryRun = true
        │   └── ExitCode::SUCCESS
        │
        ├── createTag(newTag, message) → ExitCode
        │   └── Process(['git', 'tag', '-a', tag, '-m', message])
        │
        ├── Si createTag = ExitCode::FAILURE
        │   └── Return ExitCode::FAILURE
        │
        ├── Si noPush = false
        │   └── pushTag(newTag) → ExitCode
        │       └── Process(['git', 'push', 'origin', tag])
        │
        └── ExitCode::SUCCESS

afterExecute(exitCode)
    ├── Si exitCode = ExitCode::SUCCESS
    │   └── success('✅ Tag operation completed successfully!')
    └── Sinon
        └── error('❌ Tag operation failed')
    └── Console::render()
```

## Gestion des erreurs

| Situation | Code de sortie | Message |
|-----------|---------------|---------|
| Échec de création du tag | `ExitCode::FAILURE` | `Failed to create tag: {error_output}` |
| Échec de poussée du tag | `ExitCode::FAILURE` | `Failed to push tag: {error_output}` |
| Échec de re-publication | `ExitCode::FAILURE` | `Failed to republish tag: {error_output}` |
| Aucun tag trouvé pour republish | `ExitCode::FAILURE` | `No tags found to republish` |
| Type de tag invalide | `ExitCode::SUCCESS` | `⚠️ Invalid tag type. Using default: patch` |
| Résolution de configuration impossible | `BindingResolutionException` | Non applicable (levée par le conteneur) |

**Patterns de messages d'erreur :**
- Les messages d'erreur retournés par les commandes Git sont dynamiques et dépendent du contexte (problème de réseau, permissions, conflits, etc.)
- Les erreurs de validation de version retournent des messages explicites indiquant le format attendu

## Intégration

La directive s'intègre avec :

- **`AbstractDirective`** : Classe parente fournissant les fonctionnalités de base des directives
- **`Console`** : Gestion de l'interface console (titres, séparateurs, couleurs)
- **`UtilsConfigInterface`** : Configuration des utilitaires Laravel
- **`StringTypedCollection`** : Collection typée pour les alias de commande
- **`Symfony\Process\Process`** : Exécution des commandes Git avec gestion du timeout
- **`ExitCode`** : Enumération des codes de sortie standardisés

### Points d'extension potentiels

La classe peut être étendue pour :
- Ajouter de nouveaux types de version (ex: `pre-release`, `build-metadata`)
- Modifier le format des tags (ex: `release-1.0.0` au lieu de `v1.0.0`)
- Ajouter des hooks avant/après la création du tag
- Intégrer des systèmes de versionnement alternatifs

## Performance

- **Complexité temporelle** : O(n) pour la récupération des tags (liste des tags Git)
- **Complexité spatiale** : O(n) pour la liste des tags triés
- **Timeout** : 120 secondes pour les opérations de push (adapté aux grandes bases de code)
- **Validation** : Vérification rapide du format de version via regex `'/^v?\d+\.\d+\.\d+$/'`
- **Parsing** : Découpage simple des chaînes pour l'extraction des composants de version
- **Cache** : Aucun cache nécessaire, les opérations sont atomiques
- **Mémoire** : Consommation minimale, adaptée aux environnements contraints

### Optimisations

- La liste des tags est récupérée via `git tag -l --sort=-v:refname` qui trie directement les tags par version
- Le parsing est effectué via `explode()` pour une performance optimale
- Les commandes Git sont exécutées avec un timeout configurable pour éviter les blocages

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Laravel | Support |
|-----------------|---------|
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |
| Laravel 14.x | ✅ Complet |
| Laravel 15.x | ✅ Complet |

| Version Git | Support |
|-------------|---------|
| Git 2.20+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelUtils\Directives\GitTagDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\ConsoleWriter\Console\Console;
use Illuminate\Container\Container;

// Exemple d'utilisation programmatique
$container = Container::getInstance();
$directive = new GitTagDirective();
$directive->setApplication($container);

// La directive est généralement exécutée via le système de directives
// ou via la ligne de commande. Voici quelques exemples d'utilisation :

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

// 6. Création d'un tag avec tous les paramètres
// $ ./bin/afya ugt minor --no-push --dry-run <message="Test de version mineure">

// 7. Utilisation avec un type invalide (utilise patch par défaut)
// $ ./bin/afya ugt invalid

// Exemple de workflow complet avec GitTagDirective
function exampleWorkflow(): void
{
    $directive = new GitTagDirective();
    
    // Simuler l'exécution pour voir ce qui serait fait
    // Dans un cas réel, utilisez le système de directives Laravel
    
    echo "Workflow de versionnement :\n";
    echo "1. Vérifier l'état du code\n";
    echo "2. Exécuter les tests\n";
    echo "3. Créer un tag : ./bin/afya ugt patch\n";
    echo "4. Pousser le tag : git push origin --tags\n";
    echo "5. Déployer la version\n";
}
```

## Voir aussi

- `AbstractDirective` - Classe de base pour les directives CLI
- `Console` - Gestionnaire d'interface console
- `UtilsConfigInterface` - Configuration des utilitaires Laravel
- `StringTypedCollection` - Collection typée pour les chaînes
- `GitPushDirective` - Directive pour pousser le code vers les dépôts distants
- `GitDiffDirective` - Directive pour générer des diffs de code
- `SemVer` - Convention de versioning sémantique (https://semver.org/)
- `Git Tag Documentation` - Documentation officielle Git sur les tags
- `Laravel Directive System` - Système de directives Laravel

---

## Mise à jour de la table des matières

Ajoutez cette entrée à la table des matières :

```markdown
   - [4.5 GitTagDirective](#gittagdirective)
```

Ajoutez cette section dans le README :

### GitTagDirective

Directive CLI pour créer des tags de version Git avec versioning sémantique.

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
- Versionnement sémantique automatique
- Message personnalisé pour les tags
- Simulation (dry-run) sans effectuer de modifications
- Re-publication forcée des tags existants
- Gestion des erreurs et des cas limites
- Interface utilisateur enrichie avec couleurs et icônes

### Mise à jour de la configuration

Ajoutez dans la section configuration :

```php
// config/utils.php
return [
    // ... autres configurations ...
    
    // Les tags utilisent les paramètres Git par défaut
    // Aucune configuration spécifique n'est requise
];
```