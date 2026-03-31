{{-- Terminal Mode Toggle Pill --}}
<div class="flex rounded-full bg-slate-300/50 dark:bg-white/[0.08] border border-slate-300 dark:border-white/10 overflow-hidden text-[10px] font-semibold tracking-wide">
    <button type="button"
        @click="activeMode = 'classic'"
        class="px-2.5 py-1 rounded-full transition-all duration-200"
        :class="activeMode === 'classic'
            ? 'bg-indigo-500/30 text-indigo-600 dark:text-indigo-300'
            : 'text-slate-400 dark:text-white/35 hover:text-slate-600 dark:hover:text-white/50'"
    >Classic</button>
    <button type="button"
        @click="activeMode = 'ghostty'"
        class="px-2.5 py-1 rounded-full transition-all duration-200"
        :class="activeMode === 'ghostty'
            ? 'bg-purple-500/30 text-purple-600 dark:text-purple-300'
            : 'text-slate-400 dark:text-white/35 hover:text-slate-600 dark:hover:text-white/50'"
    >Stream</button>
</div>
