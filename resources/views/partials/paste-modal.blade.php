{{-- Multi-line Paste Confirmation Modal --}}
<div
    x-show="showPasteModal"
    x-cloak
    class="absolute inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm rounded-xl"
    @keydown.escape.window="if (showPasteModal && !pasteExecuting) cancelPaste()"
>
    <div
        class="w-full max-w-lg mx-4 bg-white dark:bg-[#1e1e2e] rounded-lg shadow-2xl ring-1 ring-slate-200 dark:ring-white/10 overflow-hidden"
        @click.away="if (!pasteExecuting) cancelPaste()"
    >
        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-slate-100 dark:bg-black/30 border-b border-slate-200 dark:border-white/10">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-amber-500">
                    <path d="M13.28 1.22a.75.75 0 0 1 0 1.06L6.56 9H17.75a.75.75 0 0 1 0 1.5H6.56l6.72 6.72a.75.75 0 1 1-1.06 1.06l-8-8a.75.75 0 0 1 0-1.06l8-8a.75.75 0 0 1 1.06 0Z"/>
                </svg>
                <span class="text-sm font-medium text-slate-700 dark:text-zinc-200">Paste Multiple Commands</span>
            </div>
            <span class="text-xs text-slate-500 dark:text-zinc-400" x-text="pasteCommands.length + ' command' + (pasteCommands.length !== 1 ? 's' : '')"></span>
        </div>

        {{-- Command List --}}
        <div class="max-h-60 overflow-y-auto p-2">
            <template x-for="(cmd, index) in pasteCommands" :key="index">
                <div
                    class="flex items-start gap-2 px-3 py-1.5 rounded text-sm font-mono transition-colors"
                    :class="{
                        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': pasteExecuting && index < pasteCurrentIndex,
                        'bg-blue-500/10 text-blue-600 dark:text-blue-400 ring-1 ring-blue-500/30': pasteExecuting && index === pasteCurrentIndex,
                        'text-slate-600 dark:text-zinc-300': !pasteExecuting || index > pasteCurrentIndex
                    }"
                >
                    <span class="shrink-0 w-5 text-right text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5 select-none" x-text="index + 1"></span>
                    <span class="text-emerald-600 dark:text-emerald-400 select-none">$</span>
                    <span class="flex-1 break-all" x-text="cmd"></span>
                    <svg x-show="pasteExecuting && index < pasteCurrentIndex" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4 shrink-0 text-emerald-500 mt-0.5">
                        <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="pasteExecuting && index === pasteCurrentIndex" class="w-4 h-4 shrink-0 text-blue-500 animate-spin mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </template>
        </div>

        {{-- Modal Footer --}}
        <div class="flex items-center justify-end gap-2 px-4 py-3 bg-slate-50 dark:bg-black/20 border-t border-slate-200 dark:border-white/10">
            <button
                type="button"
                @click="cancelPaste()"
                :disabled="pasteExecuting"
                class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-zinc-400 bg-slate-200 dark:bg-white/10 rounded hover:bg-slate-300 dark:hover:bg-white/15 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >Cancel</button>
            <button
                type="button"
                @click="executePastedCommands()"
                :disabled="pasteExecuting"
                class="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span x-show="!pasteExecuting">Execute All</span>
                <span x-show="pasteExecuting" class="flex items-center gap-1">
                    <svg class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Running...
                </span>
            </button>
        </div>
    </div>
</div>
