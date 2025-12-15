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
        disconnectOnNavigate: {{ $disconnectOnNavigate ? 'true' : 'false' }},
        inactivityTimeout: {{ $inactivityTimeout }},
        lastActivityTime: Date.now(),
        inactivityCheckInterval: null,
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
                    this.startInactivityCheck();
                } else {
                    this.stopInactivityCheck();
                }
            });

            // Start polling if already in interactive mode
            if (this.isInteractive) {
                this.startPolling();
            }

            // Setup disconnect on page navigation/refresh
            if (this.disconnectOnNavigate) {
                window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));
                window.addEventListener('pagehide', this.handlePageHide.bind(this));
            }

            // Start inactivity check if already connected
            if (this.isConnected && this.inactivityTimeout > 0) {
                this.startInactivityCheck();
            }
        },
        destroy() {
            if (this.disconnectOnNavigate) {
                window.removeEventListener('beforeunload', this.handleBeforeUnload.bind(this));
                window.removeEventListener('pagehide', this.handlePageHide.bind(this));
            }
            this.stopInactivityCheck();
        },
        handleBeforeUnload(event) {
            if (this.isConnected) {
                // Call disconnect - Livewire will handle this best-effort
                // For cases where the page unloads too fast, the ProcessSessionManager
                // TTL cleanup will handle orphaned tmux sessions
                $wire.disconnect();
            }
        },
        handlePageHide(event) {
            // pagehide fires on mobile browsers when navigating away
            if (this.isConnected) {
                $wire.disconnect();
            }
        },
        recordActivity() {
            this.lastActivityTime = Date.now();
        },
        startInactivityCheck() {
            if (this.inactivityTimeout <= 0) return;

            this.lastActivityTime = Date.now();
            this.stopInactivityCheck();

            // Check every minute
            this.inactivityCheckInterval = setInterval(() => {
                if (!this.isConnected) {
                    this.stopInactivityCheck();
                    return;
                }

                const idleTime = (Date.now() - this.lastActivityTime) / 1000;
                if (idleTime >= this.inactivityTimeout) {
                    $wire.disconnect();
                    this.stopInactivityCheck();
                }
            }, 60000);
        },
        stopInactivityCheck() {
            if (this.inactivityCheckInterval) {
                clearInterval(this.inactivityCheckInterval);
                this.inactivityCheckInterval = null;
            }
        },
        handleToggle() {
            // Ignore clicks during cooldown
            if (this.cooldownActive) {
                return;
            }
            // Record activity
            this.recordActivity();
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

            // Record activity on any keypress
            this.recordActivity();

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
    @include('web-terminal::partials.header')

    @include('web-terminal::partials.info-panel')

    @include('web-terminal::partials.output')

    @include('web-terminal::partials.input')

    @include('web-terminal::partials.interactive-controls')
</div>
