<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Enums\ScriptCommandStatus;

describe('ScriptCommandStatus', function () {
    it('has all expected cases', function () {
        expect(ScriptCommandStatus::cases())->toHaveCount(5);
        expect(ScriptCommandStatus::Pending->value)->toBe('pending');
        expect(ScriptCommandStatus::Running->value)->toBe('running');
        expect(ScriptCommandStatus::Success->value)->toBe('success');
        expect(ScriptCommandStatus::Failed->value)->toBe('failed');
        expect(ScriptCommandStatus::Skipped->value)->toBe('skipped');
    });

    it('returns correct labels', function () {
        expect(ScriptCommandStatus::Pending->label())->toBe('Pending');
        expect(ScriptCommandStatus::Running->label())->toBe('Running');
        expect(ScriptCommandStatus::Success->label())->toBe('Success');
        expect(ScriptCommandStatus::Failed->label())->toBe('Failed');
        expect(ScriptCommandStatus::Skipped->label())->toBe('Skipped');
    });

    it('returns correct icons', function () {
        expect(ScriptCommandStatus::Pending->icon())->toBe('heroicon-o-minus-circle');
        expect(ScriptCommandStatus::Running->icon())->toBe('heroicon-o-arrow-path');
        expect(ScriptCommandStatus::Success->icon())->toBe('heroicon-o-check-circle');
        expect(ScriptCommandStatus::Failed->icon())->toBe('heroicon-o-x-circle');
        expect(ScriptCommandStatus::Skipped->icon())->toBe('heroicon-o-minus');
    });

    it('returns correct CSS classes', function () {
        expect(ScriptCommandStatus::Pending->cssClass())->toBe('text-slate-400 dark:text-zinc-500');
        expect(ScriptCommandStatus::Running->cssClass())->toBe('text-blue-500');
        expect(ScriptCommandStatus::Success->cssClass())->toBe('text-emerald-500');
        expect(ScriptCommandStatus::Failed->cssClass())->toBe('text-red-500');
        expect(ScriptCommandStatus::Skipped->cssClass())->toBe('text-amber-500');
    });

    it('returns correct colors', function () {
        expect(ScriptCommandStatus::Pending->color())->toBe('#94a3b8');
        expect(ScriptCommandStatus::Running->color())->toBe('#3b82f6');
        expect(ScriptCommandStatus::Success->color())->toBe('#10b981');
        expect(ScriptCommandStatus::Failed->color())->toBe('#ef4444');
        expect(ScriptCommandStatus::Skipped->color())->toBe('#f59e0b');
    });

    it('correctly identifies complete statuses', function () {
        expect(ScriptCommandStatus::Success->isComplete())->toBeTrue();
        expect(ScriptCommandStatus::Failed->isComplete())->toBeTrue();
        expect(ScriptCommandStatus::Skipped->isComplete())->toBeTrue();
        expect(ScriptCommandStatus::Pending->isComplete())->toBeFalse();
        expect(ScriptCommandStatus::Running->isComplete())->toBeFalse();
    });

    it('correctly identifies failure status', function () {
        expect(ScriptCommandStatus::Failed->isFailure())->toBeTrue();
        expect(ScriptCommandStatus::Success->isFailure())->toBeFalse();
        expect(ScriptCommandStatus::Pending->isFailure())->toBeFalse();
        expect(ScriptCommandStatus::Running->isFailure())->toBeFalse();
        expect(ScriptCommandStatus::Skipped->isFailure())->toBeFalse();
    });

    it('correctly identifies statuses that allow continuation', function () {
        expect(ScriptCommandStatus::Pending->allowsContinuation())->toBeTrue();
        expect(ScriptCommandStatus::Running->allowsContinuation())->toBeTrue();
        expect(ScriptCommandStatus::Success->allowsContinuation())->toBeTrue();
        expect(ScriptCommandStatus::Failed->allowsContinuation())->toBeFalse();
        expect(ScriptCommandStatus::Skipped->allowsContinuation())->toBeFalse();
    });
});
