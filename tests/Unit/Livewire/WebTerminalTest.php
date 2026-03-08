<?php

declare(strict_types=1);

use Livewire\Livewire;
use MWGuerra\WebTerminal\Data\CommandResult;
use MWGuerra\WebTerminal\Data\ConnectionConfig;
use MWGuerra\WebTerminal\Livewire\WebTerminal;

describe('WebTerminal Component', function () {
    describe('mounting', function () {
        it('mounts with default configuration', function () {
            Livewire::test(WebTerminal::class)
                ->assertSet('command', '')
                ->assertSet('historyLimit', 5)
                ->assertSet('prompt', '$ ')
                ->assertSet('isExecuting', false);
        });

        it('mounts with custom allowed commands', function () {
            Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['ls', 'pwd', 'whoami'],
            ])
                ->assertSet('allowedCommands', ['ls', 'pwd', 'whoami']);
        });

        it('mounts with connection array', function () {
            $component = Livewire::test(WebTerminal::class, [
                'connection' => ['type' => 'local'],
            ]);

            // connectionConfig is protected, verify through public getter
            expect($component->instance()->getConnectionType())->toBe('Local');
        });

        it('mounts with ConnectionConfig object', function () {
            $config = ConnectionConfig::local();

            $component = Livewire::test(WebTerminal::class, [
                'connection' => $config,
            ]);

            // connectionConfig is protected, verify through public getter
            expect($component->instance()->getConnectionType())->toBe('Local');
        });

        it('mounts with custom timeout', function () {
            Livewire::test(WebTerminal::class, [
                'timeout' => 30,
            ])
                ->assertSet('timeout', 30);
        });

        it('mounts with custom prompt', function () {
            Livewire::test(WebTerminal::class, [
                'prompt' => '> ',
            ])
                ->assertSet('prompt', '> ');
        });

        it('mounts with custom history limit', function () {
            Livewire::test(WebTerminal::class, [
                'historyLimit' => 10,
            ])
                ->assertSet('historyLimit', 10);
        });

        it('mounts with custom max output lines', function () {
            Livewire::test(WebTerminal::class, [
                'maxOutputLines' => 500,
            ])
                ->assertSet('maxOutputLines', 500);
        });

        it('shows welcome message on mount', function () {
            $component = Livewire::test(WebTerminal::class);

            expect($component->get('output'))->toHaveCount(1);
            expect($component->get('output')[0]['content'])->toContain('Terminal ready');
        });

        it('starts disconnected by default', function () {
            $component = Livewire::test(WebTerminal::class);

            expect($component->get('isConnected'))->toBeFalse();
        });

        it('mounts with default title', function () {
            Livewire::test(WebTerminal::class)
                ->assertSet('title', 'Terminal');
        });

        it('mounts with custom title', function () {
            Livewire::test(WebTerminal::class, [
                'title' => 'My Server Console',
            ])
                ->assertSet('title', 'My Server Console');
        });

        it('mounts with showWindowControls true by default', function () {
            Livewire::test(WebTerminal::class)
                ->assertSet('showWindowControls', true);
        });

        it('mounts with showWindowControls false when configured', function () {
            Livewire::test(WebTerminal::class, [
                'showWindowControls' => false,
            ])
                ->assertSet('showWindowControls', false);
        });

        it('mounts with startConnected false by default', function () {
            Livewire::test(WebTerminal::class)
                ->assertSet('startConnected', false);
        });

        it('auto-connects when startConnected is true', function () {
            $component = Livewire::test(WebTerminal::class, [
                'startConnected' => true,
            ]);

            // Should be connected automatically
            expect($component->get('isConnected'))->toBeTrue();

            // Should have connected messages (no "Terminal ready" welcome)
            $output = $component->get('output');
            $hasConnectedMessage = false;
            foreach ($output as $line) {
                if (str_contains($line['content'] ?? '', 'Connected')) {
                    $hasConnectedMessage = true;
                    break;
                }
            }
            expect($hasConnectedMessage)->toBeTrue();
        });

        it('can connect the terminal', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect');

            expect($component->get('isConnected'))->toBeTrue();
        });

        it('can disconnect the terminal', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->call('disconnect');

            expect($component->get('isConnected'))->toBeFalse();
        });

        it('shows connecting and connected messages on successful connect', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect');

            $output = $component->get('output');

            // Should have: welcome + connecting + connected
            expect($output)->toHaveCount(3);
            expect($output[1]['content'])->toContain('Connecting to');
            expect($output[2]['content'])->toContain('Connected');
            expect($output[2]['content'])->toContain('successfully');
        });

        it('shows connection description in messages', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect');

            $output = $component->get('output');

            // Local terminal should show "Local terminal"
            expect($output[1]['content'])->toContain('Local terminal');
        });

        it('shows disconnection message with connection type', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->call('disconnect');

            $output = $component->get('output');
            $lastOutput = end($output);

            expect($lastOutput['content'])->toContain('Disconnected from');
            expect($lastOutput['content'])->toContain('Local terminal');
        });

        it('stays disconnected if connection fails', function () {
            // SSH connection with invalid host should fail
            $component = Livewire::test(WebTerminal::class, [
                'connection' => [
                    'type' => 'ssh',
                    'host' => 'invalid.nonexistent.host.local',
                    'username' => 'testuser',
                    'password' => 'testpass',
                    'port' => 22,
                ],
            ])
                ->call('connect');

            // Should stay disconnected
            expect($component->get('isConnected'))->toBeFalse();

            // Should have error message in output
            $output = $component->get('output');
            $hasError = false;
            foreach ($output as $line) {
                if ($line['type'] === 'error' || str_contains($line['content'], 'failed') || str_contains($line['content'], 'error')) {
                    $hasError = true;
                    break;
                }
            }
            expect($hasError)->toBeTrue();
        });
    });

    describe('built-in commands', function () {
        it('handles clear command', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', 'clear')
                ->call('executeCommand');

            // After clear, output should have only the "cleared" message
            expect($component->get('output'))->toHaveCount(1);
            expect($component->get('output')[0]['content'])->toContain('cleared');
        });

        it('handles history command', function () {
            Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', 'history')
                ->call('executeCommand')
                ->assertSee('No commands in history');
        });

        it('handles help command', function () {
            Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', 'help')
                ->call('executeCommand')
                ->assertSee('Built-in commands');
        });

        it('clears command after execution', function () {
            Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', 'help')
                ->call('executeCommand')
                ->assertSet('command', '');
        });

        it('does not execute commands when disconnected', function () {
            $component = Livewire::test(WebTerminal::class)
                ->set('command', 'help')
                ->call('executeCommand');

            // Command should still be there (not executed)
            // Actually the command is cleared but nothing happens
            expect($component->get('output'))->toHaveCount(1); // Only welcome message
        });
    });

    describe('command validation', function () {
        it('rejects commands not in whitelist', function () {
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['ls'],
            ])
                ->call('connect')
                ->set('command', 'rm -rf /')
                ->call('executeCommand');

            // Check that output contains an error (either "not allowed" or similar error message)
            $output = $component->get('output');
            $hasError = false;
            foreach ($output as $line) {
                $content = strtolower($line['content'] ?? '');
                // The validator returns "Command not allowed" or similar message
                if (str_contains($content, 'not allowed') ||
                    str_contains($content, 'command not') ||
                    str_contains($content, 'not permitted') ||
                    ($line['css_class'] ?? '') === 'terminal-error') {
                    $hasError = true;
                    break;
                }
            }
            expect($hasError)->toBeTrue();
        });

        it('accepts whitelisted commands', function () {
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['echo *'],
            ])
                ->call('connect')
                ->set('command', 'echo hello')
                ->call('executeCommand');

            // Command should be added to history
            expect($component->get('history'))->toContain('echo hello');
        });

        it('ignores empty commands', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', '')
                ->call('executeCommand');

            // Output should only have welcome + connecting + connected messages
            expect($component->get('output'))->toHaveCount(3);
        });

        it('ignores whitespace-only commands', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', '   ')
                ->call('executeCommand');

            // Output should only have welcome + connecting + connected messages
            expect($component->get('output'))->toHaveCount(3);
        });
    });

    describe('command history', function () {
        it('adds non-builtin commands to history', function () {
            // Built-in commands like 'help' don't go to history
            // External commands do
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['echo *'],
            ])
                ->call('connect')
                ->set('command', 'echo test')
                ->call('executeCommand');

            expect($component->get('history'))->toContain('echo test');
        });

        it('does not add built-in commands to history', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', 'help')
                ->call('executeCommand');

            // Built-in commands don't go to external history
            expect($component->get('history'))->toBe([]);
        });

        it('respects history limit', function () {
            $component = Livewire::test(WebTerminal::class, [
                'historyLimit' => 3,
                'allowedCommands' => ['cmd1', 'cmd2', 'cmd3', 'cmd4'],
            ])
                ->call('connect');

            foreach (['cmd1', 'cmd2', 'cmd3', 'cmd4'] as $cmd) {
                $component->set('command', $cmd)->call('executeCommand');
            }

            $history = $component->get('history');

            expect($history)->toHaveCount(3);
            expect($history)->not->toContain('cmd1');
            expect($history)->toContain('cmd4');
        });

        it('navigates history up', function () {
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['first', 'second'],
            ])
                ->call('connect')
                ->set('command', 'first')
                ->call('executeCommand')
                ->set('command', 'second')
                ->call('executeCommand')
                ->call('historyUp');

            expect($component->get('command'))->toBe('second');
        });

        it('navigates history down', function () {
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['first', 'second'],
            ])
                ->call('connect')
                ->set('command', 'first')
                ->call('executeCommand')
                ->set('command', 'second')
                ->call('executeCommand')
                ->call('historyUp')
                ->call('historyUp')
                ->call('historyDown');

            expect($component->get('command'))->toBe('second');
        });

        it('clears command when navigating past history', function () {
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['first'],
            ])
                ->call('connect')
                ->set('command', 'first')
                ->call('executeCommand')
                ->call('historyUp')
                ->call('historyDown');

            expect($component->get('command'))->toBe('');
        });

        it('resets history index on input', function () {
            $component = Livewire::test(WebTerminal::class, [
                'allowedCommands' => ['first'],
            ])
                ->call('connect')
                ->set('command', 'first')
                ->call('executeCommand')
                ->call('historyUp')
                ->call('resetHistoryIndex');

            expect($component->get('historyIndex'))->toBe(-1);
        });
    });

    describe('getPlainTextOutput', function () {
        it('returns empty string when no output', function () {
            $component = Livewire::test(WebTerminal::class);

            // Clear the welcome message
            $component->call('clearOutput');

            expect($component->instance()->getPlainTextOutput())->toBe('');
        });

        it('returns plain text from output lines', function () {
            $component = Livewire::test(WebTerminal::class);
            $component->call('clearOutput');

            // Manually add output lines via reflection to test the method
            $instance = $component->instance();
            $instance->output = [
                ['type' => 'command', 'content' => '$ ls', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-command'],
                ['type' => 'stdout', 'content' => 'file1.txt', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
                ['type' => 'stdout', 'content' => 'file2.txt', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
            ];

            $result = $instance->getPlainTextOutput();
            expect($result)->toBe("$ ls\nfile1.txt\nfile2.txt");
        });

        it('strips ANSI codes from output', function () {
            $component = Livewire::test(WebTerminal::class);
            $instance = $component->instance();
            $instance->output = [
                ['type' => 'stdout', 'content' => "\x1b[31mred text\x1b[0m", 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
            ];

            $result = $instance->getPlainTextOutput();
            expect($result)->toBe('red text');
        });

        it('skips empty content lines', function () {
            $component = Livewire::test(WebTerminal::class);
            $instance = $component->instance();
            $instance->output = [
                ['type' => 'command', 'content' => '$ pwd', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-command'],
                ['type' => 'stdout', 'content' => '', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
                ['type' => 'stdout', 'content' => '/home/user', 'timestamp' => now()->toISOString(), 'css_class' => 'terminal-stdout'],
            ];

            $result = $instance->getPlainTextOutput();
            expect($result)->toBe("$ pwd\n/home/user");
        });
    });

    describe('output management', function () {
        it('limits output to max lines', function () {
            $component = Livewire::test(WebTerminal::class, [
                'maxOutputLines' => 10,
            ])
                ->call('connect');

            // Generate more output than max by calling clear multiple times
            // Clear produces exactly 1 output line each time
            for ($i = 0; $i < 20; $i++) {
                $component->set('command', 'clear')->call('executeCommand');
            }

            // Output should be capped at maxOutputLines
            expect(count($component->get('output')))->toBeLessThanOrEqual(10);
        });
    });

    describe('clear method', function () {
        it('clears all output', function () {
            $component = Livewire::test(WebTerminal::class)
                ->call('connect')
                ->set('command', 'help')
                ->call('executeCommand')
                ->call('clear');

            // Should have only the "cleared" message
            expect($component->get('output'))->toHaveCount(1);
        });
    });

    describe('rendering', function () {
        it('renders the terminal view', function () {
            Livewire::test(WebTerminal::class)
                ->assertViewIs('web-terminal::terminal');
        });

        it('renders with terminal class', function () {
            Livewire::test(WebTerminal::class)
                ->assertSee('web-terminal');
        });

        it('renders prompt', function () {
            Livewire::test(WebTerminal::class, [
                'prompt' => 'user@host:~$ ',
            ])
                ->assertSee('user@host:~$');
        });
    });
});

describe('session-based connection config', function () {
    it('generates unique component ID on mount', function () {
        $component = Livewire::test(WebTerminal::class);

        $componentId = $component->get('componentId');

        expect($componentId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('generates different component IDs for different instances', function () {
        $component1 = Livewire::test(WebTerminal::class);
        $component2 = Livewire::test(WebTerminal::class);

        expect($component1->get('componentId'))->not->toBe($component2->get('componentId'));
    });

    it('preserves connection type through getter after mount', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => ['type' => 'local'],
        ]);

        expect($component->instance()->getConnectionType())->toBe('Local');
    });

    it('preserves SSH connection config through getter', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.example.com',
                'port' => 2222,
                'username' => 'testuser',
            ],
        ]);

        expect($component->instance()->getConnectionType())->toBe('Ssh');
        expect($component->instance()->getDisplayHost())->toBe('test.example.com');
        expect($component->instance()->getDisplayPort())->toBe(2222);
        expect($component->instance()->getDisplayUsername())->toBe('testuser');
    });
});

