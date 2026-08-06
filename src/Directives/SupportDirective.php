<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Enums\ListStyle;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\SetCollection;

/**
 * CLI directive to show support options for Andy Defer's open source work.
 *
 * @example
 * // Show quick support options
 * ./bin/afya utils:support
 *
 * // Show all support options
 * ./bin/afya utils:support --all
 */
final class SupportDirective extends AbstractDirective
{
    private Console $console;

    public function getSignature(): string
    {
        return 'utils:support {--all}#"Show all support options including packages and social links"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['us', 'uhm']);
    }

    public function getDescription(): string
    {
        return 'Show ways to support Andy Defer\'s open source work';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
    }

    protected function execute(): ExitCode
    {
        $all = $this->getFlag('all');

        $this->console->title('🌟 Support Open Source Work');
        $this->console->line('');

        $this->displayHeader();

        if ($all) {
            $this->displayAllWays();
        } else {
            $this->displayQuickWays();
        }

        $this->console->line('');
        $this->console->success('🙏 Every contribution matters!');
        $this->console->render();

        return ExitCode::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->console->info('  👤 Andy Defer');
        $this->console->line('  Fullstack Developer & Open Source Contributor');
        $this->console->line('');
    }

    private function displayQuickWays(): void
    {
        $this->console->info('📌 Quick ways to support:');
        $this->console->line('');

        $items = SetCollection::from([
            '⭐ Star repositories on GitHub',
            '🐛 Report issues you find',
            '💡 Suggest new features',
            '📝 Improve documentation',
            '🔀 Submit pull requests',
            '📢 Share with your network',
            '🏗️ Use the packages in your projects',
        ]);

        $this->console->list($items, ListStyle::BULLET);
        $this->console->line('');
        $this->console->info('💖 For financial support and more details:');
        $this->console->line('  Use --all to see all options');
        $this->console->line('  Use the alias: sponsor');
        $this->console->line('');
    }

    private function displayAllWays(): void
    {
        $this->console->info('🌐 Connect & Follow');
        $this->console->line('─────────────────────');

        $social = SetCollection::from([
            'GitHub     : github.com/andydefer',
            'LinkedIn   : in/andy-kani-3751a1249',
            'WhatsApp   : +243 827 833 329',
            'Facebook   : profile.php?id=100088554107596',
        ]);
        $this->console->list($social, ListStyle::ARROW);
        $this->console->line('');

        $this->console->info('💝 Ways to support:');
        $this->console->line('─────────────────────');
        $this->console->line('');

        $ways = SetCollection::from([
            '⭐ Star repositories',
            '🐛 Report bugs',
            '💡 Suggest features',
            '📝 Write documentation',
            '🔀 Contribute code',
            '💰 Financial support',
            '📢 Share with your network',
            '🏗️ Use the packages in your projects',
            '🎓 Provide feedback',
            '🤝 Collaborate on projects',
        ]);
        $this->console->list($ways, ListStyle::NUMBER);
        $this->console->line('');

        $this->console->info('📦 Packages:');
        $this->console->line('─────────────────────');
        $this->console->line('');

        $packages = SetCollection::from([
            'laravel-directive      - CLI framework for Laravel',
            'laravel-task           - Task orchestration for Laravel',
            'php-signature-parser   - Parse CLI signatures',
            'php-console-writer     - Console UI components',
            'domain-structures      - Domain-driven design structures',
            'laravel-utils          - Laravel utilities',
            'php-pawapay            - PawaPay API client',
            'laravel-images         - Image management',
            'laravel-cluster        - Clustering utilities',
            'laravel-kernel         - Kernel extensions',
            'laravel-addresses      - Address management',
            'laravel-chronos        - Time utilities',
            'laravel-hermes         - Message/notification system',
            'laravel-notification   - Notifications',
            'repository             - Repository pattern',
            'php-vo                 - Value objects',
            'php-actions            - Action pattern',
            'php-client             - HTTP client',
            'php-services           - Service layer',
            'more on GitHub...',
        ]);
        $this->console->list($packages, ListStyle::DASH);
        $this->console->line('');

        $this->console->info('💰 Financial Support:');
        $this->console->line('─────────────────────');
        $this->console->line('');
        $this->console->line('  If you find these packages useful, consider:');

        $financial = SetCollection::from([
            'GitHub Sponsors',
            'Buy Me A Coffee',
            'PayPal donations',
            'Hire for consulting/development',
        ]);
        $this->console->list($financial, ListStyle::BULLET);
        $this->console->line('');

        $this->console->info('🤝 Need help or want to collaborate?');
        $this->console->line('─────────────────────────────────────');
        $this->console->line('');

        $contact = SetCollection::from([
            '📧 Contact     : andydefer@gmail.com',
            '🐙 GitHub      : github.com/andydefer',
            '📱 WhatsApp    : +243 827 833 329',
        ]);
        $this->console->list($contact, ListStyle::STAR);
        $this->console->line('');
    }
}
