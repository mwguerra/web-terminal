{{-- Terminal Input Area --}}
<div
    class="flex items-center px-3 py-2.5 bg-slate-100/80 dark:bg-black/20 border-t transition-colors duration-200"
    :class="{
        'opacity-50': !isConnected,
        'border-amber-500 dark:border-amber-400 bg-amber-50 dark:bg-amber-900/20': $wire.scriptAwaitingInput,
        'border-slate-200 dark:border-white/5': !$wire.scriptAwaitingInput
    }"
>
    <span class="flex items-center shrink-0 select-none mr-1" aria-hidden="true">
        <span class="text-blue-600 dark:text-blue-400 font-medium">{{ $this->getShortDirectoryName() }}</span>
        <span class="text-emerald-600 dark:text-emerald-400 font-semibold ml-0.5 mr-0.5" :class="{ 'text-amber-600 dark:text-amber-400': $wire.scriptAwaitingInput }">{{ $prompt }}</span>
    </span>
    <input
        type="text"
        class="terminal-input flex-1 bg-transparent border-none outline-none text-slate-800 dark:text-zinc-200 font-mono text-[13px] p-0 m-0 caret-emerald-500 dark:caret-emerald-400 placeholder:text-slate-400 dark:placeholder:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
        x-ref="input"
        wire:model="command"
        wire:keydown.enter="executeCommand"
        @keydown="handleKeydown($event)"
        @input="scrollToBottom()"
        @paste="handlePaste($event)"
        :placeholder="!isConnected ? 'Click Connect to start...' : ($wire.scriptAwaitingInput ? 'Enter input for running command... (Ctrl+C to cancel script)' : (isInteractive ? 'Type input and press Enter (Ctrl+C to cancel)...' : (isScriptRunning() ? 'Script running...' : 'Type a command...')))"
        autocomplete="off"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        aria-label="Command input"
        :disabled="!isConnected || ($wire.isExecuting && !isInteractive && !$wire.scriptAwaitingInput) || (isScriptRunning() && !$wire.scriptAwaitingInput)"
    />
    {{-- Loading spinner (non-interactive) --}}
    <div wire:loading wire:target="executeCommand" x-show="!isInteractive" class="ml-3 text-blue-600 dark:text-blue-400">
        <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    {{-- Script running indicator and controls - Only rendered when script is actually running --}}
    @if($this->isScriptRunning())
    <div class="flex items-center gap-2 ml-3">
        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-medium text-purple-600 dark:text-purple-400 bg-purple-400/10 border border-purple-500/30 dark:border-purple-400/20 rounded-full leading-none">
            <svg class="w-3 h-3 shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Script</span>
        </span>
        <button
            type="button"
            wire:click="showScriptPanel = true"
            class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-medium text-purple-600 dark:text-purple-400 bg-purple-400/10 border border-purple-500/30 dark:border-purple-400/20 rounded-full hover:bg-purple-400/20 transition-colors leading-none"
            title="View script progress"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 shrink-0">
                <path fill-rule="evenodd" d="M2 10a.75.75 0 0 1 .75-.75h12.59l-2.1-1.95a.75.75 0 1 1 1.02-1.1l3.5 3.25a.75.75 0 0 1 0 1.1l-3.5 3.25a.75.75 0 1 1-1.02-1.1l2.1-1.95H2.75A.75.75 0 0 1 2 10Z" clip-rule="evenodd" />
            </svg>
            <span>Progress</span>
        </button>
        <button
            type="button"
            wire:click="cancelScript"
            class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-medium text-red-600 dark:text-red-400 bg-red-400/10 border border-red-500/30 dark:border-red-400/20 rounded-full hover:bg-red-400/20 transition-colors leading-none"
            title="Emergency stop (Ctrl+C)"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 shrink-0">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            <span>Stop</span>
        </button>
    </div>
    @endif

    {{-- Interactive mode indicator and cancel button (when not running script) --}}
    @if($isInteractive && !$this->isScriptRunning())
    <div class="flex items-center gap-2 ml-3">
        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-medium text-amber-600 dark:text-amber-400 bg-amber-400/10 border border-amber-500/30 dark:border-amber-400/20 rounded-full leading-none">
            <svg class="w-3 h-3 shrink-0 animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.3" />
                <path stroke-linecap="round" d="M12 6v6l4 2" />
            </svg>
            <span>Running</span>
        </span>
        <button
            type="button"
            wire:click="cancelProcess"
            class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-medium text-red-600 dark:text-red-400 bg-red-400/10 border border-red-500/30 dark:border-red-400/20 rounded-full hover:bg-red-400/20 transition-colors leading-none"
            title="Cancel (Ctrl+C)"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 shrink-0">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            <span>Cancel</span>
        </button>
    </div>
    @endif

    {{-- Connection type badge - always visible --}}
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 ml-3 text-[11px] font-medium text-lime-600 dark:text-lime-400 bg-lime-400/10 border border-lime-500/30 dark:border-lime-400/20 rounded-full uppercase tracking-wide leading-none">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 shrink-0 opacity-80">
            <path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v8.5A2.25 2.25 0 0 1 15.75 15h-3.105a3.501 3.501 0 0 0 1.1 1.677A.75.75 0 0 1 13.26 18H6.74a.75.75 0 0 1-.484-1.323A3.501 3.501 0 0 0 7.355 15H4.25A2.25 2.25 0 0 1 2 12.75v-8.5Zm1.5 0a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-7.5Z" clip-rule="evenodd" />
        </svg>
        <span>{{ $this->getConnectionType() }}</span>
    </span>
</div>
