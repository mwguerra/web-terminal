<?php

use MWGuerra\WebTerminal\Livewire\WebTerminal as WebTerminalComponent;
use MWGuerra\WebTerminal\Schemas\Components\WebTerminal;

// Skip all tests if Filament is not installed
beforeEach(function () {
    if (! class_exists(\Filament\Schemas\Components\Livewire::class)) {
        $this->markTestSkipped('Filament is not installed. These tests require filament/filament package.');
    }
});

describe('make', function () {
    it('creates instance with default component', function () {
        $component = WebTerminal::make();

        expect($component)->toBeInstanceOf(WebTerminal::class);
    });

    it('creates instance with custom component class', function () {
        $component = WebTerminal::make(WebTerminalComponent::class);

        expect($component)->toBeInstanceOf(WebTerminal::class);
    });
});

describe('ssh', function () {
    it('configures SSH connection with password', function () {
        $component = WebTerminal::make()
            ->ssh(
                host: '192.168.1.100',
                username: 'admin',
                password: 'secret123',
                port: 22
            );

        $config = $component->getConnectionConfig();

        expect($config['type'])->toBe('ssh')
            ->and($config['host'])->toBe('192.168.1.100')
            ->and($config['username'])->toBe('admin')
            ->and($config['password'])->toBe('secret123')
            ->and($config['port'])->toBe(22);
    });

    it('configures SSH connection with key content', function () {
        $keyContent = '-----BEGIN OPENSSH PRIVATE KEY-----
test-key-content
-----END OPENSSH PRIVATE KEY-----';

        $component = WebTerminal::make()
            ->ssh(
                host: '192.168.1.100',
                username: 'admin',
                key: $keyContent,
                port: 2222
            );

        $config = $component->getConnectionConfig();

        expect($config['type'])->toBe('ssh')
            ->and($config['host'])->toBe('192.168.1.100')
            ->and($config['username'])->toBe('admin')
            ->and($config['private_key'])->toBe($keyContent)
            ->and($config['port'])->toBe(2222);
    });

    it('configures SSH connection with key and passphrase', function () {
        $keyContent = '-----BEGIN OPENSSH PRIVATE KEY-----
encrypted-key
-----END OPENSSH PRIVATE KEY-----';

        $component = WebTerminal::make()
            ->ssh(
                host: 'localhost',
                username: 'root',
                key: $keyContent,
                passphrase: 'my-secret-passphrase',
                port: 22
            );

        $config = $component->getConnectionConfig();

        expect($config['passphrase'])->toBe('my-secret-passphrase');
    });

    it('uses default port 22 when not specified', function () {
        $component = WebTerminal::make()
            ->ssh(
                host: 'localhost',
                username: 'root'
            );

        $config = $component->getConnectionConfig();

        expect($config['port'])->toBe(22);
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->ssh(host: 'localhost', username: 'root'))->toBe($component);
    });
});

describe('local', function () {
    it('configures local connection', function () {
        $component = WebTerminal::make()
            ->local();

        $config = $component->getConnectionConfig();

        expect($config['type'])->toBe('local');
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->local())->toBe($component);
    });
});

describe('height', function () {
    it('has default height of 350px', function () {
        $component = WebTerminal::make();

        expect($component->getHeight())->toBe('350px');
    });

    it('sets custom height', function () {
        $component = WebTerminal::make()
            ->height('600px');

        expect($component->getHeight())->toBe('600px');
    });

    it('evaluates closure for height', function () {
        $component = WebTerminal::make()
            ->height(fn () => '500px');

        expect($component->getHeight())->toBe('500px');
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->height('400px'))->toBe($component);
    });
});

describe('allowedCommands', function () {
    it('has empty allowed commands by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowedCommands())->toBe([]);
    });

    it('sets allowed commands', function () {
        $commands = ['ls', 'pwd', 'cd'];
        $component = WebTerminal::make()
            ->allowedCommands($commands);

        expect($component->getAllowedCommands())->toBe($commands);
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowedCommands(['ls']))->toBe($component);
    });
});

