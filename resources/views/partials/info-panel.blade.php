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
        <div class="flex flex-wrap items-center gap-3 pb-4 border-b border-slate-200 dark:border-white/10">
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

        {{-- Two Column Layout: Connection Details + Session Info --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Connection Details --}}
            <div class="space-y-3 @if(!$this->isLoggingEnabled() || !$terminalSessionId) sm:col-span-2 @endif">
                <h3 class="text-xs font-semibold text-slate-500 dark:text-white/60 uppercase tracking-wider">Connection Details</h3>

                <div class="space-y-2">
                    {{-- Connection Type --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Type</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $this->getConnectionType() }}</span>
                    </div>

                    @if($this->getConnectionType() === 'SSH')
                        {{-- Host --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                            <span class="text-xs text-slate-500 dark:text-white/50">Host</span>
                            <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono break-all">{{ $this->getDisplayHost() ?? 'N/A' }}</span>
                        </div>

                        {{-- Port --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                            <span class="text-xs text-slate-500 dark:text-white/50">Port</span>
                            <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono">{{ $this->getDisplayPort() }}</span>
                        </div>

                        {{-- Username --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                            <span class="text-xs text-slate-500 dark:text-white/50">Username</span>
                            <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono break-all">{{ $this->getDisplayUsername() ?? 'N/A' }}</span>
                        </div>

                        {{-- Auth Method --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                            <span class="text-xs text-slate-500 dark:text-white/50">Auth Method</span>
                            <span class="text-xs font-medium text-slate-800 dark:text-white/90">
                                {{ $this->getDisplayAuthMethod() === 'key' ? 'SSH Key' : 'Password' }}
                            </span>
                        </div>
                    @endif

                    {{-- Working Directory --}}
                    @if($this->getDisplayWorkingDirectory())
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Working Directory</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono break-all" title="{{ $this->getDisplayWorkingDirectory() }}">{{ $this->getDisplayWorkingDirectory() }}</span>
                    </div>
                    @endif

                    {{-- Timeout --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Command Timeout</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $timeout }}s</span>
                    </div>

                    {{-- Login Shell --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Login Shell</span>
                        <span class="text-xs font-medium {{ $useLoginShell ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-white/50' }}">
                            {{ $useLoginShell ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    {{-- Command Restrictions --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
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
            </div>

            {{-- Session Info (if logging enabled) --}}
            @if($this->isLoggingEnabled() && $terminalSessionId)
            @php($sessionStats = $this->getSessionStats())
            <div class="space-y-3">
                <h3 class="text-xs font-semibold text-slate-500 dark:text-white/60 uppercase tracking-wider">Session Info</h3>

                <div class="space-y-2">
                    {{-- Session ID --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Session ID</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90 font-mono break-all" title="{{ $terminalSessionId }}">{{ Str::limit($terminalSessionId, 8, '...') }}</span>
                    </div>

                    @if($sessionStats)
                    {{-- Commands Run --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Commands Run</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $sessionStats['command_count'] }}</span>
                    </div>

                    {{-- Session Duration --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Session Duration</span>
                        <span class="text-xs font-medium text-slate-800 dark:text-white/90">{{ $sessionStats['duration'] }}</span>
                    </div>

                    {{-- Errors --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 px-3 rounded-lg bg-slate-200/50 dark:bg-white/5">
                        <span class="text-xs text-slate-500 dark:text-white/50">Errors</span>
                        <span class="text-xs font-medium {{ $sessionStats['error_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $sessionStats['error_count'] }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Allowed Commands List (if restricted) - Full Width --}}
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

        {{-- Close Button - Full Width --}}
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
