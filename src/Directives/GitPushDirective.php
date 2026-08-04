<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Symfony\Component\Process\Process;

final class GitPushDirective extends AbstractDirective
{
    private Console $console;

    private UtilsConfigInterface $config;

    private array $repositories;

    public function getSignature(): string
    {
        return 'utils:git-push 
                {message=?}#"Commit message" 
                {sources*}#"Repository aliases to push to (empty = push to all)" 
                {--no-tests}#"Skip running tests before push" 
                {--force}#"Force push even if tests fail"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ugp']);
    }

    public function getDescription(): string
    {
        return 'Push code to configured remote repositories with interactive mode';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->loadConfiguration();

        $this->console->title('🚀 GIT PUSH');
        $this->console->separatorDouble();
        $this->console->line();
    }

    private function loadConfiguration(): void
    {
        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);
        $this->repositories = $this->config->getRepositories();
    }

    protected function execute(): ExitCode
    {
        $message = $this->getArgument('message');
        $sources = $this->getVariadic('sources');
        $noTests = $this->getFlag('no-tests');
        $force = $this->getFlag('force');

        $isInteractive = $message === null || empty($sources);

        if ($isInteractive) {
            $this->console->info('📝 Mode interactif activé');
            $this->console->line();

            $answers = $this->console->form()
                ->title('📋 Configuration du push')
                ->line()
                ->ask('💬 Message du commit :', 'message', null, 'yellow')
                ->multiChoice('🎯 Choisissez les cibles :', 'sources', array_keys($this->repositories), array_keys($this->repositories))
                ->confirm('🧪 Exécuter les tests ?', 'tests', true)
                ->submit();

            $message = $answers->get('message');
            $sources = $answers->get('sources');
            $runTests = $answers->get('tests');
        } else {
            if (empty($sources)) {
                $this->console->info('📋 Aucune cible spécifiée, push vers toutes les cibles...');
                $this->console->line();

                $confirm = $this->console->form()
                    ->confirm('⚠️  Pousser vers toutes les cibles configurées ?', 'confirm', false)
                    ->submit();

                if (! $confirm->get('confirm')) {
                    $this->console->error('❌ Opération annulée');

                    return ExitCode::FAILURE;
                }

                $sources = array_keys($this->repositories);
            }

            $runTests = ! $noTests;
        }

        if ($message === null || trim($message) === '') {
            $this->console->error('❌ Le message du commit est obligatoire');

            return ExitCode::FAILURE;
        }

        $validSources = $this->validateSources($sources);
        if (empty($validSources)) {
            $this->console->error('❌ Aucune cible valide trouvée');

            return ExitCode::FAILURE;
        }

        $this->displayConfiguration($message, $validSources, $runTests, $force);

        if ($runTests) {
            $testResult = $this->handleTests($force);
            if ($testResult !== ExitCode::SUCCESS) {
                return $testResult;
            }
        } else {
            $this->console->info('⏭️  Tests ignorés');
            $this->console->line();
        }

        $commitResult = $this->commitChanges($message);
        if ($commitResult !== ExitCode::SUCCESS) {
            $this->console->error('❌ Échec du commit');

            return ExitCode::FAILURE;
        }

        $this->console->success('✅ Commit effectué avec succès');
        $this->console->line();

        $pushResult = $this->pushToRemotes($validSources);
        if ($pushResult !== ExitCode::SUCCESS) {
            $this->console->error('❌ Échec du push');

            return ExitCode::FAILURE;
        }

        $this->console->success('✅ Push effectué avec succès');
        $this->console->line();

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->console->newLine();
        if ($exitCode === ExitCode::SUCCESS) {
            $this->console->success('✅ Opération terminée avec succès !');
        } else {
            $this->console->error('❌ Opération échouée');
        }
        $this->console->render();
    }

    private function validateSources(array $sources): array
    {
        $valid = [];
        $available = array_keys($this->repositories);

        foreach ($sources as $source) {
            if (in_array($source, $available, true)) {
                $valid[] = $source;
            } else {
                $this->console->alertWarning("⚠️  La cible '{$source}' n'existe pas dans la configuration");
            }
        }

        return $valid;
    }

    private function displayConfiguration(string $message, array $sources, bool $runTests, bool $force): void
    {
        $this->console->info('📋 Configuration :');
        $this->console->line();
        $this->console->keyValueWithValueColor([
            '💬 Message' => $message,
            '🎯 Cibles' => implode(', ', $sources),
            '🧪 Tests' => $runTests ? '✅ Activés' : '⏭️  Ignorés',
            '🔒 Force' => $force ? '✅ Oui' : '❌ Non',
        ], 'green');
        $this->console->line();
    }

    private function handleTests(bool $force): ExitCode
    {
        $this->console->info('🧪 Exécution des tests...');
        $this->console->line();

        $testResult = $this->runTests();

        if ($testResult !== ExitCode::SUCCESS) {
            if ($force) {
                $this->console->alertWarning('⚠️  Les tests ont échoué mais --force est activé, on continue...');
                $this->console->line();

                return ExitCode::SUCCESS;
            }

            $this->console->error('❌ Les tests ont échoué. Utilisez --force pour ignorer.');

            return ExitCode::FAILURE;
        }

        $this->console->success('✅ Tests passés avec succès');
        $this->console->line();

        return ExitCode::SUCCESS;
    }

    private function runTests(): ExitCode
    {
        $process = new Process(['./vendor/bin/phpunit', '--stop-on-failure']);
        $process->setTimeout(300);
        $process->run();

        if ($process->getOutput()) {
            $this->console->line($process->getOutput());
        }

        if ($process->getErrorOutput()) {
            $this->console->error($process->getErrorOutput());
        }

        return $process->isSuccessful() ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function commitChanges(string $message): ExitCode
    {
        $process = new Process(['git', 'add', '.']);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Erreur lors du git add : '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        $process = new Process(['git', 'commit', '-m', $message]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Erreur lors du commit : '.$process->getErrorOutput());

            if (str_contains($process->getErrorOutput(), 'nothing to commit')) {
                $this->console->info('ℹ️  Aucun changement à committer');

                return ExitCode::SUCCESS;
            }

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private function pushToRemotes(array $sources): ExitCode
    {
        $process = new Process(['git', 'branch', '--show-current']);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Impossible de déterminer la branche actuelle');

            return ExitCode::FAILURE;
        }

        $branch = trim($process->getOutput());

        foreach ($sources as $source) {
            $remoteUrl = $this->repositories[$source] ?? null;

            if (! $remoteUrl) {
                $this->console->alertWarning("   ⚠️  La cible '{$source}' n'a pas d'URL configurée");

                continue;
            }

            $this->console->info("   📤 Push vers {$source} ({$remoteUrl})...");

            $process = new Process(['git', 'push', $remoteUrl, $branch]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->console->error("   ❌ Échec du push vers {$source} : ".$process->getErrorOutput());

                return ExitCode::FAILURE;
            }

            $this->console->success("   ✅ Push vers {$source} réussi");
        }

        return ExitCode::SUCCESS;
    }
}
