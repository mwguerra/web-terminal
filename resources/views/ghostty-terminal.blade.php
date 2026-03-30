<div
    class="ghostty-web-terminal relative font-mono text-[13px] leading-tight bg-gradient-to-b from-slate-100 to-white dark:from-[#1a1a2e] dark:to-[#16213e] text-zinc-800 dark:text-zinc-200 rounded-xl overflow-hidden flex flex-col shadow-2xl ring-1 ring-slate-200 dark:ring-white/5 text-left"
    style="height: {{ $height }}; min-height: 200px;"
    x-data="{
        isConnected: $wire.entangle('isConnected'),
        showInfoPanel: false,
        copyFeedback: false,
        ws: null,
        terminal: null,
        fitAddon: null,
        dataDisposable: null,
        resizeDisposable: null,

        async initGhostty() {
            try {
                if (typeof GhosttyWeb === 'undefined') {
                    console.error('[GhosttyTerminal] GhosttyWeb not loaded. Ensure ghostty-terminal.js is included.');
                    return;
                }

                await GhosttyWeb.init();

                this.terminal = new GhosttyWeb.Terminal({
                    cursorBlink: true,
                    fontSize: 13,
                    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                    theme: {{ json_encode($ghosttyTheme) }},
                });

                this.fitAddon = new GhosttyWeb.FitAddon();
                this.terminal.loadAddon(this.fitAddon);
                this.terminal.open(this.$refs.ghosttyContainer);
                this.fitAddon.fit();
                this.fitAddon.observeResize();
            } catch (e) {
                console.error('[GhosttyTerminal] Failed to load ghostty-web module:', e);
            }
        },

        async connect() {
            try {
                const result = await $wire.getWebSocketUrl();

                if (result.error) {
                    console.error('[GhosttyTerminal] Auth error:', result.error);
                    return;
                }

                this.ws = new WebSocket(result.url);

                this.ws.onopen = () => {
                    $wire.connect();
                    if (this.terminal) {
                        this.ws.send(JSON.stringify({
                            type: 'resize',
                            cols: this.terminal.cols,
                            rows: this.terminal.rows,
                        }));
                    }
                };

                this.ws.onmessage = (event) => {
                    if (this.terminal) {
                        this.terminal.write(event.data);
                    }
                };

                this.ws.onerror = (error) => {
                    console.error('[GhosttyTerminal] WebSocket error:', error);
                };

                this.ws.onclose = () => {
                    $wire.disconnect();
                    this.ws = null;
                };

                if (this.terminal) {
                    if (this.dataDisposable) this.dataDisposable.dispose();
                    if (this.resizeDisposable) this.resizeDisposable.dispose();

                    this.dataDisposable = this.terminal.onData((data) => {
                        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                            this.ws.send(data);
                        }
                    });

                    this.resizeDisposable = this.terminal.onResize(({ cols, rows }) => {
                        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                            this.ws.send(JSON.stringify({ type: 'resize', cols, rows }));
                        }
                    });
                }
            } catch (e) {
                console.error('[GhosttyTerminal] Connect error:', e);
            }
        },

        disconnect() {
            if (this.ws) {
                this.ws.close();
                this.ws = null;
            }
            $wire.disconnect();
        },

        handleToggle() {
            if (this.isConnected) {
                this.disconnect();
            } else {
                this.connect();
            }
        },

        async copyAllOutput() {
            if (!this.terminal) return;

            try {
                const buffer = this.terminal.buffer.active;
                let text = '';
                for (let i = 0; i < buffer.length; i++) {
                    const line = buffer.getLine(i);
                    if (line) {
                        text += line.translateToString(true) + '\n';
                    }
                }
                await navigator.clipboard.writeText(text.trimEnd());
                this.copyFeedback = true;
                setTimeout(() => { this.copyFeedback = false; }, 2000);
            } catch (e) {
                console.error('[GhosttyTerminal] Copy failed:', e);
            }
        },

        async runScript(key) {
            const commands = await $wire.getScriptsForExecution(key);
            if (!commands || !commands.length) return;

            for (const cmd of commands) {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send(cmd + '\n');
                }
            }
        },

        destroy() {
            window.removeEventListener('beforeunload', this.destroy.bind(this));
            if (this.ws) {
                this.ws.close();
            }
            if (this.terminal) {
                this.terminal.dispose();
            }
        },

        init() {
            window.addEventListener('beforeunload', this.destroy.bind(this));

            const autoConnect = @json($autoConnect);

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !this.terminal) {
                    this.initGhostty();
                    if (autoConnect) {
                        this.$nextTick(() => this.connect());
                    }
                    observer.disconnect();
                } else if (entries[0].isIntersecting && this.fitAddon) {
                    this.fitAddon.fit();
                }
            });
            observer.observe(this.$el);
        },
    }"
