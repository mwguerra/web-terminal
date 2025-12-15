<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use MWGuerra\WebTerminal\Schemas\Components\WebTerminal;
use MWGuerra\WebTerminal\WebTerminalPlugin;

class Terminal extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'web-terminal::filament.pages.terminal';

    protected static string $routePath = 'terminal';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return static::$navigationIcon
            ?? WebTerminalPlugin::current()?->getTerminalNavigationIcon()
            ?? 'heroicon-o-command-line';
    }

    public function getTitle(): string|Htmlable
    {
        return __('web-terminal::terminal.pages.terminal.title');
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel
            ?? WebTerminalPlugin::current()?->getTerminalNavigationLabel()
            ?? __('web-terminal::terminal.navigation.terminal');
    }

    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort
            ?? WebTerminalPlugin::current()?->getTerminalNavigationSort()
            ?? 100;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup
            ?? WebTerminalPlugin::current()?->getTerminalNavigationGroup()
            ?? __('web-terminal::terminal.navigation.tools');
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return static::$slug ?? 'terminal';
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('web-terminal::terminal.pages.terminal.local_terminal'))
                    ->description(__('web-terminal::terminal.pages.terminal.local_terminal_description'))
                    ->icon('heroicon-o-command-line')
                    ->schema([
                        WebTerminal::make()
                            ->key('local-terminal')
                            ->local()
                            ->allowedCommands([
                                'ls', 'ls *', 'pwd', 'whoami', 'date', 'uptime',
                                'cat *', 'head *', 'tail *', 'wc *',
                                'php artisan *', 'composer *',
                            ])
                            ->workingDirectory(base_path())
                            ->timeout(30)
                            ->prompt('$ ')
                            ->historyLimit(50)
                            ->height('400px')
                            ->title(__('web-terminal::terminal.pages.terminal.local_terminal'))
                            ->windowControls(true)
                            ->startConnected(false)
                            ->log(
                                enabled: true,
                                connections: true,
                                commands: true,
                                output: true,
                                identifier: 'local-terminal',
                            ),
                    ]),
            ]);
    }
}
