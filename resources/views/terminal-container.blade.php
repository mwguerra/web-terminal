<div
    x-data="{ activeMode: '{{ $defaultMode }}' }"
    class="relative"
    style="height: {{ $height }}; min-height: 200px;"
>
    {{-- Classic Terminal --}}
    <div x-show="activeMode === 'classic'" x-cloak class="h-full">
        @livewire('web-terminal', array_merge($classicParams, ['hasModePill' => true]), key('classic-terminal'))
    </div>

    {{-- Stream Terminal --}}
    <div x-show="activeMode === 'stream'" x-cloak class="h-full">
        @livewire('stream-terminal', array_merge($streamParams, [
            'height' => $height,
            'title' => $title,
            'showWindowControls' => $showWindowControls,
            'hasModePill' => true,
        ]), key('stream-terminal'))
    </div>
</div>
