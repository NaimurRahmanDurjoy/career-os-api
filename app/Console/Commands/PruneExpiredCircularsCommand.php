<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobCircular;
use Carbon\Carbon;

class PruneExpiredCircularsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'circulars:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete job circulars whose deadline passed more than 60 days ago';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffDate = Carbon::now()->subDays(60);

        $deletedCount = JobCircular::where('application_deadline', '<', $cutoffDate)->delete();

        $this->info("Successfully pruned {$deletedCount} expired circulars older than 60 days.");

        return Command::SUCCESS;
    }
}
