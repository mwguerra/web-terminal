<div
    class="secure-web-terminal relative font-mono text-[13px] leading-tight bg-gradient-to-b from-slate-100 to-white dark:from-[#1a1a2e] dark:to-[#16213e] text-zinc-800 dark:text-zinc-200 rounded-xl overflow-hidden flex flex-col shadow-2xl ring-1 ring-slate-200 dark:ring-white/5 text-left"
    style="height: {{ $height }}; min-height: 200px;"
    x-data="{
        isInteractive: @entangle('isInteractive'),
        isConnected: @entangle('isConnected'),
        showInfoPanel: false,
        pollInterval: null,
        cooldownActive: false,
        cooldownProgress: 0,
        cooldownAnimationFrame: null,
        cooldownStartTime: null,
        init() {
            if (this.isConnected) {
                this.$refs.input.focus();
            }
            this.scrollToBottom();

            Livewire.hook('morph.updated', ({ el }) => {
                if (el === this.$el || this.$el.contains(el)) {
                    this.scrollToBottom();
                }
            });

            // Watch for interactive state changes using Alpine's $watch
            this.$watch('isInteractive', (value) => {
                if (value) {
                    this.startPolling();
                } else {
                    this.stopPolling();
                    if (this.isConnected) {
                        this.$refs.input.focus();
                    }
                }
            });

            // Watch for connection state changes
            this.$watch('isConnected', (value) => {
                if (value) {
                    this.$nextTick(() => this.$refs.input.focus());
                }
            });

            // Start polling if already in interactive mode
            if (this.isInteractive) {
                this.startPolling();
            }
        },
        handleToggle() {
            // Ignore clicks during cooldown
            if (this.cooldownActive) {
                return;
            }
            // Perform action immediately
            if (this.isConnected) {
                $wire.disconnect();
            } else {
                $wire.connect();
            }
            // Start cooldown animation
            this.startCooldown();
        },
        startCooldown() {
            this.cooldownActive = true;
            this.cooldownProgress = 0;
            this.cooldownStartTime = performance.now();
            this.animateCooldown();
        },
        animateCooldown() {
            const elapsed = performance.now() - this.cooldownStartTime;
            const duration = 1000; // 1 second
            this.cooldownProgress = Math.min((elapsed / duration) * 100, 100);

            if (this.cooldownProgress >= 100) {
                this.clearCooldown();
            } else {
                this.cooldownAnimationFrame = requestAnimationFrame(() => this.animateCooldown());
            }
        },
        clearCooldown() {
            this.cooldownActive = false;
            this.cooldownProgress = 0;
            this.cooldownStartTime = null;
            if (this.cooldownAnimationFrame) {
                cancelAnimationFrame(this.cooldownAnimationFrame);
                this.cooldownAnimationFrame = null;
            }
        },
        startPolling() {
            if (this.pollInterval) return;
            this.pollInterval = setInterval(() => {
                $wire.pollOutput();
            }, 500);
        },
        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const output = this.$refs.output;
                if (output) {
                    output.scrollTop = output.scrollHeight;
                }
            });
        },
        handleKeydown(event) {
            // Ignore if not connected
            if (!this.isConnected) {
                return;
            }

            // Ctrl+C to cancel process
            if (event.ctrlKey && event.key === 'c' && this.isInteractive) {
                event.preventDefault();
                $wire.cancelProcess();
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                $wire.historyUp();
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                $wire.historyDown();
            } else {
                $wire.resetHistoryIndex();
            }
        }
    }"
    x-init="init()"
    wire:loading.class="opacity-90"
