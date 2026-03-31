<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalMode;
use MWGuerra\WebTerminal\Livewire\TerminalBuilder;

describe('TerminalBuilder Stream Methods', function () {
    describe('streamTerminal()', function () {
        it('enables stream mode', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal();
            $params = $builder->getParameters();
            expect($params['streamEnabled'])->toBeTrue();
        });

        it('disables stream mode explicitly', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal(false);
            $params = $builder->getParameters();
            expect($params)->not->toHaveKey('streamEnabled');
        });

        it('is disabled by default', function () {
            $builder = new TerminalBuilder;
            $builder->local();
            $params = $builder->getParameters();
            expect($params)->not->toHaveKey('streamEnabled');
        });
    });

    describe('classicTerminal()', function () {
        it('is enabled by default', function () {
            $builder = new TerminalBuilder;
            $builder->local();
            $params = $builder->getParameters();
            expect($params)->not->toHaveKey('classicEnabled');
        });

        it('can be disabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal()->classicTerminal(false);
            $params = $builder->getParameters();
            expect($params['classicEnabled'])->toBeFalse();
        });
    });

    describe('defaultMode()', function () {
        it('defaults to classic', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal();
            $params = $builder->getParameters();
            expect($params['defaultMode'])->toBe('classic');
        });

        it('can be set to stream', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal()->defaultMode(TerminalMode::Stream);
            $params = $builder->getParameters();
            expect($params['defaultMode'])->toBe('stream');
        });
    });

    describe('streamTheme()', function () {
        it('stores theme options', function () {
            $theme = ['background' => '#1a1b26', 'fontSize' => 14];
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal()->streamTheme($theme);
            $params = $builder->getParameters();
            expect($params['streamTheme'])->toBe($theme);
        });

        it('defaults to empty array', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal();
            $params = $builder->getParameters();
            expect($params['streamTheme'])->toBe([]);
        });
    });

    describe('validation', function () {
        it('throws when both modes disabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->classicTerminal(false);
            expect(fn () => $builder->render())->toThrow(\InvalidArgumentException::class, 'At least one terminal mode must be enabled');
        });

        it('throws when defaultMode set to disabled mode', function () {
            $builder = new TerminalBuilder;
            $builder->local()->defaultMode(TerminalMode::Stream);
            expect(fn () => $builder->render())->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('render routing', function () {
        it('renders WebTerminal when only classic enabled', function () {
            $builder = new TerminalBuilder;
            $builder->local();
            $html = $builder->render();
            expect((string) $html)->toContain('secure-web-terminal');
        });

        it('renders StreamTerminal when only stream enabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal()->classicTerminal(false)->defaultMode(TerminalMode::Stream);
            $html = $builder->render();
            expect((string) $html)->toContain('stream-web-terminal');
        });

        it('renders TerminalContainer when both enabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->streamTerminal();
            $html = $builder->render();
            expect((string) $html)->toContain('activeMode');
        });
    });
});
