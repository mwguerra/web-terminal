{{-- Script Execution Slideover Panel - positioned dynamically to fill the content area --}}
@if($showScriptPanel && !empty($scriptExecution))
<div
    class="absolute right-0 w-80 max-w-[70%] bg-white dark:bg-gray-900 shadow-2xl border-l border-slate-200 dark:border-white/10 z-40 flex flex-col rounded-bl-lg overflow-hidden"
    x-data="{
        scriptState: $wire.entangle('scriptExecution').live,
        panelTop: 0,
        panelBottom: 0,
        get isRunning() {
            return this.scriptState && this.scriptState.isRunning === true;
        },
        updatePosition() {
            const terminal = this.$el.closest('.secure-web-terminal');
            if (!terminal) return;
            const header = terminal.querySelector(':scope > div.border-b');
            const input = terminal.querySelector(':scope > div.border-t');
            if (header) this.panelTop = header.offsetHeight;
            if (input) this.panelBottom = input.offsetHeight;
        }
    }"
    x-init="updatePosition(); window.addEventListener('resize', () => updatePosition())"
    :style="{ top: panelTop + 'px', bottom: panelBottom + 'px' }"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="translate-x-full"
>
    {{-- Panel Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-gray-800/50">
        <div class="flex items-center gap-2 min-w-0">
            @if($scriptExecution['isRunning'] ?? false)
            <svg class="w-5 h-5 text-purple-500 dark:text-purple-400 animate-spin shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            @elseif($scriptExecution['isCancelled'] ?? false)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-amber-500 shrink-0">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            @elseif(($scriptExecution['failedCount'] ?? 0) > 0)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-red-500 shrink-0">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-emerald-500 shrink-0">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            @endif
            <div class="min-w-0">
                <h3 class="text-sm font-medium text-slate-900 dark:text-gray-100 truncate">{{ $scriptExecution['scriptLabel'] ?? 'Script' }}</h3>
                <p class="text-xs text-slate-500 dark:text-gray-400">
                    {{ $scriptExecution['completedCount'] ?? 0 }} / {{ $scriptExecution['totalCommands'] ?? 0 }} commands
                </p>
            </div>
        </div>
        <button
            type="button"
            wire:click="closeScriptPanel"
            class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-200 dark:hover:bg-white/10 transition-colors"
            :class="{ 'opacity-50 cursor-not-allowed': isRunning }"
            :disabled="isRunning"
            :title="isRunning ? 'Cannot close while script is running' : 'Close panel'"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-slate-500 dark:text-gray-400">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
            </svg>
        </button>
    </div>

    {{-- Progress Bar --}}
    <div class="px-4 py-2 bg-slate-100/50 dark:bg-gray-800/30">
        <div class="h-1.5 w-full bg-slate-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div
                class="h-full transition-all duration-300 ease-out rounded-full {{ ($scriptExecution['failedCount'] ?? 0) > 0 ? 'bg-red-500' : 'bg-purple-500' }}"
                style="width: {{ $scriptExecution['progressPercentage'] ?? 0 }}%"
            ></div>
        </div>
        <p class="text-xs text-slate-500 dark:text-gray-400 mt-1 text-center">{{ $scriptExecution['progressPercentage'] ?? 0 }}% complete</p>
    </div>

    {{-- Command List --}}
    <div class="flex-1 overflow-y-auto px-2 py-2">
        @foreach($scriptExecution['commands'] ?? [] as $index => $cmd)
        @php
            $status = \MWGuerra\WebTerminal\Enums\ScriptCommandStatus::from($cmd['status']);
        @endphp
        <div class="flex items-start gap-2 px-2 py-2 rounded-lg {{ $status === \MWGuerra\WebTerminal\Enums\ScriptCommandStatus::Running ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
            {{-- Status Icon --}}
            <div class="shrink-0 mt-0.5">
                @switch($cmd['status'])
                    @case('pending')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-400 dark:text-gray-500">
                            <circle cx="12" cy="12" r="10" stroke-dasharray="4 4" />
                        </svg>
                        @break
                    @case('running')
                        <svg class="w-4 h-4 text-blue-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        @break
                    @case('success')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-emerald-500">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                        </svg>
                        @break
                    @case('failed')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-red-500">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        @break
                    @case('skipped')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-amber-500">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z" clip-rule="evenodd" />
                        </svg>
                        @break
                @endswitch
            </div>

            {{-- Command Info --}}
            <div class="flex-1 min-w-0">
                <p class="font-mono text-xs text-slate-700 dark:text-gray-200 truncate" title="{{ $cmd['command'] }}">{{ $cmd['command'] }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[10px] {{ $status->cssClass() }}">{{ $status->label() }}</span>
                    @if($cmd['executionTime'] !== null)
                    <span class="text-[10px] text-slate-400 dark:text-gray-500">{{ round($cmd['executionTime'] * 1000) }}ms</span>
                    @endif
                    @if($cmd['exitCode'] !== null && $cmd['exitCode'] !== 0)
                    <span class="text-[10px] text-red-500">exit {{ $cmd['exitCode'] }}</span>
                    @endif
                </div>
            </div>

            {{-- Command Number --}}
            <span class="text-[10px] text-slate-400 dark:text-gray-500 shrink-0">#{{ $index + 1 }}</span>
        </div>
        @endforeach
    </div>

    {{-- Panel Footer - only shown when script is running --}}
    @if($scriptExecution['isRunning'] ?? false)
    <div class="px-4 py-3 border-t border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-gray-800/50">
        <button
            type="button"
            wire:click="cancelScript"
            class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            <span>Emergency Stop (Ctrl+C)</span>
        </button>
    </div>
    @endif
</div>
@endif
