<div
    x-data="{ activeMode: '{{ $defaultMode }}' }"
    class="relative"
    style="height: {{ $height }}; min-height: 200px;"
>
    {{-- Toggle Pill (floating above both terminals) --}}
    <div class="absolute top-3 left-1/2 -translate-x-1/2 z-30">
        @include('web-terminal::partials.toggle-pill')
    </div>

    {{-- Classic Terminal --}}
    <div x-show="activeMode === 'classic'" x-cloak class="h-full">
        @livewire('web-terminal', $classicParams, key('classic-terminal'))
    </div>

    {{-- Ghostty Terminal --}}
    <div x-show="activeMode === 'ghostty'" x-cloak class="h-full">
        @livewire('ghostty-terminal', array_merge($ghosttyParams, [
            'height' => $height,
            'title' => $title,
            'showWindowControls' => $showWindowControls,
        ]), key('ghostty-terminal'))
    </div>
</div>