describe('allowAllCommands', function () {
    it('does not allow all commands by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowAll())->toBeFalse();
    });

    it('enables allow all commands', function () {
        $component = WebTerminal::make()
            ->allowAllCommands();

        expect($component->getAllowAll())->toBeTrue();
    });

    it('disables allow all commands when passed false', function () {
        $component = WebTerminal::make()
            ->allowAllCommands(true)
            ->allowAllCommands(false);

        expect($component->getAllowAll())->toBeFalse();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowAllCommands())->toBe($component);
    });
});

describe('workingDirectory', function () {
    it('has null working directory by default', function () {
        $component = WebTerminal::make();

        expect($component->getWorkingDirectory())->toBeNull();
    });

    it('sets working directory', function () {
        $component = WebTerminal::make()
            ->workingDirectory('/home/user');

        expect($component->getWorkingDirectory())->toBe('/home/user');
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->workingDirectory('/tmp'))->toBe($component);
    });
});

describe('timeout', function () {
    it('has default timeout of 10 seconds', function () {
        $component = WebTerminal::make();

        expect($component->getTimeout())->toBe(10);
    });

    it('sets custom timeout', function () {
        $component = WebTerminal::make()
            ->timeout(30);

        expect($component->getTimeout())->toBe(30);
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->timeout(60))->toBe($component);
    });
});

describe('prompt', function () {
    it('has default prompt', function () {
        $component = WebTerminal::make();

        expect($component->getPrompt())->toBe('$ ');
    });

    it('sets custom prompt', function () {
        $component = WebTerminal::make()
            ->prompt('root@container:~# ');

        expect($component->getPrompt())->toBe('root@container:~# ');
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->prompt('> '))->toBe($component);
    });
});

describe('historyLimit', function () {
    it('has default history limit of 50', function () {
        $component = WebTerminal::make();

        expect($component->getHistoryLimit())->toBe(50);
    });

    it('sets custom history limit', function () {
        $component = WebTerminal::make()
            ->historyLimit(100);

        expect($component->getHistoryLimit())->toBe(100);
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->historyLimit(25))->toBe($component);
    });
});

describe('loginShell', function () {
    it('has login shell disabled by default', function () {
        $component = WebTerminal::make();

        expect($component->getUseLoginShell())->toBeFalse();
    });

    it('enables login shell', function () {
        $component = WebTerminal::make()
            ->loginShell();

        expect($component->getUseLoginShell())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->loginShell())->toBe($component);
    });
});

describe('fluent api', function () {
    it('supports full method chaining for SSH with key', function () {
        $keyContent = '-----BEGIN OPENSSH PRIVATE KEY-----
test-key
-----END OPENSSH PRIVATE KEY-----';

        $component = WebTerminal::make()
            ->ssh(
                host: 'localhost',
                username: 'root',
                key: $keyContent,
                port: 2222
            )
            ->workingDirectory('/root')
            ->allowAllCommands()
            ->timeout(30)
            ->prompt('root@container:~# ')
            ->historyLimit(50)
            ->height('550px');

        expect($component)->toBeInstanceOf(WebTerminal::class)
            ->and($component->getConnectionConfig()['type'])->toBe('ssh')
            ->and($component->getConnectionConfig()['host'])->toBe('localhost')
            ->and($component->getConnectionConfig()['username'])->toBe('root')
            ->and($component->getConnectionConfig()['private_key'])->toBe($keyContent)
            ->and($component->getConnectionConfig()['port'])->toBe(2222)
            ->and($component->getWorkingDirectory())->toBe('/root')
            ->and($component->getAllowAll())->toBeTrue()
            ->and($component->getTimeout())->toBe(30)
            ->and($component->getPrompt())->toBe('root@container:~# ')
            ->and($component->getHistoryLimit())->toBe(50)
            ->and($component->getHeight())->toBe('550px');
    });
});

describe('inheritance', function () {
    it('extends Filament Livewire component', function () {
        expect(is_subclass_of(WebTerminal::class, \Filament\Schemas\Components\Livewire::class))->toBeTrue();
    });

    it('is a Filament schema component', function () {
        expect(is_subclass_of(WebTerminal::class, \Filament\Schemas\Components\Component::class))->toBeTrue();
    });
});

