<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;

class TerminalContainer extends Component
{
    #[Locked]
    public array $classicParams = [];

    #[Locked]
    public array $streamParams = [];

    #[Locked]
    public string $defaultMode = 'classic';

    public string $height = '400px';
    public string $title = 'Terminal';
    public bool $showWindowControls = true;

    public function mount(
        array $classicParams = [],
        array $streamParams = [],
        string $defaultMode = 'classic',
        string $height = '400px',
        string $title = 'Terminal',
        bool $showWindowControls = true,
    ): void {
        $this->classicParams = $classicParams;
        $this->streamParams = $streamParams;
        $this->defaultMode = $defaultMode;
        $this->height = $height;
        $this->title = $title;
        $this->showWindowControls = $showWindowControls;
    }

    public function render()
    {
        return view('web-terminal::terminal-container');
    }
}
