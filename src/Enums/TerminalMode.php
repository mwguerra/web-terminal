<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Enums;

enum TerminalMode: string
{
    case Classic = 'classic';
    case Ghostty = 'ghostty';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Ghostty => 'Stream',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Command-by-command terminal via Livewire',
            self::Ghostty => 'Full interactive PTY terminal via WebSocket',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases())
        );
    }
}
