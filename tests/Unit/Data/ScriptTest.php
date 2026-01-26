<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Data\Script;

describe('Script', function () {
    describe('construction', function () {
        it('can be created with make factory method', function () {
            $script = Script::make('deploy');

            expect($script->getKey())->toBe('deploy')
                ->and($script->getLabel())->toBe('deploy')  // Default label is key
                ->and($script->getCommands())->toBe([])
                ->and($script->getDescription())->toBeNull()
                ->and($script->getIcon())->toBe('heroicon-o-command-line')
                ->and($script->shouldStopOnError())->toBeTrue()
                ->and($script->requiresConfirmation())->toBeFalse()
                ->and($script->isElevated())->toBeFalse()
                ->and($script->causesDisconnection())->toBeFalse();
        });

        it('throws exception for empty key', function () {
            Script::make('');
        })->throws(InvalidArgumentException::class);

        it('throws exception for whitespace-only key', function () {
            Script::make('   ');
        })->throws(InvalidArgumentException::class);
    });

    describe('fluent API', function () {
        it('sets label correctly', function () {
            $script = Script::make('deploy')
                ->label('Deploy Application');

            expect($script->getLabel())->toBe('Deploy Application');
        });

        it('sets description correctly', function () {
            $script = Script::make('deploy')
                ->description('Pull latest code and restart services');

            expect($script->getDescription())->toBe('Pull latest code and restart services');
        });

        it('sets icon correctly', function () {
            $script = Script::make('deploy')
                ->icon('heroicon-o-rocket-launch');

            expect($script->getIcon())->toBe('heroicon-o-rocket-launch');
        });

        it('sets commands correctly', function () {
            $script = Script::make('deploy')
                ->commands([
                    'git pull origin main',
                    'composer install',
                    'php artisan migrate',
                ]);

            expect($script->getCommands())->toBe([
                'git pull origin main',
                'composer install',
                'php artisan migrate',
            ])
                ->and($script->commandCount())->toBe(3);
        });

        it('filters empty commands', function () {
            $script = Script::make('deploy')
                ->commands([
                    'git pull',
                    '',
                    '  ',
                    'composer install',
                ]);

            expect($script->getCommands())->toBe([
                'git pull',
                'composer install',
            ]);
        });

        it('sets stopOnError correctly', function () {
            $script = Script::make('deploy')
                ->stopOnError(false);

            expect($script->shouldStopOnError())->toBeFalse();
        });

        it('sets continueOnError correctly', function () {
            $script = Script::make('deploy')
                ->continueOnError();

            expect($script->shouldStopOnError())->toBeFalse();
        });

        it('sets confirmBeforeRun correctly', function () {
            $script = Script::make('deploy')
                ->confirmBeforeRun();

            expect($script->requiresConfirmation())->toBeTrue();
        });

        it('sets elevated correctly', function () {
            $script = Script::make('deploy')
                ->elevated();

            expect($script->isElevated())->toBeTrue();
        });

        it('sets willDisconnect correctly', function () {
            $script = Script::make('reboot')
                ->willDisconnect();

            expect($script->causesDisconnection())->toBeTrue();
        });

        it('sets beforeMessage correctly', function () {
            $script = Script::make('reboot')
                ->beforeMessage('This will reboot the server.');

            expect($script->getBeforeMessage())->toBe('This will reboot the server.');
        });

        it('sets disconnectMessage correctly', function () {
            $script = Script::make('reboot')
                ->disconnectMessage('Server is rebooting...');

            expect($script->getDisconnectMessage())->toBe('Server is rebooting...');
        });

        it('supports method chaining', function () {
            $script = Script::make('deploy')
                ->label('Deploy Application')
                ->description('Full deployment')
                ->icon('heroicon-o-rocket-launch')
                ->commands(['git pull', 'composer install'])
                ->stopOnError()
                ->confirmBeforeRun()
                ->elevated()
                ->willDisconnect()
                ->beforeMessage('Starting deployment...')
                ->disconnectMessage('Deployment complete.');

            expect($script->getKey())->toBe('deploy')
                ->and($script->getLabel())->toBe('Deploy Application')
                ->and($script->getDescription())->toBe('Full deployment')
                ->and($script->getIcon())->toBe('heroicon-o-rocket-launch')
                ->and($script->commandCount())->toBe(2)
                ->and($script->shouldStopOnError())->toBeTrue()
                ->and($script->requiresConfirmation())->toBeTrue()
                ->and($script->isElevated())->toBeTrue()
                ->and($script->causesDisconnection())->toBeTrue()
                ->and($script->getBeforeMessage())->toBe('Starting deployment...')
                ->and($script->getDisconnectMessage())->toBe('Deployment complete.');
        });
    });

    describe('fromArray', function () {
        it('can create from array configuration', function () {
            $script = Script::fromArray([
                'key' => 'deploy',
                'label' => 'Deploy App',
                'description' => 'Deploy the application',
                'icon' => 'heroicon-o-rocket-launch',
                'commands' => ['git pull', 'composer install'],
                'stopOnError' => false,
                'confirmBeforeRun' => true,
                'elevated' => true,
                'willDisconnect' => true,
                'beforeMessage' => 'Starting...',
                'disconnectMessage' => 'Disconnecting...',
            ]);

            expect($script->getKey())->toBe('deploy')
                ->and($script->getLabel())->toBe('Deploy App')
                ->and($script->getDescription())->toBe('Deploy the application')
                ->and($script->getIcon())->toBe('heroicon-o-rocket-launch')
                ->and($script->commandCount())->toBe(2)
                ->and($script->shouldStopOnError())->toBeFalse()
                ->and($script->requiresConfirmation())->toBeTrue()
                ->and($script->isElevated())->toBeTrue()
                ->and($script->causesDisconnection())->toBeTrue()
                ->and($script->getBeforeMessage())->toBe('Starting...')
                ->and($script->getDisconnectMessage())->toBe('Disconnecting...');
        });

        it('throws exception for missing key', function () {
            Script::fromArray(['label' => 'Test']);
        })->throws(InvalidArgumentException::class);

        it('throws exception for empty key', function () {
            Script::fromArray(['key' => '']);
        })->throws(InvalidArgumentException::class);

        it('uses defaults for missing optional fields', function () {
            $script = Script::fromArray(['key' => 'test']);

            expect($script->getLabel())->toBe('test')
                ->and($script->getDescription())->toBeNull()
                ->and($script->getIcon())->toBe('heroicon-o-command-line')
                ->and($script->commandCount())->toBe(0)
                ->and($script->shouldStopOnError())->toBeTrue()
                ->and($script->requiresConfirmation())->toBeFalse()
                ->and($script->isElevated())->toBeFalse()
                ->and($script->causesDisconnection())->toBeFalse();
        });
    });

    describe('toArray', function () {
        it('converts to array representation', function () {
            $script = Script::make('deploy')
                ->label('Deploy')
                ->description('Deploy app')
                ->icon('heroicon-o-rocket-launch')
                ->commands(['git pull', 'composer install'])
                ->stopOnError()
                ->confirmBeforeRun()
                ->elevated()
                ->willDisconnect()
                ->beforeMessage('Starting...')
                ->disconnectMessage('Done.');

            $array = $script->toArray();

            expect($array)->toHaveKeys([
                'key',
                'label',
                'commands',
                'description',
                'icon',
                'stopOnError',
                'confirmBeforeRun',
                'elevated',
                'requiredCommands',
                'willDisconnect',
                'beforeMessage',
                'disconnectMessage',
                'commandCount',
            ])
                ->and($array['key'])->toBe('deploy')
                ->and($array['label'])->toBe('Deploy')
                ->and($array['description'])->toBe('Deploy app')
                ->and($array['icon'])->toBe('heroicon-o-rocket-launch')
                ->and($array['commands'])->toBe(['git pull', 'composer install'])
                ->and($array['stopOnError'])->toBeTrue()
                ->and($array['confirmBeforeRun'])->toBeTrue()
                ->and($array['elevated'])->toBeTrue()
                ->and($array['willDisconnect'])->toBeTrue()
                ->and($array['beforeMessage'])->toBe('Starting...')
                ->and($array['disconnectMessage'])->toBe('Done.')
                ->and($array['commandCount'])->toBe(2);
        });

        it('roundtrips through fromArray', function () {
            $original = Script::make('deploy')
                ->label('Deploy')
                ->commands(['git pull'])
                ->elevated();

            $array = $original->toArray();
            $recreated = Script::fromArray($array);

            expect($recreated->getKey())->toBe($original->getKey())
                ->and($recreated->getLabel())->toBe($original->getLabel())
                ->and($recreated->getCommands())->toBe($original->getCommands())
                ->and($recreated->isElevated())->toBe($original->isElevated());
        });
    });

    describe('command authorization', function () {
        it('returns empty array when elevated', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'sudo reboot'])
                ->elevated();

            expect($script->getUnauthorizedCommands(['git']))->toBe([]);
        });

        it('returns empty array when all commands allowed', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'ls -la']);

            expect($script->getUnauthorizedCommands(['git', 'ls']))->toBe([]);
        });

        it('returns unauthorized commands', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'rm -rf /', 'ls -la']);

            $unauthorized = $script->getUnauthorizedCommands(['git', 'ls']);

            expect($unauthorized)->toBe(['rm -rf /']);
        });

        it('returns empty array when allowedCommands is empty', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'rm -rf /']);

            expect($script->getUnauthorizedCommands([]))->toBe([]);
        });

        it('supports wildcard matching', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'git-lfs install']);

            expect($script->getUnauthorizedCommands(['git*']))->toBe([]);
        });

        it('checks canRunWithAllowedCommands', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'rm -rf /']);

            expect($script->canRunWithAllowedCommands(['git', 'ls']))->toBeFalse()
                ->and($script->canRunWithAllowedCommands(['git', 'rm']))->toBeTrue();
        });

        it('elevated scripts always can run', function () {
            $script = Script::make('deploy')
                ->commands(['git pull', 'rm -rf /'])
                ->elevated();

            expect($script->canRunWithAllowedCommands(['git']))->toBeTrue();
        });
    });
});
