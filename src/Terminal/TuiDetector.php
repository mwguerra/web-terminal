<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Terminal;

/**
 * Detects full-screen TUI (Text User Interface) applications in command output.
 *
 * TUI apps like vim, htop, less, and nano use alternate screen buffer escape
 * sequences to take over the terminal. These sequences cannot be rendered in
 * a web terminal's line-based display. This class detects those sequences and
 * provides suggestions for non-interactive alternatives.
 */
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

    private static function extractBaseCommand(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

        $commandName = $parts[0] ?? '';

        if ($commandName === 'sudo' && isset($parts[1])) {
            $commandName = $parts[1];
        }

        return basename($commandName);
    }

    private static function extractArguments(string $command): string
    {
        $parts = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

        $startIndex = 1;

        if (($parts[0] ?? '') === 'sudo') {
            $startIndex = 2;
        }

        return implode(' ', array_slice($parts, $startIndex));
    }
}
