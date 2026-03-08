<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Data\ScriptExecution;
use MWGuerra\WebTerminal\Enums\ScriptCommandStatus;

describe('ScriptExecution', function () {
    describe('start', function () {
        it('initializes execution state correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('deploy', 'Deploy Application', [
                'git pull',
                'composer install',
                'php artisan migrate',
            ]);

            expect($execution->isRunning())->toBeTrue()
                ->and($execution->isPaused())->toBeFalse()
                ->and($execution->isCancelled())->toBeFalse()
                ->and($execution->getScriptKey())->toBe('deploy')
                ->and($execution->getScriptLabel())->toBe('Deploy Application')
                ->and($execution->getTotalCommands())->toBe(3)
                ->and($execution->getCurrentCommandIndex())->toBe(0)
                ->and($execution->hasMoreCommands())->toBeTrue()
                ->and($execution->getCurrentCommand())->toBe('git pull');
        });

        it('initializes all commands as pending', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2']);

            $commands = $execution->getCommands();

            expect($commands[0]['status'])->toBe(ScriptCommandStatus::Pending->value)
                ->and($commands[1]['status'])->toBe(ScriptCommandStatus::Pending->value);
        });
    });

    describe('command status updates', function () {
        it('marks current command as running', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2']);
            $execution->markCurrentAsRunning();

            $commands = $execution->getCommands();

            expect($commands[0]['status'])->toBe(ScriptCommandStatus::Running->value)
                ->and($commands[0]['startedAt'])->not->toBeNull();
        });

        it('marks current command as success', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, 'output', 0.5);

            $commands = $execution->getCommands();

            expect($commands[0]['status'])->toBe(ScriptCommandStatus::Success->value)
                ->and($commands[0]['exitCode'])->toBe(0)
                ->and($commands[0]['output'])->toBe('output')
                ->and($commands[0]['executionTime'])->toBe(0.5)
                ->and($commands[0]['finishedAt'])->not->toBeNull();
        });

        it('marks current command as failed', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsFailed(1, 'error', 0.3);

            $commands = $execution->getCommands();

            expect($commands[0]['status'])->toBe(ScriptCommandStatus::Failed->value)
                ->and($commands[0]['exitCode'])->toBe(1)
                ->and($commands[0]['output'])->toBe('error')
                ->and($commands[0]['executionTime'])->toBe(0.3)
                ->and($commands[0]['finishedAt'])->not->toBeNull();
        });

        it('marks remaining commands as skipped', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsFailed(1, 'error', 0.1);
            $execution->markRemainingAsSkipped();

            $commands = $execution->getCommands();

            expect($commands[0]['status'])->toBe(ScriptCommandStatus::Failed->value)
                ->and($commands[1]['status'])->toBe(ScriptCommandStatus::Skipped->value)
                ->and($commands[2]['status'])->toBe(ScriptCommandStatus::Skipped->value);
        });
    });

    describe('advancing through commands', function () {
        it('advances to next command', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3']);
            $execution->advanceToNext();

            expect($execution->getCurrentCommandIndex())->toBe(1)
                ->and($execution->getCurrentCommand())->toBe('cmd2');
        });

        it('reports no more commands at end', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->advanceToNext();

            expect($execution->hasMoreCommands())->toBeFalse()
                ->and($execution->getCurrentCommand())->toBeNull();
        });
    });

    describe('progress tracking', function () {
        it('calculates progress percentage correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3', 'cmd4']);

            expect($execution->getProgressPercentage())->toBe(0);

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);

            expect($execution->getProgressPercentage())->toBe(25);

            $execution->advanceToNext();
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);

            expect($execution->getProgressPercentage())->toBe(50);
        });

        it('returns 100% for empty script', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', []);

            expect($execution->getProgressPercentage())->toBe(100);
        });

        it('counts completed commands correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3']);

            expect($execution->getCompletedCount())->toBe(0);

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);

            expect($execution->getCompletedCount())->toBe(1);

            $execution->advanceToNext();
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsFailed(1, '', 0.1);

            expect($execution->getCompletedCount())->toBe(2);
        });

        it('counts success and failed correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3']);

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);
            $execution->advanceToNext();

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsFailed(1, '', 0.1);
            $execution->advanceToNext();

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);

            expect($execution->getSuccessCount())->toBe(2)
                ->and($execution->getFailedCount())->toBe(1);
        });
    });

    describe('pause and resume', function () {
        it('can pause execution', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->pause();

            expect($execution->isPaused())->toBeTrue()
                ->and($execution->isRunning())->toBeTrue();
        });

        it('can resume execution', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->pause();
            $execution->resume();

            expect($execution->isPaused())->toBeFalse();
        });
    });

    describe('cancel', function () {
        it('cancels execution correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);
            $execution->advanceToNext();
            $execution->cancel();

            expect($execution->isCancelled())->toBeTrue()
                ->and($execution->isRunning())->toBeFalse();

            $commands = $execution->getCommands();
            expect($commands[0]['status'])->toBe(ScriptCommandStatus::Success->value)
                ->and($commands[1]['status'])->toBe(ScriptCommandStatus::Skipped->value)
                ->and($commands[2]['status'])->toBe(ScriptCommandStatus::Skipped->value);
        });
    });

    describe('finish', function () {
        it('finishes execution correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);
            $execution->finish();

            expect($execution->isRunning())->toBeFalse()
                ->and($execution->isCancelled())->toBeFalse();
        });
    });

    describe('success/failure checks', function () {
        it('identifies successful execution', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);
            $execution->advanceToNext();
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);
            $execution->finish();

            expect($execution->isSuccessful())->toBeTrue()
                ->and($execution->hasFailed())->toBeFalse();
        });

        it('identifies failed execution', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsFailed(1, '', 0.1);
            $execution->finish();

            expect($execution->isSuccessful())->toBeFalse()
                ->and($execution->hasFailed())->toBeTrue();
        });

        it('running execution is not successful', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);

            expect($execution->isSuccessful())->toBeFalse();  // Still running
        });

        it('cancelled execution is not successful', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->cancel();

            expect($execution->isSuccessful())->toBeFalse();
        });
    });

    describe('fromArray hydration', function () {
        it('hydrates from array correctly', function () {
            $data = [
                'isRunning' => true,
                'isPaused' => false,
                'isCancelled' => false,
                'currentCommandIndex' => 1,
                'scriptKey' => 'deploy',
                'scriptLabel' => 'Deploy',
                'commands' => [
                    ['command' => 'cmd1', 'status' => 'success', 'exitCode' => 0, 'output' => '', 'executionTime' => 0.1, 'startedAt' => null, 'finishedAt' => null],
                    ['command' => 'cmd2', 'status' => 'running', 'exitCode' => null, 'output' => '', 'executionTime' => null, 'startedAt' => null, 'finishedAt' => null],
                ],
                'startedAt' => '2024-01-01T00:00:00+00:00',
                'finishedAt' => null,
            ];

            $execution = ScriptExecution::fromArray($data);

            expect($execution->isRunning())->toBeTrue()
                ->and($execution->getScriptKey())->toBe('deploy')
                ->and($execution->getScriptLabel())->toBe('Deploy')
                ->and($execution->getCurrentCommandIndex())->toBe(1)
                ->and($execution->getTotalCommands())->toBe(2);
        });
    });

    describe('toArray', function () {
        it('converts to array correctly', function () {
            $execution = new ScriptExecution;
            $execution->start('deploy', 'Deploy', ['cmd1', 'cmd2']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, 'output', 0.5);
            $execution->advanceToNext();

            $array = $execution->toArray();

            expect($array)->toHaveKeys([
                'isRunning',
                'isPaused',
                'isCancelled',
                'currentCommandIndex',
                'scriptKey',
                'scriptLabel',
                'commands',
                'startedAt',
                'finishedAt',
                'progressPercentage',
                'totalCommands',
                'completedCount',
                'successCount',
                'failedCount',
            ])
                ->and($array['isRunning'])->toBeTrue()
                ->and($array['scriptKey'])->toBe('deploy')
                ->and($array['scriptLabel'])->toBe('Deploy')
                ->and($array['currentCommandIndex'])->toBe(1)
                ->and($array['totalCommands'])->toBe(2)
                ->and($array['completedCount'])->toBe(1)
                ->and($array['successCount'])->toBe(1)
                ->and($array['failedCount'])->toBe(0)
                ->and($array['progressPercentage'])->toBe(50);
        });

        it('roundtrips through fromArray', function () {
            $original = new ScriptExecution;
            $original->start('test', 'Test', ['cmd1', 'cmd2']);
            $original->markCurrentAsRunning();
            $original->markCurrentAsSuccess(0, 'output', 0.5);

            $array = $original->toArray();
            $recreated = ScriptExecution::fromArray($array);

            expect($recreated->getScriptKey())->toBe($original->getScriptKey())
                ->and($recreated->isRunning())->toBe($original->isRunning())
                ->and($recreated->getCurrentCommandIndex())->toBe($original->getCurrentCommandIndex())
                ->and($recreated->getSuccessCount())->toBe($original->getSuccessCount());
        });
    });

    describe('reset', function () {
        it('resets all state', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);
            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.1);
            $execution->reset();

            expect($execution->isRunning())->toBeFalse()
                ->and($execution->hasStarted())->toBeFalse()
                ->and($execution->getScriptKey())->toBeNull()
                ->and($execution->getTotalCommands())->toBe(0)
                ->and($execution->getCurrentCommandIndex())->toBe(0);
        });
    });

    describe('hasStarted', function () {
        it('returns false before start', function () {
            $execution = new ScriptExecution;

            expect($execution->hasStarted())->toBeFalse();
        });

        it('returns true after start', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1']);

            expect($execution->hasStarted())->toBeTrue();
        });
    });

    describe('total execution time', function () {
        it('calculates total execution time', function () {
            $execution = new ScriptExecution;
            $execution->start('test', 'Test', ['cmd1', 'cmd2', 'cmd3']);

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.5);
            $execution->advanceToNext();

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.3);
            $execution->advanceToNext();

            $execution->markCurrentAsRunning();
            $execution->markCurrentAsSuccess(0, '', 0.2);

            expect($execution->getTotalExecutionTime())->toBe(1.0);
        });
    });
});
