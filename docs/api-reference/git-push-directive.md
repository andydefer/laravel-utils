```markdown
# GitPushDirective - Référence Technique

## Description

La directive `GitPushDirective` permet de pousser du code vers des dépôts Git distants configurés, avec un mode interactif et des options de tests.

## Hiérarchie / Implémentations

```
AbstractDirective
    └── GitPushDirective
```

**Interfaces :** `DirectiveInterface` (via `AbstractDirective`)

## Rôle principal

Cette directive orchestre le workflow Git de push en automatisant :
- La sélection des dépôts distants (configurables)
- La saisie du message de commit (interactive ou non)
- L'exécution des tests avant push
- Le choix des dossiers à ajouter
- Les options de force push (`--force-with-lease`)
- Le mode simulation (`--dry-run`)

## API / Méthodes publiques

### `getSignature(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - La signature de la commande CLI

**Exemple :**
```php
$directive = new GitPushDirective();
echo $directive->getSignature();
// utils:git-push {sources*} {folders*} {--no-tests} {--force-with-lease} {--force} {--no-interactive} {--dry-run}
```

---

### `getAliases(): StringTypedCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StringTypedCollection` - Collection contenant les alias de la commande

**Exemple :**
```php
$aliases = $directive->getAliases(); // ['ugp']
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
// "Push code to configured remote repositories with interactive mode"
```

---

### `beforeExecute(): void`

Méthode d'initialisation appelée avant l'exécution.

- Initialise la console (`Console`)
- Charge la configuration des dépôts
- Affiche la bannière de démarrage

---

### `execute(): ExitCode`

Point d'entrée principal de la directive.

1. Récupère les arguments et flags
2. Valide le message de commit (regex alphanumérique)
3. Détecte le mode interactif
4. Valide les sources
5. Exécute les tests (si demandé)
6. Effectue le commit
7. Push vers les dépôts distants

**Retourne :** `ExitCode::SUCCESS` ou `ExitCode::FAILURE`

---

### `afterExecute(ExitCode $exitCode): void`

Méthode appelée après l'exécution, affiche un message de confirmation ou d'erreur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Code de sortie de l'exécution |

---

## Cas d'utilisation

### Cas 1 : Mode interactif

```bash
./bin/afya ugp
```

Le formulaire interactif demande :
- 💬 Message du commit
- 🎯 Cibles (multi-choice)
- 🧪 Exécution des tests (confirm)

---

### Cas 2 : Push vers une cible spécifique

```bash
./bin/afya ugp [github] --dry-run <message="Fix: Correction du bug de connexion">
```

---

### Cas 3 : Push vers plusieurs cibles

```bash
./bin/afya ugp [github, o2switch] --dry-run <message="Feature: Ajout de la pagination">
```

---

### Cas 4 : Push avec dossiers spécifiques

```bash
./bin/afya ugp [github] [src, resources/views] --dry-run <message="Update: Modifications du front">
```

---

### Cas 5 : Push sans tests

```bash
./bin/afya ugp [github] --no-tests --dry-run <message="Hotfix: Correction urgente">
```

---

### Cas 6 : Force push avec lease

```bash
./bin/afya ugp [github] --force-with-lease --dry-run <message="Rebase: Réorganisation du code">
```

---

### Cas 7 : Force push même si les tests échouent

```bash
./bin/afya ugp [github] --force --dry-run <message="Hotfix: Correction critique">
```

---

### Cas 8 : Mode simulation (dry-run)

```bash
./bin/afya ugp [github] --dry-run <message="Test: Vérification des modifications">
```

## Flux d'exécution

```
execute()
    ↓
Récupération des arguments
    ↓
Validation du message (regex alphanumérique)
    ├── Échec → Error: "Commit message must contain at least one alphanumeric character"
    └── Succès → Continue
    ↓
noInteractive && empty($sources) ?
    ├── Oui → Error: "At least one target is required in non-interactive mode"
    └── Non → Continue
    ↓
dryRun ?
    ├── Oui → validateSources() → displayConfiguration() → "Dry run completed"
    └── Non → Continue
    ↓
isInteractive ? (message null ou sources empty)
    ├── Oui → Formulaire interactif (ask + multiChoice)
    └── Non → Continue
    ↓
Message vide ?
    ├── Oui → Error: "Commit message must contain at least one alphanumeric character"
    └── Non → Continue
    ↓
Sources empty ?
    ├── Oui → Confirmation "Push to all configured targets ?"
    └── Non → Continue
    ↓
validateSources()
    ├── Sources valides → Continue
    └── Sources invalides → Error: "No valid targets found"
    ↓
displayConfiguration()
    ↓
runTests ?
    ├── Oui → runTests() → handleTests()
    │   ├── Tests OK → Continue
    │   ├── Tests KO + --force → Warning + Continue
    │   └── Tests KO + sans --force → Error
    └── Non → "Tests skipped"
    ↓
commitChanges()
    ├── git add . ou git add [folders]
    ├── git commit -m "message"
    └── Check "nothing to commit"
    ↓
pushToRemotes()
    ├── Récupération branche courante
    ├── Pour chaque source :
    │   ├── git push [--force-with-lease] [remoteUrl] [branch]
    │   └── Succès ou échec
    └── Résultat final
```

## Configuration

### Fichier de configuration

```php
// config/utils.php
return [
    'repositories' => [
        'github' => 'git@github.com:andydefer/afya-medical.git',
        'o2switch' => 'ssh://user@domain.com/home/user/git/repo.git',
    ],
];
```

### Interface de configuration

```php
<?php

namespace AndyDefer\LaravelUtils\Contracts\Config;

interface UtilsConfigInterface
{
    public function getRepositories(): array;
}
```

## Gestion des erreurs

| Situation | Code de sortie | Message |
|-----------|----------------|---------|
| Message de commit vide ou sans caractère alphanumérique | `ExitCode::FAILURE` | `Commit message must contain at least one alphanumeric character` |
| Aucune source en mode non-interactif | `ExitCode::FAILURE` | `At least one target is required in non-interactive mode` |
| Aucune source valide | `ExitCode::FAILURE` | `No valid targets found` |
| Échec des tests (sans force) | `ExitCode::FAILURE` | `Tests failed. Use --force to ignore.` |
| Échec du commit | `ExitCode::FAILURE` | `Commit failed` |
| Aucun changement à committer | `ExitCode::SUCCESS` | `No changes to commit` |
| Échec du push | `ExitCode::FAILURE` | `Push failed` |
| Source invalide | `ExitCode::FAILURE` | `⚠️ Target 'X' does not exist in configuration` |
| Opération annulée | `ExitCode::FAILURE` | `Operation cancelled` |

## Intégration

La directive utilise :
- `Console` du package `console-writer` pour l'interface utilisateur
- `UtilsConfigInterface` pour la configuration des dépôts
- `Process` de Symfony pour les commandes Git
- `AbstractDirective` du package `laravel-directive`

## Performance

- **Tests** : Dépend de la taille de la suite de tests (timeout 300s)
- **Git add** : Dépend du nombre de fichiers
- **Git commit** : Opération rapide
- **Push** : Dépend de la taille des données et de la connexion réseau (timeout 120s par remote)

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```bash
# 1. Configuration des dépôts
# config/utils.php
return [
    'repositories' => [
        'github' => 'git@github.com:andydefer/afya-medical.git',
        'o2switch' => 'ssh://user@domain.com/home/user/git/repo.git',
    ],
];

# 2. Push interactif
./bin/afya ugp

# 3. Push non-interactif avec simulation
./bin/afya ugp [github, o2switch] --dry-run <message="Feature: Ajout de la nouvelle API">

# 4. Push sans tests
./bin/afya ugp [github] --no-tests --dry-run <message="Hotfix: Correction CSS">

# 5. Force push même si tests échouent
./bin/afya ugp [github] --force --dry-run <message="Urgent: Correction sécurité">
```

## Voir aussi

- `UtilsConfigInterface` - Interface de configuration
- `AbstractDirective` - Base des directives
- `Console` - Composant d'interface utilisateur
```