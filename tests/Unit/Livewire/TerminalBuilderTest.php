<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalPermission;
use MWGuerra\WebTerminal\Livewire\TerminalBuilder;

describe('TerminalBuilder', function () {
    describe('permission methods', function () {
        it('sets allowAllCommands', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowAllCommands();

            $params = $builder->getParameters();

            expect($params['allowAllCommands'])->toBeTrue();
        });

        it('sets allowPipes', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowPipes();

            $params = $builder->getParameters();

            expect($params['allowPipes'])->toBeTrue();
        });

        it('sets allowRedirection', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowRedirection();

            $params = $builder->getParameters();

            expect($params['allowRedirection'])->toBeTrue();
        });

        it('sets allowChaining', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowChaining();

            $params = $builder->getParameters();

            expect($params['allowChaining'])->toBeTrue();
        });

        it('sets allowExpansion', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowExpansion();

            $params = $builder->getParameters();

            expect($params['allowExpansion'])->toBeTrue();
        });

        it('sets allowAllShellOperators with all sub-flags', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowAllShellOperators();

            $params = $builder->getParameters();

            expect($params['allowAllShellOperators'])->toBeTrue()
                ->and($params['allowPipes'])->toBeTrue()
                ->and($params['allowRedirection'])->toBeTrue()
                ->and($params['allowChaining'])->toBeTrue()
                ->and($params['allowExpansion'])->toBeTrue();
        });

        it('sets allowInteractiveMode', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allowInteractiveMode();

            $params = $builder->getParameters();

            expect($params['allowInteractiveMode'])->toBeTrue();
        });
    });

    describe('allow() with enum', function () {
        it('sets InteractiveMode permission', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allow([TerminalPermission::InteractiveMode]);

            $params = $builder->getParameters();

            expect($params['allowInteractiveMode'])->toBeTrue();
        });

        it('sets All permission enables everything', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allow([TerminalPermission::All]);

            $params = $builder->getParameters();

            expect($params['allowAllCommands'])->toBeTrue()
                ->and($params['allowAllShellOperators'])->toBeTrue()
                ->and($params['allowInteractiveMode'])->toBeTrue()
                ->and($params['allowPipes'])->toBeTrue()
                ->and($params['allowRedirection'])->toBeTrue()
                ->and($params['allowChaining'])->toBeTrue()
                ->and($params['allowExpansion'])->toBeTrue();
        });

        it('sets ShellOperators enables all operator flags', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allow([TerminalPermission::ShellOperators]);

            $params = $builder->getParameters();

            expect($params['allowPipes'])->toBeTrue()
                ->and($params['allowRedirection'])->toBeTrue()
                ->and($params['allowChaining'])->toBeTrue()
                ->and($params['allowExpansion'])->toBeTrue()
                ->and($params['allowAllShellOperators'])->toBeTrue();

            expect($params)->not->toHaveKey('allowAllCommands')
                ->not->toHaveKey('allowInteractiveMode');
        });

        it('combines multiple permissions', function () {
            $builder = new TerminalBuilder;
            $builder->local()->allow([
                TerminalPermission::InteractiveMode,
                TerminalPermission::Pipes,
            ]);

            $params = $builder->getParameters();

            expect($params['allowInteractiveMode'])->toBeTrue()
                ->and($params['allowPipes'])->toBeTrue();

            expect($params)->not->toHaveKey('allowAllCommands');
        });
    });

    describe('new configuration methods', function () {
        it('sets environment variables', function () {
            $builder = new TerminalBuilder;
            $builder->local()->environment(['FOO' => 'bar']);

            $params = $builder->getParameters();

            expect($params['environment'])->toBe(['FOO' => 'bar']);
        });

        it('sets loginShell', function () {
            $builder = new TerminalBuilder;
            $builder->local()->loginShell();

            $params = $builder->getParameters();

            expect($params['useLoginShell'])->toBeTrue();
        });

        it('sets custom shell', function () {
            $builder = new TerminalBuilder;
            $builder->local()->shell('/bin/zsh');

            $params = $builder->getParameters();

            expect($params['shell'])->toBe('/bin/zsh');
        });

        it('sets startConnected', function () {
            $builder = new TerminalBuilder;
            $builder->local()->startConnected();

            $params = $builder->getParameters();

            expect($params['startConnected'])->toBeTrue();
        });

        it('sets title', function () {
            $builder = new TerminalBuilder;
            $builder->local()->title('My Terminal');

            $params = $builder->getParameters();

            expect($params['title'])->toBe('My Terminal');
        });

        it('sets windowControls to false', function () {
            $builder = new TerminalBuilder;
            $builder->local()->windowControls(false);

            $params = $builder->getParameters();

            expect($params['showWindowControls'])->toBeFalse();
        });

        it('sets height', function () {
            $builder = new TerminalBuilder;
            $builder->local()->height('600px');

            $params = $builder->getParameters();

            expect($params['height'])->toBe('600px');
        });

        it('sets log configuration', function () {
            $builder = new TerminalBuilder;
            $builder->local()->log(
                enabled: true,
                connections: true,
                commands: true,
                output: false,
                identifier: 'test-terminal',
            );

            $params = $builder->getParameters();

            expect($params['loggingEnabled'])->toBeTrue()
                ->and($params['logConnections'])->toBeTrue()
                ->and($params['logCommands'])->toBeTrue()
                ->and($params['logOutput'])->toBeFalse()
                ->and($params['logIdentifier'])->toBe('test-terminal');
        });

        it('sets scripts', function () {
            $builder = new TerminalBuilder;
            $builder->local()->scripts(['echo hello', 'echo world']);

            $params = $builder->getParameters();

            expect($params['scripts'])->toBe(['echo hello', 'echo world']);
        });
    });

    describe('does not include default values', function () {
        it('excludes false booleans from parameters', function () {
            $builder = new TerminalBuilder;
            $builder->local();

            $params = $builder->getParameters();

            expect($params)->not->toHaveKey('allowAllCommands')
                ->not->toHaveKey('allowPipes')
                ->not->toHaveKey('allowRedirection')
                ->not->toHaveKey('allowChaining')
                ->not->toHaveKey('allowExpansion')
                ->not->toHaveKey('allowAllShellOperators')
                ->not->toHaveKey('allowInteractiveMode')
                ->not->toHaveKey('startConnected')
                ->not->toHaveKey('useLoginShell');
        });

        it('excludes default shell from parameters', function () {
            $builder = new TerminalBuilder;
            $builder->local();

            $params = $builder->getParameters();

            expect($params)->not->toHaveKey('shell');
        });
    });
});