describe('component properties', function () {
    it('has getComponentProperties method', function () {
        expect(method_exists(WebTerminal::class, 'getComponentProperties'))->toBeTrue();
    });
});

describe('startConnected', function () {
    it('does not start connected by default', function () {
        $component = WebTerminal::make();

        expect($component->getStartConnected())->toBeFalse();
    });

    it('enables start connected', function () {
        $component = WebTerminal::make()
            ->startConnected();

        expect($component->getStartConnected())->toBeTrue();
    });

    it('disables start connected when passed false', function () {
        $component = WebTerminal::make()
            ->startConnected(true)
            ->startConnected(false);

        expect($component->getStartConnected())->toBeFalse();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->startConnected())->toBe($component);
    });
});

describe('title', function () {
    it('has default title of Terminal', function () {
        $component = WebTerminal::make();

        expect($component->getTitle())->toBe('Terminal');
    });

    it('sets custom title', function () {
        $component = WebTerminal::make()
            ->title('My Server Console');

        expect($component->getTitle())->toBe('My Server Console');
    });

    it('evaluates closure for title', function () {
        $component = WebTerminal::make()
            ->title(fn () => 'Dynamic Title');

        expect($component->getTitle())->toBe('Dynamic Title');
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->title('Custom Title'))->toBe($component);
    });
});

describe('windowControls', function () {
    it('shows window controls by default', function () {
        $component = WebTerminal::make();

        expect($component->getShowWindowControls())->toBeTrue();
    });

    it('hides window controls when set to false', function () {
        $component = WebTerminal::make()
            ->windowControls(false);

        expect($component->getShowWindowControls())->toBeFalse();
    });

    it('shows window controls when set to true', function () {
        $component = WebTerminal::make()
            ->windowControls(false)
            ->windowControls(true);

        expect($component->getShowWindowControls())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->windowControls(false))->toBe($component);
    });
});

describe('allowPipes', function () {
    it('does not allow pipes by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowPipes())->toBeFalse();
    });

    it('enables pipes', function () {
        $component = WebTerminal::make()->allowPipes();

        expect($component->getAllowPipes())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowPipes())->toBe($component);
    });
});

describe('allowRedirection', function () {
    it('does not allow redirection by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowRedirection())->toBeFalse();
    });

    it('enables redirection', function () {
        $component = WebTerminal::make()->allowRedirection();

        expect($component->getAllowRedirection())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowRedirection())->toBe($component);
    });
});

describe('allowChaining', function () {
    it('does not allow chaining by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowChaining())->toBeFalse();
    });

    it('enables chaining', function () {
        $component = WebTerminal::make()->allowChaining();

        expect($component->getAllowChaining())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowChaining())->toBe($component);
    });
});

describe('allowExpansion', function () {
    it('does not allow expansion by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowExpansion())->toBeFalse();
    });

    it('enables expansion', function () {
        $component = WebTerminal::make()->allowExpansion();

        expect($component->getAllowExpansion())->toBeTrue();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowExpansion())->toBe($component);
    });
});

describe('allowAllShellOperators', function () {
    it('does not allow all shell operators by default', function () {
        $component = WebTerminal::make();

        expect($component->getAllowAllShellOperators())->toBeFalse();
    });

    it('enables all shell operators', function () {
        $component = WebTerminal::make()->allowAllShellOperators();

        expect($component->getAllowAllShellOperators())->toBeTrue();
        expect($component->getAllowPipes())->toBeTrue();
        expect($component->getAllowRedirection())->toBeTrue();
        expect($component->getAllowChaining())->toBeTrue();
        expect($component->getAllowExpansion())->toBeTrue();
    });

    it('can disable all shell operators', function () {
        $component = WebTerminal::make()
            ->allowAllShellOperators()
            ->allowAllShellOperators(false);

        expect($component->getAllowAllShellOperators())->toBeFalse();
    });

    it('returns self for method chaining', function () {
        $component = WebTerminal::make();

        expect($component->allowAllShellOperators())->toBe($component);
    });
});

describe('backward compatibility', function () {
    it('WebTerminalEmbed alias exists', function () {
        expect(class_exists(\MWGuerra\WebTerminal\Schemas\Components\WebTerminalEmbed::class))->toBeTrue();
    });
});
