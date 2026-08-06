<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\UI;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;

final class DeploymentUI
{
    public static function displayConfiguration(Console $console, array $deploymentConfig): void
    {
        $console->info('📋 Deployment Configuration:');

        $config = MapCollection::from([
            'SSH Key' => $deploymentConfig['ssh_key'],
            'Remote Path' => $deploymentConfig['remote_path'],
            'Git Branch' => $deploymentConfig['git_branch'],
        ]);

        $console->raw(KeyValue::renderWithValueColor($config, 'cyan'));
        $console->line();
    }

    public static function displayResult(Console $console, DeploymentResultRecord $result, float $duration): void
    {
        $console->line();
        $console->info('📊 Summary:');

        $summary = MapCollection::from([
            'Duration' => number_format($duration, 2).'s',
            'Success' => $result->success ? '✅ Yes' : '❌ No',
            'Commands' => $result->commands_executed ? count($result->commands_executed) : 0,
        ]);

        $console->raw(KeyValue::renderWithValueColor($summary, 'yellow'));
        $console->line();

        if ($result->success) {
            $console->success('✨ '.$result->message);
        } else {
            $console->error('❌ '.$result->message);
            if ($result->error) {
                $console->line();
                $console->error('Error: '.$result->error);
            }
        }

        $console->render();
    }

    public static function displayConfirmation(Console $console): bool
    {
        $console->alertWarning('This will deploy the code to O2Switch');
        $console->line('   Use --force to skip confirmation');
        $console->line();

        return $console->confirm('🔄 Continue with deployment?', false);
    }

    public static function displayAfterExecute(Console $console, ExitCode $exitCode): void
    {
        $console->line();

        if ($exitCode->isSuccess()) {
            $console->success('🎉 Deployment completed successfully!');
        } else {
            $console->error('❌ Deployment failed (code: '.$exitCode->value.')');
        }

        $console->render();
    }
}
