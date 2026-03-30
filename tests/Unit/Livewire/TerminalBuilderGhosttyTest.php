<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\TerminalMode;
use MWGuerra\WebTerminal\Livewire\TerminalBuilder;

describe('TerminalBuilder Ghostty Methods', function () {
    describe('ghosttyTerminal()', function () {
        it('enables ghostty mode', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $params = $builder->getParameters();
            expect($params['ghosttyEnabled'])->toBeTrue();
        });

        it('disables ghostty mode explicitly', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal(false);
            $params = $builder->getParameters();
            expect($params)->not->toHaveKey('ghosttyEnabled');
        });

        it('is disabled by default', function () {
            $builder = new TerminalBuilder;
            $builder->local();
            $params = $builder->getParameters();
            expect($params)->not->toHaveKey('ghosttyEnabled');
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
            $builder->local()->ghosttyTerminal()->classicTerminal(false);
            $params = $builder->getParameters();
            expect($params['classicEnabled'])->toBeFalse();
        });
    });

    describe('defaultMode()', function () {
        it('defaults to classic', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $params = $builder->getParameters();
            expect($params['defaultMode'])->toBe('classic');
        });

        it('can be set to ghostty', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal()->defaultMode(TerminalMode::Ghostty);
            $params = $builder->getParameters();
            expect($params['defaultMode'])->toBe('ghostty');
        });
    });

    describe('ghosttyTheme()', function () {
        it('stores theme options', function () {
            $theme = ['background' => '#1a1b26', 'fontSize' => 14];
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal()->ghosttyTheme($theme);
            $params = $builder->getParameters();
            expect($params['ghosttyTheme'])->toBe($theme);
        });

        it('defaults to empty array', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $params = $builder->getParameters();
            expect($params['ghosttyTheme'])->toBe([]);
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
            $builder->local()->defaultMode(TerminalMode::Ghostty);
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

        it('renders GhosttyTerminal when only ghostty enabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal()->classicTerminal(false)->defaultMode(TerminalMode::Ghostty);
            $html = $builder->render();
            expect((string) $html)->toContain('ghostty-web-terminal');
        });

        it('renders TerminalContainer when both enabled', function () {
            $builder = new TerminalBuilder;
            $builder->local()->ghosttyTerminal();
            $html = $builder->render();
            expect((string) $html)->toContain('activeMode');
        });
    });
});
