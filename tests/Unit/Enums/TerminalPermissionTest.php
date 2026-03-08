<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalPermission;

describe('TerminalPermission', function () {
    describe('resolveFlags', function () {
        it('resolves AllCommands to single flag', function () {
            $flags = TerminalPermission::AllCommands->resolveFlags();

            expect($flags)->toBe(['allowAllCommands' => true]);
        });

        it('resolves Pipes to single flag', function () {
            $flags = TerminalPermission::Pipes->resolveFlags();

            expect($flags)->toBe(['allowPipes' => true]);
        });

        it('resolves Redirection to single flag', function () {
            $flags = TerminalPermission::Redirection->resolveFlags();

            expect($flags)->toBe(['allowRedirection' => true]);
        });

        it('resolves Chaining to single flag', function () {
            $flags = TerminalPermission::Chaining->resolveFlags();

            expect($flags)->toBe(['allowChaining' => true]);
        });

        it('resolves Expansion to single flag', function () {
            $flags = TerminalPermission::Expansion->resolveFlags();

            expect($flags)->toBe(['allowExpansion' => true]);
        });

        it('resolves InteractiveMode to single flag', function () {
            $flags = TerminalPermission::InteractiveMode->resolveFlags();

            expect($flags)->toBe(['allowInteractiveMode' => true]);
        });

        it('resolves ShellOperators to all operator flags', function () {
            $flags = TerminalPermission::ShellOperators->resolveFlags();

            expect($flags)->toBe([
                'allowPipes' => true,
                'allowRedirection' => true,
                'allowChaining' => true,
                'allowExpansion' => true,
                'allowAllShellOperators' => true,
            ]);
        });

        it('resolves All to every flag', function () {
            $flags = TerminalPermission::All->resolveFlags();

            expect($flags)->toBe([
                'allowAllCommands' => true,
                'allowPipes' => true,
                'allowRedirection' => true,
                'allowChaining' => true,
                'allowExpansion' => true,
                'allowAllShellOperators' => true,
                'allowInteractiveMode' => true,
            ]);
        });
    });

    describe('resolveManyFlags', function () {
        it('merges multiple permissions', function () {
            $flags = TerminalPermission::resolveManyFlags([
                TerminalPermission::InteractiveMode,
                TerminalPermission::Pipes,
            ]);

            expect($flags)
                ->toHaveKey('allowInteractiveMode', true)
                ->toHaveKey('allowPipes', true)
                ->not->toHaveKey('allowAllCommands');
        });

        it('handles empty array', function () {
            $flags = TerminalPermission::resolveManyFlags([]);

            expect($flags)->toBe([]);
        });

        it('deduplicates overlapping flags', function () {
            $flags = TerminalPermission::resolveManyFlags([
                TerminalPermission::Pipes,
                TerminalPermission::ShellOperators,
            ]);

            expect($flags)->toHaveKey('allowPipes', true)
                ->toHaveKey('allowRedirection', true)
                ->toHaveKey('allowChaining', true)
                ->toHaveKey('allowExpansion', true)
                ->toHaveKey('allowAllShellOperators', true);
        });
    });

    describe('enum values', function () {
        it('has unique string values', function () {
            $values = array_map(fn ($case) => $case->value, TerminalPermission::cases());

            expect($values)->toHaveCount(count(array_unique($values)));
        });
    });
});
