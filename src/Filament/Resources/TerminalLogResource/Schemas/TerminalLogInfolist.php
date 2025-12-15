<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Filament\Resources\TerminalLogResource\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MWGuerra\WebTerminal\Models\TerminalLog;

class TerminalLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('web-terminal::terminal.infolist.event_information'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('event_type')
                                    ->label(__('web-terminal::terminal.infolist.event_type'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        TerminalLog::EVENT_CONNECTED => 'success',
                                        TerminalLog::EVENT_DISCONNECTED => 'warning',
                                        TerminalLog::EVENT_COMMAND => 'info',
                                        TerminalLog::EVENT_OUTPUT => 'gray',
                                        TerminalLog::EVENT_ERROR => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                                TextEntry::make('connection_type')
                                    ->label(__('web-terminal::terminal.infolist.connection_type'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        TerminalLog::CONNECTION_LOCAL => 'primary',
                                        TerminalLog::CONNECTION_SSH => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                            ]),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.timing'))
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('web-terminal::terminal.infolist.timestamp'))
                                    ->dateTime('F j, Y g:i:s A'),

                                TextEntry::make('execution_time_seconds')
                                    ->label(__('web-terminal::terminal.infolist.execution_time'))
                                    ->formatStateUsing(fn (?int $state): string => $state !== null ? __('web-terminal::terminal.infolist.seconds', ['count' => $state]) : '—')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.user_session'))
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label(__('web-terminal::terminal.infolist.user'))
                                    ->placeholder(__('web-terminal::terminal.table.system')),

                                TextEntry::make('terminal_identifier')
                                    ->label(__('web-terminal::terminal.infolist.terminal_identifier'))
                                    ->fontFamily('mono')
                                    ->placeholder('—'),
                            ]),

                        TextEntry::make('terminal_session_id')
                            ->label(__('web-terminal::terminal.infolist.session_id'))
                            ->fontFamily('mono')
                            ->copyable()
                            ->copyMessage(__('web-terminal::terminal.infolist.session_id_copied')),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.ssh_connection_details'))
                    ->icon('heroicon-o-server')
                    ->visible(fn ($record): bool => $record->connection_type === TerminalLog::CONNECTION_SSH)
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('host')
                                    ->label(__('web-terminal::terminal.infolist.host'))
                                    ->fontFamily('mono'),

                                TextEntry::make('port')
                                    ->label(__('web-terminal::terminal.infolist.port'))
                                    ->fontFamily('mono'),

                                TextEntry::make('ssh_username')
                                    ->label(__('web-terminal::terminal.infolist.ssh_username'))
                                    ->fontFamily('mono'),
                            ]),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.command'))
                    ->icon('heroicon-o-command-line')
                    ->visible(fn ($record): bool => $record->command !== null)
                    ->schema([
                        TextEntry::make('command')
                            ->label(__('web-terminal::terminal.infolist.command'))
                            ->fontFamily('mono')
                            ->copyable()
                            ->copyMessage(__('web-terminal::terminal.infolist.command_copied')),

                        TextEntry::make('exit_code')
                            ->label(__('web-terminal::terminal.infolist.exit_code'))
                            ->badge()
                            ->color(fn (?int $state): string => match (true) {
                                $state === null => 'gray',
                                $state === 0 => 'success',
                                default => 'danger',
                            })
                            ->placeholder('—'),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.output'))
                    ->icon('heroicon-o-document-text')
                    ->visible(fn ($record): bool => $record->output !== null)
                    ->schema([
                        TextEntry::make('output')
                            ->label('')
                            ->formatStateUsing(function (?string $state): string {
                                if ($state === null) {
                                    return '';
                                }

                                $lines = explode("\n", $state);
                                $lineCount = count($lines);
                                // Calculate height: min 2 lines, max 10 lines
                                $displayLines = max(2, min(10, $lineCount));
                                // Each line ~1.25rem, add some padding
                                $height = ($displayLines * 1.25) + 0.5;

                                $escapedOutput = e($state);

                                return <<<HTML
                                <div class="w-full overflow-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900" style="max-height: {$height}rem;">
                                    <pre class="p-3 text-xs font-mono whitespace-pre text-gray-700 dark:text-gray-300 m-0">{$escapedOutput}</pre>
                                </div>
                                HTML;
                            })
                            ->html(),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.client_information'))
                    ->icon('heroicon-o-computer-desktop')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('ip_address')
                                    ->label(__('web-terminal::terminal.infolist.ip_address'))
                                    ->fontFamily('mono')
                                    ->placeholder('—'),

                                TextEntry::make('user_agent')
                                    ->label(__('web-terminal::terminal.infolist.user_agent'))
                                    ->limit(80)
                                    ->tooltip(fn ($record): ?string => $record->user_agent)
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make(__('web-terminal::terminal.infolist.metadata'))
                    ->icon('heroicon-o-code-bracket')
                    ->visible(fn ($record): bool => ! empty($record->metadata))
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('')
                            ->keyLabel(__('web-terminal::terminal.infolist.metadata_key'))
                            ->valueLabel(__('web-terminal::terminal.infolist.metadata_value')),
                    ]),
            ]);
    }
}
