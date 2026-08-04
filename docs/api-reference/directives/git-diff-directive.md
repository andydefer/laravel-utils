```markdown
# GitDiffDirective - Référence Technique

## Description

La directive `GitDiffDirective` génère un fichier de diff Git formaté pour l'analyse par IA, avec des instructions pour la génération de messages de commit et de résumés de travail.

## Hiérarchie / Implémentations

```
AbstractDirective
    └── GitDiffDirective
```

**Interfaces :** `DirectiveInterface` (via `AbstractDirective`)

## Rôle principal

Cette directive automatise la création de fichiers de diff pour la revue de code par IA. Elle :
- Filtre les fichiers par chemins et extensions
- Applique des recettes d'extensions prédéfinies (frontend, backend)
- Génère un fichier Markdown avec le diff et des instructions pour l'IA
- Supporte les modes interactif, non-interactif et dry-run
- Peut créer automatiquement des résumés de travail à partir des réponses de l'IA

## API / Méthodes publiques

### `getSignature(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - La signature de la commande CLI

**Exemple :**
```php
$directive = new GitDiffDirective();
echo $directive->getSignature();
// utils:git-diff {paths*} {extensions*} {--frontend} {--backend} {--recipes} {--with-summary} {--no-interactive} {--dry-run}
```

---

### `getAliases(): StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StringTypedCollection` - Collection contenant les alias de la commande

**Exemple :**
```php
$aliases = $directive->getAliases(); // ['ugd']
```

---

### `getDescription(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Description de la directive

**Exemple :**
```php
echo $directive->getDescription();
// "Generate a Git diff for AI code review and commit message generation"
```

---

### `beforeExecute(): void`

Méthode d'initialisation appelée avant l'exécution.

- Initialise la console (`Console`)
- Charge la configuration des extensions et recettes
- Affiche la bannière de démarrage

---

### `execute(): ExitCode`

Point d'entrée principal de la directive.

1. Récupère les arguments et flags
2. Valide les entrées en mode non-interactif
3. Gère la sélection interactive des chemins et extensions
4. Applique les recettes d'extensions
5. Génère le fichier de diff
6. En mode dry-run, simule sans écrire
7. Ouvre le fichier dans l'éditeur (si non-interactif désactivé)

**Retourne :** `ExitCode::SUCCESS` ou `ExitCode::FAILURE`

---

### `afterExecute(ExitCode $exitCode): void`

Méthode appelée après l'exécution, affiche un message de confirmation ou d'erreur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Code de sortie de l'exécution |

---

## Cas d'utilisation

### Cas 1 : Génération simple de diff

```bash
./bin/afya ugd
```

Mode interactif : demande les chemins et extensions à inclure.

---

### Cas 2 : Non-interactif avec chemins et extensions

```bash
./bin/afya ugd [src, tests] [.php, .js] --no-interactive
```

Génère un diff pour les fichiers PHP et JS dans les dossiers `src` et `tests`.

---

### Cas 3 : Utilisation des recettes d'extensions

```bash
# Frontend uniquement
./bin/afya ugd --frontend

# Backend uniquement
./bin/afya ugd --backend

# Sélection interactive des recettes
./bin/afya ugd --recipes
```

---

### Cas 4 : Simulation (dry-run)

```bash
./bin/afya ugd [src] --dry-run
```

Simule la génération sans créer de fichier.

---

### Cas 5 : Génération avec résumé de travail

```bash
./bin/afya ugd [src] --with-summary
```

Après la génération du diff, crée un fichier de résumé pour la réponse de l'IA.

---

## Configuration

### Fichier de configuration

```php
// config/utils.php
return [
    'default_extensions' => ['php', 'js', 'ts', 'css'],
    'extension_recipes' => [
        'frontend' => ['js', 'ts', 'tsx', 'jsx', 'vue', 'css', 'scss', 'html'],
        'backend' => ['php', 'py', 'go', 'rs', 'java'],
        'fullstack' => ['php', 'js', 'ts', 'css', 'html'],
    ],
];
```

### Interface de configuration

```php
<?php

namespace AndyDefer\LaravelUtils\Contracts\Config;

interface UtilsConfigInterface
{
    public function getDefaultExtensions(): array;
    public function getExtensionRecipes(): array;
}
```

## Flux d'exécution

```
execute()
    ↓
Récupération des arguments
    ↓
noInteractive ?
    ├── Oui → Validation stricte des chemins
    │   ├── Chemins vides → Error
    │   └── Pas d'extensions → Utilisation des extensions par défaut
    └── Non → Continue
    ↓
Chemins vides ?
    ├── Oui → askForPaths() (interactif)
    └── Non → Continue
    ↓
frontend flag ?
    ├── Oui → Utilisation de la recette frontend
    └── Non → Continue
    ↓
backend flag ?
    ├── Oui → Utilisation de la recette backend
    └── Non → Continue
    ↓
recipes flag ?
    ├── Oui → askForRecipeExtensions()
    │   ├── noInteractive → Toutes les recettes
    │   └── Sinon → Sélection multi-choice
    └── Non → Continue
    ↓
extensions vides ?
    ├── Oui → askForExtensions()
    └── Non → Continue
    ↓
generateDiff()
    ├── getModifiedFiles()
    ├── Filtrer par extensions
    ├── buildDiffContent()
    └── Écrire le fichier (sauf dry-run)
    ↓
dryRun ?
    ├── Oui → "Dry run completed successfully"
    └── Non → Continue
    ↓
noInteractive ?
    ├── Non → openFileInEditor()
    └── Oui → Skip
    ↓
withSummary ?
    ├── Oui → createWorkSummary()
    └── Non → Continue
```

## Gestion des erreurs

| Situation | Code de sortie | Message |
|-----------|----------------|---------|
| Chemins vides en mode non-interactif | `ExitCode::FAILURE` | `At least one path is required in non-interactive mode` |
| Aucun fichier modifié | `ExitCode::SUCCESS` | `No changes found` |
| Échec de l'ajout des fichiers | `ExitCode::FAILURE` | `Failed to add files: {error}` |
| Échec du commit | `ExitCode::FAILURE` | `Failed to commit: {error}` |

## Intégration

La directive utilise :
- `Console` du package `console-writer` pour l'interface utilisateur
- `UtilsConfigInterface` pour la configuration des extensions
- `Process` de Symfony pour les commandes Git
- `AbstractDirective` du package `laravel-directive`

## Performance

- **Récupération des fichiers** : `git diff --name-only` (rapide)
- **Filtrage** : O(n) où n est le nombre de fichiers
- **Génération du diff** : Dépend du nombre de fichiers modifiés
- **Écriture du fichier** : Rapide, taille du diff variable

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```bash
# 1. Configuration des extensions
# config/utils.php
return [
    'default_extensions' => ['php', 'js', 'ts', 'css'],
    'extension_recipes' => [
        'frontend' => ['js', 'ts', 'tsx', 'jsx', 'vue', 'css', 'scss', 'html'],
        'backend' => ['php', 'py', 'go', 'rs', 'java'],
    ],
];

# 2. Génération de diff pour le backend
./bin/afya ugd [src, tests] --backend --no-interactive

# 3. Génération de diff avec simulation
./bin/afya ugd [src] --frontend --dry-run

# 4. Génération de diff avec résumé de travail
./bin/afya ugd [src] --with-summary
```

## Voir aussi

- `UtilsConfigInterface` - Interface de configuration
- `AbstractDirective` - Base des directives
- `Console` - Composant d'interface utilisateur
```