<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Terminal;

/**
 * Converts ANSI escape sequences to HTML with CSS classes.
 *
 * Handles standard ANSI color codes, text attributes (bold, dim, italic, underline),
 * and 256-color/truecolor sequences commonly used in terminal output.
 */
class AnsiToHtml
{
    /**
     * Standard foreground color names (30-37).
     */
    protected const FG_COLORS = [
        30 => 'black',
        31 => 'red',
        32 => 'green',
        33 => 'yellow',
        34 => 'blue',
        35 => 'magenta',
        36 => 'cyan',
        37 => 'white',
    ];

    /**
     * Bright foreground color names (90-97).
     */
    protected const FG_BRIGHT_COLORS = [
        90 => 'bright-black',
        91 => 'bright-red',
        92 => 'bright-green',
        93 => 'bright-yellow',
        94 => 'bright-blue',
        95 => 'bright-magenta',
        96 => 'bright-cyan',
        97 => 'bright-white',
    ];

    /**
     * Standard background color names (40-47).
     */
    protected const BG_COLORS = [
        40 => 'black',
        41 => 'red',
        42 => 'green',
        43 => 'yellow',
        44 => 'blue',
        45 => 'magenta',
        46 => 'cyan',
        47 => 'white',
    ];

    /**
     * Bright background color names (100-107).
     */
    protected const BG_BRIGHT_COLORS = [
        100 => 'bright-black',
        101 => 'bright-red',
        102 => 'bright-green',
        103 => 'bright-yellow',
        104 => 'bright-blue',
        105 => 'bright-magenta',
        106 => 'bright-cyan',
        107 => 'bright-white',
    ];

    /**
     * Current text state.
     *
     * @var array{
     *     fg: string|null,
     *     bg: string|null,
     *     bold: bool,
     *     dim: bool,
     *     italic: bool,
     *     underline: bool,
     *     blink: bool,
     *     reverse: bool,
     *     hidden: bool,
     *     strikethrough: bool
     * }
     */
    protected array $state = [
        'fg' => null,
        'bg' => null,
        'bold' => false,
        'dim' => false,
        'italic' => false,
        'underline' => false,
        'blink' => false,
        'reverse' => false,
        'hidden' => false,
        'strikethrough' => false,
    ];

    /**
     * CSS class prefix for ANSI styles.
     */
    protected string $classPrefix = 'ansi-';

    /**
     * Convert ANSI escape sequences in text to HTML.
     *
     * @param  string  $text  Text containing ANSI escape sequences
     * @return string HTML with CSS classes for styling
     */
    public function convert(string $text): string
    {
        // Reset state for each conversion
        $this->resetState();

        // Strip non-SGR escape sequences first (private mode, cursor, OSC, etc.)
        $text = (string) preg_replace([
            '/\x1b\[\?[0-9;]*[a-zA-Z]/', // Private mode sequences (\x1b[?2004h, etc.)
            '/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', // OSC sequences
            '/\x1b[()][0-9A-B]/', // Character set selection
            '/\x1b[>=]/', // Keypad mode
            '/\r/', // Carriage returns
        ], '', $text);

        // Handle both \x1b (ESC) and \033 (octal) escape sequences
        // Pattern matches: ESC [ (params) m (SGR color/style sequences)
        $pattern = '/\x1b\[([0-9;]*)m|\033\[([0-9;]*)m/';

        $result = '';
        $lastPos = 0;
        $isSpanOpen = false;

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $fullMatch = $match[0];
                $position = $match[1];

                // Get the text before this escape sequence
                $textBefore = substr($text, $lastPos, $position - $lastPos);

                if ($textBefore !== '') {
                    // HTML escape the text content
                    $escapedText = htmlspecialchars($textBefore, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if ($isSpanOpen) {
                        $result .= $escapedText;
                    } else {
                        $classes = $this->getClasses();
                        if ($classes !== '') {
                            $result .= '<span class="'.$classes.'">'.$escapedText;
                            $isSpanOpen = true;
                        } else {
                            $result .= $escapedText;
                        }
                    }
                }

                // Parse the escape sequence parameters
                $params = $matches[1][$index][0] !== '' ? $matches[1][$index][0] : ($matches[2][$index][0] ?? '');
                $this->parseParams($params);

                // Close current span if open
                if ($isSpanOpen) {
                    $result .= '</span>';
                    $isSpanOpen = false;
                }

                $lastPos = $position + strlen($fullMatch);
            }
        }

