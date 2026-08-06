<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Symfony\Component\Process\Process;

/**
 * CLI directive for generating a Git diff for AI code review.
 *
 * This directive creates a diff file formatted for AI analysis,
 * including instructions for generating commit messages and work summaries.
 *
 * @example
 * // Generate diff for all changes
 * ./bin/afya ugd
 *
 * // Generate diff for specific paths
 * ./bin/afya ugd [src, tests]
 *
 * // Generate diff with frontend extensions
 * ./bin/afya ugd --frontend
 *
 * // Generate diff with backend extensions
 * ./bin/afya ugd --backend
 *
 * // Generate diff with custom extensions
 * ./bin/afya ugd [.php, .js, .ts]
 *
 * // Generate diff with paths and extensions
 * ./bin/afya ugd [src, tests] [.php, .js]
 *
 * // Generate diff with recipes
 * ./bin/afya ugd --recipes
 *
 * // Generate diff and automatically create work summary
 * ./bin/afya ugd --with-summary
 */
final class GitDiffDirective extends AbstractDirective
{
    private Console $console;

    private UtilsConfigInterface $config;

    private array $defaultExtensions;

    private array $extensionRecipes;

    public function getSignature(): string
    {
        return 'utils:git-diff 
                {paths*}#"Specific paths to include (empty = all changes)" 
                {extensions*}#"File extensions to filter (empty = all extensions)" 
                {--frontend}#"Use frontend extensions from config" 
                {--backend}#"Use backend extensions from config" 
                {--recipes}#"Select extension recipes interactively" 
                {--with-summary}#"Generate work summary after diff"
                {--no-interactive}#"Disable interactive mode"
                {--dry-run}#"Simulate the operation without actually executing"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ugd']);
    }

    public function getDescription(): string
    {
        return 'Generate a Git diff for AI code review and commit message generation';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->loadConfiguration();

        $this->console->title('📋 GIT DIFF FOR AI REVIEW');
        $this->console->separatorDouble();
        $this->console->line();
    }

