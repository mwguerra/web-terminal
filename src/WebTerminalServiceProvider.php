<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use MWGuerra\WebTerminal\Connections\ConnectionHandlerFactory;
use MWGuerra\WebTerminal\Console\Commands\TerminalInstallCommand;
use MWGuerra\WebTerminal\Console\Commands\TerminalLogsCleanupCommand;
use MWGuerra\WebTerminal\Console\Commands\TerminalMakePageCommand;
use MWGuerra\WebTerminal\Livewire\WebTerminal;
use MWGuerra\WebTerminal\Security\CommandSanitizer;
use MWGuerra\WebTerminal\Security\CommandValidator;
use MWGuerra\WebTerminal\Security\CredentialManager;
use MWGuerra\WebTerminal\Security\RateLimiter;
use MWGuerra\WebTerminal\Services\TerminalLogger;

class WebTerminalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/web-terminal.php',
            'web-terminal'
        );

        $this->app->singleton(CommandValidator::class, function ($app) {
            return new CommandValidator(
                config('web-terminal.allowed_commands', [])
            );
        });

        $this->app->singleton(CommandSanitizer::class, function ($app) {
            $blockedChars = config('web-terminal.security.blocked_characters', []);

            return new CommandSanitizer($blockedChars);
        });

        $this->app->singleton(RateLimiter::class, function ($app) {
            return RateLimiter::fromConfig();
        });

        $this->app->singleton(CredentialManager::class, function ($app) {
            return CredentialManager::fromConfig();
        });

        $this->app->singleton(ConnectionHandlerFactory::class, function ($app) {
            return new ConnectionHandlerFactory;
        });

        $this->app->singleton(TerminalLogger::class, function ($app) {
            return new TerminalLogger(config('web-terminal.logging', []));
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'web-terminal');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'web-terminal');
        $this->registerAssets();

        Livewire::component('web-terminal', WebTerminal::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                TerminalInstallCommand::class,
                TerminalLogsCleanupCommand::class,
                TerminalMakePageCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/web-terminal.php' => config_path('web-terminal.php'),
            ], 'web-terminal-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/web-terminal'),
            ], 'web-terminal-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/web-terminal'),
            ], 'web-terminal-lang');
        }
    }

    protected function registerAssets(): void
    {
        // Only register assets when Filament is installed
        if (class_exists(FilamentAsset::class)) {
            FilamentAsset::register([
                Css::make('web-terminal', __DIR__.'/../resources/dist/web-terminal.css'),
            ], 'mwguerra/web-terminal');
        }
    }
}
