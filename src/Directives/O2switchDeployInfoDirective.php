<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Enums\ListStyle;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\DomainStructures\Utils\SetCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;

/**
 * CLI directive to display information about the O2Switch deployment process.
 *
 * @example
 * // Show deployment information
 * ./bin/afya utils:o2d-info
 *
 * // Show detailed information with configuration
 * ./bin/afya utils:o2d-info --verbose
 */
final class O2switchDeployInfoDirective extends AbstractDirective
{
    private Console $console;

    private UtilsConfigInterface $config;

    private array $deploymentConfig;

    public function getSignature(): string
    {
        return 'utils:o2d-info {--verbose}#"Show detailed information with current configuration"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['o2d-info', 'deploy-info']);
    }

    public function getDescription(): string
    {
        return 'Display information about the O2Switch deployment process';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->loadConfiguration();

        $this->console->title('🚀 O2SWITCH DEPLOYMENT INFORMATION');
        $this->console->separatorDouble();
        $this->console->line();
    }

    private function loadConfiguration(): void
    {
        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);
        $this->deploymentConfig = $this->config->getDeploymentConfig();
    }

    protected function execute(): ExitCode
    {
        $verbose = $this->getFlag('verbose');

        $this->displayOverview();
        $this->displayPipelineTimeline();

        if ($verbose) {
            $this->displayConfiguration();
            $this->displayFlags();
            $this->displayAssetsConfiguration();
        }

        $this->displayQuickStart();

        $this->console->render();

        return ExitCode::SUCCESS;
    }

    private function displayOverview(): void
    {
        $this->console->info('📋 Deployment Overview');
        $this->console->separator('-', 48);
        $this->console->line('');

        $this->console->info('🎯 Target Server: O2Switch');
        $this->console->info('📦 Package: laravel-utils');
        $this->console->line('');

        $steps = SetCollection::from([
            '🔍 Check server connectivity',
            '📦 Deploy code via Git',
            '📦 Install Composer dependencies',
            '🎨 Build frontend assets',
            '📤 Export assets (images, videos)',
            '⚙️ Setup environment (.env)',
            '🔗 Setup storage links',
            '🚀 Laravel optimization (cache, config, routes)',
            '🔧 Execute custom pipelines',
            '✅ Finalize deployment',
        ]);

        $this->console->info('📌 Deployment Steps:');
        $this->console->list($steps, ListStyle::NUMBER);
        $this->console->line('');
    }

    private function displayPipelineTimeline(): void
    {
        $this->console->info('⚙️ Pipeline Execution Timeline');
        $this->console->separator('-', 48);
        $this->console->line('');

        $events = ListCollection::from([
            ListCollection::from(['1', 'CheckServerConnectivityOperation', 'SSH connectivity & path']),
            ListCollection::from(['2', 'DeployCodeOperation', 'git fetch & reset']),
            ListCollection::from(['3', 'SetupDependenciesOperation', 'composer install']),
            ListCollection::from(['4', 'SetupFrontendAssetsOperation', 'npm install & build']),
            ListCollection::from(['5', 'ExportAssetsOperation', 'images/videos (if configured)']),
            ListCollection::from(['6', 'SetupEnvironmentOperation', '.env & key:generate']),
            ListCollection::from(['7', 'SetupStorageOperation', 'php artisan storage:link']),
            ListCollection::from(['8', 'SetupLaravelOptimizationOperation', 'cache & migrate']),
            ListCollection::from(['9', 'ExecutePipelinesOperation', 'custom pipelines (if configured)']),
        ]);

        $statuses = ['info', 'info', 'info', 'info', 'info', 'info', 'info', 'info', 'info'];

        $this->console->timelineWithStatus($events, $statuses);
        $this->console->line('');
    }

    private function displayConfiguration(): void
    {
        $this->console->info('🔧 Current Configuration');
        $this->console->separator('-', 48);
        $this->console->line('');

        $this->console->keyValue([
            'SSH Key' => $this->deploymentConfig['ssh_key'] ?? 'Not configured',
            'Remote Path' => $this->deploymentConfig['remote_path'] ?? 'Not configured',
            'Git Branch' => $this->deploymentConfig['git_branch'] ?? 'Not configured',
        ]);

        $this->console->line('');

        $hlsConfig = $this->config->getHlsConfig();
        $this->console->info('📹 HLS Configuration:');
        $this->console->keyValue([
            'Segment Duration' => $hlsConfig['segment_duration'].'s',
            'CRF' => $hlsConfig['crf'],
            'Preset' => $hlsConfig['preset'],
            'Audio Bitrate' => $hlsConfig['audio_bitrate'],
            'Resolutions' => implode(', ', $hlsConfig['resolutions']),
        ]);

        $this->console->line('');

        $videoConfig = $this->config->getVideoCompressConfig();
        $this->console->info('🎬 Video Compression Configuration:');
        $this->console->keyValue([
            'Width' => $videoConfig['width'] > 0 ? $videoConfig['width'].'px' : 'Auto',
            'Height' => $videoConfig['height'] > 0 ? $videoConfig['height'].'px' : 'Auto',
            'CRF' => $videoConfig['crf'],
            'Preset' => $videoConfig['preset'],
            'Video Codec' => $videoConfig['video_codec'],
            'Audio Codec' => $videoConfig['audio_codec'],
            'Audio Bitrate' => $videoConfig['audio_bitrate'],
            'Pixel Format' => $videoConfig['pixel_format'],
        ]);

        $this->console->line('');

        $imageConfig = $this->config->getImageCompressConfig();
        $this->console->info('🖼️ Image Compression Configuration:');
        $this->console->keyValue([
            'PNG Quality' => $imageConfig['png_quality'],
            'JPG Quality' => $imageConfig['jpg_quality'],
            'Max Size' => $imageConfig['max_size'] > 0 ? $imageConfig['max_size'].'KB' : 'Disabled',
            'Strip Meta' => $imageConfig['strip_meta'] ? '✅ Yes' : '❌ No',
        ]);

        $this->console->line('');
    }

    private function displayFlags(): void
    {
        $this->console->info('🚩 Available Flags');
        $this->console->separator('-', 48);
        $this->console->line('');

        $flags = SetCollection::from([
            '--force          Skip confirmation and force deployment',
            '--verbose        Show detailed output',
            '--dry-run        Simulate the operation without actually executing',
            '--no-compress    Skip compression of assets before export',
            '--hls            Generate HLS streams for videos before export',
            '--skip-export    Skip assets export step',
        ]);

        $this->console->list($flags, ListStyle::BULLET);
        $this->console->line('');
    }

    private function displayAssetsConfiguration(): void
    {
        $assets = $this->config->getExportAssets();

        $this->console->info('📤 Export Assets');
        $this->console->separator('-', 48);
        $this->console->line('');

        if (empty($assets)) {
            $this->console->line('  ⚠️ No assets configured for export');
            $this->console->line('  💡 Add assets in config/utils.php under "export_assets"');
        } else {
            $assetItems = SetCollection::from($assets);
            $this->console->list($assetItems, ListStyle::CHECK);
            $this->console->line('');
            $this->console->line('  📌 These assets will be compressed and exported during deployment');
        }

        $this->console->line('');
    }

    private function displayQuickStart(): void
    {
        $this->console->info('🚀 Quick Start');
        $this->console->separator('-', 48);
        $this->console->line('');

        $commands = SetCollection::from([
            '# Basic deployment',
            'bin/afya o2d',
            '# Deployment with force (skip confirmation)',
            'bin/afya o2d --force',
            '# Dry run (simulate)',
            'bin/afya o2d --dry-run',
            '# Deployment with HLS generation',
            'bin/afya o2d --hls --force',
            '# Deployment skipping export',
            'bin/afya o2d --skip-export --force',
            '# Verbose deployment',
            'bin/afya o2d --verbose --force',
        ]);

        $this->console->list($commands, ListStyle::BULLET);
        $this->console->line('');

        $this->console->info('📚 More information:');
        $this->console->line('  • Use --verbose flag for detailed output');
        $this->console->line('  • Check config/utils.php for configuration');
        $this->console->line('  • Run utils:publish to publish directives');
        $this->console->line('');
    }
}