        // Handle any remaining text after the last escape sequence
        $remainingText = substr($text, $lastPos);
        if ($remainingText !== '') {
            $escapedText = htmlspecialchars($remainingText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $classes = $this->getClasses();
            if ($classes !== '' && ! $isSpanOpen) {
                $result .= '<span class="'.$classes.'">'.$escapedText.'</span>';
            } elseif ($isSpanOpen) {
                $result .= $escapedText.'</span>';
            } else {
                $result .= $escapedText;
            }
        } elseif ($isSpanOpen) {
            $result .= '</span>';
        }

        // If no escape sequences were found, just escape and return the text
        if ($result === '') {
            return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $result;
    }

    /**
     * Parse ANSI parameter string and update state.
     *
     * @param  string  $params  Semicolon-separated parameter string (e.g., "1;32")
     */
    protected function parseParams(string $params): void
    {
        if ($params === '' || $params === '0') {
            $this->resetState();

            return;
        }

        $codes = array_map('intval', explode(';', $params));
        $i = 0;

        while ($i < count($codes)) {
            $code = $codes[$i];

            switch ($code) {
                // Reset
                case 0:
                    $this->resetState();
                    break;

                    // Text attributes
                case 1:
                    $this->state['bold'] = true;
                    break;
                case 2:
                    $this->state['dim'] = true;
                    break;
                case 3:
                    $this->state['italic'] = true;
                    break;
                case 4:
                    $this->state['underline'] = true;
                    break;
                case 5:
                case 6:
                    $this->state['blink'] = true;
                    break;
                case 7:
                    $this->state['reverse'] = true;
                    break;
                case 8:
                    $this->state['hidden'] = true;
                    break;
                case 9:
                    $this->state['strikethrough'] = true;
                    break;

                    // Reset individual attributes
                case 21:
                case 22:
                    $this->state['bold'] = false;
                    $this->state['dim'] = false;
                    break;
                case 23:
                    $this->state['italic'] = false;
                    break;
                case 24:
                    $this->state['underline'] = false;
                    break;
                case 25:
                    $this->state['blink'] = false;
                    break;
                case 27:
                    $this->state['reverse'] = false;
                    break;
                case 28:
                    $this->state['hidden'] = false;
                    break;
                case 29:
                    $this->state['strikethrough'] = false;
                    break;

                    // Standard foreground colors (30-37)
                case 30:
                case 31:
                case 32:
                case 33:
                case 34:
                case 35:
                case 36:
                case 37:
                    $this->state['fg'] = self::FG_COLORS[$code];
                    break;

                    // 256-color or truecolor foreground (38;5;n or 38;2;r;g;b)
                case 38:
                    $i = $this->parseExtendedColor($codes, $i, 'fg');
                    break;

                    // Default foreground color
                case 39:
                    $this->state['fg'] = null;
                    break;

                    // Standard background colors (40-47)
                case 40:
                case 41:
                case 42:
                case 43:
                case 44:
                case 45:
                case 46:
                case 47:
                    $this->state['bg'] = self::BG_COLORS[$code];
                    break;

                    // 256-color or truecolor background (48;5;n or 48;2;r;g;b)
                case 48:
                    $i = $this->parseExtendedColor($codes, $i, 'bg');
                    break;

                    // Default background color
                case 49:
                    $this->state['bg'] = null;
                    break;

                    // Bright foreground colors (90-97)
                case 90:
                case 91:
                case 92:
                case 93:
                case 94:
                case 95:
                case 96:
                case 97:
                    $this->state['fg'] = self::FG_BRIGHT_COLORS[$code];
                    break;

                    // Bright background colors (100-107)
                case 100:
                case 101:
                case 102:
                case 103:
                case 104:
                case 105:
                case 106:
                case 107:
                    $this->state['bg'] = self::BG_BRIGHT_COLORS[$code];
                    break;
            }

            $i++;
        }
    }

    /**
     * Parse extended color sequences (256-color or truecolor).
     *
     * @param  int[]  $codes  Array of all codes
     * @param  int  $currentIndex  Current position in codes array
     * @param  string  $type  'fg' or 'bg'
     * @return int New position in codes array
     */
    protected function parseExtendedColor(array $codes, int $currentIndex, string $type): int
    {
        $nextIndex = $currentIndex + 1;

        if (! isset($codes[$nextIndex])) {
            return $currentIndex;
        }

        if ($codes[$nextIndex] === 5) {
            // 256-color mode: 38;5;n or 48;5;n
            if (isset($codes[$nextIndex + 1])) {
                $colorIndex = $codes[$nextIndex + 1];
                $this->state[$type] = $this->get256ColorClass($colorIndex, $type);

                return $currentIndex + 2;
            }
        } elseif ($codes[$nextIndex] === 2) {
            // Truecolor mode: 38;2;r;g;b or 48;2;r;g;b
            if (isset($codes[$nextIndex + 1], $codes[$nextIndex + 2], $codes[$nextIndex + 3])) {
                $r = $codes[$nextIndex + 1];
                $g = $codes[$nextIndex + 2];
                $b = $codes[$nextIndex + 3];
                // For truecolor, we'll use inline styles
                $this->state[$type] = sprintf('rgb(%d,%d,%d)', $r, $g, $b);

                return $currentIndex + 4;
            }
        }

        return $currentIndex;
    }

    /**
     * Get CSS class for 256-color palette.
     *
     * @param  int  $index  Color index (0-255)
     * @param  string  $type  'fg' or 'bg'
     * @return string Color class or RGB value
     */
    protected function get256ColorClass(int $index, string $type): string
    {
        // Standard colors (0-7)
        if ($index >= 0 && $index <= 7) {
            $names = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'];

            return $names[$index];
        }

        // High-intensity colors (8-15)
        if ($index >= 8 && $index <= 15) {
            $names = ['bright-black', 'bright-red', 'bright-green', 'bright-yellow',
                'bright-blue', 'bright-magenta', 'bright-cyan', 'bright-white'];

            return $names[$index - 8];
        }

        // 216 color cube (16-231)
        if ($index >= 16 && $index <= 231) {
            $index -= 16;
            $r = (int) ($index / 36);
            $g = (int) (($index % 36) / 6);
            $b = $index % 6;

            // Convert to RGB (each component: 0, 95, 135, 175, 215, 255)
            $values = [0, 95, 135, 175, 215, 255];

            return sprintf('rgb(%d,%d,%d)', $values[$r], $values[$g], $values[$b]);
        }

        // Grayscale (232-255)
        if ($index >= 232 && $index <= 255) {
            $gray = 8 + ($index - 232) * 10;

            return sprintf('rgb(%d,%d,%d)', $gray, $gray, $gray);
        }

        return '';
    }

    /**
     * Get CSS classes for current state.
     *
     * @return string Space-separated CSS classes
     */
    protected function getClasses(): string
    {
        $classes = [];

        // Foreground color
        if ($this->state['fg'] !== null) {
            if (str_starts_with($this->state['fg'], 'rgb(')) {
                // For RGB colors, we'll need inline styles (handled separately)
                $classes[] = $this->classPrefix.'fg-custom';
            } else {
                $classes[] = $this->classPrefix.'fg-'.$this->state['fg'];
            }
        }

        // Background color
        if ($this->state['bg'] !== null) {
            if (str_starts_with($this->state['bg'], 'rgb(')) {
                $classes[] = $this->classPrefix.'bg-custom';
            } else {
                $classes[] = $this->classPrefix.'bg-'.$this->state['bg'];
            }
        }

        // Text attributes
        if ($this->state['bold']) {
            $classes[] = $this->classPrefix.'bold';
        }
        if ($this->state['dim']) {
            $classes[] = $this->classPrefix.'dim';
        }
        if ($this->state['italic']) {
            $classes[] = $this->classPrefix.'italic';
        }
        if ($this->state['underline']) {
            $classes[] = $this->classPrefix.'underline';
        }
        if ($this->state['blink']) {
            $classes[] = $this->classPrefix.'blink';
        }
        if ($this->state['reverse']) {
            $classes[] = $this->classPrefix.'reverse';
        }
        if ($this->state['hidden']) {
            $classes[] = $this->classPrefix.'hidden';
        }
        if ($this->state['strikethrough']) {
            $classes[] = $this->classPrefix.'strikethrough';
        }

        return implode(' ', $classes);
    }

    /**
     * Reset state to defaults.
     */
    protected function resetState(): void
    {
        $this->state = [
            'fg' => null,
            'bg' => null,
            'bold' => false,
            'dim' => false,
            'italic' => false,
            'underline' => false,
            'blink' => false,
            'reverse' => false,
            'hidden' => false,
            'strikethrough' => false,
        ];
    }

    /**
     * Set the CSS class prefix.
     *
     * @param  string  $prefix  Prefix for all generated CSS classes
     * @return $this
     */
    public function setClassPrefix(string $prefix): static
    {
        $this->classPrefix = $prefix;

        return $this;
    }

    /**
     * Get the current CSS class prefix.
     */
    public function getClassPrefix(): string
    {
        return $this->classPrefix;
    }

    /**
     * Strip all ANSI escape sequences from text.
     *
     * @param  string  $text  Text containing ANSI escape sequences
     * @return string Plain text without escape sequences
     */
    public static function strip(string $text): string
    {
        // Remove all ANSI/VT100 escape sequences:
        // - CSI sequences: \x1b[ ... (letter) — colors, cursor, erase, etc.
        // - Private mode: \x1b[? ... h/l — bracketed paste, cursor visibility, etc.
        // - OSC sequences: \x1b] ... ST — title, hyperlinks, etc.
        // - Simple escapes: \x1b followed by single char
        $text = (string) preg_replace([
            '/\x1b\[\??[0-9;]*[a-zA-Z]/', // CSI sequences (including private mode ?...)
            '/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', // OSC sequences (terminated by BEL or ST)
            '/\x1b[()][0-9A-B]/', // Character set selection
            '/\x1b[>=]/', // Keypad mode
            '/\r/', // Carriage returns
        ], '', $text);

        return $text;
    }
}
