<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal;

use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use MWGuerra\WebTerminal\Filament\Pages\Terminal;
use MWGuerra\WebTerminal\Filament\Resources\TerminalLogResource;

class WebTerminalPlugin implements Plugin
{
    /**
     * Singleton instance for accessing configuration from pages/components.
     */
    protected static ?self $currentInstance = null;

    /**
     * Components to register (pages and resources).
     * If null, all enabled components are registered based on config.
     *
     * @var array<class-string>|null
     */
    protected ?array $components = null;

    /**
     * All available page classes.
     *
     * @var array<class-string>
     */
    protected array $availablePages = [
        Terminal::class,
    ];

    /**
     * All available resource classes.
     *
     * @var array<class-string>
     */
    protected array $availableResources = [
        TerminalLogResource::class,
    ];

    // =========================================================================
    // Terminal Page Configuration
    // =========================================================================

    protected bool $terminalPageEnabled = true;

    protected ?string $terminalNavigationIcon = null;

    protected ?string $terminalNavigationLabel = null;

    protected ?int $terminalNavigationSort = null;

    protected ?string $terminalNavigationGroup = null;

    // =========================================================================
    // Terminal Logs Resource Configuration
    // =========================================================================

    protected bool $terminalLogsEnabled = true;

    protected ?string $terminalLogsNavigationIcon = null;

    protected ?string $terminalLogsNavigationLabel = null;

    protected ?int $terminalLogsNavigationSort = null;

    protected ?string $terminalLogsNavigationGroup = null;

    // =========================================================================
    // Plugin Interface Methods
    // =========================================================================

    public function getId(): string
    {
        return 'web-terminal';
    }