describe('display getter methods', function () {
    it('returns null for display host when not configured', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => ['type' => 'local'],
        ]);

        expect($component->instance()->getDisplayHost())->toBeNull();
    });

    it('returns configured host for SSH connections', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'server.example.com',
            ],
        ]);

        expect($component->instance()->getDisplayHost())->toBe('server.example.com');
    });

    it('returns default port 22 when not configured', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => ['type' => 'ssh', 'host' => 'test.com'],
        ]);

        expect($component->instance()->getDisplayPort())->toBe(22);
    });

    it('returns custom port when configured', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'port' => 2222,
            ],
        ]);

        expect($component->instance()->getDisplayPort())->toBe(2222);
    });

    it('returns null for username when not configured', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => ['type' => 'local'],
        ]);

        expect($component->instance()->getDisplayUsername())->toBeNull();
    });

    it('returns configured username for SSH', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'username' => 'admin',
            ],
        ]);

        expect($component->instance()->getDisplayUsername())->toBe('admin');
    });

    it('returns password auth method when using password', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'username' => 'user',
                'password' => 'secret',
            ],
        ]);

        expect($component->instance()->getDisplayAuthMethod())->toBe('password');
    });

    it('returns key auth method when using private key', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'username' => 'user',
                'private_key' => '-----BEGIN RSA KEY-----',
            ],
        ]);

        expect($component->instance()->getDisplayAuthMethod())->toBe('key');
    });

    it('returns null for working directory when not configured', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => ['type' => 'local'],
        ]);

        expect($component->instance()->getDisplayWorkingDirectory())->toBeNull();
    });

    it('returns configured working directory', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'local',
                'working_directory' => '/var/www/html',
            ],
        ]);

        expect($component->instance()->getDisplayWorkingDirectory())->toBe('/var/www/html');
    });
});

