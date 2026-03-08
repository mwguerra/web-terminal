<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Terminal\TuiDetector;

describe('TuiDetector', function () {
    describe('containsTuiSequences', function () {
        it('detects standard alternate screen buffer', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[?1049h"))->toBeTrue();
        });

        it('detects older variant ?47h', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[?47h"))->toBeTrue();
        });

        it('detects older variant ?1047h', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[?1047h"))->toBeTrue();
        });

        it('detects leave alternate screen', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[?1049l"))->toBeTrue();
        });

        it('detects octal escape variant', function () {
            expect(TuiDetector::containsTuiSequences("\033[?1049h"))->toBeTrue();
        });

        it('does not false-positive on SGR color sequences', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[31m"))->toBeFalse();
            expect(TuiDetector::containsTuiSequences("\x1b[0m"))->toBeFalse();
            expect(TuiDetector::containsTuiSequences("\x1b[1;32m"))->toBeFalse();
        });

        it('does not false-positive on cursor movement', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[H"))->toBeFalse();
            expect(TuiDetector::containsTuiSequences("\x1b[A"))->toBeFalse();
            expect(TuiDetector::containsTuiSequences("\x1b[5;10H"))->toBeFalse();
        });

        it('does not false-positive on cursor visibility', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[?25h"))->toBeFalse();
            expect(TuiDetector::containsTuiSequences("\x1b[?25l"))->toBeFalse();
        });

        it('does not false-positive on line erase', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[K"))->toBeFalse();
        });

        it('returns false for plain text', function () {
            expect(TuiDetector::containsTuiSequences('Hello World'))->toBeFalse();
        });

        it('returns false for empty string', function () {
            expect(TuiDetector::containsTuiSequences(''))->toBeFalse();
        });

        it('detects TUI mixed with other ANSI codes', function () {
            $output = "\x1b[31mred\x1b[0m some text \x1b[?1049h more stuff";
            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('detects Device Status Report (cursor position query)', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[6n"))->toBeTrue();
        });

        it('detects Device Status Report with octal escape', function () {
            expect(TuiDetector::containsTuiSequences("\033[6n"))->toBeTrue();
        });

        it('detects Device Status Report mixed with other output', function () {
            $output = "some text \x1b[6n more output";
            expect(TuiDetector::containsTuiSequences($output))->toBeTrue();
        });

        it('does not false-positive on other CSI n sequences', function () {
            expect(TuiDetector::containsTuiSequences("\x1b[5n"))->toBeFalse();
            expect(TuiDetector::containsTuiSequences("\x1b[0n"))->toBeFalse();
        });
    });

    describe('getSuggestion', function () {
        it('suggests batch mode for top', function () {
            expect(TuiDetector::getSuggestion('top'))->toBe('Try instead: top -b -n 1');
        });

        it('suggests batch mode for top with args', function () {
            expect(TuiDetector::getSuggestion('top -u root'))->toBe('Try instead: top -b -n 1');
        });

        it('suggests batch mode for htop', function () {
            expect(TuiDetector::getSuggestion('htop'))->toBe('Try instead: top -b -n 1');
        });

        it('suggests cat for vim with file', function () {
            expect(TuiDetector::getSuggestion('vim /etc/hosts'))->toBe('Try instead: cat /etc/hosts');
        });

        it('suggests cat for vi with file', function () {
            expect(TuiDetector::getSuggestion('vi /etc/hosts'))->toBe('Try instead: cat /etc/hosts');
        });

        it('suggests cat for nano with file', function () {
            expect(TuiDetector::getSuggestion('nano /etc/hosts'))->toBe('Try instead: cat /etc/hosts');
        });

        it('suggests cat for less with file', function () {
            expect(TuiDetector::getSuggestion('less /var/log/syslog'))->toBe('Try instead: cat /var/log/syslog');
        });

        it('suggests cat for more with file', function () {
            expect(TuiDetector::getSuggestion('more README.md'))->toBe('Try instead: cat README.md');
        });

        it('returns non-null suggestion for man', function () {
            expect(TuiDetector::getSuggestion('man ls'))->not->toBeNull();
        });

        it('returns null for unknown commands', function () {
            expect(TuiDetector::getSuggestion('ls -la'))->toBeNull();
            expect(TuiDetector::getSuggestion('echo hello'))->toBeNull();
        });

        it('handles commands with paths', function () {
            expect(TuiDetector::getSuggestion('/usr/bin/vim /etc/hosts'))->toBe('Try instead: cat /etc/hosts');
        });

        it('handles sudo prefix', function () {
            expect(TuiDetector::getSuggestion('sudo vim /etc/hosts'))->toBe('Try instead: cat /etc/hosts');
        });

        it('suggests cat with placeholder for bare vim', function () {
            expect(TuiDetector::getSuggestion('vim'))->toBe('Try instead: cat <file>');
        });
    });

    describe('getErrorMessage', function () {
        it('includes TUI not supported message', function () {
            $message = TuiDetector::getErrorMessage('vim /etc/hosts');
            expect($message)->toContain('full-screen terminal interface (TUI)');
            expect($message)->toContain('not supported in the web terminal');
        });

        it('includes suggestion for known commands', function () {
            $message = TuiDetector::getErrorMessage('less /var/log/syslog');
            expect($message)->toContain('Try instead: cat /var/log/syslog');
        });

        it('has no suggestion for unknown commands', function () {
            $message = TuiDetector::getErrorMessage('some-tui-app');
            expect($message)->not->toContain('Try instead');
        });
    });
});
