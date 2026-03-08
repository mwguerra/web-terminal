<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Console\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class TerminalMakePageCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'terminal:make-page
                            {name? : The name of the terminal page class}
                            {--panel= : The Filament panel to create the page for}
                            {--key= : The terminal identifier key}
                            {--allow-all-commands : Allow all commands (DANGEROUS)}
                            {--allow-secure-commands : Allow only safe readonly commands (default)}
                            {--allow-no-commands : Allow no commands (configure manually)}
                            {--force : Overwrite existing file}';

    /**
     * The console command description.
     */
    protected $description = 'Create a custom Terminal page for a Filament panel';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * The selected Filament panel.
     */
    protected ?Panel $panel = null;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Validate command permission options
        $commandOptions = array_filter([
            $this->option('allow-all-commands'),
            $this->option('allow-secure-commands'),
            $this->option('allow-no-commands'),
        ]);

        if (count($commandOptions) > 1) {
            $this->error('Cannot use multiple --allow-* options together.');

            return self::FAILURE;
        }

        // Configure panel
        if (! $this->configurePanel()) {
            return self::FAILURE;
        }

        // Get page name
        $name = $this->getPageName();

        // Get terminal key
        $key = $this->getTerminalKey($name);

        // Get command permissions
        $commandPermission = $this->getCommandPermission();

        // Generate the page
        if (! $this->generatePage($name, $key, $commandPermission)) {
            return self::FAILURE;
        }

        $this->displayCompletion($name);

        return self::SUCCESS;
    }

    /**
     * Configure the Filament panel.
     */
    protected function configurePanel(): bool
    {
        if (! class_exists(Panel::class)) {
            $this->error('Filament Panel class not found. Please install filament/filament.');

            return false;
        }

        $panelId = $this->option('panel');

        if (filled($panelId)) {
            $this->panel = Filament::getPanel($panelId, isStrict: false);

            if (! $this->panel) {
                $this->error("Panel '{$panelId}' not found.");

                return false;
            }

            return true;
        }

        $panels = Filament::getPanels();

        if (count($panels) === 0) {
            warning('No Filament panels found. Using default paths.');
            $this->panel = null;

            return true;
        }

        if (count($panels) === 1) {
            $this->panel = Arr::first($panels);
            info("Using panel: {$this->panel->getId()}");

            return true;
        }

        if ($this->option('no-interaction')) {
            $this->panel = Filament::getDefaultPanel();

            return true;
        }

        // Multiple panels - interactive selection
        $panelOptions = [];
        foreach ($panels as $panel) {
            $panelOptions[$panel->getId()] = $panel->getId();
        }

        $selectedId = select(
            label: 'Which panel should the page be created for?',
            options: $panelOptions,
            default: Filament::getDefaultPanel()->getId(),
        );

        $this->panel = $panels[$selectedId];

        return true;
    }

    /**
     * Get the page name.
     */
    protected function getPageName(): string
    {
        $name = $this->argument('name');

        if (filled($name)) {
            return $name;
        }

        if ($this->option('no-interaction')) {
            return 'Terminal';
        }

        return text(
            label: 'What should the page class be named?',
            placeholder: 'Terminal',
            default: 'Terminal',
            required: true,
            hint: 'This will be the PHP class name (e.g., Terminal, ServerTerminal)',
        );
    }

    /**
     * Get the terminal key identifier.
     */
    protected function getTerminalKey(string $name): string
    {
        $key = $this->option('key');

        if (filled($key)) {
            return $key;
        }

        // Generate default key from name
        $defaultKey = str($name)
            ->kebab()
            ->append('-terminal')
            ->toString();

        if ($this->option('no-interaction')) {
            return $defaultKey;
        }

        return text(
            label: 'What should the terminal identifier key be?',
            placeholder: $defaultKey,
            default: $defaultKey,
            required: true,
            hint: 'This key is used for logging and identifying this terminal instance',
        );
    }

    /**
     * Get the command permission setting.
     */
    protected function getCommandPermission(): string
    {
        if ($this->option('allow-all-commands')) {
            return 'all';
        }

        if ($this->option('allow-no-commands')) {
            return 'none';
        }

        if ($this->option('allow-secure-commands')) {
            return 'secure';
        }

        if ($this->option('no-interaction')) {
            return 'secure';
        }

        $choice = select(
            label: 'Which commands should this terminal allow?',
            options: [
                'secure' => 'Safe readonly commands (ls, pwd, cat, grep, etc.) - Recommended',
                'none' => 'No commands (configure manually in the generated file)',
                'all' => 'All commands (DANGEROUS - use with caution)',
            ],
            default: 'secure',
        );

        return $choice;
    }

    /**
     * Get the page directory and namespace from the panel.
     *
     * @return array{0: string, 1: string} [directory, namespace]
     */
    protected function getPageDirectoryAndNamespace(): array
    {
        if (! $this->panel) {
            return [
                app_path('Filament/Pages'),
                app()->getNamespace().'Filament\\Pages',
            ];
        }

        $directories = $this->panel->getPageDirectories();
        $namespaces = $this->panel->getPageNamespaces();

        // Filter out vendor directories
        foreach ($directories as $index => $dir) {
            if (str($dir)->startsWith(base_path('vendor'))) {
                unset($directories[$index], $namespaces[$index]);
            }
        }

        return [
            Arr::first($directories) ?? app_path('Filament/Pages'),
            Arr::first($namespaces) ?? app()->getNamespace().'Filament\\Pages',
        ];
    }

    /**
     * Generate the terminal page.
     */
    protected function generatePage(string $name, string $key, string $commandPermission): bool
    {
        [$directory, $namespace] = $this->getPageDirectoryAndNamespace();

        $path = $directory.'/'.$name.'.php';

        if ($this->files->exists($path) && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                $this->error("Page {$name} already exists. Use --force to overwrite.");

                return false;
            }

            if (! confirm("Page {$name} already exists. Overwrite?", default: false)) {
                warning('Cancelled.');

                return false;
            }
        }

        // Generate commands configuration
        $commandsConfig = $this->getCommandsConfig($commandPermission);

        // Generate navigation label and slug from class name
        $navigationLabel = str($name)->headline()->toString();
        $slug = str($name)->kebab()->toString();

        // Generate from stub
        $content = $this->generateFromStub('terminal-page.php.stub', [
            'namespace' => $namespace,
            'class_name' => $name,
            'navigation_label' => $navigationLabel,
            'slug' => $slug,
            'terminal_key' => $key,
            'commands_config' => $commandsConfig,
        ]);

        $this->files->ensureDirectoryExists($directory);
        $this->files->put($path, $content);

        info("Terminal page created: {$path}");

        return true;
    }

    /**
     * Get the commands configuration based on permission level.
     */
    protected function getCommandsConfig(string $permission): string
    {
        return match ($permission) {
            'all' => "                            ->allowAllCommands()\n                            // WARNING: This allows all commands - use with caution",
            'none' => "                            ->allowedCommands([])\n                            // TODO: Configure your allowed commands here",
            default => "                            ->allowedCommands([
                                'ls', 'ls *', 'pwd', 'cd', 'cd *', 'whoami', 'date', 'uptime', 'uname', 'uname *',
                                'cat *', 'head *', 'tail *', 'wc *', 'grep *',
                            ])",
        };
    }

    /**
     * Generate content from a stub file.
     */
    protected function generateFromStub(string $stubName, array $replacements): string
    {
        $stubPath = __DIR__.'/../../../stubs/'.$stubName;
        $content = $this->files->get($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{ '.$key.' }}', $value, $content);
        }

        return $content;
    }

    /**
     * Display completion message.
     */
    protected function displayCompletion(string $name): void
    {
        $this->newLine();
        note('Next steps:');
        $this->line('1. Customize the terminal configuration in the generated page');
        $this->line('2. If using WebTerminalPlugin, disable its default Terminal page:');
        $this->newLine();
        $this->line('   WebTerminalPlugin::make()');
        $this->line('       ->withoutTerminalPage()');
        $this->newLine();
        $this->line('   Or use only() to keep just TerminalLogs from the plugin:');
        $this->newLine();
        $this->line('   WebTerminalPlugin::make()');
        $this->line('       ->only([');
        $this->line('           \\MWGuerra\\WebTerminal\\Filament\\Resources\\TerminalLogResource::class,');
        $this->line('       ])');
        $this->newLine();
    }
}