    public function register(Panel $panel): void
    {
        // Store instance for access from pages/components
        static::$currentInstance = $this;

        $pages = [];
        $resources = [];

        if ($this->components !== null) {
            // Register only specified components
            foreach ($this->components as $component) {
                if (in_array($component, $this->availablePages, true)) {
                    $pages[] = $component;
                } elseif (in_array($component, $this->availableResources, true)) {
                    $resources[] = $component;
                }
            }
        } else {
            // Register all components based on fluent config
            if ($this->terminalPageEnabled) {
                $pages[] = Terminal::class;
            }

            if ($this->terminalLogsEnabled) {
                $resources[] = TerminalLogResource::class;
            }
        }

        if (! empty($pages)) {
            $panel->pages($pages);
        }

        if (! empty($resources)) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void
    {
        // Store instance for access from pages/components
        static::$currentInstance = $this;
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a new plugin instance.
     *
     * @param  array<class-string>|null  $components  Optional array of page/resource classes to register.
     *                                                If null, all enabled components are registered.
     *
     * @example
     * // Register all enabled components
     * WebTerminalPlugin::make()
     * @example
     * // Register only specific components
     * WebTerminalPlugin::make([
     *     Terminal::class,
     *     TerminalLogResource::class,
     * ])
     */
    public static function make(?array $components = null): static
    {
        $plugin = app(static::class);
        $plugin->components = $components;

        return $plugin;
    }

    /**
     * Get the current plugin instance.
     */
    public static function get(): static
    {
        if (static::$currentInstance !== null) {
            return static::$currentInstance;
        }

        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Get the current plugin instance or null if not registered.
     */
    public static function current(): ?static
    {
        return static::$currentInstance;
    }

    // =========================================================================
    // Component Selection Methods
    // =========================================================================

    /**
     * Set the components to register.
     *
     * @param  array<class-string>  $components
     */
    public function components(array $components): static
    {
        $this->components = $components;

        return $this;
    }

    /**
     * Register only the specified pages/resources.
     *
     * @param  array<class-string>  $pages
     */
    public function only(array $pages): static
    {
        $this->components = $pages;

        return $this;
    }

    // =========================================================================
    // Terminal Page Configuration
    // =========================================================================

    /**
     * Configure the Terminal demo page.
     */
    public function terminalPage(bool $enabled = true): static
    {
        $this->terminalPageEnabled = $enabled;

        return $this;
    }

    /**
     * Disable the Terminal page.
     */
    public function withoutTerminalPage(): static
    {
        $this->terminalPageEnabled = false;

        return $this;
    }

    /**
     * Configure navigation for the Terminal page.
     */
    public function terminalNavigation(
        ?string $icon = null,
        ?string $label = null,
        ?int $sort = null,
        ?string $group = null
    ): static {
        if ($icon !== null) {
            $this->terminalNavigationIcon = $icon;
        }
        if ($label !== null) {
            $this->terminalNavigationLabel = $label;
        }
        if ($sort !== null) {
            $this->terminalNavigationSort = $sort;
        }
        if ($group !== null) {
            $this->terminalNavigationGroup = $group;
        }

        return $this;
    }

    /**
     * Check if Terminal page is enabled.
     */
    public function isTerminalPageEnabled(): bool
    {
        return $this->terminalPageEnabled;
    }

    /**
     * Get the Terminal navigation icon.
     */
    public function getTerminalNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->terminalNavigationIcon ?? 'heroicon-o-command-line';
    }

    /**
     * Get the Terminal navigation label.
     */
    public function getTerminalNavigationLabel(): string
    {
        return $this->terminalNavigationLabel ?? 'Terminal';
    }

    /**
     * Get the Terminal navigation sort.
     */
    public function getTerminalNavigationSort(): ?int
    {
        return $this->terminalNavigationSort ?? 100;
    }

    /**
     * Get the Terminal navigation group.
     */
    public function getTerminalNavigationGroup(): ?string
    {
        return $this->terminalNavigationGroup ?? 'Tools';
    }

    // =========================================================================
    // Terminal Logs Resource Configuration
    // =========================================================================

    /**
     * Configure the Terminal Logs resource.
     */
    public function terminalLogs(bool $enabled = true): static
    {
        $this->terminalLogsEnabled = $enabled;

        return $this;
    }

    /**
     * Disable the Terminal Logs resource.
     */
    public function withoutTerminalLogs(): static
    {
        $this->terminalLogsEnabled = false;

        return $this;
    }

    /**
     * Configure navigation for the Terminal Logs resource.
     */
    public function terminalLogsNavigation(
        ?string $icon = null,
        ?string $label = null,
        ?int $sort = null,
        ?string $group = null
    ): static {
        if ($icon !== null) {
            $this->terminalLogsNavigationIcon = $icon;
        }
        if ($label !== null) {
            $this->terminalLogsNavigationLabel = $label;
        }
        if ($sort !== null) {
            $this->terminalLogsNavigationSort = $sort;
        }
        if ($group !== null) {
            $this->terminalLogsNavigationGroup = $group;
        }

        return $this;
    }

    /**
     * Check if Terminal Logs resource is enabled.
     */
    public function isTerminalLogsEnabled(): bool
    {
        return $this->terminalLogsEnabled;
    }

    /**
     * Get the Terminal Logs navigation icon.
     */
    public function getTerminalLogsNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->terminalLogsNavigationIcon ?? 'heroicon-o-clipboard-document-list';
    }

    /**
     * Get the Terminal Logs navigation label.
     */
    public function getTerminalLogsNavigationLabel(): string
    {
        return $this->terminalLogsNavigationLabel ?? 'Terminal Logs';
    }

    /**
     * Get the Terminal Logs navigation sort.
     */
    public function getTerminalLogsNavigationSort(): ?int
    {
        return $this->terminalLogsNavigationSort ?? 101;
    }

    /**
     * Get the Terminal Logs navigation group.
     */
    public function getTerminalLogsNavigationGroup(): ?string
    {
        return $this->terminalLogsNavigationGroup ?? 'Tools';
    }
}
