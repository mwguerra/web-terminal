<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Terminal;

class TuiDetector
{
    private const ALTERNATE_SCREEN_PATTERN = '/\x1b\[\?(1049|47|1047)[hl]/';

    private const EDITOR_COMMANDS = ['vim', 'vi', 'nvim', 'nano', 'less', 'more'];

    private const MONITOR_COMMANDS = ['top', 'htop'];

    public static function containsTuiSequences(string $output): bool
    {
        if ($output === '') {
            return false;
        }

        return (bool) preg_match(self::ALTERNATE_SCREEN_PATTERN, $output);
    }

    public static function getSuggestion(string $command): ?string
    {
        $baseCommand = self::extractBaseCommand($command);
        $arguments = self::extractArguments($command);

        if (in_array($baseCommand, self::MONITOR_COMMANDS, true)) {
            return 'Try instead: top -b -n 1';
        }

        if (in_array($baseCommand, self::EDITOR_COMMANDS, true)) {
            $file = trim($arguments) !== '' ? ' ' . trim($arguments) : ' <file>';

            return 'Try instead: cat' . $file;
        }

        if ($baseCommand === 'man') {
            $topic = trim($arguments) !== '' ? ' ' . trim($arguments) : ' <topic>';

            return 'Try instead: man' . $topic . ' | col -b (requires pipes enabled)';
        }

        return null;
    }

    public static function getErrorMessage(string $command): string
    {
        $message = 'This command uses a full-screen terminal interface (TUI) which is not supported in the web terminal.';

        $suggestion = self::getSuggestion($command);

        if ($suggestion !== null) {
            $message .= ' ' . $suggestion;
        }

        return $message;
    }

    public static function extractBaseCommand(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command));

        $commandName = $parts[0] ?? '';

        if ($commandName === 'sudo' && isset($parts[1])) {
            $commandName = $parts[1];
        }

        return basename($commandName);
    }

    public static function extractArguments(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command));

        $startIndex = 1;

        if (($parts[0] ?? '') === 'sudo') {
            $startIndex = 2;
        }

        return implode(' ', array_slice($parts, $startIndex));
    }
}
