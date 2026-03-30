<div
    x-data="{ activeMode: '{{ $defaultMode }}' }"
    class="relative"
    style="height: {{ $height }}; min-height: 200px;"
>
    {{-- Classic Terminal --}}
    <div x-show="activeMode === 'classic'" x-cloak class="h-full">
        @livewire('web-terminal', array_merge($classicParams, ['hasModePill' => true]), key('classic-terminal'))
    </div>

    {{-- Ghostty Terminal --}}
    <div x-show="activeMode === 'ghostty'" x-cloak class="h-full">
        @livewire('ghostty-terminal', array_merge($ghosttyParams, [
            'height' => $height,
            'title' => $title,
            'showWindowControls' => $showWindowControls,
            'hasModePill' => true,
        ]), key('ghostty-terminal'))
    </div>
</div>
