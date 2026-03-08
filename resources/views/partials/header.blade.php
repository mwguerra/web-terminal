{{-- Terminal Header Bar --}}
<div class="flex items-center px-4 py-3 bg-slate-200/80 dark:bg-black/30 border-b border-slate-300 dark:border-white/5">
    @if($showWindowControls)
    <div class="flex gap-2">
        <span class="w-3 h-3 rounded-full bg-[#ff5f56] hover:opacity-80 transition-opacity"></span>
        <span class="w-3 h-3 rounded-full bg-[#ffbd2e] hover:opacity-80 transition-opacity"></span>
        <span class="w-3 h-3 rounded-full bg-[#27c93f] hover:opacity-80 transition-opacity"></span>
    </div>
    @endif
    <div class="flex-1 text-center text-xs font-medium text-slate-500 dark:text-white/50 tracking-wide">{{ $title }}</div>
    {{-- Header Actions --}}
    <div class="flex items-center gap-2">
        {{-- Scripts Dropdown Button --}}
        @if(!empty($this->scripts))
        <div class="relative" x-data="{
            showScriptsDropdown: false,
            isScriptActive: $wire.entangle('scriptExecution').live,
            connectedState: $wire.entangle('isConnected').live,
            dropdownMaxWidth: 320,
            dropdownMaxHeight: 320,
            updateDropdownSize() {
                const terminal = this.$el.closest('.secure-web-terminal');
                if (!terminal) return;
                const header = terminal.querySelector(':scope > div.border-b');
                const input = terminal.querySelector(':scope > div.border-t');
                const headerHeight = header ? header.offsetHeight : 0;
                const inputHeight = input ? input.offsetHeight : 0;
                this.dropdownMaxWidth = Math.max(280, terminal.offsetWidth / 2);
                this.dropdownMaxHeight = Math.max(200, terminal.offsetHeight - headerHeight - inputHeight - 20);
            }
        }" x-init="updateDropdownSize(); window.addEventListener('resize', () => updateDropdownSize())">
            <button
                type="button"
                @click="showScriptsDropdown = !showScriptsDropdown; if(showScriptsDropdown) updateDropdownSize()"
                @click.away="showScriptsDropdown = false"
                class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
                :class="{
                    'bg-purple-500/20 text-purple-600 ring-2 ring-purple-500 ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 shadow-[0_0_15px_rgba(168,85,247,0.5)] animate-pulse': isScriptActive && isScriptActive.isRunning === true,
                    'bg-purple-500/20 text-purple-600 ring-1 ring-purple-500/40 dark:bg-purple-500/30 dark:text-purple-400 dark:ring-purple-500/50': showScriptsDropdown && !(isScriptActive && isScriptActive.isRunning),
                    'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60': !showScriptsDropdown && !(isScriptActive && isScriptActive.isRunning)
                }"
                x-bind:disabled="!connectedState || (isScriptActive && isScriptActive.isRunning === true)"
                :title="isScriptActive && isScriptActive.isRunning ? 'Script running...' : 'Run scripts'"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Scripts Dropdown Menu --}}
            <div
                x-show="showScriptsDropdown"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 mt-2 min-w-72 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-black/5 dark:ring-white/10 z-50 overflow-hidden"
                :style="{ maxWidth: dropdownMaxWidth + 'px' }"
                @click.away="showScriptsDropdown = false"
            >
                <div class="px-3 py-2 border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-gray-800/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-gray-400 uppercase tracking-wide">Available Scripts</p>
                </div>
                <div class="py-1 overflow-y-auto" :style="{ maxHeight: (dropdownMaxHeight - 40) + 'px' }">
                    @foreach($this->getAuthorizedScripts() as $script)
                    <button
                        type="button"
                        wire:click="runScript('{{ $script['key'] }}')"
                        @click="showScriptsDropdown = false"
                        class="w-full px-3 py-2.5 text-left hover:bg-slate-100 dark:hover:bg-white/10 transition-colors {{ $script['authorized'] ? '' : 'opacity-50 cursor-not-allowed' }}"
                        {{ $script['authorized'] ? '' : 'disabled' }}
                    >
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-purple-500 dark:text-purple-400 shrink-0">
                                <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-800 dark:text-gray-100 truncate" title="{{ $script['label'] }}">{{ $script['label'] }}</p>
                                @if($script['description'] ?? false)
                                <p class="text-xs text-slate-500 dark:text-gray-400 truncate" title="{{ $script['description'] }}">{{ $script['description'] }}</p>
                                @endif
                                @if(!($script['authorized'] ?? true))
                                <p class="text-xs text-red-500 dark:text-red-400 truncate mt-0.5" title="Unauthorized: {{ implode(', ', $script['unauthorizedCommands'] ?? []) }}">
                                    Unauthorized: {{ implode(', ', array_slice($script['unauthorizedCommands'] ?? [], 0, 2)) }}{{ count($script['unauthorizedCommands'] ?? []) > 2 ? '...' : '' }}
                                </p>
                                @endif
                            </div>
                            <span class="text-xs text-slate-400 dark:text-gray-500 shrink-0">{{ $script['commandCount'] }} cmd{{ $script['commandCount'] !== 1 ? 's' : '' }}</span>
                        </div>
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Copy All Button --}}
        <button
            type="button"
            @click="copyAllOutput()"
            class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
            :class="{
                'bg-emerald-500/20 text-emerald-600 ring-1 ring-emerald-500/40 dark:bg-emerald-500/30 dark:text-emerald-400': copyFeedback,
                'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60': !copyFeedback
            }"
            :title="copyFeedback ? 'Copied!' : 'Copy all output'"
            :disabled="!$wire.output || $wire.output.length === 0"
        >
            {{-- Clipboard icon (default) --}}
            <svg x-show="!copyFeedback" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3A1.5 1.5 0 0 1 13 3.5H7ZM5.5 5A1.5 1.5 0 0 0 4 6.5v10A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-10A1.5 1.5 0 0 0 14.5 5h-9Z"/>
                <path d="M8.5 1A2.5 2.5 0 0 0 6 3.5H4.5A2.5 2.5 0 0 0 2 6v10.5A2.5 2.5 0 0 0 4.5 19h9a2.5 2.5 0 0 0 2.5-2.5V6a2.5 2.5 0 0 0-2.5-2.5H12A2.5 2.5 0 0 0 9.5 1h-1Z"/>
            </svg>
            {{-- Checkmark icon (after copy) --}}
            <svg x-show="copyFeedback" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
            </svg>
        </button>

        {{-- Info Toggle Button --}}
        <button
            type="button"
            @click="showInfoPanel = !showInfoPanel"
            class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
            :class="{
                'bg-blue-500/20 text-blue-600 ring-1 ring-blue-500/40 dark:bg-blue-500/30 dark:text-blue-400 dark:ring-blue-500/50': showInfoPanel,
                'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60': !showInfoPanel
            }"
            title="Connection info"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
        </button>
        <button
            type="button"
            @click="handleToggle()"
            class="relative flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full transition-all duration-200 overflow-hidden"
            :class="{
                'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40 hover:bg-emerald-500/25 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30 dark:hover:bg-emerald-500/30': !isConnected && !cooldownActive,
                'bg-red-500/10 text-red-600 border border-red-500/40 hover:bg-red-500/20 dark:text-red-400 dark:border-red-500/30': isConnected && !cooldownActive,
                'text-red-600 border border-red-500/40 dark:text-red-400 dark:border-red-500/30': isConnected && cooldownActive,
                'text-emerald-600 border border-emerald-500/40 dark:text-emerald-400 dark:border-emerald-500/30': !isConnected && cooldownActive
            }"
            :title="cooldownActive ? 'Please wait...' : (isConnected ? 'Disconnect terminal' : 'Connect terminal')"
        >
            {{-- Cooldown fill background --}}
            <div
                x-show="cooldownActive"
                class="absolute inset-0 rounded-full"
                :class="isConnected ? 'bg-red-500/40' : 'bg-emerald-500/40'"
                :style="'width: ' + cooldownProgress + '%'"
            ></div>
            {{-- Icon --}}
            <svg
                x-show="!isConnected"
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                class="relative z-10 w-3.5 h-3.5 shrink-0"
            >
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 001.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 0 10-1.02 1.1l2.1 1.95H6.75z" clip-rule="evenodd" />
            </svg>
            <svg
                x-show="isConnected"
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                class="relative z-10 w-3.5 h-3.5 shrink-0"
            >
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
            </svg>
            {{-- Text --}}
            <span x-show="!isConnected" class="relative z-10">Connect</span>
            <span x-show="isConnected" class="relative z-10">Disconnect</span>
        </button>
    </div>
</div>