>
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

    {{-- Info Panel Overlay --}}
    <div
        x-show="showInfoPanel"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 top-[49px] z-10 bg-gradient-to-b from-slate-100/98 to-white/98 dark:from-[#1a1a2e]/98 dark:to-[#16213e]/98 backdrop-blur-sm overflow-y-auto"
    >
        <div class="p-5 space-y-4">
            {{-- Connection Status --}}
            <div class="flex items-center gap-3 pb-4 border-b border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-center w-10 h-10 rounded-full"
                    :class="isConnected ? 'bg-emerald-500/20' : 'bg-zinc-500/20'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        class="w-5 h-5"
                        :class="isConnected ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400'">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium"
                        :class="isConnected ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400'"
                        x-text="isConnected ? 'Connected' : 'Disconnected'"></div>
                    <div class="text-xs text-slate-500 dark:text-white/40">Session status</div>
                </div>
            </div>

            {{-- Connection Details --}}
            <div class="space-y-3">
                <h3 class="text-xs font-semibold text-slate-500 dark:text-white/60 uppercase tracking-wider">Connection Details</h3>

                {{-- Connection Type --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Type</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $this->getConnectionType() }}</span>
                </div>

                @if($this->getConnectionType() === 'SSH')
                    {{-- Host --}}
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Host</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono">{{ $connectionConfig['host'] ?? 'N/A' }}</span>
                    </div>

                    {{-- Port --}}
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Port</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono">{{ $connectionConfig['port'] ?? 22 }}</span>
                    </div>

                    {{-- Username --}}
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Username</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono">{{ $connectionConfig['username'] ?? 'N/A' }}</span>
                    </div>

                    {{-- Auth Method --}}
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Auth Method</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90">
                            @if(!empty($connectionConfig['private_key']))
                                SSH Key
                            @else
                                Password
                            @endif
                        </span>
                    </div>
                @endif

                {{-- Working Directory --}}
                @if(!empty($connectionConfig['working_directory']))
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Working Directory</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono truncate max-w-[200px]" title="{{ $connectionConfig['working_directory'] }}">{{ $connectionConfig['working_directory'] }}</span>
                </div>
                @endif

                {{-- Timeout --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Command Timeout</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $timeout }}s</span>
                </div>

                {{-- Login Shell --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Login Shell</span>
                    <span class="text-xs font-medium {{ $useLoginShell ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-white/50' }}">
                        {{ $useLoginShell ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>

                {{-- Command Restrictions --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Command Access</span>
                    <span class="text-xs font-medium {{ $allowAllCommands ? 'text-amber-600 dark:text-amber-400' : 'text-blue-600 dark:text-blue-400' }}">
                        @if($allowAllCommands)
                            All Commands
                        @elseif(count($allowedCommands) > 0)
                            {{ count($allowedCommands) }} allowed
                        @else
                            No restrictions
                        @endif
                    </span>
                </div>
            </div>

            {{-- Session Info (if logging enabled) --}}
            @if($this->isLoggingEnabled() && $terminalSessionId)
            @php($sessionStats = $this->getSessionStats())
            <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-white/10">
                <h3 class="text-xs font-semibold text-slate-500 dark:text-white/60 uppercase tracking-wider">Session Info</h3>

                {{-- Session ID --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Session ID</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono" title="{{ $terminalSessionId }}">{{ Str::limit($terminalSessionId, 8, '...') }}</span>
                </div>

                @if($sessionStats)
                {{-- Commands Run --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Commands Run</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $sessionStats['command_count'] }}</span>
                </div>

                {{-- Session Duration --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Session Duration</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $sessionStats['duration'] }}</span>
                </div>

                {{-- Errors --}}
                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                    <span class="text-xs text-slate-500 dark:text-white/50">Errors</span>
                    <span class="text-xs font-medium {{ $sessionStats['error_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        {{ $sessionStats['error_count'] }}
                    </span>
                </div>
                @endif
            </div>
            @endif

            {{-- Allowed Commands List (if restricted) --}}
            @if(!$allowAllCommands && count($allowedCommands) > 0)
            <div class="space-y-2 pt-2">
                <h3 class="text-xs font-semibold text-slate-500 dark:text-white/60 uppercase tracking-wider">Allowed Commands</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($allowedCommands as $cmd)
                    <span class="px-2 py-0.5 text-[10px] font-mono text-blue-600 dark:text-blue-300 bg-blue-500/10 border border-blue-500/30 dark:border-blue-500/20 rounded">{{ $cmd }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Close Button --}}
            <div class="pt-4 border-t border-slate-200 dark:border-white/10">
                <button
                    type="button"
                    @click="showInfoPanel = false"
                    class="w-full py-2 px-4 text-xs font-medium text-slate-600 dark:text-white/70 bg-slate-200/50 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 rounded-lg transition-colors"
                >
                    Close Info Panel
                </button>
            </div>
        </div>
    </div>

    {{-- Terminal Output Area --}}
    <div
        class="terminal-output flex-1 overflow-y-auto p-3 scroll-smooth text-left"
        x-ref="output"
        role="log"
        aria-live="polite"
        aria-label="Terminal output"
    >
        @foreach($output as $line)
            @if(trim($line['content'] ?? '') !== '')
                <div class="whitespace-pre-wrap break-words m-0 p-0 leading-snug text-left block w-full
                    @if(($line['type'] ?? '') === 'stdout') text-slate-700 dark:text-zinc-200
                    @elseif(($line['type'] ?? '') === 'stderr') text-red-600 dark:text-red-300
                    @elseif(($line['type'] ?? '') === 'error') text-red-700 dark:text-red-500 font-semibold
                    @elseif(($line['type'] ?? '') === 'info') text-blue-600 dark:text-blue-400
                    @elseif(($line['type'] ?? '') === 'command') text-emerald-600 dark:text-emerald-400 font-medium pt-1 pb-0.5
                    @elseif(($line['type'] ?? '') === 'system') text-slate-500 dark:text-gray-500 italic
                    @else text-slate-700 dark:text-zinc-200
                    @endif
                ">{!! $this->convertAnsiToHtml($line['content']) !!}</div>
            @endif
        @endforeach
    </div>

    {{-- Terminal Input Area --}}
    <div class="flex items-center px-3 py-2.5 bg-slate-100/80 dark:bg-black/20 border-t border-slate-200 dark:border-white/5" :class="{ 'opacity-50': !isConnected }">
        <span class="flex items-center shrink-0 select-none mr-1" aria-hidden="true">
            <span class="text-blue-600 dark:text-blue-400 font-medium">{{ $this->getShortDirectoryName() }}</span>
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold ml-0.5 mr-0.5">{{ $prompt }}</span>
        </span>
        <input
            type="text"
            class="terminal-input flex-1 bg-transparent border-none outline-none text-slate-800 dark:text-zinc-200 font-mono text-[13px] p-0 m-0 caret-emerald-500 dark:caret-emerald-400 placeholder:text-slate-400 dark:placeholder:text-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
            x-ref="input"
            wire:model="command"
            wire:keydown.enter="executeCommand"
            @keydown="handleKeydown($event)"
            @input="scrollToBottom()"
            :placeholder="!isConnected ? 'Click Connect to start...' : (isInteractive ? 'Type input and press Enter (Ctrl+C to cancel)...' : 'Type a command...')"
            autocomplete="off"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
            aria-label="Command input"
            :disabled="!isConnected || ($wire.isExecuting && !isInteractive)"
        />
        {{-- Loading spinner (non-interactive) --}}
        <div wire:loading wire:target="executeCommand" x-show="!isInteractive" class="ml-3 text-blue-600 dark:text-blue-400">
            <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        {{-- Interactive mode indicator and cancel button --}}
        <div x-show="isInteractive" class="flex items-center gap-2 ml-3">
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
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 ml-3 text-[11px] font-medium text-lime-600 dark:text-lime-400 bg-lime-400/10 border border-lime-500/30 dark:border-lime-400/20 rounded-full uppercase tracking-wide leading-none">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 shrink-0 opacity-80">
                <path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v8.5A2.25 2.25 0 0 1 15.75 15h-3.105a3.501 3.501 0 0 0 1.1 1.677A.75.75 0 0 1 13.26 18H6.74a.75.75 0 0 1-.484-1.323A3.501 3.501 0 0 0 7.355 15H4.25A2.25 2.25 0 0 1 2 12.75v-8.5Zm1.5 0a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-7.5Z" clip-rule="evenodd" />
            </svg>
            <span>{{ $this->getConnectionType() }}</span>
        </span>
    </div>

    {{-- Interactive Controls Bar (only visible during interactive mode) --}}
    <div
        x-show="isInteractive"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="flex items-center justify-center gap-2 px-3 py-2 bg-slate-200/80 dark:bg-black/30 border-t border-slate-300 dark:border-white/5"
    >
        <span class="text-[10px] text-slate-500 dark:text-gray-500 uppercase tracking-wide mr-2">Keys:</span>

        {{-- Arrow Keys --}}
        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="sendSpecialKey('up')"
                class="flex items-center justify-center w-7 h-7 text-xs font-medium text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-slate-300 dark:border-zinc-700 rounded hover:bg-slate-100 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="Arrow Up"
            >↑</button>
            <button
                type="button"
                wire:click="sendSpecialKey('down')"
                class="flex items-center justify-center w-7 h-7 text-xs font-medium text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-slate-300 dark:border-zinc-700 rounded hover:bg-slate-100 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="Arrow Down"
            >↓</button>
            <button
                type="button"
                wire:click="sendSpecialKey('left')"
                class="flex items-center justify-center w-7 h-7 text-xs font-medium text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-slate-300 dark:border-zinc-700 rounded hover:bg-slate-100 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="Arrow Left"
            >←</button>
            <button
                type="button"
                wire:click="sendSpecialKey('right')"
                class="flex items-center justify-center w-7 h-7 text-xs font-medium text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-slate-300 dark:border-zinc-700 rounded hover:bg-slate-100 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="Arrow Right"
            >→</button>
        </div>

        <div class="w-px h-5 bg-slate-300 dark:bg-zinc-700 mx-1"></div>

        {{-- Action Keys --}}
        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="sendSpecialKey('enter')"
                class="flex items-center justify-center px-2.5 h-7 text-[10px] font-medium text-emerald-600 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700/50 rounded hover:bg-emerald-200 dark:hover:bg-emerald-900/50 hover:border-emerald-400 dark:hover:border-emerald-600/50 transition-colors"
                title="Enter"
            >Enter</button>
            <button
                type="button"
                wire:click="sendSpecialKey('space')"
                class="flex items-center justify-center px-2.5 h-7 text-[10px] font-medium text-blue-600 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700/50 rounded hover:bg-blue-200 dark:hover:bg-blue-900/50 hover:border-blue-400 dark:hover:border-blue-600/50 transition-colors"
                title="Space"
            >Space</button>
            <button
                type="button"
                wire:click="sendSpecialKey('tab')"
                class="flex items-center justify-center px-2.5 h-7 text-[10px] font-medium text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-slate-300 dark:border-zinc-700 rounded hover:bg-slate-100 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="Tab"
            >Tab</button>
        </div>

        <div class="w-px h-5 bg-slate-300 dark:bg-zinc-700 mx-1"></div>

        {{-- Escape/Cancel --}}
        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="sendSpecialKey('escape')"
                class="flex items-center justify-center px-2.5 h-7 text-[10px] font-medium text-yellow-600 dark:text-yellow-300 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700/50 rounded hover:bg-yellow-200 dark:hover:bg-yellow-900/50 hover:border-yellow-400 dark:hover:border-yellow-600/50 transition-colors"
                title="Escape"
            >Esc</button>
            <button
                type="button"
                wire:click="sendSpecialKey('backspace')"
                class="flex items-center justify-center px-2.5 h-7 text-[10px] font-medium text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-slate-300 dark:border-zinc-700 rounded hover:bg-slate-100 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="Backspace"
            >⌫</button>
        </div>

        <div class="w-px h-5 bg-slate-300 dark:bg-zinc-700 mx-1"></div>

        {{-- Function Keys (commonly used) --}}
        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="sendSpecialKey('f1')"
                class="flex items-center justify-center w-7 h-7 text-[10px] font-medium text-slate-500 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800/50 border border-slate-300/50 dark:border-zinc-700/50 rounded hover:bg-slate-200 dark:hover:bg-zinc-700 hover:border-slate-400 dark:hover:border-zinc-600 transition-colors"
                title="F1 - Help"
            >F1</button>
            <button
                type="button"
                wire:click="sendSpecialKey('f10')"
                class="flex items-center justify-center w-8 h-7 text-[10px] font-medium text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/20 border border-red-300/50 dark:border-red-700/30 rounded hover:bg-red-200 dark:hover:bg-red-900/40 hover:border-red-400 dark:hover:border-red-600/50 transition-colors"
                title="F10 - Quit (htop)"
            >F10</button>
        </div>
    </div>
</div>
