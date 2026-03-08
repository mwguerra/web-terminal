{{-- Terminal Output Area --}}
<div
    class="terminal-output flex-1 overflow-y-auto p-3 scroll-smooth text-left"
    x-ref="output"
    role="log"
    aria-live="polite"
    aria-label="Terminal output"
>
    @php
        // Group output lines into command blocks
        $blocks = [];
        $currentBlock = [];

        foreach ($output as $line) {
            if (trim($line['content'] ?? '') === '') {
                continue;
            }

            if (($line['type'] ?? '') === 'command' && !empty($currentBlock)) {
                $blocks[] = $currentBlock;
                $currentBlock = [];
            }

            $currentBlock[] = $line;
        }

        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }
    @endphp

    @foreach($blocks as $blockIndex => $block)
        <div
            class="terminal-block group relative"
            x-data="{ blockCopied: false }"
        >
            {{-- Per-block copy button (visible on hover) --}}
            <button
                type="button"
                class="absolute top-1 right-1 z-10 flex items-center justify-center w-6 h-6 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                :class="blockCopied
                    ? 'bg-emerald-500/20 text-emerald-500'
                    : 'bg-slate-500/20 text-slate-400 hover:bg-slate-500/30 hover:text-slate-300'"
                :title="blockCopied ? 'Copied!' : 'Copy block'"
                @click="
                    const lines = $el.closest('.terminal-block').querySelectorAll('[data-line-content]');
                    const text = Array.from(lines).map(el => el.getAttribute('data-line-content')).join('\n');
                    copyToClipboard(text).then(success => {
                        if (success) {
                            blockCopied = true;
                            setTimeout(() => { blockCopied = false; }, 1500);
                        }
                    });
                "
            >
                <svg x-show="!blockCopied" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                    <path d="M5.5 3.5A1.5 1.5 0 0 1 7 2h2.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 1 .439 1.061V9.5A1.5 1.5 0 0 1 12 11V3.5H5.5Z"/>
                    <path d="M3.5 5A1.5 1.5 0 0 0 2 6.5v7A1.5 1.5 0 0 0 3.5 15h5A1.5 1.5 0 0 0 10 13.5v-7A1.5 1.5 0 0 0 8.5 5h-5Z"/>
                </svg>
                <svg x-show="blockCopied" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-3.5 h-3.5">
                    <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Block lines --}}
            @foreach($block as $line)
                <div
                    class="whitespace-pre-wrap break-words m-0 p-0 leading-snug text-left block w-full
                        @if(($line['type'] ?? '') === 'stdout') text-slate-700 dark:text-zinc-200
                        @elseif(($line['type'] ?? '') === 'stderr') text-red-600 dark:text-red-300
                        @elseif(($line['type'] ?? '') === 'error') text-red-700 dark:text-red-500 font-semibold
                        @elseif(($line['type'] ?? '') === 'info') text-blue-600 dark:text-blue-400
                        @elseif(($line['type'] ?? '') === 'command') text-emerald-600 dark:text-emerald-400 font-medium pt-1 pb-0.5
                        @elseif(($line['type'] ?? '') === 'system') text-slate-500 dark:text-gray-500 italic
                        @else text-slate-700 dark:text-zinc-200
                        @endif
                    "
                    data-line-content="{{ \MWGuerra\WebTerminal\Terminal\AnsiToHtml::strip($line['content']) }}"
                >{!! $this->convertAnsiToHtml($line['content']) !!}</div>
            @endforeach
        </div>
    @endforeach
</div>
