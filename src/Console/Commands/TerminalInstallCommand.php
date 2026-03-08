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
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class TerminalInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'terminal:install
                            {--config : Publish the configuration file}
                            {--migration : Publish the database migration}
                            {--views : Publish Blade views for customization}
                            {--migrate : Run migration after publishing}
                            {--with-tenant : Include tenant_id column in migration}
                            {--no-tenant : Use standard migration without tenant support}
                            {--force : Overwrite existing files}
                            {--page : Generate a custom Terminal page}
                            {--resource : Generate a custom TerminalLogs resource}
                            {--panel= : The Filament panel to generate files for}
                            {--allow-all-commands : Generated page uses allowAllCommands() - DANGEROUS}
                            {--allow-secure-commands : Generated page uses default readonly commands}
                            {--allow-no-commands : Generated page uses empty allowedCommands()}';

    /**
     * The console command description.
     */
    protected $description = 'Install WebTerminal with logging support';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * The selected Filament panel.
     */
    protected ?Panel $panel = null;

    /**
     * Track what was generated for completion message.
     */
    protected bool $generatedPage = false;

    protected bool $generatedResource = false;

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
        $this->displayWelcome();

        // Validate mutually exclusive options
        if ($this->option('with-tenant') && $this->option('no-tenant')) {
            $this->error('Cannot use --with-tenant and --no-tenant together.');

            return self::FAILURE;
        }

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

        // Determine tenant support
        $withTenant = $this->determineTenantSupport();

        // Ask what to install
        $toInstall = $this->askWhatToInstall();

        // Configure panel if generating pages/resources
        if (array_intersect(['page', 'resource'], $toInstall)) {
            if (! $this->configurePanel()) {
                return self::FAILURE;
            }
        }

        // Publish selected components
        $this->publishComponents($toInstall, $withTenant);

        // Ask about running migration
        if (in_array('migration', $toInstall)) {
            $this->handleMigration();
        }

        $this->displayCompletion();

        return self::SUCCESS;
    }

    /**
     * Display welcome message.
     */
    protected function displayWelcome(): void
    {
        $this->newLine();
        note('WebTerminal Installation');
        info('Welcome to WebTerminal installer!');
        info('This will set up terminal logging for your application.');
        $this->newLine();
    }

    /**
     * Determine if tenant support should be included.
     */
    protected function determineTenantSupport(): bool
    {
        if ($this->option('with-tenant')) {
            return true;
        }

        if ($this->option('no-tenant')) {
            return false;
        }

        if ($this->option('no-interaction')) {
            return false;
        }

        // Interactive prompt
        return confirm(
            label: 'Is this a multi-tenant application?',
            default: false,
            yes: 'Yes - Add tenant_id column to logs',
            no: 'No - Standard installation',
        );
    }

    /**
     * Ask what components to install.
     */
    protected function askWhatToInstall(): array
    {
        // Check if any specific install flags are provided
        $hasInstallFlags = $this->option('config')
            || $this->option('migration')
            || $this->option('views')
            || $this->option('page')
            || $this->option('resource');

        // In non-interactive mode with flags, only install what's specified
        if ($this->option('no-interaction') && $hasInstallFlags) {
            $toInstall = [];

            if ($this->option('config')) {
                $toInstall[] = 'config';
            }
            if ($this->option('migration')) {
                $toInstall[] = 'migration';
            }
            if ($this->option('views')) {
                $toInstall[] = 'views';
            }
            if ($this->option('page')) {
                $toInstall[] = 'page';
            }
            if ($this->option('resource')) {
                $toInstall[] = 'resource';
            }

            return $toInstall;
        }

        // In non-interactive mode without flags, install defaults (config + migration)
        if ($this->option('no-interaction')) {
            return ['config', 'migration'];
        }

        // Build defaults for interactive mode based on command options
        $default = ['config', 'migration'];

        if ($this->option('page')) {
            $default[] = 'page';
        }

        if ($this->option('resource')) {
            $default[] = 'resource';
        }

        return multiselect(
            label: 'What would you like to install?',
            options: [
                'config' => 'Configuration file',
                'migration' => 'Database migration',
                'views' => 'Blade views (for customization)',
                'page' => 'Custom Terminal page (in your app)',
                'resource' => 'Custom TerminalLogs resource (in your app)',
            ],
            default: $default,
            required: true,
        );
    }

    /**
     * Configure the Filament panel for page/resource generation.
     */
    protected function configurePanel(): bool
    {
        if (! class_exists(Panel::class)) {
            $this->error('Filament Panel class not found. Please install filament/filament to generate pages/resources.');

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
            label: 'Which panel should the files be created for?',
            options: $panelOptions,
            default: Filament::getDefaultPanel()->getId(),
        );

        $this->panel = $panels[$selectedId];

        return true;
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
     * Get the resource directory and namespace from the panel.
     *
     * @return array{0: string, 1: string} [directory, namespace]
     */
    protected function getResourceDirectoryAndNamespace(): array
    {
        if (! $this->panel) {
            return [
                app_path('Filament/Resources'),
                app()->getNamespace().'Filament\\Resources',
            ];
        }

        $directories = $this->panel->getResourceDirectories();
        $namespaces = $this->panel->getResourceNamespaces();

        // Filter out vendor directories
        foreach ($directories as $index => $dir) {
            if (str($dir)->startsWith(base_path('vendor'))) {
                unset($directories[$index], $namespaces[$index]);
            }
        }

        return [
            Arr::first($directories) ?? app_path('Filament/Resources'),
            Arr::first($namespaces) ?? app()->getNamespace().'Filament\\Resources',
        ];
    }

    /**
     * Publish selected components.
     */
    protected function publishComponents(array $toInstall, bool $withTenant): void
    {
        foreach ($toInstall as $component) {
            match ($component) {
                'config' => $this->publishConfig(),
                'migration' => $this->publishMigration($withTenant),
                'views' => $this->publishViews(),
                'page' => $this->publishTerminalPage(),
                'resource' => $this->publishTerminalLogResource(),
                default => null,
            };
        }
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfig(): void
    {
        $source = __DIR__.'/../../../config/web-terminal.php';
        $destination = config_path('web-terminal.php');

        if ($this->files->exists($destination) && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('Skipped configuration file (already exists).');

                return;
            }

            if (! confirm('Configuration file already exists. Overwrite?', default: false)) {
                warning('Skipped configuration file.');

                return;
            }
        }

        $this->files->copy($source, $destination);
        info('Configuration published to config/web-terminal.php');
    }

    /**
     * Publish migration file.
     */
    protected function publishMigration(bool $withTenant): void
    {
        $stubName = $withTenant
            ? 'create_terminal_logs_table_with_tenant.php.stub'
            : 'create_terminal_logs_table.php.stub';

        $source = __DIR__.'/../../../database/migrations/'.$stubName;
        $timestamp = date('Y_m_d_His');
        $destination = database_path("migrations/{$timestamp}_create_terminal_logs_table.php");

        // Check if migration already exists
        $existingMigrations = glob(database_path('migrations/*_create_terminal_logs_table.php'));
        if (! empty($existingMigrations) && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('Skipped migration file (already exists).');

                return;
            }

            if (! confirm('Terminal logs migration already exists. Publish anyway?', default: false)) {
                warning('Skipped migration file.');

                return;
            }
        }

        $this->files->copy($source, $destination);

        $tenantInfo = $withTenant ? ' (with tenant support)' : '';
        info("Migration published to database/migrations/{$timestamp}_create_terminal_logs_table.php{$tenantInfo}");
    }

    /**
     * Publish views.
     */
    protected function publishViews(): void
    {
        $source = __DIR__.'/../../../resources/views';
        $destination = resource_path('views/vendor/web-terminal');

        if ($this->files->isDirectory($destination) && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('Skipped views (already exist).');

                return;
            }

            if (! confirm('Views directory already exists. Overwrite?', default: false)) {
                warning('Skipped views.');

                return;
            }
        }

        $this->files->copyDirectory($source, $destination);
        info('Views published to resources/views/vendor/web-terminal');
    }

    /**
     * Publish custom Terminal page.
     */
    protected function publishTerminalPage(): void
    {
        [$directory, $namespace] = $this->getPageDirectoryAndNamespace();

        $path = $directory.'/Terminal.php';

        if ($this->files->exists($path) && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('Skipped Terminal page (already exists).');

                return;
            }

            if (! confirm('Terminal page already exists. Overwrite?', default: false)) {
                warning('Skipped Terminal page.');

                return;
            }
        }

        // Determine commands configuration
        $commandsConfig = $this->getCommandsConfig();

        // Generate from stub
        $content = $this->generateFromStub('terminal-page.php.stub', [
            'namespace' => $namespace,
            'class_name' => 'Terminal',
            'navigation_label' => 'Terminal',
            'slug' => 'terminal',
            'terminal_key' => 'app-terminal',
            'commands_config' => $commandsConfig,
        ]);

        $this->files->ensureDirectoryExists($directory);
        $this->files->put($path, $content);

        $this->generatedPage = true;
        info("Terminal page created at {$path}");
    }

    /**
     * Get the commands configuration based on command options.
     */
    protected function getCommandsConfig(): string
    {
        if ($this->option('allow-all-commands')) {
            return "                            ->allowAllCommands()\n                            // WARNING: This allows all commands - use with caution";
        }

        if ($this->option('allow-no-commands')) {
            return "                            ->allowedCommands([])\n                            // TODO: Configure your allowed commands here";
        }

        // Default: secure commands
        return "                            ->allowedCommands([
                                'ls', 'ls *', 'pwd', 'cd', 'cd *', 'whoami', 'date', 'uptime', 'uname', 'uname *',
                                'cat *', 'head *', 'tail *', 'wc *', 'grep *',
                            ])";
    }

    /**
     * Publish custom TerminalLogResource.
     */
    protected function publishTerminalLogResource(): void
    {
        [$resourceDirectory, $resourceNamespace] = $this->getResourceDirectoryAndNamespace();

        $resourcePath = $resourceDirectory.'/TerminalLogResource.php';
        $pagesDirectory = $resourceDirectory.'/TerminalLogResource/Pages';

        if ($this->files->exists($resourcePath) && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('Skipped TerminalLogResource (already exists).');

                return;
            }

            if (! confirm('TerminalLogResource already exists. Overwrite?', default: false)) {
                warning('Skipped TerminalLogResource.');

                return;
            }
        }

        $pagesNamespace = $resourceNamespace.'\\TerminalLogResource\\Pages';

        // Generate resource class
        $resourceContent = $this->generateFromStub('terminal-log-resource.php.stub', [
            'namespace' => $resourceNamespace,
            'pages_namespace' => '\\'.$pagesNamespace, // Leading backslash for absolute class reference
        ]);

        $this->files->ensureDirectoryExists($resourceDirectory);
        $this->files->put($resourcePath, $resourceContent);

        // Generate pages
        $this->files->ensureDirectoryExists($pagesDirectory);

        $listContent = $this->generateFromStub('terminal-log-list-page.php.stub', [
            'namespace' => $pagesNamespace,
            'resource_class' => $resourceNamespace.'\\TerminalLogResource',
        ]);
        $this->files->put($pagesDirectory.'/ListTerminalLogs.php', $listContent);

        $viewContent = $this->generateFromStub('terminal-log-view-page.php.stub', [
            'namespace' => $pagesNamespace,
            'resource_class' => $resourceNamespace.'\\TerminalLogResource',
        ]);
        $this->files->put($pagesDirectory.'/ViewTerminalLog.php', $viewContent);

        $this->generatedResource = true;
        info("TerminalLogResource created at {$resourcePath}");
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
     * Handle migration execution.
     */
    protected function handleMigration(): void
    {
        // If --migrate flag is provided, run migration without asking
        if ($this->option('migrate')) {
            $this->call('migrate');
            info('Migration completed successfully');

            return;
        }

        // In non-interactive mode without --migrate, skip
        if ($this->option('no-interaction')) {
            note('Run `php artisan migrate` when ready.');

            return;
        }

        // Interactive prompt
        $runMigration = confirm(
            label: 'Run database migration now?',
            default: true,
        );

        if ($runMigration) {
            $this->call('migrate');
            info('Migration completed successfully');
        } else {
            note('Run `php artisan migrate` when ready.');
        }
    }

    /**
     * Display completion message.
     */
    protected function displayCompletion(): void
    {
        $this->newLine();
        info('Installation complete!');

        if ($this->generatedPage || $this->generatedResource) {
            $this->displayNextSteps();
        } else {
            note('Configure logging in config/web-terminal.php');
        }

        $this->newLine();
    }

    /**
     * Display next steps for generated files.
     */
    protected function displayNextSteps(): void
    {
        $this->newLine();
        note('Next steps:');
        $this->line('1. Customize the generated files as needed');
        $this->line('2. If using the WebTerminalPlugin, adjust your panel provider:');
        $this->newLine();

        if ($this->generatedPage && $this->generatedResource) {
            $this->line('   // Disable both default pages:');
            $this->line('   WebTerminalPlugin::make()');
            $this->line('       ->withoutTerminalPage()');
            $this->line('       ->withoutTerminalLogs()');
            $this->newLine();
            $this->line('   // Or use empty only() to keep services without pages:');
            $this->line('   WebTerminalPlugin::make()');
            $this->line('       ->only([])');
        } elseif ($this->generatedPage) {
            $this->line('   // Disable the default Terminal page:');
            $this->line('   WebTerminalPlugin::make()');
            $this->line('       ->withoutTerminalPage()');
            $this->newLine();
            $this->line('   // Or use only() to keep just TerminalLogs from the plugin:');
            $this->line('   WebTerminalPlugin::make()');
            $this->line('       ->only([');
            $this->line('           \\MWGuerra\\WebTerminal\\Filament\\Resources\\TerminalLogResource::class,');
            $this->line('       ])');
        } elseif ($this->generatedResource) {
            $this->line('   // Disable the default TerminalLogs:');
            $this->line('   WebTerminalPlugin::make()');
            $this->line('       ->withoutTerminalLogs()');
            $this->newLine();
            $this->line('   // Or use only() to keep just Terminal page from the plugin:');
            $this->line('   WebTerminalPlugin::make()');
            $this->line('       ->only([');
            $this->line('           \\MWGuerra\\WebTerminal\\Filament\\Pages\\Terminal::class,');
            $this->line('       ])');
        }
    }
}
