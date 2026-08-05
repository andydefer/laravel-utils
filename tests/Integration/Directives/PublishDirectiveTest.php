<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Directives\PublishDirective;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Integration tests for the PublishDirective.
 *
 * @group integration
 * @group directives
 * @group publish
 */
#[AllowMockObjectsWithoutExpectations]
final class PublishDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private string $tempSourceDir;

    private string $tempTargetDir;

    private UtilsConfigInterface&MockObject $configMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempSourceDir = sys_get_temp_dir().'/publish_test_source_'.uniqid();
        $this->tempTargetDir = sys_get_temp_dir().'/publish_test_target_'.uniqid();

        $this->createSourceStructure();

        // Créer le mock de UtilsConfigInterface
        $this->configMock = $this->createMock(UtilsConfigInterface::class);
        $this->configMock
            ->method('getPublishSourcePath')
            ->willReturn($this->tempSourceDir);
        $this->configMock
            ->method('getPublishTargetPath')
            ->willReturn($this->tempTargetDir);

        // Rebinder le mock dans le conteneur
        $this->app->instance(UtilsConfigInterface::class, $this->configMock);

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $kernel = $this->service->getKernel();
        $kernel->addDirective(PublishDirective::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempSourceDir)) {
            File::deleteDirectory($this->tempSourceDir);
        }
        if (is_dir($this->tempTargetDir)) {
            File::deleteDirectory($this->tempTargetDir);
        }

        $this->service->destroy();
        parent::tearDown();
    }

    private function createSourceStructure(): void
    {
        mkdir($this->tempSourceDir.'/O2switch', 0755, true);

        file_put_contents(
            $this->tempSourceDir.'/O2switch/DeployDirective.php',
            '<?php namespace App\Directives\O2switch; class DeployDirective {}'
        );

        file_put_contents(
            $this->tempSourceDir.'/README.md',
            '# README'
        );

        file_put_contents(
            $this->tempSourceDir.'/AnotherDirective.php',
            '<?php namespace App\Directives; class AnotherDirective {}'
        );
    }

    public function test_publish_alias_works(): void
    {
        $response = $this->service->run('up');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('PUBLISH DIRECTIVES', $response->output);
    }

    public function test_publish_displays_source_and_target(): void
    {
        $response = $this->service->run('utils:publish');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Source: '.$this->tempSourceDir, $response->output);
        $this->assertStringContainsString('Target: '.$this->tempTargetDir, $response->output);
    }

    public function test_publish_copies_php_files_only(): void
    {
        $response = $this->service->run('utils:publish --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $this->assertTrue(File::exists($this->tempTargetDir.'/O2switch/DeployDirective.php'));
        $this->assertTrue(File::exists($this->tempTargetDir.'/AnotherDirective.php'));
        $this->assertFalse(File::exists($this->tempTargetDir.'/README.md'));
    }

    public function test_publish_creates_target_directory(): void
    {
        $response = $this->service->run('utils:publish --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertTrue(File::isDirectory($this->tempTargetDir));
        $this->assertStringContainsString('Created directory:', $response->output);
    }

    public function test_publish_shows_summary(): void
    {
        $response = $this->service->run('utils:publish --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('Summary:', $output);
        $this->assertMatchesRegularExpression('/Copied:\s*2\s*file\(s\)/', $output);
    }

    public function test_publish_skips_existing_files_without_force(): void
    {
        $this->service->run('utils:publish --force');

        $response = $this->service->run('utils:publish');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('Skipping:', $output);
        $this->assertStringContainsString('already exists, use --force to overwrite', $output);
    }

    public function test_publish_overwrites_existing_files_with_force(): void
    {
        $this->service->run('utils:publish --force');

        file_put_contents(
            $this->tempSourceDir.'/O2switch/DeployDirective.php',
            '<?php namespace App\Directives\O2switch; class DeployDirective { public function test() {} }'
        );

        $response = $this->service->run('utils:publish --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertStringContainsString('Published: DeployDirective.php', $output);

        $content = File::get($this->tempTargetDir.'/O2switch/DeployDirective.php');
        $this->assertStringContainsString('public function test()', $content);
    }

    public function test_publish_completes_successfully(): void
    {
        $response = $this->service->run('utils:publish --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Publishing completed successfully!', $response->output);
    }

    public function test_publish_handles_subdirectories(): void
    {
        mkdir($this->tempSourceDir.'/O2switch/SubDir', 0755, true);
        file_put_contents(
            $this->tempSourceDir.'/O2switch/SubDir/SubDirective.php',
            '<?php namespace App\Directives\O2switch\SubDir; class SubDirective {}'
        );

        $response = $this->service->run('utils:publish --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertTrue(File::exists($this->tempTargetDir.'/O2switch/SubDir/SubDirective.php'));
    }

    public function test_publish_fails_when_source_not_found(): void
    {
        // Créer un nouveau mock pour ce test spécifique
        $configMock = $this->createMock(UtilsConfigInterface::class);
        $configMock
            ->method('getPublishSourcePath')
            ->willReturn('/invalid/path/that/does/not/exist');
        $configMock
            ->method('getPublishTargetPath')
            ->willReturn($this->tempTargetDir);

        $this->app->instance(UtilsConfigInterface::class, $configMock);

        // Recréer le service
        $this->service->destroy();
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );
        $this->service->getKernel()->addDirective(PublishDirective::class);

        $response = $this->service->run('utils:publish');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Source directory not found', $response->output);
    }
}
