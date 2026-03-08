<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Exceptions\ValidationException;
use MWGuerra\WebTerminal\Security\CommandValidator;
use MWGuerra\WebTerminal\Security\ValidationResult;

describe('CommandValidator', function () {
    describe('whitelist lookup', function () {
        it('allows exact match commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'pwd', 'whoami'],
            );

            expect($validator->isAllowed('ls'))->toBeTrue();
            expect($validator->isAllowed('pwd'))->toBeTrue();
            expect($validator->isAllowed('whoami'))->toBeTrue();
        });

        it('rejects commands not in whitelist', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'pwd'],
            );

            expect($validator->isAllowed('rm'))->toBeFalse();
            expect($validator->isAllowed('cat'))->toBeFalse();
            expect($validator->isAllowed('sudo'))->toBeFalse();
        });

        it('allows commands with arguments when binary is whitelisted', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'cat'],
            );

            expect($validator->isAllowed('ls -la'))->toBeTrue();
            expect($validator->isAllowed('ls -la /tmp'))->toBeTrue();
            expect($validator->isAllowed('cat /etc/hosts'))->toBeTrue();
        });

        it('allows exact command with arguments match', function () {
            $validator = new CommandValidator(
                allowedCommands: ['df -h', 'free -m'],
            );

            expect($validator->isAllowed('df -h'))->toBeTrue();
            expect($validator->isAllowed('free -m'))->toBeTrue();
        });

        it('supports wildcard patterns for any arguments', function () {
            $validator = new CommandValidator(
                allowedCommands: ['git *'],
            );

            expect($validator->isAllowed('git status'))->toBeTrue();
            expect($validator->isAllowed('git log --oneline'))->toBeTrue();
            expect($validator->isAllowed('git commit -m "test"'))->toBeTrue();
        });

        it('supports multi-word wildcard patterns like php artisan *', function () {
            $validator = new CommandValidator(
                allowedCommands: ['php artisan *', 'composer *'],
            );

            expect($validator->isAllowed('php artisan tinker'))->toBeTrue();
            expect($validator->isAllowed('php artisan migrate'))->toBeTrue();
            expect($validator->isAllowed('php artisan queue:work'))->toBeTrue();
            expect($validator->isAllowed('php artisan reverb:start --host=0.0.0.0'))->toBeTrue();
            expect($validator->isAllowed('composer install'))->toBeTrue();
            // Bare 'php' without 'artisan' should not match
            expect($validator->isAllowed('php -v'))->toBeFalse();
            // 'php artisan' without subcommand should still match (it has args: 'artisan')
            expect($validator->isAllowed('php artisan'))->toBeFalse();
        });

        it('supports three-word wildcard patterns', function () {
            $validator = new CommandValidator(
                allowedCommands: ['docker compose exec *'],
            );

            expect($validator->isAllowed('docker compose exec app bash'))->toBeTrue();
            expect($validator->isAllowed('docker compose up'))->toBeFalse();
        });

        it('has O(1) lookup performance for exact matches', function () {
            // Create validator with many commands
            $commands = [];
            for ($i = 0; $i < 10000; $i++) {
                $commands[] = "command{$i}";
            }
            $validator = new CommandValidator(allowedCommands: $commands);

            // Should be instant regardless of list size
            $start = hrtime(true);
            $validator->isAllowed('command9999');
            $end = hrtime(true);

            // Less than 1ms for lookup
            expect(($end - $start) / 1e6)->toBeLessThan(1);
        });
    });

    describe('command parsing', function () {
        it('parses simple commands', function () {
            $validator = new CommandValidator;

            $result = $validator->parseCommand('ls');

            expect($result['binary'])->toBe('ls');
            expect($result['arguments'])->toBe('');
            expect($result['parts'])->toBe([]);
        });

        it('parses commands with arguments', function () {
            $validator = new CommandValidator;

            $result = $validator->parseCommand('ls -la /tmp');

            expect($result['binary'])->toBe('ls');
            expect($result['arguments'])->toBe('-la /tmp');
            expect($result['parts'])->toBe(['-la', '/tmp']);
        });

        it('handles quoted strings', function () {
            $validator = new CommandValidator;

            $result = $validator->parseCommand('echo "hello world"');

            expect($result['binary'])->toBe('echo');
            expect($result['parts'])->toBe(['hello world']);
        });

        it('handles single-quoted strings', function () {
            $validator = new CommandValidator;

            $result = $validator->parseCommand("echo 'hello world'");

            expect($result['binary'])->toBe('echo');
            expect($result['parts'])->toBe(['hello world']);
        });

        it('handles empty commands', function () {
            $validator = new CommandValidator;

            $result = $validator->parseCommand('');

            expect($result['binary'])->toBe('');
            expect($result['arguments'])->toBe('');
            expect($result['parts'])->toBe([]);
        });

        it('trims whitespace', function () {
            $validator = new CommandValidator;

            $result = $validator->parseCommand('  ls   -la  ');

            expect($result['binary'])->toBe('ls');
            expect($result['arguments'])->toBe('-la');
        });
    });

    describe('blocked characters', function () {
        it('blocks semicolon for command chaining', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
                blockedCharacters: [';'],
            );

            expect($validator->isAllowed('ls; rm -rf /'))->toBeFalse();
        });

        it('blocks pipe for output redirection', function () {
            $validator = new CommandValidator(
                allowedCommands: ['cat'],
                blockedCharacters: ['|'],
            );

            expect($validator->isAllowed('cat /etc/passwd | grep root'))->toBeFalse();
        });

        it('blocks ampersand for background/chaining', function () {
            $validator = new CommandValidator(
                allowedCommands: ['sleep'],
                blockedCharacters: ['&'],
            );

            expect($validator->isAllowed('sleep 10 &'))->toBeFalse();
            expect($validator->isAllowed('true && rm -rf /'))->toBeFalse();
        });

        it('blocks dollar sign for variable expansion', function () {
            $validator = new CommandValidator(
                allowedCommands: ['echo'],
                blockedCharacters: ['$'],
            );

            expect($validator->isAllowed('echo $HOME'))->toBeFalse();
            expect($validator->isAllowed('echo $(whoami)'))->toBeFalse();
        });

        it('blocks backticks for command substitution', function () {
            $validator = new CommandValidator(
                allowedCommands: ['echo'],
                blockedCharacters: ['`'],
            );

            expect($validator->isAllowed('echo `whoami`'))->toBeFalse();
        });

        it('blocks newlines for command injection', function () {
            $validator = new CommandValidator(
                allowedCommands: ['echo'],
                blockedCharacters: ["\n", "\r"],
            );

            expect($validator->isAllowed("echo hello\nrm -rf /"))->toBeFalse();
        });

        it('allows commands without blocked characters', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'cat'],
                blockedCharacters: [';', '|', '&', '$', '`'],
            );

            expect($validator->isAllowed('ls -la'))->toBeTrue();
            expect($validator->isAllowed('cat /etc/hosts'))->toBeTrue();
        });
    });

    describe('validation result', function () {
        it('returns passed result for valid commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            $result = $validator->check('ls');

            expect($result)->toBeInstanceOf(ValidationResult::class);
            expect($result->isValid())->toBeTrue();
            expect($result->isFailed())->toBeFalse();
            expect($result->getException())->toBeNull();
        });

        it('returns failed result for invalid commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            $result = $validator->check('rm -rf /');

            expect($result->isValid())->toBeFalse();
            expect($result->isFailed())->toBeTrue();
            expect($result->getException())->toBeInstanceOf(ValidationException::class);
            expect($result->getErrorCode())->toBe('command_not_allowed');
        });

        it('provides user-friendly error messages', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            $result = $validator->check('rm');

            expect($result->getUserMessage())->toBe('This command is not permitted.');
        });
    });

    describe('validate method', function () {
        it('throws exception for invalid commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            expect(fn () => $validator->validate('rm'))
                ->toThrow(ValidationException::class);
        });

        it('does not throw for valid commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            expect(fn () => $validator->validate('ls'))->not->toThrow(ValidationException::class);
        });

        it('throws for empty commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            expect(fn () => $validator->validate(''))
                ->toThrow(ValidationException::class, 'Command cannot be empty');
        });

        it('throws for commands exceeding max length', function () {
            $validator = new CommandValidator(
                allowedCommands: ['echo'],
            );
            $validator->setMaxLength(10);

            expect(fn () => $validator->validate('echo '.str_repeat('a', 100)))
                ->toThrow(ValidationException::class, 'exceeds maximum length');
        });

        it('throws for blocked characters', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
                blockedCharacters: [';'],
            );

            expect(fn () => $validator->validate('ls; rm'))
                ->toThrow(ValidationException::class, 'invalid characters');
        });
    });

    describe('configuration', function () {
        it('can add allowed commands fluently', function () {
            $validator = new CommandValidator;

            $result = $validator
                ->addAllowedCommand('ls')
                ->addAllowedCommand('pwd');

            expect($result)->toBe($validator);
            expect($validator->isAllowed('ls'))->toBeTrue();
            expect($validator->isAllowed('pwd'))->toBeTrue();
        });

        it('can remove allowed commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'pwd'],
            );

            $validator->removeAllowedCommand('ls');

            expect($validator->isAllowed('ls'))->toBeFalse();
            expect($validator->isAllowed('pwd'))->toBeTrue();
        });

        it('can clear all allowed commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'pwd', 'whoami'],
            );

            $validator->clearAllowedCommands();

            expect($validator->hasAllowedCommands())->toBeFalse();
            expect($validator->isAllowed('ls'))->toBeFalse();
        });

        it('can get all allowed commands', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'pwd', 'git *'],
            );

            $commands = $validator->getAllowedCommands();

            expect($commands)->toContain('ls');
            expect($commands)->toContain('pwd');
            expect($commands)->toContain('git *');
        });

        it('can set and get blocked characters', function () {
            $validator = new CommandValidator;

            $validator->setBlockedCharacters([';', '|']);
            $validator->addBlockedCharacter('&');

            expect($validator->getBlockedCharacters())->toBe([';', '|', '&']);
        });

        it('can set and get max length', function () {
            $validator = new CommandValidator;

            $validator->setMaxLength(500);

            expect($validator->getMaxLength())->toBe(500);
        });

        it('enforces minimum max length of 1', function () {
            $validator = new CommandValidator;

            $validator->setMaxLength(0);

            expect($validator->getMaxLength())->toBe(1);
        });
    });

    describe('edge cases', function () {
        it('handles whitespace-only commands as empty', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            expect($validator->isAllowed('   '))->toBeFalse();
            expect($validator->check('   ')->getErrorCode())->toBe('empty_command');
        });

        it('handles commands with excessive whitespace', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            expect($validator->isAllowed('  ls   -la  '))->toBeTrue();
        });

        it('ignores empty strings when adding commands', function () {
            $validator = new CommandValidator;

            $validator->addAllowedCommand('');
            $validator->addAllowedCommand('  ');

            expect($validator->hasAllowedCommands())->toBeFalse();
        });

        it('does not add duplicate blocked characters', function () {
            $validator = new CommandValidator(
                blockedCharacters: [';'],
            );

            $validator->addBlockedCharacter(';');
            $validator->addBlockedCharacter(';');

            expect(count($validator->getBlockedCharacters()))->toBe(1);
        });
    });

    describe('allow all mode', function () {
        it('allows any command when allowAll is true', function () {
            $validator = new CommandValidator(
                allowedCommands: [],
                blockedCharacters: [],
                allowAll: true,
            );

            expect($validator->isAllowed('ls'))->toBeTrue();
            expect($validator->isAllowed('node --version'))->toBeTrue();
            expect($validator->isAllowed('composer install'))->toBeTrue();
            expect($validator->isAllowed('htop'))->toBeTrue();
            expect($validator->isAllowed('php artisan migrate'))->toBeTrue();
        });

        it('allows commands not in whitelist when allowAll is true', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls', 'pwd'],
                blockedCharacters: [],
                allowAll: true,
            );

            // These would normally be rejected
            expect($validator->isAllowed('node'))->toBeTrue();
            expect($validator->isAllowed('npm install'))->toBeTrue();
            expect($validator->isAllowed('composer update'))->toBeTrue();
        });

        it('can be enabled via setAllowAll method', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
            );

            // Initially rejected
            expect($validator->isAllowed('node'))->toBeFalse();

            // Enable allow all
            $validator->setAllowAll(true);

            // Now allowed
            expect($validator->isAllowed('node'))->toBeTrue();
            expect($validator->isAllowAll())->toBeTrue();
        });

        it('can be disabled via setAllowAll method', function () {
            $validator = new CommandValidator(
                allowedCommands: ['ls'],
                blockedCharacters: [],
                allowAll: true,
            );

            // Initially allowed
            expect($validator->isAllowed('node'))->toBeTrue();

            // Disable allow all
            $validator->setAllowAll(false);

            // Now rejected
            expect($validator->isAllowed('node'))->toBeFalse();
            expect($validator->isAllowAll())->toBeFalse();
        });

        it('still validates empty commands when allowAll is true', function () {
            $validator = new CommandValidator(
                allowedCommands: [],
                blockedCharacters: [],
                allowAll: true,
            );

            expect($validator->isAllowed(''))->toBeFalse();
            expect($validator->check('')->getErrorCode())->toBe('empty_command');
        });

        it('still validates command length when allowAll is true', function () {
            $validator = new CommandValidator(
                allowedCommands: [],
                blockedCharacters: [],
                allowAll: true,
            );
            $validator->setMaxLength(10);

            $longCommand = str_repeat('a', 100);
            expect($validator->isAllowed($longCommand))->toBeFalse();
        });

        it('still checks blocked characters when allowAll is true', function () {
            $validator = new CommandValidator(
                allowedCommands: [],
                blockedCharacters: [';', '|', '&'],
                allowAll: true,
            );

            // Safe commands are allowed
            expect($validator->isAllowed('node --version'))->toBeTrue();

            // Commands with blocked characters are still rejected
            expect($validator->isAllowed('ls; rm'))->toBeFalse();
            expect($validator->isAllowed('cat /etc/passwd | grep root'))->toBeFalse();
        });

        it('returns correct isAllowAll state', function () {
            $validatorWithAllowAll = new CommandValidator(
                allowedCommands: [],
                blockedCharacters: [],
                allowAll: true,
            );

            $validatorWithoutAllowAll = new CommandValidator(
                allowedCommands: ['ls'],
            );

            expect($validatorWithAllowAll->isAllowAll())->toBeTrue();
            expect($validatorWithoutAllowAll->isAllowAll())->toBeFalse();
        });

        it('setAllowAll returns fluent interface', function () {
            $validator = new CommandValidator;

            $result = $validator->setAllowAll(true);

            expect($result)->toBe($validator);
        });
    });
});

describe('ValidationException', function () {
    it('extracts binary name for not allowed error', function () {
        $exception = ValidationException::notAllowed('rm -rf /');

        expect($exception->getMessage())->toContain("'rm'");
        expect($exception->getMessage())->not->toContain('-rf');
    });

    it('hides details for blocked characters', function () {
        $exception = ValidationException::blockedCharacters('ls; rm', ';');

        // Should not reveal which character was blocked
        expect($exception->getMessage())->toBe('Command contains invalid characters.');
    });

    it('provides error codes for categorization', function () {
        expect(ValidationException::notAllowed('rm')->errorCode)->toBe('command_not_allowed');
        expect(ValidationException::emptyCommand()->errorCode)->toBe('empty_command');
        expect(ValidationException::tooLong('x', 1)->errorCode)->toBe('command_too_long');
        expect(ValidationException::blockedCharacters('x', ';')->errorCode)->toBe('blocked_characters');
        expect(ValidationException::injectionAttempt('x')->errorCode)->toBe('injection_attempt');
    });

    it('provides user-friendly messages', function () {
        $exception = ValidationException::notAllowed('rm');

        expect($exception->getUserMessage())->toBe('This command is not permitted.');
        expect($exception->isNotAllowed())->toBeTrue();
    });
});