>
    {{-- Header Bar --}}
    <div class="flex items-center px-4 py-3 bg-slate-200/80 dark:bg-black/30 border-b border-slate-300 dark:border-white/5 shrink-0">
        @if($showWindowControls)
        <div class="flex gap-2 shrink-0">
            <span class="w-3 h-3 rounded-full bg-[#ff5f56] hover:opacity-80 transition-opacity"></span>
            <span class="w-3 h-3 rounded-full bg-[#ffbd2e] hover:opacity-80 transition-opacity"></span>
            <span class="w-3 h-3 rounded-full bg-[#27c93f] hover:opacity-80 transition-opacity"></span>
        </div>
        @endif
        <div class="flex-1 min-w-0 ml-3 text-xs font-medium text-slate-500 dark:text-white/50 tracking-wide truncate">{{ $title }}</div>

        {{-- Header Actions --}}
        <div class="flex items-center gap-2 shrink-0">
            @if($hasModePill)
            @include('web-terminal::partials.toggle-pill')
            @endif

            {{-- Scripts Dropdown --}}
            @if(!empty($scripts))
            <div class="relative" x-data="{ showScriptsDropdown: false }">
                <button
                    type="button"
                    @click="showScriptsDropdown = !showScriptsDropdown"
                    @click.away="showScriptsDropdown = false"
                    class="flex items-center justify-center w-7 h-7 rounded-full transition-all duration-200"
                    :class="{
                        'bg-purple-500/20 text-purple-600 ring-1 ring-purple-500/40 dark:bg-purple-500/30 dark:text-purple-400 dark:ring-purple-500/50': showScriptsDropdown,
                        'bg-slate-300/50 text-slate-500 hover:bg-slate-300 hover:text-slate-700 dark:bg-white/5 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white/60': !showScriptsDropdown
                    }"
                    :disabled="!isConnected"
                    title="Run scripts"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-show="showScriptsDropdown"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 mt-2 min-w-64 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-black/5 dark:ring-white/10 z-50 overflow-hidden"
                    @click.away="showScriptsDropdown = false"
                >
                    <div class="px-3 py-2 border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-gray-800/50">
                        <p class="text-xs font-medium text-slate-500 dark:text-gray-400 uppercase tracking-wide">Available Scripts</p>
                    </div>
                    <div class="py-1">
                        @foreach($scripts as $script)
                        <button
                            type="button"
                            @click="runScript('{{ $script['key'] ?? '' }}'); showScriptsDropdown = false"
                            class="w-full px-3 py-2.5 text-left hover:bg-slate-100 dark:hover:bg-white/10 transition-colors"
                        >
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-purple-500 dark:text-purple-400 shrink-0">
                                    <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-800 dark:text-gray-100 truncate">{{ $script['label'] ?? $script['key'] ?? 'Script' }}</p>
                                    @if(!empty($script['description']))
                                    <p class="text-xs text-slate-500 dark:text-gray-400 truncate">{{ $script['description'] }}</p>
                                    @endif
                                </div>
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
                :disabled="!isConnected"
            >
                <svg x-show="!copyFeedback" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3A1.5 1.5 0 0 1 13 3.5H7ZM5.5 5A1.5 1.5 0 0 0 4 6.5v10A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-10A1.5 1.5 0 0 0 14.5 5h-9Z"/>
                </svg>
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

            {{-- Connect/Disconnect Button --}}
            @if(!$autoConnect)
            <button
                type="button"
                @click="handleToggle()"
                class="relative flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full transition-all duration-200"
                :class="{
                    'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40 hover:bg-emerald-500/25 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30 dark:hover:bg-emerald-500/30': !isConnected,
                    'bg-red-500/10 text-red-600 border border-red-500/40 hover:bg-red-500/20 dark:text-red-400 dark:border-red-500/30': isConnected
                }"
                :title="isConnected ? 'Disconnect terminal' : 'Connect terminal'"
            >
                <svg x-show="!isConnected" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 shrink-0">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 001.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 10-1.02 1.1l2.1 1.95H6.75z" clip-rule="evenodd" />
                </svg>
                <svg x-show="isConnected" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 shrink-0">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
                <span x-show="!isConnected">Connect</span>
                <span x-show="isConnected">Disconnect</span>
            </button>
            @endif
        </div>
    </div>

    {{-- Info Panel Overlay --}}
    <div
        x-show="showInfoPanel"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute inset-x-0 top-[49px] z-40 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-slate-200 dark:border-white/10 p-4 text-xs"
    >
        <div class="space-y-1.5 text-slate-600 dark:text-gray-300">
            <div class="flex items-center gap-2">
                <span class="font-medium text-slate-500 dark:text-gray-400 w-24">Status</span>
                <span :class="isConnected ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-gray-500'">
                    <span x-text="isConnected ? 'Connected' : 'Disconnected'"></span>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="font-medium text-slate-500 dark:text-gray-400 w-24">Mode</span>
                <span>Ghostty WebSocket PTY</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="font-medium text-slate-500 dark:text-gray-400 w-24">Component</span>
                <span class="font-mono text-[10px] text-slate-400 dark:text-gray-500">{{ $componentId }}</span>
            </div>
        </div>
    </div>

    {{-- Ghostty Terminal Container --}}
    <div
        x-ref="ghosttyContainer"
        wire:ignore
        class="flex-1 overflow-hidden"
        style="background: #1a1a2e;"
    ></div>
</div>
