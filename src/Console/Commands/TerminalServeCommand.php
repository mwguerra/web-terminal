<?php

declare(strict_types=1);

namespace MWGuerra\WebTerminal\Console\Commands;

use Illuminate\Console\Command;
use MWGuerra\WebTerminal\WebSocket\ReactPhpProvider;

class TerminalServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'terminal:serve
                            {--host= : The host to bind to}
                            {--port= : The port to listen on}';

    /**
     * The console command description.
     */
    protected $description = 'Start the WebSocket server for Ghostty terminal mode';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! class_exists(\React\Socket\SocketServer::class)) {
            $this->error('ReactPHP is not installed. Run: composer require react/socket react/event-loop ratchet/rfc6455');

            return self::FAILURE;
        }

        $host = $this->option('host') ?? config('web-terminal.ghostty.ratchet_host', '127.0.0.1');
        $port = $this->option('port') ?? config('web-terminal.ghostty.ratchet_port', 8090);

        $this->info("Starting WebSocket server on {$host}:{$port}...");
        $this->info('Press Ctrl+C to stop.');

        $provider = new ReactPhpProvider($this->laravel);
        $provider->start($host, (int) $port);

        return self::SUCCESS;
    }
}
