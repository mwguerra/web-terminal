<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Enums;

/**
 * Permissions that control what the terminal can do.
 *
 * Use with the `allow()` method on WebTerminal Schema Component or TerminalBuilder.
 */
enum TerminalPermission: string
{
    /** Bypass the command whitelist — any command can run. */
    case AllCommands = 'all_commands';

    /** Allow pipe operator (|). */
    case Pipes = 'pipes';

    /** Allow redirection operators (> >> <). */
    case Redirection = 'redirection';

    /** Allow command chaining operators (&& || ;). */
    case Chaining = 'chaining';

    /** Allow variable expansion ($() ${} ``). */
    case Expansion = 'expansion';

    /** Allow all shell operators (pipes + redirection + chaining + expansion). */
    case ShellOperators = 'shell_operators';

    /** Use interactive execution (PTY/tmux) for streaming output and stdin support. */
    case InteractiveMode = 'interactive';

    /** Enable everything: all commands, all shell operators, and interactive mode. */
    case All = 'all';

    /**
     * Resolve composite permissions into individual flags.
     *
     * @return array<string, bool>
     */
    public function resolveFlags(): array
    {
        return match ($this) {
            self::AllCommands => ['allowAllCommands' => true],
            self::Pipes => ['allowPipes' => true],
            self::Redirection => ['allowRedirection' => true],
            self::Chaining => ['allowChaining' => true],
            self::Expansion => ['allowExpansion' => true],
            self::ShellOperators => [
                'allowPipes' => true,
                'allowRedirection' => true,
                'allowChaining' => true,
                'allowExpansion' => true,
                'allowAllShellOperators' => true,
            ],
            self::InteractiveMode => ['allowInteractiveMode' => true],
            self::All => [
                'allowAllCommands' => true,
                'allowPipes' => true,
                'allowRedirection' => true,
                'allowChaining' => true,
                'allowExpansion' => true,
                'allowAllShellOperators' => true,
                'allowInteractiveMode' => true,
            ],
        };
    }

    /**
     * Resolve an array of permissions into merged flags.
     *
     * @param  array<TerminalPermission>  $permissions
     * @return array<string, bool>
     */
    public static function resolveManyFlags(array $permissions): array
    {
        $flags = [];

        foreach ($permissions as $permission) {
            $flags = array_merge($flags, $permission->resolveFlags());
        }

        return $flags;
    }
}