    private function loadConfiguration(): void
    {
        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);
        $this->defaultExtensions = $this->config->getDefaultExtensions();
        $this->extensionRecipes = $this->config->getExtensionRecipes();
    }

    protected function execute(): ExitCode
    {
        $paths = $this->getVariadic('paths');
        $extensions = $this->getVariadic('extensions');
        $withSummary = $this->getFlag('with-summary');
        $frontend = $this->getFlag('frontend');
        $backend = $this->getFlag('backend');
        $recipes = $this->getFlag('recipes');
        $noInteractive = $this->getFlag('no-interactive');
        $dryRun = $this->getFlag('dry-run');

        // Mode non-interactif: validation stricte
        if ($noInteractive) {
            if (empty($paths)) {
                $this->console->error('❌ At least one path is required in non-interactive mode');

                return ExitCode::FAILURE;
            }

            // Si aucun flag d'extension n'est passé, on utilise les extensions par défaut
            if (! $frontend && ! $backend && ! $recipes && empty($extensions)) {
                $this->console->info('📋 Using default extensions');
                $extensions = $this->defaultExtensions;
            }
        }

        // Mode interactif pour les chemins
        if (empty($paths)) {
            $paths = $this->askForPaths();
        }

        // Gestion des extensions
        if ($frontend) {
            $extensions = $this->extensionRecipes['frontend'] ?? [];
            $this->console->info('📋 Using frontend extensions: '.implode(', ', $extensions));
            $this->console->line();
        }

        if ($backend) {
            $extensions = $this->extensionRecipes['backend'] ?? [];
            $this->console->info('📋 Using backend extensions: '.implode(', ', $extensions));
            $this->console->line();
        }

        // Si --recipes est passé ET que ce n'est pas en mode non-interactif
        if ($recipes && ! $noInteractive) {
            $extensions = $this->askForRecipeExtensions();
        } elseif ($recipes && $noInteractive) {
            // En mode non-interactif, on prend toutes les recettes
            $extensions = [];
            foreach ($this->extensionRecipes as $recipeExtensions) {
                $extensions = array_merge($extensions, $recipeExtensions);
            }
            $extensions = array_unique($extensions);
            $this->console->info('📋 Using all recipes in non-interactive mode: '.implode(', ', $extensions));
            $this->console->line();
        }

        if (empty($extensions)) {
            $extensions = $this->askForExtensions();
        }

        $filename = $this->generateDiff($paths, $extensions);
        $this->console->line();
        $this->console->info("📄 Diff file: {$filename}");

        // En mode dry-run, on ne fait que simuler
        if ($dryRun) {
            $this->console->newLine();
            $this->console->success('✅ Dry run completed successfully!');
            $this->console->line('📋 No actual changes were made.');

            return ExitCode::SUCCESS;
        }

        // Ne pas ouvrir l'éditeur en mode non-interactif
        if (! $noInteractive) {
            $this->openFileInEditor($filename);
        }

        if ($withSummary) {
            $this->createWorkSummary();
        }

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->console->newLine();
        if ($exitCode === ExitCode::SUCCESS) {
            $this->console->success('✅ Diff generated successfully!');
        } else {
            $this->console->error('❌ Operation failed');
        }
        $this->console->render();
    }

    private function askForPaths(): array
    {
        $this->console->info('📋 No paths specified. Enter directories to scan:');
        $this->console->line();

        $answer = $this->console->ask('📁 Directories (separated by space, leave empty for all):', '');

        if (empty(trim($answer))) {
            $this->console->info('📂 Scanning all files');
            $this->console->line();

            return [];
        }

        $paths = array_map('trim', explode(' ', $answer));
        $paths = array_filter($paths);

        $this->console->info('📂 Scanning paths: '.implode(', ', $paths));
        $this->console->line();

        return $paths;
    }

    private function askForRecipeExtensions(): array
    {
        $this->console->info('📋 Select extension recipes:');
        $this->console->line();

        $recipeNames = array_keys($this->extensionRecipes);

        if (empty($recipeNames)) {
            $this->console->alertWarning('No recipes configured');

            return [];
        }

        $answers = $this->console->form()
            ->title('📁 Select extension recipes')
            ->line()
            ->multiChoice(
                '🔍 Choose recipes:',
                'recipes',
                $recipeNames,
                $recipeNames
            )
            ->submit();

        $selectedRecipes = $answers->get('recipes');

        if (empty($selectedRecipes)) {
            $this->console->info('📂 No recipes selected, including all extensions');

            return [];
        }

        $extensions = [];
        foreach ($selectedRecipes as $recipe) {
            if (isset($this->extensionRecipes[$recipe])) {
                $extensions = array_merge($extensions, $this->extensionRecipes[$recipe]);
            }
        }

        $extensions = array_unique($extensions);

        $this->console->info('📋 Selected extensions from recipes: '.implode(', ', $extensions));
        $this->console->line();

        return $extensions;
    }

    private function askForExtensions(): array
    {
        $this->console->info('📋 No extensions specified. Enter extensions to filter:');
        $this->console->line();

        $answer = $this->console->ask('🔍 Extensions (separated by space, e.g. .php .js .ts, leave empty for all):', '');

        if (empty(trim($answer))) {
            $this->console->info('📂 Including all file extensions');
            $this->console->line();

            return [];
        }

        $extensions = array_map('trim', explode(' ', $answer));
        $extensions = array_filter($extensions);

        // Nettoyer les extensions (enlever le point si présent)
        $extensions = array_map(function ($ext) {
            return ltrim($ext, '.');
        }, $extensions);

        $this->console->info('📋 Filtering by extensions: '.implode(', ', $extensions));
        $this->console->line();

        return $extensions;
    }

    private function generateDiff(array $paths, array $extensions): string
    {
        $this->console->info('📁 Generating diff...');
        $this->console->line();

        if (! empty($extensions)) {
            $this->console->info('📋 Filtering by extensions: '.implode(', ', $extensions));
            $this->console->line();
        }

        $date = date('Y-m-d');
        $time = date('H-i-s');
        $filename = "docs/diffs/{$date}T{$time}-diff.md";

        $content = $this->buildDiffContent($paths, $extensions);

        // Créer le dossier et écrire le fichier seulement si ce n'est pas un dry-run
        if (! $this->getFlag('dry-run')) {
            $this->ensureDirectory('docs/diffs');
            file_put_contents($filename, $content);
            $this->console->success('✅ Diff generated');
        } else {
            $this->console->line('📋 Dry run: file would be created at: '.$filename);
        }

        return $filename;
    }

    private function getModifiedFiles(array $paths): array
    {
        // Récupérer tous les fichiers modifiés (stagés ET non stagés)
        $process = new Process(['git', 'diff', '--name-only']);
        $process->run();
        $files = explode("\n", trim($process->getOutput()));

        // Récupérer les fichiers stagés
        $process = new Process(['git', 'diff', '--cached', '--name-only']);
        $process->run();
        $stagedFiles = explode("\n", trim($process->getOutput()));

        // Fusionner et supprimer les doublons
        $allFiles = array_unique(array_merge($files, $stagedFiles));
        $allFiles = array_filter($allFiles);

        // Filtrer par chemins si spécifiés
        if (! empty($paths)) {
            $allFiles = array_filter($allFiles, function ($file) use ($paths) {
                foreach ($paths as $path) {
                    if (str_starts_with($file, $path)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $allFiles;
    }

    private function buildDiffContent(array $paths, array $extensions): string
    {
        $content = "Tu es un expert en revue de code et en conventions de commits (Conventional Commits).\n\n";
        $content .= "À partir du diff Git ci-dessous, fais les choses suivantes :\n\n";
        $content .= "1. Propose un nom de fichier pour le work summary\n";
        $content .= "2. Propose un nom de commit clair et concis en anglais avec le format <type>(<scope>): <description>\n";
        $content .= "3. Rédige un résumé du travail effectué en quelques phrases (en français)\n";
        $content .= "4. Donne une liste d'exemples concrets de changements\n\n";
        $content .= "Voici le diff :\n\n```diff\n";

        // Récupérer les fichiers modifiés
        $files = $this->getModifiedFiles($paths);

        if (empty($files)) {
            $content .= "No changes found. Make sure you have uncommitted changes.\n";
            $content .= "\n```\n";

            return $content;
        }

        // Filtrer par extensions
        if (! empty($extensions)) {
            $files = array_filter($files, function ($file) use ($extensions) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);

                return in_array($ext, $extensions, true);
            });
        }

        if (empty($files)) {
            $content .= "No files match the specified criteria.\n";
            $content .= "\n```\n";

            return $content;
        }

        // Construire la commande git diff avec les fichiers filtrés
        $args = ['git', 'diff', '--'];
        $args = array_merge($args, $files);

        $process = new Process($args);
        $process->run();
        $output = $process->getOutput();

        if (empty(trim($output))) {
            $content .= "No changes found in the specified files.\n";
        } else {
            $content .= $output;
        }

        $content .= "\n```\n";

        return $content;
    }

    private function createWorkSummary(): void
    {
        $this->console->line();
        $this->console->info('📝 Creating work summary...');

        $name = $this->console->ask('📝 Work summary name (without extension):');

        if (empty($name)) {
            $this->console->alertWarning('No name provided, skipping summary creation');

            return;
        }

        $date = date('Y-m-d');
        $time = date('H-i-s');
        $filename = "docs/work-summaries/{$date}T{$time}-{$name}.md";

        $this->ensureDirectory('docs/work-summaries');

        $this->console->line();
        $this->console->info('📋 Paste the AI response (CTRL+D to finish):');

        $content = '';
        $handle = fopen('php://stdin', 'r');
        while (($line = fgets($handle)) !== false) {
            $content .= $line;
        }
        fclose($handle);

        if (empty($content)) {
            $this->console->alertWarning('No content provided, skipping summary creation');

            return;
        }

        file_put_contents($filename, $content);
        $this->console->success("✅ Work summary created: {$filename}");

        // Ne pas ouvrir l'éditeur en mode non-interactif
        if (! $this->getFlag('no-interactive')) {
            $this->openFileInEditor($filename);
        }

        $this->console->line();
        $commitMsg = $this->console->ask('📝 Commit message (from AI):');

        if ($commitMsg) {
            $this->commitAndPush($commitMsg);
        }
    }

    private function openFileInEditor(string $filename): void
    {
        $this->console->info('📂 Opening file in editor...');

        $process = new Process(['which', 'code']);
        $process->run();

        if ($process->isSuccessful()) {
            $process = new Process(['code', $filename]);
            $process->run();
            $this->console->success('✅ File opened in VS Code');
        } else {
            $this->console->info("💡 Open manually with: code {$filename}");
        }
    }

    private function commitAndPush(string $message): void
    {
        $this->console->info('📦 Committing changes...');

        $process = new Process(['git', 'add', '.']);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Failed to add files: '.$process->getErrorOutput());

            return;
        }

        $process = new Process(['git', 'commit', '-m', $message]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Failed to commit: '.$process->getErrorOutput());

            return;
        }

        $this->console->success('✅ Commit created');
        $this->console->line();

        $pushConfirm = $this->console->confirm('🚀 Push the commit?', false);

        if ($pushConfirm) {
            $process = new Process(['git', 'branch', '--show-current']);
            $process->run();
            $branch = trim($process->getOutput());

            $process = new Process(['git', 'push', 'origin', $branch]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->console->error('❌ Failed to push: '.$process->getErrorOutput());

                return;
            }

            $this->console->success('✅ Commit pushed');
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}
