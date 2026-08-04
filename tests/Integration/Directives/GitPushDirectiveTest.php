<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Directives\GitPushDirective;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;

/**
 * Integration tests for the GitPushDirective.
 *
 * @group integration
 * @group directives
 * @group git-push
 */
final class GitPushDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private array $originalRepositories;

    protected function setUp(): void
    {
        parent::setUp();

        // Save original configuration
        $this->originalRepositories = config('utils.repositories', []);

        // Configure test repositories (fake URLs)
        Config::set('utils.repositories', [
            'github' => 'git@github.com:test/repo.git',
            'o2switch' => 'ssh://user@test.com/home/user/git/repo.git',
        ]);

        // Rebind UtilsConfig with new config
        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        // Initialize test service
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $kernel = $this->service->getKernel();
        $kernel->addDirective(GitPushDirective::class);
    }

    protected function tearDown(): void
    {
        // Restore original configuration
        Config::set('utils.repositories', $this->originalRepositories);

        $this->service->destroy();
        parent::tearDown();
    }

    /**
     * Tests that the directive returns an error when message is missing.
     */
    public function test_git_push_requires_message(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-interactive --dry-run <message="">');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Commit message must contain at least one alphanumeric character', $response->output);
    }

    /**
     * Tests that the directive validates sources against configuration.
     */
    public function test_git_push_validates_sources(): void
    {
        // Act
        $response = $this->service->run('ugp [invalid-source] --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString("⚠️  Target 'invalid-source' does not exist in configuration", $response->output);
        $this->assertStringContainsString('No valid targets found', $response->output);
    }

    /**
     * Tests that the directive accepts valid sources.
     */
    public function test_git_push_accepts_valid_sources(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-tests --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Configuration:', $response->output);
        $this->assertStringContainsString('github', $response->output);
        $this->assertStringContainsString('Test commit', $response->output);
    }

    /**
     * Tests that the directive handles multiple sources.
     */
    public function test_git_push_handles_multiple_sources(): void
    {
        // Act
        $response = $this->service->run('ugp [github, o2switch] --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Configuration:', $response->output);
        $this->assertStringContainsString('github, o2switch', $response->output);
    }

    /**
     * Tests that the directive requires at least one source in non-interactive mode.
     */
    public function test_git_push_requires_sources_in_non_interactive(): void
    {
        // Act
        $response = $this->service->run('ugp --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('At least one target is required in non-interactive mode', $response->output);
    }

    /**
     * Tests that the directive handles folders parameter.
     */
    public function test_git_push_handles_folders(): void
    {
        // Act
        $response = $this->service->run('ugp [github] [src, resources/views] --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertion with preg_match
        $this->assertMatchesRegularExpression('/Folders\s*:\s*src,\s*resources\/views/', $output);
    }

    /**
     * Tests that the directive handles the --no-tests flag.
     */
    public function test_git_push_handles_no_tests_flag(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-tests --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertions with preg_match
        $this->assertMatchesRegularExpression('/Tests\s*:\s*⏭️\s*Skipped/', $output);
    }

    /**
     * Tests that the directive handles the --force-with-lease flag.
     */
    public function test_git_push_handles_force_with_lease_flag(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-tests --force-with-lease --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertion with preg_match
        $this->assertMatchesRegularExpression('/Force-with-lease\s*:\s*✅\s*Yes/', $output);
    }

    /**
     * Tests that the directive handles the --force flag.
     */
    public function test_git_push_handles_force_flag(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-tests --force --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertion with preg_match
        $this->assertMatchesRegularExpression('/Force\s*:\s*✅\s*Yes/', $output);
    }

    /**
     * Tests that the alias 'ugp' works correctly.
     */
    public function test_git_push_alias_works(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-tests --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 GIT PUSH', $response->output);
    }

    /**
     * Tests that the directive handles empty message gracefully.
     */
    public function test_git_push_handles_empty_message(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-interactive --dry-run <message="">');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Commit message must contain at least one alphanumeric character', $response->output);
    }

    /**
     * Tests that the directive handles whitespace-only message.
     */
    public function test_git_push_handles_whitespace_message(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-interactive --dry-run <message="   ">');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Commit message must contain at least one alphanumeric character', $response->output);
    }

    /**
     * Tests that the directive handles mixed sources (valid and invalid).
     */
    public function test_git_push_handles_mixed_sources(): void
    {
        // Act
        $response = $this->service->run('ugp [github, invalid-source] --no-interactive --dry-run <message="Test commit">');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertions with preg_match
        $this->assertMatchesRegularExpression("/⚠️\s*Target 'invalid-source' does not exist in configuration/", $output);
        $this->assertMatchesRegularExpression('/No valid targets found/', $output);
    }

    /**
     * Tests that the directive handles full command with all options.
     */
    public function test_git_push_full_command(): void
    {
        // Act
        $response = $this->service->run('ugp [github, o2switch] --no-tests --force-with-lease --no-interactive --dry-run <message="Full test commit">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertions with preg_match
        $this->assertMatchesRegularExpression('/Full test commit/', $output);
        $this->assertMatchesRegularExpression('/github,\s*o2switch/', $output);
        $this->assertMatchesRegularExpression('/Force-with-lease\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Tests\s*:\s*⏭️\s*Skipped/', $output);
    }

    /**
     * Tests that the directive uses the configured repositories from config.
     */
    public function test_git_push_uses_configured_repositories(): void
    {
        // Arrange: Modify configuration to add a new repository
        Config::set('utils.repositories', [
            'github' => 'git@github.com:test/repo.git',
            'o2switch' => 'ssh://user@test.com/home/user/git/repo.git',
            'gitlab' => 'git@gitlab.com:test/repo.git',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        // Re-create service with new config
        $this->service->destroy();
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );
        $this->service->getKernel()->addDirective(GitPushDirective::class);

        // Act
        $response = $this->service->run('ugp [gitlab] --no-tests --no-interactive --dry-run <message="Test with gitlab">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('gitlab', $response->output);
    }

    /**
     * Tests that the directive handles dry-run mode.
     */
    public function test_git_push_dry_run_mode(): void
    {
        // Act
        $response = $this->service->run('ugp [github] --no-interactive --dry-run <message="Dry run test">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertions with preg_match
        $this->assertMatchesRegularExpression('/Targets\s*:\s*github/', $output);
        $this->assertMatchesRegularExpression('/Dry run test/', $output);
        $this->assertStringContainsString('Dry run completed successfully', $output);
        $this->assertStringContainsString('No actual changes were made.', $output);
    }

    /**
     * Tests that dry-run mode works with multiple sources.
     */
    public function test_git_push_dry_run_multiple_sources(): void
    {
        // Act
        $response = $this->service->run('ugp [github, o2switch] --no-interactive --dry-run <message="Dry run all flags">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('github, o2switch', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    /**
     * Tests that dry-run mode works with folders.
     */
    public function test_git_push_dry_run_with_folders(): void
    {
        // Act
        $response = $this->service->run('ugp [github] [src, resources/views] --no-interactive --dry-run <message="Dry run with folders">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertions with preg_match
        $this->assertMatchesRegularExpression('/Folders\s*:\s*src,\s*resources\/views/', $output);
        $this->assertStringContainsString('Dry run completed successfully', $output);
    }

    /**
     * Tests that dry-run mode works with all flags.
     */
    public function test_git_push_dry_run_with_all_flags(): void
    {
        // Act
        $response = $this->service->run('ugp [github, o2switch] --no-tests --force-with-lease --no-interactive --dry-run <message="Commit de Test">');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Flexible assertions with regex
        $this->assertMatchesRegularExpression('/Force-with-lease\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Tests\s*:\s*⏭️\s*Skipped/', $output);
        $this->assertStringContainsString('Dry run completed successfully', $output);
    }
}
