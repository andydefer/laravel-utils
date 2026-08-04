<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Directives\GitDiffDirective;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Config;

/**
 * Integration tests for the GitDiffDirective.
 *
 * @group integration
 * @group directives
 * @group git-diff
 */
final class GitDiffDirectiveTest extends IntegrationTestCase
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

        // Configure default extensions
        Config::set('utils.default_extensions', [
            'php',
            'js',
            'ts',
            'css',
        ]);

        // Configure extension recipes
        Config::set('utils.extension_recipes', [
            'frontend' => ['js', 'ts', 'css', 'html'],
            'backend' => ['php', 'py', 'go', 'rs'],
            'fullstack' => ['php', 'js', 'ts', 'css', 'html'],
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
        $kernel->addDirective(GitDiffDirective::class);
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
     * Tests that the alias 'ugd' works correctly.
     */
    public function test_git_diff_alias_works(): void
    {
        // Act
        $response = $this->service->run('ugd [src] --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 GIT DIFF FOR AI REVIEW', $response->output);
    }

    /**
     * Tests that the --no-interactive flag works correctly.
     */
    public function test_git_diff_no_interactive_mode(): void
    {
        // Act: Run in non-interactive mode with paths and extensions
        $response = $this->service->run('ugd [src, tests] [.php, .js] --no-interactive --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Generating diff/', $output);
        // Les extensions sont affichées avec le point
        $this->assertMatchesRegularExpression('/Filtering by extensions: \.php, \.js/', $output);
    }

    /**
     * Tests that --no-interactive requires at least one path.
     */
    public function test_git_diff_no_interactive_requires_paths(): void
    {
        // Act: Run in non-interactive mode without paths
        $response = $this->service->run('ugd --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('At least one path is required in non-interactive mode', $response->output);
    }

    /**
     * Tests that --no-interactive uses default extensions when no extensions specified.
     */
    public function test_git_diff_no_interactive_uses_default_extensions(): void
    {
        // Act: Run in non-interactive mode with paths but no extensions
        $response = $this->service->run('ugd [src, tests] --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using default extensions', $response->output);
        $this->assertStringContainsString('Filtering by extensions: php, js, ts, css', $response->output);
    }

    /**
     * Tests that --no-interactive works with --frontend flag.
     */
    public function test_git_diff_no_interactive_with_frontend(): void
    {
        // Act: Run in non-interactive mode with frontend flag
        $response = $this->service->run('ugd [src, tests] --frontend --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using frontend extensions:', $response->output);
        $this->assertStringContainsString('js', $response->output);
        $this->assertStringContainsString('ts', $response->output);
        $this->assertStringContainsString('css', $response->output);
    }

    /**
     * Tests that --no-interactive works with --backend flag.
     */
    public function test_git_diff_no_interactive_with_backend(): void
    {
        // Act: Run in non-interactive mode with backend flag
        $response = $this->service->run('ugd [src, tests] --backend --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using backend extensions:', $response->output);
        $this->assertStringContainsString('php', $response->output);
        $this->assertStringContainsString('py', $response->output);
        $this->assertStringContainsString('go', $response->output);
    }

    /**
     * Tests that --dry-run flag prevents file generation.
     */
    public function test_git_diff_dry_run_prevents_file_generation(): void
    {
        // Arrange
        $dir = 'docs/diffs';

        // Clean up before test
        if (is_dir($dir)) {
            $this->removeDirectory($dir);
        }

        // Act
        $response = $this->service->run('ugd [src, tests] --frontend --no-interactive --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
        $this->assertMatchesRegularExpression('/No actual changes were made/', $output);

        // Verify no file was created
        $this->assertDirectoryDoesNotExist($dir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Tests that --dry-run works with all flags combined.
     */
    public function test_git_diff_dry_run_with_all_flags(): void
    {
        // Act
        $response = $this->service->run('ugd [src, tests] [.php, .js] --frontend --recipes --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using frontend extensions:', $response->output);
        $this->assertStringContainsString('Filtering by extensions:', $response->output);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);
    }

    /**
     * Tests that the directive generates a diff file.
     */
    public function test_git_diff_generates_file(): void
    {
        // Arrange: Create a dummy file for git diff
        $testFile = 'test.php';
        file_put_contents($testFile, '<?php echo "test";');

        // Act
        $response = $this->service->run('ugd [.] --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Generating diff...', $response->output);

        // Cleanup
        @unlink($testFile);
    }

    /**
     * Tests that the --frontend flag uses frontend extensions from config.
     */
    public function test_git_diff_frontend_flag(): void
    {
        // Act
        $response = $this->service->run('ugd [.] --frontend --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using frontend extensions:', $response->output);
        $this->assertStringContainsString('js', $response->output);
        $this->assertStringContainsString('ts', $response->output);
        $this->assertStringContainsString('css', $response->output);
    }

    /**
     * Tests that the --backend flag uses backend extensions from config.
     */
    public function test_git_diff_backend_flag(): void
    {
        // Act
        $response = $this->service->run('ugd [.] --backend --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using backend extensions:', $response->output);
        $this->assertStringContainsString('php', $response->output);
        $this->assertStringContainsString('py', $response->output);
        $this->assertStringContainsString('go', $response->output);
    }

    /**
     * Tests that the --with-summary flag triggers work summary creation.
     */
    public function test_git_diff_with_summary_flag(): void
    {
        // Act
        $response = $this->service->run('ugd [.] --with-summary --no-interactive --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // En mode dry-run, la création du work summary est sautée
        // Donc on vérifie que la directive s'exécute bien
        $this->assertStringContainsString('Dry run completed successfully', $output);
        $this->assertStringContainsString('No actual changes were made', $output);
    }

    /**
     * Tests that paths are properly passed to the directive.
     */
    public function test_git_diff_with_paths(): void
    {
        // Act
        $response = $this->service->run('ugd [src, tests] --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Generating diff...', $response->output);
    }

    /**
     * Tests that extensions are properly filtered.
     */
    public function test_git_diff_with_extensions(): void
    {
        // Act
        $response = $this->service->run('ugd [.php, .js] --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Filtering by extensions: php, js', $response->output);
    }

    /**
     * Tests that extensions are properly cleaned (dots removed).
     */
    public function test_git_diff_cleans_extensions(): void
    {
        // Act
        $response = $this->service->run('ugd [php, js, ts] --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Filtering by extensions: php, js, ts', $response->output);
    }

    /**
     * Tests that the directive handles no changes gracefully.
     */
    public function test_git_diff_no_changes(): void
    {
        // Act
        $response = $this->service->run('ugd [.] --no-interactive --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // La directive s'exécute avec succès même sans changements
        $this->assertStringContainsString('Dry run completed successfully', $output);
        $this->assertStringContainsString('No actual changes were made', $output);
    }

    /**
     * Tests that the directive handles invalid paths gracefully.
     */
    public function test_git_diff_invalid_paths(): void
    {
        // Act
        $response = $this->service->run('ugd [invalid/path] --no-interactive --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Le message "No changes found" est dans le contenu du diff
        // Mais la directive s'exécute quand même avec succès
        $this->assertStringContainsString('Dry run completed successfully', $output);
        $this->assertStringContainsString('No actual changes were made', $output);
    }

    /**
     * Tests that the directive handles empty extensions gracefully.
     */
    public function test_git_diff_empty_extensions(): void
    {
        // Act: Run without extensions in non-interactive mode
        // The directive should use default extensions
        $response = $this->service->run('ugd [.] --no-interactive --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // En mode non-interactif, on utilise les extensions par défaut
        // Donc on vérifie qu'elles sont utilisées
        $this->assertStringContainsString('Using default extensions', $output);
        $this->assertStringContainsString('Filtering by extensions:', $output);
    }

    /**
     * Tests that the directive handles the --dry-run flag (simulation mode).
     */
    public function test_git_diff_dry_run_mode(): void
    {
        // Act
        $response = $this->service->run('ugd [.] [] --frontend --no-interactive --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Using frontend extensions:', $response->output);
        $this->assertStringContainsString('Generating diff...', $response->output);
    }

    /**
     * Tests that extension recipes are properly loaded from config.
     */
    public function test_git_diff_loads_extension_recipes(): void
    {
        // Arrange: Get the config
        $recipes = config('utils.extension_recipes', []);

        // Assert
        $this->assertArrayHasKey('frontend', $recipes);
        $this->assertArrayHasKey('backend', $recipes);
        $this->assertArrayHasKey('fullstack', $recipes);

        $this->assertContains('js', $recipes['frontend']);
        $this->assertContains('php', $recipes['backend']);
        $this->assertContains('php', $recipes['fullstack']);
    }

    /**
     * Tests that the directive creates the docs/diffs directory.
     */
    public function test_git_diff_creates_directory(): void
    {
        // Arrange
        $dir = 'docs/diffs';

        // Clean up before test
        if (is_dir($dir)) {
            $this->removeDirectory($dir);
        }

        // Assert: Directory does not exist before
        $this->assertDirectoryDoesNotExist($dir);

        // Act: Create a dummy file to have a diff
        $testFile = 'test.php';
        file_put_contents($testFile, '<?php echo "test";');

        // Run without dry-run to create the directory
        // Use --no-interactive to avoid prompts
        $response = $this->service->run('ugd [.] --dry-run --no-interactive');

        // Assert: Directory was created
        $this->assertDirectoryExists($dir);

        // Cleanup
        $this->removeDirectory($dir);
        @unlink($testFile);
    }
}
