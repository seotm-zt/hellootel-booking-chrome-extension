<?php

namespace App\Console\Commands;

use App\Models\ExtensionPageReport;
use Illuminate\Console\Command;

class PrunePageReports extends Command
{
    protected $signature   = 'extension:prune-page-reports {--days=60 : Delete reports older than this many days}';
    protected $description = 'Delete old extension page reports';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $deleted = ExtensionPageReport::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} page report(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
