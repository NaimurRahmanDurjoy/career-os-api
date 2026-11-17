<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CircularAggregatorService;

class SyncJobCircularsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'circulars:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically fetch and synchronize latest Govt, Bank, Corporate and Remote Job Circulars';

    /**
     * Execute the console command.
     */
    public function handle(CircularAggregatorService $service)
    {
        $this->info('Starting multi-channel job circular aggregation...');

        $stats = $service->syncAll();

        $this->line("--------------------------------------------------");
        $this->info("✓ Remote Tech Jobs synced: {$stats['remote']}");
        $this->info("✓ Govt Circulars synced:    {$stats['govt']}");
        $this->info("✓ Banking Circulars synced: {$stats['bank']}");
        $this->info("✓ Corporate/NGO synced:     {$stats['private']}");
        $this->line("--------------------------------------------------");
        $this->info("All job circular channels synchronized successfully!");

        return Command::SUCCESS;
    }
}
