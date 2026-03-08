<?php

declare(strict_types=1);

use MWGuerra\WebTerminal\Terminal\AnsiToHtml;

describe('AnsiToHtml', function () {
    beforeEach(function () {
        $this->converter = new AnsiToHtml;
    });

    describe('convert', function () {
        it('converts plain text without ANSI codes', function () {
            $result = $this->converter->convert('Hello World');

            expect($result)->toBe('Hello World');
        });

        it('escapes HTML entities in plain text', function () {
            $result = $this->converter->convert('<script>alert("xss")</script>');

            expect($result)->toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
        });

        it('converts standard foreground colors', function () {
            $result = $this->converter->convert("\x1b[31mred text\x1b[0m");

            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('red text');
        });

        it('converts all standard foreground color codes', function () {
            $colors = [
                30 => 'black',
                31 => 'red',
                32 => 'green',
                33 => 'yellow',
                34 => 'blue',
                35 => 'magenta',
                36 => 'cyan',
                37 => 'white',
            ];

            foreach ($colors as $code => $name) {
                $result = $this->converter->convert("\x1b[{$code}mtest\x1b[0m");
                expect($result)->toContain("ansi-fg-{$name}");
            }
        });

        it('converts bright foreground colors', function () {
            $result = $this->converter->convert("\x1b[91mbright red\x1b[0m");

            expect($result)->toContain('ansi-fg-bright-red');
            expect($result)->toContain('bright red');
        });

        it('converts all bright foreground color codes', function () {
            $colors = [
                90 => 'bright-black',
                91 => 'bright-red',
                92 => 'bright-green',
                93 => 'bright-yellow',
                94 => 'bright-blue',
                95 => 'bright-magenta',
                96 => 'bright-cyan',
                97 => 'bright-white',
            ];

            foreach ($colors as $code => $name) {
                $result = $this->converter->convert("\x1b[{$code}mtest\x1b[0m");
                expect($result)->toContain("ansi-fg-{$name}");
            }
        });

        it('converts standard background colors', function () {
            $result = $this->converter->convert("\x1b[44mblue background\x1b[0m");

            expect($result)->toContain('ansi-bg-blue');
            expect($result)->toContain('blue background');
        });

        it('converts all standard background color codes', function () {
            $colors = [
                40 => 'black',
                41 => 'red',
                42 => 'green',
                43 => 'yellow',
                44 => 'blue',
                45 => 'magenta',
                46 => 'cyan',
                47 => 'white',
            ];

            foreach ($colors as $code => $name) {
                $result = $this->converter->convert("\x1b[{$code}mtest\x1b[0m");
                expect($result)->toContain("ansi-bg-{$name}");
            }
        });

        it('converts bright background colors', function () {
            $colors = [
                100 => 'bright-black',
                101 => 'bright-red',
                102 => 'bright-green',
                103 => 'bright-yellow',
                104 => 'bright-blue',
                105 => 'bright-magenta',
                106 => 'bright-cyan',
                107 => 'bright-white',
            ];

            foreach ($colors as $code => $name) {
                $result = $this->converter->convert("\x1b[{$code}mtest\x1b[0m");
                expect($result)->toContain("ansi-bg-{$name}");
            }
        });

        it('converts bold text', function () {
            $result = $this->converter->convert("\x1b[1mbold text\x1b[0m");

            expect($result)->toContain('ansi-bold');
            expect($result)->toContain('bold text');
        });

        it('converts dim text', function () {
            $result = $this->converter->convert("\x1b[2mdim text\x1b[0m");

            expect($result)->toContain('ansi-dim');
        });

        it('converts italic text', function () {
            $result = $this->converter->convert("\x1b[3mitalic text\x1b[0m");

            expect($result)->toContain('ansi-italic');
        });

        it('converts underlined text', function () {
            $result = $this->converter->convert("\x1b[4munderlined\x1b[0m");

            expect($result)->toContain('ansi-underline');
        });

        it('converts strikethrough text', function () {
            $result = $this->converter->convert("\x1b[9mstrikethrough\x1b[0m");

            expect($result)->toContain('ansi-strikethrough');
        });

        it('handles combined codes', function () {
            // Bold red text
            $result = $this->converter->convert("\x1b[1;31mbold red\x1b[0m");

            expect($result)->toContain('ansi-bold');
            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('bold red');
        });

        it('handles multiple style segments', function () {
            $result = $this->converter->convert("Normal \x1b[31mred\x1b[0m normal \x1b[32mgreen\x1b[0m");

            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('red');
            expect($result)->toContain('ansi-fg-green');
            expect($result)->toContain('green');
            expect($result)->toContain('Normal');
            expect($result)->toContain('normal');
        });

        it('handles reset code', function () {
            $result = $this->converter->convert("\x1b[31mred\x1b[0m normal");

            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('red');
            expect($result)->toContain('normal');
        });

        it('handles empty parameter as reset', function () {
            $result = $this->converter->convert("\x1b[31mred\x1b[m normal");

            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('normal');
        });

        it('handles default foreground color code', function () {
            $result = $this->converter->convert("\x1b[31mred\x1b[39mdefault");

            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('red');
            expect($result)->toContain('default');
        });

        it('handles default background color code', function () {
            $result = $this->converter->convert("\x1b[44mblue bg\x1b[49mdefault bg");

            expect($result)->toContain('ansi-bg-blue');
            expect($result)->toContain('blue bg');
            expect($result)->toContain('default bg');
        });

        it('handles octal escape sequences', function () {
            $result = $this->converter->convert("\033[32moctal green\033[0m");

            expect($result)->toContain('ansi-fg-green');
            expect($result)->toContain('octal green');
        });

        it('preserves text content while stripping codes', function () {
            $input = "\x1b[1;32;44mStyled Text\x1b[0m";
            $result = $this->converter->convert($input);

            expect($result)->toContain('Styled Text');
        });

        it('escapes HTML in styled text', function () {
            $result = $this->converter->convert("\x1b[31m<b>html</b>\x1b[0m");

            expect($result)->toContain('&lt;b&gt;html&lt;/b&gt;');
            expect($result)->not->toContain('<b>');
        });

        it('handles text with foreground and background', function () {
            $result = $this->converter->convert("\x1b[31;44mred on blue\x1b[0m");

            expect($result)->toContain('ansi-fg-red');
            expect($result)->toContain('ansi-bg-blue');
            expect($result)->toContain('red on blue');
        });

        it('handles attribute reset codes', function () {
            // Bold off (21 or 22)
            $result = $this->converter->convert("\x1b[1mbold\x1b[22mnormal");
            expect($result)->toContain('ansi-bold');

            // Italic off (23)
            $result = $this->converter->convert("\x1b[3mitalic\x1b[23mnormal");
            expect($result)->toContain('ansi-italic');

            // Underline off (24)
            $result = $this->converter->convert("\x1b[4munderlined\x1b[24mnormal");
            expect($result)->toContain('ansi-underline');
        });
    });

    describe('strip', function () {
        it('strips all ANSI codes from text', function () {
            $input = "\x1b[1;31mBold Red\x1b[0m Normal \x1b[32mGreen\x1b[0m";
            $result = AnsiToHtml::strip($input);

            expect($result)->toBe('Bold Red Normal Green');
        });

        it('strips codes with octal escape sequences', function () {
            $input = "\033[32mGreen\033[0m";
            $result = AnsiToHtml::strip($input);

            expect($result)->toBe('Green');
        });

        it('handles text without ANSI codes', function () {
            $input = 'Plain text';
            $result = AnsiToHtml::strip($input);

            expect($result)->toBe('Plain text');
        });
    });

    describe('classPrefix', function () {
        it('uses default ansi- prefix', function () {
            expect($this->converter->getClassPrefix())->toBe('ansi-');
        });

        it('allows custom prefix', function () {
            $this->converter->setClassPrefix('term-');
            $result = $this->converter->convert("\x1b[31mred\x1b[0m");

            expect($result)->toContain('term-fg-red');
            expect($result)->not->toContain('ansi-fg-red');
        });

        it('returns self for fluent interface', function () {
            $result = $this->converter->setClassPrefix('custom-');

            expect($result)->toBe($this->converter);
        });
    });

    describe('256 color support', function () {
        it('converts 256-color foreground (standard colors 0-7)', function () {
            // 38;5;1 = color 1 (red)
            $result = $this->converter->convert("\x1b[38;5;1mred\x1b[0m");

            expect($result)->toContain('ansi-fg-red');
        });

        it('converts 256-color foreground (bright colors 8-15)', function () {
            // 38;5;9 = bright red
            $result = $this->converter->convert("\x1b[38;5;9mbright red\x1b[0m");

            expect($result)->toContain('ansi-fg-bright-red');
        });

        it('converts 256-color background', function () {
            // 48;5;4 = color 4 (blue)
            $result = $this->converter->convert("\x1b[48;5;4mblue bg\x1b[0m");

            expect($result)->toContain('ansi-bg-blue');
        });
    });

    describe('real world examples', function () {
        it('handles Laravel ASCII art style output', function () {
            $input = "\x1b[32m  _                          _ \x1b[0m
\x1b[32m | |    __ _ _ __ __ ___   _____| |\x1b[0m";
            $result = $this->converter->convert($input);

            expect($result)->toContain('ansi-fg-green');
            expect($result)->toContain('_                          _');
        });

        it('handles Composer output style', function () {
            $input = "Loading composer repositories with package information
\x1b[32mInstalling dependencies\x1b[39m (including require-dev)";
            $result = $this->converter->convert($input);

            expect($result)->toContain('ansi-fg-green');
            expect($result)->toContain('Installing dependencies');
            expect($result)->toContain('Loading composer');
        });

        it('handles npm/yarn style progress', function () {
            $input = "\x1b[32m✓\x1b[0m Package installed
\x1b[31m✗\x1b[0m Package failed";
            $result = $this->converter->convert($input);

            expect($result)->toContain('ansi-fg-green');
            expect($result)->toContain('ansi-fg-red');
        });
    });
});