describe('security', function () {
    it('does not expose password in public properties', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'username' => 'user',
                'password' => 'supersecret',
            ],
        ]);

        // Get the HTML output
        $html = $component->html();

        // Password should NOT be in the HTML (not even in data attributes or wire:data)
        expect($html)->not->toContain('supersecret');
    });

    it('does not expose private key in public properties', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'username' => 'user',
                'private_key' => '-----BEGIN RSA PRIVATE KEY-----SECRETKEY-----END RSA PRIVATE KEY-----',
            ],
        ]);

        $html = $component->html();

        expect($html)->not->toContain('-----BEGIN RSA PRIVATE KEY-----');
        expect($html)->not->toContain('SECRETKEY');
    });

    it('does not expose passphrase in public properties', function () {
        $component = Livewire::test(WebTerminal::class, [
            'connection' => [
                'type' => 'ssh',
                'host' => 'test.com',
                'username' => 'user',
                'private_key' => '-----BEGIN RSA KEY-----',
                'passphrase' => 'mypassphrase',
            ],
        ]);

        $html = $component->html();

        expect($html)->not->toContain('mypassphrase');
    });
});

describe('TerminalBuilder', function () {
    it('creates a builder instance', function () {
        $builder = WebTerminal::make();

        expect($builder)->toBeInstanceOf(\MWGuerra\WebTerminal\Livewire\TerminalBuilder::class);
    });

    it('configures local connection', function () {
        $builder = WebTerminal::make()->local();
        $params = $builder->getParameters();

        expect($params['connection']['type'])->toBe('local');
    });

    it('configures SSH connection with password', function () {
        $builder = WebTerminal::make()
            ->sshWithPassword('example.com', 'admin', 'secret');

        $params = $builder->getParameters();

        expect($params['connection']['type'])->toBe('ssh');
        expect($params['connection']['host'])->toBe('example.com');
        expect($params['connection']['username'])->toBe('admin');
    });

    it('configures SSH connection with key', function () {
        $builder = WebTerminal::make()
            ->sshWithKey('example.com', 'admin', '-----BEGIN RSA KEY-----');

        $params = $builder->getParameters();

        expect($params['connection']['type'])->toBe('ssh');
        expect($params['connection']['private_key'])->toBe('-----BEGIN RSA KEY-----');
    });

    it('sets allowed commands', function () {
        $builder = WebTerminal::make()
            ->allowedCommands(['ls', 'pwd']);

        $params = $builder->getParameters();

        expect($params['allowedCommands'])->toBe(['ls', 'pwd']);
    });

    it('sets timeout', function () {
        $builder = WebTerminal::make()->timeout(30);
        $params = $builder->getParameters();

        expect($params['timeout'])->toBe(30);
    });

    it('sets prompt', function () {
        $builder = WebTerminal::make()->prompt('> ');
        $params = $builder->getParameters();

        expect($params['prompt'])->toBe('> ');
    });

    it('sets history limit', function () {
        $builder = WebTerminal::make()->historyLimit(10);
        $params = $builder->getParameters();

        expect($params['historyLimit'])->toBe(10);
    });

    it('chains multiple configurations', function () {
        $builder = WebTerminal::make()
            ->local()
            ->allowedCommands(['ls', 'pwd'])
            ->timeout(20)
            ->prompt('$ ')
            ->historyLimit(5);

        $params = $builder->getParameters();

        expect($params['connection']['type'])->toBe('local');
        expect($params['allowedCommands'])->toBe(['ls', 'pwd']);
        expect($params['timeout'])->toBe(20);
        expect($params['prompt'])->toBe('$ ');
        expect($params['historyLimit'])->toBe(5);
    });

    describe('TUI detection in synchronous output', function () {
        it('shows error when command output contains TUI sequences', function () {
            $component = Livewire::test(WebTerminal::class);
            $component->call('connect');

            $instance = $component->instance();

            $result = CommandResult::success(
                stdout: "some output\x1b[?1049hmore output",
                executionTime: 0.1,
                command: 'vim file.txt',
            );

            $method = new ReflectionMethod($instance, 'addCommandResultOutput');
            $method->invoke($instance, $result);

            $output = $component->get('output');
            $errorFound = false;
            foreach ($output as $line) {
                if ($line['type'] === 'error' && str_contains($line['content'], 'full-screen terminal')) {
                    $errorFound = true;
                    break;
                }
            }

            expect($errorFound)->toBeTrue();
        });

        it('does not trigger TUI detection for normal command output', function () {
            $component = Livewire::test(WebTerminal::class);
            $component->call('connect');

            $instance = $component->instance();

            $result = CommandResult::success(
                stdout: 'hello world',
                executionTime: 0.1,
                command: 'echo hello world',
            );

            $method = new ReflectionMethod($instance, 'addCommandResultOutput');
            $method->invoke($instance, $result);

            $output = $component->get('output');
            $hasError = false;
            foreach ($output as $line) {
                if ($line['type'] === 'error' && str_contains($line['content'], 'full-screen terminal')) {
                    $hasError = true;
                    break;
                }
            }

            expect($hasError)->toBeFalse();
        });
    });

    it('generates HTML tag', function () {
        $html = WebTerminal::make()
            ->local()
            ->timeout(30)
            ->toHtml();

        expect($html)->toContain('livewire:web-terminal');
        expect($html)->toContain(':timeout="30"');
    });
});
