<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Directives\GitTagDirective;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;

/**
 * Integration tests for the GitTagDirective.
 *
 * @group integration
 * @group directives
 * @group git-tag
 */
final class GitTagDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private array $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Save original configuration
        $this->originalConfig = [
            'repositories' => config('utils.repositories', []),
            'default_extensions' => config('utils.default_extensions', []),
            'extension_recipes' => config('utils.extension_recipes', []),
        ];

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
        $kernel->addDirective(GitTagDirective::class);
    }

    protected function tearDown(): void
    {
        // Restore original configuration
        Config::set('utils.repositories', $this->originalConfig['repositories']);
        Config::set('utils.default_extensions', $this->originalConfig['default_extensions']);
        Config::set('utils.extension_recipes', $this->originalConfig['extension_recipes']);

        $this->service->destroy();
        parent::tearDown();
    }

    /**
     * Tests that the alias 'ugt' works correctly.
     */
    public function test_git_tag_alias_works(): void
    {
        // Act
        $response = $this->service->run('ugt minor --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/GIT TAG/', $output);
    }

    /**
     * Tests that the directive creates a patch tag by default.
     */
    public function test_git_tag_patch(): void
    {
        // Act
        $response = $this->service->run('ugt --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Type\s*:\s*patch/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
    }

    /**
     * Tests that the directive creates a minor tag.
     */
    public function test_git_tag_minor(): void
    {
        // Act
        $response = $this->service->run('ugt minor --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Type\s*:\s*minor/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
    }

    /**
     * Tests that the directive creates a major tag.
     */
    public function test_git_tag_major(): void
    {
        // Act
        $response = $this->service->run('ugt major --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Type\s*:\s*major/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
    }

    /**
     * Tests that the directive handles invalid tag type.
     */
    public function test_git_tag_invalid_type(): void
    {
        // Act
        $response = $this->service->run('ugt invalid --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Invalid tag type.*Using default: patch/', $output);
    }

    /**
     * Tests that the directive handles the --no-push flag.
     */
    public function test_git_tag_no_push(): void
    {
        // Act
        $response = $this->service->run('ugt --no-push --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Push\s*:\s*❌\s*No/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
    }

    /**
     * Tests that the directive handles the --dry-run flag.
     */
    public function test_git_tag_dry_run(): void
    {
        // Act
        $response = $this->service->run('ugt --dry-run --no-interactive');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
        $this->assertMatchesRegularExpression('/No actual changes were made/', $output);
    }

    /**
     * Tests that the directive handles message via custom data.
     */
    public function test_git_tag_custom_message(): void
    {
        // Act
        $response = $this->service->run('ugt --dry-run --no-interactive <message="Custom release message">');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Custom release message/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
    }

    /**
     * Tests that the directive handles all flags combined.
     */
    public function test_git_tag_all_flags(): void
    {
        // Act
        $response = $this->service->run('ugt major --no-push --dry-run <message="Major release">');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Type\s*:\s*major/', $output);
        $this->assertMatchesRegularExpression('/Push\s*:\s*❌\s*No/', $output);
        $this->assertMatchesRegularExpression('/Major release/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
        $this->assertMatchesRegularExpression('/No actual changes were made/', $output);
    }
}
